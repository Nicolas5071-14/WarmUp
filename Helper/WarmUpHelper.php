<?php

namespace MauticPlugin\MauticWarmUpBundle\Helper;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Domain;
use MauticPlugin\MauticWarmUpBundle\Entity\Campaign;
use MauticPlugin\MauticWarmUpBundle\Entity\Contact;
use MauticPlugin\MauticWarmUpBundle\Entity\Sequence;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

class WarmUpHelper
{
    private EntityManagerInterface $em;
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        MailerInterface $mailer,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    /**
     * Process warm-up for all active campaigns
     */
    public function processWarmUp(): void
    {
        $this->logger->info('[WarmUp] Starting warm-up process');

        $campaignRepo = $this->em->getRepository(Campaign::class);
        $activeCampaigns = $campaignRepo->findBy(['status' => Campaign::STATUS_ACTIVE]);

        foreach ($activeCampaigns as $campaign) {
            try {
                $this->processCampaign($campaign);
            } catch (\Exception $e) {
                $this->logger->error('[WarmUp] Error processing campaign ' . $campaign->getId() . ': ' . $e->getMessage());
            }
        }

        $this->logger->info('[WarmUp] Warm-up process completed');
    }

    /**
     * Process a single campaign
     */
    private function processCampaign(Campaign $campaign): void
    {
        $domain = $campaign->getDomain();

        if (!$domain || !$domain->isActive() || !$domain->canSendMoreToday()) {
            return;
        }

        // Get contacts ready to receive next email
        $contacts = $this->getContactsReadyForNextEmail($campaign);

        foreach ($contacts as $contact) {
            if (!$domain->canSendMoreToday()) {
                break;
            }

            try {
                $this->sendNextEmail($campaign, $contact, $domain);
                $domain->incrementSentToday();
                $this->em->persist($domain);
            } catch (\Exception $e) {
                $this->logger->error('[WarmUp] Failed to send email to contact ' . $contact->getId() . ': ' . $e->getMessage());
            }
        }

        $this->em->flush();
    }

    /**
     * Get contacts ready for next email
     */
    private function getContactsReadyForNextEmail(Campaign $campaign): array
    {
        $contacts = [];
        $now = new \DateTime();

        foreach ($campaign->getContacts() as $contact) {
            if (!$contact->isActive()) {
                continue;
            }

            $nextSendDate = $contact->getNextSendDate();
            if ($nextSendDate && $nextSendDate <= $now) {
                $contacts[] = $contact;
            }
        }

        // Sort by next send date (oldest first)
        usort($contacts, function ($a, $b) {
            return $a->getNextSendDate() <=> $b->getNextSendDate();
        });

        return $contacts;
    }

    /**
     * Send next email in sequence to a contact
     */
    private function sendNextEmail(Campaign $campaign, Contact $contact, Domain $domain): void
    {
        $sequence = $this->getNextSequence($campaign, $contact);

        if (!$sequence) {
            $this->logger->info('[WarmUp] No more sequences for contact ' . $contact->getId());
            return;
        }

        // Prepare email
        $email = (new Email())
            ->from($domain->generateEmailAddress() ?? 'noreply@' . $domain->getDomainName())
            ->to($contact->getEmailAddress())
            ->subject($this->processTemplate($sequence->getSubjectTemplate(), $contact))
            ->html($this->processTemplate($sequence->getBodyTemplate(), $contact))
            ->text(strip_tags($this->processTemplate($sequence->getBodyTemplate(), $contact)));

        // Send email
        $this->mailer->send($email);

        // Update contact
        $contact->setSentCount($contact->getSentCount() + 1);
        $contact->setLastSent(new \DateTime());
        $contact->setNextSendDate(
            (new \DateTime())->modify('+' . $contact->getDaysBetweenEmails() . ' days')
        );

        // Update campaign
        $campaign->setEmailsSent($campaign->getEmailsSent() + 1);

        // Log the send
        $this->logEmailSent($campaign, $contact, $sequence, $email->getSubject(), 'sent');

        $this->logger->info(sprintf(
            '[WarmUp] Sent email %d/%d to %s from campaign %s',
            $contact->getSentCount(),
            count($campaign->getSequences()),
            $contact->getEmailAddress(),
            $campaign->getCampaignName()
        ));
    }

    /**
     * Get next sequence for a contact
     */
    private function getNextSequence(Campaign $campaign, Contact $contact): ?Sequence
    {
        $sentCount = $contact->getSentCount();

        foreach ($campaign->getSequences() as $sequence) {
            if ($sequence->getSequenceOrder() === ($sentCount + 1)) {
                return $sequence;
            }
        }

        return null;
    }

    /**
     * Process template with contact variables
     */
    private function processTemplate(string $template, Contact $contact): string
    {
        $variables = [
            '{{contact.email}}' => $contact->getEmailAddress(),
            '{{contact.first_name}}' => $contact->getFirstName() ?? '',
            '{{contact.last_name}}' => $contact->getLastName() ?? '',
            '{{contact.full_name}}' => trim(($contact->getFirstName() ?? '') . ' ' . ($contact->getLastName() ?? '')),
            '{{date}}' => date('Y-m-d'),
            '{{time}}' => date('H:i:s'),
        ];

        return str_replace(array_keys($variables), array_values($variables), $template);
    }

    /**
     * Log email sent
     */
    private function logEmailSent(
        Campaign $campaign,
        Contact $contact,
        Sequence $sequence,
        string $subject,
        string $status = 'sent',
        string $errorMessage = null
    ): void {
        $log = new \MauticPlugin\MauticWarmUpBundle\Entity\SentLog();
        $log->setCampaign($campaign);
        $log->setDomain($campaign->getDomain());
        $log->setContact($contact);
        $log->setSequenceDay($contact->getSentCount());
        $log->setEmailSubject($subject);
        $log->setEmailContent($sequence->getBodyTemplate());
        $log->setSendTime(new \DateTime());
        $log->setStatus($status);

        if ($errorMessage) {
            $log->setErrorMessage($errorMessage);
        }

        $this->em->persist($log);
        $this->em->flush();
    }

    /**
     * Calculate daily limit based on warm-up phase
     */
    public function calculateDailyLimit(Domain $domain, int $phase = null): int
    {
        $phase = $phase ?? $domain->getCurrentPhaseDay();
        $warmupType = $domain->getWarmupType() ?? 'arithmetic';

        switch ($warmupType) {
            case 'geometric':
                return (int) min($domain->getDailyLimit(), pow(2, $phase));

            case 'flat':
                return $phase <= 7 ? 10 : $domain->getDailyLimit();

            case 'progressive':
                return (int) min($domain->getDailyLimit(), $phase * 15);

            case 'randomize':
                $min = max(5, $phase * 2);
                $max = min($domain->getDailyLimit(), $phase * 20);
                return rand($min, $max);

            case 'arithmetic':
            default:
                return (int) min($domain->getDailyLimit(), $phase * 10);
        }
    }
}