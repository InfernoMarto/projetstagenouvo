<?php

/**
 * This file is part of the MonVoisinFaitDuBio project.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) 2016 Sebastian Castro - 90scastro@gmail.com
 * @license    MIT License
 * @Last Modified time: 2017-05-26 16:28:37
 */
 

namespace Biopen\GeoDirectoryBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Biopen\GeoDirectoryBundle\Document\ElementStatus;
use Biopen\GeoDirectoryBundle\Document\ModerationState;
use Biopen\GeoDirectoryBundle\Document\UserInteraction;
use Symfony\Component\Security\Core\SecurityContext;

class ElementVoteService
{	
	protected $em;
    protected $user;

    protected $minVoteToChangeStatus = 3;
    protected $maxOppositeVoteTolerated = 0;

	/**
     * Constructor
     */
    public function __construct(DocumentManager $documentManager, SecurityContext $securityContext)
    {
    	 $this->em = $documentManager;
         $this->user = $securityContext->getToken()->getUser(); 
    }

    public function voteForelement($element, $voteValue, $comment)
    {
        // Check user don't vote for his own creation
        if ($element->getContributorMail() == $this->user->getEmail())
                return "Erreur : vous ne pouvez pas votez pour votre propre contribution";

        // CHECK USER HASN'T ALREADY VOTED
        $currentVotes = $element->getVotes();
        $hasAlreadyVoted = false;
        foreach ($currentVotes as $key => $vote) 
        {
            if ($vote->getUserMail() == $this->user->getEmail()) 
            {
                $hasAlreadyVoted = true;
                $oldVote= $vote;
            }
        }

        if (!$hasAlreadyVoted) $vote = new UserInteraction();       
        
        $vote->setValue($voteValue);
        $vote->setUserMail($this->user->getEmail());

        if ($comment) $vote->setComment($comment);

        $element->addVote($vote);

        if ($this->user->isAdmin())
        {
            $element->setStatus($voteValue < 0 ? ElementStatus::AdminRefused : ElementStatus::AdminValidate);
        }
        else $this->checkVotes($element);

        $this->em->persist($element);
        $this->em->flush();

        $resultMessage = $hasAlreadyVoted ? 'Merci ' . $this->user . " : votre vote a bien été modifié !" : "Merci de votre contribution !";

        return $resultMessage;
    }

    private function checkVotes($element)
    {
        $currentVotes = $element->getVotes();
        $nbrePositiveVote = 0;
        $nbreNegativeVote = 0;

        foreach ($currentVotes as $key => $vote) 
        {
           $vote->getValue() >= 0 ? $nbrePositiveVote++ : $nbreNegativeVote++;
        }

        if ($nbrePositiveVote >= $this->minVoteToChangeStatus)
        {
            if ($nbreNegativeVote <= $this->maxOppositeVoteTolerated) $element->setStatus(ElementStatus::CollaborativeValidate);
            else 
            {
                $element->setModerationState(ModerationState::VotesConflicts);
            }
        }
        else if ($nbreNegativeVote >= $this->minVoteToChangeStatus)
        {
            if ($nbrePositiveVote <= $this->maxOppositeVoteTolerated) $element->setStatus(ElementStatus::CollaborativeRefused);
            else 
            {
                $element->setModerationState(ModerationState::VotesConflicts);
            }
        }
    }
}
