<?php

namespace MauticPlugin\MauticWarmUpBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Campaign;
use MauticPlugin\MauticWarmUpBundle\Entity\Contact;
use MauticPlugin\MauticWarmUpBundle\Entity\Sequence;
use MauticPlugin\MauticWarmUpBundle\Service\EmailSenderService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessWarmUpCommand extends Command
{
    protected static $defaultName = 'mautic:warmup:process';
    protected static $defaultDescription = 'Process warmup campaigns and send emails';

    private EmailSenderService $emailSender;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(
        EmailSenderService $emailSender,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->emailSender = $emailSender;
        $this->em = $em;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $now = new \DateTime();
            $output->writeln(sprintf('[%s] Starting warmup campaign processing...', $now->format('Y-m-d H:i:s')));
            $this->logger->info('Starting warmup campaign processing', ['time' => $now->format('Y-m-d H:i:s')]);

            // Get active campaigns
            $activeCampaigns = $this->getActiveCampaigns();
            $output->writeln(sprintf('Found %d active campaigns', count($activeCampaigns)));

            $totalEmailsSent = 0;
            $totalCampaignsProcessed = 0;

            foreach ($activeCampaigns as $campaign) {
                try {
                    $campaignId = $campaign->getId();
                    $output->writeln(sprintf('Processing campaign ID: %d - %s', $campaignId, $campaign->getCampaignName()));

                    // Check if campaign should be sending today
                    if (!$this->shouldSendToday($campaign, $now)) {
                        $output->writeln(sprintf('  Skipping - not scheduled for today'));
                        continue;
                    }

                    // Calculate day of campaign
                    $campaignDay = $this->calculateCampaignDay($campaign, $now);
                    if ($campaignDay <= 0) {
                        $output->writeln(sprintf('  Skipping - campaign not started yet or completed'));
                        continue;
                    }

                    // Calculate number of emails to send today based on warmup type
                    $emailsToSendToday = $this->calculateEmailsForDay($campaign, $campaignDay);
                    if ($emailsToSendToday <= 0) {
                        $output->writeln(sprintf('  No emails to send today'));
                        continue;
                    }

                    $output->writeln(sprintf('  Campaign Day: %d, Emails to send: %d', $campaignDay, $emailsToSendToday));

                    // Get contacts ready to receive emails today
                    $contacts = $this->getContactsForToday($campaign, $campaignDay, $emailsToSendToday);
                    $output->writeln(sprintf('  Found %d contacts to email', count($contacts)));

                    if (empty($contacts)) {
                        $output->writeln('  No contacts ready for email today');

                        // Check if campaign is completed
                        $this->checkCampaignCompletion($campaign);
                        continue;
                    }

                    // Get the sequence email for today's day
                    $sequenceEmail = $this->getSequenceEmailForDay($campaign, $campaignDay);

                    // Send emails with sequence
                    $emailsSent = $this->sendEmailsWithSequence($campaign, $contacts, $campaignDay, $sequenceEmail, $output);
                    $totalEmailsSent += $emailsSent;
                    $totalCampaignsProcessed++;

                    $output->writeln(sprintf('  Sent %d emails for campaign %d', $emailsSent, $campaignId));

                    // Update campaign statistics (with error handling)
                    try {
                        $this->updateCampaignStats($campaign, $emailsSent);
                    } catch (\Exception $e) {
                        $output->writeln(sprintf('  Error updating stats for campaign %d: %s', $campaignId, $e->getMessage()));
                        $this->logger->error('Error updating campaign stats', [
                            'campaign_id' => $campaignId,
                            'error' => $e->getMessage()
                        ]);
                    }

                } catch (\Exception $e) {
                    $output->writeln(sprintf('  Error processing campaign %d: %s', $campaign->getId(), $e->getMessage()));
                    $this->logger->error('Error processing campaign', [
                        'campaign_id' => $campaign->getId(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // Try to reset EntityManager if it's closed
                    $this->resetEntityManagerIfClosed();
                }
            }

            // Check for completed campaigns
            $this->checkCompletedCampaigns($output);

            $output->writeln(sprintf('Processing complete. Sent %d emails across %d campaigns', $totalEmailsSent, $totalCampaignsProcessed));
            $this->logger->info('Processing complete', [
                'emails_sent' => $totalEmailsSent,
                'campaigns_processed' => $totalCampaignsProcessed
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln(sprintf('Fatal error: %s', $e->getMessage()));
            $this->logger->error('Fatal error in ProcessWarmUpCommand', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Reset EntityManager if it's closed
     */
    private function resetEntityManagerIfClosed(): void
    {
        if (!$this->em->isOpen()) {
            $this->logger->warning('EntityManager was closed, attempting to reset...');

            // Get the current connection
            $connection = $this->em->getConnection();

            // Clear all references to closed entities
            $this->em->clear();

            // Reset the EntityManager
            $this->em = $this->em->create(
                $connection,
                $this->em->getConfiguration()
            );

            $this->logger->info('EntityManager has been reset');
        }
    }

    /**
     * Get active campaigns that should be processed
     */
    private function getActiveCampaigns(): array
    {
        try {
            $qb = $this->em->createQueryBuilder();
            $qb->select('c')
                ->from(Campaign::class, 'c')
                ->where('c.status = :status')
                ->andWhere('c.startDate <= :now')
                ->andWhere('(c.endDate IS NULL OR c.endDate >= :now)')
                ->setParameter('status', 'active')
                ->setParameter('now', new \DateTime())
                ->orderBy('c.startDate', 'ASC');

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger->error('Error getting active campaigns', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Check if campaign should send emails today
     */
    private function shouldSendToday(Campaign $campaign, \DateTime $now): bool
    {
        $sendFrequency = $campaign->getSendFrequency();
        $sendTime = $campaign->getSendTime();
        $enableWeekends = $campaign->isEnableWeekends();

        // Check if it's weekend and weekends are disabled
        $dayOfWeek = (int) $now->format('N'); // 1=Monday, 7=Sunday
        if (!$enableWeekends && ($dayOfWeek === 6 || $dayOfWeek === 7)) {
            return false;
        }

        // Check frequency
        switch ($sendFrequency) {
            case 'daily':
                return true;

            case 'weekly':
                // Send only on specific day (e.g., Monday)
                $weeklyDay = $campaign->getWeeklyDay() ?? 1; // Default Monday
                return $dayOfWeek === $weeklyDay;

            case 'monthly':
                // Send on specific day of month
                $monthlyDay = $campaign->getMonthlyDay() ?? 1; // Default 1st
                return (int) $now->format('j') === $monthlyDay;

            default:
                return true;
        }
    }

    /**
     * Calculate current day of campaign
     */
    private function calculateCampaignDay(Campaign $campaign, \DateTime $now): int
    {
        $startDate = $campaign->getStartDate();
        if (!$startDate) {
            return 0;
        }

        // Calculate days since start
        $interval = $startDate->diff($now);
        $daysSinceStart = (int) $interval->format('%a');

        // Add 1 because day 1 is the first day
        $campaignDay = $daysSinceStart + 1;

        // Check if campaign is completed
        $durationDays = $campaign->getDurationDays() ?? 30;
        if ($campaignDay > $durationDays) {
            return 0; // Campaign completed
        }

        return $campaignDay;
    }

    /**
     * Calculate number of emails to send today
     */
    private function calculateEmailsForDay(Campaign $campaign, int $campaignDay): int
    {
        try {
            // Priorité 1: Utiliser le plan stocké dans warmupPlan
            $warmupPlan = $campaign->getWarmupPlan();
            if (!empty($warmupPlan) && isset($warmupPlan[$campaignDay - 1])) {
                return $warmupPlan[$campaignDay - 1]['emails'] ?? 0;
            }

            // Priorité 2: Calculer dynamiquement
            $totalContacts = $campaign->getTotalContacts();
            $durationDays = $campaign->getDurationDays();

            if ($totalContacts === 0 || $durationDays === 0) {
                return 0;
            }

            // Volume cible final (plateau)
            $E_target = (int) ceil($totalContacts / $durationDays);

            $startVolume = max(1, (int) $campaign->getStartVolume());
            $increment = (int) $campaign->getDailyIncrement();
            $alpha = 0.10;

            $formulaType = $campaign->getWarmupType() ? $campaign->getWarmupType()->getFormulaType() : 'arithmetic';

            switch ($formulaType) {
                case 'arithmetic':
                    $E = $startVolume + ($campaignDay - 1) * $increment;
                    break;

                case 'geometric':
                    $r = 1 + ($increment / 100);
                    $E = $startVolume * pow($r, $campaignDay - 1);
                    break;

                case 'flat':
                    $E = $startVolume;
                    break;

                case 'randomize':
                    $base = $startVolume + ($campaignDay - 1) * $increment;
                    $min = $base * 0.85;
                    $max = $base * 1.15;
                    $E = mt_rand((int) $min, (int) $max);
                    break;

                case 'progressive':
                    $previous = $campaign->getLastComputedVolume() ?? $startVolume;
                    $E = $previous + $alpha * ($E_target - $previous);
                    $campaign->setLastComputedVolume((int) round($E));
                    break;

                default:
                    $E = $startVolume;
            }

            // Ne jamais dépasser le plateau cible
            return (int) max(1, min(round($E), $E_target));
        } catch (\Exception $e) {
            $this->logger->error('Error calculating emails for day', [
                'campaign_id' => $campaign->getId(),
                'campaign_day' => $campaignDay,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get contacts ready to receive emails today
     */
    private function getContactsForToday(Campaign $campaign, int $campaignDay, int $emailsToSendToday): array
    {
        try {
            // Nous sélectionnons les contacts qui n'ont pas encore reçu d'email
            $qb = $this->em->createQueryBuilder();
            $qb->select('c')
                ->from(Contact::class, 'c')
                ->where('c.campaign = :campaign')
                ->andWhere('c.isActive = :active')
                ->andWhere('c.status = :status')
                ->andWhere('c.emailsSent = 0') // Pas encore reçu d'email
                ->setParameter('campaign', $campaign)
                ->setParameter('active', true)
                ->setParameter('status', 'pending')
                ->orderBy('c.id', 'ASC')
                ->setMaxResults($emailsToSendToday);

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger->error('Error getting contacts for today', [
                'campaign_id' => $campaign->getId(),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get sequence email for current day
     */
    private function getSequenceEmailForDay(Campaign $campaign, int $campaignDay): array
    {
        try {
            $sequences = $campaign->getSequences()->toArray();

            if (empty($sequences)) {
                // Retourner le contenu par défaut de la campagne
                return [
                    'subject' => $campaign->getSubjectTemplate() ?? 'Email from campaign',
                    'body' => $campaign->getCustomMessage() ?? 'Hello {{first_name}}, this is your email.',
                    'sequence_day' => $campaignDay,
                    'sequence_name' => 'Default'
                ];
            }

            // Trier les séquences par order
            usort($sequences, function ($a, $b) {
                return $a->getSequenceOrder() <=> $b->getSequenceOrder();
            });

            // Si le jour dépasse le nombre de séquences, on utilise la dernière
            $sequenceIndex = min($campaignDay - 1, count($sequences) - 1);
            $sequence = $sequences[$sequenceIndex];

            return [
                'subject' => $sequence->getSubjectTemplate(),
                'body' => $sequence->getBodyTemplate(),
                'sequence_day' => $campaignDay,
                'sequence_name' => $sequence->getSequenceName()
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error getting sequence email', [
                'campaign_id' => $campaign->getId(),
                'campaign_day' => $campaignDay,
                'error' => $e->getMessage()
            ]);

            return [
                'subject' => $campaign->getSubjectTemplate() ?? 'Email from campaign',
                'body' => $campaign->getCustomMessage() ?? 'Hello {{first_name}}, this is your email.',
                'sequence_day' => $campaignDay,
                'sequence_name' => 'Default'
            ];
        }
    }

    /**
     * Send emails with sequence management (with error handling)
     */
    private function sendEmailsWithSequence(Campaign $campaign, array $contacts, int $campaignDay, array $sequenceEmail, OutputInterface $output): int
    {
        $emailsSent = 0;

        foreach ($contacts as $index => $contact) {
            try {
                $this->resetEntityManagerIfClosed();

                // Re-fetch contact to ensure it's managed
                $contact = $this->em->getRepository(Contact::class)->find($contact->getId());
                if (!$contact) {
                    $output->writeln(sprintf('    Contact not found: %d', $contact->getId()));
                    continue;
                }

                $domain = $campaign->getDomain();
                if (!$domain) {
                    $output->writeln('    No domain configured for campaign');
                    continue;
                }

                // Utiliser le contenu de la séquence
                $subject = $this->replaceVariables($sequenceEmail['subject'], $contact, $campaign, $campaignDay);
                $message = $this->replaceVariables($sequenceEmail['body'], $contact, $campaign, $campaignDay);

                // Add unsubscribe link
                $unsubscribeLink = $this->generateUnsubscribeLink($contact);
                $message .= "\n\n---\n" . $unsubscribeLink;

                // Send email
                $result = $this->emailSender->sendEmail(
                    $domain,
                    $contact->getEmail(),
                    $subject,
                    $message,
                    $campaign->getId(),
                    $contact->getId()
                );

                if ($result['success']) {
                    // Mettre à jour le contact
                    $contact->setLastSentDate(new \DateTime());
                    $contact->setEmailsSent(1); // Set to 1 (not increment)
                    $contact->setStatus('completed');
                    $contact->setSequenceDay($campaignDay);

                    // Persist changes
                    $this->em->persist($contact);
                    $this->em->flush();

                    $emailsSent++;

                    $output->writeln(sprintf('    Sent email (sequence day %d) to: %s', $campaignDay, $contact->getEmail()));

                } else {
                    $output->writeln(sprintf('    Failed to send to: %s - %s', $contact->getEmail(), $result['error']));

                    $contact->setFailureCount($contact->getFailureCount() + 1);
                    if ($contact->getFailureCount() >= 3) {
                        $contact->setStatus('bounced');
                    }

                    $this->em->persist($contact);
                    $this->em->flush();
                }

            } catch (\Exception $e) {
                $output->writeln(sprintf('    Error sending to %s: %s', $contact->getEmail(), $e->getMessage()));
                $this->logger->error('Error sending email', [
                    'contact_id' => $contact->getId(),
                    'email' => $contact->getEmail(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Reset EntityManager if needed
                $this->resetEntityManagerIfClosed();

                // Continue with next contact
                continue;
            }

            // Petit délai pour éviter de surcharger le serveur
            if ($index < count($contacts) - 1) {
                usleep(50000); // 0.05 seconde
            }
        }

        return $emailsSent;
    }

    /**
     * Replace variables in email content
     */
    private function replaceVariables(string $content, Contact $contact, Campaign $campaign, int $campaignDay): string
    {
        $replacements = [
            '{{first_name}}' => $contact->getFirstName() ?? '',
            '{{last_name}}' => $contact->getLastName() ?? '',
            '{{email}}' => $contact->getEmail(),
            '{{campaign_name}}' => $campaign->getCampaignName(),
            '{{campaign_day}}' => (string) $campaignDay,
            '{{date}}' => date('Y-m-d'),
            '{{time}}' => date('H:i:s'),
            '{{total_days}}' => (string) $campaign->getDurationDays(),
            '{{remaining_days}}' => (string) max(0, $campaign->getDurationDays() - $campaignDay),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Generate unsubscribe link
     */
    private function generateUnsubscribeLink(Contact $contact): string
    {
        $token = $contact->getUnsubscribeToken();
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            $contact->setUnsubscribeToken($token);
            $this->em->persist($contact);
            $this->em->flush();
        }

        $baseUrl = $_ENV['MAUTIC_BASE_URL'] ?? 'https://your-domain.com';
        return sprintf('%s/s/warmup/unsubscribe/%s', $baseUrl, $token);
    }

    /**
     * Update campaign statistics with error handling
     */
    private function updateCampaignStats(Campaign $campaign, int $emailsSentToday): void
    {
        try {
            // Refresh campaign from database
            $campaign = $this->em->getRepository(Campaign::class)->find($campaign->getId());
            if (!$campaign) {
                throw new \Exception('Campaign not found in database');
            }

            // Update statistics
            $campaign->setEmailsSent($campaign->getEmailsSent() + $emailsSentToday);
            $campaign->setUpdatedAt(new \DateTime());

            // Calculate progress percentage
            $totalContacts = $campaign->getTotalContacts();
            if ($totalContacts > 0) {
                $progress = min(100, ($campaign->getEmailsSent() / $totalContacts) * 100);
                $campaign->setProgress($progress);
            }

            // Save changes
            $this->em->persist($campaign);
            $this->em->flush();

        } catch (\Exception $e) {
            $this->logger->error('Error updating campaign stats', [
                'campaign_id' => $campaign->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Check and mark completed campaigns
     */
    private function checkCampaignCompletion(Campaign $campaign): void
    {
        try {
            // Refresh campaign
            $campaign = $this->em->getRepository(Campaign::class)->find($campaign->getId());
            if (!$campaign)
                return;

            $totalContacts = $campaign->getTotalContacts();
            $emailsSent = $campaign->getEmailsSent();

            // Check if all emails have been sent
            if ($totalContacts > 0 && $emailsSent >= $totalContacts) {
                $campaign->setStatus('completed');
                $campaign->setCompletedAt(new \DateTime());
                $campaign->setUpdatedAt(new \DateTime());

                $this->em->persist($campaign);
                $this->em->flush();

                $this->logger->info('Campaign marked as completed', [
                    'campaign_id' => $campaign->getId(),
                    'campaign_name' => $campaign->getCampaignName(),
                    'emails_sent' => $emailsSent,
                    'total_contacts' => $totalContacts
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error checking campaign completion', [
                'campaign_id' => $campaign->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check for completed campaigns (old method for backward compatibility)
     */
    private function checkCompletedCampaigns(OutputInterface $output): void
    {
        try {
            $qb = $this->em->createQueryBuilder();
            $qb->select('c')
                ->from(Campaign::class, 'c')
                ->where('c.status = :status')
                ->andWhere('c.endDate < :now')
                ->setParameter('status', 'active')
                ->setParameter('now', new \DateTime());

            $completedCampaigns = $qb->getQuery()->getResult();

            foreach ($completedCampaigns as $campaign) {
                $campaign->setStatus('completed');
                $campaign->setUpdatedAt(new \DateTime());
                $this->em->persist($campaign);

                $output->writeln(sprintf('Marked campaign %d as completed', $campaign->getId()));
            }

            if (!empty($completedCampaigns)) {
                $this->em->flush();
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in checkCompletedCampaigns', ['error' => $e->getMessage()]);
        }
    }
}