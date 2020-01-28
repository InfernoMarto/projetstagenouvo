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

namespace App\Controller;

use App\Controller\GoGoController;
use Symfony\Component\HttpFoundation\Request;

class DirectoryController extends GoGoController
{
  public function renderAction(Request $request)
  {
    $gogoConfig = $this->get('gogo.gogocartojs_service')->getConfig();
    return $this->render('BiopenGeoDirectoryBundle:directory:directory.html.twig', array('gogoConfig' => $gogoConfig));
  }
}
