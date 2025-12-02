<?php

namespace App\Repository\JeuNohad;

use App\Entity\JeuNohad\Question;
use App\Entity\JeuNohad\UserResponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserResponse>
 */
class UserResponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserResponse::class);
    }

    public function findFastestResponseForQuestion(Question $question): ?UserResponse
    {
        return $this->createQueryBuilder('response')
            ->andWhere('response.question = :question')
            ->setParameter('question', $question)
            ->orderBy('response.responseTimeMs', 'ASC')
            ->addOrderBy('response.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
