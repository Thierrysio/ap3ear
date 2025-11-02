<?php

namespace App\Repository;

use App\Entity\Tflag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TFlag>
 */
class TflagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TFlag::class);
    }

    /**
     * Trouve le prochain flag d'une épreuve selon l'ordre
     */
    public function findNextFlagForEpreuve(int $epreuveId, int $flagOrder): ?TFlag
    {
        return $this->createQueryBuilder('f')
            ->where('f.epreuve = :epreuveId')
            ->andWhere('f.ordre = :ordre')
            ->setParameter('epreuveId', $epreuveId)
            ->setParameter('ordre', $flagOrder)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte le nombre total de flags pour une épreuve
     */
    public function countFlagsForEpreuve(int $epreuveId): int
    {
        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.epreuve = :epreuveId')
            ->setParameter('epreuveId', $epreuveId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve tous les flags d'une épreuve par ordre
     */
    public function findAllFlagsForEpreuveOrdered(int $epreuveId): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.epreuve = :epreuveId')
            ->setParameter('epreuveId', $epreuveId)
            ->orderBy('f.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }


    //    /**
    //     * @return Tflag[] Returns an array of Tflag objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Tflag
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
