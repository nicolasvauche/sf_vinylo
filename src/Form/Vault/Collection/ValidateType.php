<?php

namespace App\Form\Vault\Collection;

use App\Dto\Vault\Collection\ValidateEditionDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class ValidateType extends AbstractType
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
            ->add('artistCountryCode', CountryType::class, [
                'required' => true,
                'label' => 'Pays',
                'placeholder' => '— Sélectionner —',
                'preferred_choices' => ['FR', 'US', 'GB', 'DE', 'CA'],
                'choice_translation_locale' => 'fr',
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
            ->add('recordFormat', ChoiceType::class, [
                'required' => true,
                'label' => 'Format',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'choices' => [
                    '33T' => '33T',
                    '45T' => '45T',
                    'Maxi-45T' => 'Maxi45T',
                    '78T' => '78T',
                    'Mixte' => 'Mixte',
                    'Inconnu' => 'Inconnu',
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

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var ValidateEditionDto|null $dto */
            $dto = $event->getData();
            if (!$dto) {
                return;
            }
            $code = $dto->artistCountryCode ?? null;
            if ($code && $code !== 'XX') {
                $dto->artistCountryName = Countries::exists($code)
                    ? Countries::getName($code, 'fr')
                    : $dto->artistCountryName;
            } else {
                $dto->artistCountryName = null;
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            /** @var ValidateEditionDto $dto */
            $dto = $event->getData();
            $code = $dto->artistCountryCode ?? null;

            if ($code && $code !== 'XX') {
                $dto->artistCountryName = Countries::exists($code)
                    ? Countries::getName($code, 'fr')
                    : null;
            } else {
                $dto->artistCountryName = null;
            }

            $event->setData($dto);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ValidateEditionDto::class,
        ]);
    }
}
