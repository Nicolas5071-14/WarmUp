<?php

namespace MauticPlugin\MauticWarmUpBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Domain;
use MauticPlugin\MauticWarmUpBundle\Repository\DomainRepository;

class DomainModel
{
    private EntityManagerInterface $em;
    private DomainRepository $repository;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->repository = $em->getRepository(Domain::class);
    }

    /**
     * Get entity by ID
     */
    public function getEntity(int $id = null): ?Domain
    {
        if ($id === null) {
            return new Domain();
        }

        return $this->repository->find($id);
    }

    /**
     * Save domain entity
     */
    public function saveEntity(Domain $domain): void
    {
        $isNew = $domain->getId() === null;

        // Set updated timestamp
        $domain->setUpdatedAt(new \DateTime());

        // If new, set created timestamp
        if ($isNew) {
            $domain->setCreatedAt(new \DateTime());
        }

        $this->em->persist($domain);
        $this->em->flush();
    }

    /**
     * Delete domain entity
     */
    public function deleteEntity(Domain $domain): void
    {
        $this->em->remove($domain);
        $this->em->flush();
    }

    /**
     * Get active domains
     */
    public function getActiveDomains(): array
    {
        return $this->repository->findBy(
            ['isActive' => true],
            ['domainName' => 'ASC']
        );
    }

    /**
     * Get verified domains
     */
    public function getVerifiedDomains(): array
    {
        return $this->repository->findBy(
            ['isVerified' => true],
            ['domainName' => 'ASC']
        );
    }

    /**
     * Verify SMTP settings for domain
     */
    public function verifySmtp(Domain $domain): bool
    {
        if (!$domain->getSmtpHost() || !$domain->getSmtpUsername() || !$domain->getSmtpPassword()) {
            throw new \Exception('SMTP settings are incomplete');
        }

        try {
            // Test SMTP connection
            $transport = new \Swift_SmtpTransport(
                $domain->getSmtpHost(),
                $domain->getSmtpPort(),
                $domain->getSmtpEncryption()
            );

            $transport->setUsername($domain->getSmtpUsername());
            $transport->setPassword($domain->getSmtpPassword());
            $transport->setTimeout(10);

            $mailer = new \Swift_Mailer($transport);
            $mailer->getTransport()->start();

            // Mark as verified
            $domain->setIsVerified(true);
            $domain->setVerificationDate(new \DateTime());
            $this->saveEntity($domain);

            return true;

        } catch (\Exception $e) {
            $domain->setIsVerified(false);
            $this->saveEntity($domain);
            throw new \Exception('SMTP verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Start warm-up for domain
     */
    public function startWarmUp(Domain $domain): void
    {
        if (!$domain->isVerified()) {
            throw new \Exception('Domain must be verified before starting warm-up');
        }

        $domain->setWarmupStartDate(new \DateTime());
        $domain->setCurrentPhaseDay(1);
        $domain->setTotalSentToday(0);
        $domain->setIsActive(true);

        // Calculate end date (default 30 days)
        $endDate = (new \DateTime())->modify('+30 days');
        $domain->setWarmupEndDate($endDate);

        $this->saveEntity($domain);
    }

    /**
     * Pause warm-up for domain
     */
    public function pauseWarmUp(Domain $domain): void
    {
        $domain->setIsActive(false);
        $this->saveEntity($domain);
    }

    /**
     * Reset domain daily counters
     */
    public function resetDailyCounters(): void
    {
        $domains = $this->getActiveDomains();

        foreach ($domains as $domain) {
            // Check if it's a new day
            $lastReset = $domain->getUpdatedAt();
            $now = new \DateTime();

            if ($lastReset->format('Y-m-d') !== $now->format('Y-m-d')) {
                $domain->setTotalSentToday(0);
                $domain->setUpdatedAt($now);
                $this->em->persist($domain);
            }
        }

        $this->em->flush();
    }

    /**
     * Get domain statistics
     */
    public function getStatistics(Domain $domain): array
    {
        return [
            'total_sent' => $this->repository->getTotalSent($domain),
            'sent_today' => $domain->getTotalSentToday(),
            'remaining_today' => $domain->getRemainingSendsToday(),
            'daily_limit' => $domain->getDailyLimit(),
            'warmup_progress' => $this->calculateWarmupProgress($domain),
            'campaigns_count' => $this->repository->getCampaignsCount($domain),
            'active_campaigns_count' => $this->repository->getActiveCampaignsCount($domain),
            'delivery_rate' => $this->repository->getDeliveryRate($domain),
            'bounce_rate' => $this->repository->getBounceRate($domain),
            'open_rate' => $this->repository->getOpenRate($domain),
            'click_rate' => $this->repository->getClickRate($domain),
            'spam_complaints' => $this->repository->getSpamComplaints($domain),
        ];
    }

    /**
     * Calculate warm-up progress percentage
     */
    private function calculateWarmupProgress(Domain $domain): float
    {
        if (!$domain->getWarmupStartDate() || !$domain->getWarmupEndDate()) {
            return 0;
        }

        $totalDays = $domain->getWarmupStartDate()->diff($domain->getWarmupEndDate())->days;
        $daysPassed = $domain->getWarmupStartDate()->diff(new \DateTime())->days;

        if ($totalDays <= 0) {
            return 100;
        }

        return min(100, ($daysPassed / $totalDays) * 100);
    }

    /**
     * Get domains with available sending capacity
     */
    public function getDomainsWithCapacity(int $requiredCount = 1): array
    {
        return $this->repository->getEntities([
            'isActive' => true,
            'isVerified' => true,
            // Additional filtering for capacity would be needed here
        ]);
    }

    /**
     * Get domain performance metrics
     */
    public function getPerformanceMetrics(Domain $domain, \DateTime $fromDate = null, \DateTime $toDate = null): array
    {
        if (!$fromDate) {
            $fromDate = (new \DateTime())->modify('-30 days');
        }

        if (!$toDate) {
            $toDate = new \DateTime();
        }

        return [
            'delivery_rate' => $this->repository->getDeliveryRate($domain, $fromDate, $toDate),
            'open_rate' => $this->repository->getOpenRate($domain, $fromDate, $toDate),
            'click_rate' => $this->repository->getClickRate($domain, $fromDate, $toDate),
            'bounce_rate' => $this->repository->getBounceRate($domain, $fromDate, $toDate),
            'spam_complaints' => $this->repository->getSpamComplaints($domain, $fromDate, $toDate),
            'total_sent' => $this->repository->getTotalSentInPeriod($domain, $fromDate, $toDate),
        ];
    }

    public function checkIfDomainExists(string $domainName, ?int $excludeId = null): bool
    {
        return $this->repository->existsByDomainName($domainName, $excludeId);
    }

    /**
     * Get entities as array (for forms and API).
     */
    public function getEntities(array $args = []): array
    {
        error_log('🧠 DomainModel::getEntities called');
        error_log('🧠 Args: ' . json_encode($args));

        // Use the repository's getEntities method
        $entities = $this->repository->getEntities($args);

        error_log('🧠 Repository entities count: ' . count($entities));

        $result = [];

        foreach ($entities as $domain) {
            error_log('🧠 Domain entity ID: ' . $domain->getId());

            $result[] = [
                'id' => $domain->getId(),
                'domainName' => $domain->getDomainName(),
                'emailPrefix' => $domain->getEmailPrefix(),
                'dailyLimit' => $domain->getDailyLimit(),
                'totalSentToday' => $domain->getTotalSentToday(),
                'remainingSendsToday' => $domain->getRemainingSendsToday(),
                'isActive' => $domain->isActive(),
                'isVerified' => $domain->isVerified(),
                'currentPhaseDay' => $domain->getCurrentPhaseDay(),
                'createdAt' => $domain->getCreatedAt(),
                'updatedAt' => $domain->getUpdatedAt(),
                'smtpHost' => $domain->getSmtpHost(),
                'warmupPhase' => $domain->getWarmupPhase(),
            ];
        }

        error_log('🧠 Final array count: ' . count($result));

        return $result;
    }

    /**
     * Get available domains for sending
     */
    public function getAvailableDomains(): array
    {
        $domains = $this->getActiveDomains();
        $result = [];

        foreach ($domains as $domain) {
            if ($domain->isVerified() && $domain->canSendMoreToday()) {
                $result[] = [
                    'id' => $domain->getId(),
                    'domainName' => $domain->getDomainName(),
                    'emailPrefix' => $domain->getEmailPrefix(),
                    'dailyLimit' => $domain->getDailyLimit(),
                    'totalSentToday' => $domain->getTotalSentToday(),
                    'remainingSendsToday' => $domain->getRemainingSendsToday(),
                    'warmupProgress' => $this->calculateWarmupProgress($domain),
                    'isActive' => $domain->isActive(),
                    'isVerified' => $domain->isVerified(),
                ];
            }
        }

        return $result;
    }

    /**
     * Toggle domain active status
     */
    public function toggleActive(Domain $domain): Domain
    {
        $domain->setIsActive(!$domain->isActive());
        $this->saveEntity($domain);

        return $domain;
    }

    /**
     * Get total count of domains
     */
    public function getTotalCount(): int
    {
        return (int) $this->repository->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get active domains count
     */
    public function getActiveCount(): int
    {
        return (int) $this->repository->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get verified domains count
     */
    public function getVerifiedCount(): int
    {
        return (int) $this->repository->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.isVerified = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find domains by criteria
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null): array
    {
        return $this->repository->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Find one domain by criteria
     */
    public function findOneBy(array $criteria, array $orderBy = null): ?Domain
    {
        return $this->repository->findOneBy($criteria, $orderBy);
    }

    /**
     * Search domains
     */
    public function searchDomains(string $searchTerm, int $limit = 50): array
    {
        $query = $this->repository->createQueryBuilder('d')
            ->where('d.domainName LIKE :search')
            ->orWhere('d.emailPrefix LIKE :search')
            ->orWhere('d.smtpHost LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Get domains ready for sending (active, verified, has capacity)
     */
    public function getReadyDomains(): array
    {
        return $this->repository->createQueryBuilder('d')
            ->where('d.isActive = true')
            ->andWhere('d.isVerified = true')
            ->andWhere('d.totalSentToday < d.dailyLimit')
            ->andWhere('d.smtpHost IS NOT NULL')
            ->andWhere('d.smtpUsername IS NOT NULL')
            ->andWhere('d.smtpPassword IS NOT NULL')
            ->orderBy('d.remainingSendsToday', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Increment sent count for domain
     */
    public function incrementSentCount(Domain $domain, int $count = 1): void
    {
        $domain->setTotalSentToday($domain->getTotalSentToday() + $count);
        $domain->setUpdatedAt(new \DateTime());
        $this->em->persist($domain);
        $this->em->flush();
    }

    /**
     * Get domain usage statistics for dashboard
     */
    public function getDashboardStats(): array
    {
        $qb = $this->repository->createQueryBuilder('d');

        // Total domains
        $total = (int) $qb->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Active domains
        $active = (int) $qb->select('COUNT(d.id)')
            ->where('d.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();

        // Verified domains
        $verified = (int) $qb->select('COUNT(d.id)')
            ->where('d.isVerified = true')
            ->getQuery()
            ->getSingleScalarResult();

        // Total sent today
        $sentToday = (int) $qb->select('SUM(d.totalSentToday)')
            ->getQuery()
            ->getSingleScalarResult();

        // Total daily capacity
        $dailyCapacity = (int) $qb->select('SUM(d.dailyLimit)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_domains' => $total,
            'active_domains' => $active,
            'verified_domains' => $verified,
            'sent_today' => $sentToday,
            'daily_capacity' => $dailyCapacity,
            'remaining_capacity' => max(0, $dailyCapacity - $sentToday),
            'utilization_rate' => $dailyCapacity > 0 ? ($sentToday / $dailyCapacity * 100) : 0,
        ];
    }
}