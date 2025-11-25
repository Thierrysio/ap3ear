<?php

namespace App\Controller;

<<<<<<< HEAD
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

use App\Repository\NohadQuestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

=======
use App\Repository\NohadQuestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
>>>>>>> 15d1d819cdef734eec7c5fafff16b2ab414bc63f
use Symfony\Component\Routing\Attribute\Route;

final class NohadApiController extends AbstractController
{
<<<<<<< HEAD

=======
>>>>>>> 15d1d819cdef734eec7c5fafff16b2ab414bc63f
    #[Route('/nohad/api', name: 'app_nohad_api')]
    public function index(): Response
    {
        return $this->render('nohad_api/index.html.twig', [
            'controller_name' => 'NohadApiController',
        ]);
<<<<<<< HEAD
=======
    }
>>>>>>> 15d1d819cdef734eec7c5fafff16b2ab414bc63f

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
