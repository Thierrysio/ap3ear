<?php

namespace App\Controller;

use App\Entity\AppliedEffect;
use App\Entity\FutureOption;
use App\Entity\GameSession;
use App\Entity\Round;
use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin', name: 'admin_game_')]
class AdminGameSessionController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/game-sessions', name: 'create_session', methods: ['POST'])]
    public function createSession(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        if ($data === null) {
            return $this->errorResponse('INVALID_JSON', 'Payload invalide.');
        }

        $gameSession = new GameSession();
        $gameSession
            ->setName((string) ($data['name'] ?? 'Partie'))
            ->setGameCode($this->generateGameCode())
            ->setMaxTeams((int) ($data['maxTeams'] ?? 10))
            ->setEvStart((int) ($data['evStart'] ?? 15))
            ->setChoiceTimeoutSec((int) ($data['choiceTimeoutSec'] ?? 15))
            ->setGpsEnabled((bool) ($data['gpsEnabled'] ?? false))
            ->setGpsRadiusMeters(isset($data['gpsRadiusMeters']) ? (int) $data['gpsRadiusMeters'] : null)
            ->setSettings((array) ($data['settings'] ?? []))
            ->setTepreuveId(isset($data['tepreuveId']) ? (int) $data['tepreuveId'] : null);

        $this->entityManager->persist($gameSession);
        $this->entityManager->flush();

        return $this->successResponse([
            'gameSessionId' => $gameSession->getId(),
            'gameCode' => $gameSession->getGameCode(),
        ], 201);
    }

    #[Route('/game-sessions/{id}/teams', name: 'create_team', methods: ['POST'])]
    public function createTeam(string $id, Request $request): JsonResponse
    {
        $session = $this->entityManager->getRepository(GameSession::class)->find($id);
        if (!$session instanceof GameSession) {
            return $this->errorResponse('GAME_NOT_FOUND', 'Session introuvable.', 404);
        }

        $data = $this->decodeJson($request);
        if ($data === null) {
            return $this->errorResponse('INVALID_JSON', 'Payload invalide.');
        }

        $team = new Team();
        $team->setGameSession($session)
            ->setName((string) ($data['name'] ?? 'Equipe'))
            ->setMembersCount((int) ($data['membersCount'] ?? 1))
            ->setEvCurrent($session->getEvStart())
            ->setTequipeId(isset($data['tequipeId']) ? (int) $data['tequipeId'] : null);

        $this->entityManager->persist($team);
        $this->entityManager->flush();

        return $this->successResponse([
            'teamId' => $team->getId(),
            'ev' => $team->getEvCurrent(),
        ], 201);
    }

    #[Route('/game-sessions/{id}/rounds/init', name: 'init_rounds', methods: ['POST'])]
    public function initRounds(string $id, Request $request): JsonResponse
    {
        $session = $this->entityManager->getRepository(GameSession::class)->find($id);
        if (!$session instanceof GameSession) {
            return $this->errorResponse('GAME_NOT_FOUND', 'Session introuvable.', 404);
        }

        $data = $this->decodeJson($request);
        if ($data === null || !isset($data['rounds']) || !is_array($data['rounds'])) {
            return $this->errorResponse('INVALID_PAYLOAD', 'Rounds manquants ou invalides.');
        }

        foreach ($data['rounds'] as $roundData) {
            $round = new Round();
            $round->setGameSession($session)
                ->setIndex((int) ($roundData['index'] ?? 1))
                ->setTitle((string) ($roundData['title'] ?? ''))
                ->setTimeLimitSec((int) ($roundData['timeLimitSec'] ?? 0))
                ->setQrPayloadExpected((string) ($roundData['qrPayloadExpected'] ?? ''))
                ->setPhysicalChallenge((array) ($roundData['physicalChallenge'] ?? []));

            $this->entityManager->persist($round);

            foreach (($roundData['futureOptions'] ?? []) as $position => $futureData) {
                $future = new FutureOption();
                $future->setRound($round)
                    ->setCode((string) ($futureData['code'] ?? 'F' . ($position + 1)))
                    ->setTitle((string) ($futureData['title'] ?? ''))
                    ->setRiskLevel((string) ($futureData['riskLevel'] ?? 'GREEN'))
                    ->setImmediateEffects((array) ($futureData['immediateEffects'] ?? []))
                    ->setDelayedEffects((array) ($futureData['delayedEffects'] ?? []))
                    ->setDisplayOrder((int) ($futureData['displayOrder'] ?? ($position + 1)));

                $this->entityManager->persist($future);
            }
        }

        $this->entityManager->flush();

        return $this->successResponse(['roundsCreated' => count($data['rounds'])]);
    }

    #[Route('/game-sessions/{id}/start', name: 'start_session', methods: ['POST'])]
    public function startSession(string $id): JsonResponse
    {
        $session = $this->entityManager->getRepository(GameSession::class)->find($id);
        if (!$session instanceof GameSession) {
            return $this->errorResponse('GAME_NOT_FOUND', 'Session introuvable.', 404);
        }

        $session->setStatus(GameSession::STATUS_RUNNING);
        $this->entityManager->flush();

        return $this->successResponse(['status' => $session->getStatus()]);
    }

    #[Route('/game-sessions/{id}/rounds/{roundIndex}/start', name: 'start_round', methods: ['POST'])]
    public function startRound(string $id, int $roundIndex): JsonResponse
    {
        $session = $this->entityManager->getRepository(GameSession::class)->find($id);
        if (!$session instanceof GameSession) {
            return $this->errorResponse('GAME_NOT_FOUND', 'Session introuvable.', 404);
        }

        $round = $this->entityManager->getRepository(Round::class)->findOneBy([
            'gameSession' => $session,
            'index' => $roundIndex,
        ]);
        if (!$round instanceof Round) {
            return $this->errorResponse('ROUND_NOT_FOUND', 'Round introuvable.', 404);
        }

        $round->setStatus(Round::STATUS_ACTIVE);
        $round->setStartAt(new \DateTimeImmutable());
        $session->setCurrentRoundIndex($roundIndex);
        $this->entityManager->flush();

        return $this->successResponse([
            'roundId' => $round->getId(),
            'status' => $round->getStatus(),
        ]);
    }

    #[Route('/game-sessions/{id}/rounds/{roundIndex}/close', name: 'close_round', methods: ['POST'])]
    public function closeRound(string $id, int $roundIndex): JsonResponse
    {
        $session = $this->entityManager->getRepository(GameSession::class)->find($id);
        if (!$session instanceof GameSession) {
            return $this->errorResponse('GAME_NOT_FOUND', 'Session introuvable.', 404);
        }

        $round = $this->entityManager->getRepository(Round::class)->findOneBy([
            'gameSession' => $session,
            'index' => $roundIndex,
        ]);
        if (!$round instanceof Round) {
            return $this->errorResponse('ROUND_NOT_FOUND', 'Round introuvable.', 404);
        }

        $round->setStatus(Round::STATUS_CLOSED);
        $round->setEndAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->successResponse([
            'roundId' => $round->getId(),
            'status' => $round->getStatus(),
        ]);
    }

    #[Route('/game-sessions/{id}/live', name: 'live', methods: ['GET'])]
    public function live(string $id): JsonResponse
    {
        $session = $this->entityManager->getRepository(GameSession::class)->find($id);
        if (!$session instanceof GameSession) {
            return $this->errorResponse('GAME_NOT_FOUND', 'Session introuvable.', 404);
        }

        $round = $this->entityManager->getRepository(Round::class)->findOneBy([
            'gameSession' => $session,
            'index' => $session->getCurrentRoundIndex(),
        ]);

        $teams = array_map(static fn (Team $team) => [
            'teamId' => $team->getId(),
            'name' => $team->getName(),
            'ev' => $team->getEvCurrent(),
            'status' => $team->getStatus(),
        ], $session->getTeams()->toArray());

        $futures = $round ? array_map(static fn (FutureOption $option) => [
            'code' => $option->getCode(),
            'risk' => $option->getRiskLevel(),
            'isTaken' => $option->getFuturePicks()->count() > 0,
        ], $round->getFutureOptions()->toArray()) : [];

        return $this->successResponse([
            'currentRoundIndex' => $session->getCurrentRoundIndex(),
            'roundStatus' => $round?->getStatus(),
            'teams' => $teams,
            'futureOptions' => $futures,
        ]);
    }

    #[Route('/rounds/{roundId}/teams/{teamId}/assign-future', name: 'assign_future', methods: ['POST'])]
    public function assignFuture(string $roundId, string $teamId, Request $request): JsonResponse
    {
        $round = $this->entityManager->getRepository(Round::class)->find($roundId);
        $team = $this->entityManager->getRepository(Team::class)->find($teamId);
        if (!$round instanceof Round || !$team instanceof Team) {
            return $this->errorResponse('NOT_FOUND', 'Round ou équipe introuvable.', 404);
        }

        $data = $this->decodeJson($request);
        if (!isset($data['futureOptionId'])) {
            return $this->errorResponse('INVALID_PAYLOAD', 'futureOptionId requis.');
        }

        $future = $this->entityManager->getRepository(FutureOption::class)->find($data['futureOptionId']);
        if (!$future instanceof FutureOption) {
            return $this->errorResponse('INVALID_FUTURE', 'Futur introuvable.', 404);
        }

        $pick = new \App\Entity\FuturePick();
        $pick->setRound($round)
            ->setTeam($team)
            ->setFutureOption($future)
            ->setPickMode(\App\Entity\FuturePick::MODE_ADMIN_ASSIGNED)
            ->setLocked(true);

        $this->entityManager->persist($pick);
        $this->entityManager->flush();

        return $this->successResponse(['pickId' => $pick->getId()]);
    }

    #[Route('/teams/{teamId}/adjust-ev', name: 'adjust_ev', methods: ['POST'])]
    public function adjustEv(string $teamId, Request $request): JsonResponse
    {
        $team = $this->entityManager->getRepository(Team::class)->find($teamId);
        if (!$team instanceof Team) {
            return $this->errorResponse('TEAM_NOT_FOUND', 'Equipe introuvable.', 404);
        }

        $data = $this->decodeJson($request);
        if ($data === null || !isset($data['delta'])) {
            return $this->errorResponse('INVALID_PAYLOAD', 'delta requis.');
        }

        $team->setEvCurrent($team->getEvCurrent() + (int) $data['delta']);
        $effect = new AppliedEffect();
        $effect->setTeam($team)
            ->setGameSession($team->getGameSession())
            ->setRound(null)
            ->setSourceType(AppliedEffect::SOURCE_PENALTY)
            ->setEffectType(AppliedEffect::EFFECT_EV_SUB)
            ->setPayload(['delta' => (int) $data['delta']])
            ->setAppliedAt(new \DateTimeImmutable());

        $this->entityManager->persist($effect);
        $this->entityManager->flush();

        return $this->successResponse(['ev' => $team->getEvCurrent()]);
    }

    private function successResponse(array $data, int $status = 200): JsonResponse
    {
        return $this->json(['success' => true, 'data' => $data], $status);
    }

    private function errorResponse(string $code, string $message, int $status = 400, array $details = []): JsonResponse
    {
        return $this->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }

    private function decodeJson(Request $request): ?array
    {
        $content = $request->getContent();
        if ($content === '') {
            return null;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    private function generateGameCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
