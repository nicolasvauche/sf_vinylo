<?php

namespace App\Dto\Vault\Collection;

use Symfony\Component\Validator\Constraints as Assert;

final class EditEditionDto
{
    #[Assert\NotBlank]
    public ?string $artistName = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 2)]
    #[Assert\Regex(pattern: '/^[A-Z]{2}$/')]
    public ?string $artistCountryCode = null;

    #[Assert\Type('string')]
    #[Assert\Length(max: 100)]
    public ?string $artistCountryName = null;

    #[Assert\NotBlank]
    public ?string $recordTitle = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^(0000|\d{4})$/')]
    public ?string $recordYear = null;

    public ?string $discogsMasterId = null;
    public ?string $discogsReleaseId = null;

    #[Assert\Type('string')]
    public ?string $recordCoverChoice = null;

    #[Assert\Count(max: 10)]
    public array $covers = [];

    #[Assert\Type('integer')]
    #[Assert\GreaterThanOrEqual(0)]
    public int $coverDefaultIndex = 0;
}
