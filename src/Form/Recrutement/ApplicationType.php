<?php

namespace App\Form\Recrutement;

use App\Entity\Recrutement\Application;
use App\Entity\Rh\Employee;
use App\Entity\Recrutement\JobOffer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;

/** @extends AbstractType<array<string, mixed>> */
class ApplicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('candidateName', TextType::class, [
                'label' => 'Nom du candidat',
                'constraints' => [
                    new NotBlank(['message' => 'Le nom du candidat est requis']),
                ],
                'attr' => [
                    'placeholder' => 'Nom complet du candidat',
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-800 dark:text-zinc-200 placeholder-slate-400 focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-900/30 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('jobOffer', EntityType::class, [
                'label' => 'Offre d\'emploi',
                'class' => JobOffer::class,
                'choice_label' => 'title',
                'placeholder' => 'Sélectionner une offre',
                'constraints' => [
                    new NotBlank(['message' => 'L\'offre d\'emploi est requise']),
                ],
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('cvFile', FileType::class, [
                'label' => 'CV / Resume',
                'mapped' => false,
                'required' => !$isEdit,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger un PDF ou Word valide',
                    ])
                ],
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-teal-300 transition-colors file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
                'help' => $isEdit ? 'Laissez vide pour conserver le CV actuel' : 'Téléchargez un PDF ou Word (max 5MB)',
                'help_attr' => [
                    'class' => 'text-[11px] text-slate-400 mt-1',
                ],
            ])
            ->add('coverLetterFile', FileType::class, [
                'label' => 'Lettre de motivation',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger un PDF ou Word valide',
                    ])
                ],
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-teal-300 transition-colors file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
                'help' => $isEdit ? 'Laissez vide pour conserver la lettre actuelle' : 'Téléchargez un PDF ou Word (max 5MB)',
                'help_attr' => [
                    'class' => 'text-[11px] text-slate-400 mt-1',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Nouveau' => 'PENDING',
                    'En revue' => 'REVIEWING',
                    'Entretien' => 'INTERVIEW',
                    'Offre' => 'OFFER',
                    'Recruté' => 'HIRED',
                    'Rejeté' => 'REJECTED',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le statut est requis']),
                ],
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('emailAddress', EmailType::class, [
                'label' => 'Email du candidat',
                'required' => false,
                'constraints' => [
                    new EmailConstraint(['message' => 'Veuillez entrer une adresse email valide']),
                ],
                'attr' => [
                    'placeholder' => 'candidat@example.com',
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 placeholder-slate-400 focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('department', ChoiceType::class, [
                'label' => 'Département',
                'required' => false,
                'placeholder' => '-- Sélectionner un département --',
                'choices' => [
                    'Ressources Humaines' => 'RH',
                    'Informatique' => 'IT',
                    'Finance' => 'Finance',
                    'Commercial' => 'Commercial',
                    'Marketing' => 'Marketing',
                    'Opérations' => 'Opérations',
                    'Support Client' => 'Support',
                    'Autre' => 'Autre',
                ],
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('experienceLevel', ChoiceType::class, [
                'label' => 'Niveau d\'expérience',
                'required' => false,
                'placeholder' => '-- Sélectionner un niveau --',
                'choices' => [
                    'Débutant' => 'ENTRY',
                    'Junior' => 'JUNIOR',
                    'Confirmé' => 'MID',
                    'Senior' => 'SENIOR',
                    'Lead' => 'LEAD',
                    'Cadre' => 'EXECUTIVE',
                ],
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes supplémentaires',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Entrez vos observations ou notes',
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 placeholder-slate-400 focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100 transition-colors resize-none',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('source', ChoiceType::class, [
                'label' => 'Source de la candidature',
                'required' => false,
                'placeholder' => '-- Sélectionner une source --',
                'choices' => [
                    'LinkedIn' => 'LinkedIn',
                    'Indeed' => 'Indeed',
                    'Recommandation' => 'Referral',
                    'Site web' => 'Website',
                    'Email' => 'Email',
                    'Autre' => 'Other',
                ],
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('employee', EntityType::class, [
                'label' => 'Employé associé',
                'class' => Employee::class,
                'required' => false,
                'placeholder' => '-- Sélectionner un employé interne --',
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ]);

        if (!$isEdit) {
            $builder->add('appliedAt', DateTimeType::class, [
                'label' => 'Date de candidature',
                'widget' => 'single_text',
                'data' => new \DateTime(),
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Application::class,
            'is_edit' => false,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
