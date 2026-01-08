<?php

namespace MauticPlugin\MauticWarmUpBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Template;

class TemplateModel
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Get entity by ID
     */
    public function getEntity(int $id = null): ?Template
    {
        if ($id === null) {
            return new Template();
        }

        return $this->em->getRepository(Template::class)->find($id);
    }

    /**
     * Save template entity
     */
    public function saveEntity(Template $template): void
    {
        $isNew = $template->getId() === null;

        // Set updated timestamp
        $template->setUpdatedAt(new \DateTime());

        // If new, set created timestamp
        if ($isNew) {
            $template->setCreatedAt(new \DateTime());
        }

        $this->em->persist($template);
        $this->em->flush();
    }

    /**
     * Delete template entity
     */
    public function deleteEntity(Template $template): void
    {
        $this->em->remove($template);
        $this->em->flush();
    }

    /**
     * Get active templates
     */
    public function getActiveTemplates(): array
    {
        return $this->em->getRepository(Template::class)->findBy(
            ['isActive' => true],
            ['templateName' => 'ASC']
        );
    }

    /**
     * Get email templates only
     */
    public function getEmailTemplates(): array
    {
        return $this->em->getRepository(Template::class)->findBy(
            ['templateType' => 'email', 'isActive' => true],
            ['templateName' => 'ASC']
        );
    }

    /**
     * Get template variables
     */
    public function getTemplateVariables(): array
    {
        return [
            'contact' => [
                '{{contact.email}}' => 'Contact email address',
                '{{contact.first_name}}' => 'Contact first name',
                '{{contact.last_name}}' => 'Contact last name',
                '{{contact.full_name}}' => 'Contact full name',
            ],
            'campaign' => [
                '{{campaign.name}}' => 'Campaign name',
                '{{campaign.description}}' => 'Campaign description',
            ],
            'date' => [
                '{{date}}' => 'Current date (Y-m-d)',
                '{{time}}' => 'Current time (H:i:s)',
                '{{datetime}}' => 'Current date and time',
            ],
            'unsubscribe' => [
                '{{unsubscribe_link}}' => 'Unsubscribe link',
                '{{preferences_link}}' => 'Preferences link',
            ],
        ];
    }

    /**
     * Process template with variables
     */
    public function processTemplate(string $content, array $variables = []): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace($key, $value, $content);
        }

        return $content;
    }

    /**
     * Validate template
     */
    public function validateTemplate(Template $template): array
    {
        $errors = [];

        if (empty($template->getTemplateName())) {
            $errors[] = 'Template name is required';
        }

        if (empty($template->getSubject())) {
            $errors[] = 'Email subject is required';
        }

        if (empty($template->getHtmlContent())) {
            $errors[] = 'Email content is required';
        }

        // Check for required unsubscribe link
        if (strpos($template->getHtmlContent(), '{{unsubscribe_link}}') === false) {
            $errors[] = 'Email template must include {{unsubscribe_link}} variable';
        }

        return $errors;
    }

    /**
     * Get entities as array (for forms and API)
     */
    public function getEntities(array $args = []): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('t')
            ->from(Template::class, 't')
            ->orderBy('t.id', 'DESC');

        if (isset($args['start'])) {
            $qb->setFirstResult($args['start']);
        }
        if (isset($args['limit'])) {
            $qb->setMaxResults($args['limit']);
        }
        if (isset($args['filter']) && !empty($args['filter'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('t.templateName', ':filter'),
                $qb->expr()->like('t.subject', ':filter')
            ))
                ->setParameter('filter', '%' . $args['filter'] . '%');
        }
        if (isset($args['type'])) {
            $qb->andWhere('t.templateType = :type')
                ->setParameter('type', $args['type']);
        }
        if (isset($args['isActive'])) {
            $qb->andWhere('t.isActive = :isActive')
                ->setParameter('isActive', $args['isActive']);
        }

        $entities = $qb->getQuery()->getResult();

        $result = [];
        foreach ($entities as $template) {
            $result[] = [
                'id' => $template->getId(),
                'templateName' => $template->getTemplateName(),
                'templateType' => $template->getTemplateType(),
                'subject' => $template->getSubject(),
                'htmlContent' => $template->getHtmlContent(),
                'plainTextContent' => $template->getPlainTextContent(),
                'isActive' => $template->isActive(),
                'createdAt' => $template->getCreatedAt(),
                'updatedAt' => $template->getUpdatedAt(),
            ];
        }

        return $result;
    }

    /**
     * Duplicate template
     */
    public function duplicateTemplate(Template $template): Template
    {
        $newTemplate = new Template();
        $newTemplate->setTemplateName($template->getTemplateName() . ' (Copy)');
        $newTemplate->setTemplateType($template->getTemplateType());
        $newTemplate->setSubject($template->getSubject());
        $newTemplate->setHtmlContent($template->getHtmlContent());
        $newTemplate->setPlainTextContent($template->getPlainTextContent());
        $newTemplate->setIsActive($template->isActive());

        $this->saveEntity($newTemplate);

        return $newTemplate;
    }

    /**
     * Toggle template active status
     */
    public function toggleActive(Template $template): Template
    {
        $template->setIsActive(!$template->isActive());
        $this->saveEntity($template);

        return $template;
    }

    /**
     * Get total count of templates
     */
    public function getTotalCount(): int
    {
        $query = $this->em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Template::class, 't')
            ->getQuery();

        return (int) $query->getSingleScalarResult();
    }

    /**
     * Get active templates count
     */
    public function getActiveCount(): int
    {
        $query = $this->em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Template::class, 't')
            ->where('t.isActive = true')
            ->getQuery();

        return (int) $query->getSingleScalarResult();
    }

    /**
     * Get email templates count
     */
    public function getEmailTemplatesCount(): int
    {
        $query = $this->em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Template::class, 't')
            ->where('t.templateType = :type')
            ->andWhere('t.isActive = true')
            ->setParameter('type', 'email')
            ->getQuery();

        return (int) $query->getSingleScalarResult();
    }
}