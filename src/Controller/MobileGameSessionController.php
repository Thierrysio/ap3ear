<?php

namespace App\Controller;

use App\Entity\FutureOption;
use App\Entity\FuturePick;
use App\Entity\GameSession;
use App\Entity\QrScan;
use App\Entity\Round;
use App\Entity\Team;
use App\Repository\FuturePickRepository;
use App\Repository\FutureOptionRepository;
use App\Repository\GameSessionRepository;
use App\Repository\QrScanRepository;
use App\Repository\RoundRepository;
use App\Repository\TeamRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mobile', name: 'mobile_game_')]
class MobileGameSessionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameSessionRepository $gameSessionRepository,
        private TeamRepository $teamRepository,
        private RoundRepository $roundRepository,
        private FutureOptionRepository $futureOptionRepository,
        private FuturePickRepository $futurePickRepository,
        private QrScanRepository $qrScanRepository,
    ) {
    }

    #[Route('/auth/join', name: 'join', methods: ['POST'])]
    public function join(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        if ($data === null) {
            return $this->errorResponse('INVALID_JSON', 'Payload invalide.');
        }

        $gameCode = trim((string) ($data['gameCode'] ?? ''));
        $teamName = trim((string) ($data['teamName'] ?? ''));
        $membersCount = (int) ($data['membersCount'] ?? 0);

        if ($gameCode === '' || $teamName === '' || $membersCount <= 0) {
            return $this->errorResponse('INVALID_INPUT', 'gameCode, teamName et membersCount sont requis.');
        }

        $gameSession = $this->gameSessionRepository->findOneBy(['gameCode' => $gameCode]);
        if (!$gameSession instanceof GameSession) {
            return $this->errorResponse('GAME_NOT_FOUND', 'Aucune partie trouvée pour ce gameCode.', 404);
        }

        $team = new Team();
        $team->setGameSession($gameSession)
            ->setName($teamName)
            ->setMembersCount($membersCount)
            ->setDeviceId($data['deviceId'] ?? null)
            ->setEvCurrent($gameSession->getEvStart())
            ->setTequipeId(isset($data['tequipeId']) ? (int) $data['tequipeId'] : null);

        $this->entityManager->persist($team);
        $this->entityManager->flush();

        return $this->successResponse([
            'token' => 'todo-bearer-token',
            'gameSessionId' => $gameSession->getId(),
            'teamId' => $team->getId(),
            'ev' => $team->getEvCurrent(),
        ], 201);
    }

    #[Route('/game-sessions/{id}/state', name: 'state', methods: ['GET'])]
    public function state(string $id, Request $request): JsonResponse
    {
        $gameSession = $this->gameSessionRepository->find($id);
        if (!$gameSession instanceof GameSession) {
            return $this->errorResponse('GAME_NOT_FOUND', 'Partie introuvable.', 404);
        }

        $teamId = $request->query->get('teamId');
        $team = $teamId ? $this->teamRepository->find($teamId) : null;

        $round = $this->roundRepository->findOneBy([
            'gameSession' => $gameSession,
            'index' => $gameSession->getCurrentRoundIndex(),
        ]);

        $scan = null;
        if ($round && $team) {
            $scan = $this->qrScanRepository->findOneBy([
                'round' => $round,
                'team' => $team,
            ]);
        }

        $pick = null;
        if ($round && $team) {
            $pick = $this->futurePickRepository->findOneBy([
                'round' => $round,
                'team' => $team,
            ]);
        }

        return $this->successResponse([
            'gameSession' => [
                'id' => $gameSession->getId(),
                'status' => $gameSession->getStatus(),
                'currentRoundIndex' => $gameSession->getCurrentRoundIndex(),
            ],
            'team' => $team ? [
                'id' => $team->getId(),
                'name' => $team->getName(),
                'ev' => $team->getEvCurrent(),
                'status' => $team->getStatus(),
            ] : null,
            'round' => $round ? [
                'id' => $round->getId(),
                'index' => $round->getIndex(),
                'status' => $round->getStatus(),
                'timeRemainingSec' => null,
                'physicalChallenge' => $round->getPhysicalChallenge(),
            ] : null,
            'scan' => $scan ? [
                'scanStatus' => $scan->getScanStatus(),
                'rank' => $scan->getRank(),
                'scannedAt' => $scan->getScannedAt()->format(DATE_ATOM),
            ] : null,
            'pick' => $pick ? [
                'picked' => true,
                'futureCode' => $pick->getFutureOption()->getCode(),
                'pickMode' => $pick->getPickMode(),
            ] : ['picked' => false],
        ]);
    }

    #[Route('/rounds/{roundId}/scan', name: 'scan', methods: ['POST'])]
    public function scan(string $roundId, Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        if ($data === null) {
            return $this->errorResponse('INVALID_JSON', 'Payload invalide.');
        }

        $round = $this->roundRepository->find($roundId);
        $teamId = (string) ($data['teamId'] ?? '');
        $team = $teamId !== '' ? $this->teamRepository->find($teamId) : null;
        if (!$round instanceof Round || !$team instanceof Team) {
            return $this->errorResponse('NOT_FOUND', 'Round ou équipe introuvable.', 404);
        }

        $existing = $this->qrScanRepository->findOneBy(['round' => $round, 'team' => $team]);
        if ($existing instanceof QrScan) {
            return $this->errorResponse('DUPLICATE_SCAN', 'Un scan a déjà été enregistré pour cette équipe.');
        }

        $qrScan = new QrScan();
        $qrScan->setRound($round)
            ->setTeam($team)
            ->setScanStatus(QrScan::STATUS_ACCEPTED)
            ->setRank($this->nextRank($round))
            ->setClientTimestamp(isset($data['clientTimestamp']) ? new DateTimeImmutable((string) $data['clientTimestamp']) : null)
            ->setGpsLat($data['gps']['lat'] ?? null)
            ->setGpsLng($data['gps']['lng'] ?? null)
            ->setGpsAccuracy($data['gps']['accuracy'] ?? null);

        $this->entityManager->persist($qrScan);
        $this->entityManager->flush();

        return $this->successResponse([
            'scanStatus' => $qrScan->getScanStatus(),
            'rank' => $qrScan->getRank(),
            'choiceTimeoutSec' => $round->getGameSession()?->getChoiceTimeoutSec(),
            'futureOptions' => array_map(fn (FutureOption $option) => [
                'id' => $option->getId(),
                'code' => $option->getCode(),
                'title' => $option->getTitle(),
                'risk' => $option->getRiskLevel(),
                'isTaken' => $this->futurePickRepository->findOneBy(['round' => $round, 'futureOption' => $option]) !== null,
            ], $round->getFutureOptions()->toArray()),
        ]);
    }

    #[Route('/rounds/{roundId}/futures', name: 'futures', methods: ['GET'])]
    public function futures(string $roundId): JsonResponse
    {
        $round = $this->roundRepository->find($roundId);
        if (!$round instanceof Round) {
            return $this->errorResponse('ROUND_NOT_FOUND', 'Round introuvable.', 404);
        }

        return $this->successResponse([
            'roundId' => $round->getId(),
            'futureOptions' => array_map(fn (FutureOption $option) => [
                'id' => $option->getId(),
                'code' => $option->getCode(),
                'risk' => $option->getRiskLevel(),
                'isTaken' => ($pick = $this->futurePickRepository->findOneBy(['round' => $round, 'futureOption' => $option])) !== null,
                'takenByTeamId' => isset($pick) ? $pick->getTeam()->getId() : null,
            ], $round->getFutureOptions()->toArray()),
        ]);
    }

    #[Route('/rounds/{roundId}/pick', name: 'pick', methods: ['POST'])]
    public function pick(string $roundId, Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        if ($data === null) {
            return $this->errorResponse('INVALID_JSON', 'Payload invalide.');
        }

        $round = $this->roundRepository->find($roundId);
        $team = isset($data['teamId']) ? $this->teamRepository->find($data['teamId']) : null;
        $option = isset($data['futureOptionId']) ? $this->futureOptionRepository->find($data['futureOptionId']) : null;
        if (!$round instanceof Round || !$team instanceof Team || !$option instanceof FutureOption) {
            return $this->errorResponse('NOT_FOUND', 'Round, équipe ou futur introuvable.', 404);
        }

        if ($option->getRound()?->getId() !== $round->getId()) {
            return $this->errorResponse('INVALID_FUTURE', 'Le futur ne correspond pas à cette manche.');
        }

        if ($this->futurePickRepository->findOneBy(['round' => $round, 'futureOption' => $option])) {
            return $this->errorResponse('FUTURE_ALREADY_TAKEN', 'Ce futur a déjà été choisi par une autre équipe.');
        }

        if ($this->futurePickRepository->findOneBy(['round' => $round, 'team' => $team])) {
            return $this->errorResponse('TEAM_ALREADY_PICKED', 'Cette équipe a déjà choisi un futur.');
        }

        $pick = new FuturePick();
        $pick->setRound($round)
            ->setTeam($team)
            ->setFutureOption($option)
            ->setPickMode(FuturePick::MODE_MANUAL)
            ->setLocked(true);

        $this->entityManager->persist($pick);
        $this->entityManager->flush();

        return $this->successResponse([
            'pick' => [
                'code' => $option->getCode(),
                'pickMode' => $pick->getPickMode(),
                'pickedAt' => $pick->getPickedAt()->format(DATE_ATOM),
            ],
            'team' => [
                'ev' => $team->getEvCurrent(),
                'status' => $team->getStatus(),
            ],
            'futureOptions' => [[
                'code' => $option->getCode(),
                'isTaken' => true,
            ]],
        ]);
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

    private function nextRank(Round $round): int
    {
        $qb = $this->qrScanRepository->createQueryBuilder('qs');
        $qb->select('COUNT(qs.id)')
            ->andWhere('qs.round = :round')
            ->andWhere('qs.scanStatus = :status')
            ->setParameter('round', $round)
            ->setParameter('status', QrScan::STATUS_ACCEPTED);

        $count = (int) $qb->getQuery()->getSingleScalarResult();

        return $count + 1;
    }
}
