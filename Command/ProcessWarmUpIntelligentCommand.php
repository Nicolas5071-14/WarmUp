<?php

namespace MauticPlugin\MauticWarmUpBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Campaign;
use MauticPlugin\MauticWarmUpBundle\Entity\Contact;
use MauticPlugin\MauticWarmUpBundle\Service\IntelligentEmailSenderService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessWarmUpIntelligentCommand extends Command
{
    protected static $defaultName = 'mautic:warmup:process-intelligent';
    protected static $defaultDescription = 'Process warmup campaigns with intelligent distribution';

    private IntelligentEmailSenderService $emailSender;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(
        IntelligentEmailSenderService $emailSender,
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
            $output->writeln(sprintf('[%s] Starting intelligent warmup processing...', $now->format('Y-m-d H:i:s')));
            
            // 1. Process active campaigns with distribution
            $processed = $this->processCampaignsWithDistribution($output);
            
            // 2. Update campaign statistics
            $this->updateStatistics();
            
            $output->writeln(sprintf('Processing complete. Processed %d campaigns', $processed));
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln(sprintf('Fatal error: %s', $e->getMessage()));
            $this->logger->error('Fatal error in intelligent command', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    private function processCampaignsWithDistribution(OutputInterface $output): int
    {
        $processed = 0;
        $campaigns = $this->getActiveCampaigns();
        
        foreach ($campaigns as $campaign) {
            try {
                if ($this->processSingleCampaign($campaign, $output)) {
                    $processed++;
                }
            } catch (\Exception $e) {
                $output->writeln(sprintf('Error processing campaign %d: %s', $campaign->getId(), $e->getMessage()));
            }
        }
        
        return $processed;
    }

    private function getActiveCampaigns(): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('c')
            ->from(Campaign::class, 'c')
            ->where('c.status = :status')
            ->andWhere('c.startDate <= :now')
            ->andWhere('(c.endDate IS NULL OR c.endDate >= :now)')
            ->setParameter('status', 'active')
            ->setParameter('now', new \DateTime())
            ->orderBy('c.priority', 'DESC')
            ->addOrderBy('c.createdAt', 'ASC');

        return $qb->getQuery()->getResult();
    }

    private function processSingleCampaign(Campaign $campaign, OutputInterface $output): bool
    {
        $campaignId = $campaign->getId();
        $output->writeln(sprintf('Processing campaign %d: %s', $campaignId, $campaign->getCampaignName()));
        
        // Calculate current campaign day
        $campaignDay = $this->calculateCampaignDay($campaign);
        if ($campaignDay <= 0) {
            $output->writeln('  Campaign not active today');
            return false;
        }
        
        $output->writeln(sprintf('  Campaign day: %d', $campaignDay));
        
        // Get email sequence for today
        $emailSequence = $this->getEmailSequenceForDay($campaign, $campaignDay);
        if (!$emailSequence) {
            $output->writeln('  No email sequence found for today');
            return false;
        }
        
        // Calculate how many emails to send today
        $emailsToSendToday = $this->calculateEmailsForToday($campaign, $campaignDay);
        if ($emailsToSendToday <= 0) {
            $output->writeln('  No emails to send today');
            return false;
        }
        
        $output->writeln(sprintf('  Emails to send today: %d', $emailsToSendToday));
        
        // Get contacts who should receive email today (those who haven't received any yet)
        $contacts = $this->getContactsForToday($campaign, $emailsToSendToday);
        $output->writeln(sprintf('  Contacts to email today: %d', count($contacts)));
        
        if (empty($contacts)) {
            $output->writeln('  No contacts to email today');
            return false;
        }
        
        // Send emails with intelligent distribution
        $sent = $this->emailSender->sendEmailsWithDistribution(
            $campaign,
            $contacts,
            $emailSequence,
            $campaignDay,
            $output
        );
        
        $output->writeln(sprintf('  Successfully sent %d emails', $sent));
        
        // Update campaign progress
        $this->updateCampaignProgress($campaign);
        
        return $sent > 0;
    }

    private function calculateCampaignDay(Campaign $campaign): int
    {
        $startDate = $campaign->getStartDate();
        if (!$startDate) {
            return 0;
        }
        
        $now = new \DateTime();
        $interval = $startDate->diff($now);
        $days = (int) $interval->format('%a');
        
        // Day 1 is the first day
        return $days + 1;
    }

    private function getEmailSequenceForDay(Campaign $campaign, int $day): ?array
    {
        $sequences = $campaign->getEmailSequences();
        
        if (empty($sequences)) {
            // Fallback to single email
            return [
                'subject' => $campaign->getSubjectTemplate() ?? 'Email from campaign',
                'body' => $campaign->getCustomMessage() ?? 'Hello {{first_name}}!',
                'sequence_day' => $day,
                'type' => 'default'
            ];
        }
        
        // Find sequence for this day
        foreach ($sequences as $sequence) {
            if (isset($sequence['day']) && $sequence['day'] == $day) {
                return [
                    'subject' => $sequence['subject'] ?? 'Email from campaign',
                    'body' => $sequence['body'] ?? 'Hello {{first_name}}!',
                    'sequence_day' => $day,
                    'type' => 'sequence'
                ];
            }
        }
        
        // If no exact match, use modulo (wrap around sequences)
        $totalSequences = count($sequences);
        $sequenceIndex = (($day - 1) % $totalSequences);
        
        if (isset($sequences[$sequenceIndex])) {
            $seq = $sequences[$sequenceIndex];
            return [
                'subject' => $seq['subject'] ?? 'Email from campaign',
                'body' => $seq['body'] ?? 'Hello {{first_name}}!',
                'sequence_day' => $day,
                'type' => 'sequence_wrapped'
            ];
        }
        
        return null;
    }

    private function calculateEmailsForToday(Campaign $campaign, int $campaignDay): int
    {
        // Get from warmup plan
        $warmupPlan = $campaign->getWarmupPlan();
        
        if (!empty($warmupPlan) && isset($warmupPlan[$campaignDay - 1])) {
            return $warmupPlan[$campaignDay - 1]['emails'] ?? 0;
        }
        
        // Calculate based on formula
        $totalContacts = $campaign->getTotalContacts();
        $durationDays = $campaign->getDurationDays();
        $startVolume = $campaign->getStartVolume();
        $dailyIncrement = $campaign->getDailyIncrement();
        
        if ($totalContacts === 0 || $durationDays === 0) {
            return 0;
        }
        
        // Simple arithmetic progression
        $emailsToday = $startVolume + (($campaignDay - 1) * $dailyIncrement);
        $maxPerDay = ceil($totalContacts / $durationDays);
        
        return min($emailsToday, $maxPerDay);
    }

    private function getContactsForToday(Campaign $campaign, int $limit): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('c')
            ->from(Contact::class, 'c')
            ->where('c.campaign = :campaign')
            ->andWhere('c.isActive = true')
            ->andWhere('c.emailsSent = 0') // Haven't received any email yet
            ->andWhere('c.status = :status')
            ->setParameter('campaign', $campaign)
            ->setParameter('status', 'pending')
            ->orderBy('c.id', 'ASC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    private function updateCampaignProgress(Campaign $campaign): void
    {
        // Calculate how many contacts have received emails
        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(c.id)')
            ->from(Contact::class, 'c')
            ->where('c.campaign = :campaign')
            ->andWhere('c.emailsSent > 0')
            ->setParameter('campaign', $campaign);
        
        $contactsWithEmails = $qb->getQuery()->getSingleScalarResult();
        $totalContacts = $campaign->getTotalContacts();
        
        if ($totalContacts > 0) {
            $progress = min(100, ($contactsWithEmails / $totalContacts) * 100);
            $campaign->setProgress($progress);
            
            // Check if campaign is completed
            if ($contactsWithEmails >= $totalContacts) {
                $campaign->setStatus('completed');
                $campaign->setCompletedAt(new \DateTime());
            }
            
            $campaign->setUpdatedAt(new \DateTime());
            $this->em->persist($campaign);
            $this->em->flush();
        }
    }

    private function updateStatistics(): void
    {
        // Update daily counters
        $this->em->getRepository(Campaign::class)
            ->createQueryBuilder('c')
            ->update()
            ->set('c.totalSentToday', 0)
            ->where('c.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->execute();
    }
}
