<?php

namespace MauticPlugin\MauticWarmUpBundle\Model;

use Psr\Log\LoggerInterface;

class WarmupProgressionCalculator
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Calcule le plan de warmup selon le type de progression
     */
    public function calculate(
        int $totalEmails,
        int $days,
        string $progressionType = 'arithmetic',
        ?float $ratio = null,
        ?int $startingBaseline = null,
        ?int $increasePerDay = null,
    ): array {
        $this->logger->info("Calculating warmup progression", [
            'total_emails' => $totalEmails,
            'days' => $days,
            'type' => $progressionType,
        ]);

        return match ($progressionType) {
            'geometric' => $this->calculateGeometric($totalEmails, $days, $ratio ?? 1.5),
            'geometric_alpha' => $this->calculateGeometricAlpha($totalEmails, $days, 0.1),
            'progressive' => $this->calculateProgressive($totalEmails, $days),
            'arithmetic' => $this->calculateArithmetic($totalEmails, $days, $startingBaseline, $increasePerDay),
            'flat' => $this->calculateFlat($totalEmails, $days),
            'randomize' => $this->calculateRandomize($totalEmails, $days),
            default => $this->calculateArithmetic($totalEmails, $days)
        };
    }

    /**
     * Progression Arithmétique (linéaire)
     */
    private function calculateArithmetic(
        int $totalEmails,
        int $days,
        ?int $startingBaseline = null,
        ?int $increasePerDay = null
    ): array {
        $plan = [];
        $totalSent = 0;

        // Mode personnalisé avec baseline et increase
        if ($startingBaseline !== null && $increasePerDay !== null) {
            for ($day = 1; $day <= $days; $day++) {
                $emails = $startingBaseline + ($increasePerDay * ($day - 1));

                if ($day < $days && ($totalSent + $emails) > $totalEmails) {
                    $emails = $totalEmails - $totalSent;
                }

                $plan[] = [
                    'day' => $day,
                    'emails' => max(0, $emails)
                ];

                $totalSent += $emails;

                if ($totalSent >= $totalEmails) {
                    break;
                }
            }
            // Ajuster le dernier jour si nécessaire
            if ($totalSent < $totalEmails && count($plan) > 0) {
                $plan[count($plan) - 1]['emails'] += ($totalEmails - $totalSent);
            } elseif ($totalSent > $totalEmails && count($plan) > 0) {
                $plan[count($plan) - 1]['emails'] -= ($totalSent - $totalEmails);
            }

            while (count($plan) < $days) {
                $plan[] = [
                    'day' => count($plan) + 1,
                    'emails' => 0
                ];
            }

            return $plan;
        }

        // Mode automatique
        $sumDays = ($days * ($days + 1)) / 2;
        $exactPart = $totalEmails / $sumDays;

        for ($day = 1; $day < $days; $day++) {
            $emails = (int) floor($exactPart * $day);
            $plan[] = [
                'day' => $day,
                'emails' => $emails
            ];
            $totalSent += $emails;
        }

        // Le dernier jour reçoit tous les emails restants
        $lastDayEmails = $totalEmails - $totalSent;
        $plan[] = [
            'day' => $days,
            'emails' => $lastDayEmails
        ];

        return $plan;
    }

    /**
     * Progression Géométrique (exponentielle)
     */
    private function calculateGeometric(int $totalEmails, int $days, float $ratio): array
    {
        if ($ratio <= 1.0) {
            $this->logger->warning("Geometric ratio must be > 1, using 1.5");
            $ratio = 1.5;
        }

        // Calculer le premier terme
        $sumRatios = (pow($ratio, $days) - 1) / ($ratio - 1);
        $firstDayEmails = $totalEmails / $sumRatios;

        $plan = [];
        $totalSent = 0;

        for ($day = 1; $day < $days; $day++) {
            $emails = (int) floor($firstDayEmails * pow($ratio, $day - 1));
            $plan[] = [
                'day' => $day,
                'emails' => $emails
            ];
            $totalSent += $emails;
        }

        $lastDayEmails = $totalEmails - $totalSent;
        $plan[] = [
            'day' => $days,
            'emails' => $lastDayEmails
        ];

        return $plan;
    }

    /**
     * Progression Géométrique avec Alpha
     */
    private function calculateGeometricAlpha(int $totalEmails, int $days, float $alpha = 0.1): array
    {
        if ($totalEmails <= 0) {
            throw new \InvalidArgumentException("Total emails must be positive");
        }
        if ($days <= 0) {
            throw new \InvalidArgumentException("Days must be positive");
        }

        $alpha = 0.1;
        $minAlphaPossible = 1.0 / $days;

        if ($alpha < $minAlphaPossible) {
            $alpha = $minAlphaPossible * 1.05;
        }

        // Résoudre numériquement la raison q
        $q = $this->solveForRatioIncreasing($days, $alpha);
        $qPowerN = pow($q, $days);
        $u1 = $totalEmails * ($q - 1) / ($qPowerN - 1);

        $plan = [];
        $totalSent = 0;

        for ($day = 1; $day < $days; $day++) {
            $exactEmails = $u1 * pow($q, $day - 1);
            $emails = (int) floor($exactEmails);

            $plan[] = [
                'day' => $day,
                'emails' => $emails
            ];

            $totalSent += $emails;
        }

        $lastDayEmails = $totalEmails - $totalSent;
        $plan[] = [
            'day' => $days,
            'emails' => $lastDayEmails
        ];

        return $plan;
    }

    /**
     * Progression Progressive (hybride)
     */
    private function calculateProgressive(int $totalEmails, int $days): array
    {
        $sumDays = ($days * ($days + 1)) / 2;
        $exactPart = $totalEmails / $sumDays;

        $plan = [];
        $totalSent = 0;

        for ($day = 1; $day < $days; $day++) {
            $emails = (int) floor($exactPart * $day);
            $plan[] = [
                'day' => $day,
                'emails' => $emails
            ];
            $totalSent += $emails;
        }

        $lastDayEmails = $totalEmails - $totalSent;
        $plan[] = [
            'day' => $days,
            'emails' => $lastDayEmails
        ];

        return $plan;
    }

    /**
     * Progression Flat (Distribution Uniforme)
     */
    private function calculateFlat(int $totalEmails, int $days): array
    {
        $emailsPerDay = (int) floor($totalEmails / $days);

        $plan = [];
        $totalSent = 0;

        for ($day = 1; $day < $days; $day++) {
            $plan[] = [
                'day' => $day,
                'emails' => $emailsPerDay
            ];
            $totalSent += $emailsPerDay;
        }

        $lastDayEmails = $totalEmails - $totalSent;
        $plan[] = [
            'day' => $days,
            'emails' => $lastDayEmails
        ];

        return $plan;
    }

    /**
     * Progression Randomize
     */
    private function calculateRandomize(int $totalEmails, int $days): array
    {
        $average = $totalEmails / $days;
        $minPerDay = (int) floor($average * 0.6);
        $maxPerDay = (int) ceil($average * 1.4);

        $plan = [];
        $totalSent = 0;

        for ($day = 1; $day < $days; $day++) {
            $remaining = $totalEmails - $totalSent;
            $daysLeft = $days - $day + 1;

            $maxPossible = min(
                $maxPerDay,
                $remaining - ($daysLeft - 1) * $minPerDay
            );

            $minPossible = max(
                $minPerDay,
                $remaining - ($daysLeft - 1) * $maxPerDay
            );

            $emails = rand($minPossible, $maxPossible);

            $plan[] = [
                'day' => $day,
                'emails' => $emails
            ];
            $totalSent += $emails;
        }

        $lastDayEmails = $totalEmails - $totalSent;
        $plan[] = [
            'day' => $days,
            'emails' => $lastDayEmails
        ];

        return $plan;
    }

    /**
     * Résoudre numériquement la raison q
     */
    private function solveForRatioIncreasing(int $n, float $alpha): float
    {
        $computeAlpha = function ($q) use ($n) {
            if ($q <= 1.0) {
                return 1.0;
            }
            $qPowerN_1 = pow($q, $n - 1);
            $qPowerN = $qPowerN_1 * $q;
            return $qPowerN_1 * ($q - 1) / ($qPowerN - 1);
        };

        $qMin = 1.0 + 1e-10;
        $qMax = 1.0 + ($alpha * $n);
        $qMax = min($qMax, 5.0);

        $maxIterations = 100;
        $tolerance = 1e-6;

        $alphaMin = $computeAlpha($qMin);

        if ($alpha < $alphaMin) {
            $adjustedAlpha = $alphaMin * 1.10;
            return 1.0 + ($adjustedAlpha / $n);
        }

        for ($i = 0; $i < $maxIterations; $i++) {
            $qMid = ($qMin + $qMax) / 2.0;
            $alphaMid = $computeAlpha($qMid);

            if (abs($alphaMid - $alpha) < $tolerance) {
                return $qMid;
            }

            if ($alphaMid > $alpha) {
                $qMax = $qMid;
            } else {
                $qMin = $qMid;
            }

            if (abs($qMax - $qMin) < 1e-10) {
                return $qMid;
            }
        }

        return ($qMin + $qMax) / 2.0;
    }
}
