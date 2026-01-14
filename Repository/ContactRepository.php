<?php

namespace MauticPlugin\MauticWarmUpBundle\Repository;

use Doctrine\ORM\EntityRepository;
use MauticPlugin\MauticWarmUpBundle\Entity\Contact;
use MauticPlugin\MauticWarmUpBundle\Entity\Campaign;

class ContactRepository extends EntityRepository
{
    /**
     * Get entities with filtering
     */
    public function getEntities(array $args = []): array
    {
        $qb = $this->createQueryBuilder('c');

        // Join with campaign for filtering
        $qb->leftJoin('c.campaign', 'camp');

        // Apply filters
        if (!empty($args['filter'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('c.email', ':filter'),
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
            } elseif (in_array($args['status'], ['pending', 'sent', 'opened', 'clicked', 'bounced', 'unsubscribed'])) {
                $qb->andWhere('c.status = :status')
                    ->setParameter('status', $args['status']);
            }
        }

        if (!empty($args['campaign_id'])) {
            $qb->andWhere('c.campaign = :campaignId')
                ->setParameter('campaignId', $args['campaign_id']);
        }

        if (isset($args['is_published'])) {
            $qb->andWhere('c.isPublished = :isPublished')
                ->setParameter('isPublished', (bool) $args['is_published']);
        }

        if (isset($args['unsubscribed'])) {
            $qb->andWhere('c.unsubscribed = :unsubscribed')
                ->setParameter('unsubscribed', (bool) $args['unsubscribed']);
        }

        if (!empty($args['email'])) {
            $qb->andWhere('c.email = :email')
                ->setParameter('email', $args['email']);
        }

        // Apply ordering
        if (!empty($args['orderBy'])) {
            $orderBy = $args['orderBy'];
            // Map orderBy field names if needed
            $fieldMap = [
                'emailAddress' => 'email',
                'sentCount' => 'emailsSent',
                'createdAt' => 'dateAdded',
                'updatedAt' => 'dateModified'
            ];

            if (isset($fieldMap[$orderBy])) {
                $orderBy = $fieldMap[$orderBy];
            }

            $qb->orderBy('c.' . $orderBy, $args['orderByDir'] ?? 'ASC');
        } else {
            $qb->orderBy('c.dateAdded', 'DESC');
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
    public function getContactsReadyForNextEmail(int $campaignId = null, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->andWhere('c.isPublished = :published')
            ->andWhere('c.unsubscribed = :unsubscribed')
            ->andWhere('(c.nextSendDate IS NULL OR c.nextSendDate <= :now)')
            ->andWhere('(c.status = :pending OR c.status = :scheduled)')
            ->setParameter('active', true)
            ->setParameter('published', true)
            ->setParameter('unsubscribed', false)
            ->setParameter('now', new \DateTime())
            ->setParameter('pending', 'pending')
            ->setParameter('scheduled', 'scheduled')
            ->orderBy('c.nextSendDate', 'ASC')
            ->setMaxResults($limit);

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
            ->where('c.email LIKE :domain')
            ->setParameter('domain', '%@' . $domain)
            ->orderBy('c.email', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Get duplicate emails in campaign
     */


    /**
     * Find contact by email and campaign
     */
    public function findContactByEmailAndCampaign(string $email, int $campaignId): ?Contact
    {
        return $this->createQueryBuilder('c')
            ->where('c.email = :email') // CHANGÉ: c.email au lieu de c.emailAddress
            ->andWhere('c.campaign = :campaignId')
            ->setParameter('email', $email)
            ->setParameter('campaignId', $campaignId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get duplicate emails in campaign
     */
    public function getDuplicateEmails(int $campaignId = null): array
    {
        $qb = $this->createQueryBuilder('c');

        $qb->select('c.email, COUNT(c.id) as duplicate_count') // CHANGÉ: c.email au lieu de c.emailAddress
            ->groupBy('c.email')
            ->having('duplicate_count > 1')
            ->orderBy('duplicate_count', 'DESC');

        if ($campaignId) {
            $qb->andWhere('c.campaign = :campaignId')
                ->setParameter('campaignId', $campaignId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get contact engagement metrics
     */
    public function getContactEngagement(int $contactId): array
    {
        try {
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

            $totalSent = (int) ($result['total_sent'] ?? 0);
            $opened = (int) ($result['opened'] ?? 0);
            $clicked = (int) ($result['clicked'] ?? 0);
            $replied = (int) ($result['replied'] ?? 0);

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
        } catch (\Exception $e) {
            return [
                'total_sent' => 0,
                'opened' => 0,
                'clicked' => 0,
                'replied' => 0,
                'open_rate' => 0,
                'click_rate' => 0,
                'reply_rate' => 0,
                'engagement_score' => 0,
            ];
        }
    }

    /**
     * Get contacts that haven't received emails recently
     */
    public function getInactiveContacts(\DateTime $sinceDate, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->andWhere('c.isPublished = :published')
            ->andWhere('(c.lastSentDate < :sinceDate OR c.lastSentDate IS NULL)')
            ->andWhere('c.unsubscribed = :unsubscribed')
            ->setParameter('active', true)
            ->setParameter('published', true)
            ->setParameter('unsubscribed', false)
            ->setParameter('sinceDate', $sinceDate)
            ->orderBy('c.lastSentDate', 'ASC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get contacts with errors
     */
    public function getContactsWithErrors(int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.errorMessage IS NOT NULL')
            ->andWhere('c.errorMessage != :empty')
            ->setParameter('empty', '')
            ->orderBy('c.dateModified', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get contacts by status
     */
    public function getContactsByStatus(string $status, int $campaignId = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', $status);

        if ($campaignId) {
            $qb->andWhere('c.campaign = :campaignId')
                ->setParameter('campaignId', $campaignId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get contact count by campaign
     */
    public function getCountByCampaign(): array
    {
        $qb = $this->createQueryBuilder('c');

        $qb->select('camp.id as campaign_id, camp.campaignName, COUNT(c.id) as contact_count')
            ->leftJoin('c.campaign', 'camp')
            ->groupBy('camp.id, camp.campaignName')
            ->orderBy('contact_count', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Search contacts
     */
    public function searchContacts(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.email LIKE :query')
            ->orWhere('c.firstName LIKE :query')
            ->orWhere('c.lastName LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.email', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get statistics for dashboard
     */
    public function getDashboardStats(): array
    {
        $qb = $this->createQueryBuilder('c');

        $qb->select('COUNT(c.id) as total_contacts')
            ->addSelect('SUM(CASE WHEN c.isActive = true THEN 1 ELSE 0 END) as active_contacts')
            ->addSelect('SUM(CASE WHEN c.unsubscribed = true THEN 1 ELSE 0 END) as unsubscribed_contacts')
            ->addSelect('SUM(CASE WHEN c.status = :pending THEN 1 ELSE 0 END) as pending_contacts')
            ->addSelect('SUM(c.emailsSent) as total_emails_sent')
            ->addSelect('SUM(c.emailsOpened) as total_emails_opened')
            ->addSelect('SUM(c.emailsClicked) as total_emails_clicked')
            ->setParameter('pending', 'pending');

        $result = $qb->getQuery()->getSingleResult();

        $totalSent = (int) $result['total_emails_sent'];
        $totalOpened = (int) $result['total_emails_opened'];
        $totalClicked = (int) $result['total_emails_clicked'];

        $openRate = $totalSent > 0 ? ($totalOpened / $totalSent) * 100 : 0;
        $clickRate = $totalSent > 0 ? ($totalClicked / $totalSent) * 100 : 0;

        return [
            'total_contacts' => (int) $result['total_contacts'],
            'active_contacts' => (int) $result['active_contacts'],
            'unsubscribed_contacts' => (int) $result['unsubscribed_contacts'],
            'pending_contacts' => (int) $result['pending_contacts'],
            'total_emails_sent' => $totalSent,
            'total_emails_opened' => $totalOpened,
            'total_emails_clicked' => $totalClicked,
            'open_rate' => round($openRate, 2),
            'click_rate' => round($clickRate, 2),
        ];
    }
}