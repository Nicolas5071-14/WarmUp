<?php

namespace MauticPlugin\MauticWarmUpBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticWarmUpBundle\Entity\Contact;
use MauticPlugin\MauticWarmUpBundle\Entity\EmailTracking;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TrackingController extends AbstractController
{
    private EntityManagerInterface $em;
    
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }
    
    /**
     * @Route("/track/open/{token}", name="warmup_track_open", methods={"GET"})
     */
    public function trackOpen(Request $request, string $token): Response
    {
        try {
            $data = base64_decode($token);
            list($contactId, $campaignId) = explode('|', $data);
            
            $contact = $this->em->getRepository(Contact::class)->find($contactId);
            if ($contact) {
                $contact->setOpened(true);
                $contact->setOpenedAt(new \DateTime());
                $contact->setEmailsOpened($contact->getEmailsOpened() + 1);
                
                // Find tracking record
                $tracking = $this->em->getRepository(EmailTracking::class)
                    ->findOneBy(['contact' => $contact, 'campaign' => $campaignId], ['sentAt' => 'DESC']);
                
                if ($tracking) {
                    $tracking->setOpenedAt(new \DateTime());
                    $tracking->setIsOpened(true);
                }
                
                $this->em->flush();
            }
            
        } catch (\Exception $e) {
            // Log error but don't break
            error_log('Tracking open error: ' . $e->getMessage());
        }
        
        // Return 1x1 transparent pixel
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        return new Response($pixel, 200, ['Content-Type' => 'image/gif']);
    }
    
    /**
     * @Route("/track/click/{token}", name="warmup_track_click", methods={"GET"})
     */
    public function trackClick(Request $request, string $token): Response
    {
        try {
            $data = base64_decode($token);
            list($contactId, $campaignId, $originalUrl) = explode('|', $data);
            $originalUrl = urldecode($originalUrl);
            
            $contact = $this->em->getRepository(Contact::class)->find($contactId);
            if ($contact) {
                $contact->setClicked(true);
                $contact->setClickedAt(new \DateTime());
                $contact->setEmailsClicked($contact->getEmailsClicked() + 1);
                
                // Find tracking record
                $tracking = $this->em->getRepository(EmailTracking::class)
                    ->findOneBy(['contact' => $contact, 'campaign' => $campaignId], ['sentAt' => 'DESC']);
                
                if ($tracking) {
                    $tracking->setClickedAt(new \DateTime());
                    $tracking->setIsClicked(true);
                    $tracking->setClickedUrl($originalUrl);
                }
                
                $this->em->flush();
            }
            
            // Redirect to original URL
            return $this->redirect($originalUrl);
            
        } catch (\Exception $e) {
            error_log('Tracking click error: ' . $e->getMessage());
            return $this->redirect('/');
        }
    }
}
