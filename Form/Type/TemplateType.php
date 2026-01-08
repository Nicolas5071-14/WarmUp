<?php

namespace MauticPlugin\MauticWarmUpBundle\Form\Type;

use MauticPlugin\MauticWarmUpBundle\Entity\Template;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class TemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('templateName', TextType::class, [
                'label' => 'mautic.warmup.template.name',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Template name',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.template.name.required']),
                ],
            ])
            ->add('templateType', ChoiceType::class, [
                'label' => 'mautic.warmup.template.type',
                'choices' => [
                    'Email' => 'email',
                    'Notification' => 'notification',
                    'Alert' => 'alert',
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.template.type.required']),
                ],
            ])
            ->add('subject', TextType::class, [
                'label' => 'mautic.warmup.template.subject',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Email subject',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.template.subject.required']),
                ],
            ])
            ->add('htmlContent', TextareaType::class, [
                'label' => 'mautic.warmup.template.html_content',
                'required' => true,
                'attr' => [
                    'class' => 'form-control editor',
                    'rows' => 15,
                    'placeholder' => 'HTML content...',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.template.html_content.required']),
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'mautic.warmup.template.text_content',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 8,
                    'placeholder' => 'Plain text content (optional)...',
                ],
                'help' => 'mautic.warmup.template.text_content.help',
            ])
            ->add('isActive', ChoiceType::class, [
                'label' => 'mautic.warmup.template.is_active',
                'choices' => [
                    'Yes' => true,
                    'No' => false,
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.template.is_active.required']),
                ],
            ]);

        // Add buttons
        $builder->add('buttons', 'form_buttons');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Template::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'warmup_template';
    }
}
