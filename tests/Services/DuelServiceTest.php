<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Entity\Game4Card;
use App\Entity\Game4CardDef;
use App\Entity\Game4Duel;
use App\Entity\Game4DuelPlay;
use App\Entity\Game4Game;
use App\Entity\Game4Player;
use App\Services\DuelService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DuelServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DuelService $service;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(DuelService::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testHumanTurnedZombieReceivesZombieCard(): void
    {
        $game = (new Game4Game())
            ->setPhase(Game4Game::PHASE_RUNNING);
        $this->em->persist($game);

        $zombie = (new Game4Player())
            ->setGame($game)
            ->setEquipeId(1)
            ->setName('Zombie Player')
            ->setRole(Game4Player::ROLE_ZOMBIE);
        $this->em->persist($zombie);

        $human = (new Game4Player())
            ->setGame($game)
            ->setEquipeId(2)
            ->setName('Human Player')
            ->setRole(Game4Player::ROLE_HUMAN);
        $this->em->persist($human);

        $duel = (new Game4Duel())
            ->setGame($game)
            ->setPlayerA($zombie)
            ->setPlayerB($human)
            ->setStatus(Game4Duel::STATUS_PENDING);
        $this->em->persist($duel);

        $zombieDef = (new Game4CardDef())
            ->setCode('Zombie')
            ->setLabel('Zombie')
            ->setText('Contagion')
            ->setType('ZOMBIE');
        $this->em->persist($zombieDef);

        $zombieCard = (new Game4Card())
            ->setGame($game)
            ->setDef($zombieDef)
            ->setOwner($zombie)
            ->setZone(Game4Card::ZONE_HAND)
            ->setToken('token-zombie');
        $this->em->persist($zombieCard);

        $play = (new Game4DuelPlay())
            ->setDuel($duel)
            ->setPlayer($zombie)
            ->setCard($zombieCard)
            ->setCardCode('ZOMBIE')
            ->setCardType(Game4DuelPlay::TYPE_ZOMBIE)
            ->setRoundIndex(1);
        $duel->addPlay($play);
        $this->em->persist($play);

        $this->em->flush();

        $this->service->resolve($duel, $this->em);

        $this->em->refresh($human);

        self::assertTrue($human->isZombie(), 'The targeted player should become a zombie.');

        $cards = $this->em->getRepository(Game4Card::class)->findBy([
            'owner' => $human,
            'zone'  => Game4Card::ZONE_HAND,
        ]);

        $zombieCards = array_filter($cards, static function (Game4Card $card): bool {
            return strtoupper($card->getDef()->getCode()) === 'ZOMBIE';
        });

        self::assertNotEmpty($zombieCards, 'The new zombie should receive a zombie card.');
    }

    public function testHumanTurnedZombieHandLimitReturnsLowestNumericCard(): void
    {
        $game = (new Game4Game())
            ->setPhase(Game4Game::PHASE_RUNNING);
        $this->em->persist($game);

        $zombie = (new Game4Player())
            ->setGame($game)
            ->setEquipeId(1)
            ->setName('Zombie Player')
            ->setRole(Game4Player::ROLE_ZOMBIE);
        $this->em->persist($zombie);

        $human = (new Game4Player())
            ->setGame($game)
            ->setEquipeId(2)
            ->setName('Human Player')
            ->setRole(Game4Player::ROLE_HUMAN);
        $this->em->persist($human);

        $duel = (new Game4Duel())
            ->setGame($game)
            ->setPlayerA($zombie)
            ->setPlayerB($human)
            ->setStatus(Game4Duel::STATUS_PENDING);
        $this->em->persist($duel);

        $zombieDef = (new Game4CardDef())
            ->setCode('Zombie')
            ->setLabel('Zombie')
            ->setText('Contagion')
            ->setType('ZOMBIE');
        $this->em->persist($zombieDef);

        $zombieCard = (new Game4Card())
            ->setGame($game)
            ->setDef($zombieDef)
            ->setOwner($zombie)
            ->setZone(Game4Card::ZONE_HAND)
            ->setToken('token-zombie');
        $this->em->persist($zombieCard);

        $numericCards = [];
        for ($i = 1; $i <= 7; $i++) {
            $code = sprintf('NUM%02d', $i);
            $def  = (new Game4CardDef())
                ->setCode($code)
                ->setLabel($code)
                ->setType('NUM');
            $this->em->persist($def);

            $card = (new Game4Card())
                ->setGame($game)
                ->setDef($def)
                ->setOwner($human)
                ->setZone(Game4Card::ZONE_HAND)
                ->setToken(sprintf('token-num-%02d', $i));
            $this->em->persist($card);

            $numericCards[$i] = $card;
        }

        $play = (new Game4DuelPlay())
            ->setDuel($duel)
            ->setPlayer($zombie)
            ->setCard($zombieCard)
            ->setCardCode('ZOMBIE')
            ->setCardType(Game4DuelPlay::TYPE_ZOMBIE)
            ->setRoundIndex(1);
        $duel->addPlay($play);
        $this->em->persist($play);

        $this->em->flush();

        $this->service->resolve($duel, $this->em);

        $this->em->refresh($human);
        $this->em->refresh($numericCards[1]);

        self::assertTrue($human->isZombie(), 'The targeted player should become a zombie.');

        $handCards = $this->em->getRepository(Game4Card::class)->findBy([
            'owner' => $human,
            'zone'  => Game4Card::ZONE_HAND,
        ]);

        self::assertCount(7, $handCards, 'The new zombie should not exceed the hand limit.');

        $hasZombieCard = array_filter($handCards, static function (Game4Card $card): bool {
            return strtoupper($card->getDef()->getCode()) === 'ZOMBIE';
        });

        self::assertNotEmpty($hasZombieCard, 'The new zombie should keep their zombie card.');

        self::assertNull($numericCards[1]->getOwner(), 'The lowest numeric card should return to the deck.');
        self::assertSame(Game4Card::ZONE_DECK, $numericCards[1]->getZone());
    }

    public function testForceResolveCompletesNumericDuel(): void
    {
        $game = (new Game4Game())
            ->setPhase(Game4Game::PHASE_RUNNING);
        $this->em->persist($game);

        $playerA = (new Game4Player())
            ->setGame($game)
            ->setEquipeId(2)
            ->setName('Equipe 2')
            ->setRole(Game4Player::ROLE_HUMAN);
        $this->em->persist($playerA);

        $playerB = (new Game4Player())
            ->setGame($game)
            ->setEquipeId(3)
            ->setName('Equipe 3')
            ->setRole(Game4Player::ROLE_HUMAN);
        $this->em->persist($playerB);

        $duel = (new Game4Duel())
            ->setGame($game)
            ->setPlayerA($playerA)
            ->setPlayerB($playerB)
            ->setStatus(Game4Duel::STATUS_PENDING);
        $this->em->persist($duel);

        $cards = [];
        foreach ([['code' => 'NUM_08', 'label' => '8', 'value' => 8, 'owner' => $playerA], ['code' => 'NUM_04', 'label' => '4', 'value' => 4, 'owner' => $playerA], ['code' => 'NUM_07', 'label' => '7', 'value' => 7, 'owner' => $playerB], ['code' => 'NUM_03', 'label' => '3', 'value' => 3, 'owner' => $playerB]] as $data) {
            $def = (new Game4CardDef())
                ->setCode($data['code'])
                ->setLabel($data['label'])
                ->setType('NUM');
            $this->em->persist($def);

            $card = (new Game4Card())
                ->setGame($game)
                ->setDef($def)
                ->setOwner($data['owner'])
                ->setZone(Game4Card::ZONE_HAND)
                ->setToken('token-'.$data['code']);
            $this->em->persist($card);

            $cards[$data['code']] = $card;
        }

        $round = 1;
        foreach ([['player' => $playerA, 'card' => $cards['NUM_08'], 'value' => 8], ['player' => $playerB, 'card' => $cards['NUM_07'], 'value' => 7], ['player' => $playerA, 'card' => $cards['NUM_04'], 'value' => 4], ['player' => $playerB, 'card' => $cards['NUM_03'], 'value' => 3]] as $playData) {
            $play = (new Game4DuelPlay())
                ->setPlayer($playData['player'])
                ->setCard($playData['card'])
                ->setCardCode($playData['card']->getDef()->getCode())
                ->setCardType(Game4DuelPlay::TYPE_NUM)
                ->setNumValue($playData['value'])
                ->setRoundIndex($round++);
            $duel->addPlay($play);
            $this->em->persist($play);
        }

        $this->em->flush();

        $this->service->resolve($duel, $this->em);
        self::assertSame(Game4Duel::STATUS_PENDING, $duel->getStatus(), 'The duel should still be pending before the forced resolution.');

        $result = $this->service->forceResolve($duel, $this->em, $playerA);
        $this->em->refresh($duel);

        self::assertSame(Game4Duel::STATUS_RESOLVED, $duel->getStatus(), 'The duel must be resolved after forcing.');
        self::assertSame($playerA->getId(), $duel->getWinner()?->getId(), 'Player A should win with the higher total.');
        self::assertSame($playerA->getId(), $result->winnerId, 'The result should expose the winning player.');

        $logs = $result->logs;
        self::assertNotEmpty($logs, 'Logs should describe the forced resolution.');
        self::assertTrue(
            $this->containsLogLine($logs, 'Résolution forcée déclenchée par'),
            'The forced resolution log line should be present.'
        );

        $stolenCard = $cards['NUM_07'];
        $this->em->refresh($stolenCard);
        self::assertSame($playerA->getId(), $stolenCard->getOwner()?->getId(), 'The winner should steal the best card from the opponent.');
        self::assertSame(Game4Card::ZONE_HAND, $stolenCard->getZone(), 'The stolen card must end in the winner\'s hand.');
    }

    public function testResolveHandlesLowercaseNumericTypes(): void
    {
        $game = (new Game4Game())
            ->setPhase(Game4Game::PHASE_RUNNING);
        $this->em->persist($game);

        $playerA = (new Game4Player())
            ->setGame($game)
            ->setEquipeId(4)
            ->setName('Equipe 4')
            ->setRole(Game4Player::ROLE_HUMAN);
        $this->em->persist($playerA);

        $playerB = (new Game4Player())
            ->setGame($game)
            ->setEquipeId(5)
            ->setName('Equipe 5')
            ->setRole(Game4Player::ROLE_HUMAN);
        $this->em->persist($playerB);

        $duel = (new Game4Duel())
            ->setGame($game)
            ->setPlayerA($playerA)
            ->setPlayerB($playerB)
            ->setStatus(Game4Duel::STATUS_PENDING);
        $this->em->persist($duel);

        $playsData = [
            ['player' => $playerA, 'code' => 'NUM_06', 'value' => 6],
            ['player' => $playerB, 'code' => 'NUM_05', 'value' => 5],
            ['player' => $playerA, 'code' => 'NUM_04', 'value' => 4],
            ['player' => $playerB, 'code' => 'NUM_03', 'value' => 3],
            ['player' => $playerA, 'code' => 'NUM_02', 'value' => 2],
            ['player' => $playerB, 'code' => 'NUM_02B', 'value' => 2],
            ['player' => $playerA, 'code' => 'NUM_01', 'value' => 1],
            ['player' => $playerB, 'code' => 'NUM_01B', 'value' => 1],
        ];

        $round = 1;
        $cardTypeProperty = new \ReflectionProperty(Game4DuelPlay::class, 'cardType');
        $cardTypeProperty->setAccessible(true);

        foreach ($playsData as $data) {
            $def = (new Game4CardDef())
                ->setCode($data['code'])
                ->setLabel($data['code'])
                ->setType('num');
            $this->em->persist($def);

            $card = (new Game4Card())
                ->setGame($game)
                ->setDef($def)
                ->setOwner($data['player'])
                ->setZone(Game4Card::ZONE_HAND)
                ->setToken('token-'.$data['code']);
            $this->em->persist($card);

            $play = (new Game4DuelPlay())
                ->setPlayer($data['player'])
                ->setCard($card)
                ->setCardCode($def->getCode())
                ->setCardType('num')
                ->setNumValue($data['value'])
                ->setRoundIndex($round++);
            $cardTypeProperty->setValue($play, 'num');
            $duel->addPlay($play);
            $this->em->persist($play);
        }

        $this->em->flush();

        $this->service->resolve($duel, $this->em);
        $this->em->refresh($duel);

        self::assertSame(Game4Duel::STATUS_RESOLVED, $duel->getStatus(), 'The duel should resolve even when card types are lowercase.');
        self::assertSame($playerA->getId(), $duel->getWinner()?->getId(), 'Player A should win with the higher total of numeric cards.');
    }

    /**
     * @param array<int, string> $logs
     */
    private function containsLogLine(array $logs, string $needle): bool
    {
        foreach ($logs as $line) {
            if (str_contains($line, $needle)) {
                return true;
            }
        }

        return false;
    }
}
