<?php

namespace App\Form\Shared;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** @extends AbstractType<array<string, mixed>> */
class AccountSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez renseigner votre email.']),
                    new Email(['message' => 'Veuillez saisir un email valide.']),
                    new Length(['max' => 255, 'maxMessage' => 'L\'email ne peut pas depasser 255 caracteres.']),
                ],
                'attr' => [
                    'placeholder' => 'vous@entreprise.com',
                    'class' => 'w-full px-4 py-3 rounded-xl border border-zinc-200 bg-white text-sm text-zinc-900 placeholder-zinc-400 outline-none transition-all focus:ring-2',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-bold text-zinc-700 mb-2',
                ],
            ])
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir votre mot de passe actuel.']),
                ],
                'attr' => [
                    'placeholder' => '••••••••',
                    'autocomplete' => 'current-password',
                    'class' => 'w-full px-4 py-3 rounded-xl border border-zinc-200 bg-white text-sm text-zinc-900 placeholder-zinc-400 outline-none transition-all focus:ring-2',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-bold text-zinc-700 mb-2',
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'invalid_message' => 'Les deux mots de passe doivent etre identiques.',
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'attr' => [
                        'placeholder' => 'Laisser vide pour garder le mot de passe actuel',
                        'autocomplete' => 'new-password',
                        'class' => 'w-full px-4 py-3 rounded-xl border border-zinc-200 bg-white text-sm text-zinc-900 placeholder-zinc-400 outline-none transition-all focus:ring-2',
                    ],
                    'label_attr' => [
                        'class' => 'block text-sm font-bold text-zinc-700 mb-2',
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le nouveau mot de passe',
                    'attr' => [
                        'placeholder' => 'Confirmez le nouveau mot de passe',
                        'autocomplete' => 'new-password',
                        'class' => 'w-full px-4 py-3 rounded-xl border border-zinc-200 bg-white text-sm text-zinc-900 placeholder-zinc-400 outline-none transition-all focus:ring-2',
                    ],
                    'label_attr' => [
                        'class' => 'block text-sm font-bold text-zinc-700 mb-2',
                    ],
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
