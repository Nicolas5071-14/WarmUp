<?php

declare(strict_types=1);

use MauticPlugin\MauticWarmUpBundle\Controller;
use MauticPlugin\MauticWarmUpBundle\EventListener;
use MauticPlugin\MauticWarmUpBundle\Helper;
use MauticPlugin\MauticWarmUpBundle\Model;
use MauticPlugin\MauticWarmUpBundle\Command;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Controller
    $services->set(Controller\DomainController::class)
        ->public()
        ->arg('$em', service('doctrine.orm.entity_manager'))
        ->arg('$domainModel', service('mautic_warmup.model.domain'))  // <-- Important
        ->arg('$formFactory', service('form.factory'))
        ->arg('$router', service('router'))
        ->arg('$requestStack', service('request_stack'))
        ->arg('$twig', service('twig'))
        ->tag('controller.service_arguments');

    $services->set(Controller\CampaignController::class)
        ->public()
        ->arg('$em', service('doctrine.orm.entity_manager'))
        ->arg('$campaignModel', service('mautic_warmup.model.campaign'))
        ->arg('$warmupCalculator', service('mautic_warmup.model.warmup_calculator'))
        ->arg('$segmentModel', service('mautic.lead.model.list'))
        ->arg('$formFactory', service('form.factory'))
        ->arg('$router', service('router'))
        ->arg('$requestStack', service('request_stack'))
        ->arg('$twig', service('twig'))
        ->tag('controller.service_arguments');



    $services->set(Controller\TemplateController::class)
        ->public()
        ->arg('$em', service('doctrine.orm.entity_manager'))
        ->arg('$templateModel', service('mautic_warmup.model.campaign'))  // <-- Important
        ->arg('$formFactory', service('form.factory'))
        ->arg('$router', service('router'))
        ->arg('$requestStack', service('request_stack'))
        ->tag('controller.service_arguments');

    $services->set(Controller\ContactController::class)
        ->public()
        ->arg('$em', service('doctrine.orm.entity_manager'))
        ->arg('$contactModel', service('mautic_warmup.model.contact'))  // <-- Important
        ->arg('$router', service('router'))
        ->arg('$requestStack', service('request_stack'))
        ->tag('controller.service_arguments');

    $services->set(Controller\ReportController::class)
        ->public()
        ->arg('$em', service('doctrine.orm.entity_manager'))
        ->tag('controller.service_arguments');

    $services->set(Controller\ApiController::class)
        ->public()
        ->arg('$em', service('doctrine.orm.entity_manager'))
        ->tag('controller.service_arguments');

    // Event Listener
    $services->set(EventListener\CampaignSubscriber::class)
        ->public()
        ->arg('$em', service('doctrine.orm.entity_manager'))
        ->tag('kernel.event_subscriber');

    $services->set(EventListener\EmailSendSubscriber::class)
        ->public()
        ->arg('$em', service('doctrine.orm.entity_manager'))
        ->tag('kernel.event_subscriber');

    // Helper
    $services->set(Helper\WarmUpHelper::class)
        ->arg('$em', service('doctrine.orm.entity_manager'))
        ->arg('$mailer', service('mailer'))
        ->arg('$logger', service('monolog.logger.mautic'));

    // Model
    // $services->set(Model\DomainModel::class)
    //     ->arg('$em', service('doctrine.orm.entity_manager'));

    // $services->set(Model\CampaignModel::class)
    //     ->arg('$em', service('doctrine.orm.entity_manager'));

    // $services->set(Model\TemplateModel::class)
    //     ->arg('$em', service('doctrine.orm.entity_manager'));

    // $services->set(Model\ContactModel::class)
    //     ->arg('$em', service('doctrine.orm.entity_manager'));

    $services->set('mautic_warmup.service.email_sender')
        ->class(\MauticPlugin\MauticWarmUpBundle\Service\EmailSenderService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('mailer'),
            service('monolog.logger.mautic')
        ])
        ->public();

    $services->set('mautic_warmup.command.process')
        ->class(\MauticPlugin\MauticWarmUpBundle\Command\ProcessWarmUpCommand::class)
        ->args([
            service('mautic_warmup.service.email_sender'),  // Argument 1: EmailSenderService
            service('doctrine.orm.entity_manager'),          // Argument 2: EntityManager
            service('monolog.logger.mautic')                 // Argument 3: Logger
        ])
        ->tag('console.command')
        ->public();
    // Commands
    // $services->set(Command\ProcessWarmUpCommand::class)
    //     ->arg('$em', service('doctrine.orm.entity_manager'))
    //     ->tag('console.command');
};