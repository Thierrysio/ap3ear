<?php

namespace App\Repository;

use App\Entity\NohadQuestion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NohadQuestion>
 */
class NohadQuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NohadQuestion::class);
    }

    /**
     * @return NohadQuestion[]
     */
    public function findAllWithReponses(): array
    {
        return $this->createQueryBuilder('question')
            ->leftJoin('question.reponses', 'reponse')
            ->addSelect('reponse')
            ->getQuery()
            ->getResult();
    }
}
