<?php

namespace App\Repository;

use App\Document\CodeInvitation;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

/**
 * CodeInvitationRepository.
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class CodeInvitationRepository extends DocumentRepository
{
    public function findAllOrderedByPosition()
    {
        return $this->createQueryBuilder()
          ->sort('id', 'ASC')
          ->getQuery();
          //->execute();
    }
    
    public function codeInvitationIsValid($userCodeInvitation)
    {
        $test = $this->findOneBY(['code' => $userCodeInvitation, 'active' => true]);
        if($test==null){
            return false;
        }else{
            $this->findOneBY(['code' => $userCodeInvitation])->setActive(false);
            return true;
        }

    }
}
