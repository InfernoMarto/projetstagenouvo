<?php

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Application\Sonata\UserBundle\Controller;

use FOS\UserBundle\Model\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AccountStatusException;

use App\Application\Sonata\UserBundle\Form\Type\RegistrationFormType;
use App\Document\User;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * This class is inspired from the FOS RegistrationController.
 *
 * @author Hugo Briand <briand@ekino.com>
 */
class RegistrationFOSUser1Controller extends Controller
{
    /**
     * @return RedirectResponse|Response
     */
    public function registerAction(Request $request = null, SessionInterface $session)
    {
        $odm = $this->get('doctrine_mongodb')->getManager();
        $config = $odm->getRepository('App\Document\Configuration')->findConfiguration();
        if (!$config->getUser()->getEnableRegistration()) {
            $session->getFlashBag()->add('error', "Désolé, vous n'êtes pas autorisé à créer un compte.");
            return $this->redirectToRoute('gogo_directory');
        }

        $user = $this->getUser();

        if ($user instanceof UserInterface) {
            $session->getFlashBag()->set('sonata_user_error', 'sonata_user_already_authenticated');

            return $this->redirect($this->generateUrl('sonata_user_profile_show'));
        }

        $form = $this->get('form.factory')->create(RegistrationFormType::class, new User());
        $formHandler = $this->get('gogo.registration.form.handler');


        $confirmationEnabled = $config->getUser()->getSendConfirmationEmail();

        $process = $formHandler->process($form, $confirmationEnabled);
        if ($process) {
            $user = $form->getData();

            $authUser = false;
            if ($confirmationEnabled) {
                $this->get('session')->set('fos_user_send_confirmation_email/email', $user->getEmail());
                $url = $this->generateUrl('fos_user_registration_check_email');
            } else {
                $authUser = true;
                $route = $this->get('session')->get('sonata_basket_delivery_redirect');

                if (null !== $route) {
                    $this->get('session')->remove('sonata_basket_delivery_redirect');
                    $url = $this->generateUrl($route);
                } else {
                    $url = $this->get('session')->get('sonata_user_redirect_url');
                }
            }

            if (!$url) {
                $url = $this->generateUrl('sonata_user_profile_show');
            }

            $this->setFlash('fos_user_success', 'registration.flash.user_created');

            $response = $this->redirect($url);

            if ($authUser) {
                $this->authenticateUser($user, $response);
            }

            return $response;
        }

        // NEXT_MAJOR: Inject $request in the method signature instead.
        if ($this->has('request_stack')) {
            $request = $this->get('request_stack')->getCurrentRequest();
        } else {
            $request = $this->get('request');
        }

        $this->get('session')->set('sonata_user_redirect_url', $request->headers->get('referer'));

        return $this->render('FOSUserBundle:Registration:register.html.'.$this->getEngine(), [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Tell the user to check his email provider.
     *
     * @return Response
     *
     * @throws NotFoundHttpException
     */
    public function checkEmailAction()
    {
        $dmail = $this->get('session')->get('fos_user_send_confirmation_email/email');
        $this->get('session')->remove('fos_user_send_confirmation_email/email');
        $user = $this->get('fos_user.user_manager')->findUserByEmail($dmail);

        if (null === $user) {
            throw new NotFoundHttpException(sprintf('The user with email "%s" does not exist', $dmail));
        }

        return $this->render('FOSUserBundle:Registration:checkEmail.html.'.$this->getEngine(), [
            'user' => $user,
        ]);
    }

    /**
     * Receive the confirmation token from user email provider, login the user.
     *
     * @param string $token
     *
     * @return RedirectResponse
     *
     * @throws NotFoundHttpException
     */
    public function confirmAction($token)
    {
        $user = $this->get('fos_user.user_manager')->findUserByConfirmationToken($token);

        if (null === $user) {
            throw new NotFoundHttpException(sprintf('The user with confirmation token "%s" does not exist', $token));
        }

        $user->setConfirmationToken(null);
        $user->setEnabled(true);
        $user->setLastLogin(new \DateTime());

        $this->get('fos_user.user_manager')->updateUser($user);
        if ($redirectRoute = $this->getParameter('sonata.user.register.confirm.redirect_route')) {
            $response = $this->redirect($this->generateUrl(
                $redirectRoute,
                $this->getParameter('sonata.user.register.confirm.redirect_route_params')
            ));
        } else {
            $response = $this->redirect($this->generateUrl('fos_user_registration_confirmed'));
        }

        $this->authenticateUser($user, $response);

        return $response;
    }

    /**
     * Tell the user his account is now confirmed.
     *
     * @return Response
     *
     * @throws AccessDeniedException
     */
    public function confirmedAction()
    {
        $user = $this->getUser();
        if (!is_object($user) || !$user instanceof UserInterface) {
            throw new AccessDeniedException('This user does not have access to this section.');
        }

        return $this->render('FOSUserBundle:Registration:confirmed.html.'.$this->getEngine(), [
            'user' => $user,
        ]);
    }

    /**
     * Authenticate a user with Symfony Security.
     *
     * @param UserInterface $user
     * @param Response      $response
     */
    protected function authenticateUser(UserInterface $user, Response $response)
    {
        try {
            $this->get('fos_user.security.login_manager')->loginUser(
                $this->getParameter('fos_user.firewall_name'),
                $user,
                $response
            );
        } catch (AccountStatusException $ex) {
            // We simply do not authenticate users which do not pass the user
            // checker (not enabled, expired, etc.).
        }
    }

    /**
     * @param string $action
     * @param string $value
     */
    protected function setFlash($action, $value)
    {
        $this->get('session')->getFlashBag()->set($action, $value);
    }

    /**
     * @return string
     */
    protected function getEngine()
    {
        return $this->getParameter('fos_user.template.engine');
    }
}
