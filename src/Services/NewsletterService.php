<?php
namespace App\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use App\Services\MailService;

class NewsletterService
{
    protected $em;
    protected $elementRepo;
    protected $mailService;
    protected $config;

   /**
   * Constructor
   */
   public function __construct(DocumentManager $documentManager, MailService $mailService)
   {
      $this->em = $documentManager;
      $this->mailService = $mailService;
      $this->elementRepo = $this->em->getRepository('BiopenGeoDirectoryBundle:Element');
   }

   public function sendTo($user)
   {
      $elements = $this->elementRepo->findWithinCenterFromDate(
                                        $user->getGeo()->getLatitude(),
                                        $user->getGeo()->getLongitude(),
                                        $user->getNewsletterRange(),
                                        $user->getLastNewsletterSentAt());

      $elementCount = $elements->count();
      if ($elementCount > 0)
      {
        $this->mailService->sendAutomatedMail('newsletter', $user, null, $elements);
      }

      $user->setLastNewsletterSentAt(new \DateTime);
      $user->updateNextNewsletterDate();

      return $elementCount;
   }
}