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
}
