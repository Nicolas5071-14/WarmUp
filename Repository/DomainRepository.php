<?php

namespace MauticPlugin\MauticWarmUpBundle\Repository;

use Doctrine\ORM\EntityRepository;
use MauticPlugin\MauticWarmUpBundle\Entity\Domain;

class DomainRepository extends EntityRepository
{
    /**
     * Get entities with filtering
     */
    public function getEntities(array $args = []): array
    {
        $qb = $this->createQueryBuilder('d');

        // Apply filters
        if (!empty($args['filter'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('d.domainName', ':filter'),
                $qb->expr()->like('d.emailPrefix', ':filter')
            ))
                ->setParameter('filter', '%' . $args['filter'] . '%');
        }

        if (isset($args['isActive'])) {
            $qb->andWhere('d.isActive = :isActive')
                ->setParameter('isActive', $args['isActive']);
        }

        if (isset($args['isVerified'])) {
            $qb->andWhere('d.isVerified = :isVerified')
                ->setParameter('isVerified', $args['isVerified']);
        }

        // Apply ordering
        if (!empty($args['orderBy'])) {
            $qb->orderBy('d.' . $args['orderBy'], $args['orderByDir'] ?? 'ASC');
        }

        // Apply pagination
        if (isset($args['start'])) {
            $qb->setFirstResult($args['start']);
        }

        if (isset($args['limit'])) {
            $qb->setMaxResults($args['limit']);
        }

        return $qb->getQuery()->getResult();
    }
    public function existsByDomainName(string $domainName, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.domainName = :domain')
            ->setParameter('domain', $domainName);

        if ($excludeId !== null) {
            $qb->andWhere('d.id != :id')
                ->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Get total emails sent by domain
     */
    public function getTotalSent(Domain $domain): int
    {
        $qb = $this->createQueryBuilder('d');

        $qb->select('SUM(d.totalSentToday) as total_sent')
            ->where('d.id = :domainId')
            ->setParameter('domainId', $domain->getId());

        $result = $qb->getQuery()->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Get campaigns count for domain
     */
    public function getCampaignsCount(Domain $domain): int
    {
        $qb = $this->_em->createQueryBuilder();

        $qb->select('COUNT(c.id) as campaign_count')
            ->from('MauticWarmUpBundle:Campaign', 'c')
            ->where('c.domain = :domain')
            ->setParameter('domain', $domain);

        $result = $qb->getQuery()->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Get active campaigns count for domain
     */
    public function getActiveCampaignsCount(Domain $domain): int
    {
        $qb = $this->_em->createQueryBuilder();

        $qb->select('COUNT(c.id) as active_campaigns')
            ->from('MauticWarmUpBundle:Campaign', 'c')
            ->where('c.domain = :domain')
            ->andWhere('c.status = :active')
            ->setParameter('domain', $domain)
            ->setParameter('active', 'active');

        $result = $qb->getQuery()->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Get delivery rate for domain
     */
    public function getDeliveryRate(Domain $domain, \DateTime $fromDate = null, \DateTime $toDate = null): float
    {
        $qb = $this->_em->createQueryBuilder();

        $qb->select('COUNT(l.id) as total_sent')
            ->addSelect('SUM(CASE WHEN l.status = :delivered THEN 1 ELSE 0 END) as delivered')
            ->from('MauticWarmUpBundle:SentLog', 'l')
            ->where('l.domain = :domain')
            ->setParameter('domain', $domain)
            ->setParameter('delivered', 'delivered');

        if ($fromDate) {
            $qb->andWhere('l.sendTime >= :fromDate')
                ->setParameter('fromDate', $fromDate);
        }

        if ($toDate) {
            $qb->andWhere('l.sendTime <= :toDate')
                ->setParameter('toDate', $toDate);
        }

        $result = $qb->getQuery()->getSingleResult();

        if ($result['total_sent'] > 0) {
            return ($result['delivered'] / $result['total_sent']) * 100;
        }

        return 0;
    }

    /**
     * Get open rate for domain
     */
    public function getOpenRate(Domain $domain, \DateTime $fromDate = null, \DateTime $toDate = null): float
    {
        // Implementation depends on your tracking setup
        // This would query your email tracking data

        return 0.0;
    }

    /**
     * Get click rate for domain
     */
    public function getClickRate(Domain $domain, \DateTime $fromDate = null, \DateTime $toDate = null): float
    {
        // Implementation depends on your tracking setup
        // This would query your click tracking data

        return 0.0;
    }

    /**
     * Get bounce rate for domain
     */
    public function getBounceRate(Domain $domain, \DateTime $fromDate = null, \DateTime $toDate = null): float
    {
        $qb = $this->_em->createQueryBuilder();

        $qb->select('COUNT(l.id) as total_sent')
            ->addSelect('SUM(CASE WHEN l.status = :bounced THEN 1 ELSE 0 END) as bounced')
            ->from('MauticWarmUpBundle:SentLog', 'l')
            ->where('l.domain = :domain')
            ->setParameter('domain', $domain)
            ->setParameter('bounced', 'bounced');

        if ($fromDate) {
            $qb->andWhere('l.sendTime >= :fromDate')
                ->setParameter('fromDate', $fromDate);
        }

        if ($toDate) {
            $qb->andWhere('l.sendTime <= :toDate')
                ->setParameter('toDate', $toDate);
        }

        $result = $qb->getQuery()->getSingleResult();

        if ($result['total_sent'] > 0) {
            return ($result['bounced'] / $result['total_sent']) * 100;
        }

        return 0;
    }

    /**
     * Get spam complaints for domain
     */
    public function getSpamComplaints(Domain $domain, \DateTime $fromDate = null, \DateTime $toDate = null): int
    {
        // Implementation depends on your complaint tracking
        // This would query your spam complaint data

        return 0;
    }

    /**
     * Get total sent in period
     */
    public function getTotalSentInPeriod(Domain $domain, \DateTime $fromDate = null, \DateTime $toDate = null): int
    {
        $qb = $this->_em->createQueryBuilder();

        $qb->select('COUNT(l.id) as total_sent')
            ->from('MauticWarmUpBundle:SentLog', 'l')
            ->where('l.domain = :domain')
            ->setParameter('domain', $domain);

        if ($fromDate) {
            $qb->andWhere('l.sendTime >= :fromDate')
                ->setParameter('fromDate', $fromDate);
        }

        if ($toDate) {
            $qb->andWhere('l.sendTime <= :toDate')
                ->setParameter('toDate', $toDate);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return (int) $result;
    }
}
