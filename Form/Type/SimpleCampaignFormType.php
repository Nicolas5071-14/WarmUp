<?php

namespace MauticPlugin\MauticWarmUpBundle\Form\Type;

use MauticPlugin\MauticWarmUpBundle\Entity\Domain;
use MauticPlugin\MauticWarmUpBundle\Entity\WarmupType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class SimpleCampaignFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Informations de base
            ->add('campaignName', TextType::class, [
                'label' => 'Nom de la campagne *',
                'required' => true,
                'attr' => ['class' => 'form-control'],
                'constraints' => [new NotBlank()]
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])

            ->add('domain', EntityType::class, [
                'label' => 'Domaine d\'envoi *',
                'class' => Domain::class,
                'choice_label' => 'domainName',
                'required' => true,
                'attr' => ['class' => 'form-control'],
                'constraints' => [new NotBlank()]
            ])

            ->add('warmupType', EntityType::class, [
                'label' => 'Type de warmup *',
                'class' => WarmupType::class,
                'choice_label' => 'typeName',
                'required' => true,
                'attr' => ['class' => 'form-control'],
                'constraints' => [new NotBlank()]
            ])

            // Planning
            ->add('startDate', DateType::class, [
                'label' => 'Date de début *',
                'widget' => 'single_text',
                'required' => true,
                'attr' => ['class' => 'form-control'],
                'constraints' => [new NotBlank()]
            ])

            ->add('sendTime', TimeType::class, [
                'label' => 'Heure d\'envoi (Heure de Toronto) *',
                'widget' => 'single_text',
                'required' => true,
                'attr' => ['class' => 'form-control'],
                'constraints' => [new NotBlank()]
            ])

            ->add('sendFrequency', ChoiceType::class, [
                'label' => 'Fréquence',
                'choices' => [
                    'Quotidien' => 'daily',
                    'Lun-Ven seulement' => 'weekdays',
                    'Hebdomadaire (Lundi)' => 'weekly'
                ],
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])

            // Paramètres de warmup
            ->add('startVolume', IntegerType::class, [
                'label' => 'Volume initial (emails/jour) *',
                'required' => true,
                'attr' => ['class' => 'form-control', 'min' => 1],
                'constraints' => [new NotBlank()]
            ])

            ->add('durationDays', IntegerType::class, [
                'label' => 'Durée (jours) *',
                'required' => true,
                'attr' => ['class' => 'form-control', 'min' => 1, 'max' => 180],
                'constraints' => [new NotBlank()]
            ])

            ->add('dailyIncrement', IntegerType::class, [
                'label' => 'Incrément quotidien (%)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'min' => 1, 'max' => 100]
            ])

            // Contacts
            ->add('contactSource', ChoiceType::class, [
                'label' => 'Source des contacts',
                'choices' => [
                    'Saisie manuelle' => 'manual',
                    'Segment Mautic' => 'mautic'
                ],
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])

            ->add('manualContacts', TextareaType::class, [
                'label' => 'Liste d\'emails (un par ligne)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'exemple1@domain.com\nexemple2@domain.com'
                ]
            ])

            ->add('segmentId', IntegerType::class, [
                'label' => 'ID du segment Mautic',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])

            // Contenu
            ->add('subjectTemplate', TextType::class, [
                'label' => 'Sujet de l\'email *',
                'required' => true,
                'attr' => ['class' => 'form-control'],
                'constraints' => [new NotBlank()]
            ])

            ->add('customMessage', TextareaType::class, [
                'label' => 'Contenu de l\'email *',
                'required' => true,
                'attr' => ['class' => 'form-control', 'rows' => 8],
                'constraints' => [new NotBlank()]
            ])

            // Options
            ->add('enableWeekends', CheckboxType::class, [
                'label' => 'Envoyer les week-ends',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])

            ->add('enableRandomization', CheckboxType::class, [
                'label' => 'Activer la randomisation',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])

            // Boutons
            ->add('save', SubmitType::class, [
                'label' => $options['is_edit'] ? 'Mettre à jour' : 'Créer',
                'attr' => ['class' => 'btn btn-primary']
            ])
            ->add('saveAndActivate', SubmitType::class, [
                'label' => $options['is_edit'] ? 'Mettre à jour et activer' : 'Créer et activer',
                'attr' => ['class' => 'btn btn-success']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => 'MauticPlugin\MauticWarmUpBundle\Entity\Campaign',
            'is_edit' => false,
        ]);
    }
}