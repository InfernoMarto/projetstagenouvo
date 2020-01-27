<?php

/**
 * @Author: Sebastian Castro
 * @Date:   2017-12-30 14:32:19
 * @Last Modified by:   Sebastian Castro
 * @Last Modified time: 2018-02-11 13:06:59
 */

namespace Application\Sonata\UserBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use App\Document\UserInteractionReport;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Document\InteractionType;

class GamificationService {

   protected $interactionRepo;

   public function __construct(DocumentManager $documentManager)
   {
      $this->contribsRepo = $documentManager->getRepository('App\Document\UserInteractionContribution');
      $this->votesRepo = $documentManager->getRepository('App\Document\UserInteractionVote');
      $this->reportsRepo = $documentManager->getRepository('App\Document\UserInteractionReport');
   }

   public function updateGamification($user)
   {
      if (!$user->getEmail()) return;

      $contribs = $this->contribsRepo->findByUserEmail($user->getEmail());

      $contribs = array_filter($contribs, function($interaction) {
         return in_array($interaction->getType(), [InteractionType::Add, InteractionType::Edit]);
      });

      $votes = $this->votesRepo->findByUserEmail($user->getEmail());
      $reports = $this->reportsRepo->findByUserEmail($user->getEmail());

      $result = count($contribs) * 3 + count($reports) + count($votes);
      $user->setGamification($result);
      $user->setContributionsCount(count($contribs));
      $user->setVotesCount(count($votes));
      $user->setReportsCount(count($reports));
   }
}