<?php

namespace App\ValueObject\Vault\AddRecord;

enum DraftStatus: string
{
    case PENDING = 'PENDING';
    case READY = 'READY';
    case CANCELLED = 'CANCELLED';
    case DONE = 'DONE';

    public function isTerminal(): bool
    {
        return $this === self::CANCELLED || $this === self::DONE;
    }
}
