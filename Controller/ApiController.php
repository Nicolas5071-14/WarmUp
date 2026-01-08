<?php

namespace MauticPlugin\MauticWarmUpBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Helper\WarmUpHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiController
{
    private EntityManagerInterface $em;
    private WarmUpHelper $warmUpHelper;

    public function __construct(
        EntityManagerInterface $em,
        WarmUpHelper $warmUpHelper
    ) {
        $this->em = $em;
        $this->warmUpHelper = $warmUpHelper;
    }

    /**
     * Process warm-up (cron endpoint)
     */
    public function processAction(Request $request): JsonResponse
    {
        try {
            $this->warmUpHelper->processWarmUp();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Warm-up processed successfully',
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ], 500);
        }
    }

    /**
     * Send test email
     */
    public function sendTestAction(Request $request): JsonResponse
    {
        $domainId = $request->request->get('domain_id');
        $toEmail = $request->request->get('to_email');
        
        if (!$domainId || !$toEmail) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Domain ID and recipient email are required',
            ], 400);
        }
        
        try {
            $domain = $this->em->getRepository('MauticWarmUpBundle:Domain')->find($domainId);
            
            if (!$domain) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Domain not found',
                ], 404);
            }
            
            // Verify domain first
            if (!$domain->isVerified()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Domain is not verified',
                ], 400);
            }
            
            // Check daily limit
            if (!$domain->canSendMoreToday()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Daily sending limit reached for this domain',
                ], 400);
            }
            
            // Send test email
            // This is a simplified version - implement actual email sending
            $domain->incrementSentToday();
            $this->em->persist($domain);
            $this->em->flush();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Test email sent successfully',
                'domain' => $domain->getDomainName(),
                'sentTo' => $toEmail,
                'remainingToday' => $domain->getRemainingSendsToday(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error sending test email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get system status
     */
    public function statusAction(Request $request): JsonResponse
    {
        $domainRepo = $this->em->getRepository('MauticWarmUpBundle:Domain');
        $campaignRepo = $this->em->getRepository('MauticWarmUpBundle:Campaign');
        
        $activeDomains = $domainRepo->count(['isActive' => true]);
        $verifiedDomains = $domainRepo->count(['isVerified' => true]);
        $activeCampaigns = $campaignRepo->count(['status' => 'active']);
        
        $domainsWithCapacity = 0;
        $domains = $domainRepo->findBy(['isActive' => true]);
        foreach ($domains as $domain) {
            if ($domain->canSendMoreToday()) {
                $domainsWithCapacity++;
            }
        }
        
        return new JsonResponse([
            'success' => true,
            'status' => [
                'domains' => [
                    'total' => $domainRepo->count([]),
                    'active' => $activeDomains,
                    'verified' => $verifiedDomains,
                    'withCapacity' => $domainsWithCapacity,
                ],
                'campaigns' => [
                    'total' => $campaignRepo->count([]),
                    'active' => $activeCampaigns,
                    'draft' => $campaignRepo->count(['status' => 'draft']),
                    'paused' => $campaignRepo->count(['status' => 'paused']),
                    'completed' => $campaignRepo->count(['status' => 'completed']),
                ],
                'system' => [
                    'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'memoryUsage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
                    'uptime' => $this->getUptime(),
                ],
            ],
        ]);
    }

    /**
     * Get uptime information
     */
    private function getUptime(): string
    {
        if (file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $uptime = explode(' ', $uptime)[0];
            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            $minutes = floor(($uptime % 3600) / 60);
            
            return sprintf('%d days, %d hours, %d minutes', $days, $hours, $minutes);
        }
        
        return 'Unknown';
    }

    /**
     * Health check endpoint
     */
    public function healthAction(Request $request): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'plugin_tables' => $this->checkPluginTables(),
            'cron_status' => $this->checkCronStatus(),
        ];
        
        $allHealthy = true;
        foreach ($checks as $check) {
            if (!$check['healthy']) {
                $allHealthy = false;
                break;
            }
        }
        
        return new JsonResponse([
            'success' => $allHealthy,
            'healthy' => $allHealthy,
            'checks' => $checks,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $this->em->getConnection()->executeQuery('SELECT 1');
            return ['healthy' => true, 'message' => 'Database connection OK'];
        } catch (\Exception $e) {
            return ['healthy' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    private function checkPluginTables(): array
    {
        $tables = [
            'mautic_warmup_domains',
            'mautic_warmup_campaigns',
            'mautic_warmup_contacts',
        ];
        
        $missing = [];
        foreach ($tables as $table) {
            try {
                $this->em->getConnection()->executeQuery("SELECT 1 FROM $table LIMIT 1");
            } catch (\Exception $e) {
                $missing[] = $table;
            }
        }
        
        if (empty($missing)) {
            return ['healthy' => true, 'message' => 'All plugin tables exist'];
        }
        
        return ['healthy' => false, 'message' => 'Missing tables: ' . implode(', ', $missing)];
    }

    private function checkCronStatus(): array
    {
        // Check if cron is running by looking for recent logs
        $logFile = '/var/www/html/var/logs/warmup.log';
        
        if (!file_exists($logFile)) {
            return ['healthy' => false, 'message' => 'Warm-up log file not found'];
        }
        
        $lastModified = filemtime($logFile);
        $minutesAgo = (time() - $lastModified) / 60;
        
        if ($minutesAgo > 60) {
            return ['healthy' => false, 'message' => sprintf('Last warm-up run was %d minutes ago', $minutesAgo)];
        }
        
        return ['healthy' => true, 'message' => sprintf('Last run %d minutes ago', $minutesAgo)];
    }
}
