<?php

namespace App\Controller\Admin;

use Sonata\AdminBundle\Controller\CRUDController as Controller;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use App\Document\ElementStatus;

class OptionAdminController extends Controller
{
    public function listAction()
    {
        return $this->redirectToRoute('admin_biopen_geodirectory_category_list');
    }
}