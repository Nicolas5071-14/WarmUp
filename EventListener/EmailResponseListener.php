// EventListener/EmailResponseListener.php
namespace MauticPlugin\MauticWarmUpBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

class EmailResponseListener implements EventSubscriberInterface
{
    private EntityManagerInterface $em;
    
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }
    
    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => 'onMessageSent',
        ];
    }
    
    public function onMessageSent(MessageEvent $event): void
    {
        $message = $event->getMessage();
        
        if ($message instanceof Email) {
            $headers = $message->getHeaders();
            
            // Check if this is a warmup email
            if ($headers->has('X-Warmup-Contact-Id')) {
                $contactId = $headers->get('X-Warmup-Contact-Id')->getBody();
                $messageId = $headers->get('Message-ID')->getBody();
                
                // Store message ID for tracking replies
                $this->storeMessageIdForTracking($contactId, $messageId);
            }
        }
    }
    
    private function storeMessageIdForTracking(string $contactId, string $messageId): void
    {
        // Store in database for reply tracking
        // You'll need to implement this based on your email server setup
    }
}
