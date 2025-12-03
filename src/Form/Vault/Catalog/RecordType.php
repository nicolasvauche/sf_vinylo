<?php

namespace App\Form\Vault\Catalog;

use App\Entity\Vault\Catalog\Record;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('artist', ArtistType::class, [
                'label' => false,
            ])
            ->add('title', TextType::class, [
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
            ->add('yearOriginal', TextType::class, [
                'required' => true,
                'label' => 'Année',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'attr' => [
                    'class' => 'form-control',
                    'maxlength' => 4,
                ],
                'help_attr' => [
                    'class' => 'help',
                ],
            ])
            ->add('coverFile', FileType::class, [
                'required' => false,
                'mapped' => false,
                'label' => 'Couverture',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
                'help_attr' => [
                    'class' => 'help',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Record::class,
        ]);
    }
}
