<?php


namespace MauticPlugin\MauticWarmUpBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
class WarmupType
{
    private $id;
    private $typeName;
    private $description;
    private $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $metadata->setPrimaryTable([
            'name' => 'warmup_types',
            'indexes' => [
                new ORM\Index(name: 'type_name_idx', columns: ['typeName']),
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

        // description
        $metadata->mapField([
            'fieldName' => 'description',
            'type' => 'text',
            'nullable' => true
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
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