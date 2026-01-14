<?php

namespace MauticPlugin\MauticWarmUpBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Contact;
use MauticPlugin\MauticWarmUpBundle\Entity\Campaign;

class ContactModel
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Get entity by ID
     */
    public function getEntity(int $id = null): ?Contact
    {
        if ($id === null) {
            return new Contact();
        }

        return $this->em->getRepository(Contact::class)->find($id);
    }

    /**
     * Save contact entity
     */
    public function saveEntity(Contact $contact): void
    {
        $isNew = $contact->getId() === null;

        // Generate unsubscribe token if new
        if ($isNew && !$contact->getUnsubscribeToken()) {
            $contact->setUnsubscribeToken(bin2hex(random_bytes(32)));
        }

        $this->em->persist($contact);
        $this->em->flush();
    }

    /**
     * Delete contact entity
     */
    public function deleteEntity(Contact $contact): void
    {
        $this->em->remove($contact);
        $this->em->flush();
    }

    /**
     * Get contact by unsubscribe token
     */
    public function getContactByToken(string $token): ?Contact
    {
        return $this->em->getRepository(Contact::class)->findOneBy(['unsubscribeToken' => $token]);
    }

    /**
     * Unsubscribe contact
     */
    public function unsubscribeContact(Contact $contact): void
    {
        $contact->setUnsubscribed(true);
        $contact->setUnsubscribedDate(new \DateTime());
        $contact->setIsActive(false);
        $this->saveEntity($contact);
    }

    /**
     * Get contact history
     */
    public function getContactHistory(Contact $contact): array
    {
        try {
            $qb = $this->em->createQueryBuilder();

            $qb->select('l')
                ->from('MauticWarmUpBundle:SentLog', 'l')
                ->where('l.contact = :contact')
                ->setParameter('contact', $contact)
                ->orderBy('l.sendTime', 'DESC')
                ->setMaxResults(50);

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get contact statistics
     */
    public function getContactStats(Contact $contact): array
    {
        return [
            'emails_sent' => $contact->getEmailsSent(),
            'emails_opened' => $contact->getEmailsOpened(),
            'emails_clicked' => $contact->getEmailsClicked(),
            'is_active' => $contact->isActive(),
            'is_published' => $contact->isPublished(),
            'unsubscribed' => $contact->isUnsubscribed(),
            'campaign' => $contact->getCampaign() ? [
                'id' => $contact->getCampaign()->getId(),
                'name' => $contact->getCampaign()->getCampaignName(),
            ] : null,
            'last_sent_date' => $contact->getLastSentDate(),
            'last_opened_date' => $contact->getLastOpenedDate(),
            'last_clicked_date' => $contact->getLastClickedDate(),
            'next_send_date' => $contact->getNextSendDate(),
            'sequence_day' => $contact->getSequenceDay(),
            'days_between_emails' => $contact->getDaysBetweenEmails(),
        ];
    }

    /**
     * Export contacts
     */
    public function exportContacts(array $filterParams = [], string $format = 'csv'): string
    {
        $contacts = $this->getEntities($filterParams);

        if ($format === 'csv') {
            return $this->exportToCsv($contacts);
        }

        return $this->exportToJson($contacts);
    }

    /**
     * Export to CSV
     */
    private function exportToCsv(array $contacts): string
    {
        $output = fopen('php://temp', 'r+');

        // Write headers
        fputcsv($output, [
            'Email',
            'First Name',
            'Last Name',
            'Campaign',
            'Status',
            'Emails Sent',
            'Emails Opened',
            'Emails Clicked',
            'Active',
            'Unsubscribed',
            'Last Sent',
            'Next Send Date',
            'Date Added'
        ]);

        // Write data
        foreach ($contacts as $contact) {
            fputcsv($output, [
                $contact->getEmail(),
                $contact->getFirstName(),
                $contact->getLastName(),
                $contact->getCampaign() ? $contact->getCampaign()->getCampaignName() : '',
                $contact->getStatus(),
                $contact->getEmailsSent(),
                $contact->getEmailsOpened(),
                $contact->getEmailsClicked(),
                $contact->isActive() ? 'Yes' : 'No',
                $contact->isUnsubscribed() ? 'Yes' : 'No',
                $contact->getLastSentDate() ? $contact->getLastSentDate()->format('Y-m-d H:i:s') : '',
                $contact->getNextSendDate() ? $contact->getNextSendDate()->format('Y-m-d H:i:s') : '',
                $contact->getDateAdded() ? $contact->getDateAdded()->format('Y-m-d H:i:s') : '',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Export to JSON
     */
    private function exportToJson(array $contacts): string
    {
        $data = [];

        foreach ($contacts as $contact) {
            $data[] = [
                'id' => $contact->getId(),
                'email' => $contact->getEmail(),
                'first_name' => $contact->getFirstName(),
                'last_name' => $contact->getLastName(),
                'campaign' => $contact->getCampaign() ? [
                    'id' => $contact->getCampaign()->getId(),
                    'name' => $contact->getCampaign()->getCampaignName(),
                ] : null,
                'status' => $contact->getStatus(),
                'emails_sent' => $contact->getEmailsSent(),
                'emails_opened' => $contact->getEmailsOpened(),
                'emails_clicked' => $contact->getEmailsClicked(),
                'is_active' => $contact->isActive(),
                'is_published' => $contact->isPublished(),
                'unsubscribed' => $contact->isUnsubscribed(),
                'last_sent_date' => $contact->getLastSentDate() ? $contact->getLastSentDate()->format('Y-m-d H:i:s') : null,
                'last_opened_date' => $contact->getLastOpenedDate() ? $contact->getLastOpenedDate()->format('Y-m-d H:i:s') : null,
                'last_clicked_date' => $contact->getLastClickedDate() ? $contact->getLastClickedDate()->format('Y-m-d H:i:s') : null,
                'next_send_date' => $contact->getNextSendDate() ? $contact->getNextSendDate()->format('Y-m-d H:i:s') : null,
                'sequence_day' => $contact->getSequenceDay(),
                'days_between_emails' => $contact->getDaysBetweenEmails(),
                'unsubscribe_token' => $contact->getUnsubscribeToken(),
                'date_added' => $contact->getDateAdded() ? $contact->getDateAdded()->format('Y-m-d H:i:s') : null,
                'date_modified' => $contact->getDateModified() ? $contact->getDateModified()->format('Y-m-d H:i:s') : null,
            ];
        }

        return json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Batch delete contacts
     */
    public function batchDelete(array $ids): int
    {
        $qb = $this->em->createQueryBuilder();

        $qb->delete('MauticWarmUpBundle:Contact', 'c')
            ->where($qb->expr()->in('c.id', ':ids'))
            ->setParameter('ids', $ids);

        return $qb->getQuery()->execute();
    }

    /**
     * Batch activate contacts
     */
    public function batchActivate(array $ids): int
    {
        $qb = $this->em->createQueryBuilder();

        $qb->update('MauticWarmUpBundle:Contact', 'c')
            ->set('c.isActive', ':active')
            ->where($qb->expr()->in('c.id', ':ids'))
            ->setParameter('active', true)
            ->setParameter('ids', $ids);

        return $qb->getQuery()->execute();
    }

    /**
     * Batch deactivate contacts
     */
    public function batchDeactivate(array $ids): int
    {
        $qb = $this->em->createQueryBuilder();

        $qb->update('MauticWarmUpBundle:Contact', 'c')
            ->set('c.isActive', ':active')
            ->where($qb->expr()->in('c.id', ':ids'))
            ->setParameter('active', false)
            ->setParameter('ids', $ids);

        return $qb->getQuery()->execute();
    }

    /**
     * Batch publish contacts
     */
    public function batchPublish(array $ids): int
    {
        $qb = $this->em->createQueryBuilder();

        $qb->update('MauticWarmUpBundle:Contact', 'c')
            ->set('c.isPublished', ':published')
            ->where($qb->expr()->in('c.id', ':ids'))
            ->setParameter('published', true)
            ->setParameter('ids', $ids);

        return $qb->getQuery()->execute();
    }

    /**
     * Batch unpublish contacts
     */
    public function batchUnpublish(array $ids): int
    {
        $qb = $this->em->createQueryBuilder();

        $qb->update('MauticWarmUpBundle:Contact', 'c')
            ->set('c.isPublished', ':published')
            ->where($qb->expr()->in('c.id', ':ids'))
            ->setParameter('published', false)
            ->setParameter('ids', $ids);

        return $qb->getQuery()->execute();
    }

    /**
     * Get entities as array (for forms and API)
     */
    public function getEntities(array $args = []): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Contact::class, 'c')
            ->orderBy('c.id', 'DESC');

        if (isset($args['start'])) {
            $qb->setFirstResult($args['start']);
        }
        if (isset($args['limit'])) {
            $qb->setMaxResults($args['limit']);
        }
        if (isset($args['filter']) && !empty($args['filter'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('c.email', ':filter'),
                $qb->expr()->like('c.firstName', ':filter'),
                $qb->expr()->like('c.lastName', ':filter')
            ))
                ->setParameter('filter', '%' . $args['filter'] . '%');
        }
        if (isset($args['campaign'])) {
            $qb->andWhere('c.campaign = :campaign')
                ->setParameter('campaign', $args['campaign']);
        }
        if (isset($args['isActive'])) {
            $qb->andWhere('c.isActive = :isActive')
                ->setParameter('isActive', $args['isActive']);
        }
        if (isset($args['isPublished'])) {
            $qb->andWhere('c.isPublished = :isPublished')
                ->setParameter('isPublished', $args['isPublished']);
        }
        if (isset($args['status'])) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $args['status']);
        }
        if (isset($args['unsubscribed'])) {
            $qb->andWhere('c.unsubscribed = :unsubscribed')
                ->setParameter('unsubscribed', $args['unsubscribed']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get total count of contacts
     */
    public function getTotalCount(): int
    {
        $query = $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Contact::class, 'c')
            ->getQuery();

        return (int) $query->getSingleScalarResult();
    }

    /**
     * Get active contacts count
     */
    public function getActiveCount(): int
    {
        $query = $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Contact::class, 'c')
            ->where('c.isActive = true')
            ->getQuery();

        return (int) $query->getSingleScalarResult();
    }

    /**
     * Get contacts by campaign
     */
    public function getContactsByCampaign(int $campaignId): array
    {
        $campaign = $this->em->getRepository(Campaign::class)->find($campaignId);
        if (!$campaign) {
            return [];
        }

        return $this->em->getRepository(Contact::class)->findBy(
            ['campaign' => $campaign],
            ['email' => 'ASC']
        );
    }

    /**
     * Import contacts from array
     */
    public function importContacts(array $contactsData, int $campaignId = null): array
    {
        $results = [
            'imported' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => []
        ];

        $campaign = null;
        if ($campaignId) {
            $campaign = $this->em->getRepository(Campaign::class)->find($campaignId);
        }

        foreach ($contactsData as $index => $contactData) {
            $email = $contactData['email'] ?? null;

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results['failed']++;
                $results['errors'][] = "Ligne " . ($index + 1) . ": Email invalide - " . $email;
                continue;
            }

            // Check if contact already exists
            $existing = $this->em->getRepository(Contact::class)->findOneBy(['email' => $email]);

            try {
                if ($existing) {
                    // Update existing contact
                    if (isset($contactData['first_name'])) {
                        $existing->setFirstName($contactData['first_name']);
                    }
                    if (isset($contactData['last_name'])) {
                        $existing->setLastName($contactData['last_name']);
                    }
                    if ($campaign) {
                        $existing->setCampaign($campaign);
                    }
                    if (isset($contactData['sequence_day'])) {
                        $existing->setSequenceDay((int) $contactData['sequence_day']);
                    }
                    if (isset($contactData['days_between_emails'])) {
                        $existing->setDaysBetweenEmails((int) $contactData['days_between_emails']);
                    }
                    $existing->setIsActive(true);
                    $existing->setIsPublished(true);
                    $this->em->persist($existing);
                    $results['updated']++;
                } else {
                    // Create new contact
                    $contact = new Contact();
                    $contact->setEmail($email);
                    $contact->setFirstName($contactData['first_name'] ?? '');
                    $contact->setLastName($contactData['last_name'] ?? '');
                    if ($campaign) {
                        $contact->setCampaign($campaign);
                    }
                    if (isset($contactData['sequence_day'])) {
                        $contact->setSequenceDay((int) $contactData['sequence_day']);
                    }
                    if (isset($contactData['days_between_emails'])) {
                        $contact->setDaysBetweenEmails((int) $contactData['days_between_emails']);
                    }
                    $contact->setIsActive(true);
                    $contact->setIsPublished(true);
                    $contact->setUnsubscribeToken(bin2hex(random_bytes(32)));
                    $this->em->persist($contact);
                    $results['imported']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Ligne " . ($index + 1) . ": " . $e->getMessage();
            }
        }

        $this->em->flush();

        return $results;
    }

    /**
     * Get contacts ready for sending
     */
    public function getContactsReadyForSending(int $campaignId = null, int $limit = 100): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Contact::class, 'c')
            ->where('c.isActive = true')
            ->andWhere('c.isPublished = true')
            ->andWhere('c.unsubscribed = false')
            ->andWhere('(c.nextSendDate IS NULL OR c.nextSendDate <= :now)')
            ->andWhere('(c.status = :pending OR c.status = :scheduled)')
            ->setParameter('now', new \DateTime())
            ->setParameter('pending', 'pending')
            ->setParameter('scheduled', 'scheduled')
            ->orderBy('c.nextSendDate', 'ASC')
            ->setMaxResults($limit);

        if ($campaignId) {
            $campaign = $this->em->getRepository(Campaign::class)->find($campaignId);
            if ($campaign) {
                $qb->andWhere('c.campaign = :campaign')
                    ->setParameter('campaign', $campaign);
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Update contact status
     */
    public function updateContactStatus(int $contactId, string $status): bool
    {
        try {
            $contact = $this->getEntity($contactId);
            if (!$contact) {
                return false;
            }

            $contact->setStatus($status);
            $this->saveEntity($contact);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Reset contact sequence
     */
    public function resetContactSequence(Contact $contact): void
    {
        $contact->setSequenceDay(1);
        $contact->setNextSendDate(null);
        $contact->setLastSentDate(null);
        $contact->setStatus('pending');
        $this->saveEntity($contact);
    }
}