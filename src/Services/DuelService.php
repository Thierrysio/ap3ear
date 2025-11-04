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

public function resolve(Game4Duel $duel, EntityManagerInterface $em): \stdClass
    {
        $result = new \stdClass();
        $result->winnerId = null;
        $result->logs     = [];
        $result->effects  = [];
        $result->patch    = ['A' => [], 'B' => []];

        $game    = $duel->getGame();
        $playerA = $duel->getPlayerA();
        $playerB = $duel->getPlayerB();

        // ?? Si déjà résolu, on ne refait PAS les effets, on renvoie juste l’info
        if ($duel->getStatus() === Game4Duel::STATUS_RESOLVED) {
            $winner = $duel->getWinner();
            $result->winnerId = $winner ? $winner->getId() : null;
            if (method_exists($duel, 'getLogsArray')) {
                $result->logs = $duel->getLogsArray();
            } elseif (method_exists($duel, 'getLogs') && $duel->getLogs()) {
                $result->logs = preg_split("/\r?\n/", $duel->getLogs());
            }
            return $result;
        }

        // ===== Récupérer tous les plays du duel =====
        /** @var Game4DuelPlay[] $plays */
        $plays = $this->playRepo->findAllByDuel($duel);

        // Séparation NUM / spéciales
        $numsByPlayer = [
            $playerA->getId() => [],
            $playerB->getId() => [],
        ];
        $hasSpecial = false;

        foreach ($plays as $play) {
            $type = strtoupper($play->getCardType());
            if ($type === Game4DuelPlay::TYPE_NUM) {
                $numsByPlayer[$play->getPlayer()->getId()][] = $play;
            } else {
                $hasSpecial = true;
            }
        }

        // ?? Ici, on traite d'abord le cas "aucune carte spéciale" (règle 5)
        if (!$hasSpecial) {
            // Sommes des NUM
            $sumA = 0;
            foreach ($numsByPlayer[$playerA->getId()] as $p) {
                $sumA += (int) $p->getNumValue();
            }

            $sumB = 0;
            foreach ($numsByPlayer[$playerB->getId()] as $p) {
                $sumB += (int) $p->getNumValue();
            }

            $result->logs[] = sprintf(
                "Somme des cartes NUM : %s = %d, %s = %d",
                $playerA->getName(), $sumA,
                $playerB->getName(), $sumB
            );

            $winner = null;
            $loser  = null;

            if ($sumA > $sumB) {
                $winner = $playerA;
                $loser  = $playerB;
            } elseif ($sumB > $sumA) {
                $winner = $playerB;
                $loser  = $playerA;
            } else {
                // Égalité -> personne ne perd/gagne de carte
                $result->logs[] = "Égalité : aucune carte n'est transférée.";
                $this->markDuelResolved($duel, null, $result->logs);
                $em->flush();
                return $result;
            }

            $result->winnerId = $winner->getId();
            $result->logs[] = sprintf("Vainqueur du duel : %s", $winner->getName());

            // ===== Trouver la carte NUM la plus haute du perdant =====
            $loserPlays = $numsByPlayer[$loser->getId()] ?? [];
            $highestPlay = null;
            foreach ($loserPlays as $p) {
                if ($highestPlay === null || (int)$p->getNumValue() > (int)$highestPlay->getNumValue()) {
                    $highestPlay = $p;
                }
            }

            $stolenCard = null;
            if ($highestPlay) {
                $stolenCard = $highestPlay->getCard();
                if ($stolenCard instanceof Game4Card) {
                    // Transférer du perdant -> gagnant, en main
                    $stolenCard
                        ->setOwner($winner)
                        ->setZone(Game4Card::ZONE_HAND);
                    $result->logs[] = sprintf(
                        "La carte NUM la plus élevée (%s, valeur %d) est transférée de %s à %s.",
                        $stolenCard->getDef()->getCode(),
                        (int)$highestPlay->getNumValue(),
                        $loser->getName(),
                        $winner->getName()
                    );
                    $em->persist($stolenCard);
                }
            }

            // ===== Remettre toutes les cartes jouées en main =====
            foreach ($plays as $play) {
                $card = $play->getCard();
                if (!$card instanceof Game4Card) {
                    continue;
                }

                // Si c'est la carte volée, on l'a déjà mise chez le vainqueur
                if ($stolenCard && $card->getId() === $stolenCard->getId()) {
                    $card->setZone(Game4Card::ZONE_HAND);
                    continue;
                }

                // Sinon, elle revient dans la main de SON joueur d'origine
                $card
                    ->setOwner($play->getPlayer())
                    ->setZone(Game4Card::ZONE_HAND);
                $em->persist($card);
            }

            // Marquer le duel comme résolu avec gagnant + logs
            $this->markDuelResolved($duel, $winner, $result->logs);
            $em->flush();

            return $result;
        }

        // TODO : gérer ici les cas avec cartes spéciales (ZOMBIE / SHOTGUN / VACCINE)
        // Pour l’instant on log juste et on ne change pas les cartes.
        $result->logs[] = "Résolution avec cartes spéciales non encore implémentée dans DuelService::resolve.";
        $this->markDuelResolved($duel, null, $result->logs);
        $em->flush();

        return $result;
    }

    /**
     * Factorise la mise à jour de l'entité Duel
     */
    private function markDuelResolved(Game4Duel $duel, ?Game4Player $winner, array $logs): void
    {
        if (method_exists($duel, 'markResolved')) {
            $duel->markResolved($winner, $logs);
            return;
        }

        $duel->setStatus(Game4Duel::STATUS_RESOLVED);
        $duel->setWinner($winner);
        if (method_exists($duel, 'setResolvedAt')) {
            $duel->setResolvedAt(new \DateTimeImmutable());
        }
        if (method_exists($duel, 'setLogsArray')) {
            $duel->setLogsArray($logs);
        } elseif (method_exists($duel, 'setLogs')) {
            $duel->setLogs(implode("\n", $logs));
        }
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
