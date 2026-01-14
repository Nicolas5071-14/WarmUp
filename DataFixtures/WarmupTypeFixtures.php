<?php
// Chemin: src/DataFixtures/WarmupTypeFixtures.php

namespace MauticPlugin\MauticWarmUpBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use MauticPlugin\MauticWarmUpBundle\Entity\WarmupType;

class WarmupTypeFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $warmupTypes = [
            [
                'typeName' => 'Arithmetic (Linear)',
                'formulaType' => 'arithmetic',
                'description' => 'Increases by constant value each day: I_n = a + (n-1) * d',
                'defaultStartVolume' => 20,
                'defaultDurationDays' => 30,
                'defaultIncrementPercentage' => 10
            ],
            [
                'typeName' => 'Geometric (Exponential)',
                'formulaType' => 'geometric',
                'description' => 'Multiplies by constant factor each day: I_n = a * r^(n-1)',
                'defaultStartVolume' => 20,
                'defaultDurationDays' => 30,
                'defaultIncrementPercentage' => 15
            ],
            [
                'typeName' => 'Progressive (Standard)',
                'formulaType' => 'progressive',
                'description' => 'Gradual increase to reach stable state with slowing progression',
                'defaultStartVolume' => 20,
                'defaultDurationDays' => 30,
                'defaultIncrementPercentage' => 8
            ],
            [
                'typeName' => 'Flat (Constant)',
                'formulaType' => 'flat',
                'description' => 'Constant intensity throughout: I(t) = k',
                'defaultStartVolume' => 20,
                'defaultDurationDays' => 30,
                'defaultIncrementPercentage' => 0
            ],
            [
                'typeName' => 'Randomize',
                'formulaType' => 'randomize',
                'description' => 'Random variation to stimulate nervous system',
                'defaultStartVolume' => 20,
                'defaultDurationDays' => 30,
                'defaultIncrementPercentage' => 20
            ]
        ];

        foreach ($warmupTypes as $typeData) {
            $type = new WarmupType();
            $type->setTypeName($typeData['typeName']);
            $type->setFormulaType($typeData['formulaType']);
            $type->setDescription($typeData['description']);
            $type->setDefaultStartVolume($typeData['defaultStartVolume']);
            $type->setDefaultDurationDays($typeData['defaultDurationDays']);
            $type->setDefaultIncrementPercentage($typeData['defaultIncrementPercentage']);
            $type->setIsActive(true);
            
            $manager->persist($type);
        }

        $manager->flush();
    }
}
