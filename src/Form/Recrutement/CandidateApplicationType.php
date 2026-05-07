<?php

namespace App\Form\Recrutement;

use App\Entity\Recrutement\Application;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** @extends AbstractType<array<string, mixed>> */
class CandidateApplicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cvFile', FileType::class, [
                'label' => 'CV (PDF ou Word) *',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez telecharger votre CV']),
                    new File([
                        'maxSize' => '50M',
                    ]),
                    new Callback([$this, 'validateFileExtension']),
                ],
                'attr' => [
                    'class' => 'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-colors file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100',
                    'accept' => '.pdf,.doc,.docx',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-bold text-slate-700 mb-2',
                ],
            ])
            ->add('coverLetterFile', FileType::class, [
                'label' => 'Lettre de motivation (optionnel)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '50M',
                    ]),
                    new Callback([$this, 'validateFileExtension']),
                ],
                'attr' => [
                    'class' => 'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-colors file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100',
                    'accept' => '.pdf,.doc,.docx',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-bold text-slate-700 mb-2',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Message complementaire (optionnel)',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Parlez-nous de votre motivation, de vos experiences pertinentes...',
                    'class' => 'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 placeholder-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-colors resize-none',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-bold text-slate-700 mb-2',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Application::class,
        ]);
    }

    /**
     * Custom validator for file extension (works without fileinfo extension)
     */
    public function validateFileExtension(mixed $value, ExecutionContextInterface $context): void
    {
        if (!$value instanceof UploadedFile || !$value->isValid()) {
            return;
        }

        $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'rtf'];
        $extension = strtolower($value->getClientOriginalExtension());

        if (!in_array($extension, $allowedExtensions)) {
            $context->buildViolation('Extension de fichier non valide. Formats acceptes: PDF, DOC, DOCX, TXT, RTF.')
                ->addViolation();
        }
    }
}
