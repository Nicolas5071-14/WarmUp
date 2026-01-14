<?php

namespace MauticPlugin\MauticWarmUpBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Template;
use MauticPlugin\MauticWarmUpBundle\Form\Type\TemplateType;
use MauticPlugin\MauticWarmUpBundle\Model\TemplateModel;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class TemplateController
{
    private EntityManagerInterface $em;
    private TemplateModel $templateModel;
    private FormFactoryInterface $formFactory;
    private SessionInterface $session;
    private UrlGeneratorInterface $router;
    private RequestStack $requestStack;

    public function __construct(
        EntityManagerInterface $em,
        TemplateModel $templateModel,
        FormFactoryInterface $formFactory,
        SessionInterface $session,
        UrlGeneratorInterface $router,
        RequestStack $requestStack
    ) {
        $this->em = $em;
        $this->templateModel = $templateModel;
        $this->formFactory = $formFactory;
        $this->session = $session;
        $this->router = $router;
        $this->requestStack = $requestStack;
    }

    /**
     * List templates
     */
    public function indexAction(Request $request): Response
    {
        $page = $request->query->get('page', 1);
        $limit = $request->query->get('limit', 25);
        $search = $request->query->get('search', '');
        $type = $request->query->get('type', '');

        $templates = $this->templateModel->getEntities([
            'start' => ($page - 1) * $limit,
            'limit' => $limit,
            'filter' => $search,
            'type' => $type,
        ]);

        return new JsonResponse([
            'success' => true,
            'data' => $templates,
            'page' => $page,
            'limit' => $limit,
            'total' => count($templates),
        ]);
    }

    /**
     * New template
     */
    public function newAction(Request $request): Response
    {
        $template = new Template();

        $form = $this->formFactory->create(TemplateType::class, $template);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->templateModel->saveEntity($template);

                $this->session->getFlashBag()->add('success', 'Template created successfully');

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Template created',
                        'id' => $template->getId(),
                    ]);
                }

                return new RedirectResponse($this->router->generate('warmup_template_index'));
            } catch (\Exception $e) {
                $this->session->getFlashBag()->add('error', 'Error: ' . $e->getMessage());

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => false,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'form' => $form->createView(),
            ]);
        }

        return new Response('Template form HTML would go here');
    }

    /**
     * Edit template
     */
    public function editAction(Request $request, int $id): Response
    {
        $template = $this->templateModel->getEntity($id);

        if (!$template) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'error' => 'Template not found'], 404);
            }
            return new Response('Template not found', 404);
        }

        $form = $this->formFactory->create(TemplateType::class, $template);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->templateModel->saveEntity($template);

                $this->session->getFlashBag()->add('success', 'Template updated successfully');

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Template updated',
                    ]);
                }

                return new RedirectResponse($this->router->generate('warmup_template_index'));
            } catch (\Exception $e) {
                $this->session->getFlashBag()->add('error', 'Error: ' . $e->getMessage());

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => false,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'form' => $form->createView(),
                'template' => $template,
            ]);
        }

        return new Response('Edit template form HTML would go here');
    }

    /**
     * Preview template
     */
    public function previewAction(Request $request, int $id): Response
    {
        $template = $this->templateModel->getEntity($id);

        if (!$template) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'error' => 'Template not found'], 404);
            }
            return new Response('Template not found', 404);
        }

        // Process template variables for preview
        $previewData = [
            'id' => $template->getId(),
            'name' => $template->getTemplateName(),
            'type' => $template->getTemplateType(),
            'subject' => $template->getSubject(),
            'htmlContent' => $template->getHtmlContent(),
            'textContent' => $template->getTextContent(),
            'isActive' => $template->isActive(),
            'createdAt' => $template->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $template->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];

        return new JsonResponse([
            'success' => true,
            'preview' => $previewData,
        ]);
    }

    /**
     * Duplicate template
     */
    public function duplicateAction(Request $request, int $id): JsonResponse
    {
        $original = $this->templateModel->getEntity($id);

        if (!$original) {
            return new JsonResponse(['success' => false, 'error' => 'Template not found'], 404);
        }

        try {
            $duplicate = clone $original;
            $duplicate->setTemplateName($original->getTemplateName() . ' (Copy)');
            $duplicate->setCreatedAt(new \DateTime());
            $duplicate->setUpdatedAt(new \DateTime());

            $this->templateModel->saveEntity($duplicate);

            $this->session->getFlashBag()->add('success', 'Template duplicated successfully');

            return new JsonResponse([
                'success' => true,
                'message' => 'Template duplicated',
                'id' => $duplicate->getId(),
                'name' => $duplicate->getTemplateName(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error duplicating template: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Toggle template active status
     */
    public function toggleActiveAction(Request $request, int $id): JsonResponse
    {
        $template = $this->templateModel->getEntity($id);

        if (!$template) {
            return new JsonResponse(['success' => false, 'message' => 'Template not found'], 404);
        }

        try {
            $newStatus = !$template->isActive();
            $template->setIsActive($newStatus);
            $this->templateModel->saveEntity($template);

            return new JsonResponse([
                'success' => true,
                'message' => $newStatus ? 'Template activated' : 'Template deactivated',
                'isActive' => $newStatus,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating template: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get template variables
     */
    public function variablesAction(Request $request): JsonResponse
    {
        $variables = $this->templateModel->getTemplateVariables();

        return new JsonResponse([
            'success' => true,
            'variables' => $variables,
        ]);
    }

    /**
     * Validate template
     */
    public function validateAction(Request $request, int $id): JsonResponse
    {
        $template = $this->templateModel->getEntity($id);

        if (!$template) {
            return new JsonResponse(['success' => false, 'error' => 'Template not found'], 404);
        }

        $errors = $this->templateModel->validateTemplate($template);

        return new JsonResponse([
            'success' => empty($errors),
            'errors' => $errors,
            'isValid' => empty($errors),
        ]);
    }

    /**
     * Get active templates
     */
    public function activeAction(Request $request): JsonResponse
    {
        $type = $request->query->get('type', 'email');

        $templates = [];
        if ($type === 'email') {
            $templates = $this->templateModel->getEmailTemplates();
        } else {
            $templates = $this->templateModel->getActiveTemplates();
        }

        $result = [];
        foreach ($templates as $template) {
            $result[] = [
                'id' => $template->getId(),
                'name' => $template->getTemplateName(),
                'type' => $template->getTemplateType(),
                'subject' => $template->getSubject(),
                'isActive' => $template->isActive(),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'templates' => $result,
            'count' => count($result),
        ]);
    }

    /**
     * Liste des templates (AJAX)
     */
    public function listAction(Request $request): JsonResponse
    {
        try {
            $templates = $this->em->getRepository(Template::class)
                ->findBy(['isActive' => true], ['name' => 'ASC']);

            $data = [];
            foreach ($templates as $template) {
                $data[] = [
                    'id' => $template->getId(),
                    'name' => $template->getName(),
                    'description' => $template->getDescription(),
                    'subject' => $template->getSubject(),
                    'content' => $template->getContent(),
                    'created_at' => $template->getCreatedAt() ? $template->getCreatedAt()->format('Y-m-d H:i') : null,
                ];
            }

            return new JsonResponse([
                'success' => true,
                'templates' => $data,
            ]);

        } catch (\Exception $e) {
            error_log('Error in listAction: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Error loading templates: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Charger un template spécifique (AJAX)
     */
    public function loadAction(Request $request, int $id): JsonResponse
    {
        try {
            $template = $this->em->getRepository(Template::class)->find($id);

            if (!$template) {
                throw new \Exception('Template not found');
            }

            return new JsonResponse([
                'success' => true,
                'template' => [
                    'id' => $template->getId(),
                    'name' => $template->getName(),
                    'subject' => $template->getSubject(),
                    'content' => $template->getContent(),
                ],
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}