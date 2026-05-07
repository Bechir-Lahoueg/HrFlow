<?php

namespace App\Form\Recrutement;

use App\Entity\Recrutement\Application;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, mixed>> */
class BulkApplicationActionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('selected', EntityType::class, [
                'class' => Application::class,
                'choices' => $options['applications'],
                'choice_label' => function (Application $application): string {
                    $jobTitle = $application->getJobOffer()?->getTitle() ?? 'N/A';
                    return sprintf('%s - %s', $application->getCandidateName() ?? 'Unknown', $jobTitle);
                },
                'choice_value' => function (?Application $application): ?int {
                    return $application?->getId();
                },
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => false,
            ])
            ->add('action', ChoiceType::class, [
                'choices' => [
                    'Passer en revue' => 'review',
                    'Planifier entretien' => 'interview',
                    'Envoyer offre' => 'offer',
                    'Recruter' => 'hire',
                    'Rejeter' => 'reject',
                ],
                'placeholder' => 'Sélectionner une action...',
                'required' => true,
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'applications' => [],
            'data_class' => null,
        ]);

        $resolver->setAllowedTypes('applications', 'array');
    }
}