<?php

namespace App\Controller;

use App\Repository\TEquipeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class EliesMobileController extends AbstractController
{
    #[Route('/api/elies/mobile/', name: 'app_elies_mobile_index', methods: ['GET'])]
    public function list(TEquipeRepository $teamRepository): JsonResponse
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
}
