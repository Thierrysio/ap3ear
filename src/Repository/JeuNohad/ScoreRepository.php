<?php

namespace App\Repository\JeuNohad;

use App\Entity\JeuNohad\Score;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Score>
 */
class ScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Score::class);
    }

    /**
     * @return list<Score>
     */
    public function getLeaderboard(int $limit = 20): array
    {
        return $this->createQueryBuilder('score')
            ->orderBy('score.points', 'DESC')
            ->addOrderBy('score.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
