<?php

namespace App\Repository;

use App\Entity\Game4Duel;
use App\Entity\Game4DuelPlay;
use App\Entity\Game4Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game4DuelPlay>
 */
class Game4DuelPlayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game4DuelPlay::class);
    }

    // ------------------------------------------------------------------
    // Sélecteurs de base
    // ------------------------------------------------------------------

    public function findOneByDuelAndPlayer(Game4Duel $duel, Game4Player $player): ?Game4DuelPlay
    {
        return $this->findOneBy(['duel' => $duel, 'player' => $player]);
    }

    /** @return Game4DuelPlay[] */
    public function findAllByDuel(Game4Duel $duel): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.duel = :d')->setParameter('d', $duel)
            ->orderBy('p.submittedAt', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return Game4DuelPlay[] */
    public function findByDuel(Game4Duel $duel): array
    {
        // alias de findAllByDuel pour rétro-compat
        return $this->findAllByDuel($duel);
    }

    // ------------------------------------------------------------------
    // Utilitaires “duel”
    // ------------------------------------------------------------------

    /**
     * Upsert: crée ou met à jour le play d’un joueur pour un duel.
     * Ne flush PAS (laisse le service appelant gérer la transaction).
     */
    public function upsertPlay(
        Game4Duel $duel,
        Game4Player $player,
        ?string $cardCode,
        ?string $cardType,
        ?\DateTimeImmutable $submittedAt = null
    ): Game4DuelPlay {
        $play = $this->findOneByDuelAndPlayer($duel, $player);
        if (!$submittedAt) {
            $submittedAt = new \DateTimeImmutable();
        }

        if (!$play) {
            $play = new Game4DuelPlay();
            $play->setDuel($duel)->setPlayer($player);
        }

        // card (relation) est gérée au service si besoin; ici on stocke le snapshot code/type
        $play->setCardCode((string)$cardCode);
        $play->setCardType((string)$cardType);
        $play->setSubmittedAt($submittedAt);

        $em = $this->getEntityManager();
        $em->persist($play);

        return $play;
    }

    /**
     * Renvoie les deux plays (joueur A et B) si disponibles.
     * @return Game4DuelPlay[] tableau 0..2 ordonné par submittedAt
     */
    public function getBothPlays(Game4Duel $duel): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.duel = :d')->setParameter('d', $duel)
            ->orderBy('p.submittedAt', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * True si les 2 joueurs ont déjà soumis.
     */
    public function hasBothPlays(Game4Duel $duel): bool
    {
        $count = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.duel = :d')->setParameter('d', $duel)
            ->getQuery()->getSingleScalarResult();

        return $count >= 2;
    }

    /**
     * Supprime tous les plays d’un duel (utile si recompute).
     * Ne flush PAS.
     */
    public function deleteByDuel(Game4Duel $duel): void
    {
        $this->createQueryBuilder('p')
            ->delete()
            ->andWhere('p.duel = :d')->setParameter('d', $duel)
            ->getQuery()->execute();
    }
}
