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
use Doctrine\ORM\EntityManagerInterface;
use stdClass;

/**
 * Service responsable de la rsolution des duels du "game 4".
 *
 * Il traduit les combinaisons de cartes (NUM / ZOMBIE / SHOTGUN / VACCINE)
 * en effets mtier : changements de rles, liminations, transferts de cartes
 * et journalise le rsultat dans l'entit {@see Game4Duel}.
 */
final class DuelService
{
    public function __construct(
        private readonly Game4DuelPlayRepository $playRepo,
        private readonly Game4CardRepository $cardRepo,
    ) {
    }

    public function bothSubmitted(Game4Duel $duel): bool
    {
        return \count($this->playRepo->findAllByDuel($duel)) >= 2;
    }

    public function hasSubmitted(Game4Duel $duel, Game4Player $player): bool
    {
        return $this->playRepo->findOneByDuelAndPlayer($duel, $player) !== null;
    }

    public function resolve(Game4Duel $duel, EntityManagerInterface $em): stdClass
    {
        $result = $this->createEmptyResult();

        $game    = $duel->getGame();
        $playerA = $duel->getPlayerA();
        $playerB = $duel->getPlayerB();

        if (!$game || !$playerA || !$playerB) {
            $result->logs[] = 'Duel incohérent : participants manquants.';
            return $result;
        }

        if ($duel->getStatus() === Game4Duel::STATUS_RESOLVED) {
            $result->winnerId = $duel->getWinner()?->getId();
            $result->logs     = method_exists($duel, 'getLogsArray')
                ? $duel->getLogsArray()
                : ($duel->getLogs() ? preg_split('/\r?\n/', (string) $duel->getLogs()) : []);

            return $result;
        }

        $plays = $this->playRepo->findAllByDuel($duel);
        if ($plays === []) {
            // Aucun coup encore enregistr : on laisse le duel en attente.
            $result->logs[] = 'Aucune carte jouée.';
            return $result;
        }

        $lastSpecial = $this->findLastSpecialPlay($plays);

        if ($lastSpecial instanceof Game4DuelPlay) {
            $resolved = $this->applySpecialResolution($duel, $plays, $lastSpecial, $em, $result);
        } else {
            $resolved = $this->applyNumericResolution($duel, $plays, $em, $result);
        }

        if ($resolved) {
            $em->flush();
        }

        return $result;
    }

    private function createEmptyResult(): stdClass
    {
        $result = new stdClass();
        $result->winnerId     = null;
        $result->logs         = [];
        $result->effects      = [];
        $result->patch        = ['A' => [], 'B' => []];
        $result->wonCardCode  = null;
        $result->wonCardLabel = null;
        $result->pointsDelta  = 0;

        return $result;
    }

    private function applySpecialResolution(
        Game4Duel $duel,
        array $plays,
        Game4DuelPlay $specialPlay,
        EntityManagerInterface $em,
        stdClass $result
    ): bool {
        $actor    = $specialPlay->getPlayer();
        $opponent = $actor ? $duel->getOpponentFor($actor) : null;

        if (!$actor || !$opponent) {
            $result->logs[] = "Impossible de déterminer les joueurs du duel.";
            return false;
        }

        $game    = $duel->getGame();
        $logs    = [];
        $effects = [];
        $winner  = $actor;
        $stolen  = null;

        $type = strtoupper((string) $specialPlay->getCardType());

        switch ($type) {
            case Game4DuelPlay::TYPE_SHOTGUN:
                if ($opponent->getRole() === Game4Player::ROLE_ZOMBIE) {
                    if (method_exists($opponent, 'setEliminated')) {
                        $opponent->setEliminated(true);
                    } else {
                        $opponent->setIsAlive(false);
                    }

                    $this->transferAllHand($opponent, $actor);

                    $logs[]    = sprintf('%s élimine %s (zombie) avec SHOTGUN et récupère sa main.', $actor->getName(), $opponent->getName());
                    $effects[] = ['type' => 'kill', 'playerId' => $opponent->getId()];
                } else {
                    $stolen = $this->stealHighestPostedNumeric($duel, $actor, $opponent);
                    if ($stolen) {
                        $def = $stolen->getDef();
                        $logs[] = sprintf(
                            '%s (humain) cède sa meilleure carte (%s) à %s.',
                            $opponent->getName(),
                            $def?->getLabel() ?? $def?->getCode() ?? 'carte',
                            $actor->getName()
                        );
                        $effects[] = [
                            'type'     => 'card_transfer',
                            'from'     => $opponent->getId(),
                            'to'       => $actor->getId(),
                            'cardCode' => $def?->getCode(),
                        ];
                        $result->wonCardCode  = $def?->getCode();
                        $result->wonCardLabel = $def?->getLabel();
                    } else {
                        $logs[] = sprintf("%s n'avait aucune carte numérique à transférer.", $opponent->getName());
                    }
                }
                break;

            case Game4DuelPlay::TYPE_VACCINE:
                if ($opponent->getRole() === Game4Player::ROLE_ZOMBIE) {
                    $opponent->setRole(Game4Player::ROLE_HUMAN);
                    if (method_exists($opponent, 'setZombie')) {
                        $opponent->setZombie(false);
                    }
                    $opponent->setIsAlive(true);

                    if ($this->cardRepo->removeOneZombieCardFromHand($opponent)) {
                        $logs[] = sprintf('Une carte Infection est retirée de la main de %s.', $opponent->getName());
                    }

                    $this->giveSpecificCardTo('SHOTGUN', $game, $actor, $em, $logs);
                    $this->giveSpecificCardTo('SHOTGUN', $game, $opponent, $em, $logs);

                    $logs[]    = sprintf('%s vaccine %s qui redevient humain.', $actor->getName(), $opponent->getName());
                    $effects[] = ['type' => 'role', 'playerId' => $opponent->getId(), 'role' => Game4Player::ROLE_HUMAN];
                } else {
                    $logs[] = 'VACCINE joué sans cible zombie : aucun effet.';
                }
                break;

            case Game4DuelPlay::TYPE_ZOMBIE:
                if ($actor->getRole() === Game4Player::ROLE_ZOMBIE && $opponent->getRole() === Game4Player::ROLE_HUMAN) {
                    $opponent->setRole(Game4Player::ROLE_ZOMBIE);
                    if (method_exists($opponent, 'setZombie')) {
                        $opponent->setZombie(true);
                    }

                    $this->giveSpecificCardTo('ZOMBIE', $game, $actor, $em, $logs);
                    $this->giveSpecificCardTo('ZOMBIE', $game, $opponent, $em, $logs);

                    $logs[]    = sprintf('%s infecte %s qui devient zombie.', $actor->getName(), $opponent->getName());
                    $effects[] = ['type' => 'role', 'playerId' => $opponent->getId(), 'role' => Game4Player::ROLE_ZOMBIE];
                } else {
                    $logs[] = 'ZOMBIE joué mais les conditions dinfection ne sont pas réunies.';
                }
                break;

            default:
                $logs[]  = sprintf('Carte spéciale inconnue : %s.', $type);
                $winner  = null;
        }

        $result->winnerId = $winner?->getId();
        $result->logs     = $logs;
        $result->effects  = $effects;

        $this->discardPlays($plays, $stolen);
        $this->finalizeDuel($duel, $winner, $logs);

        return true;
    }

    private function applyNumericResolution(
        Game4Duel $duel,
        array $plays,
        EntityManagerInterface $em,
        stdClass $result
    ): bool {
        $playerA = $duel->getPlayerA();
        $playerB = $duel->getPlayerB();
        if (!$playerA || !$playerB) {
            $result->logs[] = 'Participants manquants pour le duel.';
            return false;
        }

        $logs    = [];
        $effects = [];

        $sumA = $this->sumNumericForPlayer($plays, $playerA);
        $sumB = $this->sumNumericForPlayer($plays, $playerB);

        $logs[] = sprintf('%s totalise %d points, %s totalise %d points.', $playerA->getName(), $sumA, $playerB->getName(), $sumB);

        if ($sumA === $sumB) {
            $logs[] = "Égalité parfaite : aucune carte n'est transférée.";
            $result->logs    = $logs;
            $result->effects = $effects;

            $this->discardPlays($plays, null);
            $this->finalizeDuel($duel, null, $logs);

            return true;
        }

        $winner = $sumA > $sumB ? $playerA : $playerB;
        $loser  = $winner === $playerA ? $playerB : $playerA;

        $result->winnerId = $winner->getId();
        $logs[] = sprintf('%s remporte le duel.', $winner->getName());

        $stolen = $this->stealHighestPostedNumeric($duel, $winner, $loser);
        if ($stolen) {
            $def = $stolen->getDef();
            $logs[] = sprintf(
                '%s récupère la meilleure carte de %s (%s).',
                $winner->getName(),
                $loser->getName(),
                $def?->getLabel() ?? $def?->getCode() ?? 'carte'
            );
            $effects[] = [
                'type'     => 'card_transfer',
                'from'     => $loser->getId(),
                'to'       => $winner->getId(),
                'cardCode' => $def?->getCode(),
            ];
            $result->wonCardCode  = $def?->getCode();
            $result->wonCardLabel = $def?->getLabel();
        } else {
            $logs[] = sprintf('%s n’avait aucune carte numérique à céder.', $loser->getName());
        }

        $result->logs    = $logs;
        $result->effects = $effects;

        $this->discardPlays($plays, $stolen);
        $this->finalizeDuel($duel, $winner, $logs);

        return true;
    }

    private function sumNumericForPlayer(array $plays, Game4Player $player): int
    {
        $sum = 0;
        foreach ($plays as $play) {
            if (!$play instanceof Game4DuelPlay) {
                continue;
            }
            if ($play->getPlayer()?->getId() !== $player->getId()) {
                continue;
            }
            $sum += $this->valueFromPlay($play);
        }

        return $sum;
    }

    private function valueFromPlay(Game4DuelPlay $play): int
    {
        if ($play->getCardType() !== Game4DuelPlay::TYPE_NUM) {
            return 0;
        }

        $value = $play->getNumValue();
        if ($value !== null) {
            return (int) $value;
        }

        if (preg_match('/(\d+)/', (string) $play->getCardCode(), $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function stealHighestPostedNumeric(Game4Duel $duel, Game4Player $winner, Game4Player $loser): ?Game4Card
    {
        $bestCard = null;
        $bestVal  = -1;

        $plays = $this->playRepo->findBy(['duel' => $duel, 'player' => $loser]);
        foreach ($plays as $play) {
            if (!$play instanceof Game4DuelPlay || $play->getCardType() !== Game4DuelPlay::TYPE_NUM) {
                continue;
            }

            $card = $play->getCard();
            if (!$card instanceof Game4Card) {
                continue;
            }

            $value = $this->valueFromPlay($play);
            if ($value > $bestVal) {
                $bestVal  = $value;
                $bestCard = $card;
            }
        }

        if ($bestCard instanceof Game4Card) {
            $bestCard
                ->setOwner($winner)
                ->setZone(Game4Card::ZONE_HAND);
        }

        return $bestCard;
    }

    private function discardPlays(array $plays, ?Game4Card $cardToKeep): void
    {
        foreach ($plays as $play) {
            if (!$play instanceof Game4DuelPlay) {
                continue;
            }

            $card = $play->getCard();
            if (!$card instanceof Game4Card) {
                continue;
            }

            if ($cardToKeep && $card->getId() === $cardToKeep->getId()) {
                continue; // la carte a été transférée au vainqueur
            }

            $card->setOwner(null)->setZone(Game4Card::ZONE_DISCARD);
            $game = $card->getGame();
            if ($game instanceof Game4Game && method_exists($game, 'incDiscardCount')) {
                $game->incDiscardCount();
            }
        }
    }

    private function findLastSpecialPlay(array $plays): ?Game4DuelPlay
    {
        $last = null;
        foreach ($plays as $play) {
            if (!$play instanceof Game4DuelPlay) {
                continue;
            }

            $type = strtoupper((string) $play->getCardType());
            if (\in_array($type, [Game4DuelPlay::TYPE_SHOTGUN, Game4DuelPlay::TYPE_VACCINE, Game4DuelPlay::TYPE_ZOMBIE], true)) {
                $last = $play;
            }
        }

        return $last;
    }

    private function transferAllHand(Game4Player $from, Game4Player $to): void
    {
        $cards = $this->cardRepo->findBy(['owner' => $from, 'zone' => Game4Card::ZONE_HAND]);
        foreach ($cards as $card) {
            if ($card instanceof Game4Card) {
                $card->setOwner($to);
            }
        }
    }

    private function giveSpecificCardTo(
        string $code,
        Game4Game $game,
        Game4Player $to,
        EntityManagerInterface $em,
        array &$logs
    ): void {
        /** @var Game4CardDef|null $def */
        $def = $em->getRepository(Game4CardDef::class)->findOneBy(['code' => $code]);
        if (!$def) {
            return;
        }

        $card = (new Game4Card())
            ->setGame($game)
            ->setDef($def)
            ->setZone(Game4Card::ZONE_HAND)
            ->setOwner($to)
            ->setToken(hash('sha256', uniqid('bonus', true)));

        $em->persist($card);
        $logs[] = sprintf('%s reçoit une carte %s.', $to->getName(), $def->getCode());
    }

    private function finalizeDuel(Game4Duel $duel, ?Game4Player $winner, array $logs): void
    {
        if (method_exists($duel, 'markResolved')) {
            $duel->markResolved($winner, $logs);
        } else {
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

        foreach ([$duel->getPlayerA(), $duel->getPlayerB()] as $participant) {
            if (!$participant instanceof Game4Player) {
                continue;
            }

            $participant->setLockedInDuel(false);
            if ($participant->getIncomingDuel()?->getId() === $duel->getId()) {
                $participant->setIncomingDuel(null);
            }
        }
    }
}