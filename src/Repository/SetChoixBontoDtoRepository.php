<?php

namespace App\Repository;

use App\Entity\SetChoixBontoDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SetChoixBontoDto>
 */
class SetChoixBontoDtoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SetChoixBontoDto::class);
    }
    
      /** Compte les gagnants (Gagnant=1) pour la manche donnée. */
    public function countGagnantsParManche(int $mancheId): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.Manche = :m')
            ->andWhere('LOWER(s.gagnant) IN (:g)')
            ->setParameter('m', $mancheId)
            ->setParameter('g', $this->getTruthyGagnantValues())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Indique si l'équipe donnée est gagnante sur la manche. */
    public function isEquipeGagnantePourManche(int $mancheId, int $equipeId): bool
    {
        $count = (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.Manche = :m')
            ->andWhere('LOWER(s.gagnant) IN (:g)')
            ->andWhere('s.EquipeId = :e')
            ->setParameter('m', $mancheId)
            ->setParameter('g', $this->getTruthyGagnantValues())
            ->setParameter('e', $equipeId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Renvoie la synthèse attendue par l'API : une seule ligne.
     * ['mancheId'=>int, 'nbGagnants'=>int, 'monEquipeContinue'=>0|1]
     */
    public function syntheseResultats(int $mancheId, ?int $equipeId): array
    {
        $nb = $this->countGagnantsParManche($mancheId);
        $me = 0;

        if ($equipeId !== null && $equipeId > 0) {
            $me = $this->isEquipeGagnantePourManche($mancheId, $equipeId) ? 1 : 0;
        }

        return [
            'mancheId'          => $mancheId,
            'nbGagnants'        => $nb,
            'monEquipeContinue' => $me,
        ];
    }

    /**
     * Liste des valeurs interprétées comme "vrai" pour le champ gagnant.
     */
    private function getTruthyGagnantValues(): array
    {
        return ['true', '1', 'on', 'yes'];
    }

    //    /**
    //     * @return SetChoixBontoDto[] Returns an array of SetChoixBontoDto objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?SetChoixBontoDto
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
