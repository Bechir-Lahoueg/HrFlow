<?php

namespace App\Form;

use App\Entity\Applicaiton;
use App\Entity\Employee;
use App\Entity\JobOffer;
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

class ApplicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('candidateName', TextType::class, [
                'label' => 'Nom du candidat',
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
                'label' => 'Cover Letter',
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
                        'mimeTypesMessage' => 'Please upload a valid PDF or Word document',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => $isEdit ? 'Leave empty to keep current cover letter' : 'Upload PDF or Word document (max 5MB)',
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
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Enter any additional notes',
                    'class' => 'form-control',
                ],
            ])
            ->add('department', TextType::class, [
                'label' => 'Department',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter department',
                    'class' => 'form-control',
                ],
            ])
            ->add('experienceLevel', ChoiceType::class, [
                'label' => 'Experience Level',
                'required' => false,
                'choices' => [
                    'Entry Level' => 'ENTRY',
                    'Junior' => 'JUNIOR',
                    'Mid-Level' => 'MID',
                    'Senior' => 'SENIOR',
                    'Lead' => 'LEAD',
                    'Executive' => 'EXECUTIVE',
                ],
                'placeholder' => 'Select experience level',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('emailAddress', EmailType::class, [
                'label' => 'Email Address',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter email address',
                    'class' => 'form-control',
                ],
            ])
            ->add('employee', EntityType::class, [
                'label' => 'Associated Employee',
                'class' => Employee::class,
                'required' => false,
                'placeholder' => 'Select employee (if internal)',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('source', TextType::class, [
                'label' => 'Source',
                'required' => false,
                'attr' => [
                    'placeholder' => 'e.g., LinkedIn, Indeed, Referral',
                    'class' => 'form-control',
                ],
            ]);

        if (!$isEdit) {
            $builder->add('appliedAt', DateTimeType::class, [
                'label' => 'Applied At',
                'widget' => 'single_text',
                'data' => new \DateTime(),
                'attr' => [
                    'class' => 'form-control',
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Applicaiton::class,
            'is_edit' => false,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
