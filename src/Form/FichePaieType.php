<?php

namespace App\Form;

use App\Entity\FichePaie;
use App\Entity\Employee;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FichePaieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('employee', EntityType::class, [
                'label' => 'Employé',
                'class' => Employee::class,
                'query_builder' => function (\App\Repository\EmployeeRepository $er) use ($options) {
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
            ->add('mois', ChoiceType::class, [
                'label' => 'Mois',
                'choices' => [
                    'Janvier' => 1, 'Février' => 2, 'Mars' => 3, 'Avril' => 4,
                    'Mai' => 5, 'Juin' => 6, 'Juillet' => 7, 'Août' => 8,
                    'Septembre' => 9, 'Octobre' => 10, 'Novembre' => 11, 'Décembre' => 12,
                ],
                'attr' => [
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('annee', NumberType::class, [
                'label' => 'Année',
                'attr' => [
                    'min' => 2000,
                    'max' => 2100,
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('salaireBrut', MoneyType::class, [
                'label' => 'Salaire Brut',
                'currency' => 'TND',
                'attr' => [
                    'step' => '0.01',
                    'class' => 'w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-slate-700 dark:text-zinc-300 focus:outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-colors',
                ],
                'label_attr' => [
                    'class' => 'block text-[12px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Remarques',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Ajouter des remarques...',
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
            'data_class' => FichePaie::class,
            'rh_id' => null,
            'current_employee_id' => null,
        ]);
    }
}
