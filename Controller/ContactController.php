<?php

namespace MauticPlugin\MauticWarmUpBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Model\ContactModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ContactController
{
    private EntityManagerInterface $em;
    private ContactModel $contactModel;
    private SessionInterface $session;
    private UrlGeneratorInterface $router;
    private RequestStack $requestStack;

    public function __construct(
        EntityManagerInterface $em,
        ContactModel $contactModel,
        SessionInterface $session,
        UrlGeneratorInterface $router,
        RequestStack $requestStack
    ) {
        $this->em = $em;
        $this->contactModel = $contactModel;
        $this->session = $session;
        $this->router = $router;
        $this->requestStack = $requestStack;
    }

    /**
     * List contacts
     */
    public function indexAction(Request $request): Response
    {
        $campaignId = $request->query->get('campaign_id');
        $page = $request->query->get('page', 1);
        $limit = $request->query->get('limit', 50);
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', 'active');

        $filterParams = [
            'start' => ($page - 1) * $limit,
            'limit' => $limit,
            'filter' => $search,
            'status' => $status,
        ];

        if ($campaignId) {
            $filterParams['campaign_id'] = $campaignId;
        }

        $contacts = $this->contactModel->getEntities($filterParams);
        $count = count($contacts);

        // Get campaigns for filter
        $campaigns = $this->contactModel->getCampaignsWithContacts();

        return new JsonResponse([
            'success' => true,
            'data' => $contacts,
            'campaigns' => $campaigns,
            'page' => $page,
            'limit' => $limit,
            'total' => $count,
            'campaignId' => $campaignId,
            'status' => $status,
        ]);
    }

    /**
     * View contact details
     */
    public function viewAction(Request $request, int $id): JsonResponse
    {
        $contact = $this->contactModel->getEntity($id);

        if (!$contact) {
            return new JsonResponse(['success' => false, 'message' => 'Contact not found'], 404);
        }

        // Get contact history
        $history = $this->contactModel->getContactHistory($contact);

        // Get contact statistics
        $stats = $this->contactModel->getContactStats($contact);

        return new JsonResponse([
            'success' => true,
            'contact' => [
                'id' => $contact->getId(),
                'email' => $contact->getEmailAddress(),
                'firstName' => $contact->getFirstName(),
                'lastName' => $contact->getLastName(),
                'fullName' => $contact->getFullName(),
                'campaign' => $contact->getCampaign() ? [
                    'id' => $contact->getCampaign()->getId(),
                    'name' => $contact->getCampaign()->getCampaignName(),
                ] : null,
                'sequenceDay' => $contact->getSequenceDay(),
                'daysBetweenEmails' => $contact->getDaysBetweenEmails(),
                'lastSent' => $contact->getLastSent() ? $contact->getLastSent()->format('Y-m-d H:i:s') : null,
                'nextSendDate' => $contact->getNextSendDate() ? $contact->getNextSendDate()->format('Y-m-d H:i:s') : null,
                'sentCount' => $contact->getSentCount(),
                'isActive' => $contact->isActive(),
                'createdAt' => $contact->getCreatedAt()->format('Y-m-d H:i:s'),
            ],
            'history' => $history,
            'stats' => $stats,
        ]);
    }

    /**
     * Unsubscribe contact
     */
    public function unsubscribeAction(Request $request, string $token): Response
    {
        $contact = $this->contactModel->getContactByToken($token);

        if (!$contact) {
            return new Response('Invalid unsubscribe link', 404);
        }

        try {
            $this->contactModel->unsubscribeContact($contact);

            return new Response('You have been successfully unsubscribed from future emails.');
        } catch (\Exception $e) {
            return new Response('Error processing unsubscribe request: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get contact engagement metrics
     */
    public function engagementAction(Request $request, int $id): JsonResponse
    {
        $contact = $this->contactModel->getEntity($id);

        if (!$contact) {
            return new JsonResponse(['success' => false, 'message' => 'Contact not found'], 404);
        }

        $engagement = $this->contactModel->getContactEngagement($id);

        return new JsonResponse([
            'success' => true,
            'engagement' => $engagement,
        ]);
    }

    /**
     * Batch actions
     */
    public function batchAction(Request $request): JsonResponse
    {
        $action = $request->request->get('action');
        $ids = $request->request->get('ids', []);

        if (empty($ids) || empty($action)) {
            return new JsonResponse(['success' => false, 'error' => 'No items selected or invalid action']);
        }

        try {
            $processed = 0;

            switch ($action) {
                case 'delete':
                    $processed = $this->contactModel->batchDelete($ids);
                    $message = 'Deleted ' . $processed . ' contacts';
                    break;

                case 'activate':
                    $processed = $this->contactModel->batchActivate($ids);
                    $message = 'Activated ' . $processed . ' contacts';
                    break;

                case 'deactivate':
                    $processed = $this->contactModel->batchDeactivate($ids);
                    $message = 'Deactivated ' . $processed . ' contacts';
                    break;

                case 'reschedule':
                    $processed = $this->contactModel->batchReschedule($ids);
                    $message = 'Rescheduled ' . $processed . ' contacts';
                    break;

                default:
                    return new JsonResponse(['success' => false, 'error' => 'Invalid batch action']);
            }

            $this->session->getFlashBag()->add('success', $message);

            return new JsonResponse([
                'success' => true,
                'message' => $message,
                'processed' => $processed,
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => 'Error processing batch action: ' . $e->getMessage()]);
        }
    }

    /**
     * Export contacts
     */
    public function exportAction(Request $request): Response
    {
        $campaignId = $request->query->get('campaign_id');
        $format = $request->query->get('format', 'csv');

        $filterParams = [
            'campaign_id' => $campaignId,
            'status' => $request->query->get('status', 'active'),
        ];

        $data = $this->contactModel->exportContacts($filterParams, $format);

        $filename = 'warmup_contacts_' . date('Y-m-d') . '.' . $format;

        $response = new Response($data);
        $response->headers->set('Content-Type', $format === 'csv' ? 'text/csv' : 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * Get duplicate emails
     */
    public function duplicatesAction(Request $request): JsonResponse
    {
        $campaignId = $request->query->get('campaign_id');

        $duplicates = $this->contactModel->getRepository()->getDuplicateEmails($campaignId);

        return new JsonResponse([
            'success' => true,
            'duplicates' => $duplicates,
            'count' => count($duplicates),
        ]);
    }

    /**
     * Get contacts by domain
     */
    public function byDomainAction(Request $request): JsonResponse
    {
        $domain = $request->query->get('domain');

        if (!$domain) {
            return new JsonResponse(['success' => false, 'error' => 'Domain parameter is required']);
        }

        $contacts = $this->contactModel->getRepository()->getContactsByDomain($domain);

        $result = [];
        foreach ($contacts as $contact) {
            $result[] = [
                'id' => $contact->getId(),
                'email' => $contact->getEmailAddress(),
                'firstName' => $contact->getFirstName(),
                'lastName' => $contact->getLastName(),
                'campaign' => $contact->getCampaign() ? $contact->getCampaign()->getCampaignName() : '',
                'isActive' => $contact->isActive(),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'contacts' => $result,
            'count' => count($result),
            'domain' => $domain,
        ]);
    }

    /**
     * Get inactive contacts
     */
    public function inactiveAction(Request $request): JsonResponse
    {
        $days = $request->query->get('days', 30);
        $limit = $request->query->get('limit', 100);

        $sinceDate = new \DateTime('-' . $days . ' days');

        $contacts = $this->contactModel->getRepository()->getInactiveContacts($sinceDate, $limit);

        $result = [];
        foreach ($contacts as $contact) {
            $result[] = [
                'id' => $contact->getId(),
                'email' => $contact->getEmailAddress(),
                'lastSent' => $contact->getLastSent() ? $contact->getLastSent()->format('Y-m-d H:i:s') : 'Never',
                'sentCount' => $contact->getSentCount(),
                'campaign' => $contact->getCampaign() ? $contact->getCampaign()->getCampaignName() : '',
            ];
        }

        return new JsonResponse([
            'success' => true,
            'contacts' => $result,
            'count' => count($result),
            'sinceDate' => $sinceDate->format('Y-m-d'),
        ]);
    }
}