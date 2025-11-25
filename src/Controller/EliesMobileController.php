<?php

namespace App\Controller;

use App\Repository\TEquipeRepository;
use App\Repository\TEpreuveRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EliesMobileController extends AbstractController
{
    #[Route('/api/elies/mobile/EquipeScore', name: 'app_elies_mobile_index', methods: ['GET'])]
    public function EquipeScore(TEquipeRepository $teamRepository): JsonResponse
    {
        $teams = $teamRepository
            ->createQueryBuilder('team')
            ->leftJoin('team.lesUsers', 'user')
            ->addSelect('user')
            ->getQuery()
            ->getResult();

        $payload = [];
        foreach ($teams as $team) {
            $users = [];
            foreach ($team->getLesUsers() as $user) {
                $users[] = [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom(),
                ];
            }

            $payload[] = [
                'id' => $team->getId(),
                'nom' => $team->getNom(),
                'score' => $team->getScore(),
                'users' => $users,
            ];
        }

        return $this->json($payload);
    }

    #[Route('/api/mobile/nextEpreuve', name: 'app_mobile_next_epreuve', methods: ['POST'])]
public function nextEpreuve(TEpreuveRepository $epreuveRepository): JsonResponse
{
    $nextEpreuve = $epreuveRepository->findNextEpreuve();

    if (!$nextEpreuve) {
        return $this->json(
            ['message' => 'Aucune épreuve à venir.'],
            Response::HTTP_NOT_FOUND
        );
    }

    return $this->json([
        'id'        => $nextEpreuve->getId(),
        'nom'       => $nextEpreuve->getNom(),
        'dateDebut' => $nextEpreuve->getDatedebut()?->format(DATE_ATOM),
        'dureeMax'  => $nextEpreuve->getDureemax(),
    ]);
}

}
