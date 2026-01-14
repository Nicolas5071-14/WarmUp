<?php

namespace MauticPlugin\MauticWarmUpBundle\Form\Type;

use MauticPlugin\MauticWarmUpBundle\Entity\Domain;
use MauticPlugin\MauticWarmUpBundle\Entity\Template;
use MauticPlugin\MauticWarmUpBundle\Entity\WarmupType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use MauticPlugin\MauticWarmUpBundle\Entity\Campaign;

class SimpleCampaignFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Informations de base
            ->add('campaignName', TextType::class, [
                'label' => 'Campaign Name *',
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
                    'label' => 'Sending Domain *',
                    'class' => Domain::class,
                    'choice_label' => 'domainName',
                    'required' => true,
                    'attr' => ['class' => 'form-control'],
                    'constraints' => [new NotBlank()]
                ])

            ->add('warmupType', EntityType::class, [
                    'label' => 'Warmup Type *',
                    'class' => WarmupType::class,
                    'choice_label' => 'typeName',
                    'required' => true,
                    'attr' => ['class' => 'form-control'],
                    'constraints' => [new NotBlank()]
                ])

            // Planning
            ->add('startDate', DateTimeType::class, [
                'label' => 'Start Date *',
                'widget' => 'single_text',
                'required' => true,
                'attr' => ['class' => 'form-control'],
                'constraints' => [new NotBlank()]
            ])

            ->add('sendTime', TimeType::class, [
                    'label' => 'Send Time (Toronto Time) *',
                    'widget' => 'single_text',
                    'required' => true,
                    'attr' => ['class' => 'form-control'],
                    'constraints' => [new NotBlank()],
                    'data' => new \DateTime('09:00')
                ])

            ->add('endDate', DateTimeType::class, [
                    'label' => 'End Date',
                    'widget' => 'single_text',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control end-date-field',
                        'style' => 'display: none;'
                    ],
                    'html5' => false,
                    'format' => 'yyyy-MM-dd\'T\'HH:mm',
                    'input' => 'datetime'
                ])

            ->add('sendFrequency', ChoiceType::class, [
                    'label' => 'Frequency',
                    'choices' => [
                        'Daily' => 'daily',
                        'Weekdays Only' => 'weekdays',
                        'Weekly (Monday)' => 'weekly'
                    ],
                    'required' => true,
                    'attr' => ['class' => 'form-control'],
                    'data' => 'daily'
                ])

            // Paramètres de warmup
            ->add('startVolume', IntegerType::class, [
                'label' => 'Start Volume (emails/day) *',
                'required' => true,
                'attr' => ['class' => 'form-control', 'min' => 1],
                'constraints' => [new NotBlank()],
                'data' => 20
            ])

            ->add('durationDays', IntegerType::class, [
                    'label' => 'Duration (days) *',
                    'required' => true,
                    'attr' => ['class' => 'form-control', 'min' => 1, 'max' => 180],
                    'constraints' => [new NotBlank()],
                    'data' => 30
                ])

            ->add('dailyIncrement', IntegerType::class, [
                    'label' => 'Daily Increment (%)',
                    'required' => false,
                    'attr' => ['class' => 'form-control', 'min' => 1, 'max' => 100],
                    'data' => 10
                ])

            // Total Contacts (champ caché géré par JS)
            ->add('totalContacts', HiddenType::class, [
                'required' => false,
                'data' => 0
            ])

            // Contact Source (champ caché)
            ->add('contactSource', HiddenType::class, [
                'required' => false,
                'data' => 'manual'
            ])

            // Champs contacts NON mappés
            ->add('manualContacts', TextareaType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 10,
                    'placeholder' => "email1@example.com\nemail2@example.com\nemail3@example.com"
                ]
            ])

            ->add('segmentId', HiddenType::class, [
                    'label' => false,
                    'required' => false,
                    'mapped' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Enter segment ID'
                    ]
                ])

            ->add('csvFile', FileType::class, [
                    'label' => false,
                    'required' => false,
                    'mapped' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'accept' => '.csv'
                    ],
                    'constraints' => [
                        new File([
                            'maxSize' => '1M',
                            'mimeTypes' => [
                                'text/csv',
                                'text/plain',
                                'application/csv',
                                'application/vnd.ms-excel'
                            ],
                            'mimeTypesMessage' => 'Please upload a valid CSV file'
                        ])
                    ]
                ])

            // Email Content
            ->add('subjectTemplate', TextType::class, [
                'label' => 'Subject',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter email subject',
                ],
                'constraints' => [
                    new Length(['max' => 255]),
                ],
            ])

            ->add('customMessage', TextareaType::class, [
                    'label' => 'Email Body',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'rows' => 12,
                        'placeholder' => 'Enter your email content here...',
                    ],
                    'constraints' => [
                        new Length(['max' => 10000]),
                    ],
                ])

            // Email Sequences
            ->add('sequenceType', HiddenType::class, [
                'required' => false,
                'data' => 'single'
            ])

            ->add('emailSequences', TextareaType::class, [
                    'label' => false,
                    'required' => false,
                    'data' => '[]',
                    'attr' => [
                        'style' => 'display: none;',
                        'rows' => 1
                    ]
                ])

            // Options
            ->add('enableWeekends', CheckboxType::class, [
                'label' => 'Send on Weekends',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'data' => true
            ])

            ->add('enableRandomization', CheckboxType::class, [
                    'label' => 'Enable Randomization',
                    'required' => false,
                    'attr' => ['class' => 'form-check-input'],
                    'data' => true
                ])
        ;

        // Ajouter le transformer pour endDate
        $builder->get('endDate')
            ->addModelTransformer(new CallbackTransformer(
                    // Transforme DateTime → string pour l'affichage
                    function ($date) {
                        if ($date instanceof \DateTimeInterface) {
                            return $date->format('Y-m-d\TH:i');
                        }
                        return $date;
                    },
                    // Transforme string → DateTime pour l'entité
                    function ($dateString) {
                        if (empty($dateString)) {
                            return null;
                        }

                        try {
                            // Supporte plusieurs formats
                            if (strpos($dateString, 'T') !== false) {
                                // Format ISO 8601: "2024-01-15T09:00"
                                return new \DateTime($dateString);
                            } else {
                                // Format MySQL: "2024-01-15 09:00:00"
                                $date = \DateTime::createFromFormat('Y-m-d H:i:s', $dateString);
                                if (!$date) {
                                    // Essayer d'autres formats
                                    $date = new \DateTime($dateString);
                                }
                                return $date;
                            }
                        } catch (\Exception $e) {
                            // Si le parsing échoue, retourner null
                            error_log('Error parsing endDate: ' . $e->getMessage());
                            return null;
                        }
                    }
                ));

        // Ajoutez le DataTransformer APRÈS tous les add()
        $builder->get('emailSequences')
            ->addModelTransformer(new CallbackTransformer(
                    // Transforme array → string pour l'affichage
                    function ($array) {
                        if ($array === null || !is_array($array)) {
                            return '[]';
                        }
                        return json_encode($array, JSON_PRETTY_PRINT);
                    },
                    // Transforme string → array pour l'entité
                    function ($string) {
                        if (empty($string) || $string === '[]') {
                            return [];
                        }
                        try {
                            return json_decode($string, true);
                        } catch (\Exception $e) {
                            return [];
                        }
                    }
                ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Campaign::class,
            'is_edit' => false,
            'allow_extra_fields' => true,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'campaign_form',

            // Validation conditionnelle
            'validation_groups' => function ($form) {
                $data = $form->getData();
                if ($data instanceof Campaign && $data->getSequenceType() === 'multiple') {
                    return ['multiple_sequence'];
                }
                return ['Default'];
            },
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'campaign_form';
    }
}