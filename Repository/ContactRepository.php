<?php

namespace MauticPlugin\MauticWarmUpBundle\Repository;

use Doctrine\ORM\EntityRepository;

class ContactRepository extends EntityRepository
{
    /**
     * Get entities with filtering
     */
    public function getEntities(array $args = []): array
    {
        $qb = $this->createQueryBuilder('c');
        
        // Join with campaign for filtering
        $qb->innerJoin('c.campaign', 'camp');
        
        // Apply filters
        if (!empty($args['filter'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('c.emailAddress', ':filter'),
                $qb->expr()->like('c.firstName', ':filter'),
                $qb->expr()->like('c.lastName', ':filter'),
                $qb->expr()->like('camp.campaignName', ':filter')
            ))
            ->setParameter('filter', '%' . $args['filter'] . '%');
        }
        
        if (!empty($args['status'])) {
            if ($args['status'] === 'active') {
                $qb->andWhere('c.isActive = :active')
                   ->setParameter('active', true);
            } elseif ($args['status'] === 'inactive') {
                $qb->andWhere('c.isActive = :active')
                   ->setParameter('active', false);
            }
        }
        
        if (!empty($args['campaign_id'])) {
            $qb->andWhere('c.campaign = :campaignId')
               ->setParameter('campaignId', $args['campaign_id']);
        }
        
        // Apply ordering
        if (!empty($args['orderBy'])) {
            $qb->orderBy('c.' . $args['orderBy'], $args['orderByDir'] ?? 'ASC');
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

    /**
     * Get contacts ready for next email
     */
    public function getContactsReadyForNextEmail(int $campaignId = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->andWhere('c.nextSendDate <= :now OR c.nextSendDate IS NULL')
            ->setParameter('active', true)
            ->setParameter('now', new \DateTime())
            ->orderBy('c.nextSendDate', 'ASC');
        
        if ($campaignId) {
            $qb->andWhere('c.campaign = :campaignId')
               ->setParameter('campaignId', $campaignId);
        }
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Get contacts by email domain
     */
    public function getContactsByDomain(string $domain): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.emailAddress LIKE :domain')
            ->setParameter('domain', '%@' . $domain)
            ->orderBy('c.emailAddress', 'ASC');
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Get duplicate emails in campaign
     */
    public function getDuplicateEmails(int $campaignId = null): array
    {
        $qb = $this->createQueryBuilder('c');
        
        $qb->select('c.emailAddress, COUNT(c.id) as duplicate_count')
           ->groupBy('c.emailAddress')
           ->having('duplicate_count > 1')
           ->orderBy('duplicate_count', 'DESC');
        
        if ($campaignId) {
            $qb->andWhere('c.campaign = :campaignId')
               ->setParameter('campaignId', $campaignId);
        }
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Find contact by email and campaign
     */
    public function findContactByEmailAndCampaign(string $email, int $campaignId): ?object
    {
        return $this->createQueryBuilder('c')
            ->where('c.emailAddress = :email')
            ->andWhere('c.campaign = :campaignId')
            ->setParameter('email', $email)
            ->setParameter('campaignId', $campaignId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get contact engagement metrics
     */
    public function getContactEngagement(int $contactId): array
    {
        $qb = $this->_em->createQueryBuilder();
        
        $qb->select('COUNT(l.id) as total_sent')
           ->addSelect('SUM(CASE WHEN l.status = :opened THEN 1 ELSE 0 END) as opened')
           ->addSelect('SUM(CASE WHEN l.status = :clicked THEN 1 ELSE 0 END) as clicked')
           ->addSelect('SUM(CASE WHEN l.status = :replied THEN 1 ELSE 0 END) as replied')
           ->from('MauticWarmUpBundle:SentLog', 'l')
           ->where('l.contact = :contactId')
           ->setParameter('contactId', $contactId)
           ->setParameter('opened', 'opened')
           ->setParameter('clicked', 'clicked')
           ->setParameter('replied', 'replied');
        
        $result = $qb->getQuery()->getSingleResult();
        
        $totalSent = (int) $result['total_sent'];
        $opened = (int) $result['opened'];
        $clicked = (int) $result['clicked'];
        $replied = (int) $result['replied'];
        
        $openRate = $totalSent > 0 ? ($opened / $totalSent) * 100 : 0;
        $clickRate = $totalSent > 0 ? ($clicked / $totalSent) * 100 : 0;
        $replyRate = $totalSent > 0 ? ($replied / $totalSent) * 100 : 0;
        
        return [
            'total_sent' => $totalSent,
            'opened' => $opened,
            'clicked' => $clicked,
            'replied' => $replied,
            'open_rate' => round($openRate, 2),
            'click_rate' => round($clickRate, 2),
            'reply_rate' => round($replyRate, 2),
            'engagement_score' => round(($openRate + $clickRate + ($replyRate * 2)) / 4, 2),
        ];
    }

    /**
     * Get contacts that haven't received emails recently
     */
    public function getInactiveContacts(\DateTime $sinceDate, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->andWhere('c.lastSent < :sinceDate OR c.lastSent IS NULL')
            ->setParameter('active', true)
            ->setParameter('sinceDate', $sinceDate)
            ->orderBy('c.lastSent', 'ASC')
            ->setMaxResults($limit);
        
        return $qb->getQuery()->getResult();
    }
}
