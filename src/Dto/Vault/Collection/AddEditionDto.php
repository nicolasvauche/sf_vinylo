<?php

namespace App\Dto\Vault\Collection;

use Symfony\Component\Validator\Constraints as Assert;

final class AddEditionDto
{
    #[Assert\NotBlank]
    public ?string $artistName = null;

    #[Assert\NotBlank]
    public ?string $recordTitle = null;
}
