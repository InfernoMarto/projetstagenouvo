<?php

namespace App\Controller\Admin\BulkActions;

use App\Document\ModerationState;
use App\Services\ElementDuplicatesService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class DuplicatesDetectionController extends BulkActionsAbstractController
{
    public function detectDuplicatesAction(Request $request, SessionInterface $session, DocumentManager $dm,
                                           ElementDuplicatesService $duplicateService)
    {
        $this->title = 'Détection des doublons'; // TODO translate
        $this->automaticRedirection = false;
        $this->batchSize = 1000;
        $this->duplicateService = $duplicateService;
        $this->config = $dm->get('Configuration')->findConfiguration();

        if (!$request->get('batchFromStep')) {
            // reset previous detections
            $dm->query('Element')->updateMany()
                ->field('isDuplicateNode')->unsetField()
                ->field('potentialDuplicates')->unsetField()
                ->execute();
            $dm->query('Element')->updateMany()
                ->field('moderationState')->equals(ModerationState::PotentialDuplicate)
                ->field('moderationState')->set(ModerationState::NotNeeded)
                ->execute();
        }

        return $this->elementsBulkAction('detectDuplicates', $dm, $request, $session);
    }

    public function detectDuplicates($element, $dm)
    {
        $result = $this->duplicateService->detectDuplicatesFor($element);
        if ($result === null) return "";
        return $this->render('admin/pages/bulks/bulk_duplicates.html.twig', [
            'duplicates' => [$result['elementToKeep'], $result['duplicate']],
            'config' => $this->config,
            'automaticMerge' => $result['automaticMerge'],
         ]);
    }
}
