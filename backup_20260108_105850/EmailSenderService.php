<?php

namespace MauticPlugin\MauticWarmUpBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Campaign;
use MauticPlugin\MauticWarmUpBundle\Entity\Domain;
use MauticPlugin\MauticWarmUpBundle\Entity\Contact as WarmupContact;
use MauticPlugin\MauticWarmUpBundle\Entity\SentLog as WarmupSentLog;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailSenderService
{
    private EntityManagerInterface $em;
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private string $timezone = 'America/Toronto';

    public function __construct(
        EntityManagerInterface $em,
        MailerInterface $mailer,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    /**
     * Traiter toutes les campagnes actives
     */
    public function processCampaigns(?int $campaignId = null, bool $force = false): int
    {
        $this->logger->info('Starting campaign email processing');

        $qb = $this->em->getRepository(Campaign::class)
            ->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', 'active');

        if ($campaignId) {
            $qb->andWhere('c.id = :id')
                ->setParameter('id', $campaignId);
        }

        $campaigns = $qb->getQuery()->getResult();
        $processedCount = 0;

        foreach ($campaigns as $campaign) {
            try {
                if ($this->processCampaign($campaign, $force)) {
                    $processedCount++;
                }
            } catch (\Exception $e) {
                $this->logger->error('Error processing campaign: ' . $e->getMessage(), [
                    'campaign_id' => $campaign->getId(),
                    'campaign_name' => $campaign->getCampaignName()
                ]);
            }
        }

        $this->logger->info("Campaign email processing completed. Processed: {$processedCount}");
        return $processedCount;
    }

    /**
     * Traiter une seule campagne
     */
    public function processCampaign(Campaign $campaign, bool $force = false): bool
    {
        $now = new \DateTime('now', new \DateTimeZone($this->timezone));

        // Vérifier si c'est le bon moment pour envoyer
        if (!$force && !$this->shouldSendNow($campaign, $now)) {
            $this->logger->debug('Campaign not ready to send', [
                'campaign_id' => $campaign->getId(),
                'current_time' => $now->format('H:i'),
                'send_time' => $campaign->getSendTime() ? $campaign->getSendTime()->format('H:i') : 'not set'
            ]);
            return false;
        }

        $domain = $campaign->getDomain();
        if (!$domain || !$domain->isActive()) {
            $this->logger->warning('Campaign domain not active', [
                'campaign_id' => $campaign->getId()
            ]);
            return false;
        }

        // Obtenir la limite quotidienne selon le plan de warmup
        $dailyLimit = $this->getDailyLimit($campaign);

        // Vérifier la limite du domaine
        if ($domain->getTotalSentToday() >= $domain->getDailyLimit()) {
            $this->logger->info('Domain daily limit reached', [
                'domain' => $domain->getDomainName(),
                'sent_today' => $domain->getTotalSentToday(),
                'daily_limit' => $domain->getDailyLimit()
            ]);
            return false;
        }

        // Calculer combien d'emails on peut encore envoyer aujourd'hui
        $remainingForDomain = $domain->getDailyLimit() - $domain->getTotalSentToday();
        $toSend = min($dailyLimit, $remainingForDomain);

        $this->logger->info('Processing campaign', [
            'campaign_id' => $campaign->getId(),
            'campaign_name' => $campaign->getCampaignName(),
            'daily_limit' => $dailyLimit,
            'to_send' => $toSend,
            'campaign_day' => $this->getCampaignDay($campaign)
        ]);

        // Obtenir les contacts à qui envoyer
        $contacts = $this->getContactsToSend($campaign, $toSend);

        if (empty($contacts)) {
            $this->logger->info('No contacts to send', [
                'campaign_id' => $campaign->getId()
            ]);

            // Vérifier si la campagne est terminée
            $this->checkCampaignCompletion($campaign);
            return false;
        }

        $sentCount = 0;
        foreach ($contacts as $contact) {
            try {
                $this->sendEmailToContact($campaign, $contact);
                $this->updateContactAfterSend($contact);

                $campaign->incrementEmailsSent();
                $domain->incrementTotalSentToday();

                $sentCount++;

            } catch (\Exception $e) {
                $this->logger->error('Error sending email to contact', [
                    'contact_id' => $contact->getId(),
                    'email' => $contact->getEmailAddress(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->em->flush();

        $this->logger->info('Campaign batch completed', [
            'campaign_id' => $campaign->getId(),
            'sent_count' => $sentCount,
            'total_sent' => $campaign->getEmailsSent()
        ]);

        // Vérifier si la campagne est terminée
        $this->checkCampaignCompletion($campaign);

        return $sentCount > 0;
    }

    /**
     * Envoyer un email à un contact
     */
    private function sendEmailToContact(Campaign $campaign, WarmupContact $contact): void
    {
        $domain = $campaign->getDomain();
        if (!$domain) {
            throw new \Exception('No domain configured for campaign');
        }

        // Préparer le contenu
        $subject = $this->replaceVariables($campaign->getSubjectTemplate(), $contact, $campaign);
        $content = $this->replaceVariables($campaign->getCustomMessage(), $contact, $campaign);

        // Ajouter automatiquement le lien de désinscription si absent
        if (strpos($content, '{{unsubscribe_link}}') === false) {
            $content .= "\n\n---\n" . $this->generateUnsubscribeLink($contact);
        }

        // Créer l'email
        $email = (new Email())
            ->from($this->getFromEmail($domain))
            ->to($contact->getEmailAddress())
            ->subject($subject)
            ->text(strip_tags($content))
            ->html(nl2br($content));

        // Envoyer via Mailer
        $this->mailer->send($email);

        // Logger l'envoi
        $this->logEmailSent($campaign, $contact, $subject, $content, 'sent');

        $this->logger->info('Email sent successfully', [
            'campaign_id' => $campaign->getId(),
            'contact_id' => $contact->getId(),
            'email' => $contact->getEmailAddress(),
            'subject' => $subject
        ]);
    }

    /**
     * Envoyer un email de test
     */
    public function sendTestEmail(Domain $domain, string $toEmail, string $subject, string $message): array
    {
        try {
            $email = (new Email())
                ->from($this->getFromEmail($domain))
                ->to($toEmail)
                ->subject($subject)
                ->text(strip_tags($message))
                ->html(nl2br($message));

            $this->mailer->send($email);

            return [
                'success' => true,
                'from' => $this->getFromEmail($domain),
                'to' => $toEmail,
                'subject' => $subject,
                'message_id' => uniqid('test_', true),
                'sent_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            throw new \Exception('Failed to send test email: ' . $e->getMessage());
        }
    }

    /**
     * Obtenir la limite quotidienne selon le plan de warmup
     */
    private function getDailyLimit(Campaign $campaign): int
    {
        $currentDay = $this->getCampaignDay($campaign);

        // Essayer de récupérer le plan de warmup depuis customMessage
        $warmupPlanJson = $campaign->getCustomMessage();
        $warmupPlan = [];

        if ($warmupPlanJson && $this->isJson($warmupPlanJson)) {
            $warmupPlan = json_decode($warmupPlanJson, true);
        }

        // Chercher le volume pour le jour actuel
        if (!empty($warmupPlan)) {
            foreach ($warmupPlan as $dayPlan) {
                if (isset($dayPlan['day']) && $dayPlan['day'] == $currentDay) {
                    return $dayPlan['emails'] ?? $campaign->getStartVolume();
                }
            }
        }

        // Fallback: calculer dynamiquement
        return $this->calculateDailyVolume($campaign, $currentDay);
    }

    /**
     * Calculer le volume quotidien dynamiquement
     */
    private function calculateDailyVolume(Campaign $campaign, int $currentDay): int
    {
        $startVolume = $campaign->getStartVolume();
        $dailyIncrement = $campaign->getDailyIncrement();

        // Calcul simple: volume de départ + (increment * jour)
        $volume = $startVolume + ($dailyIncrement * ($currentDay - 1));

        // Limiter au max de contacts disponibles
        $maxVolume = $campaign->getTotalContacts();

        return min($volume, $maxVolume);
    }

    /**
     * Obtenir le jour actuel de la campagne
     */
    private function getCampaignDay(Campaign $campaign): int
    {
        $startDate = $campaign->getStartDate();
        $now = new \DateTime('now', new \DateTimeZone($this->timezone));

        if (!$startDate || $startDate > $now) {
            return 1;
        }

        $interval = $startDate->diff($now);
        return $interval->days + 1;
    }

    /**
     * Obtenir les contacts à qui envoyer
     */
    private function getContactsToSend(Campaign $campaign, int $limit): array
    {
        return $this->em->getRepository(WarmupContact::class)
            ->createQueryBuilder('c')
            ->where('c.campaign = :campaign')
            ->andWhere('c.isActive = :active')
            ->andWhere('(c.nextSendDate <= :now OR c.nextSendDate IS NULL)')
            ->setParameter('campaign', $campaign)
            ->setParameter('active', true)
            ->setParameter('now', new \DateTime('now', new \DateTimeZone($this->timezone)))
            ->orderBy('c.lastSent', 'ASC') // Prioriser ceux qui n'ont pas reçu d'email récemment
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Mettre à jour le contact après envoi
     */
    private function updateContactAfterSend(WarmupContact $contact): void
    {
        $now = new \DateTime('now', new \DateTimeZone($this->timezone));

        $contact->setLastSent($now);
        $contact->incrementSentCount();
        $contact->setSequenceDay($contact->getSequenceDay() + 1);

        // Planifier le prochain envoi
        $nextDate = clone $now;
        $nextDate->modify('+' . $contact->getDaysBetweenEmails() . ' days');
        $contact->setNextSendDate($nextDate);

        $this->em->persist($contact);
    }

    /**
     * Logger l'envoi d'un email
     */
    private function logEmailSent(
        Campaign $campaign,
        WarmupContact $contact,
        string $subject,
        string $content,
        string $status = 'sent'
    ): void {
        $log = new WarmupSentLog();
        $log->setCampaign($campaign);
        $log->setContact($contact);
        $log->setDomain($campaign->getDomain());
        $log->setSequenceDay($contact->getSequenceDay());
        $log->setEmailSubject($subject);
        $log->setEmailContent($content);
        $log->setSendTime(new \DateTime('now', new \DateTimeZone($this->timezone)));
        $log->setStatus($status);
        $log->setCreatedAt(new \DateTime('now', new \DateTimeZone($this->timezone)));
        $log->setMessageId(uniqid('msg_', true));

        $this->em->persist($log);
    }

    /**
     * Remplacer les variables dans le contenu
     */
    private function replaceVariables(string $content, WarmupContact $contact, Campaign $campaign): string
    {
        $variables = [
            '{{first_name}}' => $contact->getFirstName() ?: 'there',
            '{{last_name}}' => $contact->getLastName() ?: '',
            '{{email}}' => $contact->getEmailAddress(),
            '{{campaign_name}}' => $campaign->getCampaignName(),
            '{{unsubscribe_link}}' => $this->generateUnsubscribeLink($contact),
        ];

        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    /**
     * Générer le lien de désinscription
     */
    private function generateUnsubscribeLink(WarmupContact $contact): string
    {
        // Utilisez votre URL réelle ici
        $baseUrl = $_ENV['SITE_URL'] ?? 'https://your-domain.com';
        return $baseUrl . '/warmup/unsubscribe/' . $contact->getUnsubscribeToken();
    }

    /**
     * Obtenir l'adresse email d'envoi
     */
    private function getFromEmail(Domain $domain): string
    {
        $prefix = $domain->getEmailPrefix() ?: 'noreply';
        return $prefix . '@' . $domain->getDomainName();
    }

    /**
     * Vérifier s'il faut envoyer maintenant
     */
    private function shouldSendNow(Campaign $campaign, \DateTime $now): bool
    {
        $sendTime = $campaign->getSendTime();

        if (!$sendTime) {
            return true; // Pas d'heure spécifique, on peut envoyer
        }

        $currentTime = $now->format('H:i');
        $scheduledTime = $sendTime->format('H:i');

        // Vérifier l'heure (avec tolérance de 1 minute)
        if ($currentTime !== $scheduledTime) {
            return false;
        }

        // Vérifier la fréquence
        $frequency = $campaign->getSendFrequency();
        $dayOfWeek = (int) $now->format('N'); // 1=Monday, 7=Sunday

        switch ($frequency) {
            case 'weekdays':
                return $dayOfWeek <= 5; // Lundi à Vendredi
            case 'weekly':
                return $dayOfWeek == 1; // Lundi seulement
            case 'daily':
            default:
                return true;
        }
    }

    /**
     * Vérifier si la campagne est terminée
     */
    private function checkCampaignCompletion(Campaign $campaign): void
    {
        $totalToSend = $campaign->getTotalContacts();
        $sent = $campaign->getEmailsSent();

        // Vérifier si tous les emails ont été envoyés
        if ($sent >= $totalToSend) {
            $campaign->setStatus('completed');
            $campaign->setCompletedAt(new \DateTime('now', new \DateTimeZone($this->timezone)));

            $this->em->persist($campaign);
            $this->em->flush();

            $this->logger->info('Campaign completed', [
                'campaign_id' => $campaign->getId(),
                'campaign_name' => $campaign->getCampaignName(),
                'total_sent' => $sent
            ]);
        }
    }

    /**
     * Vérifier si une chaîne est du JSON valide
     */
    private function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}