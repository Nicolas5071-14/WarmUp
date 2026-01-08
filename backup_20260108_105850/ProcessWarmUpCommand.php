<?php

namespace MauticPlugin\MauticWarmUpBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Service\EmailSenderService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;

class ProcessWarmUpCommand extends Command
{
    protected static $defaultName = 'mautic:warmup:process';

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
        $this
            ->setDescription('Process and send warmup campaign emails')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force execution regardless of time'
            )
            ->addOption(
                'campaign-id',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Process specific campaign by ID'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = $input->getOption('force');
        $campaignId = $input->getOption('campaign-id');

        // Définir le fuseau horaire du Canada (Toronto/Montreal)
        date_default_timezone_set('America/Toronto');

        $currentTime = new \DateTime('now', new \DateTimeZone('America/Toronto'));

        $output->writeln(sprintf(
            '<info>[%s] Starting warmup campaign processing...</info>',
            $currentTime->format('Y-m-d H:i:s')
        ));

        try {
            // Traiter les campagnes
            $processedCount = $this->emailSender->processCampaigns($campaignId, $force);

            // Mettre à jour les statistiques de domaines
            $this->updateDomainStatistics($currentTime);

            // Nettoyer les anciennes données (optionnel)
            $this->cleanupOldData();

            $output->writeln(sprintf(
                '<info>✓ Successfully processed %d campaigns</info>',
                $processedCount
            ));

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('<error>✗ Error: ' . $e->getMessage() . '</error>');
            $this->logger->error('Warmup processing failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Mettre à jour les statistiques des domaines
     */
    private function updateDomainStatistics(\DateTime $currentTime): void
    {
        // Réinitialiser les compteurs quotidiens à minuit
        if ($currentTime->format('H:i') === '00:00') {
            $this->logger->info('Resetting daily domain counters');

            $this->em->createQuery(
                'UPDATE MauticPlugin\MauticWarmUpBundle\Entity\Domain d 
                SET d.totalSentToday = 0'
            )->execute();

            $this->em->flush();
        }
    }

    /**
     * Nettoyer les anciennes données (logs > 90 jours)
     */
    private function cleanupOldData(): void
    {
        $cutoffDate = new \DateTime('-90 days');

        $deleted = $this->em->createQuery(
            'DELETE FROM MauticPlugin\MauticWarmUpBundle\Entity\SentLog l 
            WHERE l.createdAt < :cutoff'
        )
            ->setParameter('cutoff', $cutoffDate)
            ->execute();

        if ($deleted > 0) {
            $this->logger->info("Cleaned up {$deleted} old sent logs");
        }
    }
}