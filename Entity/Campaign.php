<?php

namespace MauticPlugin\MauticWarmUpBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\File\File;
use MauticPlugin\MauticWarmUpBundle\Entity\WarmupType;


class Campaign
{
    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    const FREQUENCY_DAILY = 'daily';
    const FREQUENCY_WEEKLY = 'weekly';
    const FREQUENCY_MONTHLY = 'monthly';

    const CONTACT_SOURCE_MANUAL = 'manual';
    const CONTACT_SOURCE_MAUTIC = 'mautic';
    const CONTACT_SOURCE_CSV = 'csv';

    const DUPLICATE_HANDLING_SKIP = 'skip';
    const DUPLICATE_HANDLING_UPDATE = 'update';
    const DUPLICATE_HANDLING_ALLOW = 'allow';

    const SYNC_TYPE_ONE_TIME = 'one_time';
    const SYNC_TYPE_AUTO_SYNC = 'auto_sync';


    private $endDate;
    private $startTime;
    private $endTime;
    private $sendTime;
    private $sendFrequency = self::FREQUENCY_DAILY;
    private $startVolume = 20;
    private $durationDays = 42;
    private $maxContacts = 100;
    private $duplicateHandling = self::DUPLICATE_HANDLING_SKIP;
    private $syncType = self::SYNC_TYPE_ONE_TIME;
    private $contactSource = self::CONTACT_SOURCE_MANUAL;
    private $enableWeekends = true;
    private $enableRandomization = true;
    private $dailyIncrement = 10;
    private $subjectTemplate;
    private $customMessage;
    private $template;
    private $segmentId;


    private $id;
    private $campaignName;
    private $description;
    private $domain;
    private $warmupType;
    private $startDate;
    private $status = self::STATUS_DRAFT;
    private $totalContacts = 0;
    private $emailsSent = 0;
    private $dailyLimit;
    private $warmupDuration;
    private $sequences;
    private $contacts;
    private $createdAt;
    private $updatedAt;
    private $completedAt;
    private $emailsDelivered = 0;
    private $emailsOpened = 0;
    private $emailsClicked = 0;
    private $emailsBounced = 0;
    private $deliveryRate = 0;
    private $openRate = 0;
    private $clickRate = 0;
    private $bounceRate = 0;

    private $csvFile;

    private $manualContacts;

    private $totalSentToday = 0;
    private $totalSent = 0;


    private $sequenceName; // ← COLONNE MANQUANTE
    private $sequenceOrder; // ← Pour les séquences si nécessaire
    private $progress = 0.0; // ← Pour suivre la progression

    private $lastComputedVolume = null;

    private $warmupPlan = [];

    private $sequenceType = 'single';
    private ?array $emailSequences = [];


    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->sequences = new ArrayCollection();
        $this->contacts = new ArrayCollection();
    }

    public function getWarmupPlan(): array
    {
        return $this->warmupPlan ?: [];
    }

    public function setWarmupPlan(array $warmupPlan): self
    {
        $this->warmupPlan = $warmupPlan;
        return $this;
    }
    public function getManualContacts(): ?string
    {
        return $this->manualContacts;
    }

    public function getSequenceType(): string
    {
        return $this->sequenceType;
    }

    public function setSequenceType(string $sequenceType): self
    {
        $this->sequenceType = $sequenceType;
        return $this;
    }

    public function setEmailSequences(?array $emailSequences): self
    {
        $this->emailSequences = $emailSequences ?? [];
        return $this;
    }

    public function getEmailSequences(): array
    {
        return $this->emailSequences ?? [];
    }



    public function setManualContacts(?string $manualContacts): self
    {
        $this->manualContacts = $manualContacts;

        return $this;
    }

    public function getCsvFile(): ?File
    {
        return $this->csvFile;
    }

    /**
     * Set csvFile (pour le formulaire)
     */
    public function setCsvFile(?File $csvFile): self
    {
        $this->csvFile = $csvFile;
        return $this;
    }
    /**
     * Load metadata for Doctrine ORM
     *
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $metadata->setPrimaryTable([
            'name' => 'warmup_campaigns',
            'indexes' => [
                new ORM\Index(name: 'status_idx', columns: ['status']),
                new ORM\Index(name: 'start_date_idx', columns: ['startDate']),
                new ORM\Index(name: 'domain_idx', columns: ['domain_id']),
                new ORM\Index(name: 'warmup_type_idx', columns: ['warmup_type_id']),
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

        // campaignName
        $metadata->mapField([
            'fieldName' => 'campaignName',
            'columnName' => 'campaignName',
            'type' => 'string',
            'length' => 255,
            'nullable' => false
        ]);

        // last_computed_volume (pour warmup progressif α)
        $metadata->mapField([
            'fieldName' => 'lastComputedVolume',
            'columnName' => 'last_computed_volume',
            'type' => 'integer',
            'nullable' => true
        ]);

        // description
        $metadata->mapField([
            'fieldName' => 'description',
            'columnName' => 'description',
            'type' => 'text',
            'nullable' => true
        ]);

        $metadata->mapField([
            'fieldName' => 'totalSentToday',
            'columnName' => 'total_sent_today',  // Colonne dans la base
            'type' => 'integer',
            'nullable' => false,
            'options' => ['default' => 0]
        ]);

        // totalSent
        $metadata->mapField([
            'fieldName' => 'totalSent',
            'columnName' => 'total_sent',  // Colonne dans la base
            'type' => 'integer',
            'nullable' => false,
            'options' => ['default' => 0]
        ]);

        $metadata->mapField([
            'fieldName' => 'sequenceType',
            'columnName' => 'sequence_type',
            'type' => 'string',
            'length' => 20,
            'nullable' => false,
            'options' => ['default' => 'single']
        ]);

        // emailSequences
        $metadata->mapField([
            'fieldName' => 'emailSequences',
            'columnName' => 'email_sequences',
            'type' => 'json',
            'nullable' => true
        ]);


        // status
        $metadata->mapField([
            'fieldName' => 'status',
            'columnName' => 'status',
            'type' => 'string',
            'length' => 20,
            'nullable' => false,
            'options' => ['default' => 'draft']
        ]);

        // totalContacts
        $metadata->mapField([
            'fieldName' => 'totalContacts',
            'columnName' => 'totalContacts',
            'type' => 'integer',
            'nullable' => false,
            'options' => ['default' => 0]
        ]);

        // emailsSent
        $metadata->mapField([
            'fieldName' => 'emailsSent',
            'columnName' => 'emailsSent',
            'type' => 'integer',
            'nullable' => false,
            'options' => ['default' => 0]
        ]);

        // warmupPlan (JSON array pour stocker le plan jour par jour)
        $metadata->mapField([
            'fieldName' => 'warmupPlan',
            'columnName' => 'warmup_plan',
            'type' => 'json',
            'nullable' => true,
            'options' => [
                'default' => '[]',
                'comment' => 'JSON array of daily warmup volumes'
            ]
        ]);

        // emailsDelivered
        $metadata->mapField([
            'fieldName' => 'emailsDelivered',
            'columnName' => 'emails_delivered',
            'type' => 'integer',
            'nullable' => false,
            'options' => ['default' => 0]
        ]);

        // emailsOpened
        $metadata->mapField([
            'fieldName' => 'emailsOpened',
            'columnName' => 'emails_opened',
            'type' => 'integer',
            'nullable' => false,
            'options' => ['default' => 0]
        ]);

        // emailsClicked
        $metadata->mapField([
            'fieldName' => 'emailsClicked',
            'columnName' => 'emails_clicked',
            'type' => 'integer',
            'nullable' => false,
            'options' => ['default' => 0]
        ]);

        // emailsBounced
        $metadata->mapField([
            'fieldName' => 'emailsBounced',
            'columnName' => 'emails_bounced',
            'type' => 'integer',
            'nullable' => false,
            'options' => ['default' => 0]
        ]);

        // deliveryRate
        $metadata->mapField([
            'fieldName' => 'deliveryRate',
            'columnName' => 'delivery_rate',
            'type' => 'float',
            'nullable' => false,
            'options' => ['default' => 0]
        ]);

        // openRate
        $metadata->mapField([
            'fieldName' => 'openRate',
            'columnName' => 'open_rate',
            'type' => 'float',
            'nullable' => false,
            'options' => ['default' => 0]
        ]);

        // clickRate
        $metadata->mapField([
            'fieldName' => 'clickRate',
            'columnName' => 'click_rate',
            'type' => 'float',
            'nullable' => false,
            'options' => ['default' => 0]
        ]);

        // bounceRate
        $metadata->mapField([
            'fieldName' => 'bounceRate',
            'columnName' => 'bounce_rate',
            'type' => 'float',
            'nullable' => false,
            'options' => ['default' => 0]
        ]);

        // dailyLimit
        $metadata->mapField([
            'fieldName' => 'dailyLimit',
            'columnName' => 'daily_limit',
            'type' => 'integer',
            'nullable' => true
        ]);

        // warmupDuration
        $metadata->mapField([
            'fieldName' => 'warmupDuration',
            'columnName' => 'warmup_duration',
            'type' => 'integer',
            'nullable' => true
        ]);

        // startDate
        $metadata->mapField([
            'fieldName' => 'startDate',
            'columnName' => 'startDate',
            'type' => 'datetime',
            'nullable' => true
        ]);

        // createdAt
        $metadata->mapField([
            'fieldName' => 'createdAt',
            'columnName' => 'createdAt',
            'type' => 'datetime',
            'nullable' => false
        ]);

        // updatedAt
        $metadata->mapField([
            'fieldName' => 'updatedAt',
            'columnName' => 'updatedAt',
            'type' => 'datetime',
            'nullable' => false
        ]);

        // completedAt
        $metadata->mapField([
            'fieldName' => 'completedAt',
            'columnName' => 'completed_at',
            'type' => 'datetime',
            'nullable' => true
        ]);

        // endDate
        $metadata->mapField([
            'fieldName' => 'endDate',
            'columnName' => 'end_date',
            'type' => 'datetime',
            'nullable' => true
        ]);

        // startTime
        $metadata->mapField([
            'fieldName' => 'startTime',
            'columnName' => 'start_time',
            'type' => 'time',
            'nullable' => true
        ]);

        // endTime
        $metadata->mapField([
            'fieldName' => 'endTime',
            'columnName' => 'end_time',
            'type' => 'time',
            'nullable' => true
        ]);

        // sendTime
        $metadata->mapField([
            'fieldName' => 'sendTime',
            'columnName' => 'send_time',
            'type' => 'time',
            'nullable' => true
        ]);

        // sendFrequency
        $metadata->mapField([
            'fieldName' => 'sendFrequency',
            'columnName' => 'send_frequency',
            'type' => 'string',
            'length' => 20,
            'nullable' => false,
            'options' => ['default' => 'daily']
        ]);

        // startVolume
        $metadata->mapField([
            'fieldName' => 'startVolume',
            'columnName' => 'start_volume',
            'type' => 'integer',
            'nullable' => false,
            'options' => ['default' => 20]
        ]);

        // durationDays
        $metadata->mapField([
            'fieldName' => 'durationDays',
            'columnName' => 'duration_days',
            'type' => 'integer',
            'nullable' => false,
            'options' => ['default' => 42]
        ]);

        // maxContacts
        $metadata->mapField([
            'fieldName' => 'maxContacts',
            'columnName' => 'max_contacts',
            'type' => 'integer',
            'nullable' => false,
            'options' => ['default' => 100]
        ]);

        // duplicateHandling
        $metadata->mapField([
            'fieldName' => 'duplicateHandling',
            'columnName' => 'duplicate_handling',
            'type' => 'string',
            'length' => 20,
            'nullable' => false,
            'options' => ['default' => 'skip']
        ]);

        // syncType
        $metadata->mapField([
            'fieldName' => 'syncType',
            'columnName' => 'sync_type',
            'type' => 'string',
            'length' => 20,
            'nullable' => false,
            'options' => ['default' => 'one_time']
        ]);

        // contactSource
        $metadata->mapField([
            'fieldName' => 'contactSource',
            'columnName' => 'contact_source',
            'type' => 'string',
            'length' => 20,
            'nullable' => false,
            'options' => ['default' => 'manual']
        ]);

        // enableWeekends
        $metadata->mapField([
            'fieldName' => 'enableWeekends',
            'columnName' => 'enable_weekends',
            'type' => 'boolean',
            'nullable' => false,
            'options' => ['default' => true]
        ]);

        // enableRandomization
        $metadata->mapField([
            'fieldName' => 'enableRandomization',
            'columnName' => 'enable_randomization',
            'type' => 'boolean',
            'nullable' => false,
            'options' => ['default' => true]
        ]);

        // dailyIncrement
        $metadata->mapField([
            'fieldName' => 'dailyIncrement',
            'columnName' => 'daily_increment',
            'type' => 'integer',
            'nullable' => false,
            'options' => ['default' => 10]
        ]);

        // subjectTemplate
        $metadata->mapField([
            'fieldName' => 'subjectTemplate',
            'columnName' => 'subject_template',
            'type' => 'string',
            'length' => 255,
            'nullable' => true
        ]);

        // customMessage
        $metadata->mapField([
            'fieldName' => 'customMessage',
            'columnName' => 'custom_message',
            'type' => 'text',
            'nullable' => true
        ]);

        // segmentId
        $metadata->mapField([
            'fieldName' => 'segmentId',
            'columnName' => 'segment_id',
            'type' => 'integer',
            'nullable' => true
        ]);

        // Template (ManyToOne) - Ajoutez cette relation après les autres relations
        $metadata->mapManyToOne([
            'fieldName' => 'template',
            'targetEntity' => Template::class,
            'joinColumns' => [
                [
                    'name' => 'template_id',
                    'referencedColumnName' => 'id',
                    'nullable' => true,
                    'onDelete' => 'SET NULL'
                ]
            ]
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

        // WarmUpType (ManyToOne)
        $metadata->mapManyToOne([
            'fieldName' => 'warmupType',
            'targetEntity' => WarmupType::class,
            'joinColumns' => [
                [
                    'name' => 'warmup_type_id',
                    'referencedColumnName' => 'id',
                    'nullable' => false
                ]
            ]
        ]);

        // Sequences (OneToMany)
        $metadata->mapOneToMany([
            'fieldName' => 'sequences',
            'targetEntity' => Sequence::class,
            'mappedBy' => 'campaign',
            'cascade' => ['persist', 'remove'],
            'orderBy' => ['sequenceOrder' => 'ASC']
        ]);

        // sequence_name - COLONNE MANQUANTE
        $metadata->mapField([
            'fieldName' => 'sequenceName',
            'columnName' => 'sequence_name',
            'type' => 'string',
            'length' => 255,
            'nullable' => true
        ]);

        // sequence_order (optionnel - si vous en avez besoin)
        $metadata->mapField([
            'fieldName' => 'sequenceOrder',
            'columnName' => 'sequence_order',
            'type' => 'integer',
            'nullable' => true,
            'options' => ['default' => 0]
        ]);

        // progress (pour suivre la progression en pourcentage)
        $metadata->mapField([
            'fieldName' => 'progress',
            'columnName' => 'progress',
            'type' => 'float',
            'nullable' => false,
            'options' => ['default' => 0]
        ]);

        // Contacts (OneToMany)
        $metadata->mapOneToMany([
            'fieldName' => 'contacts',
            'targetEntity' => Contact::class,
            'mappedBy' => 'campaign',
            'cascade' => ['persist', 'remove']
        ]);
    }

    /**
     * Define Doctrine lifecycle callbacks
     *
     * @param ORM\ClassMetadata $metadata
     */



    public static function loadLifecycleCallbacks(ORM\ClassMetadata $metadata): void
    {
        $metadata->addLifecycleCallback('preUpdate', 'preUpdate');
    }

    public function getSequenceName(): ?string
    {
        return $this->sequenceName;
    }

    public function getLastComputedVolume(): ?int
    {
        return $this->lastComputedVolume;
    }

    public function setLastComputedVolume(?int $lastComputedVolume): self
    {
        $this->lastComputedVolume = $lastComputedVolume;
        return $this;
    }
    public function setSequenceName(?string $sequenceName): self
    {
        $this->sequenceName = $sequenceName;
        return $this;
    }

    public function getSequenceOrder(): int
    {
        return $this->sequenceOrder ?? 0;
    }

    public function setSequenceOrder(int $sequenceOrder): self
    {
        $this->sequenceOrder = $sequenceOrder;
        return $this;
    }

    // Le getter progress existe déjà (getProgress()) mais vous pouvez ajouter un setter
    public function setProgress(float $progress): self
    {
        $this->progress = $progress;
        return $this;
    }

    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();

        // Mettre à jour les taux si des emails ont été envoyés
        if ($this->emailsSent > 0) {
            $this->deliveryRate = ($this->emailsDelivered / $this->emailsSent) * 100;
            $this->openRate = ($this->emailsOpened / $this->emailsSent) * 100;
            $this->clickRate = ($this->emailsClicked / $this->emailsSent) * 100;
            $this->bounceRate = ($this->emailsBounced / $this->emailsSent) * 100;

            // Mettre à jour la progression
            $this->progress = $this->getProgress();
        }
    }
    // Getters and Setters...



    public function getTotalSentToday(): int
    {
        return $this->totalSentToday ?? 0;
    }

    public function setTotalSentToday(int $totalSentToday): self
    {
        $this->totalSentToday = $totalSentToday;
        return $this;
    }

    public function getTotalSent(): int
    {
        return $this->totalSent ?? 0;
    }

    public function setTotalSent(int $totalSent): self
    {
        $this->totalSent = $totalSent;
        return $this;
    }

    // Optionnel : méthodes d'incrémentation
    public function incrementTotalSentToday(int $count = 1): self
    {
        $this->totalSentToday += $count;
        return $this;
    }

    public function incrementTotalSent(int $count = 1): self
    {
        $this->totalSent += $count;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function getSendTime(): ?\DateTimeInterface
    {
        return $this->sendTime;
    }

    public function setSendTime(?\DateTimeInterface $sendTime): self
    {
        $this->sendTime = $sendTime;
        return $this;
    }

    public function getSendFrequency(): string
    {
        return $this->sendFrequency;
    }

    public function setSendFrequency(string $sendFrequency): self
    {
        $this->sendFrequency = $sendFrequency;
        return $this;
    }

    public function getStartVolume(): int
    {
        return $this->startVolume;
    }

    public function setStartVolume(int $startVolume): self
    {
        $this->startVolume = $startVolume;
        return $this;
    }

    public function getDurationDays(): int
    {
        return $this->durationDays;
    }

    public function setDurationDays(int $durationDays): self
    {
        $this->durationDays = $durationDays;
        return $this;
    }

    public function getMaxContacts(): int
    {
        return $this->maxContacts;
    }

    public function setMaxContacts(int $maxContacts): self
    {
        $this->maxContacts = $maxContacts;
        return $this;
    }

    public function getDuplicateHandling(): string
    {
        return $this->duplicateHandling;
    }

    public function setDuplicateHandling(string $duplicateHandling): self
    {
        $this->duplicateHandling = $duplicateHandling;
        return $this;
    }

    public function getSyncType(): string
    {
        return $this->syncType;
    }

    public function setSyncType(string $syncType): self
    {
        $this->syncType = $syncType;
        return $this;
    }

    public function getContactSource(): string
    {
        return $this->contactSource;
    }

    public function setContactSource(string $contactSource): self
    {
        $this->contactSource = $contactSource;
        return $this;
    }

    public function isEnableWeekends(): bool
    {
        return $this->enableWeekends;
    }

    public function setEnableWeekends(bool $enableWeekends): self
    {
        $this->enableWeekends = $enableWeekends;
        return $this;
    }

    public function isEnableRandomization(): bool
    {
        return $this->enableRandomization;
    }

    public function setEnableRandomization(bool $enableRandomization): self
    {
        $this->enableRandomization = $enableRandomization;
        return $this;
    }

    public function getDailyIncrement(): int
    {
        return $this->dailyIncrement;
    }

    public function setDailyIncrement(int $dailyIncrement): self
    {
        $this->dailyIncrement = $dailyIncrement;
        return $this;
    }

    public function getSubjectTemplate(): ?string
    {
        return $this->subjectTemplate;
    }

    public function setSubjectTemplate(?string $subjectTemplate): self
    {
        $this->subjectTemplate = $subjectTemplate;
        return $this;
    }

    public function getCustomMessage(): ?string
    {
        return $this->customMessage;
    }

    public function setCustomMessage(?string $customMessage): self
    {
        $this->customMessage = $customMessage;
        return $this;
    }

    public function getTemplate(): ?Template
    {
        return $this->template;
    }

    public function setTemplate(?Template $template): self
    {
        $this->template = $template;
        return $this;
    }

    public function getSegmentId(): ?int
    {
        return $this->segmentId;
    }

    public function setSegmentId(?int $segmentId): self
    {
        $this->segmentId = $segmentId;
        return $this;
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCampaignName(): ?string
    {
        return $this->campaignName;
    }

    public function setCampaignName(string $campaignName): self
    {
        $this->campaignName = $campaignName;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
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

    public function getWarmupType(): ?WarmupType
    {
        return $this->warmupType;
    }

    public function setWarmupType(?WarmupType $warmupType): self
    {
        $this->warmupType = $warmupType;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        // Si le statut passe à "completed", enregistrer la date de fin
        if ($status === self::STATUS_COMPLETED && !$this->completedAt) {
            $this->completedAt = new \DateTime();
        }

        return $this;
    }

    public function getTotalContacts(): int
    {
        return $this->totalContacts;
    }

    public function setTotalContacts(int $totalContacts): self
    {
        $this->totalContacts = $totalContacts;
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

    public function incrementEmailsSent(int $count = 1): self
    {
        $this->emailsSent += $count;
        return $this;
    }

    public function getDailyLimit(): ?int
    {
        return $this->dailyLimit;
    }

    public function setDailyLimit(?int $dailyLimit): self
    {
        $this->dailyLimit = $dailyLimit;
        return $this;
    }

    public function getWarmupDuration(): ?int
    {
        return $this->warmupDuration;
    }

    public function setWarmupDuration(?int $warmupDuration): self
    {
        $this->warmupDuration = $warmupDuration;
        return $this;
    }

    /**
     * @return Collection|Sequence[]
     */
    public function getSequences(): Collection
    {
        return $this->sequences;
    }

    public function addSequence(Sequence $sequence): self
    {
        if (!$this->sequences->contains($sequence)) {
            $this->sequences[] = $sequence;
            $sequence->setCampaign($this);
        }
        return $this;
    }

    public function removeSequence(Sequence $sequence): self
    {
        if ($this->sequences->removeElement($sequence)) {
            // set the owning side to null (unless already changed)
            if ($sequence->getCampaign() === $this) {
                $sequence->setCampaign(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection|Contact[]
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(Contact $contact): self
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts[] = $contact;
            $contact->setCampaign($this);
            $this->totalContacts++;
        }
        return $this;
    }

    public function removeContact(Contact $contact): self
    {
        if ($this->contacts->removeElement($contact)) {
            // set the owning side to null (unless already changed)
            if ($contact->getCampaign() === $this) {
                $contact->setCampaign(null);
            }
            $this->totalContacts--;
        }
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getEmailsDelivered(): int
    {
        return $this->emailsDelivered;
    }

    public function setEmailsDelivered(int $emailsDelivered): self
    {
        $this->emailsDelivered = $emailsDelivered;
        return $this;
    }

    public function incrementEmailsDelivered(int $count = 1): self
    {
        $this->emailsDelivered += $count;
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

    public function incrementEmailsOpened(int $count = 1): self
    {
        $this->emailsOpened += $count;
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

    public function incrementEmailsClicked(int $count = 1): self
    {
        $this->emailsClicked += $count;
        return $this;
    }

    public function getEmailsBounced(): int
    {
        return $this->emailsBounced;
    }

    public function setEmailsBounced(int $emailsBounced): self
    {
        $this->emailsBounced = $emailsBounced;
        return $this;
    }

    public function incrementEmailsBounced(int $count = 1): self
    {
        $this->emailsBounced += $count;
        return $this;
    }

    public function getDeliveryRate(): float
    {
        return $this->deliveryRate;
    }

    public function setDeliveryRate(float $deliveryRate): self
    {
        $this->deliveryRate = $deliveryRate;
        return $this;
    }

    public function getOpenRate(): float
    {
        return $this->openRate;
    }

    public function setOpenRate(float $openRate): self
    {
        $this->openRate = $openRate;
        return $this;
    }

    public function getClickRate(): float
    {
        return $this->clickRate;
    }

    public function setClickRate(float $clickRate): self
    {
        $this->clickRate = $clickRate;
        return $this;
    }

    public function getBounceRate(): float
    {
        return $this->bounceRate;
    }

    public function setBounceRate(float $bounceRate): self
    {
        $this->bounceRate = $bounceRate;
        return $this;
    }

    /**
     * Check if campaign can send emails
     */
    public function canSend(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->domain
            && $this->domain->isActive()
            && $this->domain->canSendMoreToday();
    }


    public function getNextSequence(Contact $contact): ?Sequence
    {
        $sentCount = $contact->getSentCount();

        foreach ($this->sequences as $sequence) {
            if ($sequence->getSequenceOrder() === ($sentCount + 1)) {
                return $sequence;
            }
        }

        return null;
    }

    /**
     * Check if campaign is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if campaign is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if campaign is draft
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if campaign is paused
     */
    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    /**
     * Check if campaign has started
     */
    public function hasStarted(): bool
    {
        return $this->startDate && $this->startDate <= new \DateTime();
    }

    /**
     * Get campaign progress percentage
     */
    /**
     * Get campaign progress percentage
     */
    public function getProgress(): float
    {
        // If progress is already calculated, use it
        if ($this->progress > 0) {
            return $this->progress;
        }

        // Calculate dynamically: emails sent (contacts processed) / total contacts
        $totalContacts = $this->getTotalContacts();
        $emailsSent = $this->getEmailsSent();

        if ($totalContacts === 0) {
            return 0;
        }

        $progress = ($emailsSent / $totalContacts) * 100;
        $calculatedProgress = min(100, round($progress, 2));

        // Store the calculation for later
        $this->progress = $calculatedProgress;

        return $calculatedProgress;
    }
    /**
     * Get remaining emails to send
     */
    public function getRemainingEmails(): int
    {
        $totalContacts = $this->getTotalContacts();
        $emailsSent = $this->getEmailsSent();

        return max(0, $totalContacts - $emailsSent);
    }

    /**
     * Get estimated completion date
     */
    public function getEstimatedCompletionDate(): ?\DateTimeInterface
    {
        if (!$this->startDate || !$this->isActive() || $this->getProgress() >= 100) {
            return null;
        }

        $remainingContacts = $this->getRemainingEmails();
        $durationDays = $this->getDurationDays();
        $totalContacts = $this->getTotalContacts();

        if ($durationDays <= 0 || $totalContacts <= 0) {
            return null;
        }

        // Calculate ideal daily volume (contacts per day)
        $idealDailyVolume = ceil($totalContacts / $durationDays);

        if ($idealDailyVolume <= 0) {
            return null;
        }

        $daysRemaining = ceil($remainingContacts / $idealDailyVolume);
        $completionDate = clone $this->startDate;
        $completionDate->modify("+{$daysRemaining} days");

        return $completionDate;
    }

    public function getCurrentDailyAverage(): float
    {
        if (!$this->startDate) {
            return 0;
        }

        $now = new \DateTime();
        $interval = $this->startDate->diff($now);
        $daysElapsed = max(1, (int) $interval->format('%a'));

        return round($this->emailsSent / $daysElapsed, 1);
    }

    public function getDaysRemaining(): int
    {
        $remainingContacts = $this->getRemainingEmails();
        $averageDaily = $this->getCurrentDailyAverage();

        if ($averageDaily <= 0) {
            return 0;
        }

        return (int) ceil($remainingContacts / $averageDaily);
    }


    /**
     * Get estimated completion date
     */


    /**
     * Get campaign statistics as array
     */
    public function getStatistics(): array
    {
        $totalContacts = $this->totalContacts;
        $emailsSent = $this->emailsSent;
        $durationDays = $this->getDurationDays();

        return [
            'total_contacts' => $totalContacts,
            'emails_sent' => $emailsSent,
            'contacts_processed' => $emailsSent, // 1 email = 1 contact processed
            'contacts_remaining' => max(0, $totalContacts - $emailsSent),
            'progress_percentage' => $this->getProgress(),

            // Planning metrics
            'duration_days' => $durationDays,
            'ideal_daily_volume' => $durationDays > 0 ? ceil($totalContacts / $durationDays) : 0,
            'current_daily_average' => $this->getCurrentDailyAverage(),
            'estimated_completion_date' => $this->getEstimatedCompletionDate() ?
                $this->getEstimatedCompletionDate()->format('Y-m-d H:i:s') : null,
            'days_remaining' => $this->getDaysRemaining(),

            // Performance metrics
            'delivery_rate' => $this->deliveryRate,
            'open_rate' => $this->openRate,
            'click_rate' => $this->clickRate,
            'bounce_rate' => $this->bounceRate,

            // Campaign info
            'status' => $this->status,
            'has_started' => $this->hasStarted(),
            'can_send' => $this->canSend(),
        ];
    }

    /**
     * Get campaign name (alias for getCampaignName for compatibility)
     */
    public function getName(): ?string
    {
        return $this->campaignName;
    }

    /**
     * Set campaign name (alias for setCampaignName for compatibility)
     */
    public function setName(string $name): self
    {
        $this->campaignName = $name;
        return $this;
    }

    /**
     * Get remaining emails
     */


    /**
     * Get status label for display
     */
    public function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PAUSED => 'Paused',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
        ];

        return $labels[$this->status] ?? 'Unknown';
    }

    /**
     * Get status color for UI
     */
    public function getStatusColor(): string
    {
        $colors = [
            self::STATUS_DRAFT => 'default',
            self::STATUS_ACTIVE => 'success',
            self::STATUS_PAUSED => 'warning',
            self::STATUS_COMPLETED => 'info',
            self::STATUS_FAILED => 'danger',
        ];

        return $colors[$this->status] ?? 'default';
    }

    /**
     * Validate campaign configuration
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->campaignName)) {
            $errors[] = 'Campaign name is required';
        }

        if (strlen($this->campaignName) > 255) {
            $errors[] = 'Campaign name cannot exceed 255 characters';
        }

        if (!$this->startDate) {
            $errors[] = 'Start date is required';
        } elseif ($this->startDate < new \DateTime()) {
            $errors[] = 'Start date cannot be in the past';
        }

        if (!$this->domain) {
            $errors[] = 'Domain is required';
        } elseif (!$this->domain->isVerified()) {
            $errors[] = 'Selected domain is not verified';
        }

        if (!$this->warmupType) {
            $errors[] = 'Warm-up type is required';
        }

        if ($this->totalContacts < 1) {
            $errors[] = 'At least one contact is required';
        }

        if (count($this->sequences) === 0) {
            $errors[] = 'At least one sequence is required';
        }

        if ($this->dailyLimit !== null && $this->dailyLimit < 1) {
            $errors[] = 'Daily limit must be at least 1';
        }

        if ($this->warmupDuration !== null && $this->warmupDuration < 1) {
            $errors[] = 'Warm-up duration must be at least 1 day';
        }

        return $errors;
    }

    /**
     * Check if campaign is valid for activation
     */
    public function isValidForActivation(): bool
    {
        return empty($this->validate());
    }

    /**
     * Get campaign as array for API/JSON
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'campaignName' => $this->campaignName,
            'description' => $this->description,
            'status' => $this->status,
            'statusLabel' => $this->getStatusLabel(),
            'statusColor' => $this->getStatusColor(),
            'totalContacts' => $this->totalContacts,
            'emailsSent' => $this->emailsSent,
            'dailyLimit' => $this->dailyLimit,
            'warmupDuration' => $this->warmupDuration,
            'progress' => $this->getProgress(),
            'remainingEmails' => $this->getRemainingEmails(),
            'deliveryRate' => $this->deliveryRate,
            'openRate' => $this->openRate,
            'clickRate' => $this->clickRate,
            'bounceRate' => $this->bounceRate,
            'domain' => $this->domain ? [
                'id' => $this->domain->getId(),
                'domainName' => $this->domain->getDomainName(),
            ] : null,
            'warmupType' => $this->warmupType ? [
                'id' => $this->warmupType->getId(),
                'typeName' => $this->warmupType->getTypeName(),
            ] : null,
            'startDate' => $this->startDate ? $this->startDate->format('Y-m-d H:i:s') : null,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
            'completedAt' => $this->completedAt ? $this->completedAt->format('Y-m-d H:i:s') : null,
            'sequencesCount' => count($this->sequences),
            'canSend' => $this->canSend(),
            'hasStarted' => $this->hasStarted(),
            'isCompleted' => $this->isCompleted(),
            'estimatedCompletion' => $this->getEstimatedCompletionDate() ?
                $this->getEstimatedCompletionDate()->format('Y-m-d H:i:s') : null,
        ];
    }
}