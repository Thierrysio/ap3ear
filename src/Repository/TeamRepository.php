<?php

namespace App\Repository;

use App\Entity\Team;
use App\Entity\GameSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    public function findOneBySessionAndTequipeId(GameSession $session, int $tequipeId): ?Team
    {
        return $this->findOneBy([
            'gameSession' => $session,
            'tequipeId' => $tequipeId,
        ]);
    }
}
