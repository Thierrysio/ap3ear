<?php

namespace App\Services;

use App\Entity\TEquipe;
use App\Entity\TEpreuve;
use App\Entity\TScore;
use App\Entity\Tflag;
use App\Repository\TflagRepository;
use App\Repository\TScoreRepository;
use App\Repository\TEpreuve3FinishRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class Epreuve3Manager
{
    public function __construct(
        private TflagRepository $flagRepository,
        private TScoreRepository $scoreRepository,
        private TEpreuve3FinishRepository $finishRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private string $secretToken
    ) {}

    /**
     * Traite la validation d'un token et retourne le prochain flag
     */
    public function processNextFlag(string $token, TEquipe $equipe, TEpreuve $epreuve): ?array
    {
        $this->logger->info("Validation token pour l'équipe {$equipe->getId()}, épreuve {$epreuve->getId()}");

        // 1. Calcul du hash côté serveur
        $scannedTokenHash = hash('sha256', $token);
        
        // 2. Récupération du prochain flag pour l'équipe
        $nextFlag = $this->getNextFlagForEquipe($equipe, $epreuve);
        
        
        
        if (!$nextFlag) {
            $this->logger->warning("Plus de flags disponibles pour l'équipe {$equipe->getId()}");
            return null;
        }
        
        // 3. Vérification que le token correspond au flag attendu
        $expectedToken = $this->generateExpectedToken($nextFlag->getId(), $equipe->getId());
        
        
        if (!hash_equals(hash('sha256', $expectedToken), hash('sha256', $token))) {
            $this->logger->warning("Token invalide pour l'équipe {$equipe->getId()}, flag {$nextFlag->getId()}");
            return null;
        }
        
        // 4. Marquer le flag comme trouvé (créer un score)
        
        
        $this->logger->info("Flag {$nextFlag->getId()} validé pour l'équipe {$equipe->getId()}");

        return [
            'valid' => true,
            'lat' => $nextFlag->getLatitude(),
            'lon' => $nextFlag->getLongitude(),
            'hint' => $nextFlag->getIndication(),
            'timeoutSec' => $nextFlag->getTimeoutSec(),
            'scannedTokenHash' => $scannedTokenHash,
            'flagId' => $nextFlag->getId()
        ];
    }

    /**
     * Récupère le prochain flag à trouver pour une équipe
     */
    private function getNextFlagForEquipe(TEquipe $equipe, TEpreuve $epreuve): ?Tflag
    {
        // 1. Compter combien de flags l'équipe a déjà trouvés
        $flagsFound = $this->countFlagsFound($equipe, $epreuve);
        
        // 2. Récupérer le prochain flag dans la séquence
        return $this->flagRepository->findNextFlagForEpreuve(
            $epreuve->getId(),
            $flagsFound + 1
        );
    }

    /**
     * Compte les flags déjà trouvés par l'équipe
     */
    private function countFlagsFound(TEquipe $equipe, TEpreuve $epreuve): int
    {
        return $this->scoreRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.latEquipe = :equipe')
            ->andWhere('s.latEpreuve = :epreuve')
            ->setParameter('equipe', $equipe)
            ->setParameter('epreuve', $epreuve)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Génère le token attendu pour un flag
     */
    private function generateExpectedToken(int $flagId, int $equipeId): string
    {
        $payload = $flagId . '_' . $equipeId . '_' . $this->secretToken;
        
        return 'FLAG_' . $flagId . '_EQ_' . $equipeId . '_' . hash('sha256', $payload);
    }

    /**
     * Vérifie si une équipe a terminé l'épreuve
     */
    public function hasCompletedEpreuve(TEquipe $equipe, TEpreuve $epreuve): bool
    {
        $totalFlags = $this->flagRepository->countFlagsForEpreuve($epreuve->getId());
        $flagsFound = $this->countFlagsFound($equipe, $epreuve);
        
        return $flagsFound >= $totalFlags;
    }

    /**
     * Marque une équipe comme ayant terminé l'épreuve
     */
    public function markEpreuveAsFinished(TEquipe $equipe, TEpreuve $epreuve): int
    {
        if ($this->finishRepository->hasEquipeFinished($equipe->getId(), $epreuve->getId())) {
            return $this->finishRepository->findPositionForEquipe($equipe->getId(), $epreuve->getId());
        }

        $finish = new \App\Entity\TEpreuve3Finish();
        $finish->setEquipeId($equipe->getId());
        $finish->setEpreuveId($epreuve->getId());
        
        $this->em->persist($finish);
        $this->em->flush();

        return $this->finishRepository->findPositionForEquipe($equipe->getId(), $epreuve->getId());
    }
}