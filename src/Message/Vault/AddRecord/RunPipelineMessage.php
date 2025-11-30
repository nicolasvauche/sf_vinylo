<?php

namespace App\Message\Vault\AddRecord;

final readonly class RunPipelineMessage
{
    public function __construct(
        public int $draftId
    ) {
    }
}
