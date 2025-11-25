<?php

namespace App\Controller;

use App\Repository\NohadQuestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class NohadApiController extends AbstractController
{
    #[Route('/api/mobile/nohadQuizz', name: 'api_mobile_nohad_quizz', methods: ['GET'])]
    public function list(NohadQuestionRepository $questionRepository): JsonResponse
    {
        $questions = $questionRepository->findAllWithReponses();

        $payload = [];
        foreach ($questions as $question) {
            $reponses = [];
            foreach ($question->getReponses() as $reponse) {
                $reponses[] = [
                    'id' => $reponse->getId(),
                    'identifiant' => $reponse->getIdentifiant(),
                    'texte' => $reponse->getTexte(),
                ];
            }

            $payload[] = [
                'id' => $question->getId(),
                'enonce' => $question->getEnonce(),
                'dureeSecondes' => $question->getDureeSecondes(),
                'reponses' => $reponses,
            ];
        }

        return $this->json($payload);
    }
}
