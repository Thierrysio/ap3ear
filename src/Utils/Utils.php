<?php

namespace App\Utils;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class Utils
{
    /**
     * Sérialise $var en JSON, avec exclusions :
     *  - $tab peut contenir des noms "plats" (ex: 'password', 'roles')
     *  - et/ou des chemins en notation pointée (ex: 'lestEpreuves.lestScores.latEquipe.lestScores')
     */
    public function GetJsonResponse(Request $request, $var, array $tab = []): JsonResponse
    {
        // 1) Sépare exclusions "plates" et exclusions "chemins"
        [$ignoredAttrs, $pathExclusions] = $this->splitExclusions($tab);

        // 2) Contexte par défaut
        $defaultContext = [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                return method_exists($object, 'getId') ? $object->getId() : null;
            },
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ];

        $normalizer     = new ObjectNormalizer(null, null, null, null, null, null, $defaultContext);
        $dateNormalizer = new DateTimeNormalizer(['datetime_format' => 'Y-m-d H:i:s']);
        $serializer     = new Serializer([$dateNormalizer, $normalizer], [new JsonEncoder()]);

        // 3) IGNORED_ATTRIBUTES (plats) + nettoyages Doctrine
        $ignoredAttrs = array_values(array_unique(array_merge(
            $ignoredAttrs,
            ['__initializer__', '__cloner__', '__isInitialized__']
        )));

        $context = [AbstractNormalizer::IGNORED_ATTRIBUTES => $ignoredAttrs] + $defaultContext;

        // 4) On normalise en tableau pour pouvoir post-filtrer par chemins
        $data = $serializer->normalize($var, 'json', $context);

        // 5) Supprime les chemins pointés (gère automatiquement les tableaux)
        foreach ($pathExclusions as $path) {
            $this->removePath($data, explode('.', $path));
        }

        // 6) Réponse JSON
        return new JsonResponse($data, Response::HTTP_OK);
    }

    // ------------------ Helpers exclusions ------------------

    private function splitExclusions(array $tab): array
    {
        $ignored = [];
        $paths   = [];

        foreach ($tab as $item) {
            if (strpos($item, '.') === false) {
                $ignored[] = $item; // exclusion "plate"
            } else {
                $paths[] = $item;   // exclusion "chemin"
            }
        }
        return [$ignored, $paths];
    }

    /**
     * Supprime la clé correspondant au chemin $segments.
     * Gère les tableaux (propagation récursive sur chaque élément).
     */
    private function removePath(&$node, array $segments): void
    {
        if (empty($segments)) return;

        $key = array_shift($segments);

        // Si tableau indexé => propage sur chaque item
        if (is_array($node) && $this->isList($node)) {
            foreach ($node as &$item) {
                $this->removePath($item, array_merge([$key], $segments));
            }
            return;
        }

        // Si associatif avec la clé recherchée
        if (is_array($node) && array_key_exists($key, $node)) {
            if (empty($segments)) {
                unset($node[$key]); // suppression finale
                return;
            }
            // Descendre et continuer
            $this->removePath($node[$key], $segments);

            // Optionnel: si vide après suppression, on peut nettoyer
            if (is_array($node[$key]) && empty($node[$key])) {
                // unset($node[$key]);
            }
        }
    }

    private function isList(array $arr): bool
    {
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    // ------------------ Erreurs utilitaires ------------------

    public static function ErrorMissingArguments(): Response
    {
        return new Response('MISSING_ARGUMENTS_PARAMETERS', 400, ['Content-Type' => 'text/html']);
    }

    public static function ErrorMissingArgumentsDebug($content): Response
    {
        return new Response('MISSING_ARGUMENTS_PARAMETERS content :' . $content, 400, ['Content-Type' => 'text/html']);
    }

    public static function ErrorCustom($message): Response
    {
        return new Response($message, 400, ['Content-Type' => 'text/html']);
    }

    public static function ErrorInvalidCredentials(): Response
    {
        return new JsonResponse(['error' => 'INVALID_CREDENTIALS'], Response::HTTP_UNAUTHORIZED);
    }
}