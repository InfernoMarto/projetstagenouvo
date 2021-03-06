<?php

namespace App\Command;

use App\Services\MailService;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Services\DocumentManagerFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Services\UrlService;

final class RemoveAbandonnedProjectsCommand extends GoGoAbstractCommand
{

    public function __construct(DocumentManagerFactory $dm, LoggerInterface $commandsLogger,
                               TokenStorageInterface $security, UrlService $urlService,
                               MailService $mailService,
                               $baseUrl
                           )
    {
        $this->baseUrl = $baseUrl;
        $this->urlService = $urlService;
        $this->mailService = $mailService;
        parent::__construct($dm, $commandsLogger, $security);

        $this->runOnlyOnRootDatabase = true;
    }

    protected function gogoConfigure(): void
    {
        $this
          ->setName('app:projects:check-for-deleting')
          ->setDescription('Check project that are abandonned (no login, no elements...) and ask owner to remove them') // 
       ;
    }

    protected function gogoExecute(DocumentManager $dm, InputInterface $input, OutputInterface $output): void
    {
        $date = new \DateTime();
        $qb = $dm->query('Project');
        $projectsToWarn = $qb
            ->addAnd(
                $qb->expr()->addOr(
                    $qb->expr()->field('lastLogin')->lte($date->setTimestamp(strtotime("-8 month"))), 
                    $qb->expr()->field('lastLogin')->lte($date->setTimestamp(strtotime("-4 month")))->field('dataSize')->lte(5)
                ),
                $qb->expr()->addOr(
                    $qb->expr()->field('warningToDeleteProjectSentAt')->exists(false),
                    // resend the message every month
                    $qb->expr()->field('warningToDeleteProjectSentAt')->lte($date->setTimestamp(strtotime("-1 month"))) 
                )
            )->getCursor();
                        
        if ($projectsToWarn->count() > 0)
            $this->log('Project warned count : '. $projectsToWarn->count());

        foreach ($projectsToWarn as $project) {
            $subject = "Votre carte cr????e sur $this->baseUrl peut elle ??tre effac??e?"; // TODO translate
            $adminUrl = $this->urlService->generateUrlFor($project, 'sonata_admin_dashboard');
            $content = "Bonjour !</br></br> Vous ??tes administrateur.ice de la carte {$project->getName()} sur {$this->baseUrl}. Nous avons not?? qu'aucun utilisateur ne s'est logu?? sur cette carte depuis plusieurs mois. Votre projet est-il abandonn???</br>
                Le nombre de carte sur $this->baseUrl ne cesse de grandir, et cela utilise pas mal de ressources sur notre serveur. </br>
                <b>Si votre projet n'a plus lieu d'??tre merci de vous connecter ?? votre <a href='{$adminUrl}'>espace d'administration</a> et de cliquer sur \"Supprimer mon projet\" en bas du menu de gauche.</b></br>
                Si au contraire vous souhaitez conserver votre projet, merci de vous loguer sur votre carte.</br>
                <b>Si votre inactivit?? persiste dans les prochains mois, nous nous r??servons le droit de supprimer votre carte</b></br></br>
                Bien cordialement,</br>
                L'??quipe de {$this->baseUrl}"; // TODO translate
            foreach ($project->getAdminEmailsArray() as $email) {
                $this->mailService->sendMail($email, $subject, $content);
            }

            $project->setWarningToDeleteProjectSentAt(time());
            $dm->persist($project);
        }

        // $projectsToDelete = $dm->query('Project')
        //                 ->field('lastLogin')->lte($date->setTimestamp(strtotime("-12 month")))
        //                 ->field('warningToDeleteProjectSentAt')->lte($date->setTimestamp(strtotime("-4 month")))
        //                 ->execute();

        // $message = "Les projets suivants sont probablement ?? supprimer : ";
        // foreach ($projectsToDelete as $project) {
        //     $projectUrl = $this->urlService->generateUrlFor($project);
        //     $message .= '<li><a target="_blank" href="' . $projectUrl .'">' . $project->getName() .' / Nombre de points : ' . $project->getDataSize() .'</a></li>';
        //     $project->setWarningToDeleteProjectSentAt(time());
        // }

        // if ($projectsToDelete->count() > 0) {
        //     $this->log('Nombre de projets ?? supprimer : '. $projectsToDelete->count());
        //     $log = new GoGoLogUpdate('info',  $message);
        //     $dm->persist($log);
        // }

        $dm->flush();
    }
}
