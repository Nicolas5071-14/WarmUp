<?php

namespace MauticPlugin\MauticWarmUpBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\MauticWarmUpBundle\Entity\Domain;
use MauticPlugin\MauticWarmUpBundle\Form\Type\DomainType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class DomainController extends CommonController
{
    /**
     * Returns the services this controller needs
     */

    /**
     * List domains - Compatible avec navigation AJAX Mautic
     */
    public function indexAction(Request $request): Response
    {
        return $this->delegateView([
            'viewParameters' => [
                'tmpl' => $request->get('tmpl', 'index'),
            ],
            'contentTemplate' => '@MauticWarmUp/Domain/index.html.twig',
            'passthroughVars' => [
                'activeLink' => '#warmup_domain_index',
                'mauticContent' => 'warmupDomain',
                'route' => $this->generateUrl('warmup_domain_index'),
            ]
        ]);
    }

    /**
     * AJAX endpoint to list domains with pagination and search
     */
    public function ajaxListAction(Request $request): JsonResponse
    {
        error_log('🔥 ajaxListAction CALLED');

        try {
            $domainModel = $this->get('mautic_warmup.model.domain');

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

            $domains = $domainModel->getEntities($params);
            $totalCount = $domainModel->getTotalCount();

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
     * New domain - Compatible AJAX
     */
    public function newAction(Request $request): Response
    {
        $domainModel = $this->get('mautic_warmup.model.domain');
        $domain = new Domain();

        $actionUrl = $this->generateUrl('warmup_domain_new');

        $form = $this->createForm(DomainType::class, $domain, [
            'action' => $actionUrl,
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        error_log('=== NEW DOMAIN ACTION ===');
        error_log('Form submitted: ' . ($form->isSubmitted() ? 'Yes' : 'No'));
        error_log('Form valid: ' . ($form->isValid() ? 'Yes' : 'No'));

        if ($form->isSubmitted() && $form->isValid()) {
            if ($domainModel->checkIfDomainExists($domain->getDomainName())) {
                $this->addFlashMessage(
                    'Domain already exists: ' . $domain->getDomainName(),
                    [],
                    'error'
                );

                return $this->delegateView([
                    'viewParameters' => [
                        'form' => $form->createView(),
                        'domain' => $domain,
                        'actionUrl' => $actionUrl,
                    ],
                    'contentTemplate' => '@MauticWarmUp/Domain/form.html.twig',
                    'passthroughVars' => [
                        'activeLink' => '#warmup_domain_new',
                        'mauticContent' => 'warmupDomain',
                        'route' => $this->generateUrl('warmup_domain_new'),
                    ]
                ]);
            }

            try {
                $domainModel->saveEntity($domain);

                $this->addFlashMessage('Domain created successfully');

                return $this->postActionRedirect([
                    'returnUrl' => $this->generateUrl('warmup_domain_index'),
                    'contentTemplate' => '@MauticWarmUp/Domain/index.html.twig',
                    'passthroughVars' => [
                        'activeLink' => '#warmup_domain_index',
                        'mauticContent' => 'warmupDomain',
                    ]
                ]);

            } catch (\Exception $e) {
                $this->addFlashMessage($e->getMessage(), [], 'error');
                error_log('Save error: ' . $e->getMessage());
            }
        }

        return $this->delegateView([
            'viewParameters' => [
                'form' => $form->createView(),
                'domain' => $domain,
                'actionUrl' => $actionUrl,
            ],
            'contentTemplate' => '@MauticWarmUp/Domain/form.html.twig',
            'passthroughVars' => [
                'activeLink' => '#warmup_domain_new',
                'mauticContent' => 'warmupDomain',
                'route' => $this->generateUrl('warmup_domain_new'),
            ]
        ]);
    }

    /**
     * Edit domain - Compatible AJAX
     */
    public function editAction(Request $request, int $id): Response
    {
        $domainModel = $this->get('mautic_warmup.model.domain');
        $domain = $domainModel->getEntity($id);

        if (!$domain) {
            $this->addFlashMessage('Domain not found', [], 'error');

            return $this->postActionRedirect([
                'returnUrl' => $this->generateUrl('warmup_domain_index'),
                'contentTemplate' => '@MauticWarmUp/Domain/index.html.twig',
                'passthroughVars' => [
                    'activeLink' => '#warmup_domain_index',
                    'mauticContent' => 'warmupDomain',
                ]
            ]);
        }

        $actionUrl = $this->generateUrl('warmup_domain_edit', ['id' => $id]);

        $form = $this->createForm(DomainType::class, $domain, [
            'action' => $actionUrl,
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $domainModel->saveEntity($domain);

                $this->addFlashMessage('Domain updated successfully');

                return $this->postActionRedirect([
                    'returnUrl' => $this->generateUrl('warmup_domain_index'),
                    'contentTemplate' => '@MauticWarmUp/Domain/index.html.twig',
                    'passthroughVars' => [
                        'activeLink' => '#warmup_domain_index',
                        'mauticContent' => 'warmupDomain',
                    ]
                ]);

            } catch (\Exception $e) {
                $this->addFlashMessage('Error: ' . $e->getMessage(), [], 'error');
            }
        }

        return $this->delegateView([
            'viewParameters' => [
                'form' => $form->createView(),
                'domain' => $domain,
                'actionUrl' => $actionUrl,
            ],
            'contentTemplate' => '@MauticWarmUp/Domain/form.html.twig',
            'passthroughVars' => [
                'activeLink' => '#warmup_domain_edit',
                'mauticContent' => 'warmupDomain',
                'route' => $this->generateUrl('warmup_domain_edit', ['id' => $id]),
            ]
        ]);
    }

    /**
     * Delete domain
     */
    public function deleteAction(Request $request, int $id): JsonResponse
    {
        $domainModel = $this->get('mautic_warmup.model.domain');
        $domain = $domainModel->getEntity($id);

        if (!$domain) {
            return new JsonResponse(['success' => false, 'error' => 'Domain not found'], 404);
        }

        if ($request->isMethod('POST')) {
            try {
                $domainModel->deleteEntity($domain);

                $this->addFlashMessage('Domain deleted successfully');

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
        $domainModel = $this->get('mautic_warmup.model.domain');
        $domain = $domainModel->getEntity($id);

        if (!$domain) {
            return new JsonResponse(['success' => false, 'message' => 'Domain not found'], 404);
        }

        try {
            $verified = $domainModel->verifySmtp($domain);

            return new JsonResponse([
                'success' => $verified,
                'message' => $verified ? 'SMTP verification successful' : 'SMTP verification failed',
                'isVerified' => $verified,
            ]);
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
        $domainModel = $this->get('mautic_warmup.model.domain');
        $domain = $domainModel->getEntity($id);

        if (!$domain) {
            return new JsonResponse(['success' => false, 'message' => 'Domain not found'], 404);
        }

        try {
            $newStatus = !$domain->isActive();
            $domain->setIsActive($newStatus);
            $domainModel->saveEntity($domain);

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
        $domainModel = $this->get('mautic_warmup.model.domain');
        $domain = $domainModel->getEntity($id);

        if (!$domain) {
            return new JsonResponse(['success' => false, 'message' => 'Domain not found'], 404);
        }

        $stats = $domainModel->getStatistics($domain);

        return new JsonResponse([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Get available domains
     */
    public function availableDomainsAction(Request $request): JsonResponse
    {
        $domainModel = $this->get('mautic_warmup.model.domain');
        $requiredCount = $request->query->get('required', 1);

        try {
            $domains = $domainModel->getDomainsWithCapacity((int) $requiredCount);

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