<?php

namespace App\Form\Recrutement;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CandidateLoginFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('identifier', TextType::class, [
                'label' => 'Email ou Username',
                'attr' => [
                    'placeholder' => 'nom@email.com ou jdupont',
                    'class' => 'w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-sm text-slate-900 placeholder-slate-400 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-bold text-slate-700 mb-2',
                ],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe',
                'attr' => [
                    'placeholder' => '********',
                    'class' => 'w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-sm text-slate-900 placeholder-slate-400 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-bold text-slate-700 mb-2',
                ],
            ])
            ->add('_csrf_token', HiddenType::class, [
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id' => 'authenticate',
            'attr' => [
                'class' => 'space-y-5',
                'data-turbo' => 'false',
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
