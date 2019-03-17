<?php

namespace Biopen\CoreBundle\Command;

use Biopen\SaasBundle\Command\GoGoAbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Biopen\CoreBundle\Document\MigrationState;
use Biopen\CoreBundle\Document\GoGoLogUpdate;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Process\Process;
/**
 * Command to update database when schema need migration
 * Also provide some update message in the admin dashboard
 */
class MigrationCommand extends GoGoAbstractCommand
{
    protected function gogoConfigure()
    {
        $this->setName('db:migrate')
             ->setDescription('Update datatabse each time after code update');
    }

    protected function gogoExecute($em, InputInterface $input, OutputInterface $output)
    {       
        $migrationState = $em->createQueryBuilder('BiopenCoreBundle:MigrationState')->getQuery()->getSingleResult();
        if ($migrationState == null) // Meaning the migration state was not yet in the place in the code
        {
            $migrationState = new MigrationState();
            $em->persist($migrationState);
        }

        // Collecting the Database to be updated
        $dbs = ['gogocarto_default'];
        $dbNames = $em->createQueryBuilder('BiopenSaasBundle:Project')->select('domainName')->hydrate(false)->getQuery()->execute()->toArray();            
        foreach ($dbNames as $object) { $dbs[] = $object['domainName']; }

        if (count($this->migrations) > $migrationState->getMigrationIndex()) {
            $migrationsToRun = array_slice($this->migrations, $migrationState->getMigrationIndex());
            foreach($dbs as $db) {
                foreach($migrationsToRun as $migration) {
                    $this->runCommand($db, $migration);
                }                    
            }
            $this->log(count($migrationsToRun) . " migrations performed");
        } else {
            $this->log("No Migrations to perform");
        }

        $asyncService = $this->getContainer()->get('biopen.async');
        if (count($this->messages) > $migrationState->getMessagesIndex()) {
            $messagesToAdd = array_slice($this->messages, $migrationState->getMessagesIndex());
            foreach($dbs as $db) {
                foreach($messagesToAdd as $message) {
                    // create a GoGoLogUpdate                    
                    $asyncService->callCommand('gogolog:add:message', ['"' . $message . '"'], $db);
                }                    
            }
            $this->log(count($messagesToAdd) . " messages added to admin dashboard");
        } else {
            $this->log("No Messages to add to dashboard");
        }

        $migrationState->setMigrationIndex(count($this->migrations));
        $migrationState->setMessagesIndex(count($this->messages));
        $em->flush();        
    }

    private function runCommand($db, $command)
    {
        $process = new Process("mongo {$db} --eval \"{$command}\"");
        return $process->start();
    }
    
    // ---------------------------------------------------------------
    // DO NOT REMOVE A SINGLE ELEMENT OF THIS ARRAY, ONLY ADD NEW ONES
    // ---------------------------------------------------------------
    public $migrations = [
      // March 2019
      // "db.Category.renameCollection('CategoryGroup')",
      // "db.Option.renameCollection('Category')"
    ];

    public $messages = [
        "Un champ <b>Image (url)</b> est maintenant disponible dans la confiugration du formulaire !"
    ];
}