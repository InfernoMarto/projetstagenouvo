<?php
/**
 * @Author: Sebastian Castro
 * @Date:   2017-03-28 15:29:03
 * @Last Modified by:   Sebastian Castro
 * @Last Modified time: 2017-08-22 11:54:45
 */
namespace Biopen\CoreBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Biopen\CoreBundle\Admin\FeatureConfigurationAdmin;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class InteractionConfigurationAdmin extends FeatureConfigurationAdmin
{
    protected function configureFormFields(FormMapper $formMapper)
    {
        parent::configureFormFields($formMapper);
        $formMapper
            ->add('allow_role_anonymous_with_mail', CheckboxType::class, ['required'=>false, 'label' => "Autoriser Anonymes avec Mail"]);
    }
}