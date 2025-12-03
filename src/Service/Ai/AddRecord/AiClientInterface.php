<?php

namespace App\Service\Ai\AddRecord;

interface AiClientInterface
{
    public function enrich(array $input, array $discogsCandidates): array;
}
