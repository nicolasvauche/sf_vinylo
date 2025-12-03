<?php

namespace App\Service\Ai\AddRecord;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class AiHttpClient implements AiClientInterface
{
    private const ALLOWED_FORMATS = ['33T', '45T', 'Maxi45T', '78T', 'Mixte', 'Inconnu'];

    public function __construct(
        private HttpClientInterface $openaiClient,
        private ?string $token,
        private string $model,
        private LoggerInterface $aiLogger
    ) {
    }

    public function enrich(array $input, array $discogsCandidates): array
    {
        if (!$this->token) {
            $this->aiLogger->warning('ai.fallback.no_token', ['input' => $input]);

            $displayArtist = $this->titleCase($input['artistCanonical'] ?? '');
            $displayTitle = $this->titleCase($input['recordCanonical'] ?? '');

            return [
                'artist' => [
                    'displayName' => $displayArtist,
                    'canonicalName' => mb_strtolower(trim($displayArtist), 'UTF-8'),
                    'countryCode' => 'XX',
                    'countryName' => null,
                ],
                'record' => [
                    'displayTitle' => $displayTitle,
                    'canonicalTitle' => mb_strtolower(trim($displayTitle), 'UTF-8'),
                    'yearOriginal' => '0000',
                    'format' => 'Inconnu',
                ],
            ];
        }

        $cands = array_slice($discogsCandidates, 0, 5);
        $evidence = [];
        foreach ($cands as $c) {
            $evidence[] = [
                'artistName' => (string)($c['artistName'] ?? ''),
                'recordTitle' => (string)($c['recordTitle'] ?? ''),
                'years' => array_values(array_unique(array_map('strval', (array)($c['years'] ?? [])))),
            ];
        }

        $system = 'Tu es un assistant qui renvoie STRICTEMENT du JSON valide, sans commentaire.';

        $user = [
            'instruction' => "Fusionne une saisie utilisateur et des candidats Discogs en remplissant les champs demandés, y compris un format vinyle probable.",
            'input' => [
                'artistCanonical' => (string)($input['artistCanonical'] ?? ''),
                'recordCanonical' => (string)($input['recordCanonical'] ?? ''),
                'artistRawDisplay' => (string)($input['artistRawDisplay'] ?? ($input['artistCanonical'] ?? '')),
                'recordRawDisplay' => (string)($input['recordRawDisplay'] ?? ($input['recordCanonical'] ?? '')),
            ],
            'discogsEvidence' => $evidence,
            'output_contract' => [
                'artist' => ['displayName', 'canonicalName', 'countryCode', 'countryName'],
                'record' => ['displayTitle', 'canonicalTitle', 'yearOriginal', 'format'],
            ],
            'rules' => [
                // Pays de l'artiste
                'artist_country_inference' =>
                    "Déterminer le pays de l'artiste d'après les connaissances générales (pays d'origine/formation). ".
                    "Utiliser les indices des titres/années fournis pour identifier l'artiste. ".
                    "Si groupe multinational, privilégier le pays de formation. ".
                    "Si incertitude >= 20%, utiliser countryCode='XX' et countryName=null.",
                'countryCode' =>
                    'Doit être un code ISO 3166-1 alpha-2 MAJUSCULE (ex: FR, US). Si inconnu => "XX".',
                'countryName' =>
                    'Nom du pays correspondant, ou null si countryCode="XX".',

                // Année d’origine
                'yearOriginal' =>
                    'Format AAAA, entre 1900 et (année courante + 1), ou "0000" si inconnu.',

                // Format vinyle
                'format_inference' =>
                    "Renvoyer un des formats EXACTS suivants: ".
                    implode(', ', self::ALLOWED_FORMATS).". ".
                    "Heuristiques: Album/LP → 33T ; Single 7\" → 45T ; 12\" single/maxi/remix → Maxi45T ; ".
                    "Shellac ancien (~avant 1958) → 78T ; plusieurs vitesses → Mixte ; sinon → Inconnu. ".
                    "Ne pas inventer d'autres libellés. Si information insuffisante → Inconnu.",

                'no_scores' => true,
                'strict_json' => true,
            ],
        ];

        $this->aiLogger->info('ai.request', [
            'payload' => [
                'input' => $user['input'],
                'candidates_count' => count($discogsCandidates),
            ],
        ]);

        $data = $this->call($system, $user);

        if (is_array($data) && isset($data['output']) && is_array($data['output'])) {
            $data = $data['output'];
        }
        if (!is_array($data)) {
            $this->aiLogger->error('ai.parse_error', ['content' => $data]);

            return $this->fallbackFromInput($input);
        }

        $data['artist'] = is_array($data['artist'] ?? null) ? $data['artist'] : [];
        $data['record'] = is_array($data['record'] ?? null) ? $data['record'] : [];

        // Noms display/canonical
        $artistDisplay = (string)($data['artist']['displayName'] ?? $this->titleCase($input['artistCanonical'] ?? ''));
        $artistCanon = (string)($data['artist']['canonicalName'] ?? mb_strtolower($artistDisplay, 'UTF-8'));
        $recordDisplay = (string)($data['record']['displayTitle'] ?? $this->titleCase($input['recordCanonical'] ?? ''));
        $recordCanon = (string)($data['record']['canonicalTitle'] ?? mb_strtolower($recordDisplay, 'UTF-8'));

        $data['artist']['displayName'] = $artistDisplay;
        $data['artist']['canonicalName'] = mb_strtolower(trim($artistCanon), 'UTF-8');
        $data['record']['displayTitle'] = $recordDisplay;
        $data['record']['canonicalTitle'] = mb_strtolower(trim($recordCanon), 'UTF-8');

        // Pays
        $cc = (string)($data['artist']['countryCode'] ?? '');
        $data['artist']['countryCode'] = preg_match('/^[A-Z]{2}$/', $cc) ? $cc : 'XX';
        $data['artist']['countryName'] = $data['artist']['countryCode'] === 'XX'
            ? null
            : ((is_string(
                    $data['artist']['countryName'] ?? null
                ) && $data['artist']['countryName'] !== '') ? $data['artist']['countryName'] : null);

        // Année d’origine
        $yo = (string)($data['record']['yearOriginal'] ?? '');
        $data['record']['yearOriginal'] = preg_match('/^\d{4}$/', $yo) ? $yo : '0000';

        // FORMAT
        $fmt = (string)($data['record']['format'] ?? '');
        if (!in_array($fmt, self::ALLOWED_FORMATS, true)) {
            $fmt = $this->sanitizeGuessedFormat($fmt, $recordDisplay);
        }
        $data['record']['format'] = $fmt;

        if ($data['artist']['countryCode'] === 'XX' && $artistDisplay !== '') {
            $this->aiLogger->info('ai.retry_country_inference.start', ['artist' => $artistDisplay]);

            $retryUser = [
                'instruction' => "Pour l'artiste suivant, renvoie STRICTEMENT un JSON {\"countryCode\":\"XX|FR|US|...\",\"countryName\":string|null}.",
                'artist' => $artistDisplay,
                'rules' => [
                    "Utilise tes connaissances générales uniquement. Si tu n'es pas sûr, countryCode='XX' et countryName=null.",
                    "countryCode doit être ISO 3166-1 alpha-2 en MAJUSCULE.",
                    "strict_json" => true,
                ],
            ];
            $retryData = $this->call($system, $retryUser);

            if (is_array($retryData) && isset($retryData['output']) && is_array($retryData['output'])) {
                $retryData = $retryData['output'];
            }

            if (is_array($retryData)) {
                $rcc = (string)($retryData['countryCode'] ?? '');
                if (preg_match('/^[A-Z]{2}$/', $rcc)) {
                    $data['artist']['countryCode'] = $rcc;
                    $rcn = $retryData['countryName'] ?? null;
                    $data['artist']['countryName'] = $rcc === 'XX' ? null : (is_string(
                        $rcn
                    ) && $rcn !== '' ? $rcn : null);
                    $this->aiLogger->info('ai.retry_country_inference.ok', ['artist' => $artistDisplay, 'cc' => $rcc]);
                } else {
                    $this->aiLogger->info(
                        'ai.retry_country_inference.ignored',
                        ['artist' => $artistDisplay, 'payload' => $retryData]
                    );
                }
            } else {
                $this->aiLogger->info(
                    'ai.retry_country_inference.no_parse',
                    ['artist' => $artistDisplay, 'payload' => $retryData]
                );
            }
        }

        $this->aiLogger->info('ai.response', ['data' => $data]);

        return $data;
    }

    private function call(string $system, array $userPayload): mixed
    {
        $res = $this->openaiClient->request('POST', '/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => json_encode($userPayload, JSON_UNESCAPED_UNICODE)],
                ],
            ],
        ]);

        $json = $res->toArray(false);
        $content = $json['choices'][0]['message']['content'] ?? '{}';

        $data = json_decode($content, true);

        return (json_last_error() === JSON_ERROR_NONE) ? $data : $content;
    }

    private function fallbackFromInput(array $input): array
    {
        $displayArtist = $this->titleCase($input['artistCanonical'] ?? '');
        $displayTitle = $this->titleCase($input['recordCanonical'] ?? '');

        return [
            'artist' => [
                'displayName' => $displayArtist,
                'canonicalName' => mb_strtolower(trim($displayArtist), 'UTF-8'),
                'countryCode' => 'XX',
                'countryName' => null,
            ],
            'record' => [
                'displayTitle' => $displayTitle,
                'canonicalTitle' => mb_strtolower(trim($displayTitle), 'UTF-8'),
                'yearOriginal' => '0000',
                'format' => 'Inconnu',
            ],
        ];
    }

    private function titleCase(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
        if ($s === '') {
            return '';
        }
        $parts = preg_split('/\s/u', mb_strtolower($s, 'UTF-8')) ?: [];
        $out = [];
        foreach ($parts as $w) {
            $out[] = mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($w, 1, null, 'UTF-8');
        }

        return implode(' ', $out);
    }

    private function sanitizeGuessedFormat(string $raw, string $recordDisplay): string
    {
        $r = mb_strtolower(trim($raw), 'UTF-8');

        if ($r === '33' || $r === '33t' || str_contains($r, 'lp')) {
            return '33T';
        }
        if ($r === '45' || $r === '45t' || str_contains($r, '7"') || str_contains($r, '7 ’') || str_contains(
                $r,
                '7 ”'
            )) {
            return '45T';
        }
        if (str_contains($r, '12"') || str_contains($r, '12 ’') || str_contains($r, '12 ”') || str_contains(
                $r,
                'maxi'
            )) {
            return 'Maxi45T';
        }
        if (str_contains($r, '78')) {
            return '78T';
        }
        if (str_contains($r, 'mix')) {
            return 'Mixte';
        }

        $t = mb_strtolower($recordDisplay, 'UTF-8');
        if (str_contains($t, '12"')) {
            return 'Maxi45T';
        }
        if (str_contains($t, '7"') || str_contains($t, ' single')) {
            return '45T';
        }
        if (str_contains($t, 'lp')) {
            return '33T';
        }

        return 'Inconnu';
    }
}
