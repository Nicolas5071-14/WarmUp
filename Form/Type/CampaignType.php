<?php

namespace MauticPlugin\MauticWarmUpBundle\Form\Type;

use MauticPlugin\MauticWarmUpBundle\Entity\Campaign;
use MauticPlugin\MauticWarmUpBundle\Entity\Domain;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CampaignType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('campaignName', TextType::class, [
                'label' => 'mautic.warmup.campaign.name',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter campaign name',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.campaign.name.required']),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'mautic.warmup.campaign.description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                ],
            ])
            ->add('domain', EntityType::class, [
                'label' => 'mautic.warmup.campaign.domain',
                'class' => Domain::class,
                'choices' => $options['domains'],
                'choice_label' => 'domainName',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.campaign.domain.required']),
                ],
            ])
            ->add('warmupType', EntityType::class, [
                'label' => 'mautic.warmup.campaign.warmup_type',
                'class' => 'MauticPlugin\MauticWarmUpBundle\Entity\WarmUpType',
                'choice_label' => 'typeName',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.campaign.warmup_type.required']),
                ],
            ])
            ->add('startDate', DateTimeType::class, [
                'label' => 'mautic.warmup.campaign.start_date',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'data-toggle' => 'datetime',
                ],
                'html5' => false,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'mautic.warmup.campaign.status',
                'choices' => [
                    'Draft' => Campaign::STATUS_DRAFT,
                    'Active' => Campaign::STATUS_ACTIVE,
                    'Paused' => Campaign::STATUS_PAUSED,
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'mautic.warmup.campaign.status.required']),
                ],
            ])
            ->add('sequences', CollectionType::class, [
                'label' => 'mautic.warmup.campaign.sequences',
                'entry_type' => SequenceType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'by_reference' => false,
                'required' => false,
                'attr' => [
                    'class' => 'sequence-collection',
                ],
            ]);

        // Add buttons
        $builder->add('buttons', 'form_buttons');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Campaign::class,
            'domains' => [],
        ]);
        
        $resolver->setRequired(['domains']);
    }

    public function getBlockPrefix(): string
    {
        return 'warmup_campaign';
    }
}
