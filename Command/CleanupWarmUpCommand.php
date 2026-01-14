<?php
// src/Command/CleanupWarmUpCommand.php

namespace MauticPlugin\MauticWarmUpBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Campaign;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupWarmUpCommand extends Command
{
    protected static $defaultName = 'mautic:warmup:cleanup';
    protected static $defaultDescription = 'Cleanup old warmup campaign data';

    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->em = $em;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting warmup cleanup...');
        $this->logger->info('Starting warmup cleanup');

        try {
            // Archive completed campaigns older than 30 days
            $thirtyDaysAgo = new \DateTime('-30 days');
            
            $qb = $this->em->createQueryBuilder();
            $qb->select('c')
                ->from(Campaign::class, 'c')
                ->where('c.status = :status')
                ->andWhere('c.updatedAt < :date')
                ->setParameter('status', 'completed')
                ->setParameter('date', $thirtyDaysAgo);

            $oldCampaigns = $qb->getQuery()->getResult();
            
            foreach ($oldCampaigns as $campaign) {
                $campaign->setIsArchived(true);
                $this->em->persist($campaign);
                $output->writeln(sprintf('Archived campaign: %s', $campaign->getCampaignName()));
            }

            // Delete failed contacts older than 90 days
            $ninetyDaysAgo = new \DateTime('-90 days');
            
            $qb = $this->em->createQueryBuilder();
            $qb->delete('MauticPlugin\MauticWarmUpBundle\Entity\Contact', 'c')
                ->where('c.status = :status')
                ->andWhere('c.updatedAt < :date')
                ->setParameter('status', 'failed')
                ->setParameter('date', $ninetyDaysAgo);

            $deletedCount = $qb->getQuery()->execute();
            $output->writeln(sprintf('Deleted %d failed contacts', $deletedCount));

            // Optimize database
            $this->em->flush();

            $output->writeln('Cleanup completed successfully');
            $this->logger->info('Cleanup completed', [
                'campaigns_archived' => count($oldCampaigns),
                'contacts_deleted' => $deletedCount
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln(sprintf('Error during cleanup: %s', $e->getMessage()));
            $this->logger->error('Error during cleanup', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
