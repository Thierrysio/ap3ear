<?php

namespace App\Repository;

use App\Entity\TCompetition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TCompetition>
 */
class TCompetitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TCompetition::class);
    }

    /**
     * Renvoie la compétition en cours (dateDebut <= now <= datefin) ou null.
     */
    public function findCompetitionProchaine(): ?TCompetition
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('c')
            ->andWhere('c.dateDebut > :now')
            ->setParameter('now', $now)
            ->orderBy('c.dateDebut', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
    
        /**
     * Renvoie la compétition en cours (dateDebut <= now <= datefin) ou null.
     */
    public function findCompetitionEnCours(): ?TCompetition
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('c')
            ->andWhere('c.dateDebut <= :now')
            ->andWhere('c.datefin >= :now')
            ->setParameter('now', $now)
            ->orderBy('c.dateDebut', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // Exemples générés par défaut si besoin :
    // /**
    //  * @return TCompetition[] Returns an array of TCompetition objects
    //  */
    // public function findByExampleField($value): array
    // {
    //     return $this->createQueryBuilder('t')
    //         ->andWhere('t.exampleField = :val')
    //         ->setParameter('val', $value)
    //         ->orderBy('t.id', 'ASC')
    //         ->setMaxResults(10)
    //         ->getQuery()
    //         ->getResult()
    //     ;
    // }

    // public function findOneBySomeField($value): ?TCompetition
    // {
    //     return $this->createQueryBuilder('t')
    //         ->andWhere('t.exampleField = :val')
    //         ->setParameter('val', $value)
    //         ->getQuery()
    //         ->getOneOrNullResult()
    //     ;
    // }
}
