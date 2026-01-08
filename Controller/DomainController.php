<?php

namespace MauticPlugin\MauticWarmUpBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Domain;
use MauticPlugin\MauticWarmUpBundle\Form\Type\DomainType;
use MauticPlugin\MauticWarmUpBundle\Model\DomainModel;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class DomainController
{
    private EntityManagerInterface $em;
    private DomainModel $domainModel;
    private FormFactoryInterface $formFactory;
    private UrlGeneratorInterface $router;
    private RequestStack $requestStack;
    private Environment $twig;

    public function __construct(
        EntityManagerInterface $em,
        DomainModel $domainModel,
        FormFactoryInterface $formFactory,
        UrlGeneratorInterface $router,
        RequestStack $requestStack,
        Environment $twig
    ) {
        $this->em = $em;
        $this->domainModel = $domainModel;
        $this->formFactory = $formFactory;
        $this->router = $router;
        $this->requestStack = $requestStack;
        $this->twig = $twig;
    }

    /**
     * Helper method to get session from request stack
     */


    private function getSession(): SessionInterface
    {
        return $this->requestStack->getSession();
    }

    /**
     * List domains
     */
    public function indexAction(Request $request): Response
    {
        return new Response(
            $this->twig->render('@MauticWarmUp/Domain/index.html.twig', [
                'tmpl' => 'index',
            ])
        );
    }

    /**
     * AJAX endpoint to list domains with pagination and search
     */
    public function ajaxListAction(Request $request): JsonResponse
    {
        error_log('🔥 ajaxListAction CALLED');

        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(100, (int) $request->query->get('limit', 25)));
            $search = trim((string) $request->query->get('search', ''));

            error_log('📥 Params: ' . json_encode([
                'page' => $page,
                'limit' => $limit,
                'search' => $search
            ]));

            $start = ($page - 1) * $limit;

            $params = [
                'start' => $start,
                'limit' => $limit,
            ];

            if (!empty($search)) {
                $params['filter'] = $search;
            }

            error_log('📦 Calling DomainModel::getEntities with: ' . json_encode($params));

            $domains = $this->domainModel->getEntities($params);

            error_log('📊 Domains RAW result count: ' . count($domains));
            error_log('📊 Domains RAW: ' . print_r($domains, true));

            $totalCount = $this->domainModel->getTotalCount();
            error_log('📊 Total count: ' . $totalCount);

            $data = [];

            foreach ($domains as $domain) {
                $data[] = [
                    'id' => $domain['id'],
                    'domainName' => $domain['domainName'],
                    'emailPrefix' => $domain['emailPrefix'],
                    'dailyLimit' => $domain['dailyLimit'],
                    'totalSentToday' => $domain['totalSentToday'],
                    'remainingSendsToday' => $domain['remainingSendsToday'],
                    'isVerified' => $domain['isVerified'],
                    'isActive' => $domain['isActive'],
                    'smtpHost' => $domain['smtpHost'] ?? '',
                    'warmupPhase' => $domain['currentPhaseDay'] ?? 1,
                    'createdAt' => $domain['createdAt'] instanceof \DateTime
                        ? $domain['createdAt']->format('Y-m-d H:i:s')
                        : null,
                ];
            }

            error_log('✅ JSON DATA FINAL: ' . json_encode($data));

            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'totalPages' => (int) ceil($totalCount / $limit),
                ]
            ]);

        } catch (\Throwable $e) {
            error_log('❌ ajaxListAction ERROR: ' . $e->getMessage());
            error_log($e->getTraceAsString());

            return new JsonResponse([
                'success' => false,
                'message' => 'Error loading domains',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * New domain
     */


    public function newAction(Request $request): Response
    {
        $domain = new Domain();
        $actionUrl = $this->router->generate('warmup_domain_new');

        $form = $this->formFactory->create(DomainType::class, $domain, [
            'action' => $actionUrl,
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->domainModel->checkIfDomainExists($domain->getDomainName())) {
                // Utiliser 'error' au lieu de 'success'
                $this->getSession()->getFlashBag()->add(
                    'error',
                    'Domain already exists: ' . $domain->getDomainName()
                );

                return new Response(
                    $this->twig->render('@MauticWarmUp/Domain/form.html.twig', [
                        'form' => $form->createView(),
                        'domain' => $domain,
                        'actionUrl' => $actionUrl,
                    ])
                );
            }

            try {
                $this->domainModel->saveEntity($domain);

                // Utiliser 'notice' pour les succès
                $this->getSession()->getFlashBag()->add(
                    'notice',
                    'Domain created successfully'
                );

                return new RedirectResponse(
                    $this->router->generate('warmup_domain_index')
                );

            } catch (\Exception $e) {
                $this->getSession()->getFlashBag()->add(
                    'error',
                    $e->getMessage()
                );
            }
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form' => $form->createView(),
                    'domain' => $domain,
                    'actionUrl' => $actionUrl,
                ],
                'contentTemplate' => '@MauticWarmUp/Domain/form.html.twig',
            ],
            $request
        );
    }


    private function delegateView(array $params, ?Request $request = null): Response
    {
        $viewParameters = $params['viewParameters'] ?? [];
        $contentTemplate = $params['contentTemplate'] ?? '';

        if (empty($contentTemplate)) {
            throw new \InvalidArgumentException('contentTemplate is required');
        }

        return new Response(
            $this->twig->render($contentTemplate, array_merge(
                $viewParameters,
                [
                    // Mautic layout system
                    'tmpl' => $request->isXmlHttpRequest()
                        ? $request->get('tmpl', 'index')
                        : 'index',

                    // Permissions (safe fallback)
                    'permissions' => [
                        'warmup:domains:view' => true,
                        'warmup:domains:create' => true,
                        'warmup:domains:edit' => true,
                        'warmup:domains:delete' => true,
                    ],
                ]
            ))
        );
    }

    /**
     * Edit domain
     */
    public function editAction(Request $request, int $id): Response
    {
        $domain = $this->domainModel->getEntity($id);

        if (!$domain) {
            $this->getSession()->getFlashBag()->add('error', 'Domain not found');
            return new RedirectResponse($this->router->generate('warmup_domain_index'));
        }

        $actionUrl = $this->router->generate('warmup_domain_edit', ['id' => $id]);
        $form = $this->formFactory->create(DomainType::class, $domain, [
            'action' => $actionUrl,
            'method' => 'POST',
            'csrf_protection' => false, // Désactivez CSRF temporairement pour tester
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->domainModel->saveEntity($domain);

                $this->getSession()->getFlashBag()->add('success', 'Domain updated successfully');

                return new RedirectResponse($this->router->generate('warmup_domain_index'));
            } catch (\Exception $e) {
                $this->getSession()->getFlashBag()->add('error', 'Error: ' . $e->getMessage());
            }
        }

        return new Response(
            $this->twig->render('@MauticWarmUp/Domain/form.html.twig', [
                'form' => $form->createView(),
                'domain' => $domain,
                'actionUrl' => $actionUrl,
            ])
        );
    }

    /**
     * Delete domain
     */
    public function deleteAction(Request $request, int $id): Response
    {
        $domain = $this->domainModel->getEntity($id);

        if (!$domain) {
            return new JsonResponse(['success' => false, 'error' => 'Domain not found'], 404);
        }

        if ($request->isMethod('POST')) {
            try {
                $this->domainModel->deleteEntity($domain);

                $this->getSession()->getFlashBag()->add('success', 'Domain deleted successfully');
                return new JsonResponse(['success' => true, 'message' => 'Domain deleted']);
            } catch (\Exception $e) {
                return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }

        return new JsonResponse([
            'success' => true,
            'domain' => [
                'id' => $domain->getId(),
                'domainName' => $domain->getDomainName(),
            ],
            'confirmation' => 'Are you sure you want to delete this domain?',
        ]);
    }
    /**
     * Verify domain SMTP
     */
    public function verifyAction(Request $request, int $id): JsonResponse
    {
        $domain = $this->domainModel->getEntity($id);

        if (!$domain) {
            return new JsonResponse(['success' => false, 'message' => 'Domain not found'], 404);
        }

        try {
            $verified = $this->domainModel->verifySmtp($domain);

            if ($verified) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'SMTP verification successful',
                    'isVerified' => true,
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'SMTP verification failed. Please check your credentials.',
                    'isVerified' => false,
                ]);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error during verification: ' . $e->getMessage(),
                'isVerified' => false,
            ]);
        }
    }

    /**
     * Toggle domain active status
     */
    public function toggleActiveAction(Request $request, int $id): JsonResponse
    {
        $domain = $this->domainModel->getEntity($id);

        if (!$domain) {
            return new JsonResponse(['success' => false, 'message' => 'Domain not found'], 404);
        }

        try {
            $newStatus = !$domain->isActive();
            $domain->setIsActive($newStatus);
            $this->domainModel->saveEntity($domain);

            return new JsonResponse([
                'success' => true,
                'message' => $newStatus ? 'Domain activated' : 'Domain deactivated',
                'isActive' => $newStatus,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating domain: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get domain usage statistics
     */
    public function statsAction(Request $request, int $id): JsonResponse
    {
        $domain = $this->domainModel->getEntity($id);

        if (!$domain) {
            return new JsonResponse(['success' => false, 'message' => 'Domain not found'], 404);
        }

        $stats = $this->domainModel->getStatistics($domain);

        return new JsonResponse([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Start warm-up for domain
     */
    public function startWarmupAction(Request $request, int $id): JsonResponse
    {
        $domain = $this->domainModel->getEntity($id);

        if (!$domain) {
            return new JsonResponse(['success' => false, 'message' => 'Domain not found'], 404);
        }

        try {
            $this->domainModel->startWarmUp($domain);

            return new JsonResponse([
                'success' => true,
                'message' => 'Warm-up started successfully',
                'isActive' => true,
                'warmupStartDate' => $domain->getWarmupStartDate() ? $domain->getWarmupStartDate()->format('Y-m-d H:i:s') : null,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error starting warm-up: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Pause warm-up for domain
     */
    public function pauseWarmupAction(Request $request, int $id): JsonResponse
    {
        $domain = $this->domainModel->getEntity($id);

        if (!$domain) {
            return new JsonResponse(['success' => false, 'message' => 'Domain not found'], 404);
        }

        try {
            $this->domainModel->pauseWarmUp($domain);

            return new JsonResponse([
                'success' => true,
                'message' => 'Warm-up paused',
                'isActive' => false,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error pausing warm-up: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Reset domain daily counter
     */
    public function resetDailyCounterAction(Request $request, int $id): JsonResponse
    {
        $domain = $this->domainModel->getEntity($id);

        if (!$domain) {
            return new JsonResponse(['success' => false, 'message' => 'Domain not found'], 404);
        }

        try {
            $domain->setTotalSentToday(0);
            $domain->setUpdatedAt(new \DateTime());
            $this->domainModel->saveEntity($domain);

            return new JsonResponse([
                'success' => true,
                'message' => 'Daily counter reset',
                'totalSentToday' => 0,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error resetting counter: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get domains with available capacity
     */
    public function availableDomainsAction(Request $request): JsonResponse
    {
        $requiredCount = $request->query->get('required', 1);

        try {
            $domains = $this->domainModel->getDomainsWithCapacity((int) $requiredCount);

            $result = [];
            foreach ($domains as $domain) {
                $result[] = [
                    'id' => $domain->getId(),
                    'domainName' => $domain->getDomainName(),
                    'emailPrefix' => $domain->getEmailPrefix(),
                    'dailyLimit' => $domain->getDailyLimit(),
                    'totalSentToday' => $domain->getTotalSentToday(),
                    'remaining' => $domain->getRemainingSendsToday(),
                    'isActive' => $domain->isActive(),
                    'isVerified' => $domain->isVerified(),
                ];
            }

            return new JsonResponse([
                'success' => true,
                'domains' => $result,
                'count' => count($result),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error getting domains: ' . $e->getMessage(),
            ]);
        }
    }
}