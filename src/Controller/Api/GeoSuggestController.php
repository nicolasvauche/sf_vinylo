<?php

namespace App\Controller\Api;

use App\Service\Geo\NominatimClient;
use App\Service\Geo\NominatimNormalizer;
use Psr\Cache\CacheItemInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;

final class GeoSuggestController extends AbstractController
{
    #[Route('/api/geo/suggest', name: 'api_geo_suggest', methods: ['GET'])]
    public function __invoke(
        Request $request,
        NominatimClient $client,
        NominatimNormalizer $normalizer,
        CacheInterface $cache
    ): JsonResponse {
        $q = trim((string)$request->query->get('q', ''));
        $limit = (int)($request->query->get('limit', 5));
        $lang = (string)$request->query->get('lang', 'fr');

        if (mb_strlen($q) < 3) {
            return $this->json([
                'error' => 'query_too_short',
                'message' => 'Le paramètre q doit contenir au moins 3 caractères.',
            ], 400);
        }

        $limit = max(1, min($limit, 8));
        $providerLimit = min($limit * 3, 20);

        $cacheKey = 'geo_suggest_'.md5($q.'|'.$lang.'|'.$limit);

        try {
            /** @var array<int, array<string, string>> $suggestions */
            $suggestions = $cache->get(
                $cacheKey,
                function (CacheItemInterface $item) use ($client, $normalizer, $q, $limit, $providerLimit, $lang) {
                    $item->expiresAfter(600);
                    $raw = $client->search($q, $providerLimit, $lang);
                    $normalized = $normalizer->normalize($raw);

                    return array_slice($normalized, 0, $limit);
                }
            );
        } catch (\RuntimeException $e) {
            return $this->json([
                'error' => 'provider_unreachable',
                'message' => 'Le service de géocodage est momentanément indisponible.',
            ], 503);
        }

        return $this->json($suggestions, 200);
    }
}
