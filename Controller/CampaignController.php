<?php

namespace MauticPlugin\MauticWarmUpBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticWarmUpBundle\Entity\Campaign;
use MauticPlugin\MauticWarmUpBundle\Entity\Domain;
use MauticPlugin\MauticWarmUpBundle\Entity\Template;
// use MauticPlugin\MauticWarmUpBundle\Entity\WarmupType;
use MauticPlugin\MauticWarmUpBundle\Entity\WarmupType;
use MauticPlugin\MauticWarmUpBundle\Entity\WarmupContact;
use MauticPlugin\MauticWarmUpBundle\Form\Type\SimpleCampaignFormType;
use MauticPlugin\MauticWarmUpBundle\Model\CampaignModel;
use MauticPlugin\MauticWarmUpBundle\Model\WarmupProgressionCalculator;
use MauticPlugin\MauticWarmUpBundle\Service\EmailSenderService;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class CampaignController
{
    private EntityManagerInterface $em;
    private CampaignModel $campaignModel;
    private WarmupProgressionCalculator $warmupCalculator;
    private ListModel $segmentModel;
    private EmailSenderService $emailSender;
    private FormFactoryInterface $formFactory;
    private UrlGeneratorInterface $router;
    private RequestStack $requestStack;
    private Environment $twig;

    public function __construct(
        EntityManagerInterface $em,
        CampaignModel $campaignModel,
        WarmupProgressionCalculator $warmupCalculator,
        ListModel $segmentModel,
        EmailSenderService $emailSender,
        FormFactoryInterface $formFactory,
        UrlGeneratorInterface $router,
        RequestStack $requestStack,
        Environment $twig
    ) {
        $this->em = $em;
        $this->campaignModel = $campaignModel;
        $this->warmupCalculator = $warmupCalculator;
        $this->segmentModel = $segmentModel;
        $this->emailSender = $emailSender;
        $this->formFactory = $formFactory;
        $this->router = $router;
        $this->requestStack = $requestStack;
        $this->twig = $twig;
    }

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getSession();
    }

    /**
     * Page principale des campagnes (avec DataTables)
     */
    public function indexAction(Request $request): Response
    {
        return new Response(
            $this->twig->render('@MauticWarmUp/Campaign/index.html.twig')
        );
    }

    /**
     * AJAX: Liste des campagnes pour DataTables
     */
    public function ajaxListCampaignAction(Request $request): JsonResponse
    {
        try {
            $draw = $request->query->getInt('draw', 1);
            $start = $request->query->getInt('start', 0);
            $length = $request->query->getInt('length', 10);
            $searchValue = $request->query->all('search')['value'] ?? '';

            $qb = $this->em->createQueryBuilder()
                ->select('c')
                ->from(Campaign::class, 'c')
                ->orderBy('c.createdAt', 'DESC');

            if (!empty($searchValue)) {
                $qb->andWhere('c.campaignName LIKE :search')
                    ->setParameter('search', '%' . $searchValue . '%');
            }

            // Total records
            $totalQb = clone $qb;
            $totalRecords = (int) $totalQb
                ->select('COUNT(c.id)')
                ->getQuery()
                ->getSingleScalarResult();

            // Paginated results
            $qb->setFirstResult($start)
                ->setMaxResults($length);

            $campaigns = $qb->getQuery()->getResult();

            $data = [];
            foreach ($campaigns as $campaign) {
                $data[] = [
                    'id' => $campaign->getId(),
                    'campaignName' => $campaign->getCampaignName() ?? 'Untitled',
                    'status' => $campaign->getStatus() ?? 'draft',
                    'statusLabel' => $this->getStatusLabel($campaign->getStatus() ?? 'draft'),
                    'statusColor' => $this->getStatusColor($campaign->getStatus() ?? 'draft'),
                    'totalContacts' => $campaign->getTotalContacts() ?? 0,
                    'emailsSent' => $campaign->getEmailsSent() ?? 0,
                    'startDate' => $campaign->getStartDate() ? $campaign->getStartDate()->format('Y-m-d H:i') : '-',
                    'progress' => $campaign->getProgress() ?? 0,
                    'actions' => $this->getActionButtons($campaign),
                ];
            }

            return new JsonResponse([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords,
                'data' => $data,
            ]);

        } catch (\Throwable $e) {
            return new JsonResponse([
                'draw' => 1,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Créer une nouvelle campagne
     */
    public function newAction(Request $request): Response
    {
        $campaign = new Campaign();
        return $this->showForm($request, $campaign, false);
    }

    /**
     * Éditer une campagne existante
     */
    public function editAction(Request $request, int $id): Response
    {
        $campaign = $this->em->getRepository(Campaign::class)->find($id);

        if (!$campaign) {
            $this->getSession()->getFlashBag()->add('error', 'Campaign not found');
            return new RedirectResponse($this->router->generate('warmup_campaign_index'));
        }

        return $this->showForm($request, $campaign, true);
    }

    /**
     * Afficher le formulaire de campagne
     */
    private function showForm(Request $request, Campaign $campaign, bool $isEdit): Response
    {
        if (!$isEdit) {
            $campaign->setStartDate(new \DateTime('tomorrow 09:00'));
            $campaign->setSendTime(new \DateTime('09:00'));
            $campaign->setStatus('draft');
            $campaign->setStartVolume(20);
            $campaign->setDurationDays(30);
            $campaign->setSendFrequency('daily');
            $campaign->setContactSource('manual');
        }

        $form = $this->formFactory->create(SimpleCampaignFormType::class, $campaign, [
            'is_edit' => $isEdit,
        ]);

        $domains = $this->em->getRepository(Domain::class)->findBy(['isActive' => true]);
        $warmupTypes = $this->em->getRepository(WarmupType::class)->findAll();
        $templates = $this->em->getRepository(Template::class)->findBy(['isActive' => true]);

        return new Response(
            $this->twig->render('@MauticWarmUp/Campaign/simple_form.html.twig', [
                'form' => $form->createView(),
                'campaign' => $campaign,
                'domains' => $domains,
                'warmupTypes' => $warmupTypes,
                'templates' => $templates,
                'isEdit' => $isEdit,
            ])
        );
    }

    /**
     * Sauvegarder la campagne (AJAX)
     */
    public function saveAction(Request $request): JsonResponse
    {
        try {

            error_log('Request method: ' . $request->getMethod());
            error_log('Is AJAX: ' . ($request->isXmlHttpRequest() ? 'Yes' : 'No'));
            error_log('Request data: ' . json_encode($request->request->all()));
            // Récupérer les données du formulaire
            $campaignData = $request->request->all('campaign_form');

            $campaignId = isset($campaignData['id']) ? (int) $campaignData['id'] : null;
            $isEdit = $campaignId ? true : false;

            $campaign = $isEdit
                ? $this->em->getRepository(Campaign::class)->find($campaignId)
                : new Campaign();

            if (!$campaign && $isEdit) {
                throw new \Exception('Campaign not found');
            }

            $form = $this->formFactory->create(SimpleCampaignFormType::class, $campaign, [
                'is_edit' => $isEdit,
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                // Traiter les contacts
                $contacts = $this->processContacts($campaign, $request);
                $campaign->setTotalContacts(count($contacts));

                // Calculer le plan de warmup si demandé
                $warmupPlan = $this->calculateWarmupPlan($campaign);

                // Sauvegarder le plan en JSON
                if (!empty($warmupPlan)) {
                    $campaign->setCustomMessage(json_encode($warmupPlan));
                }

                // Définir les dates si nouvelle campagne
                if (!$isEdit) {
                    $campaign->setCreatedAt(new \DateTime());
                }
                $campaign->setUpdatedAt(new \DateTime());

                // Sauvegarder la campagne
                $this->em->persist($campaign);
                $this->em->flush();

                // Sauvegarder les contacts
                if (!empty($contacts)) {
                    $this->saveContacts($campaign, $contacts);
                }

                return new JsonResponse([
                    'success' => true,
                    'message' => $isEdit ? 'Campaign updated successfully!' : 'Campaign created successfully!',
                    'campaignId' => $campaign->getId(),
                    'redirect' => $this->router->generate('warmup_campaign_index'),
                ]);
            } else {
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                throw new \Exception('Form validation failed: ' . implode(', ', $errors));
            }

        } catch (\Exception $e) {
            error_log('Error saving campaign: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Error saving campaign: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Démarrer une campagne (AJAX)
     */
    public function startAction(Request $request, int $id): JsonResponse
    {
        try {
            $campaign = $this->em->getRepository(Campaign::class)->find($id);

            if (!$campaign) {
                throw new \Exception('Campaign not found');
            }

            $campaign->setStatus('active');
            $campaign->setStartDate(new \DateTime());

            $this->em->persist($campaign);
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Campaign started successfully!',
                'status' => 'active',
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error starting campaign: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Mettre en pause une campagne (AJAX)
     */
    public function pauseAction(Request $request, int $id): JsonResponse
    {
        try {
            $campaign = $this->em->getRepository(Campaign::class)->find($id);

            if (!$campaign) {
                throw new \Exception('Campaign not found');
            }

            $campaign->setStatus('paused');

            $this->em->persist($campaign);
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Campaign paused',
                'status' => 'paused',
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error pausing campaign: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reprendre une campagne (AJAX)
     */
    public function resumeAction(Request $request, int $id): JsonResponse
    {
        try {
            $campaign = $this->em->getRepository(Campaign::class)->find($id);

            if (!$campaign) {
                throw new \Exception('Campaign not found');
            }

            $campaign->setStatus('active');

            $this->em->persist($campaign);
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Campaign resumed',
                'status' => 'active',
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error resuming campaign: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Calculer le plan de warmup (AJAX)
     */
    public function calculateWarmupAction(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['totalContacts'], $data['durationDays'], $data['warmupType'], $data['startVolume'])) {
                throw new \Exception('Missing required parameters');
            }

            $plan = $this->warmupCalculator->calculate(
                (int) $data['totalContacts'],
                (int) $data['durationDays'],
                $data['warmupType'],
                isset($data['ratio']) ? (float) $data['ratio'] : null,
                (int) $data['startVolume'],
                isset($data['increasePerDay']) ? (int) $data['increasePerDay'] : null
            );

            $stats = $this->calculateWarmupStats($plan);

            return new JsonResponse([
                'success' => true,
                'plan' => $plan,
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Preview contacts from segment (AJAX)
     */
    public function previewContactsAction(Request $request): JsonResponse
    {
        try {
            $segmentId = $request->request->get('segment_id');
            $limit = $request->request->get('limit', 10);

            if (!$segmentId) {
                throw new \Exception('Segment ID is required');
            }

            $segment = $this->segmentModel->getEntity($segmentId);

            if (!$segment) {
                throw new \Exception('Segment not found');
            }

            $contacts = $this->segmentModel->getLeadsByList($segment, true);

            $preview = array_slice($contacts, 0, $limit);

            return new JsonResponse([
                'success' => true,
                'contacts' => $preview,
                'count' => count($preview),
                'total_available' => count($contacts),
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Envoyer un email test (AJAX)
     */
    public function sendTestEmailAction(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['testEmail'], $data['domainId'], $data['subject'], $data['message'])) {
                throw new \Exception('Missing required parameters');
            }

            $domain = $this->em->getRepository(Domain::class)->find($data['domainId']);

            if (!$domain) {
                throw new \Exception('Domain not found');
            }

            $result = $this->emailSender->sendTestEmail(
                $domain,
                $data['testEmail'],
                $data['subject'],
                $data['message']
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Test email sent successfully!',
                'details' => $result,
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error sending test email: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Traiter les contacts depuis différentes sources
     */
    private function processContacts(Campaign $campaign, Request $request): array
    {
        $formData = $request->request->all('campaign_form');
        $contactSource = $formData['contactSource'] ?? 'manual';
        $contacts = [];

        switch ($contactSource) {
            case 'manual':
                $manualText = $formData['manualContacts'] ?? '';
                $lines = array_filter(array_map('trim', explode("\n", $manualText)));

                foreach ($lines as $line) {
                    $email = trim($line);
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $contacts[] = [
                            'email' => $email,
                            'first_name' => '',
                            'last_name' => '',
                        ];
                    }
                }
                break;

            case 'mautic':
                $segmentId = $request->request->get('segment_id');

                if ($segmentId) {
                    $segment = $this->segmentModel->getEntity($segmentId);

                    if ($segment) {
                        $mauticContacts = $this->segmentModel->getLeadsByList($segment, true);

                        foreach ($mauticContacts as $contact) {
                            $contacts[] = [
                                'email' => $contact['email'] ?? '',
                                'first_name' => $contact['firstname'] ?? '',
                                'last_name' => $contact['lastname'] ?? '',
                            ];
                        }
                    }
                }
                break;
        }

        return $contacts;
    }

    /**
     * Sauvegarder les contacts dans la base de données
     */
    private function saveContacts(Campaign $campaign, array $contacts): void
    {
        foreach ($contacts as $contactData) {
            if (empty($contactData['email'])) {
                continue;
            }

            $contact = new WarmupContact();
            $contact->setCampaign($campaign);
            $contact->setEmailAddress($contactData['email']);
            $contact->setFirstName($contactData['first_name'] ?? '');
            $contact->setLastName($contactData['last_name'] ?? '');
            $contact->setSequenceDay(1);
            $contact->setDaysBetweenEmails(2);
            $contact->setSentCount(0);
            $contact->setIsActive(true);
            $contact->setCreatedAt(new \DateTime());
            $contact->setUnsubscribeToken(bin2hex(random_bytes(32)));

            $this->em->persist($contact);
        }

        $this->em->flush();
    }

    /**
     * Calculer le plan de warmup pour une campagne
     */
    private function calculateWarmupPlan(Campaign $campaign): array
    {
        $warmupType = $campaign->getWarmupType();

        if (!$warmupType) {
            return [];
        }

        $typeName = $warmupType->getTypeName();

        $mapping = [
            'Arithmetic' => 'arithmetic',
            'Geometric' => 'geometric',
            'Flat' => 'flat',
            'Progressive' => 'progressive',
            'Randomize' => 'randomize',
        ];

        $progressionType = $mapping[$typeName] ?? 'arithmetic';

        return $this->warmupCalculator->calculate(
            $campaign->getTotalContacts() ?? 100,
            $campaign->getDurationDays() ?? 30,
            $progressionType,
            null,
            $campaign->getStartVolume() ?? 20,
            null
        );
    }

    /**
     * Calculer les statistiques du plan de warmup
     */
    private function calculateWarmupStats(array $plan): array
    {
        $emails = array_column($plan, 'emails');

        if (empty($emails)) {
            return [
                'total' => 0,
                'average' => 0,
                'min' => 0,
                'max' => 0,
            ];
        }

        return [
            'total' => array_sum($emails),
            'average' => round(array_sum($emails) / count($emails), 1),
            'min' => min($emails),
            'max' => max($emails),
        ];
    }

    /**
     * Helper: Get status label
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            'draft' => 'Draft',
            'active' => 'Active',
            'paused' => 'Paused',
            'completed' => 'Completed',
        ];
        return $labels[$status] ?? $status;
    }

    /**
     * Helper: Get status color
     */
    private function getStatusColor(string $status): string
    {
        $colors = [
            'draft' => 'default',
            'active' => 'success',
            'paused' => 'warning',
            'completed' => 'info',
        ];
        return $colors[$status] ?? 'default';
    }

    /**
     * Helper: Get action buttons HTML
     */
    private function getActionButtons(Campaign $campaign): string
    {
        $buttons = [];
        $id = $campaign->getId();

        // Edit button
        $buttons[] = '<a href="' . $this->router->generate('warmup_campaign_edit', ['id' => $id]) .
            '" class="btn btn-xs btn-default" title="Edit"><i class="fa fa-edit"></i></a>';

        // Start button
        if ($campaign->getStatus() === 'draft' || $campaign->getStatus() === 'paused') {
            $buttons[] = '<button class="btn btn-xs btn-success start-campaign" data-id="' . $id .
                '" title="Start"><i class="fa fa-play"></i></button>';
        }

        // Pause button
        if ($campaign->getStatus() === 'active') {
            $buttons[] = '<button class="btn btn-xs btn-warning pause-campaign" data-id="' . $id .
                '" title="Pause"><i class="fa fa-pause"></i></button>';
        }

        return implode(' ', $buttons);
    }
}