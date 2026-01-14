<?php

namespace MauticPlugin\MauticWarmUpBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

class WarmupType
{
    private $id;
    private $typeName;
    private $description;
    private $createdAt;

    // NOUVEAUX CHAMPS
    private $formulaType; // arithmetic, geometric, progressive, flat, randomize
    private $defaultStartVolume;
    private $defaultDurationDays;
    private $defaultIncrementPercentage;
    private $isActive;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->isActive = true;
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $metadata->setPrimaryTable([
            'name' => 'warmup_types',
            'indexes' => [
                new ORM\Index(name: 'type_name_idx', columns: ['typeName']),
                new ORM\Index(name: 'formula_type_idx', columns: ['formulaType']),
                new ORM\Index(name: 'is_active_idx', columns: ['isActive']),
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

        // typeName
        $metadata->mapField([
            'fieldName' => 'typeName',
            'columnName' => 'typeName',
            'type' => 'string',
            'length' => 100,
            'nullable' => false
        ]);

        // formulaType
        $metadata->mapField([
            'fieldName' => 'formulaType',
            'columnName' => 'formulaType',
            'type' => 'string',
            'length' => 50,
            'nullable' => false,
            'default' => 'arithmetic'
        ]);

        // description
        $metadata->mapField([
            'fieldName' => 'description',
            'type' => 'text',
            'nullable' => true
        ]);

        // defaultStartVolume
        $metadata->mapField([
            'fieldName' => 'defaultStartVolume',
            'columnName' => 'defaultStartVolume',
            'type' => 'integer',
            'nullable' => true,
            'default' => 20
        ]);

        // defaultDurationDays
        $metadata->mapField([
            'fieldName' => 'defaultDurationDays',
            'columnName' => 'defaultDurationDays',
            'type' => 'integer',
            'nullable' => true,
            'default' => 30
        ]);

        // defaultIncrementPercentage
        $metadata->mapField([
            'fieldName' => 'defaultIncrementPercentage',
            'columnName' => 'defaultIncrementPercentage',
            'type' => 'float',
            'nullable' => true,
            'default' => 10.0
        ]);

        // isActive
        $metadata->mapField([
            'fieldName' => 'isActive',
            'columnName' => 'isActive',
            'type' => 'boolean',
            'nullable' => false,
            'default' => true
        ]);

        // createdAt
        $metadata->mapField([
            'fieldName' => 'createdAt',
            'columnName' => 'createdAt',
            'type' => 'datetime',
            'nullable' => false
        ]);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeName(): ?string
    {
        return $this->typeName;
    }

    public function setTypeName(string $typeName): self
    {
        $this->typeName = $typeName;
        return $this;
    }

    public function getFormulaType(): ?string
    {
        return $this->formulaType;
    }

    public function setFormulaType(string $formulaType): self
    {
        $this->formulaType = $formulaType;
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

    public function getDefaultStartVolume(): ?int
    {
        return $this->defaultStartVolume;
    }

    public function setDefaultStartVolume(?int $defaultStartVolume): self
    {
        $this->defaultStartVolume = $defaultStartVolume;
        return $this;
    }

    public function getDefaultDurationDays(): ?int
    {
        return $this->defaultDurationDays;
    }

    public function setDefaultDurationDays(?int $defaultDurationDays): self
    {
        $this->defaultDurationDays = $defaultDurationDays;
        return $this;
    }

    public function getDefaultIncrementPercentage(): ?float
    {
        return $this->defaultIncrementPercentage;
    }

    public function setDefaultIncrementPercentage(?float $defaultIncrementPercentage): self
    {
        $this->defaultIncrementPercentage = $defaultIncrementPercentage;
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

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}