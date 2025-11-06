<?php

namespace App\Repository;

use App\Entity\Game4Duel;
use App\Entity\Game4Game;
use App\Entity\Game4Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game4Player>
 *
 * Méthodes clés :
 * - findOneByGameAndEquipe() : retrouver un joueur par (game, equipeId)
 * - findAllByGame() / findAliveByGame() / countByGameAndRole()
 * - findByIds() / findOneByIdInGame()
 * - findOpponentsAlive() : tous les adversaires vivants (? moi)
 * - findByEquipeIds() : joueurs d’un jeu pour un ensemble d’équipes
 * - lockForDuel() / unlockFromDuel() : verrou duel UX/serveur
 * - markIncomingDuel() / clearIncomingDuel() : bannière "vous avez été choisi"
 * - save() : persist + (optionnel) flush
 */
class Game4PlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game4Player::class);
    }

    public function findOneByGameAndEquipe(Game4Game $game, int $equipeId): ?Game4Player
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.game = :g')
            ->andWhere('p.equipeId = :e')
            ->setParameter('g', $game)
            ->setParameter('e', $equipeId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Game4Player[] */
    public function findAllByGame(Game4Game $game): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.game = :g')
            ->setParameter('g', $game)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Game4Player[] */
    public function findAliveByGame(Game4Game $game): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.game = :g')
            ->andWhere('p.isAlive = 1')
            ->setParameter('g', $game)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByGameAndRole(Game4Game $game, string $role): int
    {
        return (int)$this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.game = :g')
            ->andWhere('p.role = :r')
            ->setParameter('g', $game)
            ->setParameter('r', $role)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère une liste de joueurs par leurs IDs (dans un même jeu).
     * @param int[] $playerIds
     * @return Game4Player[]
     */
    public function findByIds(Game4Game $game, array $playerIds): array
    {
        if (empty($playerIds)) return [];
        return $this->createQueryBuilder('p')
            ->andWhere('p.game = :g')->setParameter('g', $game)
            ->andWhere('p.id IN (:ids)')->setParameter('ids', $playerIds)
            ->getQuery()->getResult();
    }

    public function findOneByIdInGame(Game4Game $game, int $playerId): ?Game4Player
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.game = :g')->setParameter('g', $game)
            ->andWhere('p.id = :id')->setParameter('id', $playerId)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * Adversaires vivants d’un joueur (exclut lui-même).
     * @return Game4Player[]
     */
    public function findOpponentsAlive(Game4Game $game, Game4Player $me): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.game = :g')->setParameter('g', $game)
            ->andWhere('p.isAlive = 1')
            ->andWhere('p != :me')->setParameter('me', $me)
            ->orderBy('p.id', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Récupère les joueurs d’un jeu pour une liste d’ID d’équipes.
     * @param int[] $equipeIds
     * @return Game4Player[]
     */
    public function findByEquipeIds(Game4Game $game, array $equipeIds): array
    {
        if (empty($equipeIds)) return [];
        return $this->createQueryBuilder('p')
            ->andWhere('p.game = :g')->setParameter('g', $game)
            ->andWhere('p.equipeId IN (:eids)')->setParameter('eids', $equipeIds)
            ->orderBy('p.id', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Marque un joueur "verrouillé" dans un duel et enregistre
     * le duel entrant pour lui (utile pour la bannière côté client).
     */
    public function lockForDuel(Game4Player $player, ?Game4Duel $incoming = null, bool $flush = true): void
    {
        $player->setLockedInDuel(true);
        if ($incoming) {
            $player->setIncomingDuel($incoming);
        }
        $this->save($player, $flush);
    }

    /**
     * Déverrouille un joueur (fin de duel) et nettoie son duel entrant.
     */
    public function unlockFromDuel(Game4Player $player, bool $flush = true): void
    {
        $player->setLockedInDuel(false);
        $player->setIncomingDuel(null);
        $this->save($player, $flush);
    }

    /**
     * Spécifiquement paramétrer un duel entrant (pour la bannière),
     * sans toucher au flag lockedInDuel.
     */
    public function markIncomingDuel(Game4Player $player, Game4Duel $duel, bool $flush = true): void
    {
        $player->setIncomingDuel($duel);
        $this->save($player, $flush);
    }

    /**
     * Efface le duel entrant affiché pour le joueur.
     */
    public function clearIncomingDuel(Game4Player $player, bool $flush = true): void
    {
        $player->setIncomingDuel(null);
        $this->save($player, $flush);
    }

    /**
     * Persist + (optionnel) flush.
     */
    public function save(Game4Player $entity, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);

        if ($flush) {
            $em->flush();
        }
    }
}
