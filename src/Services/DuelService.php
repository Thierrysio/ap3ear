<?php

namespace App\Services;

use App\Entity\Game4Card;
use App\Entity\Game4CardDef;
use App\Entity\Game4Duel;
use App\Entity\Game4DuelPlay;
use App\Entity\Game4Game;
use App\Entity\Game4Player;
use App\Repository\Game4CardRepository;
use App\Repository\Game4DuelPlayRepository;
use App\Repository\Game4PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de résolution de duel :
 * - Vérifie la présence des 2 coups
 * - Applique les règles (SHOTGUN / VACCINE / ZOMBIE / valeur numérique)
 * - Met à jour l'état (rôles, vies, transferts de cartes éventuels)
 * - Défausse les cartes jouées
 * - Déverrouille les joueurs & nettoie la bannière
 * - Écrit les logs et renvoie { winnerId, logs[] }
 */
final class DuelService
{
    public function __construct(
        private Game4DuelPlayRepository $playRepo,
        private Game4CardRepository $cardRepo
    ) {}

    public function bothSubmitted(Game4Duel $duel): bool
    {
        return \count($this->playRepo->findAllByDuel($duel)) >= 2;
    }

    public function hasSubmitted(Game4Duel $duel, Game4Player $player): bool
    {
        return (bool)$this->playRepo->findOneByDuelAndPlayer($duel, $player);
    }

/**
 * Résout le duel selon les règles et retourne {winnerId, logs[], pointsDelta, wonCardCode, wonCardLabel}.
 * IMPORTANT : le contrôleur doit déjà avoir persisté les Game4DuelPlay correspondants.
 */
public function resolve(Game4Duel $duel, EntityManagerInterface $em): object
{
    $A = $duel->getPlayerA();
    $B = $duel->getPlayerB();

    $plays = $this->playRepo->findBy(
        ['duel' => $duel],
        ['roundIndex' => 'ASC', 'submittedAt' => 'ASC']
    );

    if (count($plays) === 0) {
        $logs = ['Aucune carte jouée.'];
        // Marquage “résolu sans effet” pour éviter de rejouer ce duel par erreur
        $duel->setStatus(Game4Duel::STATUS_RESOLVED)
             ->setWinner(null)
             ->setResolvedAt(new \DateTimeImmutable());
        $A->setLockedInDuel(false);
        $B->setLockedInDuel(false);
        if ($B->getIncomingDuel()?->getId() === $duel->getId()) {
            $B->setIncomingDuel(null);
        }
        if (method_exists($duel, 'setLogsArray')) {
            $duel->setLogsArray($logs);
        }
        $em->flush();
        return (object)[
            'winnerId'     => null,
            'logs'         => $logs,
            'pointsDelta'  => 0,
            'wonCardCode'  => null,
            'wonCardLabel' => null,
        ];
    }

    $logs = [];
    $winner = null;
    $loser  = null;
    $pointsDelta  = 0;     // Par défaut 0 (cartes spéciales n’affectent pas le score numérique)
    $wonCardCode  = null;
    $wonCardLabel = null;

    // --- 1) Détecter la DERNIÈRE carte spéciale jouée (si plusieurs)
    $lastSpecial = null;
    foreach ($plays as $play) {
        $type = strtoupper((string) $play->getCardType());
        if (in_array($type, ['ZOMBIE', 'SHOTGUN', 'VACCINE'], true)) {
            $lastSpecial = $play;
        }
    }

    // --- 2) Si spéciale présente : appliquer sa règle
    if ($lastSpecial) {
        $actor    = $lastSpecial->getPlayer();
        $opponent = $duel->getOpponentFor($actor);
        $type     = strtoupper((string) $lastSpecial->getCardType());

        switch ($type) {
            case 'SHOTGUN':
                if ($opponent->getRole() === Game4Player::ROLE_ZOMBIE) {
                    // Élimine le zombie + transfère sa main
                    $opponent->setIsAlive(false);
                    $this->transferAllHand($opponent, $actor, $em);
                    $logs[] = sprintf("%s tue %s (zombie) avec SHOTGUN et récupère sa main.",
                        $actor->getName(), $opponent->getName());
                    $winner = $actor;
                } else {
                    // Contre humain : il doit donner sa carte numérique POSÉE la plus élevée
                    $give = $this->highestPostedNumericCard($duel, $opponent);
                    if ($give) {
                        $give->setOwner($actor)->setZone(Game4Card::ZONE_HAND);
                        $wonCardCode  = $give->getDef()->getCode();
                        $wonCardLabel = $give->getDef()->getLabel();
                        $logs[] = sprintf("%s (humain) donne sa carte posée la plus élevée (%s) à %s.",
                            $opponent->getName(), $wonCardLabel, $actor->getName());
                    } else {
                        $logs[] = sprintf("%s n'avait aucune carte numérique posée à donner.", $opponent->getName());
                    }
                    $winner = $actor;
                }
                break;

            case 'VACCINE':
                if ($opponent->getRole() === Game4Player::ROLE_ZOMBIE) {
                    $opponent->setRole(Game4Player::ROLE_HUMAN);
                    $logs[] = sprintf("%s vaccine %s : il redevient humain.",
                        $actor->getName(), $opponent->getName());
                    // +1 SHOTGUN chacun
                    $this->giveSpecificCardTo('SHOTGUN', $duel->getGame(), $actor, $em, $logs);
                    $this->giveSpecificCardTo('SHOTGUN', $duel->getGame(), $opponent, $em, $logs);
                } else {
                    $logs[] = "VACCINE sans cible zombie : aucun effet.";
                }
                $winner = $actor;
                break;

            case 'ZOMBIE':
                if ($actor->getRole() === Game4Player::ROLE_ZOMBIE
                    && $opponent->getRole() === Game4Player::ROLE_HUMAN) {
                    $opponent->setRole(Game4Player::ROLE_ZOMBIE);
                    $logs[] = sprintf("%s infecte %s : %s devient zombie.",
                        $actor->getName(), $opponent->getName(), $opponent->getName());
                    // +1 ZOMBIE chacun
                    $this->giveSpecificCardTo('ZOMBIE', $duel->getGame(), $actor, $em, $logs);
                    $this->giveSpecificCardTo('ZOMBIE', $duel->getGame(), $opponent, $em, $logs);
                } else {
                    $logs[] = "ZOMBIE joué sans infection (conditions non remplies).";
                }
                $winner = $actor;
                break;
        }

        // Toutes les cartes jouées partent en défausse
        foreach ($plays as $p) {
            $this->discardPlayedCard($p, $em);
        }
    }
    // --- 3) Sinon : résolution par somme des valeurs numériques (4 cartes chacun)
    else {
        $sumA = $this->sumNumericForPlayer($plays, $A);
        $sumB = $this->sumNumericForPlayer($plays, $B);

        if ($sumA === $sumB) {
            $logs[] = sprintf("Égalité parfaite (%d = %d). Aucun point gagné/perdu, aucune carte remportée.", $sumA, $sumB);
            $winner = null;
            $pointsDelta = 0;
        } elseif ($sumA > $sumB) {
            $winner = $A;
            $loser  = $B;
            $pointsDelta = $sumA - $sumB;
            $logs[] = sprintf("%s gagne par somme (%d > %d).", $A->getName(), $sumA, $sumB);
            $logs[] = sprintf("Points : +%d pour %s, -%d pour %s.",
                $pointsDelta, $A->getName(), $pointsDelta, $B->getName());
        } else {
            $winner = $B;
            $loser  = $A;
            $pointsDelta = $sumB - $sumA;
            $logs[] = sprintf("%s gagne par somme (%d > %d).", $B->getName(), $sumB, $sumA);
            $logs[] = sprintf("Points : +%d pour %s, -%d pour %s.",
                $pointsDelta, $B->getName(), $pointsDelta, $A->getName());
        }

        // Le perdant cède sa carte numérique POSÉE la plus élevée
        if ($loser && $winner) {
            $give = $this->highestPostedNumericCard($duel, $loser);
            if ($give) {
                $give->setOwner($winner)->setZone(Game4Card::ZONE_HAND);
                $wonCardCode  = $give->getDef()->getCode();
                $wonCardLabel = $give->getDef()->getLabel();
                $logs[] = sprintf("%s remporte la carte posée la plus élevée de %s : %s.",
                    $winner->getName(), $loser->getName(), $wonCardLabel);
            } else {
                $logs[] = sprintf("%s n'avait aucune carte numérique posée à céder.", $loser->getName());
            }
        }

        // Toutes les cartes jouées partent en défausse
        foreach ($plays as $p) {
            $this->discardPlayedCard($p, $em);
        }
    }

    // --- 4) Finaliser le duel
    $duel->setStatus(Game4Duel::STATUS_RESOLVED)
         ->setWinner($winner)
         ->setResolvedAt(new \DateTimeImmutable());

    // Déverrouiller les joueurs
    $A->setLockedInDuel(false);
    $B->setLockedInDuel(false);
    if ($B->getIncomingDuel()?->getId() === $duel->getId()) {
        $B->setIncomingDuel(null);
    }

    // (Optionnel) stocker les logs si le champ existe
    if (method_exists($duel, 'setLogsArray')) {
        $duel->setLogsArray($logs);
    }

    $em->flush();

    return (object)[
        'winnerId'     => $winner?->getId(),
        'logs'         => $logs,
        'pointsDelta'  => $pointsDelta,   // 0 pour les résolutions par spéciale ou égalité
        'wonCardCode'  => $wonCardCode,   // null si aucune
        'wonCardLabel' => $wonCardLabel,  // null si aucune
    ];
}


// NOUVELLE METHODE : Vérifier si une carte spéciale a été jouée
private function hasSpecialCardInPlays(array $plays): bool
{
    $specialTypes = ['ZOMBIE', 'VACCINE', 'SHOTGUN'];
    
    foreach ($plays as $play) {
        if (in_array(strtoupper($play->getCardType()), $specialTypes)) {
            return true;
        }
    }
    
    return false;
}

// NOUVELLE METHODE : Déterminer le gagnant
private function determineWinner($A, $B, $ta, $tb, $cardCodeA, $cardCodeB, &$logs)
{
    $isZombie = fn($p) => $p->getRole() === Game4Player::ROLE_ZOMBIE;
    
    // Logique des cartes spéciales (identique à votre code existant)
    if ($ta === 'SHOTGUN' && $isZombie($B)) {
        $this->killAndLoot($B, $A, $this->em, $logs, "{$A->getName()} tue {$B->getName()} avec SHOTGUN");
        return $A->getId();
    }
    // ... (reprendre toute votre logique spéciale existante)
    
    // Si pas de carte spéciale, comparer les valeurs numériques
    $va = $this->valueFromCode($cardCodeA);
    $vb = $this->valueFromCode($cardCodeB);
    
    if ($va > $vb) {
        $logs[] = "{$A->getName()} gagne avec {$va} contre {$vb}";
        return $A->getId();
    } elseif ($vb > $va) {
        $logs[] = "{$B->getName()} gagne avec {$vb} contre {$va}";
        return $B->getId();
    } else {
        $logs[] = "Égalité {$va}-{$vb} - Le duel continue";
        return null; // Égalité = pas de gagnant
    }

    // ===== Défausse des cartes jouées =====
    $this->discardPlayedCard($pa, $em);
    $this->discardPlayedCard($pb, $em);

    // ===== Finalise duel =====
    $duel->setStatus(Game4Duel::STATUS_RESOLVED)
         ->setWinner($winner) // ? on pose l'ENTITÉ gagnante (ou null en cas d'égalité)
         ->setResolvedAt(new \DateTimeImmutable())
         ->setLogsArray($logs);

    // Déverrouille joueurs & nettoie bannière
    $A->setLockedInDuel(false);
    $B->setLockedInDuel(false);
    if ($B->getIncomingDuel()?->getId() === $duel->getId()) {
        $B->setIncomingDuel(null);
    }

    $em->flush();

    return (object)[
        'winnerId' => $winner?->getId(),
        'logs'     => $logs,
    ];
}


    // ===================== Helpers règles =====================

    /** Assure que $plays[0] est le coup de A, $plays[1] celui de B */
    private function alignPlays(Game4Duel $duel, array $plays): array
    {
        /** @var Game4DuelPlay $p0 */
        /** @var Game4DuelPlay $p1 */
        $p0 = $plays[0] ?? null;
        $p1 = $plays[1] ?? null;
        if (!$p0 || !$p1) return [$p0, $p1];

        $aId = $duel->getPlayerA()->getId();
        if ($p0->getPlayer()->getId() === $aId) {
            return [$p0, $p1];
        }
        return [$p1, $p0];
    }

    private function killAndLoot(Game4Player $victim, Game4Player $looter, EntityManagerInterface $em, array &$logs, string $msg): void
    {
        $victim->setIsAlive(false);
        $this->transferAllHand($from = $victim, $to = $looter, $em);
        $logs[] = $msg;
    }

    /** Attribue une carte Infection à $p (s’il existe un Game4CardDef 'ZOMBIE'). */
    private function giveZombieCard(Game4Player $p, Game4Game $game, EntityManagerInterface $em, array &$logs): void
    {
        // On cherche un def code 'ZOMBIE'
        /** @var Game4CardDef|null $def */
        $def = $em->getRepository(Game4CardDef::class)->findOneBy(['code' => 'ZOMBIE']);
        if (!$def) return;

        $c = (new Game4Card())
            ->setGame($game)
            ->setDef($def)
            ->setZone(Game4Card::ZONE_HAND)
            ->setOwner($p)
            ->setToken(hash('sha256', uniqid('zmb', true)));
        $em->persist($c);
        $logs[] = "{$p->getName()} reçoit une carte Infection.";
    }

    /** Retire UNE carte d’infection (ZOMBIE) de la main de $p si présente. */
    private function removeOneZombieCardFromHand(Game4Player $p, EntityManagerInterface $em, array &$logs): void
    {
        // On charge les cartes en main et on supprime 1ère carte de type ZOMBIE trouvée
        $hand = $this->cardRepo->findHandByPlayer($p);
        foreach ($hand as $c) {
            if (strtoupper($c->getDef()->getType()) === 'ZOMBIE' || strtoupper($c->getDef()->getCode()) === 'ZOMBIE') {
                $em->remove($c); // supprime la carte
                $logs[] = "Une carte Infection a été retirée de la main de {$p->getName()}.";
                return;
            }
        }
    }

      private function transferAllHand(Game4Player $from, Game4Player $to, EntityManagerInterface $em): void
    {
        $cards = $this->cardRepo->findBy(['owner' => $from, 'zone' => Game4Card::ZONE_HAND]);
        foreach ($cards as $card) {
            $card->setOwner($to);
        }
    }

    /** Défausse la carte jouée (si présent). */
        private function discardPlayedCard(Game4DuelPlay $play, EntityManagerInterface $em): void
    {
        $card = $play->getCard();
        if (!$card) return;

        $card->setOwner(null)->setZone(Game4Card::ZONE_DISCARD);
        $game = $card->getGame();
        $game->incDiscardCount();
    }

    /** Convertit un code carte “7”, “NUM_7”, etc. vers une valeur entière. */
     private function valueFromCode(string $code): int
    {
        if (preg_match('/(\d+)/', $code, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }

    
    private function sumNumeric(array $plays): int
{
    $s = 0;
    foreach ($plays as $p) {
        $t = strtoupper($p->getCardType());
        if ($t === 'NUM' && preg_match('/(\d+)/', $p->getCardCode(), $m)) {
            $s += (int)$m[1];
        }
    }
    return $s;
}

    private function sumNumericForPlayer(array $plays, Game4Player $player): int
    {
        $sum = 0;
        foreach ($plays as $play) {
            if ($play->getPlayer()->getId() === $player->getId()) {
                $value = $this->valueFromCode($play->getCardCode());
                $sum += $value;
            }
        }
        return $sum;
    }
    private function highestPostedNumericCard(Game4Duel $duel, Game4Player $player): ?Game4Card
    {
        $plays = $this->playRepo->findBy(['duel' => $duel, 'player' => $player]);
        $best = null;
        $bestVal = -1;
        
        foreach ($plays as $play) {
            $card = $play->getCard();
            if (!$card) continue;
            
            $def = $card->getDef();
            if (strtoupper($def->getType()) !== 'NUM') continue;
            
            $value = $this->valueFromCode($def->getCode());
            if ($value > $bestVal) {
                $bestVal = $value;
                $best = $card;
            }
        }
        return $best;
    }


    private function giveSpecificCardTo(string $code, Game4Game $game, Game4Player $to, EntityManagerInterface $em, array &$logs): void
    {
        $def = $em->getRepository(Game4CardDef::class)->findOneBy(['code' => $code]);
        if (!$def) return;
        
        $card = (new Game4Card())
            ->setGame($game)
            ->setDef($def)
            ->setZone(Game4Card::ZONE_HAND)
            ->setOwner($to)
            ->setToken(hash('sha256', uniqid('bonus', true)));
        $em->persist($card);
        $logs[] = "{$to->getName()} reçoit une carte {$code}.";
    }

}
