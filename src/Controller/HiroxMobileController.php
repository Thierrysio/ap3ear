<?php

namespace App\Controller;

use App\Entity\AppliedEffect;
use App\Entity\FutureOption;
use App\Entity\FuturePick;
use App\Entity\GameSession;
use App\Entity\QrScan;
use App\Entity\Round;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\AppliedEffectRepository;
use App\Repository\FutureOptionRepository;
use App\Repository\FuturePickRepository;
use App\Repository\GameSessionRepository;
use App\Repository\QrScanRepository;
use App\Repository\RoundRepository;
use App\Repository\TEpreuveRepository;
use App\Repository\TeamRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mobile', name: 'hirox_')]
class HiroxMobileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameSessionRepository $gameSessionRepository,
        private TeamRepository $teamRepository,
        private RoundRepository $roundRepository,
        private FutureOptionRepository $futureOptionRepository,
        private FuturePickRepository $futurePickRepository,
        private QrScanRepository $qrScanRepository,
        private AppliedEffectRepository $appliedEffectRepository,
        private TEpreuveRepository $tEpreuveRepository,
    ) {
    }

    #[Route('/epreuves/{tepreuveId}/hirox/session', name: 'session_by_epreuve', methods: ['GET'])]
    public function findSessionForEpreuve(int $tepreuveId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->errorResponse('UNAUTHORIZED', 'Authentification requise.', 401);
        }

        $epreuve = $this->tEpreuveRepository->find($tepreuveId);
        if (!$epreuve) {
            return $this->errorResponse('EPREUVE_NOT_FOUND', "L'épreuve demandée est introuvable.", 404);
        }

        $session = $this->gameSessionRepository->findRunningForTepreuve($tepreuveId);
        if (!$session instanceof GameSession) {
            return $this->errorResponse('SESSION_NOT_RUNNING', "Aucune session Hirox en cours pour cette épreuve.", 404);
        }

        return $this->successResponse([
            'tepreuveId' => $tepreuveId,
            'gameSession' => $this->serializeSession($session),
        ]);
    }

    #[Route('/hirox/sessions/{gameSessionId}/bootstrap', name: 'hirox_bootstrap', methods: ['GET'])]
    public function bootstrap(string $gameSessionId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->errorResponse('UNAUTHORIZED', 'Authentification requise.', 401);
        }

        $session = $this->gameSessionRepository->find($gameSessionId);
        if (!$session instanceof GameSession) {
            return $this->errorResponse('SESSION_NOT_FOUND', 'Session Hirox introuvable.', 404);
        }

        [$team, $error] = $this->resolveTeamForSession($session, $user);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $round = $this->roundRepository->findOneBy([
            'gameSession' => $session,
            'index' => $session->getCurrentRoundIndex(),
        ]);

        if (!$round instanceof Round) {
            return $this->errorResponse('ROUND_NOT_FOUND', 'Manche introuvable pour cette session.', 404);
        }

        $scan = $this->qrScanRepository->findOneBy(['round' => $round, 'team' => $team]);
        $pick = $this->futurePickRepository->findOneBy(['round' => $round, 'team' => $team]);

        $timeRemainingSec = null;
        if ($round->getStatus() === Round::STATUS_ACTIVE && $round->getStartAt()) {
            $deadlineTs = $round->getStartAt()->getTimestamp() + $round->getTimeLimitSec();
            $timeRemainingSec = max(0, $deadlineTs - (new DateTimeImmutable())->getTimestamp());
        }

        $effectsPendingQb = $this->appliedEffectRepository->createQueryBuilder('ae');
        $effectsPending = $effectsPendingQb
            ->andWhere('ae.team = :team')
            ->andWhere('ae.scheduledRoundIndex >= :index')
            ->setParameter('team', $team)
            ->setParameter('index', $round->getIndex())
            ->getQuery()
            ->getResult();

        return $this->successResponse([
            'team' => [
                'id' => $team->getId(),
                'tequipeId' => $team->getTequipeId(),
                'name' => $team->getName(),
                'ev' => $team->getEvCurrent(),
                'status' => $team->getStatus(),
            ],
            'gameSession' => $this->serializeSession($session),
            'round' => [
                'id' => $round->getId(),
                'index' => $round->getIndex(),
                'status' => $round->getStatus(),
                'timeLimitSec' => $round->getTimeLimitSec(),
                'timeRemainingSec' => $timeRemainingSec,
                'title' => $round->getTitle(),
                'physicalChallenge' => $round->getPhysicalChallenge(),
                'qrPayloadExpectedHint' => $round->getQrPayloadExpected(),
            ],
            'scan' => $scan ? [
                'exists' => true,
                'scanStatus' => $scan->getScanStatus(),
                'rank' => $scan->getRank(),
                'scannedAt' => $scan->getScannedAt()->format(DATE_ATOM),
            ] : ['exists' => false],
            'pick' => $pick ? [
                'exists' => true,
                'futureCode' => $pick->getFutureOption()->getCode(),
                'pickedAt' => $pick->getPickedAt()->format(DATE_ATOM),
                'pickMode' => $pick->getPickMode(),
            ] : ['exists' => false],
            'effectsPending' => array_map(fn (AppliedEffect $effect) => [
                'type' => $effect->getEffectType(),
                'delta' => $effect->getPayload()['delta'] ?? null,
                'timing' => 'ROUND_START',
                'roundIndex' => $effect->getScheduledRoundIndex(),
            ], $effectsPending),
        ]);
    }

    #[Route('/hirox/rounds/{roundId}/futures', name: 'hirox_futures', methods: ['GET'])]
    public function futures(string $roundId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->errorResponse('UNAUTHORIZED', 'Authentification requise.', 401);
        }

        $round = $this->roundRepository->find($roundId);
        if (!$round instanceof Round) {
            return $this->errorResponse('ROUND_NOT_FOUND', 'Manche introuvable.', 404);
        }

        [$team, $error] = $this->resolveTeamForSession($round->getGameSession(), $user);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $futureOptions = $this->futureOptionRepository->findBy(['round' => $round], ['displayOrder' => 'ASC']);
        $picks = $this->futurePickRepository->findBy(['round' => $round]);
        $picksByOption = [];
        foreach ($picks as $pick) {
            $picksByOption[$pick->getFutureOption()->getId()] = $pick;
        }

        $scanMap = [];
        $scans = $this->qrScanRepository->findBy(['round' => $round, 'scanStatus' => QrScan::STATUS_ACCEPTED]);
        foreach ($scans as $scan) {
            $scanMap[$scan->getTeam()?->getId() ?? ''] = $scan->getRank();
        }

        return $this->successResponse([
            'roundId' => $round->getId(),
            'futureOptions' => array_map(function (FutureOption $option) use ($picksByOption, $scanMap) {
                $pick = $picksByOption[$option->getId()] ?? null;
                $takenByTeam = $pick?->getTeam();

                return [
                    'id' => $option->getId(),
                    'code' => $option->getCode(),
                    'title' => $option->getTitle(),
                    'riskLevel' => $option->getRiskLevel(),
                    'isTaken' => $pick instanceof FuturePick,
                    'takenByRank' => $takenByTeam ? ($scanMap[$takenByTeam->getId()] ?? null) : null,
                    'takenByTeamName' => $takenByTeam?->getName(),
                ];
            }, $futureOptions),
            'serverTime' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    #[Route('/hirox/rounds/{roundId}/scan', name: 'hirox_scan', methods: ['POST'])]
    public function scan(string $roundId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->errorResponse('UNAUTHORIZED', 'Authentification requise.', 401);
        }

        $data = $this->decodeJson($request);
        if ($data === null) {
            return $this->errorResponse('INVALID_JSON', 'Payload invalide.');
        }

        $round = $this->roundRepository->find($roundId);
        if (!$round instanceof Round) {
            return $this->errorResponse('ROUND_NOT_FOUND', 'Manche introuvable.', 404);
        }

        [$team, $error] = $this->resolveTeamForSession($round->getGameSession(), $user);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        if ($round->getStatus() !== Round::STATUS_ACTIVE) {
            return $this->errorResponse('ROUND_NOT_ACTIVE', 'La manche n\'est pas active.');
        }

        $payload = trim((string) ($data['qrPayload'] ?? ''));
        $expected = $round->getQrPayloadExpected();
        if ($payload === '' || ($payload !== $expected && !str_starts_with($payload, $expected))) {
            return $this->errorResponse('INVALID_QR', 'QR non reconnu pour cette manche.');
        }

        $existing = $this->qrScanRepository->findOneBy(['round' => $round, 'team' => $team]);
        if ($existing instanceof QrScan) {
            return $this->errorResponse('DUPLICATE_SCAN', 'Scan déjà enregistré pour cette manche.');
        }

        $now = new DateTimeImmutable();
        $scan = new QrScan();
        $scan->setRound($round)
            ->setTeam($team)
            ->setScanStatus(QrScan::STATUS_ACCEPTED)
            ->setRank($this->nextRank($round))
            ->setScannedAt($now)
            ->setClientTimestamp(isset($data['clientTimestamp']) ? new DateTimeImmutable((string) $data['clientTimestamp']) : null)
            ->setGpsLat($data['gps']['lat'] ?? null)
            ->setGpsLng($data['gps']['lng'] ?? null)
            ->setGpsAccuracy($data['gps']['accuracy'] ?? null);

        $this->entityManager->persist($scan);
        $this->entityManager->flush();

        $choiceTimeout = $round->getGameSession()?->getChoiceTimeoutSec() ?? 0;
        $deadline = $now->modify(sprintf('+%d seconds', $choiceTimeout));

        return $this->successResponse([
            'scanStatus' => $scan->getScanStatus(),
            'rank' => $scan->getRank(),
            'choiceTimeoutSec' => $choiceTimeout,
            'choiceDeadlineAt' => $deadline->format(DATE_ATOM),
        ], 201);
    }

    #[Route('/hirox/rounds/{roundId}/pick', name: 'hirox_pick', methods: ['POST'])]
    public function pick(string $roundId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->errorResponse('UNAUTHORIZED', 'Authentification requise.', 401);
        }

        $data = $this->decodeJson($request);
        if ($data === null) {
            return $this->errorResponse('INVALID_JSON', 'Payload invalide.');
        }

        $round = $this->roundRepository->find($roundId);
        if (!$round instanceof Round) {
            return $this->errorResponse('ROUND_NOT_FOUND', 'Manche introuvable.', 404);
        }

        [$team, $error] = $this->resolveTeamForSession($round->getGameSession(), $user);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $scan = $this->qrScanRepository->findOneBy(['round' => $round, 'team' => $team]);
        if (!$scan instanceof QrScan || $scan->getScanStatus() !== QrScan::STATUS_ACCEPTED) {
            return $this->errorResponse('SCAN_REQUIRED', 'Aucun scan valide pour cette manche.');
        }

        $choiceTimeout = $round->getGameSession()?->getChoiceTimeoutSec() ?? 0;
        $deadline = $scan->getScannedAt()->modify(sprintf('+%d seconds', $choiceTimeout));
        if ((new DateTimeImmutable()) > $deadline) {
            return $this->errorResponse('CHOICE_WINDOW_EXPIRED', 'Temps de choix dépassé.');
        }

        $futureOptionId = (string) ($data['futureOptionId'] ?? '');
        $futureOption = $futureOptionId !== '' ? $this->futureOptionRepository->find($futureOptionId) : null;
        if (!$futureOption instanceof FutureOption || $futureOption->getRound()?->getId() !== $round->getId()) {
            return $this->errorResponse('INVALID_FUTURE', 'Futur invalide pour cette manche.');
        }

        if ($this->futurePickRepository->findOneBy(['round' => $round, 'team' => $team])) {
            return $this->errorResponse('TEAM_ALREADY_PICKED', 'Cette équipe a déjà choisi un futur.');
        }

        if ($this->futurePickRepository->findOneBy(['round' => $round, 'futureOption' => $futureOption])) {
            return $this->errorResponse('FUTURE_ALREADY_TAKEN', "Ce futur vient d'être choisi par une autre équipe.");
        }

        $pick = new FuturePick();
        $pick->setRound($round)
            ->setTeam($team)
            ->setFutureOption($futureOption)
            ->setPickMode(FuturePick::MODE_MANUAL)
            ->setLocked(true);

        $effectsApplied = $this->applyEffects($round, $team, $futureOption);

        try {
            $this->entityManager->persist($pick);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->errorResponse('FUTURE_ALREADY_TAKEN', "Ce futur vient d'être choisi par une autre équipe.");
        }

        return $this->successResponse([
            'pick' => [
                'futureCode' => $futureOption->getCode(),
                'pickMode' => $pick->getPickMode(),
                'pickedAt' => $pick->getPickedAt()->format(DATE_ATOM),
            ],
            'team' => [
                'ev' => $team->getEvCurrent(),
                'status' => $team->getStatus(),
            ],
            'effectsApplied' => $effectsApplied,
        ]);
    }

    private function applyEffects(Round $round, Team $team, FutureOption $futureOption): array
    {
        $effectsApplied = [];

        foreach ($futureOption->getImmediateEffects() as $effect) {
            if (($effect['type'] ?? null) === 'EV_DELTA') {
                $delta = (int) ($effect['delta'] ?? 0);
                $team->setEvCurrent($team->getEvCurrent() + $delta);

                $applied = new AppliedEffect();
                $applied->setGameSession($round->getGameSession())
                    ->setRound($round)
                    ->setTeam($team)
                    ->setEffectType($delta >= 0 ? AppliedEffect::EFFECT_EV_ADD : AppliedEffect::EFFECT_EV_SUB)
                    ->setPayload(['delta' => $delta])
                    ->setScheduledRoundIndex(null)
                    ->setSourceType(AppliedEffect::SOURCE_FUTURE_PICK);

                $effectsApplied[] = [
                    'type' => 'EV_DELTA',
                    'delta' => $delta,
                    'timing' => 'IMMEDIATE',
                ];

                $this->entityManager->persist($applied);
            }
        }

        foreach ($futureOption->getDelayedEffects() as $effect) {
            if (($effect['type'] ?? null) === 'EV_DELTA') {
                $delta = (int) ($effect['delta'] ?? 0);
                $roundIndex = isset($effect['roundIndex']) ? (int) $effect['roundIndex'] : null;

                $applied = new AppliedEffect();
                $applied->setGameSession($round->getGameSession())
                    ->setRound($round)
                    ->setTeam($team)
                    ->setEffectType($delta >= 0 ? AppliedEffect::EFFECT_EV_ADD : AppliedEffect::EFFECT_EV_SUB)
                    ->setPayload(['delta' => $delta])
                    ->setScheduledRoundIndex($roundIndex)
                    ->setSourceType(AppliedEffect::SOURCE_FUTURE_PICK);

                $effectsApplied[] = [
                    'type' => 'EV_DELTA',
                    'delta' => $delta,
                    'timing' => 'ROUND_START',
                    'roundIndex' => $roundIndex,
                ];

                $this->entityManager->persist($applied);
            }
        }

        return $effectsApplied;
    }

    private function resolveTeamForSession(GameSession $session, User $user): array
    {
        $tequipeId = $user->getLatEquipe()?->getId();
        if (!$tequipeId) {
            return [null, $this->errorResponse('TEAM_NOT_REGISTERED', "Votre équipe n'est pas inscrite à cette session Hirox.", 403)];
        }

        $team = $this->teamRepository->findOneBySessionAndTequipeId($session, $tequipeId);
        if (!$team instanceof Team) {
            return [null, $this->errorResponse('TEAM_NOT_REGISTERED', "Votre équipe n'est pas inscrite à cette session Hirox.", 403)];
        }

        return [$team, null];
    }

    private function serializeSession(GameSession $session): array
    {
        return [
            'id' => $session->getId(),
            'status' => $session->getStatus(),
            'currentRoundIndex' => $session->getCurrentRoundIndex(),
            'choiceTimeoutSec' => $session->getChoiceTimeoutSec(),
            'gpsEnabled' => $session->isGpsEnabled(),
            'gpsRadiusMeters' => $session->getGpsRadiusMeters(),
        ];
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
