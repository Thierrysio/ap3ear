<?php

namespace App\Repository;

use App\Entity\GameSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameSession>
 */
class GameSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameSession::class);
    }

    public function findRunningForTepreuve(int $tepreuveId): ?GameSession
    {
        $qb = $this->createQueryBuilder('gs');
        $qb->andWhere('gs.status = :status')
            ->setParameter('status', GameSession::STATUS_RUNNING)
            ->andWhere('gs.tepreuveId = :tepreuveId')
            ->setParameter('tepreuveId', $tepreuveId)
            ->setMaxResults(1);

        $session = $qb->getQuery()->getOneOrNullResult();
        if ($session instanceof GameSession) {
            return $session;
        }

        $runningSessions = $this->findBy(['status' => GameSession::STATUS_RUNNING]);
        foreach ($runningSessions as $runningSession) {
            $settings = $runningSession->getSettings();
            if (($settings['tepreuveId'] ?? null) === $tepreuveId) {
                return $runningSession;
            }
        }

        return null;
    }
}
