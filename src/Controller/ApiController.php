<?php

namespace App\Controller;

use App\Utils\Utils;

// === Entit?s ?classiques? d?j? pr?sentes chez toi ===
use App\Entity\User;
use App\Entity\TScore;
use App\Entity\TEpreuve3Next;
use App\Entity\TEpreuve3Finish;
use App\Entity\SetChoixBontoDto;

// === Services/Managers ?classiques? ===
use App\Services\Epreuve3Manager;

// === Repositories ?classiques? ===
use App\Repository\UserRepository;
use App\Repository\TCompetitionRepository;
use App\Repository\TEpreuveRepository;
use App\Repository\TEquipeRepository;
use App\Repository\TflagRepository;
use App\Repository\SetChoixBontoDtoRepository;

// === Entit?s Game 4 ===
use App\Entity\Game4Card;
use App\Entity\Game4CardDef;
use App\Entity\Game4Game;
use App\Entity\Game4Player;
use App\Entity\Game4Duel;
use App\Entity\Game4DuelPlay;

// === Repositories Game 4 ===
use App\Repository\Game4CardRepository;
use App\Repository\Game4CardDefRepository;
use App\Repository\Game4GameRepository;
use App\Repository\Game4PlayerRepository;
use App\Repository\Game4DuelRepository;
use App\Repository\Game4DuelPlayRepository;

// === Services Game 4 ===
use App\Services\DuelService;

// === Symfony/Doctrine ===
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ApiController extends AbstractController
{
    // =======================
    // CONFIG ?PREUVE 4
    // =======================
    /** N joueurs avant tirage du zombie initial */
    private int $g4ThresholdForDraw = 4;

    /** Types de cartes ? sp?ciales ? (quota max 2 en main) */
    private const G4_SPECIAL_TYPES = ['ZOMBIE', 'VACCINE', 'SHOTGUN'];
    /** Nombre max de cartes en main */
    private const G4_MAX_HAND = 7;
    /** Cooldown (en secondes) entre deux duels */
    private const G4_DUEL_COOLDOWN_SECONDS = 600;


    public function __construct(
        private EntityManagerInterface $em,

        // Game 4
        private Game4GameRepository $gameRepo,
        private Game4PlayerRepository $playerRepo,
        private Game4CardRepository $cardRepo,
        private Game4CardDefRepository $defRepo,
        private Game4DuelRepository $duelRepo,
        private Game4DuelPlayRepository $playRepo,

        // R?gles de duel (r?solution)
        private DuelService $duelService,
    ) {}

    // ---------------------------------------------------------------------
    // INDEX
    // ---------------------------------------------------------------------
    #[Route('/api', name: 'app_api', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('api/index.html.twig', [
            'controller_name' => 'ApiController',
        ]);
    }

    // ---------------------------------------------------------------------
    // REGISTER (d?j? chez toi)
    // ---------------------------------------------------------------------
    #[Route('/api/mobile/register', name: 'app_mobile_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        ValidatorInterface $validator
    ): JsonResponse {
        if (0 !== strpos((string) $request->headers->get('Content-Type', ''), 'application/json')) {
            return $this->jsonOk(['error' => 'Content-Type must be application/json'], Response::HTTP_BAD_REQUEST);
        }
        try {
            $data = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->jsonOk(['error' => 'JSON invalide', 'details' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $email  = $this->toUtf8($data->Email   ?? null);
        $pwd    = $this->toUtf8($data->Password?? null);
        $nom    = $this->toUtf8($data->Nom     ?? null, normalize: true);
        $prenom = $this->toUtf8($data->Prenom  ?? null, normalize: true);

        if (!$email || !$pwd || !$nom || !$prenom) {
            return $this->jsonOk(['error' => 'Champs manquants'], Response::HTTP_BAD_REQUEST);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonOk(['error' => 'Email invalide'], Response::HTTP_BAD_REQUEST);
        }
        if ($userRepository->findOneBy(['email' => mb_strtolower($email)])) {
            return $this->jsonOk(['error' => 'Email d?j? utilis?'], Response::HTTP_CONFLICT);
        }
        if (mb_strlen($pwd) < 8) {
            return $this->jsonOk(['error' => 'Mot de passe trop court (min 8 caract?res)'], Response::HTTP_BAD_REQUEST);
        }

        $user = new User();
        $user->setEmail(mb_strtolower($email));
        $user->setNom(trim($nom));
        $user->setPrenom(trim($prenom));
        $user->setStatut(true);
        $user->setRoles(['ROLE_USER']);

        $hashed = $passwordHasher->hashPassword($user, $pwd);
        $user->setPassword($hashed);

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $err = [];
            foreach ($errors as $violation) {
                $err[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            return $this->jsonOk(['error' => 'Validation failed', 'details' => $err], Response::HTTP_BAD_REQUEST);
        }

        try {
            $em->persist($user);
            $em->flush();
        } catch (\Throwable $e) {
            return $this->jsonOk(['error' => 'Erreur serveur'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $payload = [
            'id'     => $user->getId(),
            'email'  => $user->getEmail(),
            'nom'    => $user->getNom(),
            'prenom' => $user->getPrenom(),
        ];

        return $this->jsonOk($payload, Response::HTTP_CREATED);
    }
    
     // ---------------------------------------------------------------------
    // REGISTER (d?j? chez toi)
    // ---------------------------------------------------------------------
    #[Route('/api/mobile/registerAvecEquipe', name: 'app_mobile_registerEquipe', methods: ['POST'])]
    public function registerAvecEquipe(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        ValidatorInterface $validator,
        TEquipeRepository $equipeRepository
    ): JsonResponse {
        if (0 !== strpos((string) $request->headers->get('Content-Type', ''), 'application/json')) {
            return $this->jsonOk(['error' => 'Content-Type must be application/json'], Response::HTTP_BAD_REQUEST);
        }
        try {
            $data = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->jsonOk(['error' => 'JSON invalide', 'details' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $email         = $this->toUtf8($data->Email         ?? null);
        $pwd           = $this->toUtf8($data->Password      ?? null);
        $nom           = $this->toUtf8($data->Nom           ?? null, normalize: true);
        $prenom        = $this->toUtf8($data->Prenom        ?? null, normalize: true);
        $equipeChoisie = $data->equipeChoisie ?? $data->EquipeChoisie ?? null;

        if (!$email || !$pwd || !$nom || !$prenom || null === $equipeChoisie) {
            return $this->jsonOk(['error' => 'Champs manquants'], Response::HTTP_BAD_REQUEST);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonOk(['error' => 'Email invalide'], Response::HTTP_BAD_REQUEST);
        }
        if ($userRepository->findOneBy(['email' => mb_strtolower($email)])) {
            return $this->jsonOk(['error' => 'Email d?j? utilis?'], Response::HTTP_CONFLICT);
        }
        if (mb_strlen($pwd) < 8) {
            return $this->jsonOk(['error' => 'Mot de passe trop court (min 8 caract?res)'], Response::HTTP_BAD_REQUEST);
        }

        $user = new User();
        $user->setEmail(mb_strtolower($email));
        $user->setNom(trim($nom));
        $user->setPrenom(trim($prenom));
        $user->setStatut(true);
        $user->setRoles(['ROLE_USER']);

        $equipeId = filter_var($equipeChoisie, FILTER_VALIDATE_INT);
        if (false === $equipeId) {
            return $this->jsonOk(['error' => 'Identifiant d\'équipe invalide'], Response::HTTP_BAD_REQUEST);
        }

        $equipe = $equipeRepository->find((int) $equipeId);
        if (!$equipe) {
            return $this->jsonOk(['error' => 'Équipe introuvable'], Response::HTTP_NOT_FOUND);
        }

        $user->setLatEquipe($equipe);

        $hashed = $passwordHasher->hashPassword($user, $pwd);
        $user->setPassword($hashed);

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $err = [];
            foreach ($errors as $violation) {
                $err[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            return $this->jsonOk(['error' => 'Validation failed', 'details' => $err], Response::HTTP_BAD_REQUEST);
        }

        try {
            $em->persist($user);
            $em->flush();
        } catch (\Throwable $e) {
            return $this->jsonOk(['error' => 'Erreur serveur'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $payload = [
            'id'     => $user->getId(),
            'email'  => $user->getEmail(),
            'nom'    => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'equipe' => [
                'id'  => $equipe->getId(),
                'nom' => $equipe->getNom(),
            ],
        ];

        return $this->jsonOk($payload, Response::HTTP_CREATED);
    }


    // ---------------------------------------------------------------------
    // LOGIN (GetFindUser)
    // ---------------------------------------------------------------------
    #[Route('/api/mobile/GetFindUser', name: 'app_api_mobile_getuser', methods: ['POST'])]
    public function getFindUser(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ) {
        if (0 !== strpos((string) $request->headers->get('Content-Type', ''), 'application/json')) {
            return $this->jsonOk(['success' => false, 'message' => 'Content-Type must be application/json'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $data = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->jsonOk(['success' => false, 'message' => 'JSON invalide', 'details' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $email = $this->toUtf8($data->Email ?? null);
        $pwd   = $this->toUtf8($data->Password ?? null);

        if (!$email || !$pwd) {
            return $this->jsonOk(['success' => false, 'message' => 'Champs manquants'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => mb_strtolower($email)]);
        if (!$user || !$passwordHasher->isPasswordValid($user, $pwd)) {
            return $this->jsonOk(['success' => false, 'message' => 'Identifiants incorrects'], Response::HTTP_OK);
        }

        $response = new Utils;
        $tab = ["lesUsers","lestScores"];
        return $response->GetJsonResponse($request, $user, $tab);
    }

    // ---------------------------------------------------------------------
    // COMP?TITION EN COURS
    // ---------------------------------------------------------------------
    #[Route('/api/mobile/competition/enCours', name: 'app_mobile_competition_encours', methods: ['GET'])]
    public function getCompetitionEnCours(
        Request $request,
        TCompetitionRepository $competitionRepository
    ) {
        $var = $competitionRepository->findCompetitionEnCours();

        if (!$var) {
            return $this->jsonOk([
                'success' => false,
                'message' => 'Aucune comp?tition en cours.'
            ], Response::HTTP_OK);
        }

        $response = new Utils;
        $tab = [
            'password',
            'roles',
            'lestEpreuves.lestScores.latEquipe.lestScores',
            'lesUsers',
        ];

        return $response->GetJsonResponse($request, $var, $tab);
    }

    // ---------------------------------------------------------------------
    // COMP?TITION ? VENIR
    // ---------------------------------------------------------------------
    #[Route('/api/mobile/competition/prochaine', name: 'app_mobile_competition_prochaine', methods: ['GET'])]
    public function getCompetitionProchaine(
        Request $request,
        TCompetitionRepository $competitionRepository
    ) {
        $var = $competitionRepository->findCompetitionProchaine();

        if (!$var) {
            return $this->jsonOk([
                'success' => false,
                'message' => 'Aucune comp?tition ? venir.'
            ], Response::HTTP_OK);
        }

        $response = new Utils;
        $tab = ["laCompetition","latEpreuve","password","roles"];
        return $response->GetJsonResponse($request, $var, $tab);
    }

    #[Route('/api/mobile/getLesEquipes', name: 'api_mobile_get_les_equipes', methods: ['GET'])]
    public function getLesEquipes(
        Request $request,
        TEquipeRepository $equipeRepository
    ): JsonResponse {
        $equipes = $equipeRepository->createQueryBuilder('e')
            ->leftJoin('e.lesUsers', 'u')
            ->addSelect('u')
            ->orderBy('e.nom', 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()
            ->getResult();

        $utils = new Utils();

        return $utils->GetJsonResponse($request, $equipes, [
            'lesUsers.password',
            'lesUsers.roles',
            'lestScores',
        ]);
    }
    
        #[Route('/api/mobile/getJusteLesEquipes', name: 'api_mobile_get_juste_les_equipes', methods: ['GET'])]
    public function getJusteLesEquipes(
        Request $request,
        TEquipeRepository $equipeRepository
    ): JsonResponse {
        $equipes = $equipeRepository->createQueryBuilder('e')
           
            ->orderBy('e.nom', 'ASC')
          
            ->getQuery()
            ->getResult();

        $utils = new Utils();

        return $utils->GetJsonResponse($request, $equipes, [
            'lesUsers',
            'lestScores',
        ]);
    }

    // ---------------------------------------------------------------------
    // SET SCORE
    // ---------------------------------------------------------------------
    #[Route('/api/mobile/SetScore', name: 'api_mobile_set_score', methods: ['POST'])]
    public function setScore(
        Request $request,
        EntityManagerInterface $em,
        TEpreuveRepository $epreuveRepository,
        TEquipeRepository $equipeRepository
    ): Response {
        $data = json_decode($request->getContent(), true) ?? [];

        foreach (['note','equipeId','epreuveId'] as $k) {
            if (!array_key_exists($k, $data)) {
                return $this->json(['message' => "Champ \"$k\" manquant."], Response::HTTP_BAD_REQUEST);
            }
        }

        $note      = (int)$data['note'];
        $equipeId  = (int)$data['equipeId'];
        $epreuveId = (int)$data['epreuveId'];

        $equipe = $equipeRepository->find($equipeId);
        if (!$equipe) {
            return $this->json(['message' => "?quipe introuvable (id=$equipeId)."], Response::HTTP_NOT_FOUND);
        }

        $epreuve = $epreuveRepository->find($epreuveId);
        if (!$epreuve) {
            return $this->json(['message' => "?preuve introuvable (id=$epreuveId)."], Response::HTTP_NOT_FOUND);
        }

        $score = new TScore();
        $score->setNote($note);
        $score->setLatEquipe($equipe);
        $score->setLatEpreuve($epreuve);

        $em->persist($score);
        $em->flush();

        return $this->json([
            'message' => 'Score enregistr? avec succ?s.',
            'score' => [
                'id'     => $score->getId(),
                'note'   => $score->getNote(),
                'equipe' => ['id' => $equipe->getId(), 'nom' => $equipe->getNom()],
                'epreuve'=> ['id' => $epreuve->getId(), 'nom' => $epreuve->getNom()],
            ]
        ], Response::HTTP_CREATED);
    }

    // ---------------------------------------------------------------------
    // ?preuve en cours (mobile)
    // ---------------------------------------------------------------------
    #[Route('/api/mobile/GetEpreuveEnCours', name: 'api_mobile_getEpreuveEnCoursMobile')]
    public function getEpreuveEnCoursMobile(
        Request $request,
        TEpreuveRepository $epreuveRepository
    ): Response {
        $var = $epreuveRepository->findEpreuveEnCours();

        $response = new Utils;
        $tab = ["laCompetition","lestScores"];
        return $response->GetJsonResponse($request, $var, $tab);
    }

    // ---------------------------------------------------------------------
    // SetChoixBonto
    // ---------------------------------------------------------------------
    #[Route('/api/mobile/SetChoixBonto', name: 'api_mobile_set_choix_bonto', methods: ['POST'])]
    public function setChoixBonto(
        Request $request,
        EntityManagerInterface $em,
        SetChoixBontoDtoRepository $repo
    ): JsonResponse {
        if (0 !== strpos((string) $request->headers->get('Content-Type', ''), 'application/json')) {
            return $this->jsonOk(false, Response::HTTP_BAD_REQUEST);
        }
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->jsonOk(false, Response::HTTP_BAD_REQUEST);
        }

        foreach (['Manche','EquipeId','ChoixIndex','Gagnant'] as $k) {
            if (!array_key_exists($k, $data)) {
                return $this->jsonOk(false, Response::HTTP_BAD_REQUEST);
            }
        }

        $manche     = (int) $data['Manche'];
        $equipeId   = (int) $data['EquipeId'];
        $choixIndex = (int) $data['ChoixIndex'];

        $gagnantRaw  = $data['Gagnant'];
        if (is_string($gagnantRaw)) {
            $gagnantRaw = trim($gagnantRaw);
        }
        $gagnantBool = filter_var($gagnantRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($gagnantBool === null) {
            $gagnant = (string) $gagnantRaw;
        } else {
            $gagnant = $gagnantBool ? 'True' : 'False';
        }

        if ($manche <= 0 || $equipeId <= 0) {
            return $this->jsonOk(false, Response::HTTP_BAD_REQUEST);
        }

        $row = $repo->findOneBy(['manche' => $manche, 'equipeId' => $equipeId]);
        if (!$row) {
            $row = new SetChoixBontoDto();
            $row->setManche($manche);
            $row->setEquipeId($equipeId);
            $em->persist($row);
        }

        $row->setGagnant($gagnant);
        $row->setChoixIndex($choixIndex);

        try {
            $em->flush();
        } catch (\Throwable $e) {
            return $this->jsonOk(false, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->jsonOk(true, Response::HTTP_CREATED);
    }

    // ---------------------------------------------------------------------
    // GetResultatsManche
    // ---------------------------------------------------------------------
    #[Route('/api/mobile/GetResultatsManche', name: 'api_mobile_get_resultats_manche', methods: ['POST'])]
    public function getResultatsManche(
        Request $request,
        SetChoixBontoDtoRepository $repo
    ): Response {
        if (0 !== strpos((string) $request->headers->get('Content-Type', ''), 'application/json')) {
            return $this->jsonOk(['success' => false, 'message' => 'Content-Type must be application/json'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->jsonOk(['success' => false, 'message' => 'JSON invalide', 'details' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['id'])) {
            return $this->jsonOk(['success' => false, 'message' => 'Champ "Id" manquant.'], Response::HTTP_BAD_REQUEST);
        }

        $mancheId = (int) $data['id'];
        if ($mancheId <= 0) {
            return $this->jsonOk(['success' => false, 'message' => 'Id de manche invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $equipeId = null;
        if (isset($data['equipeId'])) {
            $equipeId = max(0, (int) $data['equipeId']);
        }
        if (!$equipeId) {
            $user = $this->getUser();
            if ($user) {
                if (method_exists($user, 'getEquipe') && $user->getEquipe()) {
                    $equipeId = (int) $user->getEquipe()->getId();
                } elseif (method_exists($user, 'getEquipeId')) {
                    $equipeId = (int) $user->getEquipeId();
                }
            }
        }

        $payload = $repo->syntheseResultats($mancheId, $equipeId ?: null);

        $utils = new Utils();
        return $utils->GetJsonResponse($request, $payload, []);
    }

    // ---------------------------------------------------------------------
    // EPREUVE 3 ? NEXT
    // ---------------------------------------------------------------------
    #[Route('/api/mobile/Next', name: 'api_mobile_epreuve3_next', methods: ['POST'])]
    public function epreuve3Next(
        Request $request,
        EntityManagerInterface $em,
        TEpreuveRepository $epreuveRepository,
        TEquipeRepository $equipeRepository,
        Epreuve3Manager $epreuve3Manager
    ): Response {
        if (0 !== strpos((string) $request->headers->get('Content-Type', ''), 'application/json')) {
            return $this->jsonOk(['error' => 'Content-Type must be application/json'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->jsonOk(['error' => 'JSON invalide', 'details' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $requiredFields = ['token', 'equipeId', 'epreuveId'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return $this->jsonOk(['error' => "Champ '$field' manquant"], Response::HTTP_BAD_REQUEST);
            }
        }

        $token = $this->toUtf8($data['token']);
        $equipeId = (int) $data['equipeId'];
        $epreuveId = (int) $data['epreuveId'];

        $equipe = $equipeRepository->find($equipeId);
        if (!$equipe) {
            return $this->jsonOk(['error' => "?quipe introuvable (id=$equipeId)"], Response::HTTP_NOT_FOUND);
        }

        $epreuve = $epreuveRepository->find($epreuveId);
        if (!$epreuve) {
            return $this->jsonOk(['error' => "?preuve introuvable (id=$epreuveId)"], Response::HTTP_NOT_FOUND);
        }

        try {
            $nextFlagData = $epreuve3Manager->processNextFlag($token, $equipe, $epreuve);

            if (!$nextFlagData) {
                return $this->jsonOk([
                    'valid' => false,
                    'error' => 'Token invalide ou ?preuve termin?e'
                ], Response::HTTP_OK);
            }

            $epreuve3Next = new TEpreuve3Next();
            $epreuve3Next->setLat($nextFlagData['lat']);
            $epreuve3Next->setLon($nextFlagData['lon']);
            $epreuve3Next->setHint($nextFlagData['hint']);
            $epreuve3Next->setTimeOutSec($nextFlagData['timeoutSec']);
            $epreuve3Next->setValid(true);
            $epreuve3Next->setScannedTokenHash($nextFlagData['scannedTokenHash']);
            $epreuve3Next->setFlagId($nextFlagData['flagId']);

            $em->persist($epreuve3Next);
            $em->flush();

            $responseData = [
                'valid' => true,
                'lat' => $nextFlagData['lat'],
                'lon' => $nextFlagData['lon'],
                'hint' => $nextFlagData['hint'],
                'timeoutSec' => $nextFlagData['timeoutSec'],
                'scannedTokenHash' => $nextFlagData['scannedTokenHash'],
                'flagId' => $nextFlagData['flagId']
            ];

            $response = new Utils();
            return $response->GetJsonResponse($request, $responseData, []);

        } catch (\Exception $e) {
            return $this->jsonOk([
                'valid' => false,
                'error' => 'Erreur serveur lors du traitement',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ---------------------------------------------------------------------
    // EPREUVE 3 ? FINISH
    // ---------------------------------------------------------------------
    #[Route('/api/mobile/Finish', name: 'api_mobile_epreuve3_finish', methods: ['POST'])]
    public function epreuve3Finish(
        Request $request,
        Epreuve3Manager $epreuve3Manager,
        TEquipeRepository $equipeRepository,
        TEpreuveRepository $epreuveRepository
    ): Response {
        if (0 !== strpos((string) $request->headers->get('Content-Type', ''), 'application/json')) {
            return $this->jsonOk(['error' => 'Content-Type must be application/json'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->jsonOk(['error' => 'JSON invalide', 'details' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $requiredFields = ['equipeId', 'epreuveId'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return $this->jsonOk(['error' => "Champ '$field' manquant"], Response::HTTP_BAD_REQUEST);
            }
        }

        $equipeId = (int) $data['equipeId'];
        $epreuveId = (int) $data['epreuveId'];

        $equipe = $equipeRepository->find($equipeId);
        if (!$equipe) {
            return $this->jsonOk(['error' => "?quipe introuvable (id=$equipeId)"], Response::HTTP_NOT_FOUND);
        }

        $epreuve = $epreuveRepository->find($epreuveId);
        if (!$epreuve) {
            return $this->jsonOk(['error' => "?preuve introuvable (id=$epreuveId)"], Response::HTTP_NOT_FOUND);
        }

        try {
            $position = $epreuve3Manager->markEpreuveAsFinished($equipe, $epreuve);

            $responseData = [
                'position' => $position
            ];

            $response = new Utils();
            return $response->GetJsonResponse($request, $responseData, []);

        } catch (\Exception $e) {
            return $this->jsonOk([
                'error' => 'Erreur serveur lors du traitement de fin d\'?preuve',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // =====================================================================
    // ==========================  ?PREUVE 4  ==============================
    // =====================================================================

    // STATE
    #[Route('/api/mobile/state', name: 'g4_state', methods: ['GET'])]
    public function g4_state(Request $req): JsonResponse
    {
        $gameId   = (int) $req->query->get('gameId', 0);
        $equipeId = (int) $req->query->get('equipeId', 0);

        $game = $this->em->find(Game4Game::class, $gameId);
        if (!$game) return $this->jsonOk(['error' => 'game_not_found'], Response::HTTP_NOT_FOUND);

        $me = $equipeId > 0 ? $this->playerRepo->findOneByGameAndEquipe($game, $equipeId) : null;

        return $this->jsonOk($this->buildG4State($game, $me));
    }


    
        // =========================================================
    //  HELPER : Donne la carte spéciale obligatoire selon le rôle
    //           (HUMAN ? SHOTGUN, ZOMBIE ? ZOMBIE)
    // =========================================================

    
// Donne au joueur une carte précise $code.
// 1) tente de la prendre dans le DECK
// 2) sinon en forge UNE pour garantir la règle
// ? Renvoie la Game4Card attribuée (ou null en cas d'échec improbable)
private function giveSpecificFromDeckOrForge(Game4Game $game, Game4Player $player, string $code): ?Game4Card
{
    // Récupère/assure la définition
    $def = $this->defRepo->findOneByCode($code);
    if (!$def) {
        $def = (new Game4CardDef())
            ->setCode($this->toUtf8($code))
            ->setLabel($this->toUtf8(ucfirst(strtolower($code))))
            ->setText($this->toUtf8(''))
            ->setType($this->toUtf8(strtoupper($code)));
        $this->em->persist($def);
        $this->em->flush();
    }

    // 1) Cherche une carte de ce type dans le DECK
    $card = $this->cardRepo->findOneBy([
        'game' => $game,
        'zone' => Game4Card::ZONE_DECK,
        'def'  => $def,
    ]);

    if (!$card) {
        // 2) Plus rien en deck ? on forge UNE carte
        $card = (new Game4Card())
            ->setGame($game)
            ->setDef($def)
            ->setOwner($player)
            ->setZone(Game4Card::ZONE_HAND)
            ->setToken($this->makeToken($game->getId(), $player->getId(), $def->getCode()));
        $this->em->persist($card);
        return $card;
    }

    // Transfert deck ? main
    $card->setOwner($player)
         ->setZone(Game4Card::ZONE_HAND)
         ->setToken($this->makeToken($game->getId(), $player->getId(), $def->getCode()));
    $this->em->persist($card);

    return $card;
}



// JOIN — ne donne QUE la carte obligatoire selon le rôle (pas de main à 7 ici)
// JOIN — ne donne QUE la carte obligatoire selon le rôle (pas de main à 7 ici)
#[Route('/api/mobile/join', name: 'g4_join', methods: ['POST'])]
public function g4_join(Request $req): JsonResponse
{
    $data = json_decode($req->getContent(), true) ?? [];

    // Ton client envoie "userId" mais tu l'utilises comme "equipeId"
    $equipeId = (int)($data['userId'] ?? 0);
    if ($equipeId <= 0) {
        return $this->jsonOk(['equipeId_required' => 'equipeId requis'], 400);
    }

    // Partie en cours (création si besoin)
    $game = $this->gameRepo->findRunning();
    if (!$game) {
        $game = (new Game4Game())
            ->setPhase(Game4Game::PHASE_RUNNING)
            ->setEndsAt((new \DateTimeImmutable())->modify('+15 minutes'));
        $this->em->persist($game);
        $this->em->flush();
    }

    // Deck : définitions + cartes si besoin
    $this->ensureDeck($game);

    // Récupérer/créer le joueur par equipeId
    $player = $this->playerRepo->findOneByGameAndEquipe($game, $equipeId);
    if (!$player) {
        $player = (new Game4Player())
            ->setGame($game)
            ->setEquipeId($equipeId)
            ->setName('Equipe '.$equipeId)
            ->setRole(Game4Player::ROLE_HUMAN)
            ->setLives(3)
            ->setIsAlive(true);
        $this->em->persist($player);
        $this->em->flush();
    } else {
        // Optionnel : si tu veux "reset" la main à chaque nouveau join
        $handCards = $this->cardRepo->findHandByPlayer($player);
        if (\count($handCards) > 0) {
            foreach ($handCards as $c) {
                $c->setOwner(null)->setZone(Game4Card::ZONE_DECK);
                $this->em->persist($c);
            }
            $this->em->flush();
        }
    }

    // Attribution des rôles (ta logique existante)
    $players = $this->playerRepo->findAllByGame($game);
    $total   = \count($players);
    if ($total > 0) {
        $zWanted  = max(1, intdiv($total - 1, 10) + 1);
        $currentZ = $this->playerRepo->countByGameAndRole($game, Game4Player::ROLE_ZOMBIE);

        if ($currentZ !== $zWanted) {
            // On remet tout le monde humain
            foreach ($players as $p) {
                $p->setRole(Game4Player::ROLE_HUMAN)
                  ->setLives(3)
                  ->setIsAlive(true);
                $this->em->persist($p);
            }

            // On tire au hasard les nouveaux zombies
            $indices = range(0, $total - 1);
            shuffle($indices);
            foreach (array_slice($indices, 0, $zWanted) as $i) {
                $players[$i]->setRole(Game4Player::ROLE_ZOMBIE);
                $this->em->persist($players[$i]);
            }

            $this->em->flush();
        }
    }

    // ? 1) Si main vide, donner la carte obligatoire selon le rôle
    $handCount = $this->cardRepo->countByOwnerAndZone($player, Game4Card::ZONE_HAND);
    if ($handCount === 0) {
        $this->giveMandatorySpecialOnFirstJoin($game, $player);
        $this->em->flush();
    }

    // ? 2) Garantir : tout joueur ZOMBIE doit avoir au moins UNE carte ZOMBIE
    //    (y compris si son rôle vient de changer à ce join)
    foreach ($players as $p) {
        if ($p->isZombie()) {
            $hasZombieCard = $this->cardRepo->handHasCode($p, 'ZOMBIE');
            if (!$hasZombieCard) {
                $this->giveSpecificFromDeckOrForge($game, $p, 'ZOMBIE');
            }
        }
    }
    $this->em->flush();

    // Réponse state pour le joueur qui vient de joindre
    $state = $this->buildG4State($game, $player);
    return $this->jsonOk($state);
}

// =========================================================
// DRAW — Pioche UNE carte en respectant :
// - 1ère carte = obligatoire (SHOTGUN si humain, ZOMBIE si zombie)
// - Toujours viser >=5 NUM en main (forcer NUM si nécessaire)
// =========================================================
#[Route('/api/mobile/draw', name: 'g4_draw', methods: ['POST'])]
public function g4_draw(Request $req): JsonResponse
{
    $data = json_decode($req->getContent(), true) ?? [];
    $gameId   = (int)($data['gameId']   ?? 0);
    $equipeId = (int)($data['equipeId'] ?? 0);

    /** @var Game4Game|null $game */
    $game = $this->em->find(Game4Game::class, $gameId);
    if (!$game) {
        return $this->jsonOk(['error' => 'game_not_found'], Response::HTTP_NOT_FOUND);
    }

    // ?? On enlève la restriction de phase pour que la pioche fonctionne
    // Si plus tard tu as une vraie phase SETUP, on pourra remettre une règle du genre :
    // if ($game->getPhase() === Game4Game::PHASE_RUNNING && $ilYADesDuelsEnCours) { ... }

    $this->ensureDeck($game);

    /** @var Game4Player|null $player */
    $player = $this->playerRepo->findOneByGameAndEquipe($game, $equipeId);
    if (!$player) {
        return $this->jsonOk(['error' => 'player_not_in_game'], Response::HTTP_FORBIDDEN);
    }
    if ($player->isEliminated()) {
        return $this->jsonOk(['error' => 'player_eliminated'], Response::HTTP_CONFLICT);
    }

    // Pioche 1 carte
    $card = $this->drawOne($game, $player);
    if (!$card) {
        return $this->jsonOk(['error' => 'deck_empty_or_quota_blocked'], Response::HTTP_CONFLICT);
    }

    // ?? Important : écrire les changements (deck/hand) en base
    $this->em->flush();

    // Retour propre pour le front
    return $this->jsonOk([
        'card'     => $this->toCardDto($card),
        'handSize' => $this->cardRepo->countByOwnerAndZone($player, Game4Card::ZONE_HAND),
    ]);
}


 // VALIDATE TARGET
#[Route('/api/mobile/validateTarget', name: 'g4_validate_target', methods: ['POST'])]
public function g4_validate_target(Request $req): JsonResponse
{
    $data = json_decode($req->getContent(), true) ?? [];
    $gameId         = (int)($data['gameId'] ?? 0);
    $targetPlayerId = (int)($data['targetPlayerId'] ?? 0);
    $equipeId       = (int)($data['equipeId'] ?? 0);
    $duelId         = (int)($data['duelId'] ?? 0); // ? nouveau

    if ($gameId <= 0 || $targetPlayerId <= 0 || $equipeId <= 0) {
        return $this->jsonOk(['error' => 'invalid_parameters'], Response::HTTP_BAD_REQUEST);
    }

    $game = $this->em->find(Game4Game::class, $gameId);
    if (!$game) return $this->jsonOk(['error' => 'game_not_found'], Response::HTTP_NOT_FOUND);

    // ?? Attention : ici, $equipeId est l'identifiant "?quipe/joueur" c?t? appli
    // Dans ton code, findOneByGameAndEquipe mappe bien game + equipeId -> Game4Player
    $actor  = $this->playerRepo->findOneByGameAndEquipe($game, $equipeId);
    $target = $this->playerRepo->findOneByGameAndEquipe($game, $targetPlayerId);

    if (!$actor)  return $this->jsonOk(['valid' => false, 'message' => 'player_not_in_game']);
    if (!$target || $target->getGame()->getId() !== $game->getId()) {
        return $this->jsonOk(['valid' => false, 'message' => 'target_not_found']);
    }
    if ($actor->getId() === $target->getId()) {
        return $this->jsonOk(['valid' => false, 'message' => 'Impossible de vous cibler vous-même']);
    }
    if (!$target->isAlive()) {
        return $this->jsonOk(['valid' => false, 'message' => 'Cible éliminée']);
    }

    $response = [
        'valid' => true,
        'message' => 'Cible valide',
        'targetInfo' => [
            'id'      => $target->getId(),
            'name'    => $target->getName(),
            'role'    => $target->getRole(),
            'lives'   => $target->getLives(),
            'isAlive' => $target->isAlive()
        ],
        'duel' => null,
    ];

    // =========================
    // ? MAJ incomingDuel/lockedInDuel si duelId fourni
    // =========================
    if ($duelId > 0) {
        /** @var Game4Duel|null $duel */
        $duel = $this->em->find(Game4Duel::class, $duelId);
        if (!$duel) {
            return $this->jsonOk([
                'valid' => false,
                'message' => 'duel_not_found'
            ], Response::HTTP_NOT_FOUND);
        }
        if ($duel->getGame()->getId() !== $game->getId()) {
            return $this->jsonOk([
                'valid' => false,
                'message' => 'duel_game_mismatch'
            ], Response::HTTP_CONFLICT);
        }
        if ($duel->getStatus() !== Game4Duel::STATUS_PENDING) {
            return $this->jsonOk([
                'valid' => false,
                'message' => 'duel_not_pending'
            ], Response::HTTP_CONFLICT);
        }

        // Coh?rence participants (ordre A/B indiff?rent)
        $isActorIn = ($duel->getPlayerA()->getId() === $actor->getId() || $duel->getPlayerB()->getId() === $actor->getId());
        $isTargetIn = ($duel->getPlayerA()->getId() === $target->getId() || $duel->getPlayerB()->getId() === $target->getId());
        if (!$isActorIn || !$isTargetIn) {
            return $this->jsonOk([
                'valid' => false,
                'message' => 'duel_players_mismatch'
            ], Response::HTTP_CONFLICT);
        }

        // ? Met ? jour les flags c?t? joueurs
        // - la cible voit une banni?re de duel entrant
        // - les deux sont "verrouill?s" en duel
        $target->setIncomingDuel($duel)->setLockedInDuel(true);
        $actor->setLockedInDuel(true);

        $this->em->flush();

        $response['duel'] = [
            'duelId'       => $duel->getId(),
            'status'       => $duel->getStatus(),
            'opponentOfActor' => $target->getId(),
            'opponentOfTarget'=> $actor->getId(),
            'banner'       => sprintf("Vous avez été choisi par l'équipe %d", $actor->getEquipeId()),
        ];
    }

    return $this->jsonOk($response);
}


// DUEL START ? anti-doublons robustes
#[Route('/api/mobile/duelStart', name: 'g4_duel_start', methods: ['POST'])]
public function g4_duel_start(Request $req): JsonResponse
{
    $data = json_decode($req->getContent(), true) ?? [];
    $gameId         = (int)($data['gameId']         ?? 0);
    $sourcePlayerId = (int)($data['sourcePlayerId'] ?? 0);
    $targetPlayerId = (int)($data['targetPlayerId'] ?? 0);

    $game = $this->em->find(Game4Game::class, $gameId);
    if (!$game) return $this->jsonOk(['success' => false, 'message' => 'game_not_found'], Response::HTTP_NOT_FOUND);

    /** @var Game4Player|null $source */
    /** @var Game4Player|null $target */
    $source = $this->em->find(Game4Player::class, $sourcePlayerId);
    $target = $this->em->find(Game4Player::class, $targetPlayerId);

    if (!$source || $source->getGame()->getId() !== $game->getId()) {
        return $this->jsonOk(['success' => false, 'message' => 'source_invalid'], Response::HTTP_NOT_FOUND);
    }
    if (!$target || $target->getGame()->getId() !== $game->getId()) {
        return $this->jsonOk(['success' => false, 'message' => 'target_invalid'], Response::HTTP_NOT_FOUND);
    }
    if (!$source->isAlive() || !$target->isAlive()) {
        return $this->jsonOk(['success' => false, 'message' => 'player_dead'], Response::HTTP_CONFLICT);
    }

    // 1) Y a-t-il d?j? un duel PENDING entre ces 2 joueurs (ordre indiff?rent) ?
    $qb = $this->em->createQueryBuilder();
    /** @var Game4Duel|null $duel */
    $duel = $qb->select('d')->from(Game4Duel::class, 'd')
        ->where('d.game = :g')->setParameter('g', $game)
        ->andWhere('d.status = :s')->setParameter('s', Game4Duel::STATUS_PENDING)
        ->andWhere('(d.playerA = :a AND d.playerB = :b) OR (d.playerA = :b AND d.playerB = :a)')
        ->setParameter('a', $source)->setParameter('b', $target)
        ->setMaxResults(1)->getQuery()->getOneOrNullResult();

    if ($duel) {
        // (re)sync flags UI
        if (!$source->isLockedInDuel()) $source->setLockedInDuel(true);
        if (!$target->isLockedInDuel()) $target->setLockedInDuel(true);
        $target->setIncomingDuel($duel);
        $this->em->flush();

        return $this->jsonOk([
            'success' => true,
            'duelId'  => $duel->getId(),
            'message' => 'Duel déjà existant',
        ]);
    }

    // 2) Interdire >1 duel PENDING par joueur : si source OU target a d?j? un duel PENDING avec quelqu?un d?autre -> 409
    $qb2 = $this->em->createQueryBuilder();
    $duelForEither = $qb2->select('d')->from(Game4Duel::class, 'd')
        ->where('d.game = :g')->setParameter('g', $game)
        ->andWhere('d.status = :s')->setParameter('s', Game4Duel::STATUS_PENDING)
        ->andWhere('(d.playerA = :source OR d.playerB = :source OR d.playerA = :target OR d.playerB = :target)')
        ->setParameter('source', $source)->setParameter('target', $target)
        ->setMaxResults(1)->getQuery()->getOneOrNullResult();

    if ($duelForEither) {
        // Option A : renvoyer 409 explicite
        return $this->jsonOk([
            'success' => false,
            'message' => 'player_already_in_pending_duel',
            'duelId'  => $duelForEither->getId(),
        ], Response::HTTP_CONFLICT);

        // Option B : retourner ce duel et resynchroniser les flags (d?commente si tu pr?f?res ce comportement)
        /*
        if (!$source->isLockedInDuel()) $source->setLockedInDuel(true);
        if (!$target->isLockedInDuel()) $target->setLockedInDuel(true);
        if ($duelForEither->getPlayerB()->getId() === $target->getId()) {
            $target->setIncomingDuel($duelForEither);
        }
        $this->em->flush();
        return $this->jsonOk(['success' => true, 'duelId' => $duelForEither->getId(), 'message' => 'Duel existant r?utilis?']);
        */
    }

    // 3) Cr?ation du duel si tout est OK
    $duel = (new Game4Duel())
        ->setGame($game)
        ->setPlayerA($source)
        ->setPlayerB($target)
        ->setStatus(Game4Duel::STATUS_PENDING)
        ->setCreatedAt(new \DateTimeImmutable());
    $this->em->persist($duel);

    // Flags UI
    $source->setLockedInDuel(true);
    $target->setIncomingDuel($duel)->setLockedInDuel(true);

    // 4) Gestion d?une collision concurrente (unique constraint) -> on rattrape proprement
    try {
        $this->em->flush();
    } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
        // Quelqu?un a cr?? un duel PENDING en parall?le -> on r?cup?re celui existant
        $this->em->clear(); // pour repartir proprement
        $duel = $this->em->getRepository(Game4Duel::class)->createQueryBuilder('d')
            ->where('d.game = :g')->setParameter('g', $game)
            ->andWhere('d.status = :s')->setParameter('s', Game4Duel::STATUS_PENDING)
            ->andWhere('(d.playerA = :a AND d.playerB = :b) OR (d.playerA = :b AND d.playerB = :a)')
            ->setParameter('a', $source)->setParameter('b', $target)
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();

        if (!$duel) {
            // si la contrainte vise "un joueur ne peut avoir qu?un duel PENDING", on retourne celui li? au joueur
            $duel = $this->em->getRepository(Game4Duel::class)->createQueryBuilder('d')
                ->where('d.game = :g')->setParameter('g', $game)
                ->andWhere('d.status = :s')->setParameter('s', Game4Duel::STATUS_PENDING)
                ->andWhere('(d.playerA = :source OR d.playerB = :source OR d.playerA = :target OR d.playerB = :target)')
                ->setParameter('source', $source)->setParameter('target', $target)
                ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        }

        if ($duel) {
            // resync flags c?t? cible
            $src = $duel->getPlayerA(); $tgt = $duel->getPlayerB();
            $src->setLockedInDuel(true);
            $tgt->setLockedInDuel(true)->setIncomingDuel($duel);
            $this->em->flush();

            return $this->jsonOk([
                'success' => true,
                'duelId'  => $duel->getId(),
                'message' => 'Duel concurrent détecté, réutilisation',
            ]);
        }

        // Rien trouv? : on renvoie une 409 g?n?rique
        return $this->jsonOk(['success' => false, 'message' => 'duel_conflict'], Response::HTTP_CONFLICT);
    }

    return $this->jsonOk([
        'success' => true,
        'duelId'  => $duel->getId(),
        'message' => 'Duel lancé',
    ]);
}


// DUEL SUBMIT - MODIFI?
#[Route('/api/mobile/duelSubmit', name: 'g4_duel_submit', methods: ['POST'])]
public function g4_duel_submit(Request $req): JsonResponse
{
    $data = json_decode($req->getContent(), true) ?? [];

    $duelId    = (int)($data['duelId']    ?? 0);
    $equipeId  = (int)($data['equipeId']  ?? 0); // équipe du joueur appelant
    $cardToken = (string)($data['cardToken'] ?? '');

    /** @var Game4Duel|null $duel */
    $duel = $this->em->find(Game4Duel::class, $duelId);
    if (!$duel) {
        return $this->jsonOk([
            'success'  => false,
            'message'  => 'duel_not_found',
            'resolved' => true,
        ], Response::HTTP_NOT_FOUND);
    }

    $game = $duel->getGame();
    if (!$game) {
        return $this->jsonOk([
            'success'  => false,
            'message'  => 'game_not_found_for_duel',
            'resolved' => true,
        ], Response::HTTP_NOT_FOUND);
    }

    // Joueur courant (celui qui soumet la carte)
    $player = $this->playerRepo->findOneByGameAndEquipe($game, $equipeId);
    if (!$player) {
        return $this->jsonOk([
            'success'  => false,
            'message'  => 'player_not_in_game',
            'resolved' => true,
        ], Response::HTTP_NOT_FOUND);
    }

    // Duel déjà résolu ?
    if ($duel->getStatus() === Game4Duel::STATUS_RESOLVED) {
        // On renvoie juste l’état courant du duel, pas de nouveau coup
        $res = $this->duelService->resolve($duel, $this->em);

        // Relecture des plays pour affichage
        $allPlays = $this->playRepo->findBy(
            ['duel' => $duel],
            ['roundIndex' => 'ASC', 'submittedAt' => 'ASC']
        );

        $playsView = array_map(function (Game4DuelPlay $p) {
            return [
                'playerId' => $p->getPlayer()->getId(),
                'cardCode' => $p->getCardCode(),
                'cardType' => $p->getCardType(),
                'numValue' => $p->getCardType() === Game4DuelPlay::TYPE_NUM ? $p->getNumValue() : null,
                'round'    => $p->getRoundIndex(),
            ];
        }, $allPlays);

        $isWinner = ($res->winnerId !== null && $res->winnerId === $player->getId());

        return $this->jsonOk([
            'success'  => true,
            'duelId'   => $duel->getId(),
            'status'   => 'RESOLVED',
            'resolved' => true,
            'message'  => 'card_confirmed',
            'result'   => [
                'winnerId'      => $res->winnerId,
                'messageForYou' => $res->winnerId === null
                    ? 'Égalité.'
                    : ($isWinner ? 'Vous avez gagné !' : 'Vous avez perdu...'),
                'logs'          => $res->logs,
                'effects'       => $res->effects,
                'wonCardCode'   => $res->wonCardCode ?? null,
                'wonCardLabel'  => $res->wonCardLabel ?? null,
            ],
            'plays'    => $playsView,
            'state'    => $this->buildG4State($duel->getGame(), $player),
        ]);
    }

    // Vérifier que ce joueur fait bien partie du duel
    if ($duel->getPlayerA()?->getId() !== $player->getId()
        && $duel->getPlayerB()?->getId() !== $player->getId()
    ) {
        return $this->jsonOk([
            'success'  => false,
            'message'  => 'player_not_in_duel',
            'resolved' => false,
        ], Response::HTTP_FORBIDDEN);
    }

    $opponent = $duel->getOpponentFor($player);
    if (!$opponent) {
        return $this->jsonOk([
            'success'  => false,
            'message'  => 'opponent_not_found',
            'resolved' => false,
        ], Response::HTTP_NOT_FOUND);
    }

    // Compter combien de cartes ce joueur a déjà jouées dans ce duel
    $myTotalPlays   = $this->playRepo->count([
        'duel'   => $duel,
        'player' => $player,
    ]);
    $myNumericCount = $this->playRepo->countNumericByDuelAndPlayer($duel, $player);

    // Récupérer la carte en main via son token
    /** @var Game4Card|null $card */
    $card = $this->cardRepo->findOneBy([
        'token' => $cardToken,
        'owner' => $player,
        'zone'  => Game4Card::ZONE_HAND,
    ]);

    if (!$card) {
        return $this->jsonOk([
            'success'  => false,
            'message'  => 'card_not_found_in_hand',
            'resolved' => false,
        ], Response::HTTP_NOT_FOUND);
    }

    $def = $card->getDef();
    if (!$def) {
        return $this->jsonOk([
            'success'  => false,
            'message'  => 'card_def_not_found',
            'resolved' => false,
        ], Response::HTTP_NOT_FOUND);
    }

    if ($def->getType() === Game4DuelPlay::TYPE_NUM && $myNumericCount >= 4) {
        return $this->jsonOk([
            'success'  => false,
            'message'  => 'max_rounds_reached_for_player',
            'resolved' => false,
        ], Response::HTTP_CONFLICT);
    }

    // Création du play pour ce duel
    $play = new Game4DuelPlay();
    $play
        ->setDuel($duel)
        ->setPlayer($player)
        ->setCard($card)
        ->setCardCode($def->getCode())
        ->setCardType($def->getType())
        ->setNumValue($this->extractNumValueFromDef($def))
        ->setRoundIndex($myTotalPlays + 1)
        ->setSubmittedAt(new \DateTimeImmutable());

    // La carte quitte la main ? défausse
    $card->setZone(Game4Card::ZONE_DISCARD);

    $this->em->persist($play);
    $this->em->persist($card);
    $this->em->flush();

    // Appel au service de résolution du duel
    $res = $this->duelService->resolve($duel, $this->em);

    // Relecture de tous les plays pour construire la vue
    $allPlays = $this->playRepo->findBy(
        ['duel' => $duel],
        ['roundIndex' => 'ASC', 'submittedAt' => 'ASC']
    );

    $playsView = array_map(function (Game4DuelPlay $p) {
        return [
            'playerId' => $p->getPlayer()->getId(),
            'cardCode' => $p->getCardCode(),
            'cardType' => $p->getCardType(),
            'numValue' => $p->getCardType() === Game4DuelPlay::TYPE_NUM ? $p->getNumValue() : null,
            'round'    => $p->getRoundIndex(),
        ];
    }, $allPlays);

    // Recompte des cartes numériques jouées pour informer le client
    $myCount       = $this->playRepo->countNumericByDuelAndPlayer($duel, $player);
    $oppCount      = $this->playRepo->countNumericByDuelAndPlayer($duel, $opponent);
    $canPlayMore   = $myCount < 4;

    // Si le service a résolu le duel, on renvoie le résultat complet
    if ($duel->getStatus() === Game4Duel::STATUS_RESOLVED) {
        $isWinner = ($res->winnerId !== null && $res->winnerId === $player->getId());

        return $this->jsonOk([
            'success'  => true,
            'duelId'   => $duel->getId(),
            'status'   => 'RESOLVED',
            'resolved' => true,
            'message'  => 'card_confirmed',
            'result'   => [
                'winnerId'      => $res->winnerId,
                'messageForYou' => $res->winnerId === null
                    ? 'Égalité.'
                    : ($isWinner ? 'Vous avez gagné !' : 'Vous avez perdu...'),
                'logs'          => $res->logs,
                'effects'       => $res->effects,
                'wonCardCode'   => $res->wonCardCode ?? null,
                'wonCardLabel'  => $res->wonCardLabel ?? null,
            ],
            'plays'    => $playsView,
            'state'    => $this->buildG4State($duel->getGame(), $player),
        ]);
    }

    // Sinon : duel encore en attente
    return $this->jsonOk([
        'success'            => true,
        'duelId'             => $duel->getId(),
        'status'             => 'PENDING',
        'resolved'           => false,
        'message'            => 'card_confirmed',
        'youHaveSubmitted'   => true,
        'myPlaysCount'       => $myCount,
        'opponentPlaysCount' => $oppCount,
        'canPlayMore'        => $canPlayMore,
        'plays'              => $playsView,
        'state'              => $this->buildG4State($duel->getGame(), $player),
    ]);
}

    // -------- ?preuve 4 : deck (NUM + sp?ciales) --------
    private function extractNumValueFromDef(Game4CardDef $def): ?int
    {
        if ($def->getType() !== Game4DuelPlay::TYPE_NUM) {
            return null;
        }

        if (preg_match('/^NUM_(\d+)$/', (string) $def->getCode(), $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

// DUEL STATUS - VERSION CORRIG?E (UTF-8 safe)
// DUEL STATUS - VERSION UTF-8 SAFE
#[Route('/api/mobile/duelStatus', name: 'g4_duel_status', methods: ['GET'])]
public function g4_duel_status(Request $req): JsonResponse
{
    $duelId   = (int) $req->query->get('duelId', 0);
    $equipeId = (int) $req->query->get('equipeId', 0); // joueur appelant

    /** @var Game4Duel|null $duel */
    $duel = $this->em->find(Game4Duel::class, $duelId);
    if (!$duel) {
        return $this->jsonOk([
            'success'  => false,
            'duelId'   => $duelId,
            'status'   => 'UNKNOWN',
            'resolved' => true,
            'logs'     => ['duel_not_found'],
            'plays'    => [],
            'state'    => null,
        ], Response::HTTP_NOT_FOUND);
    }

    $game = $duel->getGame();
    if (!$game) {
        return $this->jsonOk([
            'success'  => false,
            'duelId'   => $duel->getId(),
            'status'   => 'ERROR',
            'resolved' => true,
            'logs'     => ['game_not_found_for_duel'],
            'plays'    => [],
            'state'    => null,
        ], 500);
    }

    // Joueur "moi" (optionnel pour personnaliser le message)
    $me = null;
    if ($equipeId > 0) {
        $me = $this->playerRepo->findOneByGameAndEquipe($game, $equipeId);
    }

    // Relecture des coups
    $allPlays = $this->playRepo->findBy(
        ['duel' => $duel],
        ['roundIndex' => 'ASC', 'submittedAt' => 'ASC']
    );

    $playsView = array_map(function (Game4DuelPlay $p) {
        return [
            'playerId' => $p->getPlayer()->getId(),
            'cardCode' => $p->getCardCode(),
            'cardType' => $p->getCardType(),
            'numValue' => $p->getCardType() === Game4DuelPlay::TYPE_NUM ? $p->getNumValue() : null,
            'round'    => $p->getRoundIndex(),
        ];
    }, $allPlays);

    // Appel au service de résolution : si déjà résolu, il renvoie juste les infos stockées
    $res = $this->duelService->resolve($duel, $this->em);

    // Si le duel n'est pas encore résolu
    if ($duel->getStatus() !== Game4Duel::STATUS_RESOLVED) {
        return $this->jsonOk([
            'success'  => true,
            'duelId'   => $duel->getId(),
            'status'   => 'PENDING',
            'resolved' => false,
            'logs'     => $res->logs ?? [],
            'plays'    => $playsView,
            'state'    => $this->buildG4State($game, $me),
        ]);
    }

    // Duel résolu : on renvoie le résultat
    $isWinner = false;
    if ($me && isset($res->winnerId) && $res->winnerId !== null) {
        $isWinner = ($res->winnerId === $me->getId());
    }

    $messageForYou = null;
    if ($me) {
        $messageForYou = $res->winnerId === null
            ? 'Égalité.'
            : ($isWinner ? 'Vous avez gagné !' : 'Vous avez perdu...');
    }

    return $this->jsonOk([
        'success'  => true,
        'duelId'   => $duel->getId(),
        'status'   => 'RESOLVED',
        'resolved' => true,
        'result'   => [
            'winnerId'      => $res->winnerId,
            'messageForYou' => $messageForYou,
            'logs'          => $res->logs ?? [],
            'effects'       => $res->effects ?? [],
            'wonCardCode'   => $res->wonCardCode ?? null,
            'wonCardLabel'  => $res->wonCardLabel ?? null,
        ],
        'plays'    => $playsView,
        'state'    => $this->buildG4State($game, $me),
    ]);
}

#[Route('/api/mobile/duelForceResolve', name: 'g4_duel_force_resolve', methods: ['POST'])]
public function g4_duel_force_resolve(Request $req): JsonResponse
{
    $data = json_decode($req->getContent(), true) ?? [];

    $duelId   = (int)($data['duelId'] ?? 0);
    $equipeId = (int)($data['equipeId'] ?? 0);

    if ($duelId <= 0 || $equipeId <= 0) {
        return $this->jsonOk([
            'success' => false,
            'message' => 'invalid_payload',
            'errors'  => ['duelId', 'equipeId'],
        ], Response::HTTP_BAD_REQUEST);
    }

    /** @var Game4Duel|null $duel */
    $duel = $this->em->find(Game4Duel::class, $duelId);
    if (!$duel) {
        return $this->jsonOk([
            'success'  => false,
            'message'  => 'duel_not_found',
            'resolved' => true,
        ], Response::HTTP_NOT_FOUND);
    }

    $game = $duel->getGame();
    if (!$game) {
        return $this->jsonOk([
            'success'  => false,
            'message'  => 'game_not_found_for_duel',
            'resolved' => true,
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    $player = $this->playerRepo->findOneByGameAndEquipe($game, $equipeId);
    if (!$player) {
        return $this->jsonOk([
            'success'  => false,
            'message'  => 'player_not_in_game',
            'resolved' => false,
        ], Response::HTTP_NOT_FOUND);
    }

    $playerId = $player->getId();
    $playerA  = $duel->getPlayerA();
    $playerB  = $duel->getPlayerB();

    if (!$playerId || ($playerA?->getId() !== $playerId && $playerB?->getId() !== $playerId)) {
        return $this->jsonOk([
            'success'  => false,
            'message'  => 'player_not_in_duel',
            'resolved' => false,
        ], Response::HTTP_FORBIDDEN);
    }

    $res = $this->duelService->forceResolve($duel, $this->em, $player);

    $allPlays = $this->playRepo->findBy(
        ['duel' => $duel],
        ['roundIndex' => 'ASC', 'submittedAt' => 'ASC']
    );

    $playsView = array_map(function (Game4DuelPlay $p) {
        return [
            'playerId' => $p->getPlayer()->getId(),
            'cardCode' => $p->getCardCode(),
            'cardType' => $p->getCardType(),
            'numValue' => $p->getCardType() === Game4DuelPlay::TYPE_NUM ? $p->getNumValue() : null,
            'round'    => $p->getRoundIndex(),
        ];
    }, $allPlays);

    $status   = $duel->getStatus();
    $resolved = $status === Game4Duel::STATUS_RESOLVED;

    $messageForYou = null;
    if ($resolved) {
        $messageForYou = $res->winnerId === null
            ? 'Égalité.'
            : ($res->winnerId === $playerId ? 'Vous avez gagné !' : 'Vous avez perdu...');
    }

    return $this->jsonOk([
        'success'  => true,
        'duelId'   => $duel->getId(),
        'status'   => $status,
        'resolved' => $resolved,
        'message'  => 'force_resolve_triggered',
        'result'   => [
            'winnerId'      => $res->winnerId ?? null,
            'messageForYou' => $messageForYou,
            'logs'          => $res->logs ?? [],
            'effects'       => $res->effects ?? [],
            'wonCardCode'   => $res->wonCardCode ?? null,
            'wonCardLabel'  => $res->wonCardLabel ?? null,
            'pointsDelta'   => $res->pointsDelta ?? 0,
        ],
        'plays'    => $playsView,
        'state'    => $this->buildG4State($game, $player),
    ]);
}



    // =====================================================================
    // ==========================  HELPERS  =================================
    // =====================================================================

    // -------- ?preuve 4 : build state --------

private function buildG4State(Game4Game $game, ?Game4Player $me, ?string $serverMsg = null): array
{
    $now = new \DateTimeImmutable();
    $timeLeft = $game->getEndsAt() ? max(0, $game->getEndsAt()->getTimestamp() - $now->getTimestamp()) : 0;

    $cooldownLeft = 0;
    if (!$this->duelRepo->hasPending($game)) {
        $lastResolved = $this->duelRepo->findLastResolved($game);
        if ($lastResolved && $lastResolved->getResolvedAt()) {
            $elapsed = max(0, $now->getTimestamp() - $lastResolved->getResolvedAt()->getTimestamp());
            $cooldownLeft = max(0, self::G4_DUEL_COOLDOWN_SECONDS - $elapsed);
        }
    }

    $players = [];
    foreach ($this->playerRepo->findAliveByGame($game) as $p) {
        $players[] = [
            'playerId'     => $p->getId(),
            'name'         => $p->getName(),
            'lives'        => $p->getLives(),
            'isAlive'      => $p->isAlive(),
            'isTargetable' => $me ? $p->getId() !== $me->getId() : true,
            // ? utilise la propriété persistée (et plus le role pour l’UI)
            'isZombie'     => $p->isZombie(),
        ];
    }

    $hand = [];
    if ($me) {
        $q = $this->em->createQuery('SELECT c FROM App\Entity\Game4Card c WHERE c.game = :g AND c.owner = :p AND c.zone = :z')
            ->setParameter('g', $game)->setParameter('p', $me)->setParameter('z', Game4Card::ZONE_HAND);
        $cards = $q->getResult();
        foreach ($cards as $c) {
            /** @var Game4Card $c */
            $code  = (string)$c->getDef()->getCode();
            $value = null;
            if (preg_match('/^NUM_(\d+)$/', $code, $m)) {
                $value = (int)$m[1];
            }
            $hand[] = [
                'cardId'    => $code,
                'token'     => $c->getToken(),
                'label'     => $this->toUtf8($c->getDef()->getLabel(), true),
                'text'      => $this->toUtf8($c->getDef()->getText(),  true),
                'type'      => $c->getDef()->getType(),
                'value'     => $value,
                'isSpecial' => in_array($c->getDef()->getType(), self::G4_SPECIAL_TYPES, true),
            ];
        }
    }

    // Bannière duel entrant (dernier PENDING où me est playerB)
    $incoming = null;
    if ($me) {
        $qb = $this->em->createQueryBuilder();
        $duel = $qb->select('d')->from(Game4Duel::class, 'd')
            ->where('d.game = :g')->setParameter('g', $game)
            ->andWhere('d.status = :s')->setParameter('s', Game4Duel::STATUS_PENDING)
            ->andWhere('d.playerB = :me')->setParameter('me', $me)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();

        if ($duel) {
            $incoming = [
                'duelId'       => $duel->getId(),
                'opponentId'   => $duel->getPlayerA()->getId(),
                'opponentTeam' => $duel->getPlayerA()->getEquipeId(),
                'banner'       => sprintf("Vous avez été choisi par l'équipe %d", $duel->getPlayerA()->getEquipeId()),
            ];
        }
    }

    return [
        'gameId'        => $game->getId(),
        'round'         => 0,
        'roundEndsAt'   => $game->getEndsAt()?->format('c'),
        'timeLeftSec'   => $timeLeft,
        'you'           => $me ? [
            'playerId'    => $me->getId(),
            'equipeId'    => $me->getEquipeId(),
            'role'        => $me->getRole(),
            'isZombie'    => $me->isZombie(),
            'lives'       => $me->getLives(),
            'lockedInDuel'=> $me->isLockedInDuel(),
        ] : null,
        'counts'        => [
            'humans'  => $this->playerRepo->countByGameAndRole($game, Game4Player::ROLE_HUMAN),
            'zombies' => $this->playerRepo->countByGameAndRole($game, Game4Player::ROLE_ZOMBIE),
        ],
        'hand'          => $hand,
        'players'       => $players,
        'drawCount'     => $game->getDrawCount(),
        'discardCount'  => $game->getDiscardCount(),
        'incomingDuel'  => $incoming,
        'duelCooldownSec' => $cooldownLeft,
        'serverMessage' => $serverMsg,
    ];
}


    // -------- ?preuve 4 : deck (NUM + sp?ciales) --------
    private function ensureDeck(Game4Game $game): void
    {
       // 1) Définitions des cartes (NUM 1..10 + spéciales)
$defs = [
    // --- Numériques 1..10 ---
    ['code' => 'NUM_1',  'label' => '1',  'text' => 'Valeur 1',  'type' => 'NUM'],
    ['code' => 'NUM_2',  'label' => '2',  'text' => 'Valeur 2',  'type' => 'NUM'],
    ['code' => 'NUM_3',  'label' => '3',  'text' => 'Valeur 3',  'type' => 'NUM'],
    ['code' => 'NUM_4',  'label' => '4',  'text' => 'Valeur 4',  'type' => 'NUM'],
    ['code' => 'NUM_5',  'label' => '5',  'text' => 'Valeur 5',  'type' => 'NUM'],
    ['code' => 'NUM_6',  'label' => '6',  'text' => 'Valeur 6',  'type' => 'NUM'],
    ['code' => 'NUM_7',  'label' => '7',  'text' => 'Valeur 7',  'type' => 'NUM'],
    ['code' => 'NUM_8',  'label' => '8',  'text' => 'Valeur 8',  'type' => 'NUM'],
    ['code' => 'NUM_9',  'label' => '9',  'text' => 'Valeur 9',  'type' => 'NUM'],
    ['code' => 'NUM_10', 'label' => '10', 'text' => 'Valeur 10', 'type' => 'NUM'],

    // --- Spéciales ---
    ['code' => 'ZOMBIE',  'label' => 'Contagion', 'text' => 'Pouvoir du zombie', 'type' => 'ZOMBIE'],
    ['code' => 'VACCINE', 'label' => 'Vaccin',     'text' => 'Protège un humain', 'type' => 'VACCINE'],
    ['code' => 'SHOTGUN', 'label' => 'Fusil',      'text' => 'Attaque spéciale',  'type' => 'SHOTGUN'],
];

        foreach ($defs as $d) {
            $def = $this->defRepo->findOneByCode($d['code']);
            if (!$def) {
            $def = (new Game4CardDef())
                ->setCode($this->toUtf8($d['code']))
                ->setLabel($this->toUtf8($d['label']))
                ->setText($this->toUtf8($d['text']))
                ->setType($this->toUtf8($d['type']));
            $this->em->persist($def);
        }
        }
        $this->em->flush();

        // 2) Si un deck existe d?j?, ne pas reconstituer
        $countDeck = $this->cardRepo->countInZone($game, Game4Card::ZONE_DECK);
        if ($countDeck > 0) return;

        // 3) Constitution du deck (?quilibrage ? ajuster selon ton gameplay)
        $copies = [
    'NUM_1' => 8, 'NUM_2' => 8, 'NUM_3' => 8, 'NUM_4' => 8, 'NUM_5' => 8,
    'NUM_6' => 8, 'NUM_7' => 8, 'NUM_8' => 8, 'NUM_9' => 8, 'NUM_10' => 8,
    'ZOMBIE' => 4, 'VACCINE' => 10, 'SHOTGUN' => 10,
];

        foreach ($copies as $code => $n) {
            $def = $this->defRepo->findOneByCode($code);
            for ($i = 0; $i < $n; $i++) {
                $card = (new Game4Card())
                    ->setGame($game)
                    ->setDef($def)
                    ->setZone(Game4Card::ZONE_DECK)
                    ->setToken($this->makeToken($game->getId(), null, $def->getCode()));
                $this->em->persist($card);
            }
        }
        $this->em->flush();
    }

    // -------- ?preuve 4 : tirage respectant ?ligibilit? & quota --------
    private function drawOne(Game4Game $game, Game4Player $player): ?Game4Card
    {
        $handCount = $this->cardRepo->countByOwnerAndZone($player, Game4Card::ZONE_HAND);

        if ($handCount >= self::G4_MAX_HAND) {
            return null;
        }

        $role = $player->getRole();
        $needsMandatory = null;

        if ($role === Game4Player::ROLE_HUMAN) {
            if (!$this->cardRepo->handHasCode($player, 'SHOTGUN')) {
                $needsMandatory = 'SHOTGUN';
            }
        } elseif ($handCount === 0) {
            $needsMandatory = 'ZOMBIE';
        }

        if ($needsMandatory) {
            $card = $this->giveSpecificFromDeckOrForge($game, $player, $needsMandatory);
            if ($card instanceof Game4Card) {
                return $card;
            }
        }

        $maxAttempts     = 30;
        $currentSpecials = $this->countSpecialsInHand($player);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $card = $this->cardRepo->pickFromDeck($game);
            if (!$card) {
                return null;
            }

            $def       = $card->getDef();
            $isSpecial = $this->isSpecialDef($def);
            $code      = strtoupper($def->getCode());
            $type      = strtoupper($def->getType());

            if (!$this->isDefAllowedForPlayer($def, $player)) {
                $card->setOwner(null)->setZone(Game4Card::ZONE_DECK);
                $this->em->persist($card);
                continue;
            }

            if (($type === 'ZOMBIE' || $code === 'ZOMBIE') && $this->cardRepo->handHasCode($player, 'ZOMBIE')) {
                $card->setOwner(null)->setZone(Game4Card::ZONE_DECK);
                $this->em->persist($card);
                continue;
            }

            if ($isSpecial && $currentSpecials >= 2) {
                $card->setOwner(null)->setZone(Game4Card::ZONE_DECK);
                $this->em->persist($card);
                continue;
            }

            $card->setOwner($player)
                 ->setZone(Game4Card::ZONE_HAND)
                 ->setToken($this->makeToken($game->getId(), $player->getId(), $def->getCode()));
            $game->incDrawCount();

            if ($isSpecial) {
                $currentSpecials++;
            }

            return $card;
        }

        return null;
    }

    /** Compte les cartes spéciales dans la main d’un joueur */
private function countSpecialsInHand(Game4Player $player): int
{
    return $this->cardRepo->countSpecialsInHandByTypes($player, self::G4_SPECIAL_TYPES);
}

    /** Renvoie true si la def est spéciale (ZOMBIE/VACCINE/SHOTGUN) */
private function isSpecialDef(Game4CardDef $def): bool
{
    return in_array(strtoupper($def->getType()), self::G4_SPECIAL_TYPES, true);
}
/** Nombre de cartes numériques dans la main */
private function countNumericsInHand(Game4Player $player): int
{
    return $this->cardRepo->countNumericsInHand($player);
}
/** Tente de piocher une carte précise (par code) depuis le deck */
private function pickSpecificFromDeck(Game4Game $game, string $code): ?Game4Card
{
    return $this->cardRepo->pickFromDeckByCode($game, $code); // voir patch repo plus bas
}
/** Forcer au moins un SHOTGUN chez un humain */
private function ensureShotgunForHuman(Game4Game $game, Game4Player $player): void
{
    if ($player->getRole() !== Game4Player::ROLE_HUMAN) return;

    $hasShotgun = $this->cardRepo->handHasCode($player, 'SHOTGUN');
    if ($hasShotgun) return;

    $shot = $this->pickSpecificFromDeck($game, 'SHOTGUN');
    if (!$shot) return; // rien à faire si deck épuisé côté SHOTGUN

    $shot->setOwner($player)->setZone(Game4Card::ZONE_HAND)
         ->setToken($this->makeToken($game->getId(), $player->getId(), 'SHOTGUN'));
    $game->incDrawCount();
    $this->em->persist($shot);

    // Si on dépasse 7, on défausse une NUM (priorité : la plus basse)
    $hand = $this->cardRepo->findHandByPlayer($player);
    if (count($hand) > self::G4_MAX_HAND) {
        $toDiscard = null; $lowest = PHP_INT_MAX;
        foreach ($hand as $c) {
            $t = strtoupper($c->getDef()->getType());
            if ($t === 'NUM') {
                if (preg_match('/(\d+)/', $c->getDef()->getCode(), $m)) {
                    $v = (int)$m[1];
                    if ($v < $lowest) { $lowest = $v; $toDiscard = $c; }
                }
            }
        }
        if ($toDiscard) {
            $toDiscard->setOwner(null)->setZone(Game4Card::ZONE_DISCARD);
            $game->incDiscardCount();
            $this->em->persist($toDiscard);
        }
    }
}

/** Distribue 7 cartes avec =5 NUM ; laisse les VACCINE / ZOMBIE / SHOTGUN passer selon éligibilité.
 *  Termine par une garantie SHOTGUN pour les HUMAINs. */
private function dealInitialHand(Game4Game $game, Game4Player $player): void
{
    // Sécurité : vider la main existante si ré-entrée
    $handCount = $this->cardRepo->countByOwnerAndZone($player, Game4Card::ZONE_HAND);
    if ($handCount > 0) {
        foreach ($this->cardRepo->findHandByPlayer($player) as $c) {
            $c->setOwner(null)->setZone(Game4Card::ZONE_DECK);
        }
        $this->em->flush();
    }
    
   
// 1) D’abord piocher 5 NUM minimum — version sûre mémoire
$need = max(0, 5 - $this->countNumericsInHand($player));
$tries = 0;                 // garde-fou
$maxTries = 50;             // évite boucle folle si deck incompatible

while ($need > 0 && $tries < $maxTries) {
    $tries++;

    // doit retourner UNE SEULE carte ou null (cf. repo plus bas)
    $card = $this->cardRepo->pickNumericFromDeck($game);
    if (!$card) {
        // plus de NUM disponibles dans le deck
        break;
    }

    // Assigne sans persist (entité déjà managed par Doctrine)
    $card->setOwner($player)
         ->setZone(Game4Card::ZONE_HAND)
         ->setToken($this->makeToken($game->getId(), $player->getId(), $card->getDef()->getCode()));

    $game->incDrawCount();
    $need--;

    // flush léger pour éviter d'accumuler des objets en UoW
    $this->em->flush();

    // (optionnel) si tu manipules beaucoup d’entités ailleurs :
    // $this->em->clear(Game4Card::class); // nécessite Doctrine >= 2.7
}

// 2) Compléter jusqu’à 7 avec drawOne() (respecte quota spé & éligibilité)
//    -> on évite les requêtes en boucle et on sort si drawOne() renvoie null
$handCount     = $this->cardRepo->countByOwnerAndZone($player, Game4Card::ZONE_HAND);
$deckRemaining = $this->cardRepo->countInZone($game, Game4Card::ZONE_DECK);
$attempts      = 0;
$attemptsMax   = 20; // garde-fou global

while ($handCount < self::G4_MAX_HAND && $deckRemaining > 0 && $attempts < $attemptsMax) {
    $card = $this->drawOne($game, $player);
    $attempts++;

    if (!$card) {
        // Aucune carte admissible (quotas/eligibilité bloquants) -> on arrête proprement
        break;
    }

    // Succès : on a réellement ajouté une carte en main
    $handCount++;
    $deckRemaining--; // la carte a quitté le deck
}


    // 3) Garanti : si HUMAIN -> au moins un SHOTGUN en main
    $this->ensureShotgunForHuman($game, $player);

    $this->em->flush();
}


    private function isDefAllowedForPlayer(Game4CardDef $def, Game4Player $player): bool
    {
        $t = $def->getType();
        if ($t === 'NUM') return true; // toujours OK

        if ($t === 'ZOMBIE') {
            return $player->getRole() === Game4Player::ROLE_ZOMBIE;
        }

        if (in_array($t, ['VACCINE', 'SHOTGUN'], true)) {
            return $player->getRole() === Game4Player::ROLE_HUMAN;
        }

        return false;
    }

    // -------- Token --------
    private function makeToken(int $gameId, ?int $playerId, string $code): string
    {
        $secret = $_ENV['APP_EPREUVE4_SECRET'] ?? 'change-me';
        $base = $gameId.'|'.($playerId ?? 0).'|'.$code.'|'.bin2hex(random_bytes(8));
        return hash_hmac('sha256', $base, $secret);
    }

    // -------- Helpers JSON / UTF-8 --------
   private function jsonOk(mixed $data, int $status = 200, array $headers = []): JsonResponse
{
    $options = \JSON_UNESCAPED_UNICODE;
    // Certains environnements anciens peuvent ne pas d?finir la constante
    if (\defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $options |= \JSON_INVALID_UTF8_SUBSTITUTE;
    }

    return $this->json(
        $this->utf8ize($data),
        $status,
        $headers,
        ['json_encode_options' => $options]
    );
}


    private function utf8ize(mixed $data): mixed
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) $data[$k] = $this->utf8ize($v);
            return $data;
        }
        if (is_object($data)) {
            foreach ($data as $k => $v) $data->$k = $this->utf8ize($v);
            return $data;
        }
        if (is_string($data) && !mb_check_encoding($data, 'UTF-8')) {
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }
        return $data;
    }

    private function toUtf8(?string $s, bool $normalize = false): ?string
    {
        if ($s === null) return null;
        if (!preg_match('//u', $s)) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }
        if ($normalize && class_exists(\Normalizer::class) && !\Normalizer::isNormalized($s, \Normalizer::FORM_C)) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_C);
        }
        return $s;
    }
    
    private function toCardDto(Game4Card $card): array
{
    $def = $card->getDef();
    $code = $def->getCode();

    // valeur numérique éventuelle (NUM_1..NUM_5), sinon null
    $value = null;
    if (preg_match('/^NUM_(\d+)$/', (string)$code, $m)) {
        $value = (int)$m[1];
    }

    return [
        'cardId' => $code,                                     // ex: NUM_3, ZOMBIE...
        'token'  => $card->getToken(),                         // jeton unique (pour jouer)
        'label'  => $this->toUtf8($def->getLabel(), true),     // libellé lisible
        'text'   => $this->toUtf8($def->getText(),  true),     // description
        'type'   => $def->getType(),                           // NUM | ZOMBIE | VACCINE | SHOTGUN
        'value'  => $value,                                    // entier pour NUM_*, sinon null
        'isSpecial' => in_array($def->getType(), self::G4_SPECIAL_TYPES, true),
    ];
}
// ?? Signature alignée avec ton appel: ($game, $player)
private function giveMandatorySpecialOnFirstJoin(Game4Game $game, Game4Player $player): void
{
    $role = strtoupper((string)$player->getRole());
    if ($role === Game4Player::ROLE_HUMAN) {
        $this->giveSpecificFromDeckOrForge($game, $player, 'SHOTGUN');
        return;
    }
    if ($role === Game4Player::ROLE_ZOMBIE) {
        $this->giveSpecificFromDeckOrForge($game, $player, 'ZOMBIE');
        return;
    }
    // autres rôles : rien au join
}


}
