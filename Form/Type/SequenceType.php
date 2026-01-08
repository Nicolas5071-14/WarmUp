<?php

namespace MauticPlugin\MauticWarmUpBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class SequenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'mautic.warmup.sequence.name',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Sequence name',
                ],
            ])
            ->add('order', IntegerType::class, [
                'label' => 'mautic.warmup.sequence.order',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.sequence.order.required']),
                    new Range(['min' => 1]),
                ],
            ])
            ->add('days_after', IntegerType::class, [
                'label' => 'mautic.warmup.sequence.days_after',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'max' => 30,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.sequence.days_after.required']),
                    new Range(['min' => 1, 'max' => 30]),
                ],
            ])
            ->add('subject', TextType::class, [
                'label' => 'mautic.warmup.sequence.subject',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Email subject',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.sequence.subject.required']),
                ],
            ])
            ->add('body', TextareaType::class, [
                'label' => 'mautic.warmup.sequence.body',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 8,
                    'placeholder' => 'Email body content...',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.sequence.body.required']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'warmup_sequence';
    }
}
