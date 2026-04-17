<?php

namespace App\Form\Formation;

use App\Entity\Formation\Formation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre de la formation',
                'attr' => ['placeholder' => 'Ex: Symfony avancé']
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description'
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Technique' => 'Technique',
                    'Soft Skills' => 'Soft Skills',
                    'Management' => 'Management',
                    'Leadership' => 'Leadership',
                    'Langues' => 'Langues',
                    'Qualité' => 'Qualité',
                    'Sécurité' => 'Sécurité',
                    'Conformité' => 'Conformité',
                    'Bureautique' => 'Bureautique',
                    'Finance' => 'Finance',
                    'RH' => 'RH',
                    'Digital' => 'Digital',
                    'Autre' => 'Autre',
                ],
                'label' => 'Type de formation'
            ])
            ->add('duree', IntegerType::class, [
                'label' => 'Durée (en jours)',
                'attr' => ['min' => 1]
            ])
            ->add('organisme', TextType::class, [
                'required' => false,
                'label' => 'Organisme / Formateur'
            ])
            ->add('objectifs', TextareaType::class, [
                'required' => false,
                'label' => 'Objectifs (Générables via l\'IA)',
                'attr' => ['id' => 'formation_objectifs_field']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
        ]);
    }
}

