<?php

namespace Biopen\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Biopen\GeoDirectoryBundle\Document\Coordinates;
use Biopen\GeoDirectoryBundle\Document\ElementStatus;
use Biopen\GeoDirectoryBundle\Document\ModerationState;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class MailTestController extends Controller
{
   public function draftAutomatedAction(Request $request, SessionInterface $session, $mailType)
   {
      $mailService = $this->container->get('biopen.mail_service');
      $draftResponse = $this->draftTest($mailType);

      if ($draftResponse == null) return new Response('No visible elements in database, please create an element');

      if ($draftResponse['success'])
      {
         $mailContent = $mailService->draftTemplate($draftResponse['content']);
         return $this->render('@BiopenCoreBundle/emails/test-emails.html.twig', array('subject' => $draftResponse['subject'], 'content' => $mailContent, 'mailType' => $mailType));
      }
      else
      {
         $session->getFlashBag()->add('error', 'Error : ' . $draftResponse['message']);
         return $this->redirectToRoute('admin_biopen_core_configuration_list');
      }
   }

   public function sentTestAutomatedAction(Request $request, SessionInterface $session, $mailType)
   {
      $mail = $request->get('email');

      if (!$mail) return new Response('Aucune adresse mail n\'a été renseignée');
      $mailService = $this->container->get('biopen.mail_service');

      $draftResponse = $this->draftTest($mailType);

      if ($draftResponse == null)
      {
         $session->getFlashBag()->add('error', 'No elements in database, please create an element for email testing');
         return $this->redirectToRoute('admin_biopen_core_configuration_list');
      }

      if ($draftResponse['success'])
      {
         $result = $mailService->sendMail($mail,$draftResponse['subject'], $draftResponse['content']);
         if ($result['success'])
          $session->getFlashBag()->add('success', 'Le mail a bien été envoyé à ' . $mail . '</br>Si vous ne le voyez pas vérifiez dans vos SPAMs');
         else
          $session->getFlashBag()->add('error', $result['message']);
      }
      else
      {
         $session->getFlashBag()->add('error', 'Erreur : ' . $draftResponse['message']);
      }
      return $this->redirectToRoute('biopen_mail_draft_automated', array('mailType' => $mailType));
   }

  private function draftTest($mailType)
  {
     $em = $this->get('doctrine_mongodb')->getManager();
     $options = null;

     if ($mailType == 'newsletter')
     {
        $element = $em->getRepository('BiopenCoreBundle:User')->findOneByEnabled(true);
        $element->setLocation('bordeaux');
        $element->setGeo(new Coordinates(44.876,-0.512));
        $qb = $em->createQueryBuilder('BiopenGeoDirectoryBundle:Element');
        $qb->field('status')->gte(ElementStatus::AdminRefused);
        $qb->field('moderationState')->notIn(array(ModerationState::GeolocError, ModerationState::NoOptionProvided));
        $options = $qb->limit(30)->getQuery()->execute();
     }
     else
     {
      $element = $em->getRepository('BiopenGeoDirectoryBundle:Element')->findVisibles()->getSingleResult();
     }

     if (!$element) return null;

     $mailService = $this->container->get('biopen.mail_service');
     $draftResponse = $mailService->draftEmail($mailType, $element, "Un customMessage de test", $options);
     return $draftResponse;
  }
}
