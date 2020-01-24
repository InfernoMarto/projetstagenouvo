<?php

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) 2016 Sebastian Castro - 90scastro@gmail.com
 * @license    MIT License
 * @Last Modified time: 2018-04-07 16:22:43
 */


namespace Biopen\GeoDirectoryBundle\Controller;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Biopen\GeoDirectoryBundle\Document\Element;
use Biopen\GeoDirectoryBundle\Document\ElementStatus;
use Biopen\GeoDirectoryBundle\Document\UserInteractionReport;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

class ElementInteractionController extends Controller
{
    public function voteAction(Request $request)
    {
        if (!$this->container->get('biopen.config_service')->isUserAllowed('vote', $request))
            return $this->returnResponse(false,"Désolé, vous n'êtes pas autorisé à voter !");

        // CHECK REQUEST IS VALID
        if (!$request->get('elementId') || $request->get('value') === null)
            return $this->returnResponse(false,"Les paramètres du vote sont incomplets");

        $em = $this->get('doctrine_mongodb')->getManager();

        $element = $em->getRepository('BiopenGeoDirectoryBundle:Element')->find($request->get('elementId'));

        $resultMessage = $this->get('biopen.element_vote_service')
                         ->voteForElement($element, $request->get('value'), $request->get('comment'), $request->get('userEmail'));

        return $this->returnResponse(true, $resultMessage, $element->getStatus());
    }

    public function reportErrorAction(Request $request)
    {
        if (!$this->container->get('biopen.config_service')->isUserAllowed('report', $request))
            return $this->returnResponse(false,"Désolé, vous n'êtes pas autorisé à signaler d'erreurs !");

        // CHECK REQUEST IS VALID
        if (!$request->get('elementId') || $request->get('value') === null || !$request->get('userEmail'))
            return $this->returnResponse(false,"Les paramètres du signalement sont incomplets");

        $em = $this->get('doctrine_mongodb')->getManager();

        $element = $em->getRepository('BiopenGeoDirectoryBundle:Element')->find($request->get('elementId'));

        $report = new UserInteractionReport();
        $report->setValue($request->get('value'));
        $report->updateUserInformation($this->container->get('security.token_storage'), $request->get('userEmail'));
        $comment = $request->get('comment');
        if ($comment) $report->setComment($comment);

        $element->addReport($report);

        $element->updateTimestamp();

        $em->persist($element);
        $em->flush();

        return $this->returnResponse(true, "Merci, votre signalement a bien été enregistré !");
    }

    public function deleteAction(Request $request)
    {
        if (!$this->container->get('biopen.config_service')->isUserAllowed('delete', $request))
            return $this->returnResponse(false,"Désolé, vous n'êtes pas autorisé à supprimer un élément !");

        // CHECK REQUEST IS VALID
        if (!$request->get('elementId'))
            return $this->returnResponse(false,"Les paramètres sont incomplets");

        $em = $this->get('doctrine_mongodb')->getManager();
        $element = $em->getRepository('BiopenGeoDirectoryBundle:Element')->find($request->get('elementId'));
        $em->persist($element);

        $elementActionService = $this->container->get('biopen.element_action_service');
        $elementActionService->delete($element, true, $request->get('message'));

        $em->flush();

        return $this->returnResponse(true, "L'élément a bien été supprimé");
    }

    public function resolveReportsAction(Request $request)
    {
        if (!$this->container->get('biopen.config_service')->isUserAllowed('directModeration', $request))
            return $this->returnResponse(false,"Désolé, vous n'êtes pas autorisé à modérer cet élément !");

        // CHECK REQUEST IS VALID
        if (!$request->get('elementId'))
            return $this->returnResponse(false,"Les paramètres sont incomplets");

        $em = $this->get('doctrine_mongodb')->getManager();
        $element = $em->getRepository('BiopenGeoDirectoryBundle:Element')->find($request->get('elementId'));

        $elementActionService = $this->container->get('biopen.element_action_service');
        $elementActionService->resolveReports($element, $request->get('comment'), true);

        $em->persist($element);
        $em->flush();

        return $this->returnResponse(true, "L'élément a bien été modéré");
    }

    public function sendMailAction(Request $request)
    {
        if (!$this->container->get('biopen.config_service')->isUserAllowed('sendMail', $request))
            return $this->returnResponse(false,"Désolé, vous n'êtes pas autorisé à envoyer des mails !");

        // CHECK REQUEST IS VALID
        if (!$request->get('elementId') || !$request->get('subject') || !$request->get('content') || !$request->get('userEmail'))
            return $this->returnResponse(false,"Les paramètres sont incomplets");

        $em = $this->get('doctrine_mongodb')->getManager();
        $element = $em->getRepository('BiopenGeoDirectoryBundle:Element')->find($request->get('elementId'));

        $user = $this->getUser();

        $senderMail = $request->get('userEmail');

        // TODO make it configurable
        $mailSubject = 'Message reçu depuis la plateforme ' . $this->getParameter('instance_name');
        $mailContent =
            "<p>Bonjour <i>" . $element->getName() . '</i>,</p>
            <p>Vous avez reçu un message de la part de <a href="mailto:' . $senderMail . '">' . $senderMail . "</a></br>
            </p>
            <p><b>Titre du message</b></p><p> " . $request->get('subject') . "</p>
            <p><b>Contenu</b></p><p> " . $request->get('content') . "</p>";

        $mailService = $this->container->get('biopen.mail_service');
        $mailService->sendMail($element->getEmail(), $mailSubject, $mailContent);

        return $this->returnResponse(true, "L'email a bien été envoyé");
    }

    public function stampAction(Request $request)
    {
        // CHECK REQUEST IS VALID
        if (!$request->get('stampId') || $request->get('value') === null || !$request->get('elementId'))
            return $this->returnResponse(false,"Les paramètres sont incomplets");

        $em = $this->get('doctrine_mongodb')->getManager();
        $element = $em->getRepository('BiopenGeoDirectoryBundle:Element')->find($request->get('elementId'));
        $stamp = $em->getRepository('BiopenGeoDirectoryBundle:Stamp')->find($request->get('stampId'));
        $user = $this->getUser();

        if (!in_array($stamp, $user->getAllowedStamps()->toArray()))  return $this->returnResponse(false,"Vous n'êtes pas autorisé à utiliser cette étiquette");

        if ($request->get('value') == "true")
        {
            if (!in_array($stamp, $element->getStamps()->toArray())) $element->addStamp($stamp);
        }
        else
            $element->removeStamp($stamp);

        $em->persist($element);
        $em->flush();

        return $this->returnResponse(true, "L'étiquette a bien été modifiée", $element->getStampIds());
    }

    private function returnResponse($success, $message, $data = null)
    {
        $response['success'] = $success;
        $response['message'] = $message;
        if ($data !== null) $response['data'] = $data;

        $serializer = $this->container->get('jms_serializer');
        $responseJson = $serializer->serialize($response, 'json');

        $em = $this->get('doctrine_mongodb')->getManager();
        $config = $em->getRepository('BiopenCoreBundle:Configuration')->findConfiguration();

        $response = new Response($responseJson);
        if ($config->getApi()->getInternalApiAuthorizedDomains())
           $response->headers->set('Access-Control-Allow-Origin', $config->getApi()->getInternalApiAuthorizedDomains());
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }
}
