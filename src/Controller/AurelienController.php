<?php

namespace App\Controller;

use App\Entity\Aurelien\Lieu;
use App\Entity\TCompetition;
use App\Repository\Aurelien\LieuRepository;
use App\Repository\Aurelien\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
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
            ->orderBy('RAND()')
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

    #[Route('/api/mobile/competition', name: 'app_aurelien_competition_create', methods: ['POST'])]
    public function createCompetition(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return $this->json([
                'message' => 'Corps de requête JSON invalide.',
            ], Response::HTTP_BAD_REQUEST);
        }

        foreach (['dateDebut', 'dateFin'] as $field) {
            if (empty($payload[$field])) {
                return $this->json([
                    'message' => sprintf('Le champ "%s" est requis.', $field),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $dateDebut = new \DateTime((string) $payload['dateDebut']);
            $dateFin = new \DateTime((string) $payload['dateFin']);
        } catch (\Exception) {
            return $this->json([
                'message' => 'Format de date invalide. Utilisez une date ISO 8601.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($dateFin->getTimestamp() <= $dateDebut->getTimestamp()) {
            return $this->json([
                'message' => 'La date de fin doit être postérieure à la date de début.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $competition = new TCompetition();
        $competition->setDateDebut($dateDebut);
        $competition->setDatefin($dateFin);

        if (!empty($payload['nom'])) {
            $competition->setNom((string) $payload['nom']);
        }

        $entityManager->persist($competition);
        $entityManager->flush();

        return $this->json([
            'id' => $competition->getId(),
            'dateDebut' => $competition->getDateDebut()?->format(DATE_ATOM),
            'dateFin' => $competition->getDatefin()?->format(DATE_ATOM),
            'nom' => $competition->getNom(),
        ], Response::HTTP_CREATED);
    }
}
