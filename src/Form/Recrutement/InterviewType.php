<?php

namespace App\Form\Recrutement;

use App\Entity\Recrutement\Application;
use App\Entity\Recrutement\Interview;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class InterviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('application', EntityType::class, [
                'label' => 'Candidature',
                'class' => Application::class,
                'query_builder' => function (\App\Repository\Recrutement\ApplicationRepository $er) use ($options) {
                    $qb = $er->createQueryBuilder('a')
                        ->join('a.jobOffer', 'jo')
                        ->where('jo.createdBy = :rhId')
                        ->andWhere('a.isDeleted = false')
                        ->setParameter('rhId', $options['rh_id'])
                        ->orderBy('a.candidateName', 'ASC');
                    
                    // If editing, also include the current application
                    if ($options['current_application_id']) {
                        $qb->orWhere('a.id = :currentAppId')
                           ->setParameter('currentAppId', $options['current_application_id']);
                    }
                    
                    return $qb;
                },
                'choice_label' => function (Application $application) {
                    return $application->getCandidateName() . ' - ' . ($application->getJobOffer()?->getTitle() ?? '—');
                },
                'placeholder' => '-- Sélectionner un candidat --',
                'constraints' => [
                    new NotBlank(['message' => 'La candidature est requise']),
                ],
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('interviewerId', ChoiceType::class, [
                'label' => 'Interviewer',
                'choices' => $options['interviewer_choices'] ?? [],
                'placeholder' => '-- Sélectionner --',
                'constraints' => [
                    new NotBlank(['message' => 'L\'interviewer est requis']),
                ],
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('interviewDate', DateTimeType::class, [
                'label' => 'Date et heure',
                'widget' => 'single_text',
                'html5' => true,
                'constraints' => [
                    new NotBlank(['message' => 'La date et heure sont requises']),
                ],
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type d\'entretien',
                'choices' => [
                    'Téléphonique' => 'PHONE',
                    'Vidéo' => 'VIDEO',
                    'En présentiel' => 'ONSITE',
                    'Technique' => 'TECHNICAL',
                    'RH' => 'HR',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le type d\'entretien est requis']),
                ],
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('meetingLink', UrlType::class, [
                'label' => 'Lien de réunion (optionnel)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://meet.google.com/...',
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 placeholder-slate-400 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('location', TextType::class, [
                'label' => 'Lieu (optionnel)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Salle de réunion A',
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 placeholder-slate-400 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('feedback', TextareaType::class, [
                'label' => 'Feedback',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Entrez le feedback de l\'entretien',
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 placeholder-slate-400 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors resize-none',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('score', IntegerType::class, [
                'label' => 'Score /10',
                'required' => false,
                'constraints' => [
                    new Range([
                        'min' => 0,
                        'max' => 10,
                        'notInRangeMessage' => 'Le score doit être compris entre 0 et 10',
                    ]),
                ],
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
                'help' => 'Score minimum 6/10 pour réussir',
                'help_attr' => [
                    'class' => 'text-[11px] text-slate-400 mt-1',
                ],
            ])
            ->add('result', ChoiceType::class, [
                'label' => 'Résultat',
                'choices' => [
                    'En attente' => 'PENDING',
                    'Réussi' => 'PASSED',
                    'Échoué' => 'FAILED',
                    'No-show' => 'NO_SHOW',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le résultat est requis']),
                ],
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Interview::class,
            'is_edit' => false,
            'interviewer_choices' => [],
            'rh_id' => null,
            'current_application_id' => null,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
        $resolver->setAllowedTypes('interviewer_choices', 'array');
        $resolver->setAllowedTypes('rh_id', ['int', 'null']);
        $resolver->setAllowedTypes('current_application_id', ['int', 'null']);
    }
}

