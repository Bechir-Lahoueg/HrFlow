<?php

namespace App\Form\Paie;

use App\Entity\Paie\Prime;
use App\Entity\Rh\Employee;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PrimeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('employee', EntityType::class, [
                'label' => 'Employé',
                'class' => Employee::class,
                'query_builder' => function (\App\Repository\Rh\EmployeeRepository $er) use ($options) {
                    $qb = $er->createQueryBuilder('e')
                        ->where('e.rhId = :rhId')
                        ->setParameter('rhId', $options['rh_id'])
                        ->orderBy('e.firstName', 'ASC')
                        ->addOrderBy('e.lastName', 'ASC');
                    
                    if ($options['current_employee_id']) {
                        $qb->orWhere('e.id = :currentEmpId')
                           ->setParameter('currentEmpId', $options['current_employee_id']);
                    }
                    
                    return $qb;
                },
                'choice_label' => function (Employee $employee) {
                    return $employee->getFullName() . ' (' . $employee->getJobTitle() . ')';
                },
                'placeholder' => '-- Sélectionner un employé --',
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('typePrime', TextType::class, [
                'label' => 'Type de Prime',
                'attr' => [
                    'placeholder' => 'Ex: Prime de performance, Prime d\'assiduité...',
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 placeholder-slate-400 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('montant', MoneyType::class, [
                'label' => 'Montant',
                'currency' => 'TND',
                'attr' => [
                    'step' => '0.01',
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('dateAttribution', DateType::class, [
                'label' => 'Date d\'attribution',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('motif', TextareaType::class, [
                'label' => 'Motif',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Motif de la prime...',
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 placeholder-slate-400 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Prime::class,
            'rh_id' => null,
            'current_employee_id' => null,
        ]);
    }
}
