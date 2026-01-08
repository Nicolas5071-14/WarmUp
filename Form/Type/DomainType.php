<?php

namespace MauticPlugin\MauticWarmUpBundle\Form\Type;

use MauticPlugin\MauticWarmUpBundle\Entity\Domain;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class DomainType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('domainName', TextType::class, [
                'label' => 'mautic.warmup.domain.name',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'example.com',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.domain.name.required']),
                ],
            ])
            ->add('emailPrefix', TextType::class, [
                'label' => 'mautic.warmup.domain.email_prefix',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'noreply',
                ],
                'help' => 'mautic.warmup.domain.email_prefix.help',
            ])
            ->add('dailyLimit', IntegerType::class, [
                'label' => 'mautic.warmup.domain.daily_limit',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'max' => 10000,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.domain.daily_limit.required']),
                    new Range(['min' => 1, 'max' => 10000]),
                ],
            ])
            ->add('smtpHost', TextType::class, [
                'label' => 'mautic.warmup.domain.smtp_host',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'smtp.example.com',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.domain.smtp_host.required']),
                ],
            ])
            ->add('smtpPort', IntegerType::class, [
                'label' => 'mautic.warmup.domain.smtp_port',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'max' => 65535,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.domain.smtp_port.required']),
                    new Range(['min' => 1, 'max' => 65535]),
                ],
            ])
            ->add('smtpUsername', TextType::class, [
                'label' => 'mautic.warmup.domain.smtp_username',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'user@example.com',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.domain.smtp_username.required']),
                ],
            ])
            ->add('smtpPassword', PasswordType::class, [
                'label' => 'mautic.warmup.domain.smtp_password',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'autocomplete' => 'new-password',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.domain.smtp_password.required']),
                ],
            ])
            ->add('smtpEncryption', ChoiceType::class, [
                'label' => 'mautic.warmup.domain.smtp_encryption',
                'choices' => [
                    'None' => '',
                    'SSL' => 'ssl',
                    'TLS' => 'tls',
                ],
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('warmupPhase', IntegerType::class, [
                'label' => 'mautic.warmup.domain.warmup_phase',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'max' => 30,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.domain.warmup_phase.required']),
                    new Range(['min' => 1, 'max' => 30]),
                ],
            ])
            ->add('isActive', ChoiceType::class, [
                'label' => 'mautic.warmup.domain.is_active',
                'choices' => [
                    'Yes' => true,
                    'No' => false,
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.domain.is_active.required']),
                ],
            ]);

        // Add save button directly in the form
        $builder->add('save', SubmitType::class, [
            'label' => 'Save',
            'attr' => ['class' => 'btn btn-primary'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Domain::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'warmup_domain';
    }
}