<?php

namespace App\Form\Shared;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DeleteConfirmationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('action', HiddenType::class, [
                'data' => $options['action_value'],
            ])
            ->add('confirm', SubmitType::class, [
                'label' => $options['submit_label'],
                'attr' => [
                    'class' => $options['button_class'],
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'action_value' => 'delete',
            'submit_label' => 'Confirmer',
            'button_class' => 'inline-flex w-full justify-center rounded-lg bg-rose-600 hover:bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:shadow-md sm:w-auto',
        ]);

        $resolver->setAllowedTypes('action_value', 'string');
        $resolver->setAllowedTypes('submit_label', 'string');
        $resolver->setAllowedTypes('button_class', 'string');
    }
}
