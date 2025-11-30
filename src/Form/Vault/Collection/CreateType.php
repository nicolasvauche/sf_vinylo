<?php

namespace App\Form\Vault\Collection;

use App\Dto\Vault\Collection\EditEditionDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class CreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('artistName', TextType::class, [
                'required' => true,
                'label' => 'Artiste',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'attr' => [
                    'class' => 'form-control',
                    'autofocus' => true,
                ],
                'help_attr' => [
                    'class' => 'help',
                ],
            ])
            ->add('artistCountryName', TextType::class, [
                'required' => true,
                'label' => 'Pays',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
                'help_attr' => [
                    'class' => 'help',
                ],
            ])
            ->add('recordTitle', TextType::class, [
                'required' => true,
                'label' => 'Titre',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
                'help_attr' => [
                    'class' => 'help',
                ],
            ])
            ->add('recordYear', TextType::class, [
                'required' => true,
                'label' => 'Année',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
                'help_attr' => [
                    'class' => 'help',
                ],
            ])
            ->add('recordCoverChoice', TextType::class, [
                'required' => false,
                'label' => 'Cover choisie',
                'label_attr' => ['class' => 'form-label'],
                'attr' => [
                    'class' => 'form-control',
                    'hidden' => true,
                ],
                'help_attr' => ['class' => 'help'],
            ])
            ->add('recordCoverUpload', FileType::class, [
                'required' => false,
                'mapped' => false,
                'label' => 'Importer une photo',
                'label_attr' => ['class' => 'form-label'],
                'attr' => [
                    'class' => 'form-control',
                    'hidden' => true,
                    'accept' => 'image/*',
                ],
                'help_attr' => ['class' => 'help'],
                'constraints' => [
                    new File(
                        maxSize: '2048k',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Il faut une image JPG, PNG ou WebP',
                    ),
                ],
            ])
            ->add('recordCoverCamera', FileType::class, [
                'required' => false,
                'mapped' => false,
                'label' => 'Prendre une photo (mobile)',
                'label_attr' => ['class' => 'form-label'],
                'attr' => [
                    'class' => 'form-control',
                    'hidden' => true,
                    'accept' => 'image/*',
                    'capture' => 'environment',
                ],
                'help_attr' => ['class' => 'help'],
                'constraints' => [
                    new File(
                        maxSize: '2048k',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Il faut une image JPG, PNG ou WebP',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EditEditionDto::class,
        ]);
    }
}
