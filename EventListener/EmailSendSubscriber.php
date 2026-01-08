<?php

namespace MauticPlugin\MauticWarmUpBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailSendEvent;
use MauticPlugin\MauticWarmUpBundle\Helper\WarmUpHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EmailSendSubscriber implements EventSubscriberInterface
{
    private WarmUpHelper $warmUpHelper;
    private EntityManagerInterface $em;

    public function __construct(
        WarmUpHelper $warmUpHelper,
        EntityManagerInterface $em
    ) {
        $this->warmUpHelper = $warmUpHelper;
        $this->em = $em;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_ON_SEND => ['onEmailSend', 0],
            EmailEvents::EMAIL_PARSE => ['onEmailParse', 0],
        ];
    }

    /**
     * Intercept email sending for warm-up processing
     */
    public function onEmailSend(EmailSendEvent $event): void
    {
        $email = $event->getEmail();
        $lead = $event->getLead();
        
        // Check if this email should go through warm-up
        if ($this->shouldUseWarmUp($email, $lead)) {
            // Get available domain with capacity
            $domains = $this->warmUpHelper->getDomainsWithCapacity();
            
            if (!empty($domains)) {
                $domain = $domains[0]; // Use first available domain
                
                // Modify sender to use warm-up domain
                $fromEmail = $domain->generateEmailAddress();
                if ($fromEmail) {
                    $event->setFrom($fromEmail, $event->getFromName());
                    
                    // Add warm-up tracking token
                    $tokens = $event->getTokens();
                    $tokens['{warmup_domain}'] = $domain->getDomainName();
                    $tokens['{warmup_tracking_id}'] = $this->generateTrackingId($domain, $email, $lead);
                    $event->setTokens($tokens);
                    
                    // Log this send for warm-up tracking
                    $this->logWarmUpSend($domain, $email, $lead);
                }
            }
        }
    }

    /**
     * Parse email content for warm-up specific tokens
     */
    public function onEmailParse(EmailSendEvent $event): void
    {
        $content = $event->getContent();
        $plainText = $event->getPlainText();
        $tokens = $event->getTokens();
        
        // Add warm-up unsubscribe link if not present
        if (strpos($content, '{unsubscribe_url}') === false) {
            $unsubscribeHtml = '<p style="font-size:12px;color:#666;">'
                . 'If you no longer wish to receive these emails, '
                . '<a href="{unsubscribe_url}">unsubscribe here</a>.'
                . '</p>';
            
            $content = str_replace('</body>', $unsubscribeHtml . '</body>', $content);
            $event->setContent($content);
        }
        
        // Process warm-up specific tokens
        if (isset($tokens['{warmup_tracking_id}'])) {
            $trackingPixel = '<img src="https://tracker.example.com/pixel.gif?id=' 
                . urlencode($tokens['{warmup_tracking_id}']) 
                . '" width="1" height="1" style="display:none;" />';
            
            $content = str_replace('</body>', $trackingPixel . '</body>', $content);
            $event->setContent($content);
        }
    }

    /**
     * Check if email should use warm-up
     */
    private function shouldUseWarmUp($email, $lead): bool
    {
        // Add your logic here to determine if warm-up should be used
        // For example: check email type, lead source, campaign, etc.
        
        // For now, always return true for testing
        return true;
    }

    /**
     * Generate tracking ID for warm-up email
     */
    private function generateTrackingId($domain, $email, $lead): string
    {
        return md5($domain->getId() . '_' . $email->getId() . '_' . $lead['id'] . '_' . time());
    }

    /**
     * Log warm-up send for tracking
     */
    private function logWarmUpSend($domain, $email, $lead): void
    {
        // Implementation depends on your logging setup
        // This would create a record in your warm-up tracking table
    }
}
