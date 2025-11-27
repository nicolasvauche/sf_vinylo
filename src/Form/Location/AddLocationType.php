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
            'label_attr' => [
                'class' => 'form-label',
            ],
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'Label',
            ],
            'help_attr' => [
                'class' => 'help',
            ],
        ]);

        $builder->add('addressInput', TextType::class, [
            'required' => true,
            'label' => 'Adresse',
            'label_attr' => [
                'class' => 'form-label',
            ],
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'Adresse',
            ],
            'help_attr' => [
                'class' => 'help',
            ],
        ]);

        $hidden = static fn(array $extra = []) => array_merge([
            'required' => false,
            'label' => false,
        ], $extra);

        $builder
            ->add('placeId', HiddenType::class, $hidden())
            ->add('displayName', HiddenType::class, $hidden())
            ->add('locality', HiddenType::class, $hidden())
            ->add('countryCode', HiddenType::class, $hidden())
            ->add('lat', HiddenType::class, $hidden())
            ->add('lng', HiddenType::class, $hidden());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AddLocationDto::class,
            'csrf_protection' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'add_location';
    }
}

