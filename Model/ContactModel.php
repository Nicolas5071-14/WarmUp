<?php

namespace MauticPlugin\MauticWarmUpBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Contact;

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
        $contact->setIsActive(false);
        $this->saveEntity($contact);
    }

    /**
     * Get contact history
     */
    public function getContactHistory(Contact $contact): array
    {
        // Assurez-vous que l'entité SentLog existe dans votre bundle
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
            // Retourner un tableau vide si l'entité n'existe pas encore
            return [];
        }
    }

    /**
     * Get contact statistics
     */
    public function getContactStats(Contact $contact): array
    {
        return [
            'sent_count' => $contact->getSentCount(),
            'is_active' => $contact->isActive(),
            'campaign' => $contact->getCampaign() ? [
                'id' => $contact->getCampaign()->getId(),
                'name' => $contact->getCampaign()->getCampaignName(),
            ] : null,
            'last_sent' => $contact->getLastSent(),
            'next_send_date' => $contact->getNextSendDate(),
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
            'Sent Count',
            'Active',
            'Last Sent',
            'Next Send Date'
        ]);

        // Write data
        foreach ($contacts as $contact) {
            fputcsv($output, [
                $contact->getEmailAddress(),
                $contact->getFirstName(),
                $contact->getLastName(),
                $contact->getCampaign() ? $contact->getCampaign()->getCampaignName() : '',
                $contact->getSentCount(),
                $contact->isActive() ? 'Yes' : 'No',
                $contact->getLastSent() ? $contact->getLastSent()->format('Y-m-d H:i:s') : '',
                $contact->getNextSendDate() ? $contact->getNextSendDate()->format('Y-m-d H:i:s') : '',
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
                'email' => $contact->getEmailAddress(),
                'first_name' => $contact->getFirstName(),
                'last_name' => $contact->getLastName(),
                'campaign' => $contact->getCampaign() ? [
                    'id' => $contact->getCampaign()->getId(),
                    'name' => $contact->getCampaign()->getCampaignName(),
                ] : null,
                'sent_count' => $contact->getSentCount(),
                'is_active' => $contact->isActive(),
                'last_sent' => $contact->getLastSent() ? $contact->getLastSent()->format('Y-m-d H:i:s') : null,
                'next_send_date' => $contact->getNextSendDate() ? $contact->getNextSendDate()->format('Y-m-d H:i:s') : null,
                'unsubscribe_token' => $contact->getUnsubscribeToken(),
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
                $qb->expr()->like('c.emailAddress', ':filter'),
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

        $entities = $qb->getQuery()->getResult();

        $result = [];
        foreach ($entities as $contact) {
            $result[] = [
                'id' => $contact->getId(),
                'emailAddress' => $contact->getEmailAddress(),
                'firstName' => $contact->getFirstName(),
                'lastName' => $contact->getLastName(),
                'campaign' => $contact->getCampaign() ? [
                    'id' => $contact->getCampaign()->getId(),
                    'name' => $contact->getCampaign()->getCampaignName(),
                ] : null,
                'sentCount' => $contact->getSentCount(),
                'isActive' => $contact->isActive(),
                'lastSent' => $contact->getLastSent(),
                'nextSendDate' => $contact->getNextSendDate(),
                'unsubscribeToken' => $contact->getUnsubscribeToken(),
                'createdAt' => $contact->getCreatedAt(),
                'updatedAt' => $contact->getUpdatedAt(),
            ];
        }

        return $result;
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
        return $this->em->getRepository(Contact::class)->findBy(
            ['campaign' => $campaignId],
            ['emailAddress' => 'ASC']
        );
    }

    /**
     * Import contacts from array
     */
    public function importContacts(array $contactsData, int $campaignId = null): int
    {
        $imported = 0;

        foreach ($contactsData as $contactData) {
            $email = $contactData['email'] ?? null;

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Check if contact already exists
            $existing = $this->em->getRepository(Contact::class)->findOneBy(['emailAddress' => $email]);

            if ($existing) {
                // Update existing contact
                if (isset($contactData['first_name'])) {
                    $existing->setFirstName($contactData['first_name']);
                }
                if (isset($contactData['last_name'])) {
                    $existing->setLastName($contactData['last_name']);
                }
                if ($campaignId) {
                    // Note: Vous devrez récupérer l'entité Campaign par son ID ici
                }
                $existing->setIsActive(true);
                $this->em->persist($existing);
            } else {
                // Create new contact
                $contact = new Contact();
                $contact->setEmailAddress($email);
                $contact->setFirstName($contactData['first_name'] ?? '');
                $contact->setLastName($contactData['last_name'] ?? '');
                $contact->setIsActive(true);
                if ($campaignId) {
                    // Note: Vous devrez récupérer l'entité Campaign par son ID ici
                }
                $contact->setUnsubscribeToken(bin2hex(random_bytes(32)));
                $this->em->persist($contact);
            }

            $imported++;
        }

        $this->em->flush();

        return $imported;
    }
}