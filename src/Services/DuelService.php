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

    // 1) Récup plays dans l’ordre (tu as déjà ce repo)
    $plays = $this->playRepo->findBy(
        ['duel' => $duel],
        ['roundIndex' => 'ASC', 'submittedAt' => 'ASC']
    );

    if (count($plays) === 0) {
        // Rien joué => on clos quand même pour ne pas “rejouer par erreur”
        $duel->setStatus(Game4Duel::STATUS_RESOLVED)
             ->setWinner(null)
             ->setResolvedAt(new \DateTimeImmutable());
        $A->setLockedInDuel(false);
        $B->setLockedInDuel(false);
        $em->flush();

        return (object)[
            'winnerId' => null,
            'messageForA' => "Aucune carte jouée.",
            'messageForB' => "Aucune carte jouée.",
            'logs' => ['Aucune carte jouée.'],
            'effects' => [],
            'patch' => ['A' => ['handDelta'=>['add'=>[], 'remove'=>[]]], 'B' => ['handDelta'=>['add'=>[], 'remove'=>[]]]]
        ];
    }

    $logs = [];
    $effects = [];
    $patchA = ['handDelta'=>['add'=>[], 'remove'=>[]], 'isZombie'=>null, 'isEliminated'=>null];
    $patchB = ['handDelta'=>['add'=>[], 'remove'=>[]], 'isZombie'=>null, 'isEliminated'=>null];

    // 2) Carte spéciale la plus récente (si plusieurs, on applique la dernière)
    $lastSpecial = null;
    foreach ($plays as $p) {
        if (in_array($p->getCardType(), ['ZOMBIE','SHOTGUN','VACCINE'], true)) {
            $lastSpecial = $p;
        }
    }

    // Helpers locaux
    $highestNumPlayed = function(Game4Player $p) use ($plays): ?int {
        $max = null;
        foreach ($plays as $pl) {
            if ($pl->getPlayer()->getId() === $p->getId() && $pl->getCardType()==='NUM') {
                $v = (int)$pl->getNumValue();
                $max = $max === null ? $v : max($max, $v);
            }
        }
        return $max;
    };
    $sumNums = function(Game4Player $p) use ($plays): int {
        $s = 0;
        foreach ($plays as $pl) {
            if ($pl->getPlayer()->getId() === $p->getId() && $pl->getCardType()==='NUM') {
                $s += (int)$pl->getNumValue();
            }
        }
        return $s;
    };
    $giveSpecific = function(Game4Game $game, Game4Player $to, string $code) use ($em, &$logs, &$effects, &$patchA, &$patchB, $A, $B) {
        // Essaie depuis le deck sinon “forge” (bonus)
        $def = $em->getRepository(Game4CardDef::class)->findOneBy(['code' => $code]);
        if (!$def) return;

        $card = (new Game4Card())
            ->setGame($game)
            ->setDef($def)
            ->setZone(Game4Card::ZONE_HAND)
            ->setOwner($to)
            ->setToken(hash('sha256', uniqid('bonus', true)));
        $em->persist($card);
        $effects[] = ['type'=>'GAIN_CARD','playerId'=>$to->getId(),'code'=>$code];
        $logs[] = $to->getName()." reçoit une carte ".$code.".";
        $patch = ($to->getId()===$A->getId()) ? $patchA : $patchB;
        $patch['handDelta']['add'][] = ['code'=>$code];
        if ($to->getId()===$A->getId()) $patchA = $patch; else $patchB = $patch;
    };
    $removeOneZombieFromHand = function(Game4Player $p) use ($em, &$logs, &$effects, &$patchA, &$patchB, $A, $B) {
        foreach ($p->getCards() as $c) {
            if ($c->getZone()===Game4Card::ZONE_HAND && $c->getDef()->getCode()==='ZOMBIE') {
                $c->setZone(Game4Card::ZONE_DISCARD);
                $effects[] = ['type'=>'LOSE_CARD','playerId'=>$p->getId(),'code'=>'ZOMBIE'];
                $logs[] = $p->getName()." perd une carte ZOMBIE.";
                $patch = ($p->getId()===$A->getId()) ? $patchA : $patchB;
                $patch['handDelta']['remove'][] = ['code'=>'ZOMBIE'];
                if ($p->getId()===$A->getId()) $patchA = $patch; else $patchB = $patch;
                return;
            }
        }
    };
    $loseHighestNumPlayedInThisDuel = function(Game4Player $p) use ($em, &$logs, &$effects, &$patchA, &$patchB, $A, $B, $highestNumPlayed) {
        $max = $highestNumPlayed($p);
        if ($max===null) return;
        // On journalise la perte (la carte “jouée” est déjà en table, on simule l’effet de perte)
        $effects[] = ['type'=>'LOSE_NUM_HIGHEST','playerId'=>$p->getId(),'numValue'=>$max];
        $logs[] = $p->getName()." perd sa carte NUM la plus élevée jouée (".$max.").";
        // Pas de retrait de main (elle n'est plus en main), mais on peut afficher un effet UI côté client
    };
    $transferHighestNumPlayedToOpponent = function(Game4Player $from, Game4Player $to) use (&$logs, &$effects, $highestNumPlayed) {
        $max = $highestNumPlayed($from);
        if ($max===null) return;
        $effects[] = ['type'=>'TRANSFER_NUM_HIGHEST','from'=>$from->getId(),'to'=>$to->getId(),'numValue'=>$max];
        $logs[] = "Transfert de la plus haute NUM jouée (".$max.") de ".$from->getName()." vers ".$to->getName().".";
    };
    $eliminate = function(Game4Player $p) use (&$logs, &$effects, &$patchA, &$patchB, $A, $B) {
        $p->setEliminated(true);
        $effects[] = ['type'=>'ELIMINATION','playerId'=>$p->getId()];
        $logs[] = $p->getName()." est éliminé.";
        if ($p->getId()===$A->getId()) $patchA['isEliminated'] = true; else $patchB['isEliminated'] = true;
    };
    $setZombie = function(Game4Player $p, bool $z) use (&$logs, &$effects, &$patchA, &$patchB, $A, $B) {
        $p->setZombie($z);
        $effects[] = ['type'=>'STATE_CHANGE','playerId'=>$p->getId(),'isZombie'=>$z];
        $logs[] = $p->getName()." devient ".($z?'zombie':'humain').".";
        if ($p->getId()===$A->getId()) $patchA['isZombie'] = $z; else $patchB['isZombie'] = $z;
    };

    $game = $duel->getGame();
    $winner = null;

    // 3) Appliquer les règles spéciales si présentes
    if ($lastSpecial) {
        $actor = $lastSpecial->getPlayer();
        $target = ($actor->getId()===$A->getId()) ? $B : $A;

        switch ($lastSpecial->getCardType()) {
            case 'ZOMBIE':
                if (!$target->isZombie()) {
                    // L'adversaire devient zombie et +1 ZOMBIE pour chacun
                    $setZombie($target, true);
                    $giveSpecific($game, $actor, 'ZOMBIE');
                    $giveSpecific($game, $target, 'ZOMBIE');
                    // Pas de vainqueur imposé par la carte : on tranche par impact ? La règle dit uniquement side-effect
                } else {
                    // Adversaire déjà zombie ? le joueur perd sa NUM la plus élevée jouée dans CE duel
                    $loseHighestNumPlayedInThisDuel($actor);
                }
                break;

            case 'SHOTGUN':
                if ($target->isZombie()) {
                    // Élimination du zombie
                    $eliminate($target);
                } else {
                    // Ciblé humain : perd sa carte ZOMBIE (si en main) + sa NUM la plus élevée jouée
                    $removeOneZombieFromHand($target);
                    $loseHighestNumPlayedInThisDuel($target);
                }
                break;

            case 'VACCINE':
                if ($target->isZombie()) {
                    // Le zombie redevient humain, gagne un SHOTGUN, perd sa ZOMBIE
                    $setZombie($target, false);
                    $giveSpecific($game, $target, 'SHOTGUN');
                    $removeOneZombieFromHand($target);
                } else {
                    // Vaccin inutile : le joueur perd son VACCINE + transfert de sa NUM la plus élevée à l’adversaire
                    $effects[] = ['type'=>'LOSE_CARD','playerId'=>$actor->getId(),'code'=>'VACCINE'];
                    $logs[] = $actor->getName()." perd sa carte VACCINE (usage inutile).";
                    $transferHighestNumPlayedToOpponent($actor, $target);
                }
                break;
        }
    }

    // 4) Si pas de spéciale, départage par somme des NUM (règle 5)
    if (!$lastSpecial) {
        $sumA = $sumNums($A);
        $sumB = $sumNums($B);
        if ($sumA > $sumB) {
            $winner = $A;
            $loseHighestNumPlayedInThisDuel($B);
        } elseif ($sumB > $sumA) {
            $winner = $B;
            $loseHighestNumPlayedInThisDuel($A);
        } else {
            // Égalité : pas de vainqueur, pas d'effet supplémentaire
            $logs[] = "Égalité sur la somme des NUM (".$sumA." = ".$sumB.").";
        }
    }

    // 5) Close duel
    if ($winner) {
        $duel->setWinner($winner);
        $logs[] = $winner->getName()." remporte le duel.";
    }
    $duel->setStatus(Game4Duel::STATUS_RESOLVED)
         ->setResolvedAt(new \DateTimeImmutable());

    // Déverrouille
    $A->setLockedInDuel(false);
    $B->setLockedInDuel(false);

    $em->flush();

    $winnerId = $winner ? $winner->getId() : null;
    return (object)[
        'winnerId' => $winnerId,
        'messageForA' => $winnerId === $A->getId() ? "? Vous avez gagné !" : ($winnerId===null ? "Égalité." : "? Vous avez perdu..."),
        'messageForB' => $winnerId === $B->getId() ? "? Vous avez gagné !" : ($winnerId===null ? "Égalité." : "? Vous avez perdu..."),
        'logs'   => $logs,
        'effects'=> $effects,
        'patch'  => ['A'=>$patchA, 'B'=>$patchB],
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
