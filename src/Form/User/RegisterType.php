<?php

namespace App\Form\User;

use App\Entity\User\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegisterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pseudo', TextType::class, [
                'required' => true,
                'label' => 'Votre pseudo',
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
            ->add('email', EmailType::class, [
                'required' => true,
                'label' => 'Votre adresse e-mail',
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
            ->add('password', PasswordType::class, [
                'required' => true,
                'label' => 'Votre mot de passe',
                'help' => 'Minimum 6 caractères',
                'label_attr' => [
                    'class' => 'form-label',
                    'autocomplete' => 'new-password',
                ],
                'attr' => [
                    'class' => 'form-control',
                    'autocomplete' => 'off',
                    'minlength' => 6,
                    'minMessage' => 'Votre mot de passe doit contenir au moins 6 caractères',
                ],
                'help_attr' => [
                    'class' => 'help',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
