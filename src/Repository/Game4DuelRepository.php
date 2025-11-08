<?php

namespace App\Repository;

use App\Entity\Game4Game;
use App\Entity\Game4Duel;
use App\Entity\Game4Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game4Duel>
 */
class Game4DuelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game4Duel::class);
    }

    // ------------------------------------------------------------------
    // Sélecteurs de base (cohérents avec Game4Duel)
    // ------------------------------------------------------------------

    /**
     * Duel actif (PENDING) impliquant ce joueur (A ou B).
     * Le plus récent est renvoyé si plusieurs existent (par sécurité).
     */
    public function findActiveForPlayer(Game4Player $p): ?Game4Duel
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :s')->setParameter('s', Game4Duel::STATUS_PENDING)
            ->andWhere('d.playerA = :p OR d.playerB = :p')->setParameter('p', $p)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * Duel actif (PENDING) entre A et B (quel que soit l’ordre).
     */
    public function findActiveBetween(Game4Game $game, Game4Player $a, Game4Player $b): ?Game4Duel
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.game = :g')->setParameter('g', $game)
            ->andWhere('d.status = :s')->setParameter('s', Game4Duel::STATUS_PENDING)
            ->andWhere('(d.playerA = :a AND d.playerB = :b) OR (d.playerA = :b AND d.playerB = :a)')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * Dernier duel entrant (PENDING) pour "me".
     * Convention : "entrant" = le joueur est côté B (cible).
     */
    public function findLastIncomingFor(Game4Game $game, Game4Player $me): ?Game4Duel
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.game = :g')->setParameter('g', $game)
            ->andWhere('d.status = :s')->setParameter('s', Game4Duel::STATUS_PENDING)
            ->andWhere('d.playerB = :me')->setParameter('me', $me)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * Tous les duels en attente pour une partie.
     * @return Game4Duel[]
     */
    public function findAllPending(Game4Game $game): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.game = :g')->setParameter('g', $game)
            ->andWhere('d.status = :s')->setParameter('s', Game4Duel::STATUS_PENDING)
            ->orderBy('d.createdAt', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Indique si au moins un duel est actuellement en attente dans la partie.
     */
    public function hasPending(Game4Game $game): bool
    {
        $count = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.game = :g')->setParameter('g', $game)
            ->andWhere('d.status = :s')->setParameter('s', Game4Duel::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * Dernier duel rsolu pour une partie donne.
     */
    public function findLastResolved(Game4Game $game): ?Game4Duel
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.game = :g')->setParameter('g', $game)
            ->andWhere('d.status = :s')->setParameter('s', Game4Duel::STATUS_RESOLVED)
            ->andWhere('d.resolvedAt IS NOT NULL')
            ->orderBy('d.resolvedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ------------------------------------------------------------------
    // Utilitaires (création / résolution)
    // ------------------------------------------------------------------

    /**
     * Persiste un duel (sans flush par défaut).
     */
    public function save(Game4Duel $duel, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($duel);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Marque un duel comme résolu, pose le vainqueur et les logs.
     * (Sans flush par défaut : laisse au service appelant gérer la transaction.)
     */
    public function resolve(Game4Duel $duel, ?int $winnerId, array $logLines = [], bool $flush = false): void
    {
        $duel->setStatus(Game4Duel::STATUS_RESOLVED)
             ->setWinnerId($winnerId)
             ->setResolvedAt(new \DateTimeImmutable());

        if (!empty($logLines)) {
            $duel->setLogsArray($logLines);
        }

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
