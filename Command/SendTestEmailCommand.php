<?php

namespace MauticPlugin\MauticWarmUpBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Domain;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use MauticPlugin\MauticWarmUpBundle\Service\EmailSenderService;

class SendTestEmailCommand extends Command
{
    protected static $defaultName = 'mautic:warmup:test-email';

    private EntityManagerInterface $em;
    private EmailSenderService $emailSenderService;

    public function __construct(
        EntityManagerInterface $em,
        EmailSenderService $emailSenderService
    ) {
        $this->em = $em;
        $this->emailSenderService = $emailSenderService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Send a test email using a domain configuration')
            ->addArgument('domain-id', InputArgument::REQUIRED, 'ID of the domain to use for sending')
            ->addArgument('to-email', InputArgument::REQUIRED, 'Recipient email address')
            ->addOption('subject', 's', InputOption::VALUE_OPTIONAL, 'Email subject', 'Test Email from WarmUp Plugin')
            ->addOption('message', 'm', InputOption::VALUE_OPTIONAL, 'Email message', 'This is a test email sent from the Mautic WarmUp plugin.')
            ->addOption('list-domains', null, InputOption::VALUE_NONE, 'List all available domains before sending')
            ->setHelp(<<<EOT
This command sends a test email using a configured domain.

Examples:
  <info>%command.full_name% 1 test@example.com</info>
  Send test email using domain ID 1 to test@example.com

  <info>%command.full_name% 1 test@example.com --subject="Hello" --message="Test message"</info>
  Send test email with custom subject and message

  <info>%command.full_name% --list-domains</info>
  List all available domains with their status
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Option pour lister les domaines
        if ($input->getOption('list-domains')) {
            $this->listDomains($io);
            return Command::SUCCESS;
        }

        // Récupérer les arguments
        $domainId = (int) $input->getArgument('domain-id');
        $toEmail = $input->getArgument('to-email');
        $subject = $input->getOption('subject');
        $message = $input->getOption('message');

        // Valider l'email
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $io->error("Invalid email address: {$toEmail}");
            return Command::FAILURE;
        }

        // Récupérer le domaine
        $domain = $this->em->getRepository(Domain::class)->find($domainId);

        if (!$domain) {
            $io->error("Domain with ID {$domainId} not found");

            // Suggérer les domaines disponibles
            $this->suggestAvailableDomains($io);

            return Command::FAILURE;
        }

        // Vérifier si le domaine est actif
        if (!$domain->isActive()) {
            $io->warning("Domain '{$domain->getDomainName()}' is not active. Test email might still be sent.");

            if (!$io->confirm('Continue anyway?', false)) {
                return Command::SUCCESS;
            }
        }

        // Afficher le résumé
        $io->section('Test Email Summary');
        $io->table(
            ['Parameter', 'Value'],
            [
                ['From Domain', $domain->getDomainName()],
                ['From Email', $this->generateFromEmail($domain)],
                ['To Email', $toEmail],
                ['Subject', $subject],
                ['Message', substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '')],
            ]
        );

        // Confirmation
        if (!$io->confirm('Send test email?', false)) {
            return Command::SUCCESS;
        }

        try {
            // Envoyer l'email de test
            $result = $this->emailSenderService->sendTestEmail($domain, $toEmail, $subject, $message);

            // Afficher le résultat
            $io->success('Test email sent successfully!');

            $io->table(
                ['Field', 'Value'],
                [
                    ['Status', 'Success ✓'],
                    ['From', $result['from']],
                    ['To', $result['to']],
                    ['Subject', $result['subject']],
                    ['Message ID', $result['message_id']],
                    ['Sent At', $result['sent_at']],
                ]
            );

            $io->note('Note: Check your email inbox (and spam folder) for the test email.');

        } catch (\Exception $e) {
            $io->error('Failed to send test email: ' . $e->getMessage());

            $io->section('Troubleshooting Tips');
            $io->listing([
                'Check if the domain is properly configured',
                'Verify SMTP settings in your Mautic configuration',
                'Check if the domain\'s DNS records are properly set up',
                'Make sure the recipient email is valid and accepting emails',
                'Check Mautic logs for more detailed error information'
            ]);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function listDomains(SymfonyStyle $io): void
    {
        $domains = $this->em->getRepository(Domain::class)->findAll();

        if (empty($domains)) {
            $io->warning('No domains found. Please configure domains first.');
            return;
        }

        $rows = [];
        foreach ($domains as $domain) {
            $rows[] = [
                $domain->getId(),
                $domain->getDomainName(),
                $this->generateFromEmail($domain),
                $domain->isActive() ? 'Active ✓' : 'Inactive ✗',
                $domain->getDailyLimit(),
                $domain->getTotalSentToday(),
                $domain->getEmailPrefix() ?: 'noreply',
            ];
        }

        $io->title('Available Domains');
        $io->table(
            ['ID', 'Domain', 'From Email', 'Status', 'Daily Limit', 'Sent Today', 'Prefix'],
            $rows
        );
    }

    private function suggestAvailableDomains(SymfonyStyle $io): void
    {
        $domains = $this->em->getRepository(Domain::class)->findAll();

        if (!empty($domains)) {
            $io->note('Available domains:');
            foreach ($domains as $domain) {
                $io->text(sprintf(
                    '  ID: %d - %s (%s)',
                    $domain->getId(),
                    $domain->getDomainName(),
                    $domain->isActive() ? 'Active' : 'Inactive'
                ));
            }
        }
    }

    private function generateFromEmail(Domain $domain): string
    {
        $prefix = $domain->getEmailPrefix() ?: 'noreply';
        return $prefix . '@' . $domain->getDomainName();
    }
}