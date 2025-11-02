<?php

namespace App\Repository;

use App\Entity\Game4Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game4Game>
 *
 * Méthodes utiles :
 * - findRunning()        : retourne la partie en cours (PHASE_RUNNING + non expirée), la plus récente.
 * - ensureRunning()      : retourne une partie en cours, en crée une si aucune n'existe.
 * - startNew()           : crée une nouvelle partie en phase running, avec une durée (minutes) optionnelle.
 * - markFinished()       : passe la partie en phase finished et coupe la fin.
 * - extendEndsAt()       : prolonge la fin de partie.
 * - save()               : persiste + (optionnel) flush.
 */
class Game4GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game4Game::class);
    }

    /**
     * Retourne la partie courante (phase RUNNING, non expirée), la plus récente.
     */
    public function findRunning(): ?Game4Game
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('g')
            ->andWhere('g.phase = :p')->setParameter('p', Game4Game::PHASE_RUNNING)
            ->andWhere('g.endsAt IS NULL OR g.endsAt > :now')->setParameter('now', $now)
            ->orderBy('g.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * Garantit qu’une partie "running" existe.
     * Si aucune partie en cours n’existe, on en crée une.
     *
     * @param int|null $minutes Durée en minutes (null = sans date de fin).
     */
    public function ensureRunning(?int $minutes = 15): Game4Game
    {
        $running = $this->findRunning();
        if ($running) {
            return $running;
        }

        return $this->startNew($minutes);
    }

    /**
     * Crée une nouvelle partie en phase RUNNING.
     *
     * @param int|null $minutes Durée de la partie (null => pas d’endsAt)
     */
    public function startNew(?int $minutes = 15): Game4Game
    {
        $game = new Game4Game();
        $game->setPhase(Game4Game::PHASE_RUNNING);

        if ($minutes !== null && $minutes > 0) {
            $game->setEndsAt((new \DateTimeImmutable())->modify(sprintf('+%d minutes', $minutes)));
        } else {
            $game->setEndsAt(null);
        }

        $this->save($game, true);
        return $game;
    }

    /**
     * Passe la partie en FINISHED et coupe son endsAt.
     */
    public function markFinished(Game4Game $game, bool $flush = true): void
    {
        $game->setPhase(Game4Game::PHASE_FINISHED);
        $game->setEndsAt(new \DateTimeImmutable());
        $this->save($game, $flush);
    }

    /**
     * Prolonge la partie.
     */
    public function extendEndsAt(Game4Game $game, int $minutes, bool $flush = true): void
    {
        $base = $game->getEndsAt() ?? new \DateTimeImmutable();
        $game->setEndsAt($base->modify(sprintf('+%d minutes', $minutes)));
        $this->save($game, $flush);
    }

    /**
     * Persist + (optionnel) flush.
     */
    public function save(Game4Game $entity, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);

        if ($flush) {
            $em->flush();
        }
    }
}
