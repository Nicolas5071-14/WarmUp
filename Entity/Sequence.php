<?php

namespace MauticPlugin\MauticWarmUpBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
// use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

/**
 * @ORM\Entity(repositoryClass="MauticPlugin\MauticWarmUpBundle\Repository\SequenceRepository")
 * @ORM\Table(name="warmup_sequences")
 */
class Sequence
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Campaign", inversedBy="sequences")
     * @ORM\JoinColumn(name="campaign_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $campaign;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $sequenceName;

    /**
     * @ORM\Column(type="integer")
     */
    private $sequenceOrder;

    /**
     * @ORM\Column(type="integer")
     */
    private $daysAfterPrevious;

    /**
     * @ORM\Column(type="text")
     */
    private $subjectTemplate;

    /**
     * @ORM\Column(type="text")
     */
    private $bodyTemplate;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isActive;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    // public static function loadMetadata(ORM\ClassMetadata $metadata): void
    // {
    //     $builder = new ClassMetadataBuilder($metadata);
    //     $builder->setTable('warmup_sequences');

    //     $builder->addId();
    //     $builder->createManyToOne('campaign', 'Campaign')
    //         ->addJoinColumn('campaign_id', 'id', false, false, 'CASCADE')
    //         ->build();
    //     $builder->addField('sequenceName', 'string', ['length' => 255]);
    //     $builder->addField('sequenceOrder', 'integer');
    //     $builder->addField('daysAfterPrevious', 'integer');
    //     $builder->addField('subjectTemplate', 'text');
    //     $builder->addField('bodyTemplate', 'text');
    //     $builder->addField('isActive', 'boolean');
    //     $builder->addField('createdAt', 'datetime');
    // }
    /**
     * @ORM\Column(type="datetime")
     */
    private $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getSequenceOrder(): int
    {
        return $this->sequenceOrder;
    }

    public function setSequenceOrder(int $sequenceOrder): self
    {
        $this->sequenceOrder = $sequenceOrder;
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

    public function getDelayDays(): int
    {
        return $this->delayDays;
    }

    public function setDelayDays(int $delayDays): self
    {
        $this->delayDays = $delayDays;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $metadata->setPrimaryTable([
            'name' => 'warmup_sequences',
            'indexes' => [
                new ORM\Index(name: 'campaign_idx', columns: ['campaign_id']),
                new ORM\Index(name: 'sequence_order_idx', columns: ['sequence_order']),
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

        // sequenceName
        $metadata->mapField([
            'fieldName' => 'sequenceName',
            'columnName' => 'sequence_name',
            'type' => 'string',
            'length' => 255,
            'nullable' => false
        ]);

        // sequenceOrder
        $metadata->mapField([
            'fieldName' => 'sequenceOrder',
            'columnName' => 'sequenceOrder',
            'type' => 'integer',
            'nullable' => false
        ]);

        // daysAfterPrevious
        $metadata->mapField([
            'fieldName' => 'daysAfterPrevious',
            'columnName' => 'days_after_previous',
            'type' => 'integer',
            'nullable' => false
        ]);

        // subjectTemplate
        $metadata->mapField([
            'fieldName' => 'subjectTemplate',
            'columnName' => 'subject_template',
            'type' => 'text',
            'nullable' => false
        ]);

        // bodyTemplate
        $metadata->mapField([
            'fieldName' => 'bodyTemplate',
            'columnName' => 'body_template',
            'type' => 'text',
            'nullable' => false
        ]);

        // isActive
        $metadata->mapField([
            'fieldName' => 'isActive',
            'columnName' => 'is_active',
            'type' => 'boolean',
            'nullable' => false
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
