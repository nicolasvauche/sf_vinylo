<?php

namespace App\Tests\Dto\Location;

use App\Dto\Location\AddLocationDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(AddLocationDto::class)]
final class AddLocationDtoTest extends TestCase
{
    private function createValidator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testInitialStateIsInvalidWithAllRequiredErrors(): void
    {
        $validator = $this->createValidator();
        $dto = new AddLocationDto();

        $violations = $validator->validate($dto);

        $this->assertGreaterThanOrEqual(8, count($violations), 'On attend au moins 8 erreurs NotBlank.');

        $paths = [];
        foreach ($violations as $v) {
            $paths[] = $v->getPropertyPath();
        }

        foreach (
            [
                'label',
                'addressInput',
                'placeId',
                'displayName',
                'locality',
                'countryCode',
                'lat',
                'lng',
            ] as $field
        ) {
            $this->assertContains($field, $paths, "Violation manquante pour le champ {$field}");
        }
    }

    public function testValidDtoPassesValidation(): void
    {
        $validator = $this->createValidator();
        $dto = new AddLocationDto();

        $dto->label = 'Maison';
        $dto->addressInput = '12 rue des Forges, Gouzon';
        $dto->placeId = '1234567890';
        $dto->displayName = '12 Rue des Forges, 23230 Gouzon, France';
        $dto->locality = 'Gouzon';
        $dto->countryCode = 'fr';
        $dto->lat = '46.224500';
        $dto->lng = '2.194300';

        $violations = $validator->validate($dto);

        $this->assertCount(0, $violations, (string)$violations);
    }

    public function testCountryCodeMustBeTwoLetters(): void
    {
        $validator = $this->createValidator();
        $dto = new AddLocationDto();

        $dto->label = 'Travail';
        $dto->addressInput = 'Paris, France';
        $dto->placeId = 'abcdef';
        $dto->displayName = 'Boulevard Saint Michel, Paris, France';
        $dto->locality = 'Paris';
        $dto->countryCode = 'fra';
        $dto->lat = '48.846000';
        $dto->lng = '2.343000';

        $violations = $validator->validate($dto);

        $this->assertGreaterThan(0, count($violations), 'Aucune violation alors que countryCode est invalide.');
        $found = false;
        foreach ($violations as $v) {
            if ($v->getPropertyPath() === 'countryCode') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Aucune violation sur countryCode alors qu’il est trop long.');
    }

    public function testLatAndLngMustBeNumeric(): void
    {
        $validator = $this->createValidator();
        $dto = new AddLocationDto();

        $dto->label = 'Sophie';
        $dto->addressInput = 'Montluçon';
        $dto->placeId = 'plc_42';
        $dto->displayName = 'Montluçon, Allier, France';
        $dto->locality = 'Montluçon';
        $dto->countryCode = 'fr';
        $dto->lat = 'not-a-number';
        $dto->lng = 'oops';

        $violations = $validator->validate($dto);

        $this->assertGreaterThanOrEqual(2, count($violations));

        $latTypeErr = false;
        $lngTypeErr = false;

        foreach ($violations as $v) {
            if ($v->getPropertyPath() === 'lat') {
                $latTypeErr = true;
            }
            if ($v->getPropertyPath() === 'lng') {
                $lngTypeErr = true;
            }
        }

        $this->assertTrue($latTypeErr, 'Pas de violation sur lat alors que la valeur n’est pas numérique.');
        $this->assertTrue($lngTypeErr, 'Pas de violation sur lng alors que la valeur n’est pas numérique.');
    }
}

