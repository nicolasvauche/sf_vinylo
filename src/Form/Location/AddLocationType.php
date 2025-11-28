<?php

namespace App\Form\Location;

use App\Dto\Location\AddLocationDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AddLocationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('label', TextType::class, [
            'required' => true,
            'label' => 'Label',
            'label_attr' => ['class' => 'form-label'],
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'Label',
            ],
            'help_attr' => ['class' => 'help'],
        ]);

        $builder->add('addressInput', TextType::class, [
            'required' => true,
            'label' => 'Adresse',
            'label_attr' => ['class' => 'form-label'],
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'Adresse',
                'autocomplete' => 'off',
                'aria-autocomplete' => 'list',
                'aria-expanded' => 'false',
                'aria-controls' => 'geo-suggestions',
                'spellcheck' => 'false',
                'inputmode' => 'search',
                'data-location--autocomplete-target' => 'input',
                'data-action' => 'input->location--autocomplete#onInput keydown->location--autocomplete#onKeydown',
            ],
            'help_attr' => ['class' => 'help'],
        ]);

        $hidden = static fn(array $extra = []) => array_merge([
            'required' => false,
            'label' => false,
        ], $extra);

        $builder
            ->add(
                'placeId',
                HiddenType::class,
                $hidden([
                    'attr' => ['data-location--autocomplete-target' => 'placeId'],
                ])
            )
            ->add(
                'displayName',
                HiddenType::class,
                $hidden([
                    'attr' => ['data-location--autocomplete-target' => 'displayName'],
                ])
            )
            ->add(
                'locality',
                HiddenType::class,
                $hidden([
                    'attr' => ['data-location--autocomplete-target' => 'locality'],
                ])
            )
            ->add(
                'countryCode',
                HiddenType::class,
                $hidden([
                    'attr' => ['data-location--autocomplete-target' => 'countryCode'],
                ])
            )
            ->add(
                'lat',
                HiddenType::class,
                $hidden([
                    'attr' => ['data-location--autocomplete-target' => 'lat'],
                ])
            )
            ->add(
                'lng',
                HiddenType::class,
                $hidden([
                    'attr' => ['data-location--autocomplete-target' => 'lng'],
                ])
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AddLocationDto::class,
            'csrf_protection' => true,
            'error_mapping' => [
                'placeId' => 'addressInput',
                'displayName' => 'addressInput',
                'locality' => 'addressInput',
                'countryCode' => 'addressInput',
                'lat' => 'addressInput',
                'lng' => 'addressInput',
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'add_location';
    }
}
