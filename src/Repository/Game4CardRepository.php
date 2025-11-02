<?php

namespace App\Repository;

use App\Entity\Game4Card;
use App\Entity\Game4CardDef;
use App\Entity\Game4Game;
use App\Entity\Game4Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;

/**
 * @extends ServiceEntityRepository<Game4Card>
 */
class Game4CardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game4Card::class);
    }

    public function countInZone(Game4Game $game, string $zone): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.game = :g')->setParameter('g', $game)
            ->andWhere('c.zone = :z')->setParameter('z', $zone)
            ->getQuery()->getSingleScalarResult();
    }
    
// Sélectionne et sort une carte précise (code) depuis le deck
// --- pickFromDeckByCode : remplace setParameters([...]) par setParameter() ---
public function pickFromDeckByCode(Game4Game $game, string $code): ?Game4Card
{
    $qb = $this->createQueryBuilder('c')
        ->join('c.def', 'd')
        ->andWhere('c.game = :g')
        ->andWhere('c.zone = :z')
        ->andWhere('d.code = :code')
        ->setMaxResults(1)
        ->setParameter('g', $game)
        ->setParameter('z', Game4Card::ZONE_DECK)
        ->setParameter('code', $code);

    $card = $qb->getQuery()->getOneOrNullResult();
    return $card ?: null;
}


// Privilégie une NUM lors de la pioche (pour atteindre rapidement les 5 NUM)
public function pickFromDeckPreferNumeric(Game4Game $game): ?Game4Card
{
    // 1) Tenter une NUM d’abord
    $qbNum = $this->createQueryBuilder('c')
        ->join('c.def', 'd')
        ->andWhere('c.game = :g')
        ->andWhere('c.zone = :z')
        ->andWhere('d.type = :t')
        ->setParameter('g', $game)
        ->setParameter('z', Game4Card::ZONE_DECK)
        ->setParameter('t', 'NUM')
        ->setMaxResults(1);

    $card = $qbNum->getQuery()->getOneOrNullResult();
    if ($card) return $card;

    // 2) Sinon n’importe quelle carte
    return $this->pickFromDeck($game);
}

// Comptages utiles
public function countSpecialsInHandByTypes(Game4Player $player, array $types): int
{
    $qb = $this->createQueryBuilder('c')
        ->select('COUNT(c.id)')
        ->join('c.def', 'd')
        ->andWhere('c.owner = :p')
        ->andWhere('c.zone  = :z')
        ->andWhere('UPPER(d.type) IN (:types)')
        ->setParameter('p', $player)
        ->setParameter('z', Game4Card::ZONE_HAND)
        ->setParameter('types', array_map('strtoupper', $types), Connection::PARAM_STR_ARRAY);

    return (int) $qb->getQuery()->getSingleScalarResult();
}

// --- countNumericsInHand : remplace setParameters([...]) par setParameter() ---
public function countNumericsInHand(Game4Player $player): int
{
    $qb = $this->createQueryBuilder('c')
        ->select('COUNT(c.id)')
        ->join('c.def', 'd')
        ->andWhere('c.owner = :p')
        ->andWhere('c.zone  = :z')
        ->andWhere('UPPER(d.type) = :t')
        ->setParameter('p', $player)
        ->setParameter('z', Game4Card::ZONE_HAND)
        ->setParameter('t', 'NUM');

    return (int) $qb->getQuery()->getSingleScalarResult();
}


public function pickNumericFromDeck(Game4Game $game): ?Game4Card
{
    return $this->createQueryBuilder('c')
        ->join('c.def', 'd')
        ->andWhere('c.game = :g')
        ->andWhere('c.zone = :z')
        ->andWhere('d.type = :t')
        ->setParameter('g', $game)
        ->setParameter('z', Game4Card::ZONE_DECK)
        ->setParameter('t', 'NUM')
        ->orderBy('c.id', 'ASC')
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
}


// --- handHasCode : remplace setParameters([...]) par setParameter() ---
public function handHasCode(Game4Player $player, string $code): bool
{
    $qb = $this->createQueryBuilder('c')
        ->select('COUNT(c.id)')
        ->join('c.def', 'd')
        ->andWhere('c.owner = :p')
        ->andWhere('c.zone  = :z')
        ->andWhere('d.code = :code')
        ->setParameter('p', $player)
        ->setParameter('z', Game4Card::ZONE_HAND)
        ->setParameter('code', $code);

    return (int) $qb->getQuery()->getSingleScalarResult() > 0;
}



    /** Sélectionne une carte aléatoire dans le deck du jeu (ZONE_DECK) */
    public function pickFromDeck(Game4Game $game): ?Game4Card
{
    $conn = $this->getEntityManager()->getConnection(); // ? au lieu de $this->_em

    $row = $conn->fetchAssociative(
        'SELECT id
           FROM game4_card
          WHERE game_id = :g AND zone = :z
       ORDER BY RAND()
          LIMIT 1',
        [
            'g' => $game->getId(),
            'z' => Game4Card::ZONE_DECK,
        ]
    );

    if (!$row || !isset($row['id'])) {
        return null;
    }
    return $this->find($row['id']);
}


    /** Compte les cartes d’un joueur dans une zone donnée (utile pour la main à 7) */
    public function countByOwnerAndZone(Game4Player $owner, string $zone): int
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('COUNT(c.id)')
           ->andWhere('c.owner = :o')
           ->andWhere('c.zone  = :z')
           ->setParameter('o', $owner)
           ->setParameter('z', $zone);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findByToken(string $token): ?Game4Card
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * Toutes les cartes en main d’un joueur.
     * @return Game4Card[]
     */
    public function findHandByPlayer(Game4Player $player): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.owner = :p')->setParameter('p', $player)
            ->andWhere('c.zone = :z')->setParameter('z', Game4Card::ZONE_HAND)
            ->orderBy('c.id', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Trouve un Game4CardDef par code (ex: 'ZOMBIE').
     * NB: repository de CardDef via EM (quand besoin), mais on garde un helper ici si tu veux.
     */
    public function findOneDefByCode(string $code): ?Game4CardDef
    {
        return $this->getEntityManager()
            ->getRepository(Game4CardDef::class)
            ->findOneBy(['code' => $code]);
    }

    /**
     * Supprime UNE carte 'ZOMBIE' dans la main du joueur si présente.
     * Retourne true si suppression effectuée.
     */
    public function removeOneZombieCardFromHand(Game4Player $player): bool
    {
        $cards = $this->findHandByPlayer($player);
        foreach ($cards as $c) {
            $def = $c->getDef();
            if (!$def) continue;

            $isZombie = strtoupper($def->getType()) === 'ZOMBIE' || strtoupper($def->getCode()) === 'ZOMBIE';
            if ($isZombie) {
                $em = $this->getEntityManager();
                $em->remove($c);
                return true;
            }
        }
        return false;
    }
}
