<?php

namespace MauticPlugin\MauticWarmUpBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="MauticPlugin\MauticWarmUpBundle\Repository\SentLogRepository")
 * @ORM\Table(name="warmup_sent_logs")
 */
class SentLog
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Domain")
     * @ORM\JoinColumn(name="domain_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $domain;

    /**
     * @ORM\ManyToOne(targetEntity="Campaign")
     * @ORM\JoinColumn(name="campaign_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $campaign;

    /**
     * @ORM\ManyToOne(targetEntity="Contact")
     * @ORM\JoinColumn(name="contact_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $contact;

    /**
     * @ORM\Column(type="integer")
     */
    private $sequenceDay;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $emailSubject;

    /**
     * @ORM\Column(type="text")
     */
    private $emailContent;

    /**
     * @ORM\Column(type="datetime")
     */
    private $sendTime;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $status;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $messageId;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $errorMessage;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDomain(): ?Domain
    {
        return $this->domain;
    }

    public function setDomain(?Domain $domain): self
    {
        $this->domain = $domain;
        return $this;
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

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): self
    {
        $this->contact = $contact;
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

    public function getEmailSubject(): ?string
    {
        return $this->emailSubject;
    }

    public function setEmailSubject(string $emailSubject): self
    {
        $this->emailSubject = $emailSubject;
        return $this;
    }

    public function getEmailContent(): ?string
    {
        return $this->emailContent;
    }

    public function setEmailContent(string $emailContent): self
    {
        $this->emailContent = $emailContent;
        return $this;
    }

    public function getSendTime(): \DateTime
    {
        return $this->sendTime;
    }

    public function setSendTime(\DateTime $sendTime): self
    {
        $this->sendTime = $sendTime;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function setMessageId(?string $messageId): self
    {
        $this->messageId = $messageId;
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

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $metadata->setPrimaryTable([
            'name' => 'warmup_sent_logs',
            'indexes' => [
                new ORM\Index(name: 'domain_idx', columns: ['domain_id']),
                new ORM\Index(name: 'campaign_idx', columns: ['campaign_id']),
                new ORM\Index(name: 'contact_idx', columns: ['contact_id']),
                new ORM\Index(name: 'status_idx', columns: ['status']),
                new ORM\Index(name: 'send_time_idx', columns: ['send_time']),
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

        // sequenceDay
        $metadata->mapField([
            'fieldName' => 'sequenceDay',
            'columnName' => 'sequence_day',
            'type' => 'integer',
            'nullable' => false
        ]);

        // emailSubject
        $metadata->mapField([
            'fieldName' => 'emailSubject',
            'columnName' => 'email_subject',
            'type' => 'string',
            'length' => 255,
            'nullable' => false
        ]);

        // emailContent
        $metadata->mapField([
            'fieldName' => 'emailContent',
            'columnName' => 'email_content',
            'type' => 'text',
            'nullable' => false
        ]);

        // sendTime
        $metadata->mapField([
            'fieldName' => 'sendTime',
            'columnName' => 'send_time',
            'type' => 'datetime',
            'nullable' => false
        ]);

        // status
        $metadata->mapField([
            'fieldName' => 'status',
            'columnName' => 'status',
            'type' => 'string',
            'length' => 50,
            'nullable' => false
        ]);

        // messageId
        $metadata->mapField([
            'fieldName' => 'messageId',
            'columnName' => 'message_id',
            'type' => 'string',
            'length' => 255,
            'nullable' => true
        ]);

        // errorMessage
        $metadata->mapField([
            'fieldName' => 'errorMessage',
            'columnName' => 'error_message',
            'type' => 'text',
            'nullable' => true
        ]);

        // createdAt
        $metadata->mapField([
            'fieldName' => 'createdAt',
            'columnName' => 'created_at',
            'type' => 'datetime',
            'nullable' => false
        ]);

        // Relations
        // Domain (ManyToOne)
        $metadata->mapManyToOne([
            'fieldName' => 'domain',
            'targetEntity' => Domain::class,
            'joinColumns' => [
                [
                    'name' => 'domain_id',
                    'referencedColumnName' => 'id',
                    'nullable' => true,
                    'onDelete' => 'SET NULL'
                ]
            ]
        ]);

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

        // Contact (ManyToOne)
        $metadata->mapManyToOne([
            'fieldName' => 'contact',
            'targetEntity' => Contact::class,
            'joinColumns' => [
                [
                    'name' => 'contact_id',
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
        // Pas de lifecycle callbacks nécessaires pour SentLog
    }
}
