<?php
// Chemin: src/Repository/WarmupTypeRepository.php

namespace MauticPlugin\MauticWarmUpBundle\Repository;

use Doctrine\ORM\EntityRepository;

class WarmupTypeRepository extends EntityRepository
{
    public function findActiveTypes()
    {
        return $this->createQueryBuilder('wt')
            ->where('wt.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('wt.typeName', 'ASC')
            ->getQuery()
            ->getResult();
    }
    
    public function findByFormulaType(string $formulaType)
    {
        return $this->createQueryBuilder('wt')
            ->where('wt.formulaType = :formulaType')
            ->andWhere('wt.isActive = :active')
            ->setParameter('formulaType', $formulaType)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }
}
