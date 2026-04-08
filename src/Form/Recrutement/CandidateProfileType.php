<?php

namespace App\Form\Recrutement;

use App\Entity\Recrutement\Candidate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CandidateProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prenom',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Jean',
                    'class' => 'w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-sm text-slate-900 placeholder-slate-400 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-bold text-slate-700 mb-2',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Dupont',
                    'class' => 'w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-sm text-slate-900 placeholder-slate-400 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-bold text-slate-700 mb-2',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'placeholder' => 'jean.dupont@email.com',
                    'class' => 'w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-sm text-slate-900 placeholder-slate-400 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-bold text-slate-700 mb-2',
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Telephone',
                'required' => false,
                'attr' => [
                    'placeholder' => '+216 12 345 678',
                    'class' => 'w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-sm text-slate-900 placeholder-slate-400 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-bold text-slate-700 mb-2',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Candidate::class,
        ]);
    }
}
