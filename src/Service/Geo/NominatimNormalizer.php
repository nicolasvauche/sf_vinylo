<?php

namespace App\Service\Geo;

final class NominatimNormalizer
{
    public function normalize(array $items): array
    {
        $out = [];

        foreach ($items as $item) {
            $placeId = (string)($item['place_id'] ?? '');
            $displayName = (string)($item['display_name'] ?? '');
            $lat = $this->roundCoord($item['lat'] ?? null);
            $lng = $this->roundCoord($item['lon'] ?? null);

            $address = $item['address'] ?? [];
            $countryCode = isset($address['country_code']) ? strtolower((string)$address['country_code']) : '';
            $addresstype = $item['addresstype'] ?? null;
            $allowedTypes = ['city', 'town', 'village', 'hamlet'];
            if (!in_array($addresstype, $allowedTypes, true)) {
                continue;
            }

            $locality = $this->firstNonNull(
                $address['city'] ?? null,
                $address['town'] ?? null,
                $address['village'] ?? null,
                $address['hamlet'] ?? null
            );

            if ($placeId === '' || $displayName === '' || $lat === '' || $lng === '' || $countryCode === '' || $locality === '') {
                continue;
            }

            $out[] = [
                'placeId' => $placeId,
                'displayName' => $displayName,
                'locality' => $locality,
                'countryCode' => $countryCode,
                'lat' => $lat,
                'lng' => $lng,
                'label' => sprintf('%s, %s', $locality, strtoupper($countryCode)),
            ];
        }

        return $out;
    }

    private function roundCoord(null|string|float|int $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }

        return number_format((float)$v, 6, '.', '');
    }

    private function firstNonNull(mixed ...$vals): string
    {
        foreach ($vals as $v) {
            if ($v !== null && $v !== '') {
                return (string)$v;
            }
        }

        return '';
    }
}
