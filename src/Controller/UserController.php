<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Utils\Utils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    #[Route('/api/mobile/user', name: 'app_mobile_user_with_team', methods: ['POST'])]
    public function getUserWithTeam(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        if (0 !== strpos((string) $request->headers->get('Content-Type', ''), 'application/json')) {
            return $this->jsonOk(
                ['success' => false, 'message' => 'Content-Type must be application/json'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $data = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->jsonOk([
                'success' => false,
                'message' => 'JSON invalide',
                'details' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $email = $this->toUtf8($data->Email ?? null);
        $pwd   = $this->toUtf8($data->Password ?? null);

        if (!$email || !$pwd) {
            return $this->jsonOk([
                'success' => false,
                'message' => 'Champs manquants',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => mb_strtolower($email)]);
        if (!$user || !$passwordHasher->isPasswordValid($user, $pwd)) {
            return $this->jsonOk([
                'success' => false,
                'message' => 'Identifiants incorrects',
            ], Response::HTTP_OK);
        }

        $utils = new Utils();

        $estAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);

        $response = $utils->GetJsonResponse($request, $user, [
            'password',
            'roles',
            'latEquipe.competitions',
            'latEquipe.lesUsers',
            'latEquipe.lestScores',
        ]);

        try {
            $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->jsonOk([
                'success' => false,
                'message' => 'Réponse JSON invalide',
                'details' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $responseData['estAdmin'] = $estAdmin;

        return $this->jsonOk($responseData);
    }

    private function jsonOk(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        $options = \JSON_UNESCAPED_UNICODE;
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
            foreach ($data as $k => $v) {
                $data[$k] = $this->utf8ize($v);
            }

            return $data;
        }

        if (is_object($data)) {
            foreach ($data as $k => $v) {
                $data->$k = $this->utf8ize($v);
            }

            return $data;
        }

        if (is_string($data) && !mb_check_encoding($data, 'UTF-8')) {
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

        return $data;
    }

    private function toUtf8(?string $s, bool $normalize = false): ?string
    {
        if ($s === null) {
            return null;
        }

        if (!preg_match('//u', $s)) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

        if ($normalize && class_exists(\Normalizer::class) && !\Normalizer::isNormalized($s, \Normalizer::FORM_C)) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_C);
        }

        return $s;
    }
}
