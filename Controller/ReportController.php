<?php

namespace MauticPlugin\MauticWarmUpBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Model\DomainModel;
use MauticPlugin\MauticWarmUpBundle\Model\CampaignModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController
{
    private EntityManagerInterface $em;
    private DomainModel $domainModel;
    private CampaignModel $campaignModel;

    public function __construct(
        EntityManagerInterface $em,
        DomainModel $domainModel,
        CampaignModel $campaignModel
    ) {
        $this->em = $em;
        $this->domainModel = $domainModel;
        $this->campaignModel = $campaignModel;
    }

    /**
     * Get dashboard statistics
     */
    public function dashboardAction(Request $request): JsonResponse
    {
        $fromDate = $request->query->get('from') ? new \DateTime($request->query->get('from')) : new \DateTime('-30 days');
        $toDate = $request->query->get('to') ? new \DateTime($request->query->get('to')) : new \DateTime();
        
        // Get campaign stats
        $campaignStats = $this->campaignModel->getCampaignStats();
        
        // Get domain stats
        $domains = $this->domainModel->getActiveDomains();
        $domainStats = [];
        $totalSent = 0;
        $totalDelivered = 0;
        
        foreach ($domains as $domain) {
            $metrics = $this->domainModel->getPerformanceMetrics($domain, $fromDate, $toDate);
            $domainStats[] = [
                'id' => $domain->getId(),
                'name' => $domain->getDomainName(),
                'metrics' => $metrics,
            ];
            $totalSent += $metrics['total_sent'];
            $totalDelivered += ($metrics['total_sent'] * ($metrics['delivery_rate'] / 100));
        }
        
        $deliveryRate = $totalSent > 0 ? ($totalDelivered / $totalSent) * 100 : 0;
        
        return new JsonResponse([
            'success' => true,
            'stats' => [
                'campaigns' => $campaignStats,
                'domains' => $domainStats,
                'overall' => [
                    'totalSent' => $totalSent,
                    'totalDelivered' => (int)$totalDelivered,
                    'deliveryRate' => round($deliveryRate, 2),
                    'period' => [
                        'from' => $fromDate->format('Y-m-d'),
                        'to' => $toDate->format('Y-m-d'),
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get performance report
     */
    public function performanceAction(Request $request): JsonResponse
    {
        $type = $request->query->get('type', 'daily'); // daily, weekly, monthly
        $entity = $request->query->get('entity', 'campaign'); // campaign, domain
        $id = $request->query->get('id');
        
        $data = [];
        
        switch ($type) {
            case 'daily':
                $interval = new \DateInterval('P1D');
                break;
            case 'weekly':
                $interval = new \DateInterval('P7D');
                break;
            case 'monthly':
                $interval = new \DateInterval('P1M');
                break;
            default:
                $interval = new \DateInterval('P1D');
        }
        
        $endDate = new \DateTime();
        $startDate = clone $endDate;
        $startDate->sub(new \DateInterval('P30D'));
        
        $period = new \DatePeriod($startDate, $interval, $endDate);
        
        foreach ($period as $date) {
            $nextDate = clone $date;
            $nextDate->add($interval);
            
            // This is a simplified version - you'd need to query your actual data
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'sent' => rand(0, 100),
                'delivered' => rand(0, 100),
                'opened' => rand(0, 50),
                'clicked' => rand(0, 20),
                'bounced' => rand(0, 5),
                'complaints' => rand(0, 2),
            ];
        }
        
        return new JsonResponse([
            'success' => true,
            'report' => [
                'type' => $type,
                'entity' => $entity,
                'id' => $id,
                'data' => $data,
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ],
            ],
        ]);
    }

    /**
     * Get deliverability report
     */
    public function deliverabilityAction(Request $request): JsonResponse
    {
        $domainId = $request->query->get('domain_id');
        $fromDate = $request->query->get('from') ? new \DateTime($request->query->get('from')) : new \DateTime('-7 days');
        $toDate = $request->query->get('to') ? new \DateTime($request->query->get('to')) : new \DateTime();
        
        $domains = [];
        
        if ($domainId) {
            $domain = $this->domainModel->getEntity($domainId);
            if ($domain) {
                $domains[] = $domain;
            }
        } else {
            $domains = $this->domainModel->getActiveDomains();
        }
        
        $report = [];
        foreach ($domains as $domain) {
            $metrics = $this->domainModel->getPerformanceMetrics($domain, $fromDate, $toDate);
            
            $report[] = [
                'id' => $domain->getId(),
                'name' => $domain->getDomainName(),
                'email' => $domain->generateEmailAddress(),
                'metrics' => $metrics,
                'status' => [
                    'isActive' => $domain->isActive(),
                    'isVerified' => $domain->isVerified(),
                    'warmupPhase' => $domain->getWarmupPhase(),
                    'currentPhaseDay' => $domain->getCurrentPhaseDay(),
                    'dailyLimit' => $domain->getDailyLimit(),
                    'sentToday' => $domain->getTotalSentToday(),
                    'remainingToday' => $domain->getRemainingSendsToday(),
                ],
            ];
        }
        
        return new JsonResponse([
            'success' => true,
            'report' => $report,
            'period' => [
                'from' => $fromDate->format('Y-m-d'),
                'to' => $toDate->format('Y-m-d'),
            ],
            'totalDomains' => count($report),
        ]);
    }

    /**
     * Get engagement report
     */
    public function engagementAction(Request $request): JsonResponse
    {
        $campaignId = $request->query->get('campaign_id');
        $fromDate = $request->query->get('from') ? new \DateTime($request->query->get('from')) : new \DateTime('-30 days');
        $toDate = $request->query->get('to') ? new \DateTime($request->query->get('to')) : new \DateTime();
        
        // This is a simplified version - you'd need to implement actual engagement tracking
        $engagementData = [
            'opens' => [
                'total' => rand(100, 1000),
                'unique' => rand(50, 500),
                'rate' => rand(10, 50),
                'trend' => $this->generateTrendData(30),
            ],
            'clicks' => [
                'total' => rand(10, 200),
                'unique' => rand(5, 100),
                'rate' => rand(1, 10),
                'trend' => $this->generateTrendData(30),
            ],
            'replies' => [
                'total' => rand(0, 20),
                'unique' => rand(0, 10),
                'rate' => rand(0, 5),
                'trend' => $this->generateTrendData(30),
            ],
            'unsubscribes' => [
                'total' => rand(0, 10),
                'rate' => rand(0, 2),
                'trend' => $this->generateTrendData(30),
            ],
            'complaints' => [
                'total' => rand(0, 5),
                'rate' => rand(0, 1),
                'trend' => $this->generateTrendData(30),
            ],
        ];
        
        return new JsonResponse([
            'success' => true,
            'engagement' => $engagementData,
            'period' => [
                'from' => $fromDate->format('Y-m-d'),
                'to' => $toDate->format('Y-m-d'),
            ],
            'campaignId' => $campaignId,
        ]);
    }

    /**
     * Get daily summary
     */
    public function dailySummaryAction(Request $request): JsonResponse
    {
        $date = $request->query->get('date') ? new \DateTime($request->query->get('date')) : new \DateTime();
        
        // This would query your actual data
        $summary = [
            'date' => $date->format('Y-m-d'),
            'domains' => [
                'active' => rand(1, 10),
                'verified' => rand(1, 8),
                'sending' => rand(1, 5),
            ],
            'campaigns' => [
                'active' => rand(1, 20),
                'completed' => rand(0, 5),
                'draft' => rand(0, 10),
            ],
            'emails' => [
                'sent' => rand(100, 5000),
                'delivered' => rand(80, 4500),
                'failed' => rand(0, 100),
                'deliveryRate' => rand(85, 99),
            ],
            'engagement' => [
                'opens' => rand(10, 2000),
                'clicks' => rand(1, 200),
                'replies' => rand(0, 20),
                'openRate' => rand(10, 50),
                'clickRate' => rand(1, 10),
            ],
        ];
        
        return new JsonResponse([
            'success' => true,
            'summary' => $summary,
        ]);
    }

    /**
     * Generate trend data for charts
     */
    private function generateTrendData(int $days): array
    {
        $trend = [];
        $base = rand(10, 100);
        
        for ($i = 0; $i < $days; $i++) {
            $value = max(0, $base + rand(-20, 20));
            $trend[] = [
                'date' => (new \DateTime('-' . ($days - $i) . ' days'))->format('Y-m-d'),
                'value' => $value,
            ];
            $base = $value;
        }
        
        return $trend;
    }
}
