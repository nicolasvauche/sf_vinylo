<?php

namespace App\Dto\Vault\AddRecord;

final class ResolvedDraftDto
{
    public function __construct(
        public string $artistName,
        public string $artistNameCanonical,
        public string $artistCountryCode,
        public ?string $artistCountryName,
        public ?string $discogsArtistId,
        public string $recordTitle,
        public string $recordTitleCanonical,
        public string $yearOriginal,
        public ?string $discogsMasterId,
        public ?string $discogsReleaseId,
        /** @var array<int,array{url:string,width?:int,height?:int,source?:string}> */
        public array $covers,
        public int $coverDefaultIndex
    ) {
    }
}
