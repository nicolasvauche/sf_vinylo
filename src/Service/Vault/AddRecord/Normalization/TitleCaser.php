<?php

namespace App\Service\Vault\AddRecord\Normalization;

final class TitleCaser
{
    public function titleCase(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
        $lower = [
            'a',
            'an',
            'and',
            'the',
            'of',
            'for',
            'to',
            'in',
            'on',
            'or',
            'et',
            'de',
            'du',
            'des',
            'la',
            'le',
            'les',
            'au',
            'aux',
            'd\'',
            'l\'',
        ];
        $parts = preg_split('/\s/u', $s) ?: [];
        $out = [];
        foreach ($parts as $i => $w) {
            $lw = mb_strtolower($w, 'UTF-8');
            if ($i > 0 && in_array($lw, $lower, true)) {
                $out[] = $lw;
            } else {
                $out[] = mb_strtoupper(mb_substr($lw, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($lw, 1, null, 'UTF-8');
            }
        }

        return implode(' ', $out);
    }
}
