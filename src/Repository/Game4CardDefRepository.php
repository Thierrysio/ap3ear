<?php

namespace App\Repository;

use App\Entity\Game4CardDef;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Game4CardDefRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game4CardDef::class);
    }

    public function findOneByCode(string $code): ?Game4CardDef
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findOneByCodeInsensitive(string $code): ?Game4CardDef
    {
        return $this->createQueryBuilder('d')
            ->where('UPPER(d.code) = :code')
            ->setParameter('code', mb_strtoupper($code))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
