<?php

namespace App\Repository;

use App\Entity\TEpreuve3Finish;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TEpreuve3Finish>
 */
class TEpreuve3FinishRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TEpreuve3Finish::class);
    }

     /**
     * Trouve la position/classement d'une équipe dans une épreuve
     */
    public function findPositionForEquipe(int $equipeId, int $epreuveId): int
    {
        $result = $this->createQueryBuilder('f')
            ->select('f.id, f.equipeId')
            ->where('f.epreuveId = :epreuveId')
            ->setParameter('epreuveId', $epreuveId)
            ->orderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();

        $position = 1;
        foreach ($result as $finish) {
            if ($finish['equipeId'] === $equipeId) {
                return $position;
            }
            $position++;
        }

        return 1; // Par défaut
    }

    /**
     * Vérifie si une équipe a déjà terminé l'épreuve
     */
    public function hasEquipeFinished(int $equipeId, int $epreuveId): bool
    {
        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.equipeId = :equipeId')
            ->andWhere('f.epreuveId = :epreuveId')
            ->setParameter('equipeId', $equipeId)
            ->setParameter('epreuveId', $epreuveId)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    //    /**
    //     * @return TEpreuve3Finish[] Returns an array of TEpreuve3Finish objects
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

    //    public function findOneBySomeField($value): ?TEpreuve3Finish
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
