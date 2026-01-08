<?php

namespace MauticPlugin\MauticWarmUpBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\CampaignEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CampaignSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public static function getSubscribedEvents(): array
    {
        return [
                // Note: Utilisez les constantes correctes de Mautic
            CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
        ];
    }

    /**
     * Add campaign actions
     */
    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        // Add warm-up campaign action
        $action = [
            'label' => 'mautic.warmup.campaign.action.send_email',
            'description' => 'mautic.warmup.campaign.action.send_email.descr',
            'formType' => 'MauticPlugin\MauticWarmUpBundle\Form\Type\CampaignActionType',
            // Note: Supprimez 'eventName' car Mautic le gère automatiquement
        ];

        $event->addAction('warmup.send_email', $action);

        // Add domain verification check
        $condition = [
            'label' => 'mautic.warmup.campaign.condition.domain_verified',
            'description' => 'mautic.warmup.campaign.condition.domain_verified.descr',
            'formType' => 'MauticPlugin\MauticWarmUpBundle\Form\Type\DomainConditionType',
        ];

        $event->addCondition('warmup.domain_verified', $condition);
    }
}