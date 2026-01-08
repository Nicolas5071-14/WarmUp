<?php

return [
    'name' => 'Email WarmUp',
    'description' => 'Email warm-up and deliverability optimization for Mautic',
    'version' => '1.0.0',
    'author' => 'RAMAHALEFITRA Abelson Nicolas',
    'menu' => [
        'main' => [
            'warmup' => [
                'id' => 'warmup_menu',
                'iconClass' => 'ri-fire-fill',
                'priority' => 80,
                'children' => [
                    'warmup.domains' => [
                        'route' => 'warmup_domain_index',
                        'access' => 'plugin:warmup:domains:view'
                    ],
                    'warmup.campaigns' => [
                        'route' => 'warmup_campaign_index',
                        'access' => 'plugin:warmup:campaigns:view'
                    ],
                    'warmup.campaigns.new' => [
                        'route' => 'warmup_campaign_new',
                        'access' => 'plugin:warmup:campaigns:create'
                    ],
                    'warmup.templates' => [
                        'route' => 'warmup_template_index',
                        'access' => 'plugin:warmup:templates:view'
                    ],
                    'warmup.contacts' => [
                        'route' => 'warmup_contact_index',
                        'access' => 'plugin:warmup:contacts:view'
                    ],
                    'warmup.reports' => [
                        'route' => 'warmup_report_index',
                        'access' => 'plugin:warmup:reports:view'
                    ]
                ]
            ]
        ]
    ],
    'routes' => [
        'main' => [

            // ============================================
            // ROUTES CAMPAIGN
            // ============================================

            // Page index
            'warmup_campaign_index' => [
                'path' => '/warmup/campaigns',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\CampaignController::indexAction'
            ],

            // Liste AJAX pour DataTables
            'warmup_campaign_ajax_list' => [
                'path' => '/warmup/campaigns/ajax/list',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\CampaignController::ajaxListCampaignAction',
                'method' => 'GET'
            ],

            // Nouveau / Édition
            'warmup_campaign_new' => [
                'path' => '/warmup/campaigns/new',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\CampaignController::newAction'
            ],
            'warmup_campaign_edit' => [
                'path' => '/warmup/campaigns/edit/{id}',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\CampaignController::editAction'
            ],

            // Sauvegarde
            'warmup_campaign_save' => [
                'path' => '/warmup/campaigns/save',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\CampaignController::saveAction',
                'method' => 'POST'
            ],

            // Actions de contrôle
            'warmup_campaign_start' => [
                'path' => '/warmup/campaigns/{id}/start',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\CampaignController::startAction',
                'method' => 'POST'
            ],
            'warmup_campaign_pause' => [
                'path' => '/warmup/campaigns/{id}/pause',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\CampaignController::pauseAction',
                'method' => 'POST'
            ],
            'warmup_campaign_resume' => [
                'path' => '/warmup/campaigns/{id}/resume',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\CampaignController::resumeAction',
                'method' => 'POST'
            ],

            // Infos et statistiques
            'warmup_campaign_progress' => [
                'path' => '/warmup/campaigns/{id}/progress',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\CampaignController::progressAction',
                'method' => 'GET'
            ],
            'warmup_campaign_contacts' => [
                'path' => '/warmup/campaigns/{id}/contacts',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\CampaignController::contactsAction',
                'method' => 'GET'
            ],

            // Actions AJAX pour formulaire
            'warmup_campaign_calculate_warmup' => [
                'path' => '/warmup/campaigns/calculate-warmup',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\CampaignController::calculateWarmupAction',
                'method' => 'POST'
            ],
            'warmup_campaign_preview_contacts' => [
                'path' => '/warmup/campaigns/preview-contacts',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\CampaignController::previewContactsAction',
                'method' => 'POST'
            ],
            'warmup_campaign_send_test_email' => [
                'path' => '/warmup/campaigns/send-test-email',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\CampaignController::sendTestEmailAction',
                'method' => 'POST'
            ],
            'warmup_campaign_upload_csv' => [
                'path' => '/warmup/campaigns/upload-csv',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\CampaignController::uploadCsvAction',
                'method' => 'POST'
            ],




            // ROUTES EXISTANTES
            'warmup_domain_index' => [
                'path' => '/warmup/domains',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\DomainController::indexAction'
            ],
            'warmup_domain_list_ajax' => [
                'path' => '/warmup/domains/ajax',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\DomainController::ajaxListAction',
                'method' => 'GET',
            ],

            'warmup_domain_new' => [
                'path' => '/warmup/domains/new',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\DomainController::newAction'
            ],
            'warmup_domain_edit' => [
                'path' => '/warmup/domains/edit/{id}',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\DomainController::editAction'
            ],
            'warmup_domain_delete' => [
                'path' => '/warmup/domains/delete/{id}',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\DomainController::deleteAction'
            ],
            'warmup_domain_verify' => [
                'path' => '/warmup/domains/verify/{id}',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\DomainController::verifyAction',
                'method' => 'POST'
            ],
            'warmup_domain_toggle' => [
                'path' => '/warmup/domains/toggle/{id}',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\DomainController::toggleActiveAction',
                'method' => 'POST'
            ],
            'warmup_domain_stats' => [
                'path' => '/warmup/domains/stats/{id}',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\DomainController::statsAction'
            ],



            'warmup_template_index' => [
                'path' => '/warmup/templates',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\TemplateController::indexAction'
            ],
            'warmup_template_new' => [
                'path' => '/warmup/templates/new',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\TemplateController::newAction'
            ],
            'warmup_template_edit' => [
                'path' => '/warmup/templates/edit/{id}',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\TemplateController::editAction'
            ],
            'warmup_template_preview' => [
                'path' => '/warmup/templates/preview/{id}',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\TemplateController::previewAction'
            ],
            'warmup_template_duplicate' => [
                'path' => '/warmup/templates/duplicate/{id}',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\TemplateController::duplicateAction',
                'method' => 'POST'
            ],
            'warmup_template_toggle' => [
                'path' => '/warmup/templates/toggle/{id}',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\TemplateController::toggleActiveAction',
                'method' => 'POST'
            ],

            'warmup_contact_index' => [
                'path' => '/warmup/contacts',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\ContactController::indexAction'
            ],
            'warmup_contact_view' => [
                'path' => '/warmup/contacts/view/{id}',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\ContactController::viewAction'
            ],
            'warmup_contact_unsubscribe' => [
                'path' => '/warmup/unsubscribe/{token}',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\ContactController::unsubscribeAction'
            ],
            'warmup_contact_export' => [
                'path' => '/warmup/contacts/export',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\ContactController::exportAction'
            ],
            'warmup_contact_batch' => [
                'path' => '/warmup/contacts/batch',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\ContactController::batchAction',
                'method' => 'POST'
            ],

            'warmup_report_index' => [
                'path' => '/warmup/reports',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\ReportController::indexAction'
            ],
            'warmup_report_dashboard' => [
                'path' => '/warmup/reports/dashboard',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\ReportController::dashboardAction'
            ],
            'warmup_report_performance' => [
                'path' => '/warmup/reports/performance',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\ReportController::performanceAction'
            ],
            'warmup_report_deliverability' => [
                'path' => '/warmup/reports/deliverability',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\ReportController::deliverabilityAction'
            ],

            'warmup_api_process' => [
                'path' => '/warmup/api/process',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\ApiController::processAction',
                'method' => 'POST'
            ],
            'warmup_api_send_test' => [
                'path' => '/warmup/api/send-test',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\ApiController::sendTestAction',
                'method' => 'POST'
            ],
            'warmup_api_status' => [
                'path' => '/warmup/api/status',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\ApiController::statusAction'
            ],
            'warmup_api_health' => [
                'path' => '/warmup/api/health',
                'controller' => 'MauticPlugin\MauticWarmUpBundle\Controller\ApiController::healthAction'
            ]
        ]
    ],
    'services' => [
        'events' => [
            'mautic_warmup.event_listener.campaign_subscriber' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\EventListener\CampaignSubscriber::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager'
                ],
                'tags' => ['kernel.event_subscriber']
            ],
            'mautic_warmup.event_listener.email_send_subscriber' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\EventListener\EmailSendSubscriber::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager'
                ],
                'tags' => ['kernel.event_subscriber']
            ]
        ],
        'other' => [

            'mautic_warmup.service.email_sender' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\Service\EmailSenderService::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager',
                    '@mailer',
                    '@monolog.logger.mautic'
                ]
            ],
            'mautic_warmup.helper.warmup' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\Helper\WarmUpHelper::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager',
                    '@mailer',
                    '@monolog.logger.mautic'
                ]
            ],
            'mautic_warmup.model.domain' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\Model\DomainModel::class,
                'arguments' => ['@doctrine.orm.entity_manager']
            ],
            'mautic_warmup.model.campaign' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\Model\CampaignModel::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager',
                    '@request_stack'
                ]
            ],
            'mautic_warmup.model.template' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\Model\TemplateModel::class,
                'arguments' => ['@doctrine.orm.entity_manager']
            ],
            'mautic_warmup.model.contact' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\Model\ContactModel::class,
                'arguments' => ['@doctrine.orm.entity_manager']
            ],
            // NOUVEAU SERVICE: Warmup Progression Calculator
            'mautic_warmup.model.warmup_calculator' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\Model\WarmupProgressionCalculator::class,
                'arguments' => ['@monolog.logger.mautic']
            ],
        ],
        'controller' => [
            'mautic_warmup.controller.domain' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\Controller\DomainController::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager',
                    '@mautic_warmup.model.domain',
                    '@form.factory',
                    '@router',
                    '@request_stack',
                    '@twig'
                ]
            ],




            'mautic_warmup.controller.campaign' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\Controller\CampaignController::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager',
                    '@mautic_warmup.model.campaign',
                    '@mautic_warmup.model.warmup_calculator',
                    '@mautic.lead.model.list',
                    '@mautic_warmup.service.email_sender',
                    '@form.factory',
                    '@router',
                    '@request_stack',
                    '@twig'
                ]
            ],
            'mautic_warmup.controller.template' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\Controller\TemplateController::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager',
                    '@mautic_warmup.model.template',
                    '@form.factory',
                    '@router',
                    '@request_stack'
                ]
            ],
            'mautic_warmup.controller.contact' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\Controller\ContactController::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager',
                    '@mautic_warmup.model.contact',
                    '@router',
                    '@request_stack'
                ]
            ],
            'mautic_warmup.controller.report' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\Controller\ReportController::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager'
                ]
            ],
            'mautic_warmup.controller.api' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\Controller\ApiController::class,
                'arguments' => [
                    '@doctrine.orm.entity_manager'
                ]
            ]
        ],
        'commands' => [
            'mautic_warmup.command.process' => [
                'class' => \MauticPlugin\MauticWarmUpBundle\Command\ProcessWarmUpCommand::class,
                'arguments' => [
                    '@mautic_warmup.service.email_sender',
                    '@doctrine.orm.entity_manager',
                    '@monolog.logger.mautic'
                ],
                'tags' => ['console.command']
            ]
        ]
    ],
    'parameters' => [
        'warmup_default_daily_limit' => 100,
        'warmup_max_phase_days' => 30,
        'warmup_check_interval' => 5,
        'warmup_enable_logging' => true
    ],
    'css' => [
        'libraries' => [
            'fontawesome' => [
                'path' => 'app/bundles/CoreBundle/Assets/css/libraries/font-awesome/css/font-awesome.min.css',
                'priority' => 100,
            ],
        ],
    ],
];