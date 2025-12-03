<?php

namespace App\Validator\Vault\AddRecord;

final class ResolvedDraftValidator
{
    /** @return list<string> */
    public function validate(array $resolved): array
    {
        $e = [];

        $a = $resolved['artist'] ?? null;
        $r = $resolved['record'] ?? null;

        if (!is_array($a) || !is_array($r)) {
            $e[] = 'structure.invalid';

            return $e;
        }

        foreach (['name', 'nameCanonical', 'countryCode'] as $k) {
            if (!isset($a[$k]) || !is_string($a[$k]) || $a[$k] === '') {
                $e[] = "artist.$k.invalid";
            }
        }
        if (($a['countryCode'] ?? '') === 'XX' && !empty($a['countryName'] ?? null)) {
            $e[] = 'artist.countryName.mustBeNullWhenXX';
        }

        foreach (['title', 'titleCanonical', 'yearOriginal'] as $k) {
            if (!isset($r[$k]) || !is_string($r[$k]) || $r[$k] === '') {
                $e[] = "record.$k.invalid";
            }
        }
        if (isset($r['yearOriginal']) && !preg_match('/^\d{4}$|^0000$/', $r['yearOriginal'])) {
            $e[] = 'record.yearOriginal.invalid';
        }

        $covers = $resolved['covers'] ?? [];
        if (!is_array($covers)) {
            $e[] = 'covers.invalid';
        } elseif (count($covers) > 10) {
            $e[] = 'covers.tooMany';
        }

        if (!empty($covers)) {
            $idx = $resolved['coverDefaultIndex'] ?? null;
            if (!is_int($idx) || $idx < 0 || $idx >= count($covers)) {
                $e[] = 'coverDefaultIndex.invalid';
            }
        }

        return $e;
    }
}
