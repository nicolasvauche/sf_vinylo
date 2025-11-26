<?php

namespace App\Twig\Components\Ui;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('ModalShell', template: '_components/modal_shell.html.twig')]
final class ModalShell
{
    public string $id = 'app-modal';
    public string $size = 'm';           // 's' | 'm'
    public bool $dismissible = true;
    public string $variant = 'confirm';  // 'confirm' | 'picker | logout'
    public ?string $title = null;
    public ?string $description = null;
}
