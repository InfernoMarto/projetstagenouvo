<?php

namespace Biopen\GeoDirectoryBundle\Controller\Admin\BulkActions;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class DbMigrationsActionsController extends BulkActionsAbstractController
{
   public function generateRandomHashAction(Request $request, SessionInterface $session)
   {
      return $this->elementsBulkAction('generateRandomHash', $request, $session);
   }
   public function generateRandomHash($element)
   {
      $element->updateRandomHash();
   }

   public function generateTokenAction(Request $request, SessionInterface $session)
   {
      $em = $this->get('doctrine_mongodb')->getManager();
      $users = $em->getRepository('BiopenCoreBundle:User')->findAll();

      $i = 0;
      foreach ($users as $key => $user)
      {
         $user->createToken();
         if ((++$i % 100) == 0) {
            $em->flush();
            $em->clear();
         }
      }
      $em->flush();
      $em->clear();

      $session->getFlashBag()->add('success', "Les éléments ont été mis à jours avec succès.");
      return $this->redirectToIndex();
   }

   public function addImportContributionAction(Request $request, SessionInterface $session)
   {
      return $this->elementsBulkAction('addImportContribution', $request, $session);
   }
   public function addImportContribution($element)
   {
      $contribution = new UserInteractionContribution();
      $contribution->setUserRole(UserRoles::Admin);
      $contribution->setUserEmail('admin@presdecheznous.fr');
      $contribution->setType(InteractionType::Import);

      $element->resetContributions();
      $element->resetReports();
      $element->addContribution($contribution);
      $element->setStatus(ElementStatus::AdminValidate, false);
   }

}