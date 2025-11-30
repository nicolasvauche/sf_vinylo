<?php

namespace App\Service\Discogs\AddRecord;

interface DiscogsClientInterface
{
    public function search(string $artistCanonical, string $recordCanonical): DiscogsSearchResult;
}
