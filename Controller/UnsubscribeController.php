<?php
// src/Controller/UnsubscribeController.php

namespace MauticPlugin\MauticWarmUpBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Contact;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UnsubscribeController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route("/warmup/unsubscribe/{token}", name="warmup_unsubscribe", methods={"GET"})
     */
    public function unsubscribeAction(string $token): Response
    {
        $contact = $this->em->getRepository(Contact::class)->findOneBy(['unsubscribeToken' => $token]);

        if (!$contact) {
            return new Response('Invalid unsubscribe link', 404);
        }

        $contact->setIsActive(false);
        $contact->setStatus('unsubscribed');
        $contact->setUnsubscribedAt(new \DateTime());

        $this->em->persist($contact);
        $this->em->flush();

        return new Response(
            '<html>
                <body>
                    <div style="text-align: center; padding: 50px;">
                        <h2>You have been unsubscribed</h2>
                        <p>You will no longer receive emails from this campaign.</p>
                        <p>Thank you.</p>
                    </div>
                </body>
            </html>',
            200,
            ['Content-Type' => 'text/html']
        );
    }

    /**
     * @Route("/api/warmup/unsubscribe", name="warmup_api_unsubscribe", methods={"POST"})
     */
    public function apiUnsubscribeAction(Request $request): JsonResponse
    {
        $email = $request->request->get('email');
        $campaignId = $request->request->get('campaign_id');

        if (!$email) {
            return new JsonResponse(['success' => false, 'message' => 'Email required'], 400);
        }

        $qb = $this->em->createQueryBuilder();
        $qb->select('c')
            ->from(Contact::class, 'c')
            ->where('c.email = :email')
            ->setParameter('email', $email);

        if ($campaignId) {
            $qb->andWhere('c.campaign = :campaignId')
                ->setParameter('campaignId', $campaignId);
        }

        $contacts = $qb->getQuery()->getResult();

        if (empty($contacts)) {
            return new JsonResponse(['success' => false, 'message' => 'Contact not found'], 404);
        }

        foreach ($contacts as $contact) {
            $contact->setIsActive(false);
            $contact->setStatus('unsubscribed');
            $contact->setUnsubscribedAt(new \DateTime());
            $this->em->persist($contact);
        }

        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Successfully unsubscribed',
            'contacts_updated' => count($contacts)
        ]);
    }
}
