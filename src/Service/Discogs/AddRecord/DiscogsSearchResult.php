<?php

namespace App\Service\Discogs\AddRecord;

final class DiscogsSearchResult
{
    public function __construct(
        public array $candidates,
        public ?array $chosen
    ) {
    }
}
