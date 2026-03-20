<?php

namespace App\Controller;

use App\Entity\Aurelien\Lieu;
use App\Repository\Aurelien\LieuRepository;
use App\Repository\Aurelien\QuestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AurelienController extends AbstractController
{
    #[Route('/api/mobile/jeu/questions', name: 'app_jeu_questions', methods: ['GET'])]
    public function questions(QuestionRepository $questionRepository): JsonResponse
    {
        $questions = $questionRepository
            ->createQueryBuilder('question')
            ->leftJoin('question.reponses', 'reponse')
            ->addSelect('reponse')
            ->leftJoin('question.lieu', 'lieu')
            ->addSelect('lieu')
            ->addSelect('RAND() as HIDDEN random_order')
            ->orderBy('random_order')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $payload = array_map(static function ($question) {
            $reponses = [];
            foreach ($question->getReponses() as $reponse) {
                $reponses[] = [
                    'id' => $reponse->getId(),
                    'libelle' => $reponse->getLibelle(),
                    'est_correcte' => $reponse->isEstCorrecte(),
                ];
            }

            return [
                'id' => $question->getId(),
                'libelle' => $question->getLibelle(),
                'lieu' => [
                    'id' => $question->getLieu()?->getId(),
                    'nom' => $question->getLieu()?->getNom(),
                ],
                'reponses' => $reponses,
            ];
        }, $questions);

        return $this->json($payload);
    }

    #[Route('/api/mobile/jeu/lieu/{id}', name: 'app_jeu_lieu', methods: ['GET'])]
    public function lieu(Lieu $lieu): JsonResponse
    {
        return $this->json([
            'id' => $lieu->getId(),
            'nom' => $lieu->getNom(),
            'image_url' => $lieu->getImageUrl(),
        ]);
    }

    #[Route('/api/mobile/jeu/valider', name: 'app_jeu_valider', methods: ['POST'])]
    public function valider(Request $request, LieuRepository $lieuRepository): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload) || !isset($payload['lieu_id'], $payload['code_qr'], $payload['score'])) {
            return $this->json([
                'message' => 'Paramètres manquants : lieu_id, code_qr et score sont requis.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $lieu = $lieuRepository->find((int) $payload['lieu_id']);

        if (!$lieu) {
            return $this->json([
                'message' => 'Lieu introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        $isValid = hash_equals($lieu->getCodeQr(), (string) $payload['code_qr']);

        return $this->json([
            'valide' => $isValid,
            'score' => $isValid ? (int) $payload['score'] : 0,
            'lieu' => [
                'id' => $lieu->getId(),
                'nom' => $lieu->getNom(),
            ],
            'message' => $isValid ? 'QR Code valide.' : 'QR Code invalide.',
        ], $isValid ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }
}
