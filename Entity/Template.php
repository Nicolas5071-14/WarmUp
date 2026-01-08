<?php

namespace MauticPlugin\MauticWarmUpBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="MauticPlugin\MauticWarmUpBundle\Repository\TemplateRepository")
 * @ORM\Table(name="warmup_templates")
 */
class Template
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $templateName;

    /**
     * @ORM\Column(type="string", length=50, options={"default": "email"})
     */
    private $templateType = 'email';

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $subject;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $content;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $htmlContent;

    /**
     * @ORM\Column(type="boolean", options={"default": true})
     */
    private $isActive = true;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

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

    public function getTemplateName(): ?string
    {
        return $this->templateName;
    }

    public function setTemplateName(string $templateName): self
    {
        $this->templateName = $templateName;
        return $this;
    }

    public function getTemplateType(): string
    {
        return $this->templateType;
    }

    public function setTemplateType(string $templateType): self
    {
        $this->templateType = $templateType;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getHtmlContent(): ?string
    {
        return $this->htmlContent;
    }

    public function setHtmlContent(?string $htmlContent): self
    {
        $this->htmlContent = $htmlContent;
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

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
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

    /**
     * Get text content (fallback to stripped HTML)
     */
    public function getTextContent(): string
    {
        if ($this->content) {
            return $this->content;
        }

        if ($this->htmlContent) {
            return strip_tags($this->htmlContent);
        }

        return '';
    }

    /**
     * Clone template
     */
    public function __clone()
    {
        if ($this->id) {
            $this->id = null;
            $this->templateName = $this->templateName . ' (Copy)';
            $this->createdAt = new \DateTime();
            $this->updatedAt = new \DateTime();
        }
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $metadata->setPrimaryTable([
            'name' => 'warmup_templates',
            'indexes' => [
                new ORM\Index(name: 'template_name_idx', columns: ['templateName']),
                new ORM\Index(name: 'template_type_idx', columns: ['templateType']),
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

        // templateName
        $metadata->mapField([
            'fieldName' => 'templateName',
            'columnName' => 'templateName',
            'type' => 'string',
            'length' => 255,
            'nullable' => false
        ]);

        // templateType
        $metadata->mapField([
            'fieldName' => 'templateType',
            'columnName' => 'templateType',
            'type' => 'string',
            'length' => 50,
            'nullable' => false,
            'options' => ['default' => 'email']
        ]);

        // subject
        $metadata->mapField([
            'fieldName' => 'subject',
            'columnName' => 'subject',
            'type' => 'string',
            'length' => 255,
            'nullable' => true
        ]);

        // content
        $metadata->mapField([
            'fieldName' => 'content',
            'columnName' => 'content',
            'type' => 'text',
            'nullable' => true
        ]);

        // htmlContent
        $metadata->mapField([
            'fieldName' => 'htmlContent',
            'columnName' => 'htmlContent',
            'type' => 'text',
            'nullable' => true
        ]);

        // isActive
        $metadata->mapField([
            'fieldName' => 'isActive',
            'columnName' => 'isActive',
            'type' => 'boolean',
            'nullable' => false,
            'options' => ['default' => true]
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
