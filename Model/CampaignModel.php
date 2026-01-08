<?php

declare(strict_types=1);

namespace MauticPlugin\MauticWarmUpBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Campaign;
use MauticPlugin\MauticWarmUpBundle\Entity\Contact;
use MauticPlugin\MauticWarmUpBundle\Entity\Sequence;
use MauticPlugin\MauticWarmUpBundle\Entity\Domain;

class CampaignModel
{
    private EntityManagerInterface $em;
    private $repository; // Pas de type strict

    public function __construct(EntityManagerInterface $em)
    {
        error_log('🧠 CampaignModel::__construct called');
        $this->em = $em;
        $this->repository = $em->getRepository(Campaign::class);

        // Vérifiez que le repository a bien la méthode getEntities
        if (!method_exists($this->repository, 'getEntities')) {
            error_log('❌ CampaignRepository does not have getEntities method!');
            error_log('❌ Repository class: ' . get_class($this->repository));

            // Vérifiez les méthodes disponibles
            $methods = get_class_methods($this->repository);
            error_log('❌ Available methods: ' . implode(', ', $methods));
        } else {
            error_log('✅ CampaignRepository has getEntities method');
        }
    }

    /**
     * Get entities with filtering
     */
    public function getEntities(array $args = []): array
    {
        error_log('🧠 CampaignModel::getEntities called with args: ' . json_encode($args));

        // Vérifiez si le repository existe
        if (!$this->repository) {
            error_log('❌ Repository not initialized!');
            return [];
        }

        // Vérifiez si la méthode existe
        if (!method_exists($this->repository, 'getEntities')) {
            error_log('❌ getEntities method does not exist in repository!');
            error_log('❌ Repository class: ' . get_class($this->repository));

            // Essayez d'utiliser la méthode findBy à la place
            $filters = [];
            if (!empty($args['status'])) {
                $filters['status'] = $args['status'];
            }
            if (!empty($args['orderBy'])) {
                $orderBy = [$args['orderBy'] => $args['orderByDir'] ?? 'ASC'];
            } else {
                $orderBy = ['createdAt' => 'DESC'];
            }

            $limit = $args['limit'] ?? null;
            $offset = $args['start'] ?? null;

            $result = $this->repository->findBy($filters, $orderBy, $limit, $offset);
            error_log('🧠 Using findBy, found ' . count($result) . ' results');
            return $result;
        }

        try {
            $result = $this->repository->getEntities($args);
            error_log('🧠 Repository getEntities returned ' . count($result) . ' results');
            return $result;
        } catch (\Exception $e) {
            error_log('❌ Error calling getEntities: ' . $e->getMessage());
            return [];
        }
    }
    /**
     * Save campaign entity
     */
    public function saveEntity(Campaign $campaign): void
    {
        $isNew = $campaign->getId() === null;

        // Set updated timestamp
        $campaign->setUpdatedAt(new \DateTime());

        // If new, set created timestamp
        if ($isNew) {
            $campaign->setCreatedAt(new \DateTime());
        }

        $this->em->persist($campaign);
        $this->em->flush();
    }

    /**
     * Delete campaign entity
     */
    public function deleteEntity(Campaign $campaign): void
    {
        $this->em->remove($campaign);
        $this->em->flush();
    }

    /**
     * Create campaign from wizard data
     */
    public function createCampaignFromWizard(array $wizardData): Campaign
    {
        $session = $this->requestStack->getSession();

        $campaign = new Campaign();

        // Set basic info from step1
        if (isset($wizardData['step1'])) {
            $step1 = $wizardData['step1'];
            $campaign->setCampaignName($step1['campaignName'] ?? 'New Campaign');
            $campaign->setDescription($step1['description'] ?? '');
            $campaign->setWarmupType($step1['warmupType'] ?? 1);

            // Convert string to DateTime for startDate
            $startDate = $step1['startDate'] ?? 'now';
            if (is_string($startDate)) {
                $startDate = new \DateTime($startDate);
            }
            $campaign->setStartDate($startDate);
        }

        // Set domain from step3
        if (isset($wizardData['step3']) && isset($wizardData['step3']['domainId'])) {
            $domainId = $wizardData['step3']['domainId'];
            if ($domainId) {
                $domain = $this->em->getRepository(Domain::class)->find($domainId);
                if ($domain) {
                    $campaign->setDomain($domain);
                }
            }
        }

        // Set warmup settings from step4
        if (isset($wizardData['step4'])) {
            $step4 = $wizardData['step4'];
            $campaign->setDailyLimit($step4['dailyLimit'] ?? 100);
            $campaign->setWarmupDuration($step4['warmupDuration'] ?? 30);
        }

        // Set initial status
        $campaign->setStatus(Campaign::STATUS_DRAFT);
        $campaign->setTotalContacts(count($wizardData['contacts'] ?? []));
        $campaign->setEmailsSent(0);
        $campaign->setCreatedAt(new \DateTime());
        $campaign->setUpdatedAt(new \DateTime());

        // Save campaign
        $this->em->persist($campaign);
        $this->em->flush();

        // Create sequences from step5
        if (isset($wizardData['step5']) && isset($wizardData['step5']['sequences'])) {
            $sequences = $wizardData['step5']['sequences'];
            $order = 1;

            foreach ($sequences as $sequenceData) {
                if (is_array($sequenceData)) {
                    $sequence = new Sequence();
                    $sequence->setCampaign($campaign);
                    $sequence->setSequenceName($sequenceData['name'] ?? "Sequence {$order}");
                    $sequence->setSequenceOrder($order);
                    $sequence->setDaysAfterPrevious($sequenceData['daysAfterPrevious'] ?? 2);
                    $sequence->setSubjectTemplate($sequenceData['subject'] ?? '');
                    $sequence->setBodyTemplate($sequenceData['content'] ?? '');
                    $sequence->setIsActive(true);
                    $sequence->setCreatedAt(new \DateTime());

                    $this->em->persist($sequence);
                    $order++;
                }
            }
        }

        // Create contacts
        $contacts = $wizardData['contacts'] ?? [];
        foreach ($contacts as $contactData) {
            if (is_array($contactData) && !empty($contactData['email'])) {
                $contact = new Contact();
                $contact->setCampaign($campaign);
                $contact->setEmailAddress($contactData['email']);
                $contact->setFirstName($contactData['firstName'] ?? '');
                $contact->setLastName($contactData['lastName'] ?? '');
                $contact->setSequenceDay(1);
                $contact->setDaysBetweenEmails(2);

                // Calculate next send date based on campaign start date
                $nextSendDate = clone $campaign->getStartDate();
                $contact->setNextSendDate($nextSendDate);

                $contact->setIsActive(true);
                $contact->setUnsubscribeToken(bin2hex(random_bytes(32)));
                $contact->setCreatedAt(new \DateTime());

                $this->em->persist($contact);
            }
        }

        // Update contact count
        $campaign->setTotalContacts(count($contacts));

        // Final save
        $this->em->flush();

        return $campaign;
    }

    /**
     * Get available domains for campaigns
     */
    public function getAvailableDomains(): array
    {
        $domains = $this->em->getRepository(Domain::class)->findBy(
            ['isActive' => true, 'isVerified' => true],
            ['domainName' => 'ASC']
        );

        $result = [];
        foreach ($domains as $domain) {
            $result[] = [
                'id' => $domain->getId(),
                'domainName' => $domain->getDomainName(),
                'emailPrefix' => $domain->getEmailPrefix(),
                'dailyLimit' => $domain->getDailyLimit(),
                'totalSentToday' => $domain->getTotalSentToday(),
                'remainingSendsToday' => $domain->getRemainingSendsToday(),
                'isActive' => $domain->isActive(),
                'isVerified' => $domain->isVerified(),
            ];
        }

        return $result;
    }

    /**
     * Get warmup types
     */
    public function getWarmupTypes(): array
    {
        return [
            1 => ['name' => 'Arithmetic', 'description' => 'Linear increase each day'],
            2 => ['name' => 'Geometric', 'description' => 'Exponential growth pattern'],
            3 => ['name' => 'Flat', 'description' => 'Constant volume then ramp up'],
            4 => ['name' => 'Progressive', 'description' => 'Volume based on success rate'],
            5 => ['name' => 'Randomize', 'description' => 'Random variation within limits']
        ];
    }

    /**
     * Start a campaign
     */
    public function startCampaign(Campaign $campaign): void
    {
        if (!$campaign->getDomain()) {
            throw new \Exception('Campaign must have a domain assigned');
        }

        if (!$campaign->getDomain()->isVerified()) {
            throw new \Exception('Domain must be verified before starting campaign');
        }

        if (!$campaign->getStartDate()) {
            throw new \Exception('Campaign start date is required');
        }

        $campaign->setStatus(Campaign::STATUS_ACTIVE);
        $campaign->setUpdatedAt(new \DateTime());
        $this->saveEntity($campaign);
    }

    /**
     * Pause a campaign
     */
    public function pauseCampaign(Campaign $campaign): void
    {
        $campaign->setStatus(Campaign::STATUS_PAUSED);
        $campaign->setUpdatedAt(new \DateTime());
        $this->saveEntity($campaign);
    }

    /**
     * Resume a campaign
     */
    public function resumeCampaign(Campaign $campaign): void
    {
        $campaign->setStatus(Campaign::STATUS_ACTIVE);
        $campaign->setUpdatedAt(new \DateTime());
        $this->saveEntity($campaign);
    }

    /**
     * Complete a campaign
     */
    public function completeCampaign(Campaign $campaign): void
    {
        $campaign->setStatus(Campaign::STATUS_COMPLETED);
        $campaign->setCompletedAt(new \DateTime());
        $campaign->setUpdatedAt(new \DateTime());
        $this->saveEntity($campaign);
    }

    /**
     * Get active campaigns
     */
    public function getActiveCampaigns(): array
    {
        return $this->repository->findActiveCampaigns();
    }

    /**
     * Get draft campaigns
     */
    public function getDraftCampaigns(): array
    {
        return $this->repository->findByStatus(Campaign::STATUS_DRAFT);
    }

    /**
     * Get paused campaigns
     */
    public function getPausedCampaigns(): array
    {
        return $this->repository->findByStatus(Campaign::STATUS_PAUSED);
    }

    /**
     * Get completed campaigns
     */
    public function getCompletedCampaigns(): array
    {
        return $this->repository->findByStatus(Campaign::STATUS_COMPLETED);
    }

    /**
     * Get campaigns ready to send today
     */
    public function getCampaignsReadyToSend(): array
    {
        return $this->repository->findCampaignsReadyToSend();
    }


    public function getTotalCount(): int
    {
        return $this->repository->getTotalCount();
    }

    /**
     * Get active campaigns count
     */
    public function getActiveCount(): int
    {
        return count($this->getActiveCampaigns());
    }

    /**
     * Get campaigns by status
     */
    public function getCampaignsByStatus(string $status): array
    {
        return $this->repository->findByStatus($status);
    }

    /**
     * Check if campaign name exists
     */
    public function checkIfCampaignExists(string $campaignName, ?int $excludeId = null): bool
    {
        $qb = $this->repository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.campaignName = :name')
            ->setParameter('name', $campaignName);

        if ($excludeId !== null) {
            $qb->andWhere('c.id != :id')
                ->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Increment email sent count for campaign
     */
    public function incrementSentCount(Campaign $campaign, int $count = 1): void
    {
        $campaign->setEmailsSent($campaign->getEmailsSent() + $count);
        $campaign->setUpdatedAt(new \DateTime());
        $this->em->persist($campaign);
        $this->em->flush();
    }

    /**
     * Increment delivery stats for campaign
     */
    public function incrementDeliveryStats(Campaign $campaign, array $stats): void
    {
        if (isset($stats['delivered']) && $stats['delivered']) {
            $campaign->incrementEmailsDelivered();
        }

        if (isset($stats['opened']) && $stats['opened']) {
            $campaign->incrementEmailsOpened();
        }

        if (isset($stats['clicked']) && $stats['clicked']) {
            $campaign->incrementEmailsClicked();
        }

        if (isset($stats['bounced']) && $stats['bounced']) {
            $campaign->incrementEmailsBounced();
        }

        $campaign->setUpdatedAt(new \DateTime());
        $this->em->persist($campaign);
        $this->em->flush();
    }

    /**
     * Get campaign statistics
     */
    public function getStatistics(Campaign $campaign): array
    {
        return $campaign->getStatistics();
    }

    /**
     * Get campaign performance metrics
     */
    public function getPerformanceMetrics(Campaign $campaign, \DateTime $fromDate = null, \DateTime $toDate = null): array
    {
        // You would implement this based on your SentLog entity
        return [
            'total_sent' => $campaign->getEmailsSent(),
            'delivery_rate' => $campaign->getDeliveryRate(),
            'open_rate' => $campaign->getOpenRate(),
            'click_rate' => $campaign->getClickRate(),
            'bounce_rate' => $campaign->getBounceRate(),
            'progress' => $campaign->getProgress(),
            'remaining_emails' => $campaign->getRemainingEmails(),
            'estimated_completion' => $campaign->getEstimatedCompletionDate() ?
                $campaign->getEstimatedCompletionDate()->format('Y-m-d H:i:s') : null,
        ];
    }

    /**
     * Get campaign dashboard statistics
     */
    public function getDashboardStats(): array
    {
        $qb = $this->repository->createQueryBuilder('c');

        // Total campaigns
        $total = (int) $qb->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Active campaigns
        $active = (int) $qb->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', Campaign::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        // Draft campaigns
        $draft = (int) $qb->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', Campaign::STATUS_DRAFT)
            ->getQuery()
            ->getSingleScalarResult();

        // Completed campaigns
        $completed = (int) $qb->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', Campaign::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        // Total emails sent
        $totalSent = (int) $qb->select('SUM(c.emailsSent)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_campaigns' => $total,
            'active_campaigns' => $active,
            'draft_campaigns' => $draft,
            'completed_campaigns' => $completed,
            'total_emails_sent' => $totalSent,
            'campaigns_by_status' => [
                'active' => $active,
                'draft' => $draft,
                'completed' => $completed,
                'paused' => $total - ($active + $draft + $completed),
            ],
        ];
    }

    /**
     * Search campaigns
     */
    public function searchCampaigns(string $searchTerm, int $limit = 50): array
    {
        $query = $this->repository->createQueryBuilder('c')
            ->where('c.campaignName LIKE :search')
            ->orWhere('c.description LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Get campaigns for a specific domain
     */
    public function getCampaignsForDomain(Domain $domain): array
    {
        return $this->repository->createQueryBuilder('c')
            ->where('c.domain = :domain')
            ->setParameter('domain', $domain)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Validate campaign data
     */
    public function validateCampaignData(array $data): array
    {
        $errors = [];

        if (empty($data['campaignName'])) {
            $errors[] = 'Campaign name is required';
        }

        if (empty($data['startDate'])) {
            $errors[] = 'Start date is required';
        }

        if (empty($data['domainId'])) {
            $errors[] = 'Domain is required';
        }

        if (empty($data['warmupType'])) {
            $errors[] = 'Warm-up type is required';
        }

        if (empty($data['contacts']) || !is_array($data['contacts']) || count($data['contacts']) === 0) {
            $errors[] = 'At least one contact is required';
        }

        if (empty($data['sequences']) || !is_array($data['sequences']) || count($data['sequences']) === 0) {
            $errors[] = 'At least one sequence is required';
        }

        return $errors;
    }
}