<?php

namespace App\Service\Geo;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final readonly class NominatimClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl = 'https://nominatim.openstreetmap.org'
    ) {
    }

    public function search(string $q, int $limit = 5, string $lang = 'fr'): array
    {
        $url = rtrim($this->baseUrl, '/').'/search';

        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => [
                    'q' => $q,
                    'format' => 'json',
                    'addressdetails' => 1,
                    'limit' => $limit,
                    'accept-language' => $lang,
                ],
                'headers' => [
                    'User-Agent' => 'Vinylo/1.0 (+contact@nicolasvauche.net)',
                ],
                'timeout' => 2.0,
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('provider_unreachable', previous: $e);
        }

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('provider_unreachable');
        }

        /** @var array<mixed> $data */
        $data = $response->toArray(false);

        return is_array($data) ? $data : [];
    }
}
