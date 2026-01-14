<?php

namespace MauticPlugin\MauticWarmUpBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

/**
 * @ORM\Entity(repositoryClass="MauticPlugin\MauticWarmUpBundle\Repository\ContactRepository")
 * @ORM\Table(name="warmup_contacts")
 * @ORM\HasLifecycleCallbacks()
 */
class Contact
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Campaign
     * @ORM\ManyToOne(targetEntity="Campaign", inversedBy="contacts", cascade: ["persist"])
     * @ORM\JoinColumn(name="campaign_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $campaign;

    /**
     * @var string
     * @ORM\Column(name="email", type="string", length=255)
     */
    private $email;

    /**
     * @var string|null
     * @ORM\Column(name="first_name", type="string", length=255, nullable=true)
     */
    private $firstName;

    /**
     * @var string|null
     * @ORM\Column(name="last_name", type="string", length=255, nullable=true)
     */
    private $lastName;

    /**
     * @var string
     * @ORM\Column(type="string", length=50, options={"default": "pending"})
     */
    private $status = 'pending';

    /**
     * @var int
     * @ORM\Column(name="sequenceDay", type="integer", options={"default": 1})
     */
    private $sequenceDay = 1;

    /**
     * @var int
     * @ORM\Column(name="daysBetweenEmails", type="integer", options={"default": 2})
     */
    private $daysBetweenEmails = 2;

    /**
     * @var \DateTime|null
     * @ORM\Column(name="last_sent_date", type="datetime", nullable=true)
     */
    private $lastSentDate;

    /**
     * @var \DateTime|null
     * @ORM\Column(name="last_opened_date", type="datetime", nullable=true)
     */
    private $lastOpenedDate;

    /**
     * @var \DateTime|null
     * @ORM\Column(name="last_clicked_date", type="datetime", nullable=true)
     */
    private $lastClickedDate;

    /**
     * @var array|null
     * @ORM\Column(name="metadata", type="json", nullable=true)
     */
    private $metadata;

    /**
     * @var string|null
     * @ORM\Column(name="error_message", type="text", nullable=true)
     */
    private $errorMessage;

    /**
     * @var bool
     * @ORM\Column(type="boolean", options={"default": 0})
     */
    private $unsubscribed = false;

    /**
     * @var \DateTime|null
     * @ORM\Column(name="unsubscribed_date", type="datetime", nullable=true)
     */
    private $unsubscribedDate;

    /**
     * @var bool
     * @ORM\Column(name="is_published", type="boolean", options={"default": 1})
     */
    private $isPublished = true;

    /**
     * @var int|null
     * @ORM\Column(name="created_by", type="integer", nullable=true)
     */
    private $createdBy;

    /**
     * @var string|null
     * @ORM\Column(name="created_by_user", type="string", length=255, nullable=true)
     */
    private $createdByUser;

    /**
     * @var \DateTime|null
     * @ORM\Column(name="nextSendDate", type="datetime", nullable=true)
     */
    private $nextSendDate;

    /**
     * @var int
     * @ORM\Column(name="emails_sent", type="integer", options={"default": 0})
     */
    private $emailsSent = 0;

    /**
     * @var int
     * @ORM\Column(name="emails_opened", type="integer", options={"default": 0})
     */
    private $emailsOpened = 0;

    /**
     * @var int
     * @ORM\Column(name="emails_clicked", type="integer", options={"default": 0})
     */
    private $emailsClicked = 0;

    /**
     * @var bool
     * @ORM\Column(name="isActive", type="boolean", options={"default": 1})
     */
    private $isActive = true;

    /**
     * @var string|null
     * @ORM\Column(name="unsubscribeToken", type="string", length=64, nullable=true)
     */
    private $unsubscribeToken;

    /**
     * @var \DateTime
     * @ORM\Column(name="date_added", type="datetime")
     */
    private $dateAdded;

    /**
     * @var \DateTime
     * @ORM\Column(name="date_modified", type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $dateModified;

    /**
     * @var int|null
     * @ORM\Column(name="modified_by", type="integer", nullable=true)
     */
    private $modifiedBy;

    /**
     * @var string|null
     * @ORM\Column(name="modified_by_user", type="string", length=255, nullable=true)
     */
    private $modifiedByUser;

    /**
     * @var \DateTime|null
     * @ORM\Column(name="checked_out", type="datetime", nullable=true)
     */
    private $checkedOut;

    /**
     * @var int|null
     * @ORM\Column(name="checked_out_by", type="integer", nullable=true)
     */
    private $checkedOutBy;

    /**
     * @var string|null
     * @ORM\Column(name="checked_out_by_user", type="string", length=255, nullable=true)
     */
    private $checkedOutByUser;

    public function __construct()
    {
        $this->dateAdded = new \DateTime();
        $this->dateModified = new \DateTime();
    }

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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
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

    public function getLastSentDate(): ?\DateTime
    {
        return $this->lastSentDate;
    }

    public function setLastSentDate(?\DateTime $lastSentDate): self
    {
        $this->lastSentDate = $lastSentDate;
        return $this;
    }

    public function getLastOpenedDate(): ?\DateTime
    {
        return $this->lastOpenedDate;
    }

    public function setLastOpenedDate(?\DateTime $lastOpenedDate): self
    {
        $this->lastOpenedDate = $lastOpenedDate;
        return $this;
    }

    public function getLastClickedDate(): ?\DateTime
    {
        return $this->lastClickedDate;
    }

    public function setLastClickedDate(?\DateTime $lastClickedDate): self
    {
        $this->lastClickedDate = $lastClickedDate;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function isUnsubscribed(): bool
    {
        return $this->unsubscribed;
    }

    public function setUnsubscribed(bool $unsubscribed): self
    {
        $this->unsubscribed = $unsubscribed;
        if ($unsubscribed && !$this->unsubscribedDate) {
            $this->unsubscribedDate = new \DateTime();
        }
        return $this;
    }

    public function getUnsubscribedDate(): ?\DateTime
    {
        return $this->unsubscribedDate;
    }

    public function setUnsubscribedDate(?\DateTime $unsubscribedDate): self
    {
        $this->unsubscribedDate = $unsubscribedDate;
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): self
    {
        $this->isPublished = $isPublished;
        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedByUser(): ?string
    {
        return $this->createdByUser;
    }

    public function setCreatedByUser(?string $createdByUser): self
    {
        $this->createdByUser = $createdByUser;
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

    public function getEmailsSent(): int
    {
        return $this->emailsSent;
    }

    public function setEmailsSent(int $emailsSent): self
    {
        $this->emailsSent = $emailsSent;
        return $this;
    }

    public function incrementEmailsSent(): self
    {
        $this->emailsSent++;
        return $this;
    }

    public function getEmailsOpened(): int
    {
        return $this->emailsOpened;
    }

    public function setEmailsOpened(int $emailsOpened): self
    {
        $this->emailsOpened = $emailsOpened;
        return $this;
    }

    public function incrementEmailsOpened(): self
    {
        $this->emailsOpened++;
        return $this;
    }

    public function getEmailsClicked(): int
    {
        return $this->emailsClicked;
    }

    public function setEmailsClicked(int $emailsClicked): self
    {
        $this->emailsClicked = $emailsClicked;
        return $this;
    }

    public function incrementEmailsClicked(): self
    {
        $this->emailsClicked++;
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

    public function getDateAdded(): \DateTime
    {
        return $this->dateAdded;
    }

    public function setDateAdded(\DateTime $dateAdded): self
    {
        $this->dateAdded = $dateAdded;
        return $this;
    }

    public function getDateModified(): \DateTime
    {
        return $this->dateModified;
    }

    public function setDateModified(\DateTime $dateModified): self
    {
        $this->dateModified = $dateModified;
        return $this;
    }

    public function getModifiedBy(): ?int
    {
        return $this->modifiedBy;
    }

    public function setModifiedBy(?int $modifiedBy): self
    {
        $this->modifiedBy = $modifiedBy;
        return $this;
    }

    public function getModifiedByUser(): ?string
    {
        return $this->modifiedByUser;
    }

    public function setModifiedByUser(?string $modifiedByUser): self
    {
        $this->modifiedByUser = $modifiedByUser;
        return $this;
    }

    public function getCheckedOut(): ?\DateTime
    {
        return $this->checkedOut;
    }

    public function setCheckedOut(?\DateTime $checkedOut): self
    {
        $this->checkedOut = $checkedOut;
        return $this;
    }

    public function getCheckedOutBy(): ?int
    {
        return $this->checkedOutBy;
    }

    public function setCheckedOutBy(?int $checkedOutBy): self
    {
        $this->checkedOutBy = $checkedOutBy;
        return $this;
    }

    public function getCheckedOutByUser(): ?string
    {
        return $this->checkedOutByUser;
    }

    public function setCheckedOutByUser(?string $checkedOutByUser): self
    {
        $this->checkedOutByUser = $checkedOutByUser;
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
        if (!$this->isActive || $this->unsubscribed || !$this->isPublished) {
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

    /**
     * Mark email as opened
     */
    public function markAsOpened(): self
    {
        $this->lastOpenedDate = new \DateTime();
        $this->emailsOpened++;
        return $this;
    }

    /**
     * Mark email as clicked
     */
    public function markAsClicked(): self
    {
        $this->lastClickedDate = new \DateTime();
        $this->emailsClicked++;
        return $this;
    }

    /**
     * Mark email as sent
     */
    public function markAsSent(): self
    {
        $this->lastSentDate = new \DateTime();
        $this->emailsSent++;

        // Calculate next send date
        if ($this->daysBetweenEmails > 0) {
            $this->nextSendDate = (new \DateTime())->modify("+{$this->daysBetweenEmails} days");
        }

        return $this;
    }

    /**
     * @ORM\PrePersist
     */
    public function prePersist(): void
    {
        if ($this->dateAdded === null) {
            $this->dateAdded = new \DateTime();
        }
        $this->dateModified = new \DateTime();

        // Generate unsubscribe token if not set
        if (!$this->unsubscribeToken) {
            $this->unsubscribeToken = bin2hex(random_bytes(32));
        }
    }

    /**
     * @ORM\PreUpdate
     */
    public function preUpdate(): void
    {
        $this->dateModified = new \DateTime();
    }

    /**
     * Load metadata for Doctrine
     */
    /**
     * Load metadata for Doctrine
     */
    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder->setTable('warmup_contacts')
            ->addIndex(['email'], 'email_idx')
            ->addIndex(['campaign_id'], 'campaign_idx')
            ->addIndex(['nextSendDate'], 'next_send_idx')
            ->addIndex(['isActive'], 'is_active_idx')
            ->addIndex(['status'], 'status_idx')
            ->addIndex(['unsubscribed'], 'unsubscribed_idx');

        // ID
        // ID
        $builder->addId();

        // Campaign relation - AJOUTEZ CASCADE PERSIST ICI
        $builder->createManyToOne('campaign', Campaign::class)
            ->inversedBy('contacts')
            ->cascade(['persist'])  // <-- IMPORTANT
            ->addJoinColumn('campaign_id', 'id', true, false, 'CASCADE')
            ->build();

        // Fields
        $builder->addField('email', 'string', ['length' => 255]);
        $builder->addField('firstName', 'string', ['columnName' => 'first_name', 'length' => 255, 'nullable' => true]);
        $builder->addField('lastName', 'string', ['columnName' => 'last_name', 'length' => 255, 'nullable' => true]);
        $builder->createField('status', 'string')
            ->length(50)
            ->option('default', 'pending')
            ->build();
        $builder->createField('sequenceDay', 'integer')
            ->columnName('sequenceDay')
            ->option('default', 1)
            ->build();
        $builder->createField('daysBetweenEmails', 'integer')
            ->columnName('daysBetweenEmails')
            ->option('default', 2)
            ->build();
        $builder->createField('lastSentDate', 'datetime')
            ->columnName('last_sent_date')
            ->nullable()
            ->build();
        $builder->createField('lastOpenedDate', 'datetime')
            ->columnName('last_opened_date')
            ->nullable()
            ->build();
        $builder->createField('lastClickedDate', 'datetime')
            ->columnName('last_clicked_date')
            ->nullable()
            ->build();
        $builder->createField('metadata', 'json')
            ->nullable()
            ->build();
        $builder->createField('errorMessage', 'text')
            ->columnName('error_message')
            ->nullable()
            ->build();
        $builder->createField('unsubscribed', 'boolean')
            ->option('default', 0)
            ->build();
        $builder->createField('unsubscribedDate', 'datetime')
            ->columnName('unsubscribed_date')
            ->nullable()
            ->build();
        $builder->createField('isPublished', 'boolean')
            ->columnName('is_published')
            ->option('default', 1)
            ->build();
        $builder->createField('createdBy', 'integer')
            ->columnName('created_by')
            ->nullable()
            ->build();
        $builder->createField('createdByUser', 'string')
            ->columnName('created_by_user')
            ->length(255)
            ->nullable()
            ->build();
        $builder->createField('nextSendDate', 'datetime')
            ->columnName('nextSendDate')
            ->nullable()
            ->build();
        $builder->createField('emailsSent', 'integer')
            ->columnName('emails_sent')
            ->option('default', 0)
            ->build();
        $builder->createField('emailsOpened', 'integer')
            ->columnName('emails_opened')
            ->option('default', 0)
            ->build();
        $builder->createField('emailsClicked', 'integer')
            ->columnName('emails_clicked')
            ->option('default', 0)
            ->build();
        $builder->createField('isActive', 'boolean')
            ->columnName('isActive')
            ->option('default', 1)
            ->build();
        $builder->createField('unsubscribeToken', 'string')
            ->columnName('unsubscribeToken')
            ->length(64)
            ->nullable()
            ->build();

        // Date Added - REMPLACER addDateAdded()
        $builder->createField('dateAdded', 'datetime')
            ->columnName('date_added')
            ->nullable()
            ->build();

        // Date Modified - REMPLACER addDateModified()
        $builder->createField('dateModified', 'datetime')
            ->columnName('date_modified')
            ->nullable()
            ->option('default', 'CURRENT_TIMESTAMP')
            ->build();

        $builder->createField('modifiedBy', 'integer')
            ->columnName('modified_by')
            ->nullable()
            ->build();
        $builder->createField('modifiedByUser', 'string')
            ->columnName('modified_by_user')
            ->length(255)
            ->nullable()
            ->build();
        $builder->createField('checkedOut', 'datetime')
            ->columnName('checked_out')
            ->nullable()
            ->build();
        $builder->createField('checkedOutBy', 'integer')
            ->columnName('checked_out_by')
            ->nullable()
            ->build();
        $builder->createField('checkedOutByUser', 'string')
            ->columnName('checked_out_by_user')
            ->length(255)
            ->nullable()
            ->build();
    }
}