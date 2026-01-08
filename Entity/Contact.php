<?php

namespace MauticPlugin\MauticWarmUpBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="MauticPlugin\MauticWarmUpBundle\Repository\ContactRepository")
 * @ORM\Table(name="warmup_contacts")
 */
class Contact
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Campaign", inversedBy="contacts")
     * @ORM\JoinColumn(name="campaign_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $campaign;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $emailAddress;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $firstName;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $lastName;

    /**
     * @ORM\Column(type="integer", options={"default": 1})
     */
    private $sequenceDay = 1;

    /**
     * @ORM\Column(type="integer", options={"default": 2})
     */
    private $daysBetweenEmails = 2;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $lastSent;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $nextSendDate;

    /**
     * @ORM\Column(type="integer", options={"default": 0})
     */
    private $sentCount = 0;

    /**
     * @ORM\Column(type="boolean", options={"default": true})
     */
    private $isActive = true;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $unsubscribeToken;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;
    private $updatedAt;


    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCampaign(): ?Campaign
    {
        return $this->campaign;
    }

    public function setCampaign(?Campaign $campaign): self
    {
        $this->campaign = $campaign;
        return $this;
    }

    public function getEmailAddress(): ?string
    {
        return $this->emailAddress;
    }

    public function setEmailAddress(string $emailAddress): self
    {
        $this->emailAddress = $emailAddress;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getSequenceDay(): int
    {
        return $this->sequenceDay;
    }

    public function setSequenceDay(int $sequenceDay): self
    {
        $this->sequenceDay = $sequenceDay;
        return $this;
    }

    public function getDaysBetweenEmails(): int
    {
        return $this->daysBetweenEmails;
    }

    public function setDaysBetweenEmails(int $daysBetweenEmails): self
    {
        $this->daysBetweenEmails = $daysBetweenEmails;
        return $this;
    }

    public function getLastSent(): ?\DateTime
    {
        return $this->lastSent;
    }

    public function setLastSent(?\DateTime $lastSent): self
    {
        $this->lastSent = $lastSent;
        return $this;
    }

    public function getNextSendDate(): ?\DateTime
    {
        return $this->nextSendDate;
    }

    public function setNextSendDate(?\DateTime $nextSendDate): self
    {
        $this->nextSendDate = $nextSendDate;
        return $this;
    }

    public function getSentCount(): int
    {
        return $this->sentCount;
    }

    public function setSentCount(int $sentCount): self
    {
        $this->sentCount = $sentCount;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getUnsubscribeToken(): ?string
    {
        return $this->unsubscribeToken;
    }

    public function setUnsubscribeToken(?string $unsubscribeToken): self
    {
        $this->unsubscribeToken = $unsubscribeToken;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Get full name
     */
    public function getFullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
    }

    /**
     * Check if contact is ready for next email
     */
    public function isReadyForNextEmail(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        if (!$this->nextSendDate) {
            return true;
        }

        return $this->nextSendDate <= new \DateTime();
    }

    /**
     * Generate unsubscribe URL
     */
    public function getUnsubscribeUrl(): string
    {
        if (!$this->unsubscribeToken) {
            return '';
        }

        // This would be generated by your router
        return '/warmup/unsubscribe/' . $this->unsubscribeToken;
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $metadata->setPrimaryTable([
            'name' => 'warmup_contacts',
            'indexes' => [
                new ORM\Index(name: 'email_idx', columns: ['email_address']),
                new ORM\Index(name: 'campaign_idx', columns: ['campaign_id']),
                new ORM\Index(name: 'next_send_idx', columns: ['next_send_date']),
                new ORM\Index(name: 'is_active_idx', columns: ['is_active']),
            ]
        ]);

        // ID
        $metadata->mapField([
            'id' => true,
            'fieldName' => 'id',
            'type' => 'integer',
            'options' => ['unsigned' => true]
        ]);
        $metadata->setIdGeneratorType(ORM\ClassMetadata::GENERATOR_TYPE_AUTO);

        // emailAddress
        $metadata->mapField([
            'fieldName' => 'emailAddress',
            'columnName' => 'email_address',
            'type' => 'string',
            'length' => 255,
            'nullable' => false
        ]);

        // firstName
        $metadata->mapField([
            'fieldName' => 'firstName',
            'columnName' => 'first_name',
            'type' => 'string',
            'length' => 100,
            'nullable' => true
        ]);

        // lastName
        $metadata->mapField([
            'fieldName' => 'lastName',
            'columnName' => 'last_name',
            'type' => 'string',
            'length' => 100,
            'nullable' => true
        ]);

        // sequenceDay
        $metadata->mapField([
            'fieldName' => 'sequenceDay',
            'columnName' => 'sequence_day',
            'type' => 'integer',
            'nullable' => false,
            'options' => ['default' => 1]
        ]);

        // daysBetweenEmails
        $metadata->mapField([
            'fieldName' => 'daysBetweenEmails',
            'columnName' => 'days_between_emails',
            'type' => 'integer',
            'nullable' => false,
            'options' => ['default' => 2]
        ]);

        // lastSent
        $metadata->mapField([
            'fieldName' => 'lastSent',
            'columnName' => 'last_sent',
            'type' => 'datetime',
            'nullable' => true
        ]);

        // nextSendDate
        $metadata->mapField([
            'fieldName' => 'nextSendDate',
            'columnName' => 'next_send_date',
            'type' => 'datetime',
            'nullable' => true
        ]);

        // sentCount
        $metadata->mapField([
            'fieldName' => 'sentCount',
            'columnName' => 'sent_count',
            'type' => 'integer',
            'nullable' => false,
            'options' => ['default' => 0]
        ]);

        // isActive
        $metadata->mapField([
            'fieldName' => 'isActive',
            'columnName' => 'is_active',
            'type' => 'boolean',
            'nullable' => false,
            'options' => ['default' => true]
        ]);

        // unsubscribeToken
        $metadata->mapField([
            'fieldName' => 'unsubscribeToken',
            'columnName' => 'unsubscribe_token',
            'type' => 'string',
            'length' => 64,
            'nullable' => true
        ]);

        // createdAt
        $metadata->mapField([
            'fieldName' => 'createdAt',
            'columnName' => 'created_at',
            'type' => 'datetime',
            'nullable' => false
        ]);

        // updatedAt
        $metadata->mapField([
            'fieldName' => 'updatedAt',
            'columnName' => 'updated_at',
            'type' => 'datetime',
            'nullable' => false
        ]);

        // Relations
        // Campaign (ManyToOne)
        $metadata->mapManyToOne([
            'fieldName' => 'campaign',
            'targetEntity' => Campaign::class,
            'joinColumns' => [
                [
                    'name' => 'campaign_id',
                    'referencedColumnName' => 'id',
                    'nullable' => false,
                    'onDelete' => 'CASCADE'
                ]
            ]
        ]);
    }

    /**
     * Define Doctrine lifecycle callbacks
     *
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadLifecycleCallbacks(ORM\ClassMetadata $metadata): void
    {
        $metadata->addLifecycleCallback('updateTimestamps', 'prePersist');
        $metadata->addLifecycleCallback('updateTimestamps', 'preUpdate');
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();

        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }
}
