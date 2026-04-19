<?php

namespace App\Form\Formation;

use App\Entity\Formation\SessionFormation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SessionFormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateDebut', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de début',
            ])
            ->add('dateFin', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Date de fin (calculée si vide)',
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu (ou lien)',
                'required' => false,
                'attr' => ['placeholder' => 'Salle A ou Lien Zoom']
            ])
            ->add('mode', ChoiceType::class, [
                'choices' => [
                    'En ligne' => 'En ligne',
                    'Présentiel' => 'Présentiel',
                    'Hybride' => 'Hybride',
                ],
                'label' => 'Mode de formation'
            ])
            ->add('capaciteMax', IntegerType::class, [
                'label' => 'Capacité maximale',
                'attr' => ['min' => 1]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SessionFormation::class,
        ]);
    }
}

