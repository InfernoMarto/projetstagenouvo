<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/*
* For SAAS Instance, this command is executed every minute, and check if there is a command to execute
* for a particular instance. This permit to not run all the commands as the same time
*/
class GoGoMainCommand extends ContainerAwareCommand
{
   // List of the command to execute periodically, with the period in hours
   public $scheduledCommands = [
      "app:elements:checkvote" => "24H",
      "app:elements:checkExternalSourceToUpdate" => "24H",
      "app:users:sendNewsletter" => "1H",
      "app:webhooks:post" => "5M" // 5 minuutes
   ];

   protected function configure()
   {
      $this->setName('app:main-command');
   }

   protected function execute(InputInterface $input, OutputInterface $output)
   {
      $dm = $this->getContainer()->get('doctrine_mongodb.odm.default_document_manager');

      $qb = $dm->createQueryBuilder('BiopenSaasBundle:ScheduledCommand');

      $commandToExecute = $qb->field('nextExecutionAt')->lte(new \DateTime())
                             ->sort('nextExecutionAt', 'ASC')
                             ->getQuery()->getSingleResult();

      $logger = $this->getContainer()->get('monolog.logger.commands');

      if ($commandToExecute !== null)
      {
         // Updating next execution time
         $dateNow = new \DateTime();
         $dateNow->setTimestamp(time());
         $interval = new \DateInterval('PT' . $this->scheduledCommands[$commandToExecute->getCommandName()]);
         $commandToExecute->setNextExecutionAt($dateNow->add($interval));
         $dm->persist($commandToExecute);
         $dm->flush();

         try {
          $logger->info('---- Running command ' . $commandToExecute->getCommandName() . ' for project : ' . $commandToExecute->getProject()->getName());
         } catch (\Exception $e) {
          // the project has been deleted
          $logger->info('---- DELETEING command ' . $commandToExecute->getCommandName());
          $dm->remove($commandToExecute);
          $dm->flush();
          return;
         }
         $command = $this->getApplication()->find($commandToExecute->getCommandNAme());

         $arguments = array(
           'command' => $commandToExecute->getCommandName(),
           'dbname'  => $commandToExecute->getProject()->getDbName(),
         );

         $input = new ArrayInput($arguments);
         try { $command->run($input, $output); }
         catch (\Exception $e) { $logger->error($e->getMessage()); }
      }
   }
}