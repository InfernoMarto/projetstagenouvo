<?php

/**
 * This file is part of the GoGoCarto project.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) 2016 Sebastian Castro - 90scastro@gmail.com
 * @license    MIT License
 * @Last Modified time: 2018-06-17 16:46:33
 */


namespace Biopen\GeoDirectoryBundle\Repository;
use Doctrine\ODM\MongoDB\DocumentRepository;
use Biopen\GeoDirectoryBundle\Document\ElementStatus;
use Biopen\GeoDirectoryBundle\Document\ModerationState;

/**
 * ElementRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ElementRepository extends DocumentRepository
{
  public function findDuplicatesFor($element, $distance, $maxResults, $includeDeleted = true, $hydrate = false)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    // convert kilometre in degrees
    $radius = $distance / 110;
    $status = $includeDeleted ? ElementStatus::Duplicate : ElementStatus::PendingModification;

    $qb->addOr($this->queryText($qb->expr(), $element->getName()));
    if ($element->getEmail()) $qb->addOr($qb->expr()->field('email')->equals($element->getEmail()));

    $qb->limit($maxResults)
       ->field('status')->gt($status)
       ->field('geo')->withinCenter((float) $element->getGeo()->getLatitude(), (float) $element->getGeo()->getLongitude(), $radius);

    if ($element->getId()) $qb->field('id')->notIn($element->getNonDuplicatesIds());
    if (!$includeDeleted) $qb->field('moderationState')->notEqual(ModerationState::PotentialDuplicate);

    return $qb->sortMeta('score', 'textScore')
              ->hydrate($hydrate)->getQuery()->execute()->toArray();
  }

  public function findWhithinBoxes($bounds, $request, $getFullRepresentation, $isAdmin = false)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    $status = ($request->get('pendings') === "false") ? ElementStatus::AdminValidate : ElementStatus::PendingModification;
    $this->filterVisibles($qb, $status);

    // get elements within box
    foreach ($bounds as $key => $bound)
      if (count($bound) == 4)
        $qb->addOr($qb->expr()->field('geo')->withinBox((float) $bound[1], (float) $bound[0], (float) $bound[3], (float) $bound[2]));

    if ($request) $this->filterWithRequest($qb, $request);
    $this->selectJson($qb, $getFullRepresentation, $isAdmin);

    // execute request
    $results = $this->queryToArray($qb);

    return $results;
  }

  public function findDuplicatesNodes($limit = null, $getCount = null)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');
    $qb->field('isDuplicateNode')->equals(true);
    if ($getCount) $qb->count();
    else {
      $qb->field('lockUntil')->lte(time());
      if ($limit) $qb->limit($limit);
    }
    return $qb->getQuery()->execute();
  }

  public function findElementsWithText($text, $fullRepresentation = true, $isAdmin = false)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    $this->queryText($qb, $text)->sortMeta('score', 'textScore');
    $this->filterVisibles($qb);

    $this->selectJson($qb, $fullRepresentation, $isAdmin);

    return $this->queryToArray($qb);
  }

  public function findPendings($getCount = false)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    $qb->field('status')->in(array(ElementStatus::PendingAdd,ElementStatus::PendingModification));
    if ($getCount) $qb->count();

    return $qb->getQuery()->execute();
  }

  public function findModerationNeeded($getCount = false, $moderationState = null)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    if ($moderationState != null) $qb->field('moderationState')->equals($moderationState);
    else $qb->field('moderationState')->notIn([ModerationState::NotNeeded]);
    $qb->field('status')->gte(ElementStatus::PendingModification);

    if ($getCount) $qb->count();

    return $qb->getQuery()->execute();
  }

  public function findValidated($getCount = false)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    $qb->field('status')->gt(ElementStatus::PendingAdd)->field('status')->notEqual(ElementStatus::DynamicImport);
    if ($getCount) $qb->count();

    return $qb->getQuery()->execute();
  }

  public function findVisibles($getCount = false, $excludeImported = false)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    $qb = $this->filterVisibles($qb);
    if ($excludeImported) $qb->field('status')->notEqual(ElementStatus::DynamicImport);
    if ($getCount) $qb->count();

    return $qb->getQuery()->execute();
  }

  public function findAllPublics($getFullRepresentation, $isAdmin, $request = null)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    $qb = $this->filterVisibles($qb);
    $qb->field('moderationState')->equals(ModerationState::NotNeeded);

    if ($request) $this->filterWithRequest($qb, $request);
    $this->selectJson($qb, $getFullRepresentation, $isAdmin);

    return $this->queryToArray($qb);
  }

  public function findAllElements($limit = null, $skip = null, $getCount = false)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    if ($limit) $qb->limit($limit);
    if ($skip) $qb->skip($skip);
    if ($getCount) $qb->count();

    return $qb->getQuery()->execute();
  }

  private function queryToArray($qb)
  {
    return $qb->hydrate(false)->getQuery()->execute()->toArray();
  }

  private function filterWithRequest($qb, $request)
  {
    $categoriesIds = $request->get('categories');
    if ($categoriesIds) {
      if (!is_array($categoriesIds)) $categoriesIds = explode(',' , $categoriesIds);
      $categoriesIds = array_map(function($el) { return (float) $el; }, $categoriesIds);
      $qb->field('optionValues.optionId')->in($categoriesIds);
    }

    if ($request->get('excludeExternal')) $qb->field('status')->notEqual(ElementStatus::DynamicImport);

    $stampsIds = $request->get('stampsIds');
    if ($stampsIds) {
      if (!is_array($stampsIds)) $stampsIds = explode(',' , $stampsIds);
      $qb->field('stamps.id')->in($stampsIds);
    }

    $limit = $request->get('limit');
    if ($limit && $limit > 0) $qb->limit($limit);
  }

  private function queryText($qb, $text)
  {
    $config = $this->getDocumentManager()->getRepository('BiopenCoreBundle:Configuration')->findConfiguration();
    $text = $text . ' --' . str_replace(',', ' --', $config->getSearchExcludingWords());
    return $qb->text($text)->language('fr');
  }

  private function filterVisibles($qb, $status = ElementStatus::PendingModification)
  {
    // fetching pendings and validated
    $qb->field('status')->gte($status);
    // removing element withtout category or withtout geolocation
    $qb->field('moderationState')->notIn(array(ModerationState::GeolocError, ModerationState::NoOptionProvided));
    return $qb;
  }

  private function selectJson($qb, $getFullRepresentation, $isAdmin)
  {
    // get json representation
    if ($getFullRepresentation == 'true')
    {
      $qb->select(['baseJson', 'privateJson']);
      if ($isAdmin) $qb->select('adminJson');
    }
    else
    {
      $qb->select('compactJson');
    }
  }

  public function findElementsOwnedBy($userEmail)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    $qb->field('userOwnerEmail')->equals($userEmail);
    $qb->field('status')->notEqual(ElementStatus::ModifiedPendingVersion);
    $qb->sort('updatedAt', 'DESC');
    return $qb->getQuery()->execute();
  }

  public function findWithinCenterFromDate($lat, $lng, $distance, $date, $limit = null)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');
    $radius = $distance / 110;
    $qb->field('geo')->withinCenter((float)$lat, (float)$lng, $radius);
    $qb->field('createdAt')->gt($date);
    $qb = $this->filterVisibles($qb);
    if ($limit) $qb->limit($limit);
    return $qb->getQuery()->execute();
  }

  public function findStampedWithId($stampId)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');
    $qb->field('stamps.id')->in(array((float) $stampId));
    $qb->select('id');
    return $this->queryToArray($qb);
  }

  public function findPotentialDuplicateOwner($element)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');
    $qb->field('potentialDuplicates')->includesReferenceTo($element);
    return $qb->getQuery()->execute();
  }

  public function findOriginalElementOfModifiedPendingVersion($element)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');
    $qb->field('modifiedElement')->references($element);
    return $qb->getQuery()->getSingleResult();
  }

  public function findDataCustomProperties()
  {
    return array_merge($this->findPublicCustomProperties(), $this->findPrivateCustomProperties());
  }

  public function findPublicCustomProperties()
  {
    return $this->findProperties('this.data');
  }

  public function findPrivateCustomProperties()
  {
    return $this->findProperties('this.privateData');
  }

  private function findProperties($rootPath = 'this')
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element')
      ->map('function() { for (var key in ' . $rootPath . ') { emit(key, null); } }')
      ->reduce('function(k, vals) { return null; }');
    return array_map(function($array) { return $array['_id']; }, $qb->getQuery()->execute()->toArray());
  }

  public function findAllCustomProperties($onlyPublic = false)
  {
    $dataProperties = $onlyPublic ? $this->findPublicCustomProperties() : $this->findDataCustomProperties();
    $allProperties = [];
    foreach ($dataProperties as $prop) {
        $allProperties[] = $prop;
    }

    $formProperties = $this->findFormProperties();
    return array_merge($allProperties, $formProperties);
  }

  public function findFormProperties()
  {
    $formProperties = [];
    $propTypeToIgnore = ['separator', 'header', 'address', 'title', 'email', 'taxonomy', 'openhours'];
    $config = $this->getDocumentManager()->getRepository('BiopenCoreBundle:Configuration')->findConfiguration();
    foreach ($config->getElementFormFields() as $key => $field) {
      if (property_exists($field, 'name') && !in_array($field->type, $propTypeToIgnore))
        $formProperties[] = $field->name;
    }
    return $formProperties;
  }

  public function findDeletedElementsByImportIdCount()
  {
    $builder = $this->createAggregationBuilder('BiopenGeoDirectoryBundle:Element');
    $builder
      ->match()
        ->field('status')->lte(ElementStatus::AdminRefused)
      ->group()
          ->field('_id')
          ->expression('$source')
          ->field('count')
          ->sum(1)
      ;
    $queryResult = $builder->execute();
    $result = [];
    foreach ($queryResult as $key => $value) {
      $result[$value['_id']['$id']] = $value['count'];
    }
    return $result;
  }
}


