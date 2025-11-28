<?php

namespace App\Dto\Location;

use Symfony\Component\Validator\Constraints as Assert;

final class AddLocationDto
{
    #[Assert\NotBlank(message: 'Veuillez saisir un label')]
    #[Assert\Length(max: 255)]
    public ?string $label = null;

    #[Assert\NotBlank(message: 'Veuillez saisir une adresse')]
    #[Assert\Length(max: 255)]
    public ?string $addressInput = null;

    #[Assert\NotBlank(message: 'Aucune suggestion sélectionnée')]
    public ?string $placeId = null;

    #[Assert\NotBlank(message: 'Adresse invalide')]
    public ?string $displayName = null;

    #[Assert\NotBlank(message: 'La ville est introuvable')]
    #[Assert\Length(max: 255)]
    public ?string $locality = null;

    #[Assert\NotBlank(message: 'Le pays est obligatoire')]
    #[Assert\Length(min: 2, max: 2)]
    public ?string $countryCode = null;

    #[Assert\NotBlank(message: 'Coordonnées lat manquantes')]
    #[Assert\Type(type: 'numeric', message: 'Latitude invalide')]
    public ?string $lat = null;

    #[Assert\NotBlank(message: 'Coordonnées lng manquantes')]
    #[Assert\Type(type: 'numeric', message: 'Longitude invalide')]
    public ?string $lng = null;
}

