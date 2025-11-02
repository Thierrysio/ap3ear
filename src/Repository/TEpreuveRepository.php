<?php

namespace App\Repository;

use App\Entity\TEpreuve;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TEpreuve>
 */
class TEpreuveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TEpreuve::class);
    }
    
public function findEpreuveEnCours(): ?TEpreuve
{
    $tz  = new \DateTimeZone('Europe/Paris');              // <-- important
    $now = new \DateTimeImmutable('now', $tz);

    return $this->createQueryBuilder('e')
        ->where('e.datedebut <= :now')
        // équivaut à: now < dateDebut + (dureeMax + 60)
        // => dateDebut + dureeMax > now - 60
        ->andWhere('DATE_ADD(e.datedebut, e.dureemax, \'minute\') > :limit')
        ->setParameter('now', $now)
        ->setParameter('limit', $now->sub(new \DateInterval('PT60M')))
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
}


    //    /**
    //     * @return TEpreuve[] Returns an array of TEpreuve objects
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

    //    public function findOneBySomeField($value): ?TEpreuve
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
