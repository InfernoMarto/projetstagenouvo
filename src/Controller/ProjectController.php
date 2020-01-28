<?php

namespace App\Controller;

use App\Controller\AbstractSaasController;
use App\Helper\SaasHelper;
use Symfony\Component\HttpFoundation\Request;
use App\Document\Project;
use App\Document\ScheduledCommand;
use App\Command\GoGoMainCommand;
use Symfony\Component\HttpFoundation\Response;
use App\Document\Configuration;
use App\DataFixtures\MongoDB\LoadTileLayers;
use Application\Sonata\UserBundle\Form\Type\RegistrationFormType;
use App\Document\User;
use FOS\UserBundle\Model\UserInterface;
use App\DataFixtures\MongoDB\LoadConfiguration;
use App\Document\Taxonomy;
use App\Document\Category;
use App\Document\Option;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

class ProjectController extends AbstractSaasController
{
    protected function isAuthorized()
    {
        $sassHelper = new SaasHelper();
        return $sassHelper->isRootProject();
    }

    protected function getOdmForProject($project)
    {
        $odm = $this->get('doctrine_mongodb')->getManager();
        $odm->getConfiguration()->setDefaultDB($project->getDbName());
        return $odm;
    }

    protected function generateUrlForProject($project, $route = 'biopen_homepage')
    {
        return 'http://' . $project->getDomainName() . '.' . $this->getParameter('base_url') . $this->generateUrl($route);
    }

    public function createAction(Request $request)
    {
        if (!$this->isAuthorized()) return $this->redirectToRoute('biopen_homepage');

        $odm = $this->get('doctrine_mongodb')->getManager();
        $domain = $request->request->get('form')['domainName'];
        if ($domain) // if submiting the form
        {
            $existingProject = $odm->getRepository(Project::class)->findOneByDomainName($domain);
            // fix a bug sometime the form says that the project already exist but actually we just created it
            // but it has not been initialized
            // so redirect to initialize project
            if ($existingProject && $existingProject->getDataSize() == 0)
                return $this->redirect($this->generateUrlForProject($existingProject, 'biopen_saas_initialize_project'));
        }

        $project = new Project();

        $projectForm = $this->createFormBuilder($project)
            ->add('name', null, array('required' => true))
            ->add('domainName', null, array('required' => true))
            ->getForm();

        if ($projectForm->handleRequest($request)->isValid())
        {
            $odm->persist($project);
            $odm->flush();
            // initialize commands
            $commands = (new GoGoMainCommand())->scheduledCommands;
            foreach ($commands as $commandName => $period) {
                $scheduledCommand = new ScheduledCommand();
                $scheduledCommand->setProject($project);
                $scheduledCommand->setNextExecutionAt(time());
                $scheduledCommand->setCommandName($commandName);
                $project->addCommand($scheduledCommand);
                $odm->persist($scheduledCommand);
            }
            $odm->flush();

            // Switch to new project ODM
            $projectOdm = $this->getOdmForProject($project);

            // Clone the root configuration into the new project
            // Due to conflicts between ODM, we get the Configuration froma Json API, and convert it to an object
            $baseUrl = $this->getParameter('base_url');
            if ($baseUrl == 'saas.localhost') $baseUrl = "gogocarto.fr"; # Fixs for docker in localhost
            $configUrl = 'http://' . $baseUrl . $this->generateUrl('biopen_api_configuration');
            $rootConfigToCopy = json_decode(file_get_contents($configUrl));
            $rootConfigToCopy->appName = $project->getName();
            $rootConfigToCopy->appBaseLine = "";
            $rootConfigToCopy->dbName = $project->getDbName();
            // Duplicate configuration
            $confLoader = new LoadConfiguration();
            $configuration = $confLoader->load($projectOdm, $this->container, $rootConfigToCopy, $request->request->get('contrib'));

            // Generate basic categories
            $mainCategory = new Category();
            $mainCategory->setName('Catégories Principales');
            $mainCategory->setPickingOptionText('Une catégorie principale');
            $projectOdm->persist($mainCategory);

            $mains = array(
                array('Catégorie 1'  , 'fa fa-recycle'     , '#98a100'),
                array('Catégorie 2'  , 'fa fa-home'       , '#7e3200'),
            );

            foreach ($mains as $key => $main)
            {
                $new_main = new Option();
                $new_main->setName($main[0]);
                $new_main->setIcon($main[1]);
                $new_main->setColor($main[2]);
                $new_main->setIsFixture(true);
                $mainCategory->addOption($new_main);
            }

            $projectOdm->flush(); // flush before taxonomy creating otherwise strange bug creating option with only DBRef

            $taxonomy = new Taxonomy();
            $projectOdm->persist($taxonomy);
            $projectOdm->flush();

            $projectOdm->getSchemaManager()->updateIndexes();

            $url = $this->generateUrlForProject($project, 'biopen_saas_initialize_project');
            return $this->redirect($url);
        }

        $config = $odm->getRepository('App\Document\Configuration')->findConfiguration();

        return $this->render('saas/projects/create.html.twig', ['form' => $projectForm->createView(), 'config' => $config]);
    }

    /**
     * @Route("/projects", name="biopen_saas_home")
     */
    public function homeAction()
    {
        if (!$this->isAuthorized()) return $this->redirectToRoute('biopen_homepage');

        $odm = $this->get('doctrine_mongodb')->getManager();
        $repository = $odm->getRepository('App\Document\Project');

        $config = $odm->getRepository('App\Document\Configuration')->findConfiguration();

        $projects = $repository->findBy([], ['dataSize' => 'DESC']);

        foreach ($projects as $project) {
            $project->setHomeUrl($this->generateUrlForProject($project));
        }

        return $this->render('saas/home.html.twig', array('projects' => $projects, 'config' => $config));
    }

    public function initializeAction(Request $request)
    {
        $odm = $this->get('doctrine_mongodb')->getManager();
        $users = $odm->getRepository('App\Document\User')->findAll();
        if (count($users) > 0) return $this->redirectToRoute('biopen_homepage');

        $userManager = $this->container->get('fos_user.user_manager');
        $user = $userManager->createUser();

        $form = $this->get('form.factory')->create(RegistrationFormType::class, $user);

        if ($form->handleRequest($request)->isValid()) {
            $user = $form->getData();
            $user->setEnabled(true);
            $user->setRoles(array('ROLE_SUPER_ADMIN'));
            $userManager->updateUser($user, true);

            $this->get('session')->getFlashBag()->add('success', "<b>Bienvenue dans votre espace Administrateur !</b></br>
                L'aventure commence tout juste pour vous, il vous faut maintenant commencer à configurer votre site :)</br>
                Je vous invite à consulter les <a target='_blank' href='https://video.colibris-outilslibres.org/video-channels/gogocarto_channel/videos'>vidéos tutoriels</a> pour vous apprendre à configurer votre carte !</br>
                Si vous avez des questions (après avoir regardé ces vidéos) rendez vous sur <a target='_blank' href='https://chat.lescommuns.org/channel/gogocarto'>le chat #gogocarto</a> !");
            $response = $this->redirectToRoute('sonata_admin_dashboard');

            $this->authenticateUser($user, $response);

            return $response;
        }

        $config = $odm->getRepository('App\Document\Configuration')->findConfiguration();
        return $this->render('saas/projects/initialize.html.twig', ['form' => $form->createView(), 'config' => $config]);
    }

    protected function authenticateUser(UserInterface $user, Response $response)
    {
        try {
            $this->get('fos_user.security.login_manager')->loginUser(
                $this->getParameter('fos_user.firewall_name'),
                $user,
                $response
            );
        } catch (AccountStatusException $ex) { }
    }

    // The project is being deleted by the owner
    public function deleteCurrProjectAction()
    {
        $saasHelper = new SaasHelper();
        $dbname = $saasHelper->getCurrentProjectCode();
        $commandline = 'mongo ' . $dbname .' --eval "db.dropDatabase()"';
        $process = new Process($commandline);
        $process->start();
        $url = $this->generateUrl('biopen_project_delete_saas_record', ['dbName' => $dbname], true);
        $url = str_replace($dbname . '.', '', $url);
        return $this->redirect($url);
    }

    public function deleteSaasRecordAction($dbName)
    {
        $dm = $this->get('doctrine_mongodb')->getManager();
        $command = "mongo --eval 'db.getMongo().getDBNames().indexOf(\"{$dbName}\")'";
        $process = new Process($command);
        $process->run();
        $isDbEmpty = substr($process->getOutput(),-3) == "-1\n";
        // As it is a public API, only allow delete if the db is empty
        if ($isDbEmpty) {
            $project = $dm->getRepository(Project::class)->findOneByDomainName($dbName);
            $dm->remove($project);
            $dm->flush();
        }

        return $this->redirectToRoute('biopen_homepage');
    }
}
