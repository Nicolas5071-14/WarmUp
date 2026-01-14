<?php

namespace MauticPlugin\MauticWarmUpBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use MauticPlugin\MauticWarmUpBundle\Entity\Campaign;
use MauticPlugin\MauticWarmUpBundle\Entity\Contact;
use MauticPlugin\MauticWarmUpBundle\Entity\Domain;
use MauticPlugin\MauticWarmUpBundle\Entity\Template;
use MauticPlugin\MauticWarmUpBundle\Entity\WarmupType;
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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\FormError;

class CampaignController
{
    private EntityManagerInterface $em;
    private CampaignModel $campaignModel;
    private WarmupProgressionCalculator $warmupCalculator;
    private Connection $connection;
    private EmailSenderService $emailSender;
    private FormFactoryInterface $formFactory;
    private UrlGeneratorInterface $router;
    private RequestStack $requestStack;
    private Environment $twig;

    public function __construct(
        EntityManagerInterface $em,
        CampaignModel $campaignModel,
        WarmupProgressionCalculator $warmupCalculator,
        EmailSenderService $emailSender,
        FormFactoryInterface $formFactory,
        UrlGeneratorInterface $router,
        RequestStack $requestStack,
        Environment $twig
    ) {
        $this->em = $em;
        $this->campaignModel = $campaignModel;
        $this->warmupCalculator = $warmupCalculator;
        $this->connection = $em->getConnection();
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
     * Liste des segments Mautic (AJAX)
     */
    public function listSegmentsAction(Request $request): JsonResponse
    {
        try {
            $sql = "
            SELECT 
                ll.id,
                ll.name,
                ll.description,
                ll.is_global,
                ll.is_preferred,
                COUNT(lll.lead_id) as lead_count
            FROM lead_lists ll
            LEFT JOIN lead_lists_leads lll ON ll.id = lll.leadlist_id 
                AND lll.manually_removed = 0
            WHERE ll.is_published = 1
            GROUP BY ll.id, ll.name, ll.description, ll.is_global, ll.is_preferred
            ORDER BY ll.name ASC
        ";

            $stmt = $this->connection->prepare($sql);
            $result = $stmt->executeQuery();
            $segments = $result->fetchAllAssociative();

            return new JsonResponse([
                'success' => true,
                'segments' => $segments,
                'total' => count($segments),
            ]);

        } catch (\Exception $e) {
            error_log('Error in listSegmentsAction: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Error loading segments: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher les contacts d'une campagne (AJAX)
     */
    public function contactsAction(Request $request, int $id): JsonResponse
    {
        try {
            $campaign = $this->em->getRepository(Campaign::class)->find($id);

            if (!$campaign) {
                throw new \Exception('Campaign not found');
            }

            $contacts = $this->em->getRepository(Contact::class)
                ->findBy(['campaign' => $campaign]);

            $contactData = [];
            foreach ($contacts as $contact) {
                $contactData[] = [
                    'id' => $contact->getId(),
                    'email' => $contact->getEmail(),
                    'first_name' => $contact->getFirstName(),
                    'last_name' => $contact->getLastName(),
                    'emails_sent' => $contact->getEmailsSent(),
                    'is_active' => $contact->isActive(),
                    'last_sent_date' => $contact->getLastSentDate() ?
                        $contact->getLastSentDate()->format('Y-m-d H:i') : null,
                    'next_send_date' => $contact->getNextSendDate() ?
                        $contact->getNextSendDate()->format('Y-m-d H:i') : null,
                    'status' => $contact->getStatus(),
                    'unsubscribed' => $contact->isUnsubscribed(),
                ];
            }

            return new JsonResponse([
                'success' => true,
                'campaign_id' => $campaign->getId(),
                'campaign_name' => $campaign->getCampaignName(),
                'total_contacts' => $campaign->getTotalContacts(),
                'contacts' => $contactData,
            ]);

        } catch (\Exception $e) {
            error_log('Error in contactsAction: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Error loading contacts: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Progression de la campagne (AJAX)
     */
    public function progressAction(Request $request, int $id): JsonResponse
    {
        try {
            $campaign = $this->em->getRepository(Campaign::class)->find($id);

            if (!$campaign) {
                throw new \Exception('Campaign not found');
            }

            return new JsonResponse([
                'success' => true,
                'progress' => [
                    'percentage' => $campaign->getProgress(),
                    'emails_sent' => $campaign->getEmailsSent(),
                    'emails_delivered' => $campaign->getEmailsDelivered(),
                    'emails_opened' => $campaign->getEmailsOpened(),
                    'emails_clicked' => $campaign->getEmailsClicked(),
                    'emails_bounced' => $campaign->getEmailsBounced(),
                    'delivery_rate' => $campaign->getDeliveryRate(),
                    'open_rate' => $campaign->getOpenRate(),
                    'click_rate' => $campaign->getClickRate(),
                    'bounce_rate' => $campaign->getBounceRate(),
                    'total_contacts' => $campaign->getTotalContacts(),
                    'remaining_emails' => $campaign->getRemainingEmails(),
                    'estimated_completion' => $campaign->getEstimatedCompletionDate() ?
                        $campaign->getEstimatedCompletionDate()->format('Y-m-d H:i') : null,
                ]
            ]);

        } catch (\Exception $e) {
            error_log('Error in progressAction: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Error loading progress: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Page principale des campagnes
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
            error_log('=== AJAX LIST CAMPAIGNS START ===');

            $page = $request->query->getInt('page', 1);
            $limit = $request->query->getInt('limit', 25);
            $search = $request->query->get('search', '');

            $offset = ($page - 1) * $limit;

            error_log("Params - page: {$page}, limit: {$limit}, search: {$search}");

            $qb = $this->em->createQueryBuilder()
                ->select('c')
                ->from(Campaign::class, 'c')
                ->orderBy('c.createdAt', 'DESC');

            if (!empty($search)) {
                $qb->andWhere('c.campaignName LIKE :search')
                    ->setParameter('search', '%' . $search . '%');
            }

            $countQb = clone $qb;
            $totalRecords = (int) $countQb
                ->select('COUNT(c.id)')
                ->getQuery()
                ->getSingleScalarResult();

            error_log("Total records: {$totalRecords}");

            $qb->setFirstResult($offset)
                ->setMaxResults($limit);

            $campaigns = $qb->getQuery()->getResult();

            error_log("Found " . count($campaigns) . " campaigns");

            $data = [];
            foreach ($campaigns as $campaign) {
                try {
                    $campaignData = [
                        'id' => $campaign->getId(),
                        'campaignName' => $campaign->getCampaignName() ?? ($campaign->getName() ?? 'Untitled'),
                        'description' => $campaign->getDescription() ?? '',
                        'status' => $campaign->getStatus() ?? 'draft',
                        'statusLabel' => $this->getStatusLabel($campaign->getStatus() ?? 'draft'),
                        'statusColor' => $this->getStatusColor($campaign->getStatus() ?? 'draft'),
                        'totalContacts' => $campaign->getTotalContacts() ?? 0,
                        'emailsSent' => $campaign->getEmailsSent() ?? 0,
                        'startDate' => $campaign->getStartDate() ? $campaign->getStartDate()->format('Y-m-d H:i') : '-',
                        'progress' => $campaign->getProgress() ?? 0,
                    ];

                    $data[] = $campaignData;

                } catch (\Exception $e) {
                    error_log("Error processing campaign {$campaign->getId()}: " . $e->getMessage());
                    $data[] = [
                        'id' => $campaign->getId(),
                        'campaignName' => 'Error loading campaign',
                        'description' => '',
                        'status' => 'error',
                        'statusLabel' => 'Error',
                        'statusColor' => 'danger',
                        'totalContacts' => 0,
                        'emailsSent' => 0,
                        'startDate' => '-',
                        'progress' => 0,
                    ];
                }
            }

            $response = [
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalRecords,
                    'total_pages' => $totalRecords > 0 ? ceil($totalRecords / $limit) : 1
                ]
            ];

            error_log('=== AJAX LIST CAMPAIGNS END - Success ===');

            return new JsonResponse($response);

        } catch (\Throwable $e) {
            error_log('❌ Error in ajaxListCampaignAction:');
            error_log('Message: ' . $e->getMessage());
            error_log('File: ' . $e->getFile());
            error_log('Line: ' . $e->getLine());

            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error loading campaigns list'
            ], 500);
        }
    }

    /**
     * Créer une nouvelle campagne
     */
    public function newAction(Request $request): Response
    {
        try {
            $campaign = new Campaign();
            return $this->showForm($request, $campaign, false);
        } catch (\Exception $e) {
            error_log('Error in newAction: ' . $e->getMessage());
            $this->getSession()->getFlashBag()->add('error', 'Error: ' . $e->getMessage());
            return new RedirectResponse($this->router->generate('warmup_campaign_index'));
        }
    }

    /**
     * Éditer une campagne existante
     */
    public function editAction(Request $request, int $id): Response
    {
        try {
            $campaign = $this->em->getRepository(Campaign::class)->find($id);

            if (!$campaign) {
                $this->getSession()->getFlashBag()->add('error', 'Campaign not found');
                return new RedirectResponse($this->router->generate('warmup_campaign_index'));
            }

            return $this->showForm($request, $campaign, true);
        } catch (\Exception $e) {
            error_log('Error in editAction: ' . $e->getMessage());
            $this->getSession()->getFlashBag()->add('error', 'Error: ' . $e->getMessage());
            return new RedirectResponse($this->router->generate('warmup_campaign_index'));
        }
    }

    /**
     * Afficher le formulaire de campagne
     */
    private function showForm(Request $request, Campaign $campaign, bool $isEdit): Response
    {
        try {
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

            // Préparer les formules de warmup pour JavaScript
            $warmupFormulas = [];
            foreach ($warmupTypes as $type) {
                $warmupFormulas[$type->getId()] = [
                    'id' => $type->getId(),
                    'name' => $type->getTypeName(),
                    'formulaType' => $type->getFormulaType(),
                    'description' => $type->getDescription(),
                    'defaultStartVolume' => $type->getDefaultStartVolume(),
                    'defaultDurationDays' => $type->getDefaultDurationDays(),
                    'defaultIncrementPercentage' => $type->getDefaultIncrementPercentage()
                ];
            }

            return new Response(
                $this->twig->render('@MauticWarmUp/Campaign/simple_form.html.twig', [
                    'form' => $form->createView(),
                    'campaign' => $campaign,
                    'domains' => $domains,
                    'warmupTypes' => $warmupTypes,
                    'warmupFormulas' => $warmupFormulas,
                    'templates' => $templates,
                    'isEdit' => $isEdit,
                ])
            );
        } catch (\Exception $e) {
            error_log('Error in showForm: ' . $e->getMessage());
            return new Response('Error loading form: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Sauvegarder la campagne - CORRIGÉ POUR L'OUT OF MEMORY
     */
    // Dans CampaignController.php, modifiez la méthode saveAction

    // public function saveAction(Request $request): Response
    // {
    //     try {
    //         error_log('=== SAVE CAMPAIGN ACTION ===');

    //         if (!$request->isMethod('POST')) {
    //             throw new \Exception('Invalid request method');
    //         }

    //         // Récupérer les données du formulaire
    //         $formData = $request->request->all('campaign_form');
    //         error_log('Form data keys: ' . implode(', ', array_keys($formData)));

    //         $campaignId = $formData['id'] ?? null;
    //         $isEdit = $campaignId ? true : false;

    //         error_log('Campaign ID: ' . $campaignId);
    //         error_log('Is Edit: ' . ($isEdit ? 'Yes' : 'No'));

    //         $campaign = $isEdit
    //             ? $this->em->getRepository(Campaign::class)->find($campaignId)
    //             : new Campaign();

    //         if ($isEdit && !$campaign) {
    //             throw new \Exception('Campaign not found for ID: ' . $campaignId);
    //         }

    //         // Créer le formulaire AVANT de l'utiliser
    //         $form = $this->formFactory->create(SimpleCampaignFormType::class, $campaign, [
    //             'is_edit' => $isEdit,
    //         ]);

    //         // IMPORTANT: HandleRequest va traiter les données ET le token CSRF
    //         $form->handleRequest($request);

    //         if ($form->isSubmitted() && $form->isValid()) {
    //             error_log('Form is valid, processing...');

    //             // Récupérer le type de séquence depuis le formulaire soumis
    //             $sequenceType = $form->get('sequenceType')->getData() ?? 'single';
    //             $campaign->setSequenceType($sequenceType);

    //             // Traiter les séquences d'emails si c'est le mode multiple
    //             if ($sequenceType === 'multiple') {
    //                 $emailSequences = $this->processEmailSequences($request);
    //                 $campaign->setEmailSequences($emailSequences);
    //                 error_log("Saved " . count($emailSequences) . " email sequences");
    //             } else {
    //                 // Mode single - utiliser les champs subjectTemplate et customMessage
    //                 $subject = $form->get('subjectTemplate')->getData() ?? '';
    //                 $message = $form->get('customMessage')->getData() ?? '';

    //                 // Créer une séquence simple avec un seul email
    //                 $emailSequences = [
    //                     [
    //                         'day' => 1,
    //                         'subject' => $subject,
    //                         'body' => $message
    //                     ]
    //                 ];

    //                 $emailSequences = $request->request->all('emailSequences') ?? [];


    //                 $campaign->setEmailSequences(
    //                     empty($emailSequences) ? null : json_encode($emailSequences)
    //                 );
    //             }

    //             // Traiter les contacts selon la source
    //             $contactSource = $campaign->getContactSource();
    //             $contacts = [];

    //             switch ($contactSource) {
    //                 case 'manual':
    //                     $manualContacts = $form->get('manualContacts')->getData() ?? '';
    //                     $contacts = $this->processManualContacts($manualContacts);
    //                     break;

    //                 case 'mautic':
    //                     $segmentId = $campaign->getSegmentId();
    //                     if ($segmentId) {
    //                         $contacts = $this->getContactsFromSegment($segmentId);
    //                     }
    //                     break;

    //                 case 'csv':
    //                     $csvFile = $request->files->get('campaign_form')['csvFile'] ?? null;
    //                     if ($csvFile) {
    //                         $contacts = $this->processCsvFile($csvFile);
    //                     }
    //                     break;
    //             }

    //             $campaign->setTotalContacts(count($contacts));
    //             error_log("Contacts to save: " . count($contacts));

    //             // Calculer le plan de warmup
    //             $warmupPlan = $this->calculateWarmupPlan($campaign);
    //             if (!empty($warmupPlan)) {
    //                 $campaign->setWarmupPlan($warmupPlan);
    //             }

    //             if (!$isEdit) {
    //                 $campaign->setCreatedAt(new \DateTime());
    //             }
    //             $campaign->setUpdatedAt(new \DateTime());

    //             // Vérifier si c'est un bouton "Save and Activate"
    //             $saveAndActivate = $request->request->has('campaign_form[saveAndActivate]');
    //             if ($saveAndActivate) {
    //                 $campaign->setStatus('active');
    //                 error_log('Setting campaign status to ACTIVE');
    //             }

    //             // Sauvegarder d'abord la campagne
    //             $this->em->persist($campaign);
    //             $this->em->flush();

    //             error_log('Campaign saved with ID: ' . $campaign->getId());

    //             // Sauvegarder les contacts en batch pour éviter l'out of memory
    //             if (!empty($contacts)) {
    //                 $this->saveContactsInBatch($campaign, $contacts);
    //                 error_log('Contacts saved: ' . count($contacts));
    //             }

    //             $message = $isEdit ? 'Campaign updated successfully!' : 'Campaign created successfully!';
    //             error_log('Success: ' . $message);

    //             $this->getSession()->getFlashBag()->add('notice', $message);
    //             return new RedirectResponse($this->router->generate('warmup_campaign_index'));

    //         } else {
    //             // FORMULAIRE INVALIDE - réafficher avec les variables nécessaires
    //             $errors = [];
    //             foreach ($form->getErrors(true) as $error) {
    //                 $errors[] = $error->getMessage();
    //             }
    //             error_log('Form errors: ' . print_r($errors, true));

    //             // Vérifier spécifiquement les erreurs CSRF
    //             if ($form->isSubmitted() && !$form->isValid()) {
    //                 $csrfErrors = [];
    //                 foreach ($form->getErrors() as $error) {
    //                     if (strpos($error->getMessage(), 'CSRF') !== false) {
    //                         $csrfErrors[] = $error->getMessage();
    //                     }
    //                 }
    //                 if (!empty($csrfErrors)) {
    //                     error_log('CSRF Errors: ' . print_r($csrfErrors, true));
    //                     // Régénérer le token CSRF
    //                     $this->getSession()->remove('_csrf/token');
    //                 }
    //             }

    //             $errorMessage = 'Form validation failed: ' . implode(', ', $errors);
    //             error_log($errorMessage);

    //             $this->getSession()->getFlashBag()->add('error', $errorMessage);

    //             // CHANGEMENT IMPORTANT: Récupérer TOUTES les variables nécessaires pour Twig
    //             $domains = $this->em->getRepository(Domain::class)->findBy(['isActive' => true]);
    //             $warmupTypes = $this->em->getRepository(WarmupType::class)->findAll();
    //             $templates = $this->em->getRepository(Template::class)->findBy(['isActive' => true]);

    //             // Préparer les formules de warmup pour JavaScript
    //             $warmupFormulas = [];
    //             foreach ($warmupTypes as $type) {
    //                 $warmupFormulas[$type->getId()] = [
    //                     'id' => $type->getId(),
    //                     'name' => $type->getTypeName(),
    //                     'formulaType' => $type->getFormulaType(),
    //                     'description' => $type->getDescription(),
    //                     'defaultStartVolume' => $type->getDefaultStartVolume(),
    //                     'defaultDurationDays' => $type->getDefaultDurationDays(),
    //                     'defaultIncrementPercentage' => $type->getDefaultIncrementPercentage()
    //                 ];
    //             }

    //             return new Response(
    //                 $this->twig->render('@MauticWarmUp/Campaign/simple_form.html.twig', [
    //                     'form' => $form->createView(),
    //                     'campaign' => $campaign,
    //                     'domains' => $domains,
    //                     'warmupTypes' => $warmupTypes,
    //                     'warmupFormulas' => $warmupFormulas, // <-- AJOUTÉ
    //                     'templates' => $templates,
    //                     'isEdit' => $isEdit,
    //                 ])
    //             );
    //         }

    //     } catch (\Exception $e) {
    //         error_log('Error in saveAction: ' . $e->getMessage());
    //         error_log('Trace: ' . $e->getTraceAsString());
    //         $this->getSession()->getFlashBag()->add('error', 'Error saving campaign: ' . $e->getMessage());
    //         return new RedirectResponse($this->router->generate('warmup_campaign_index'));
    //     }
    // }

    // REMPLACER la méthode saveAction dans CampaignController.php

    /**
     * Sauvegarder la campagne - Version simplifiée et corrigée
     */
    /**
     * Sauvegarder la campagne - Version corrigée pour AJAX
     */
    /**
     * Sauvegarder la campagne - Version corrigée pour CSRF
     */

    public function saveAction(Request $request): Response
    {
        try {
            error_log('=== SAVE CAMPAIGN ACTION START ===');
            error_log('Request Method: ' . $request->getMethod());

            // Vérifier que c'est bien une requête POST
            if (!$request->isMethod('POST')) {
                error_log('❌ Not a POST request');
                throw new \Exception('Invalid request method. Only POST allowed.');
            }

            // 1️⃣ Récupérer les données du formulaire
            $formData = $request->request->all('campaign_form');

            error_log('=== FORM DATA RECEIVED ===');
            error_log('Keys in formData: ' . implode(', ', array_keys($formData)));

            // Log CSRF token (mais ne pas vérifier manuellement)
            if (isset($formData['_token'])) {
                error_log('CSRF Token present: Yes (length: ' . strlen($formData['_token']) . ')');
            } else {
                error_log('CSRF Token present: No');
            }

            // 2️⃣ Déterminer si c'est une édition
            $campaignId = $formData['id'] ?? null;
            $isEdit = !empty($campaignId);

            error_log('Campaign ID: ' . ($campaignId ?: 'NEW'));
            error_log('Is Edit: ' . ($isEdit ? 'Yes' : 'No'));

            // 3️⃣ Récupérer ou créer la campagne
            if ($isEdit) {
                $campaign = $this->em->getRepository(Campaign::class)->find($campaignId);
                if (!$campaign) {
                    throw new \Exception('Campaign not found for ID: ' . $campaignId);
                }
                error_log('Editing existing campaign: ' . $campaign->getCampaignName());
            } else {
                $campaign = new Campaign();
                error_log('Creating new campaign');
            }

            // 4️⃣ Gérer endDate avant le formulaire (pour éviter les erreurs)
            $endDateToSet = null;
            if (isset($formData['endDate']) && !empty($formData['endDate'])) {
                $endDateString = $formData['endDate'];
                error_log('Raw endDate from request: ' . $endDateString);

                try {
                    if (strpos($endDateString, 'T') !== false) {
                        // Format ISO 8601: "2024-01-15T09:00"
                        $endDateToSet = new \DateTime($endDateString);
                    } else {
                        // Format MySQL: "2024-01-15 09:00:00" ou autre
                        $endDateToSet = \DateTime::createFromFormat('Y-m-d H:i:s', $endDateString);
                        if (!$endDateToSet) {
                            $endDateToSet = new \DateTime($endDateString);
                        }
                    }
                    error_log('✅ Parsed endDate: ' . $endDateToSet->format('Y-m-d H:i:s'));

                    // Retirer du formData pour éviter l'erreur de validation
                    unset($formData['endDate']);
                    // Mettre à jour la requête
                    $request->request->set('campaign_form', $formData);

                } catch (\Exception $e) {
                    error_log('❌ Error parsing endDate: ' . $e->getMessage());
                    $endDateToSet = null;
                    // Retirer quand même pour éviter l'erreur
                    unset($formData['endDate']);
                    $request->request->set('campaign_form', $formData);
                }
            } else {
                error_log('No endDate in request data');
            }

            // 5️⃣ Créer le formulaire - LAISSER Symfony gérer le CSRF
            $form = $this->formFactory->create(SimpleCampaignFormType::class, $campaign, [
                'is_edit' => $isEdit,
                'csrf_protection' => true, // Laisser Symfony gérer
            ]);

            // 6️⃣ Utiliser handleRequest au lieu de submit pour que Symfony gère tout
            $form->handleRequest($request);

            error_log('=== FORM STATUS ===');
            error_log('Submitted: ' . ($form->isSubmitted() ? 'Yes' : 'No'));
            error_log('Valid: ' . ($form->isValid() ? 'Yes' : 'No'));
            error_log('CSRF Valid: ' . ($form->isSubmitted() ? 'Auto-checked by Symfony' : 'Not submitted'));

            // 7️⃣ Si le formulaire n'est pas valide
            if (!$form->isSubmitted() || !$form->isValid()) {
                error_log('❌ FORM VALIDATION FAILED');

                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errorMsg = $error->getMessage();
                    $errors[] = $errorMsg;
                    error_log('Form error: ' . $errorMsg);

                    // Afficher aussi la cause de l'erreur
                    if ($error->getCause()) {
                        error_log('Cause: ' . $error->getCause());
                    }
                }

                // Retourner à la vue avec les erreurs
                $errorMessage = 'Form validation failed: ' . implode(', ', $errors);
                error_log('Error message: ' . $errorMessage);

                $this->getSession()->getFlashBag()->add('error', $errorMessage);

                // Si c'est une requête AJAX, retourner JSON
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => $errorMessage,
                        'errors' => $errors
                    ]);
                }

                return $this->redirectToRoute('warmup_campaign_new');
            }

            // 8️⃣ ✅ FORMULAIRE VALIDE - TRAITEMENT
            error_log('✅ FORM IS VALID - PROCESSING DATA...');

            // Ajouter endDate (si calculé)
            if ($endDateToSet) {
                $campaign->setEndDate($endDateToSet);
                error_log('✅ EndDate set to entity: ' . $endDateToSet->format('Y-m-d H:i:s'));
            }

            // Déterminer l'action (save vs saveAndActivate)
            $saveAndActivate = $request->request->has('campaign_form[saveAndActivate]');
            error_log('Save and Activate: ' . ($saveAndActivate ? 'Yes' : 'No'));

            // Définir le statut
            $campaign->setStatus($saveAndActivate ? 'active' : 'draft');

            // 9️⃣ TRAITEMENT DES CONTACTS (garder votre code existant)
            $totalContacts = 0;

            // Contacts manuels
            $manualContacts = $form->get('manualContacts')->getData();
            if ($manualContacts) {
                error_log('Processing manual contacts');
                $emails = array_filter(
                    array_map('trim', explode("\n", $manualContacts)),
                    function ($email) {
                        return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
                    }
                );
                $manualCount = count($emails);
                $totalContacts += $manualCount;
                error_log('Valid manual contacts: ' . $manualCount);

                if ($manualCount > 0) {
                    $campaign->setContactSource('manual');
                    $this->saveContactsInBatch($campaign, array_map(function ($email) {
                        return [
                            'email' => $email,
                            'first_name' => '',
                            'last_name' => '',
                            'source' => 'manual'
                        ];
                    }, $emails));
                }
            }

            // Fichier CSV
            $csvFile = $form->get('csvFile')->getData();
            if ($csvFile) {
                error_log('Processing CSV file: ' . $csvFile->getClientOriginalName());
                $campaign->setContactSource('csv');

                $csvContacts = $this->processCsvFile($csvFile);
                $totalContacts += count($csvContacts);
                error_log('CSV contacts: ' . count($csvContacts));

                if (!empty($csvContacts)) {
                    $this->saveContactsInBatch($campaign, $csvContacts);
                }
            }

            // Segment Mautic
            $segmentId = $form->get('segmentId')->getData();
            if ($segmentId) {
                error_log('Processing segment ID: ' . $segmentId);
                $campaign->setContactSource('segment');
                $campaign->setSegmentId($segmentId);

                $segmentContacts = $this->getContactsFromSegment($segmentId);
                $totalContacts += count($segmentContacts);
                error_log('Segment contacts: ' . count($segmentContacts));

                if (!empty($segmentContacts)) {
                    $this->saveContactsInBatch($campaign, $segmentContacts);
                }
            }

            // Mettre à jour le total
            $campaign->setTotalContacts($totalContacts);
            error_log('Total contacts: ' . $totalContacts);

            // 🔟 CALCULER LE PLAN DE WARMUP
            $warmupPlan = $this->calculateWarmupPlan($campaign);
            if (!empty($warmupPlan)) {
                $campaign->setWarmupPlan($warmupPlan);
                error_log('Warmup plan calculated: ' . count($warmupPlan) . ' days');
            }

            // 1️⃣1️⃣ TRAITER LES SÉQUENCES D'EMAILS
            $sequenceType = $form->get('sequenceType')->getData() ?? 'single';
            $campaign->setSequenceType($sequenceType);


            if ($sequenceType === 'multiple') {
                $emailSequencesData = $form->get('emailSequences')->getData();

                error_log('Email sequences data type: ' . gettype($emailSequencesData));
                error_log('Email sequences data: ' . print_r($emailSequencesData, true));

                $emailSequences = [];

                // Gérer le cas où c'est une chaîne JSON
                if (is_string($emailSequencesData)) {
                    if ($emailSequencesData && $emailSequencesData !== '[]') {
                        try {
                            $decoded = json_decode($emailSequencesData, true);
                            if (is_array($decoded)) {
                                $emailSequences = $decoded;
                            }
                        } catch (\Exception $e) {
                            error_log('Error decoding emailSequences JSON: ' . $e->getMessage());
                        }
                    }
                }
                // Gérer le cas où c'est déjà un tableau
                elseif (is_array($emailSequencesData)) {
                    $emailSequences = $emailSequencesData;
                }

                if (!empty($emailSequences)) {
                    $campaign->setEmailSequences($emailSequences);
                    error_log('Saved ' . count($emailSequences) . ' email sequences');
                } else {
                    error_log('No email sequences found');
                    $campaign->setEmailSequences([]);
                }
            } else {
                // Mode single
                $subject = $form->get('subjectTemplate')->getData() ?? '';
                $message = $form->get('customMessage')->getData() ?? '';

                if ($subject && $message) {
                    $campaign->setEmailSequences([
                        [
                            'day' => 1,
                            'subject' => $subject,
                            'body' => $message
                        ]
                    ]);
                    error_log('Saved single email as sequence');
                } else {
                    // Toujours définir un tableau vide si pas de contenu
                    $campaign->setEmailSequences([]);
                    error_log('No email content for single mode, setting empty sequences');
                }
            }

            // Timestamps
            if (!$isEdit) {
                $campaign->setCreatedAt(new \DateTime());
            }
            $campaign->setUpdatedAt(new \DateTime());

            // 1️⃣2️⃣ SAUVEGARDER DANS LA BASE DE DONNÉES
            error_log('💾 PERSISTING CAMPAIGN TO DATABASE...');

            $this->em->persist($campaign);
            $this->em->flush();

            error_log('✅ CAMPAIGN SAVED SUCCESSFULLY');
            error_log('Campaign ID: ' . $campaign->getId());

            // 1️⃣3️⃣ MESSAGE DE SUCCÈS ET REDIRECTION
            $successMessage = $isEdit
                ? ($saveAndActivate ? 'Campaign updated and activated!' : 'Campaign updated!')
                : ($saveAndActivate ? 'Campaign created and activated!' : 'Campaign created!');

            error_log('Success message: ' . $successMessage);

            $this->getSession()->getFlashBag()->add('notice', $successMessage);

            // Si c'est AJAX, dretourner JSON
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => $successMessage,
                    'redirect' => $this->router->generate('warmup_campaign_index')
                ]);
            }

            // Sinon, rediriger normalement
            return new RedirectResponse($this->router->generate('warmup_campaign_index'));

        } catch (\Exception $e) {
            error_log('❌ ERROR IN saveAction');
            error_log('Message: ' . $e->getMessage());
            error_log('File: ' . $e->getFile() . ':' . $e->getLine());
            error_log('Trace: ' . $e->getTraceAsString());

            $errorMessage = 'An error occurred: ' . $e->getMessage();

            $this->getSession()->getFlashBag()->add('error', $errorMessage);

            // Si c'est AJAX
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $errorMessage
                ], 500);
            }

            return new RedirectResponse($this->router->generate('warmup_campaign_new'));

        } finally {
            error_log('=== SAVE CAMPAIGN ACTION END ===');
        }
    }
    /**
     * Helper pour rendre un message flash d'erreur
     */
    private function renderErrorFlash(string $message): string
    {
        return $this->twig->render('@MauticCore/Notification/flash_messages.html.twig', [
            'flashes' => ['error' => [$message]]
        ]);
    }
    private function validateForm(Campaign $campaign, array $formData): array
    {
        $errors = [];

        // Validation de base
        if (empty($campaign->getCampaignName())) {
            $errors[] = 'Campaign name is required';
        }

        if (empty($campaign->getDomain())) {
            $errors[] = 'Domain is required';
        }

        if (empty($campaign->getWarmupType())) {
            $errors[] = 'Warmup type is required';
        }

        // Validation conditionnelle pour le contenu email
        $sequenceType = $formData['sequenceType'] ?? 'single';

        if ($sequenceType === 'single') {
            if (empty($formData['subjectTemplate'])) {
                $errors[] = 'Email subject is required for single email campaigns';
            }

            if (empty($formData['customMessage'])) {
                $errors[] = 'Email content is required for single email campaigns';
            }
        } else {
            // Validation pour les séquences multiples
            $emailSequences = json_decode($formData['emailSequences'] ?? '[]', true);
            if (empty($emailSequences)) {
                $errors[] = 'At least one email sequence is required for multiple email campaigns';
            } else {
                foreach ($emailSequences as $index => $sequence) {
                    if (empty($sequence['subject'])) {
                        $errors[] = "Subject is required for email " . ($index + 1);
                    }
                    if (empty($sequence['body'])) {
                        $errors[] = "Content is required for email " . ($index + 1);
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Nettoyer les données du formulaire pour éviter l'out of memory
     */
    private function cleanFormData(array $formData): array
    {
        // Garder seulement les champs essentiels pour éviter l'out of memory
        $cleanData = [];

        $essentialFields = [
            'campaignName',
            'description',
            'domain',
            'warmupType',
            'startVolume',
            'durationDays',
            'dailyIncrement',
            'startDate',
            'sendTime',
            'sendFrequency',
            'enableWeekends',
            'enableRandomization',
            'contactSource',
            'segmentId',
            'subjectTemplate',
            'customMessage'
        ];

        foreach ($essentialFields as $field) {
            if (isset($formData[$field])) {
                $cleanData[$field] = $formData[$field];
            }
        }

        // Pour manualContacts, limiter la taille
        if (isset($formData['manualContacts'])) {
            $manualContacts = substr($formData['manualContacts'], 0, 10000); // Limiter à 10k caractères
            $cleanData['manualContacts'] = $manualContacts;
        }

        return $cleanData;
    }

    /**
     * Traiter les séquences d'emails en mode multiple
     */
    private function processEmailSequences(Request $request): array
    {
        $formData = $request->request->all('campaign_form');
        $sequences = [];

        // Récupérer les séquences depuis le champ JSON
        if (isset($formData['emailSequences']) && !empty($formData['emailSequences'])) {
            try {
                $sequencesData = json_decode($formData['emailSequences'], true);
                if (is_array($sequencesData)) {
                    foreach ($sequencesData as $index => $sequence) {
                        if (isset($sequence['subject']) && isset($sequence['body'])) {
                            $sequences[] = [
                                'day' => $index + 1,
                                'subject' => substr($sequence['subject'], 0, 500), // Limiter la taille
                                'body' => substr($sequence['body'], 0, 5000) // Limiter à 5000 caractères
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log('Error parsing email sequences: ' . $e->getMessage());
            }
        }

        // Sinon, chercher dans les paramètres de formulaire individuels
        if (empty($sequences)) {
            $i = 1;
            while (true) {
                $subjectKey = "email_sequences[$i][subject]";
                $bodyKey = "email_sequences[$i][body]";

                $subject = $request->request->get($subjectKey);
                $body = $request->request->get($bodyKey);

                if (!$subject && !$body) {
                    break;
                }

                if ($subject && $body) {
                    $sequences[] = [
                        'day' => $i,
                        'subject' => substr($subject, 0, 500),
                        'body' => substr($body, 0, 5000)
                    ];
                }

                $i++;
                if ($i > 30)
                    break; // Limiter à 30 séquences maximum
            }
        }

        return $sequences;
    }

    // Méthode helper pour traiter les contacts manuels
    private function processManualContacts(string $manualText): array
    {
        $contacts = [];
        $lines = array_filter(array_map('trim', explode("\n", $manualText)));

        foreach ($lines as $line) {
            $email = trim($line);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $contacts[] = [
                    'email' => $email,
                    'first_name' => '',
                    'last_name' => '',
                    'source' => 'manual'
                ];
            }
        }

        return $contacts;
    }

    /**
     * Traiter le fichier CSV
     */
    private function processCsvFile(UploadedFile $csvFile): array
    {
        $contacts = [];
        $csvPath = $csvFile->getRealPath();

        if (($handle = fopen($csvPath, 'r')) !== false) {
            // Ignorer l'en-tête (première ligne)
            fgetcsv($handle);

            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) >= 1) {
                    $email = trim($data[0]);

                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $contact = [
                            'email' => $email,
                            'first_name' => isset($data[1]) ? trim($data[1]) : '',
                            'last_name' => isset($data[2]) ? trim($data[2]) : '',
                        ];
                        $contacts[] = $contact;
                    }
                }
            }
            fclose($handle);
        }

        return $contacts;
    }

    /**
     * Sauvegarder les contacts dans la base de données EN BATCH
     */
    private function saveContactsInBatch(Campaign $campaign, array $contacts): void
    {
        $batchSize = 100;
        $contactCount = count($contacts);

        // S'assurer que la campagne est persistée
        if (!$this->em->contains($campaign)) {
            $this->em->persist($campaign);
        }

        for ($i = 0; $i < $contactCount; $i += $batchSize) {
            $batch = array_slice($contacts, $i, $batchSize);

            foreach ($batch as $contactData) {
                if (empty($contactData['email'])) {
                    continue;
                }

                $contact = new Contact();
                // Utilisez la méthode addContact de Campaign qui gère la relation bidirectionnelle
                $campaign->addContact($contact);

                $contact->setEmail($contactData['email']);
                $contact->setFirstName($contactData['first_name'] ?? '');
                $contact->setLastName($contactData['last_name'] ?? '');
                $contact->setSequenceDay(1);
                $contact->setDaysBetweenEmails(2);
                $contact->setEmailsSent(0);
                $contact->setIsActive(true);
                $contact->setIsPublished(true);
                $contact->setStatus('pending');
                $contact->setDateAdded(new \DateTime());
                $contact->setUnsubscribeToken(bin2hex(random_bytes(16)));

                $this->em->persist($contact);
            }

            $this->em->flush();
            $this->em->clear(Contact::class);

            error_log("Saved batch " . (($i / $batchSize) + 1) . " of " . ceil($contactCount / $batchSize));
        }

        // Flush final pour s'assurer que tout est sauvegardé
        $this->em->flush();
    }

    /**
     * Calculer le plan de warmup pour une campagne
     */
    private function calculateWarmupPlan(Campaign $campaign): array
    {
        $totalContacts = $campaign->getTotalContacts();
        $durationDays = $campaign->getDurationDays();
        $startVolume = $campaign->getStartVolume();
        $dailyIncrement = $campaign->getDailyIncrement();
        $warmupType = $campaign->getWarmupType();

        if (!$warmupType || $totalContacts <= 0 || $durationDays <= 0) {
            return [];
        }

        $formulaType = $warmupType->getFormulaType();
        $plan = [];

        for ($day = 1; $day <= $durationDays; $day++) {
            $emails = $this->calculateDailyVolume($campaign, $day);
            $plan[] = [
                'day' => $day,
                'emails' => $emails,
                'cumulative' => array_sum(array_column(array_slice($plan, 0, $day - 1), 'emails')) + $emails
            ];
        }

        return $plan;
    }

    private function calculateDailyVolume(Campaign $campaign, int $day): int
    {
        $totalContacts = $campaign->getTotalContacts();
        $durationDays = $campaign->getDurationDays();
        $startVolume = $campaign->getStartVolume();
        $dailyIncrement = $campaign->getDailyIncrement();
        $warmupType = $campaign->getWarmupType();

        if (!$warmupType) {
            return 0;
        }

        $formulaType = $warmupType->getFormulaType();
        $alpha = 0.10;

        switch ($formulaType) {
            case 'arithmetic':
                return min(
                    $startVolume + (($day - 1) * $dailyIncrement),
                    ceil($totalContacts / $durationDays)
                );

            case 'geometric':
                $r = 1 + ($dailyIncrement / 100);
                return min(
                    $startVolume * pow($r, $day - 1),
                    ceil($totalContacts / $durationDays)
                );

            case 'flat':
                return min($startVolume, ceil($totalContacts / $durationDays));

            case 'progressive':
                $target = ceil($totalContacts / $durationDays);
                $previous = ($day === 1) ? $startVolume :
                    $this->calculateDailyVolume($campaign, $day - 1);
                return (int) round($previous + $alpha * ($target - $previous));

            case 'randomize':
                $base = $startVolume + (($day - 1) * $dailyIncrement);
                $min = $base * 0.85;
                $max = $base * 1.15;
                return (int) rand($min, $max);

            default:
                return $startVolume;
        }
    }

    /**
     * Preview CSV file (AJAX)
     */
    public function previewCsvAction(Request $request): JsonResponse
    {
        try {
            $file = $request->files->get('csvFile');

            if (!$file) {
                throw new \Exception('No CSV file uploaded');
            }

            $contacts = [];
            $csvPath = $file->getRealPath();
            $previewData = [];
            $headers = [];

            if (($handle = fopen($csvPath, 'r')) !== false) {
                $headers = fgetcsv($handle) ?: ['email', 'first_name', 'last_name'];

                $rowCount = 0;
                while (($data = fgetcsv($handle)) !== false && $rowCount < 10) {
                    if (count($data) >= 1) {
                        $email = trim($data[0]);

                        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $contact = [
                                'email' => $email,
                                'first_name' => isset($data[1]) ? trim($data[1]) : '',
                                'last_name' => isset($data[2]) ? trim($data[2]) : '',
                            ];
                            $contacts[] = $contact;
                            $previewData[] = $data;
                            $rowCount++;
                        }
                    }
                }
                fclose($handle);
            }

            $totalContacts = 0;
            if (($handle = fopen($csvPath, 'r')) !== false) {
                fgetcsv($handle);
                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) >= 1) {
                        $email = trim($data[0]);
                        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $totalContacts++;
                        }
                    }
                }
                fclose($handle);
            }

            return new JsonResponse([
                'success' => true,
                'total_contacts' => $totalContacts,
                'preview' => $previewData,
                'headers' => $headers,
                'message' => "CSV file processed. Found {$totalContacts} valid contacts."
            ]);

        } catch (\Exception $e) {
            error_log('Error in previewCsvAction: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Error processing CSV: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Upload CSV file (AJAX)
     */
    public function uploadCsvAction(Request $request): JsonResponse
    {
        try {
            $file = $request->files->get('csvFile');

            if (!$file) {
                throw new \Exception('No CSV file uploaded');
            }

            $tempDir = sys_get_temp_dir() . '/warmup_csv/';
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            $originalName = $file->getClientOriginalName();
            $tempFileName = uniqid('csv_', true) . '_' . $originalName;
            $tempFilePath = $tempDir . $tempFileName;
            $file->move($tempDir, $tempFileName);

            $totalContacts = 0;
            if (($handle = fopen($tempFilePath, 'r')) !== false) {
                fgetcsv($handle);

                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) >= 1) {
                        $email = trim($data[0]);

                        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $totalContacts++;
                        }
                    }
                }
                fclose($handle);
            }

            $sessionKey = 'csv_import_' . md5($tempFileName);
            $this->getSession()->set($sessionKey, [
                'file_path' => $tempFilePath,
                'total_contacts' => $totalContacts,
                'original_name' => $originalName,
                'upload_time' => time()
            ]);

            return new JsonResponse([
                'success' => true,
                'total_contacts' => $totalContacts,
                'session_key' => $sessionKey,
                'message' => "CSV file uploaded successfully. Found " . $totalContacts . " valid contacts."
            ]);

        } catch (\Exception $e) {
            error_log('Error in uploadCsvAction: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Error uploading CSV: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Démarrer une campagne (AJAX)
     */
    public function startAction(Request $request, int $id): JsonResponse
    {
        try {
            error_log('=== START ACTION DEBUG ===');

            $campaign = $this->em->getRepository(Campaign::class)->find($id);

            if (!$campaign) {
                throw new \Exception('Campaign not found');
            }

            $currentStatus = $campaign->getStatus();
            if (!in_array($currentStatus, ['draft', 'paused'])) {
                throw new \Exception('Campaign cannot be started from status: ' . $currentStatus);
            }

            $campaign->setStatus('active');
            $campaign->setStartDate(new \DateTime());
            $campaign->setUpdatedAt(new \DateTime());

            $this->em->persist($campaign);


            $this->em->flush();

            error_log('✅ Campaign started: ' . $campaign->getId());

            return new JsonResponse([
                'success' => true,
                'message' => 'Campaign started successfully!',
                'status' => 'active',
                'campaign_id' => $campaign->getId(),
            ]);

        } catch (\Exception $e) {
            error_log('❌ Error in startAction: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
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

            $sql = "
                SELECT l.id, l.email, l.firstname, l.lastname 
                FROM leads l
                INNER JOIN lead_lists_leads ll ON l.id = ll.lead_id
                WHERE ll.leadlist_id = ?
                AND ll.manually_removed = 0
                AND l.email IS NOT NULL
                AND l.email != ''
                LIMIT ?
            ";

            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(1, $segmentId);
            $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
            $stmt->execute();

            $contacts = $stmt->fetchAllAssociative();

            $countSql = "
                SELECT COUNT(l.id) as total
                FROM leads l
                INNER JOIN lead_lists_leads ll ON l.id = ll.lead_id
                WHERE ll.leadlist_id = ?
                AND ll.manually_removed = 0
                AND l.email IS NOT NULL
                AND l.email != ''
            ";

            $countStmt = $this->connection->prepare($countSql);
            $countStmt->bindValue(1, $segmentId);
            $countStmt->execute();
            $total = $countStmt->fetchOne();

            return new JsonResponse([
                'success' => true,
                'contacts' => $contacts,
                'count' => count($contacts),
                'total_available' => $total,
            ]);

        } catch (\Exception $e) {
            error_log('Error in previewContactsAction: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Error previewing contacts: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Envoyer un email test (AJAX)
     */
    public function sendTestEmailAction(Request $request): JsonResponse
    {
        try {
            error_log('=== SEND TEST EMAIL ACTION ===');

            $testEmail = $request->request->get('testEmail');
            $domainId = $request->request->get('domainId');
            $subject = $request->request->get('subject', 'Test Email');
            $message = $request->request->get('message', 'This is a test email');

            error_log("Received: testEmail=$testEmail, domainId=$domainId");

            if (!$testEmail || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Valid test email address is required');
            }

            if (!$domainId) {
                throw new \Exception('Domain ID is required');
            }

            $domain = $this->em->getRepository(Domain::class)->find($domainId);

            if (!$domain) {
                throw new \Exception('Domain not found for ID: ' . $domainId);
            }

            $result = $this->emailSender->sendTestEmail(
                $domain,
                $testEmail,
                $subject,
                $message
            );

            error_log('✅ Test email sent to: ' . $testEmail);

            return new JsonResponse([
                'success' => true,
                'message' => 'Test email sent successfully!',
                'details' => $result,
            ]);

        } catch (\Exception $e) {
            error_log('❌ Error in sendTestEmailAction: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Error sending test email: ' . $e->getMessage(),
            ], 400);
        }
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

        $buttons[] = '<a href="' . $this->router->generate('warmup_campaign_edit', ['id' => $id]) .
            '" class="btn btn-xs btn-default" title="Edit"><i class="ri-edit-line ri-fw"></i></a>';

        if ($campaign->getStatus() === 'draft' || $campaign->getStatus() === 'paused') {
            $buttons[] = '<button class="btn btn-xs btn-success start-campaign" data-id="' . $id .
                '" title="Start"><i class="ri-play-line ri-fw"></i></button>';
        }

        if ($campaign->getStatus() === 'active') {
            $buttons[] = '<button class="btn btn-xs btn-warning pause-campaign" data-id="' . $id .
                '" title="Pause"><i class="ri-pause-line ri-fw"></i></button>';
        }

        if ($campaign->getStatus() === 'paused') {
            $buttons[] = '<button class="btn btn-xs btn-info resume-campaign" data-id="' . $id .
                '" title="Resume"><i class="ri-play-line ri-fw"></i></button>';
        }

        return implode(' ', $buttons);
    }

    private function getContactsFromSegment(int $segmentId): array
    {
        try {
            error_log("Getting contacts from segment ID: " . $segmentId);

            $segmentCheck = $this->connection->prepare("
            SELECT id, name, is_published 
            FROM lead_lists 
            WHERE id = ? AND is_published = 1
        ");
            $segmentCheck->bindValue(1, $segmentId);
            $segmentCheck->execute();
            $segment = $segmentCheck->fetchAssociative();

            if (!$segment) {
                throw new \Exception("Segment not found or not published");
            }

            $sql = "
            SELECT 
                l.id,
                l.email,
                l.firstname as first_name,
                l.lastname as last_name,
                l.lead_email_sent_count,
                l.last_active,
                l.date_identified
            FROM leads l
            INNER JOIN lead_lists_leads ll ON l.id = ll.lead_id
            WHERE ll.leadlist_id = ?
            AND ll.manually_removed = 0
            AND l.email IS NOT NULL
            AND l.email != ''
            AND l.email NOT LIKE '%@example.com'
            ORDER BY l.date_identified DESC
            LIMIT 5000
        ";

            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(1, $segmentId);
            $stmt->execute();

            $contacts = $stmt->fetchAllAssociative();

            error_log("Found " . count($contacts) . " contacts in segment");

            $formattedContacts = [];
            foreach ($contacts as $contact) {
                $formattedContacts[] = [
                    'email' => $contact['email'] ?? '',
                    'first_name' => $contact['first_name'] ?? '',
                    'last_name' => $contact['last_name'] ?? '',
                    'lead_id' => $contact['id'] ?? null,
                    'metadata' => [
                        'segment_id' => $segmentId,
                        'segment_name' => $segment['name'] ?? '',
                        'lead_email_sent_count' => $contact['lead_email_sent_count'] ?? 0,
                        'last_active' => $contact['last_active'] ?? null,
                        'date_identified' => $contact['date_identified'] ?? null
                    ]
                ];
            }

            return $formattedContacts;

        } catch (\Exception $e) {
            error_log("Error getting contacts from segment: " . $e->getMessage());
            return [];
        }
    }

    /**
     * AJAX: Prévisualiser les contacts d'un segment
     */
    public function previewSegmentContactsAction(Request $request): JsonResponse
    {
        try {
            $segmentId = $request->request->get('segment_id');
            $limit = $request->request->getInt('limit', 10);

            if (!$segmentId) {
                throw new \Exception('Segment ID is required');
            }

            $contacts = $this->getContactsFromSegment($segmentId);
            $previewContacts = array_slice($contacts, 0, $limit);

            return new JsonResponse([
                'success' => true,
                'contacts' => $previewContacts,
                'total_contacts' => count($contacts),
                'preview_count' => count($previewContacts)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}