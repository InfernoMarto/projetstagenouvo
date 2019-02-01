<?php

/**
 * This file is part of the GoGoCarto project.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) 2016 Sebastian Castro - 90scastro@gmail.com
 * @license    MIT License
 * @Last Modified time: 2018-05-09 10:08:34
 */
 

namespace Biopen\GeoDirectoryBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Security\Core\SecurityContext;
use Biopen\GeoDirectoryBundle\Document\ElementStatus;
use Biopen\GeoDirectoryBundle\Document\UserInteractionContribution;
use Biopen\CoreBundle\Services\MailService;

abstract class ValidationType
{
   const Collaborative = 1;
   const Admin = 2;            
}

/**
* Service used to handle to resolution of pending Elements
**/
class ElementPendingService
{  
   /**
   * Constructor
   */
   public function __construct(DocumentManager $documentManager, SecurityContext $securityContext, MailService $mailService, WebhookService $webhookService)
   {
      $this->em = $documentManager;
      $this->securityContext = $securityContext;
      $this->mailService = $mailService;
      $this->webhookService = $webhookService;
   }

   // When element in added or modified by non admin, we go throw this function
   // It create an appropriate contribution, and set the status to pending
   // We could also send a confirmation mail to the contributor for example
   public function createPending($element, $editMode, $userEmail)
   {
      // in case user edit it's own contribution, the element is still pending, and
      // we want to make it pending again. So we resolve the previous contribution 
      if ($element->isPending() && $element->getCurrContribution()) $element->getCurrContribution()->setStatus(ElementStatus::ModifiedByOwner);
      
      $contribution = new UserInteractionContribution();
      $contribution->updateUserInformation($this->securityContext, $userEmail);
      $contribution->setType($editMode ? 1 : 0);
      $element->addContribution($contribution);

      $element->setStatus($editMode ? ElementStatus::PendingModification : ElementStatus::PendingAdd);  

      // TODO send mail to contributor?    
   }

   // In case of collaborative modification, we actually don't change the elements attributes. 
   // Instead we save the modifications in the modifiedElement attributes.
   // The old element as just his status attribute modified, all the other modifications are saved in modifiedelement attribute
   public function savePendingModification($element)
   {      
      $modifiedElement = clone $element;
      $modifiedElement->setId(null);
      $modifiedElement->setStatus(ElementStatus::ModifiedPendingVersion);

      // making a real refresh, calling refresh and getting again the element from DB (otherwise there were conflicts)
      $element->reset();
      $this->em->refresh($element);
      $id = $element->getId();
      $oldElement = $this->em->getRepository('BiopenGeoDirectoryBundle:Element')->find($id);
      
      $this->em->persist($modifiedElement);
      $oldElement->setModifiedElement($modifiedElement);
      return $oldElement;
   }

   // Action called to relsolve a pending element. This actions in triggered from both admin or collaborative resolve
   public function resolve($element, $isAccepted, $validationType = ValidationType::Admin, $message = null)
   {
      // Call specifics action depending of contribution type and validation or refusal
      if ($element->getStatus() == ElementStatus::PendingAdd) 
      {
         if ($isAccepted) $this->acceptNewElement($element, $message);
         else $this->refuseNewElement($element);

         $this->updateStatusAfterValidationOrRefusal($element, $isAccepted, $validationType);
      }
      else if ($element->getStatus() == ElementStatus::PendingModification) 
      {
         if ($isAccepted) $this->acceptModifiedElement($element, $message);
         else $this->refuseModifiedElement($element);

         // For pending modification, both validation or refusal ends with validation status
         $element->setStatus($validationType == ValidationType::Collaborative ? ElementStatus::CollaborativeValidate : ElementStatus::AdminValidate);
      }     

      $this->resolveContribution($element, $isAccepted, $validationType, $message);      

      $this->sendMailToContributorAfterValidationOrRefusal($element, $isAccepted, $validationType, $message);
   }

   private function acceptNewElement($element, $message) 
   {      
      $this->mailService->sendAutomatedMail('add', $element, $message);

      $this->webhookService->queue('add', $element, $this->securityContext->getToken()->getUser());
   }

   public function refuseNewElement($element) 
   {
   }

   private function acceptModifiedElement($element, $message) 
   {
      $modifiedElement = $element->getModifiedElement();
      if ($modifiedElement)
      {
         // copying following attributes
         $attributes = ['name', 'geo', 'address', 'commitment', 'description', 'descriptionMore', 'telephone', 'email', 'website', 'optionValues', 'openHours', 'openHoursMoreInfos'];
         foreach ($attributes as $key) 
         {
           $getter = 'get' . ucfirst($key);
           $setter = 'set' . ucfirst($key);
           $element->$setter($modifiedElement->$getter());
         }
         $element->setModifiedElement(null);
      }

      $this->mailService->sendAutomatedMail('edit', $element, $message);

      $this->webhookService->queue('edit', $element, $this->securityContext->getToken()->getUser());
   }

   private function refuseModifiedElement($element) 
   {
      $element->setModifiedElement(null);
   }

   private function updateStatusAfterValidationOrRefusal($element, $isAccepted, $validationType)
   {
      if ($validationType == ValidationType::Collaborative) $element->setStatus($isAccepted ? ElementStatus::CollaborativeValidate : ElementStatus::CollaborativeRefused);
      else if ($validationType == ValidationType::Admin) $element->setStatus($isAccepted ? ElementStatus::AdminValidate : ElementStatus::AdminRefused); 
   }

   private function sendMailToContributorAfterValidationOrRefusal($element, $isAccepted, $validationType, $message = null)
   {
      if (!$message) $message = $element->getCurrContribution()->getResolvedMessage();
      $this->mailService->sendAutomatedMail($isAccepted ? 'validation' : 'refusal', $element, $message);
   }

   private function resolveContribution($element, $isAccepted, $validationType, $message)
   {
      if ($validationType == ValidationType::Admin)
      {
         $element->getCurrContribution()->setResolvedMessage($message);
         $element->getCurrContribution()->updateResolvedby($this->securityContext);
         $element->getCurrContribution()->setStatus($isAccepted ? ElementStatus::AdminValidate : ElementStatus::AdminRefused);
      }
      else
      {
         $text = $isAccepted ? 'Cette contribution a été approuvée le processus de modération collaborative' : 'Cette contribution a été refusée par le processus de modération collaborative';
         $element->getCurrContribution()->setResolvedMessage($text);
         $element->getCurrContribution()->setResolvedby("Collaborative process");
         $element->getCurrContribution()->setStatus($isAccepted ? ElementStatus::CollaborativeValidate : ElementStatus::CollaborativeRefused);
      }
   }

}