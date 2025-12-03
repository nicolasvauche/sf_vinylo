<?php

namespace App\Dto\Vault\AddRecord;

final class DuplicateProbeDto
{
    public function __construct(
        public int $ownerId,
        public string $artistCanonical,
        public string $recordCanonical,
        public string $yearOriginal
    ) {
    }
}
