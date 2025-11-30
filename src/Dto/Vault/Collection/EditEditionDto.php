<?php

namespace App\Dto\Vault\Collection;

use Symfony\Component\Validator\Constraints as Assert;

final class EditEditionDto
{
    #[Assert\NotBlank]
    public ?string $artistName = null;

    #[Assert\NotBlank]
    public ?string $artistCountryName = null;

    #[Assert\NotBlank]
    public ?string $recordTitle = null;

    #[Assert\NotBlank]
    public ?string $recordYear = null;

    public ?string $recordCoverChoice = null;
}
