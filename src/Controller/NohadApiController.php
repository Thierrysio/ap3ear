<?php

namespace App\Controller;

use App\Entity\JeuNohad\Choice;
use App\Entity\JeuNohad\Question;
use App\Entity\JeuNohad\Quiz;
use App\Entity\JeuNohad\Score;
use App\Entity\JeuNohad\UserResponse;
use App\Repository\JeuNohad\ChoiceRepository;
use App\Repository\JeuNohad\QuestionRepository;
use App\Repository\JeuNohad\QuizRepository;
use App\Repository\JeuNohad\ScoreRepository;
use App\Repository\JeuNohad\UserResponseRepository;
use App\Repository\NohadQuestionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NohadApiController extends AbstractController
{
    #[Route('/nohad/api', name: 'app_nohad_api')]
    public function index(): Response
    {
        return $this->render('nohad_api/index.html.twig', [
            'controller_name' => 'NohadApiController',
        ]);
    }

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

    #[Route('/api/nohad/quizzes', name: 'api_nohad_quizzes', methods: ['GET'])]
    public function getQuizzes(QuizRepository $quizRepository): JsonResponse
    {
        $data = [];
        foreach ($quizRepository->findAll() as $quiz) {
            $data[] = $this->serializeQuiz($quiz);
        }

        return $this->json($data);
    }

    #[Route('/api/nohad/quizzes', name: 'api_nohad_quiz_create', methods: ['POST'])]
    public function createQuiz(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = $request->toArray();
        $quiz = (new Quiz())
            ->setTitre($payload['titre'] ?? 'Nouveau quizz')
            ->setDescription($payload['description'] ?? null);

        $entityManager->persist($quiz);
        $entityManager->flush();

        return $this->json($this->serializeQuiz($quiz), Response::HTTP_CREATED);
    }

    #[Route('/api/nohad/quizzes/{id}', name: 'api_nohad_quiz_update', methods: ['PUT', 'PATCH'])]
    public function updateQuiz(int $id, Request $request, QuizRepository $quizRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $quiz = $quizRepository->find($id);
        if (!$quiz) {
            return $this->json(['message' => 'Quiz introuvable'], Response::HTTP_NOT_FOUND);
        }

        $payload = $request->toArray();
        if (isset($payload['titre'])) {
            $quiz->setTitre($payload['titre']);
        }
        if (array_key_exists('description', $payload)) {
            $quiz->setDescription($payload['description']);
        }

        $entityManager->flush();

        return $this->json($this->serializeQuiz($quiz));
    }

    #[Route('/api/nohad/quizzes/{id}', name: 'api_nohad_quiz_delete', methods: ['DELETE'])]
    public function deleteQuiz(int $id, QuizRepository $quizRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $quiz = $quizRepository->find($id);
        if (!$quiz) {
            return $this->json(['message' => 'Quiz introuvable'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($quiz);
        $entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/nohad/quizzes/{id}/questions', name: 'api_nohad_question_create', methods: ['POST'])]
    public function createQuestion(
        int $id,
        Request $request,
        QuizRepository $quizRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $quiz = $quizRepository->find($id);
        if (!$quiz) {
            return $this->json(['message' => 'Quiz introuvable'], Response::HTTP_NOT_FOUND);
        }

        $payload = $request->toArray();
        $question = (new Question())
            ->setQuiz($quiz)
            ->setEnonce($payload['enonce'] ?? 'Question')
            ->setDureeSecondes((int) ($payload['dureeSecondes'] ?? 30));

        foreach ($payload['choices'] ?? [] as $choiceData) {
            $choice = (new Choice())
                ->setTexte($choiceData['texte'] ?? '')
                ->setIsCorrect((bool) ($choiceData['isCorrect'] ?? false));
            $question->addChoice($choice);
        }

        $entityManager->persist($question);
        $entityManager->flush();

        return $this->json($this->serializeQuestion($question), Response::HTTP_CREATED);
    }

    #[Route('/api/nohad/questions/{id}', name: 'api_nohad_question_update', methods: ['PUT', 'PATCH'])]
    public function updateQuestion(
        int $id,
        Request $request,
        QuestionRepository $questionRepository,
        EntityManagerInterface $entityManager,
        ChoiceRepository $choiceRepository
    ): JsonResponse {
        $question = $questionRepository->find($id);
        if (!$question) {
            return $this->json(['message' => 'Question introuvable'], Response::HTTP_NOT_FOUND);
        }

        $payload = $request->toArray();
        if (isset($payload['enonce'])) {
            $question->setEnonce($payload['enonce']);
        }
        if (isset($payload['dureeSecondes'])) {
            $question->setDureeSecondes((int) $payload['dureeSecondes']);
        }

        if (isset($payload['choices'])) {
            foreach ($payload['choices'] as $choiceData) {
                if (!isset($choiceData['id'])) {
                    $choice = (new Choice())
                        ->setTexte($choiceData['texte'] ?? '')
                        ->setIsCorrect((bool) ($choiceData['isCorrect'] ?? false));
                    $question->addChoice($choice);
                    continue;
                }

                $choice = $choiceRepository->find($choiceData['id']);
                if (!$choice) {
                    continue;
                }

                if (isset($choiceData['texte'])) {
                    $choice->setTexte($choiceData['texte']);
                }
                if (isset($choiceData['isCorrect'])) {
                    $choice->setIsCorrect((bool) $choiceData['isCorrect']);
                }
            }
        }

        $entityManager->flush();

        return $this->json($this->serializeQuestion($question));
    }

    #[Route('/api/nohad/questions/{id}', name: 'api_nohad_question_delete', methods: ['DELETE'])]
    public function deleteQuestion(int $id, QuestionRepository $questionRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $question = $questionRepository->find($id);
        if (!$question) {
            return $this->json(['message' => 'Question introuvable'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($question);
        $entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/nohad/questions/{id}/responses', name: 'api_nohad_question_answer', methods: ['POST'])]
    public function answerQuestion(
        int $id,
        Request $request,
        QuestionRepository $questionRepository,
        ChoiceRepository $choiceRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $question = $questionRepository->find($id);
        if (!$question) {
            return $this->json(['message' => 'Question introuvable'], Response::HTTP_NOT_FOUND);
        }

        $payload = $request->toArray();
        $user = isset($payload['userId']) ? $userRepository->find($payload['userId']) : null;
        $choice = isset($payload['choiceId']) ? $choiceRepository->find($payload['choiceId']) : null;
        $responseTime = (int) ($payload['responseTimeMs'] ?? 0);

        if (!$user || !$choice || $choice->getQuestion() !== $question) {
            return $this->json(['message' => 'Utilisateur ou choix invalide'], Response::HTTP_BAD_REQUEST);
        }

        $response = (new UserResponse())
            ->setUser($user)
            ->setQuestion($question)
            ->setChoice($choice)
            ->setResponseTimeMs($responseTime);

        $entityManager->persist($response);
        $entityManager->flush();

        return $this->json([
            'id' => $response->getId(),
            'questionId' => $question->getId(),
            'userId' => $user->getId(),
            'choiceId' => $choice->getId(),
            'responseTimeMs' => $response->getResponseTimeMs(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/nohad/questions/{id}/fastest', name: 'api_nohad_question_fastest', methods: ['POST'])]
    public function rewardFastest(
        int $id,
        QuestionRepository $questionRepository,
        UserResponseRepository $userResponseRepository,
        ScoreRepository $scoreRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $question = $questionRepository->find($id);
        if (!$question) {
            return $this->json(['message' => 'Question introuvable'], Response::HTTP_NOT_FOUND);
        }

        $fastest = $userResponseRepository->findFastestResponseForQuestion($question);
        if (!$fastest) {
            return $this->json(['message' => 'Aucune réponse enregistrée'], Response::HTTP_NOT_FOUND);
        }

        $score = $scoreRepository->findOneBy(['user' => $fastest->getUser()]);
        if (!$score) {
            $score = (new Score())->setUser($fastest->getUser());
            $entityManager->persist($score);
        }

        $score->addPoints(100);
        $entityManager->flush();

        return $this->json([
            'userId' => $fastest->getUser()->getId(),
            'questionId' => $question->getId(),
            'responseId' => $fastest->getId(),
            'newPoints' => $score->getPoints(),
            'awarded' => 100,
        ]);
    }

    #[Route('/api/nohad/leaderboard', name: 'api_nohad_leaderboard', methods: ['GET'])]
    public function leaderboard(ScoreRepository $scoreRepository): JsonResponse
    {
        $leaders = [];
        foreach ($scoreRepository->getLeaderboard() as $score) {
            $leaders[] = [
                'userId' => $score->getUser()->getId(),
                'points' => $score->getPoints(),
                'updatedAt' => $score->getUpdatedAt()->format(DATE_ATOM),
            ];
        }

        return $this->json($leaders);
    }

    private function serializeQuiz(Quiz $quiz): array
    {
        $questions = [];
        foreach ($quiz->getQuestions() as $question) {
            $questions[] = $this->serializeQuestion($question);
        }

        return [
            'id' => $quiz->getId(),
            'titre' => $quiz->getTitre(),
            'description' => $quiz->getDescription(),
            'createdAt' => $quiz->getCreatedAt()->format(DATE_ATOM),
            'questions' => $questions,
        ];
    }

    private function serializeQuestion(Question $question): array
    {
        $choices = [];
        foreach ($question->getChoices() as $choice) {
            $choices[] = [
                'id' => $choice->getId(),
                'texte' => $choice->getTexte(),
                'isCorrect' => $choice->isCorrect(),
            ];
        }

        return [
            'id' => $question->getId(),
            'enonce' => $question->getEnonce(),
            'dureeSecondes' => $question->getDureeSecondes(),
            'quizId' => $question->getQuiz()?->getId(),
            'choices' => $choices,
        ];
    }
}
