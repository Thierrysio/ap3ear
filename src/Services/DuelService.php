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
    private const MAX_HAND = 7;

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
            $result->logs[] = 'Duel incohrent : participants manquants.';
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
            $result->logs[] = 'Aucune carte joue.';
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
            $result->logs[] = "Impossible de dterminer les joueurs du duel.";
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

                    $transferred = $this->transferAllHand($opponent, $actor);
                    $handSnapshot = $this->collectHandSnapshot($actor, $plays, $transferred);

                    foreach ($this->enforceHandLimit($actor, handSnapshot: $handSnapshot) as $returned) {
                        $this->appendHandLimitLog($actor, $returned, $logs);
                    }

                    $logs[]    = sprintf('%s limine %s (zombie) avec SHOTGUN et rcupre sa main.', $actor->getName(), $opponent->getName());
                    $effects[] = ['type' => 'kill', 'playerId' => $opponent->getId()];
                } else {
                    $winnerLowestBefore = $this->getLowestNumericValue($actor);
                    $stolen = $this->stealHighestPostedNumeric($duel, $actor, $opponent);
                    if ($stolen) {
                        $def = $stolen->getDef();
                        $logs[] = sprintf(
                            "%s (humain) cde sa meilleure carte (%s)  %s.",
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
                        $handSnapshot = $this->collectHandSnapshot($actor, $plays, $stolen ? [$stolen] : []);
                        foreach ($this->enforceHandLimit($actor, $stolen, $winnerLowestBefore, $handSnapshot) as $returned) {
                            $this->appendHandLimitLog($actor, $returned, $logs, $stolen);
                        }
                        $result->wonCardCode  = $def?->getCode();
                        $result->wonCardLabel = $def?->getLabel();
                    } else {
                        $logs[] = sprintf("%s n'avait aucune carte numrique  transfrer.", $opponent->getName());
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
                        $logs[] = sprintf('Une carte Infection est retire de la main de %s.', $opponent->getName());
                    }

                    $this->giveSpecificCardTo('SHOTGUN', $game, $actor, $em, $logs);
                    $this->giveSpecificCardTo('SHOTGUN', $game, $opponent, $em, $logs);

                    $logs[]    = sprintf('%s vaccine %s qui redevient humain.', $actor->getName(), $opponent->getName());
                    $effects[] = ['type' => 'role', 'playerId' => $opponent->getId(), 'role' => Game4Player::ROLE_HUMAN];
                } else {
                    $logs[] = 'VACCINE jou sans cible zombie : aucun effet.';
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
                    $logs[] = "ZOMBIE jou mais les conditions d'infection ne sont pas runies.";
                }
                break;

            default:
                $logs[]  = sprintf('Carte spciale inconnue : %s.', $type);
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
    \stdClass $result
): bool {
    $playerA = $duel->getPlayerA();
    $playerB = $duel->getPlayerB();
    if (!$playerA || !$playerB) {
        $result->logs[] = 'Participants manquants pour le duel.';
        return false;
    }

    // ?? Nombre de cartes numriques joues par chaque joueur
    $countA = 0;
    $countB = 0;

    foreach ($plays as $play) {
        if (!$play instanceof Game4DuelPlay) {
            continue;
        }
        if ($play->getCardType() !== Game4DuelPlay::TYPE_NUM) {
            continue;
        }

        $pid = $play->getPlayer()?->getId();
        if ($pid === $playerA->getId()) {
            $countA++;
        } elseif ($pid === $playerB->getId()) {
            $countB++;
        }
    }

    // On veut un duel NUM qui se joue sur 4 cartes chacun
    $requiredCards = 4;

    // ? Tant que les deux joueurs nont pas jou leurs 4 cartes NUM, on NE rsout PAS
    if ($countA < $requiredCards || $countB < $requiredCards) {
        $result->logs[] = sprintf(
            "Duel numrique en cours : %s a jou %d carte(s), %s a jou %d carte(s).",
            $playerA->getName(),
            $countA,
            $playerB->getName(),
            $countB
        );
        // Pas de finalisation, pas de gagnant
        $result->winnerId     = null;
        $result->pointsDelta  = 0;
        $result->wonCardCode  = null;
        $result->wonCardLabel = null;
        $result->effects      = [];

        return false; // ? Trs important : on NE finalise pas le duel
    }

    // ?  partir d'ici : les 2 joueurs ont jou toutes leurs cartes NUM ? on peut rsoudre

    $logs    = [];
    $effects = [];

    // Totaux numriques
    $sumA = $this->sumNumericForPlayer($plays, $playerA);
    $sumB = $this->sumNumericForPlayer($plays, $playerB);

    $logs[] = sprintf(
        "%s totalise %d point(s) avec ses cartes numriques.",
        $playerA->getName(),
        $sumA
    );
    $logs[] = sprintf(
        "%s totalise %d point(s) avec ses cartes numriques.",
        $playerB->getName(),
        $sumB
    );

    $winner = null;
    $loser  = null;
    $stolen = null;

    if ($sumA === $sumB) {
        // ?? galit
        $logs[] = "galit parfaite sur le total des cartes numriques : le duel est nul.";
        $result->winnerId     = null;
        $result->pointsDelta  = 0;
        $result->wonCardCode  = null;
        $result->wonCardLabel = null;

        // ?? RGLE :
        // Les cartes joues (NUM) sont rendues  leurs propritaires
        $this->returnPlayedCardsToHands($plays, null);

        // On finalise quand mme le duel (termin, mais personne ne gagne)
        $this->finalizeDuel($duel, null, $logs);

        $result->logs    = $logs;
        $result->effects = $effects;

        return true;
    }

    // ?? Il y a un gagnant
    if ($sumA > $sumB) {
        $winner = $playerA;
        $loser  = $playerB;
    } else {
        $winner = $playerB;
        $loser  = $playerA;
    }

    $logs[] = sprintf(
        "%s remporte le duel numrique contre %s.",
        $winner->getName(),
        $loser->getName()
    );
    $result->winnerId    = $winner->getId();
    $result->pointsDelta = abs($sumA - $sumB);

    $winnerLowestBefore = $this->getLowestNumericValue($winner);

    // ?? Le gagnant vole la meilleure carte numrique joue par le perdant
    $stolen = $this->findBestNumericCardForPlayer($plays, $loser);

    if ($stolen instanceof Game4Card) {
        // Transfert de proprit de la carte vers le gagnant
        $stolen->setOwner($winner);
        $stolen->setZone(Game4Card::ZONE_HAND);

        $def   = $stolen->getDef();
        $code  = null;
        $label = null;

        if ($def instanceof Game4CardDef) {
            $code  = $def->getCode();
            $label = $def->getLabel() ?? $code;
        }

        $logs[] = sprintf(
            "%s rcupre la meilleure carte numrique de %s (%s).",
            $winner->getName(),
            $loser->getName(),
            $label ?? 'carte'
        );

        $effects[] = [
            'type'     => 'card_transfer',
            'from'     => $loser->getId(),
            'to'       => $winner->getId(),
            'cardCode' => $code,
        ];

        $result->wonCardCode  = $code;
        $result->wonCardLabel = $label;
    } else {
        $logs[] = sprintf(
            "%s navait aucune carte numrique  cder.",
            $loser->getName()
        );
        $result->wonCardCode  = null;
        $result->wonCardLabel = null;
    }

    // ?? RGLE :
    // Toutes les cartes joues qui ne sont pas "perdues" (ici : la carte vole) reviennent en main
    $this->returnPlayedCardsToHands($plays, $stolen);

    $winnerHandSnapshot = $this->collectHandSnapshot($winner, $plays, $stolen ? [$stolen] : []);
    foreach ($this->enforceHandLimit($winner, $stolen, $winnerLowestBefore, $winnerHandSnapshot) as $returnedWinner) {
        $this->appendHandLimitLog($winner, $returnedWinner, $logs, $stolen);
    }

    $loserHandSnapshot = $this->collectHandSnapshot($loser, $plays);
    foreach ($this->enforceHandLimit($loser, handSnapshot: $loserHandSnapshot) as $returnedLoser) {
        $this->appendHandLimitLog($loser, $returnedLoser, $logs);
    }

    // ? On marque le duel comme rsolu
    $this->finalizeDuel($duel, $winner, $logs);

    $result->logs    = $logs;
    $result->effects = $effects;

    return true;
}

private function findBestNumericCardForPlayer(array $plays, Game4Player $player): ?Game4Card
{
    $bestPlay = null;
    $bestVal  = null;

    foreach ($plays as $play) {
        if (!$play instanceof Game4DuelPlay) {
            continue;
        }
        if ($play->getPlayer()?->getId() !== $player->getId()) {
            continue;
        }
        if ($play->getCardType() !== Game4DuelPlay::TYPE_NUM) {
            continue;
        }

        // Valeur numrique pratique pour comparer les cartes
        $val = $this->valueFromPlay($play);
        if ($bestPlay === null || $val > $bestVal) {
            $bestPlay = $play;
            $bestVal  = $val;
        }
    }

    if ($bestPlay instanceof Game4DuelPlay) {
        return $bestPlay->getCard();
    }

    return null;
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
    

/**
 * Remet en main toutes les cartes joues dans ce duel,
 * sauf ventuellement une carte "perdue" (vole / dtruite)
 * passe en paramtre.
 *
 * @param Game4DuelPlay[] $plays
 * @param Game4Card|null  $lost  Carte qui ne doit PAS tre rendue (ex : carte vole par le gagnant)
 */
private function returnPlayedCardsToHands(array $plays, ?Game4Card $lost = null): void
{
    $lostId = $lost?->getId();

    foreach ($plays as $play) {
        if (!$play instanceof Game4DuelPlay) {
            continue;
        }

        $card = $play->getCard();
        if (!$card instanceof Game4Card) {
            continue;
        }

        // Si c'est la carte "perdue" (vole/dtruite), on ne la touche pas ici
        if ($lostId !== null && $card->getId() === $lostId) {
            continue;
        }

        // Rgle : la carte joue mais non perdue revient dans la main de son propritaire
        $card->setZone(Game4Card::ZONE_HAND);
        // On NE change PAS l'owner : il reste le joueur qui l'avait joue
    }
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


    private function getLowestNumericValue(Game4Player $player): ?int
    {
        $cards = $this->cardRepo->findBy(['owner' => $player, 'zone' => Game4Card::ZONE_HAND]);
        $lowestCard = $this->findLowestNumericCard($cards);

        if (!$lowestCard instanceof Game4Card) {
            return null;
        }

        return $this->getNumericValueFromCard($lowestCard);
    }

    /**
     * @param array<int|string, Game4Card>|null $handSnapshot
     *
     * @return list<Game4Card>
     */
    private function enforceHandLimit(
        Game4Player $player,
        ?Game4Card $addedCard = null,
        ?int $previousLowest = null,
        ?array $handSnapshot = null
    ): array
    {
        $returned    = [];
        $pendingGain = $addedCard;

        $handCards = $handSnapshot ?? $this->collectHandSnapshot($player);

        while (true) {
            
            if (count($handCards) <= self::MAX_HAND) {
                break;
            }

            $numericCards = array_filter(
                $handCards,
                fn (Game4Card $card) => $this->getNumericValueFromCard($card) !== null
            );

            if ($numericCards === []) {
                break;
            }

            $cardToReturn = null;
            $addedValue   = null;

            if ($pendingGain instanceof Game4Card) {
                $addedValue = $this->getNumericValueFromCard($pendingGain);
                if ($addedValue !== null && $previousLowest !== null && $addedValue < $previousLowest) {
                    $cardToReturn = $pendingGain;
                }
            }

            if (!$cardToReturn instanceof Game4Card) {
                $cardToReturn = $this->findLowestNumericCard($numericCards);
            }

            if (!$cardToReturn instanceof Game4Card && $pendingGain instanceof Game4Card && $addedValue !== null) {
                $cardToReturn = $pendingGain;
            }

            if (!$cardToReturn instanceof Game4Card) {
                break;
            }

            if ($this->getNumericValueFromCard($cardToReturn) === null) {
                break;
            }

            $cardToReturn->setOwner(null)->setZone(Game4Card::ZONE_DECK);
            $returned[] = $cardToReturn;

            $this->removeFromSnapshot($handCards, $cardToReturn);

            if ($pendingGain instanceof Game4Card && $cardToReturn->getId() === $pendingGain->getId()) {
                $pendingGain = null;
            }

            $previousLowest = null;
        }

        return $returned;
    }

    /**
     * @param array<int|string, Game4Card> $snapshot
     */
    private function removeFromSnapshot(array &$snapshot, Game4Card $card): void
    {
        $key = $card->getId();
        if ($key !== null && isset($snapshot[$key])) {
            unset($snapshot[$key]);

            return;
        }

        $hash = spl_object_hash($card);
        if (isset($snapshot[$hash])) {
            unset($snapshot[$hash]);
        }
    }

    /**
     * @param array<int|string, Game4Card> $snapshot
     */
    private function addToSnapshot(array &$snapshot, Game4Card $card): void
    {
        $key = $card->getId();
        if ($key === null) {
            $key = spl_object_hash($card);
        }

        $snapshot[$key] = $card;
    }

    /**
     * @param Game4DuelPlay[] $plays
     * @param Game4Card[]     $extraCards
     *
     * @return array<int|string, Game4Card>
     */
    private function collectHandSnapshot(Game4Player $player, array $plays = [], array $extraCards = []): array
    {
        $snapshot = [];

        foreach ($this->cardRepo->findBy(['owner' => $player, 'zone' => Game4Card::ZONE_HAND]) as $card) {
            if ($card instanceof Game4Card) {
                $this->addToSnapshot($snapshot, $card);
            }
        }

        foreach ($plays as $play) {
            if (!$play instanceof Game4DuelPlay) {
                continue;
            }

            if ($play->getPlayer()?->getId() !== $player->getId()) {
                continue;
            }

            $card = $play->getCard();
            if ($card instanceof Game4Card && $card->getOwner()?->getId() === $player->getId() && $card->getZone() === Game4Card::ZONE_HAND) {
                $this->addToSnapshot($snapshot, $card);
            }
        }

        foreach ($extraCards as $card) {
            if (!$card instanceof Game4Card) {
                continue;
            }

            if ($card->getOwner()?->getId() !== $player->getId() || $card->getZone() !== Game4Card::ZONE_HAND) {
                continue;
            }

            $this->addToSnapshot($snapshot, $card);
        }

        return $snapshot;
    }

    private function appendHandLimitLog(Game4Player $player, Game4Card $returned, array &$logs, ?Game4Card $gained = null): void
    {
        $returnedDef   = $returned->getDef();
        $returnedLabel = ($returnedDef instanceof Game4CardDef) ? ($returnedDef->getLabel() ?? $returnedDef->getCode()) : 'carte';

        if ($gained instanceof Game4Card && $returned->getId() === $gained->getId()) {
            $logs[] = sprintf('La main de %s depassait 7 cartes : la carte gagnee (%s) retourne dans le deck.', $player->getName(), $returnedLabel ?? 'carte');

            return;
        }

        $logs[] = sprintf('La main de %s depassait 7 cartes : %s retourne dans le deck.', $player->getName(), $returnedLabel ?? 'carte');
    }

    /**
     * @param array<int, Game4Card> $cards
     */
    private function findLowestNumericCard(array $cards): ?Game4Card
    {
        $lowestCard  = null;
        $lowestValue = PHP_INT_MAX;

        foreach ($cards as $card) {
            if (!$card instanceof Game4Card) {
                continue;
            }

            $value = $this->getNumericValueFromCard($card);
            if ($value === null) {
                continue;
            }

            if ($value < $lowestValue || ($value === $lowestValue && $lowestCard instanceof Game4Card && $card->getId() < $lowestCard->getId())) {
                $lowestValue = $value;
                $lowestCard  = $card;
            }
        }

        return $lowestCard;
    }

    private function getNumericValueFromCard(Game4Card $card): ?int
    {
        $def = $card->getDef();

        if (!$def instanceof Game4CardDef) {
            return null;
        }

        if (strtoupper((string) $def->getType()) !== 'NUM') {
            return null;
        }

        if (preg_match('/(\d+)/', (string) $def->getCode(), $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/(\d+)/', (string) $def->getLabel(), $matches)) {
            return (int) $matches[1];
        }

        return null;
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
                continue; // la carte a t transfre au vainqueur
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

    /**
     * @return Game4Card[]
     */
    private function transferAllHand(Game4Player $from, Game4Player $to): array
    {
        $moved  = [];
        $cards = $this->cardRepo->findBy(['owner' => $from, 'zone' => Game4Card::ZONE_HAND]);
        foreach ($cards as $card) {
            if (!$card instanceof Game4Card) {
                continue;
            }

            $card->setOwner($to);
            $moved[] = $card;
        }

        return $moved;
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
        $logs[] = sprintf('%s reoit une carte %s.', $to->getName(), $def->getCode());
    }

    private function finalizeDuel(Game4Duel $duel, ?Game4Player $winner, array $logs): void
    {
        $logs = $this->sanitizeLogs($logs);

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

    /**
     * Normalise les lignes de journal vers UTF-8 pour viter les caractres invalides.
     *
     * @param array<int, string> $logs
     *
     * @return array<int, string>
     */
    private function sanitizeLogs(array $logs): array
    {
        return array_values(array_map(fn ($line) => $this->normalizeLogLine((string) $line), $logs));
    }

    private function normalizeLogLine(string $line): string
    {
        $converted = @mb_convert_encoding($line, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');

        if ($converted === false) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $line) ?: '';
        }

        return $converted;
    }
}