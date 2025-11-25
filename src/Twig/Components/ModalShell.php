<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('ModalShell')]
final class ModalShell
{
    public string $id = 'app-modal';
    public string $size = 'm';           // 's' | 'm'
    public bool $dismissible = true;
    public string $variant = 'confirm';  // 'confirm' | 'picker'
    public ?string $title = null;
    public ?string $description = null;
}
