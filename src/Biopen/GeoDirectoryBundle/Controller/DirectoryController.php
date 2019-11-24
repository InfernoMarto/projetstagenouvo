<?php

/**
 * This file is part of the GoGoCarto project.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) 2016 Sebastian Castro - 90scastro@gmail.com
 * @license    MIT License
 * @Last Modified time: 2018-07-08 12:11:20
 */

namespace Biopen\GeoDirectoryBundle\Controller;

use Biopen\CoreBundle\Controller\GoGoController;
use Symfony\Component\HttpFoundation\Request;

class DirectoryController extends GoGoController
{
  public function renderAction(Request $request)
  {
    // If Url is like annuaire/#fiche/element/50 with a jsonld header, return the element json instead

    $gogoConfig = $this->get('biopen.gogocartojs_service')->getConfig();
    return $this->render('BiopenGeoDirectoryBundle:directory:directory.html.twig', array('gogoConfig' => $gogoConfig));
  }
}
