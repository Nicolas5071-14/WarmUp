<?php

namespace MauticPlugin\MauticWarmUpBundle\Repository;

use Doctrine\ORM\EntityRepository;
use MauticPlugin\MauticWarmUpBundle\Entity\Campaign;
use MauticPlugin\MauticWarmUpBundle\Entity\Domain;

class CampaignRepository extends EntityRepository
{
    /**
     * Get entities with filtering
     */
    public function getEntities(array $args = []): array
    {
        error_log('📁 CampaignRepository::getEntities called with args: ' . json_encode($args));

        // Sélectionnez uniquement les colonnes qui existent
        $qb = $this->createQueryBuilder('c')
            ->select('c.id, c.campaignName, c.description, c.status, c.totalContacts, 
                 c.emailsSent, c.dailyLimit, c.warmupDuration, c.startDate, 
                 c.createdAt, c.updatedAt, c.completedAt, c.domain, c.warmupType');

        // Appliquez les filtres
        if (!empty($args['filter'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('c.campaignName', ':filter'),
                $qb->expr()->like('c.description', ':filter')
            ))
                ->setParameter('filter', '%' . $args['filter'] . '%');
        }

        if (isset($args['status'])) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $args['status']);
        }

        if (isset($args['domain'])) {
            $qb->andWhere('c.domain = :domain')
                ->setParameter('domain', $args['domain']);
        }

        // Apply ordering
        if (!empty($args['orderBy'])) {
            $qb->orderBy('c.' . $args['orderBy'], $args['orderByDir'] ?? 'ASC');
        } else {
            $qb->orderBy('c.createdAt', 'DESC');
        }

        // Apply pagination
        if (isset($args['start'])) {
            $qb->setFirstResult($args['start']);
        }

        if (isset($args['limit'])) {
            $qb->setMaxResults($args['limit']);
        }

        $result = $qb->getQuery()->getResult();
        error_log('📁 CampaignRepository::getEntities returning ' . count($result) . ' results');

        return $result;
    }
    /**
     * Get active campaigns
     */
    public function findActiveCampaigns(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.startDate <= :now')
            ->setParameter('status', Campaign::STATUS_ACTIVE)
            ->setParameter('now', new \DateTime())
            ->orderBy('c.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get campaigns ready to send today
     */
    public function findCampaignsReadyToSend(): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('c')
            ->leftJoin('c.domain', 'd')
            ->where('c.status = :status')
            ->andWhere('c.startDate <= :now')
            ->andWhere('d.isActive = true')
            ->andWhere('d.isVerified = true')
            ->andWhere('d.totalSentToday < d.dailyLimit')
            ->setParameter('status', Campaign::STATUS_ACTIVE)
            ->setParameter('now', $now)
            ->orderBy('c.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get campaigns by status
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', $status)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total campaigns count
     */
    public function getTotalCount(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Check if campaign name exists
     */
    public function existsByCampaignName(string $campaignName, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
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
     * Get campaigns for a specific domain
     */
    public function findCampaignsForDomain(Domain $domain): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.domain = :domain')
            ->setParameter('domain', $domain)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get active campaigns count for domain
     */
    public function getActiveCampaignsCountForDomain(Domain $domain): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.domain = :domain')
            ->andWhere('c.status = :status')
            ->setParameter('domain', $domain)
            ->setParameter('status', Campaign::STATUS_ACTIVE);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}