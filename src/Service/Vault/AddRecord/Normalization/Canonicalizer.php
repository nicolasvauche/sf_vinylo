<?php

namespace App\Service\Vault\AddRecord\Normalization;

final class Canonicalizer
{
    public function canonicalize(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = \Normalizer::isNormalized($s) ? $s : \Normalizer::normalize($s, \Normalizer::FORM_D);
        $s = preg_replace('/\p{Mn}+/u', '', $s) ?? $s;
        $s = mb_strtolower($s, 'UTF-8');

        return $s;
    }
}
