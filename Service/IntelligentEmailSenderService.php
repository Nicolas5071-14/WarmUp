<?php

namespace MauticPlugin\MauticWarmUpBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Campaign;
use MauticPlugin\MauticWarmUpBundle\Entity\Contact;
use MauticPlugin\MauticWarmUpBundle\Entity\EmailTracking;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class IntelligentEmailSenderService
{
    private EntityManagerInterface $em;
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private array $distributionSchedule = [];

    public function __construct(
        EntityManagerInterface $em,
        MailerInterface $mailer,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->mailer = $mailer;
        $this->logger = $logger;
        
        // Define distribution throughout the day (every 5 minutes)
        $this->initializeDistributionSchedule();
    }

    private function initializeDistributionSchedule(): void
    {
        // Distribute emails throughout working hours (9 AM to 5 PM)
        $startHour = 9;
        $endHour = 17;
        $intervalMinutes = 5; // Every 5 minutes
        
        for ($hour = $startHour; $hour < $endHour; $hour++) {
            for ($minute = 0; $minute < 60; $minute += $intervalMinutes) {
                $this->distributionSchedule[] = sprintf('%02d:%02d', $hour, $minute);
            }
        }
    }

    public function sendEmailsWithDistribution(
        Campaign $campaign,
        array $contacts,
        array $emailSequence,
        int $campaignDay,
        OutputInterface $output
    ): int {
        $sentCount = 0;
        $totalContacts = count($contacts);
        
        if ($totalContacts === 0) {
            return 0;
        }
        
        // Calculate distribution per time slot
        $timeSlots = count($this->distributionSchedule);
        $perSlot = max(1, floor($totalContacts / $timeSlots));
        $remaining = $totalContacts % $timeSlots;
        
        $output->writeln(sprintf('  Distributing %d emails across %d time slots', $totalContacts, $timeSlots));
        
        $currentSlot = 0;
        $sentInCurrentSlot = 0;
        $slotStartTime = null;
        
        foreach ($contacts as $index => $contact) {
            // Determine if we should move to next time slot
            if ($sentInCurrentSlot >= $perSlot + ($currentSlot < $remaining ? 1 : 0)) {
                if ($slotStartTime) {
                    // Wait until next slot time
                    $this->waitForNextSlot($currentSlot, $output);
                }
                
                $currentSlot++;
                $sentInCurrentSlot = 0;
                $slotStartTime = new \DateTime();
                
                $output->writeln(sprintf('    Moving to time slot %d/%d', $currentSlot + 1, $timeSlots));
            }
            
            try {
                // Send email
                $this->sendSingleEmail($campaign, $contact, $emailSequence, $campaignDay);
                $sentCount++;
                $sentInCurrentSlot++;
                
                $output->writeln(sprintf('    Sent to %s (slot %d)', $contact->getEmail(), $currentSlot + 1));
                
                // Small delay between emails in same slot (0.5 seconds)
                if ($index < $totalContacts - 1) {
                    usleep(500000);
                }
                
            } catch (\Exception $e) {
                $output->writeln(sprintf('    Failed to send to %s: %s', $contact->getEmail(), $e->getMessage()));
                $this->logger->error('Failed to send email', [
                    'contact_id' => $contact->getId(),
                    'email' => $contact->getEmail(),
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $sentCount;
    }

    private function waitForNextSlot(int $currentSlot, OutputInterface $output): void
    {
        if (!isset($this->distributionSchedule[$currentSlot])) {
            return;
        }
        
        $nextSlotTime = $this->distributionSchedule[$currentSlot];
        $now = new \DateTime();
        $targetTime = new \DateTime($now->format('Y-m-d') . ' ' . $nextSlotTime);
        
        if ($targetTime > $now) {
            $waitSeconds = $targetTime->getTimestamp() - $now->getTimestamp();
            $output->writeln(sprintf('    Waiting %d seconds for next time slot (%s)', $waitSeconds, $nextSlotTime));
            sleep($waitSeconds);
        }
    }

    private function sendSingleEmail(
        Campaign $campaign,
        Contact $contact,
        array $emailSequence,
        int $campaignDay
    ): void {
        $domain = $campaign->getDomain();
        if (!$domain) {
            throw new \Exception('No domain configured');
        }
        
        // Prepare content with variables
        $subject = $this->replaceVariables($emailSequence['subject'], $contact, $campaign, $campaignDay);
        $body = $this->replaceVariables($emailSequence['body'], $contact, $campaign, $campaignDay);
        
        // Add unsubscribe link
        $unsubscribeLink = $this->generateUnsubscribeLink($contact);
        $trackingPixel = $this->generateTrackingPixel($contact, $campaign);
        $clickTrackingLinks = $this->processClickTracking($body, $contact, $campaign);
        
        $finalBody = $body . "\n\n" . $unsubscribeLink . "\n" . $trackingPixel;
        
        // Create email
        $email = (new Email())
            ->from($this->getFromEmail($domain))
            ->to($contact->getEmail())
            ->subject($subject)
            ->html($finalBody)
            ->text(strip_tags($finalBody));
        
        // Send email
        $this->mailer->send($email);
        
        // Update contact
        $contact->setEmailsSent(1);
        $contact->setEmailSequenceDay($campaignDay);
        $contact->setEmailSentDay($campaignDay);
        $contact->setLastEmailType($emailSequence['type']);
        $contact->setLastSentDate(new \DateTime());
        $contact->setStatus('sent');
        
        // Create tracking record
        $this->createTrackingRecord($contact, $campaign, $subject, 'sent');
        
        $this->em->persist($contact);
        $this->em->flush();
    }

    private function replaceVariables(string $content, Contact $contact, Campaign $campaign, int $day): string
    {
        $variables = [
            '{{first_name}}' => $contact->getFirstName() ?? '',
            '{{last_name}}' => $contact->getLastName() ?? '',
            '{{email}}' => $contact->getEmail(),
            '{{campaign_name}}' => $campaign->getCampaignName(),
            '{{campaign_day}}' => (string) $day,
            '{{total_days}}' => (string) $campaign->getDurationDays(),
            '{{date}}' => date('Y-m-d'),
        ];
        
        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    private function getFromEmail($domain): string
    {
        $prefix = $domain->getEmailPrefix() ?: 'noreply';
        return $prefix . '@' . $domain->getDomainName();
    }

    private function generateUnsubscribeLink(Contact $contact): string
    {
        $token = $contact->getUnsubscribeToken();
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            $contact->setUnsubscribeToken($token);
        }
        
        $baseUrl = $_ENV['MAUTIC_BASE_URL'] ?? 'https://your-domain.com';
        return sprintf('<p><a href="%s/s/warmup/unsubscribe/%s">Unsubscribe</a></p>', $baseUrl, $token);
    }

    private function generateTrackingPixel(Contact $contact, Campaign $campaign): string
    {
        $trackingId = base64_encode($contact->getId() . '|' . $campaign->getId());
        $url = $_ENV['MAUTIC_BASE_URL'] ?? 'https://your-domain.com';
        
        return sprintf(
            '<img src="%s/s/warmup/track/open/%s" width="1" height="1" alt="" style="display:none;" />',
            $url,
            $trackingId
        );
    }

    private function processClickTracking(string $body, Contact $contact, Campaign $campaign): string
    {
        // Find all links in the body
        preg_match_all('/href="([^"]+)"/', $body, $matches);
        
        if (empty($matches[1])) {
            return $body;
        }
        
        $processedBody = $body;
        foreach ($matches[1] as $link) {
            // Skip unsubscribe links and tracking pixels
            if (strpos($link, 'unsubscribe') !== false || strpos($link, 'track/') !== false) {
                continue;
            }
            
            // Create tracked link
            $trackingId = base64_encode($contact->getId() . '|' . $campaign->getId() . '|' . urlencode($link));
            $trackedLink = sprintf(
                '%s/s/warmup/track/click/%s',
                $_ENV['MAUTIC_BASE_URL'] ?? 'https://your-domain.com',
                $trackingId
            );
            
            $processedBody = str_replace(
                sprintf('href="%s"', $link),
                sprintf('href="%s"', $trackedLink),
                $processedBody
            );
        }
        
        return $processedBody;
    }

    private function createTrackingRecord(
        Contact $contact,
        Campaign $campaign,
        string $subject,
        string $status
    ): void {
        $tracking = new EmailTracking();
        $tracking->setContact($contact);
        $tracking->setCampaign($campaign);
        $tracking->setEmailSubject($subject);
        $tracking->setStatus($status);
        $tracking->setSentAt(new \DateTime());
        $tracking->setTrackingToken(bin2hex(random_bytes(16)));
        
        $this->em->persist($tracking);
    }
}
