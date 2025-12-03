<?php

namespace App\ValueObject\Vault;

enum RecordFormat: string
{
    case F33 = '33T';
    case F45 = '45T';
    case F45_MAXI = 'Maxi45T';
    case F78 = '78T';
    case MIXED = 'Mixte';
    case UNKNOWN = 'Inconnu';

    public static function fromDiscogs(?string $speed, ?string $size): self
    {
        return match ($speed) {
            '33_1_3' => self::F33,
            '78' => self::F78,
            'mixed' => self::MIXED,
            '45' => ($size === '12') ? self::F45_MAXI : self::F45,
            default => self::UNKNOWN,
        };
    }
}
