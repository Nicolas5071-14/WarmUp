<?php

namespace MauticPlugin\MauticWarmUpBundle\Entity;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * Class WarmupContactRepository
 * 
 * @package MauticPlugin\MauticWarmUpBundle\Entity
 */
class WarmupContactRepository extends CommonRepository
{
    /**
     * Get contacts by campaign
     *
     * @param int $campaignId
     * @param array $options
     * @return array
     */
    public function getContactsByCampaign($campaignId, array $options = [])
    {
        $qb = $this->createQueryBuilder('c');
        
        $qb->where('c.campaign = :campaignId')
           ->setParameter('campaignId', $campaignId);

        // Filter by status
        if (!empty($options['status'])) {
            if (is_array($options['status'])) {
                $qb->andWhere($qb->expr()->in('c.status', ':status'))
                   ->setParameter('status', $options['status']);
            } else {
                $qb->andWhere('c.status = :status')
                   ->setParameter('status', $options['status']);
            }
        }

        // Filter by unsubscribed
        if (isset($options['unsubscribed'])) {
            $qb->andWhere('c.unsubscribed = :unsubscribed')
               ->setParameter('unsubscribed', (bool) $options['unsubscribed']);
        }

        // Ordering
        $orderBy = $options['orderBy'] ?? 'c.id';
        $order = $options['order'] ?? 'ASC';
        $qb->orderBy($orderBy, $order);

        // Limit
        if (!empty($options['limit'])) {
            $qb->setMaxResults($options['limit']);
        }

        // Offset
        if (!empty($options['offset'])) {
            $qb->setFirstResult($options['offset']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get contact by email and campaign
     *
     * @param string $email
     * @param int $campaignId
     * @return WarmupContact|null
     */
    public function getContactByEmail($email, $campaignId)
    {
        return $this->createQueryBuilder('c')
            ->where('c.email = :email')
            ->andWhere('c.campaign = :campaignId')
            ->setParameter('email', strtolower(trim($email)))
            ->setParameter('campaignId', $campaignId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get contacts count by campaign
     *
     * @param int $campaignId
     * @param array $filters
     * @return int
     */
    public function getContactsCount($campaignId, array $filters = [])
    {
        $qb = $this->createQueryBuilder('c');
        
        $qb->select('COUNT(c.id)')
           ->where('c.campaign = :campaignId')
           ->setParameter('campaignId', $campaignId);

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $qb->andWhere($qb->expr()->in('c.status', ':status'))
                   ->setParameter('status', $filters['status']);
            } else {
                $qb->andWhere('c.status = :status')
                   ->setParameter('status', $filters['status']);
            }
        }

        if (isset($filters['unsubscribed'])) {
            $qb->andWhere('c.unsubscribed = :unsubscribed')
               ->setParameter('unsubscribed', (bool) $filters['unsubscribed']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get contacts statistics by campaign
     *
     * @param int $campaignId
     * @return array
     */
    public function getContactsStatistics($campaignId)
    {
        $qb = $this->createQueryBuilder('c');
        
        $qb->select([
                'COUNT(c.id) as total',
                'SUM(CASE WHEN c.status = :pending THEN 1 ELSE 0 END) as pending',
                'SUM(CASE WHEN c.status = :sent THEN 1 ELSE 0 END) as sent',
                'SUM(CASE WHEN c.status = :failed THEN 1 ELSE 0 END) as failed',
                'SUM(CASE WHEN c.status = :bounced THEN 1 ELSE 0 END) as bounced',
                'SUM(CASE WHEN c.unsubscribed = 1 THEN 1 ELSE 0 END) as unsubscribed',
                'SUM(c.emailsSent) as totalEmailsSent',
                'SUM(c.emailsOpened) as totalEmailsOpened',
                'SUM(c.emailsClicked) as totalEmailsClicked'
            ])
           ->where('c.campaign = :campaignId')
           ->setParameter('campaignId', $campaignId)
           ->setParameter('pending', 'pending')
           ->setParameter('sent', 'sent')
           ->setParameter('failed', 'failed')
           ->setParameter('bounced', 'bounced');

        $result = $qb->getQuery()->getSingleResult();

        // Calculate rates
        $totalSent = (int) $result['totalEmailsSent'];
        $result['openRate'] = $totalSent > 0 ? round(((int) $result['totalEmailsOpened'] / $totalSent) * 100, 2) : 0;
        $result['clickRate'] = $totalSent > 0 ? round(((int) $result['totalEmailsClicked'] / $totalSent) * 100, 2) : 0;

        return $result;
    }

    /**
     * Get contacts pending for send
     *
     * @param int $campaignId
     * @param int $limit
     * @return array
     */
    public function getPendingContacts($campaignId, $limit = 100)
    {
        return $this->createQueryBuilder('c')
            ->where('c.campaign = :campaignId')
            ->andWhere('c.status = :status')
            ->andWhere('c.unsubscribed = 0')
            ->setParameter('campaignId', $campaignId)
            ->setParameter('status', 'pending')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get contacts for next batch
     *
     * @param int $campaignId
     * @param int $batchSize
     * @return array
     */
    public function getNextBatchContacts($campaignId, $batchSize)
    {
        $qb = $this->createQueryBuilder('c');
        
        $qb->where('c.campaign = :campaignId')
           ->andWhere('c.unsubscribed = 0')
           ->andWhere($qb->expr()->orX(
               $qb->expr()->eq('c.status', ':pending'),
               $qb->expr()->eq('c.status', ':sent')
           ))
           ->setParameter('campaignId', $campaignId)
           ->setParameter('pending', 'pending')
           ->setParameter('sent', 'sent')
           ->orderBy('c.lastSentDate', 'ASC')
           ->addOrderBy('c.id', 'ASC')
           ->setMaxResults($batchSize);

        return $qb->getQuery()->getResult();
    }

    /**
     * Bulk update contacts status
     *
     * @param array $contactIds
     * @param string $status
     * @return int Number of updated records
     */
    public function bulkUpdateStatus(array $contactIds, $status)
    {
        if (empty($contactIds)) {
            return 0;
        }

        $qb = $this->_em->createQueryBuilder();
        
        $qb->update($this->getEntityName(), 'c')
           ->set('c.status', ':status')
           ->where($qb->expr()->in('c.id', ':ids'))
           ->setParameter('status', $status)
           ->setParameter('ids', $contactIds);

        return $qb->getQuery()->execute();
    }

    /**
     * Delete contacts by campaign
     *
     * @param int $campaignId
     * @return int Number of deleted records
     */
    public function deleteContactsByCampaign($campaignId)
    {
        $qb = $this->_em->createQueryBuilder();
        
        $qb->delete($this->getEntityName(), 'c')
           ->where('c.campaign = :campaignId')
           ->setParameter('campaignId', $campaignId);

        return $qb->getQuery()->execute();
    }

    /**
     * Get top performers (contacts with highest engagement)
     *
     * @param int $campaignId
     * @param int $limit
     * @return array
     */
    public function getTopPerformers($campaignId, $limit = 10)
    {
        return $this->createQueryBuilder('c')
            ->where('c.campaign = :campaignId')
            ->andWhere('c.emailsSent > 0')
            ->setParameter('campaignId', $campaignId)
            ->orderBy('c.emailsOpened + (c.emailsClicked * 2)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get contacts with low engagement
     *
     * @param int $campaignId
     * @param int $minEmailsSent
     * @param int $limit
     * @return array
     */
    public function getLowEngagementContacts($campaignId, $minEmailsSent = 3, $limit = 100)
    {
        return $this->createQueryBuilder('c')
            ->where('c.campaign = :campaignId')
            ->andWhere('c.emailsSent >= :minSent')
            ->andWhere('c.emailsOpened = 0')
            ->andWhere('c.unsubscribed = 0')
            ->setParameter('campaignId', $campaignId)
            ->setParameter('minSent', $minEmailsSent)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get engagement statistics grouped by date
     *
     * @param int $campaignId
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return array
     */
    public function getEngagementByDate($campaignId, \DateTime $startDate, \DateTime $endDate)
    {
        $qb = $this->_em->createQueryBuilder();
        
        $qb->select([
                'DATE(c.lastSentDate) as date',
                'COUNT(c.id) as sent',
                'SUM(CASE WHEN c.emailsOpened > 0 THEN 1 ELSE 0 END) as opened',
                'SUM(CASE WHEN c.emailsClicked > 0 THEN 1 ELSE 0 END) as clicked'
            ])
           ->from($this->getEntityName(), 'c')
           ->where('c.campaign = :campaignId')
           ->andWhere('c.lastSentDate BETWEEN :startDate AND :endDate')
           ->setParameter('campaignId', $campaignId)
           ->setParameter('startDate', $startDate)
           ->setParameter('endDate', $endDate)
           ->groupBy('date')
           ->orderBy('date', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Search contacts
     *
     * @param int $campaignId
     * @param string $search
     * @param int $limit
     * @return array
     */
    public function searchContacts($campaignId, $search, $limit = 50)
    {
        $qb = $this->createQueryBuilder('c');
        
        $qb->where('c.campaign = :campaignId')
           ->andWhere($qb->expr()->orX(
               $qb->expr()->like('c.email', ':search'),
               $qb->expr()->like('c.firstName', ':search'),
               $qb->expr()->like('c.lastName', ':search')
           ))
           ->setParameter('campaignId', $campaignId)
           ->setParameter('search', '%' . $search . '%')
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get contacts that need attention (failed or bounced recently)
     *
     * @param int $campaignId
     * @param int $days
     * @return array
     */
    public function getContactsNeedingAttention($campaignId, $days = 7)
    {
        $since = new \DateTime("-{$days} days");
        
        return $this->createQueryBuilder('c')
            ->where('c.campaign = :campaignId')
            ->andWhere($qb->expr()->in('c.status', ':statuses'))
            ->andWhere('c.dateModified >= :since')
            ->setParameter('campaignId', $campaignId)
            ->setParameter('statuses', ['failed', 'bounced'])
            ->setParameter('since', $since)
            ->orderBy('c.dateModified', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get duplicate emails in campaign
     *
     * @param int $campaignId
     * @return array
     */
    public function getDuplicateEmails($campaignId)
    {
        $qb = $this->_em->createQueryBuilder();
        
        $qb->select('c.email', 'COUNT(c.id) as count')
           ->from($this->getEntityName(), 'c')
           ->where('c.campaign = :campaignId')
           ->setParameter('campaignId', $campaignId)
           ->groupBy('c.email')
           ->having('count > 1');

        return $qb->getQuery()->getResult();
    }

    /**
     * Export contacts to array
     *
     * @param int $campaignId
     * @param array $fields
     * @return array
     */
    public function exportContacts($campaignId, array $fields = [])
    {
        $defaultFields = ['email', 'firstName', 'lastName', 'status', 'emailsSent', 'emailsOpened', 'emailsClicked'];
        $selectFields = empty($fields) ? $defaultFields : $fields;
        
        $qb = $this->createQueryBuilder('c');
        
        foreach ($selectFields as $field) {
            $qb->addSelect('c.' . $field);
        }
        
        $qb->where('c.campaign = :campaignId')
           ->setParameter('campaignId', $campaignId);

        return $qb->getQuery()->getArrayResult();
    }
}
