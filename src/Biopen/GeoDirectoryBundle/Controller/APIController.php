<?php

namespace Biopen\GeoDirectoryBundle\Controller;

use Biopen\GeoDirectoryBundle\Document\ElementJsonOntology;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Biopen\CoreBundle\Controller\GoGoController;
use JMS\Serializer\SerializationContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Biopen\GeoDirectoryBundle\Services\GoGoCartoJsService;

class APIController extends GoGoController
{
  /* Retrieve elements via API, allow params are
  * @id
  * @limit
  * @excludeExternal -> exclude external sources in API
  * @bounds
  * @categories (ids)
  * @stamps (ids)
  * @ontology (gogofull, gogocompact or semantic) -> see ElementJsonOntology
  **/
  public function getElementsAction(Request $request, $id = null, $_format = 'json')
  {
    $em = $this->get('doctrine_mongodb')->getManager();     

    $jsonLdRequest = $this->isJsonLdRequest($request, $_format); 
    $token = $request->get('token');
    $ontology = $jsonLdRequest ? ElementJsonOntology::Semantic : ( $request->get('ontology') ? strtolower($request->get('ontology')) : ElementJsonOntology::Full );
    $elementId = $id ? $id : $request->get('id');     
    $config = $em->getRepository('BiopenCoreBundle:Configuration')->findConfiguration();  
    $protectWithToken = $config->getApi()->getProtectPublicApiWithToken();
    $apiUiUrl = $this->generateUrl('biopen_api_ui', [], UrlGeneratorInterface::ABSOLUTE_URL);

    if ($request->isMethod('POST')) // this kind of call is restricted with cross domain headers
    {
      $isAdmin = $this->isUserAdmin();
      $includePrivateFields = true;
    }
    elseif (!$protectWithToken || $token) // otherwise API can be protected by user token 
    {
      if ($protectWithToken)
      {
        $user = $em->getRepository('BiopenCoreBundle:User')->findOneByToken($token);
        if (!$user) {
          $response = "The token you provided does not correspond to any existing user. Please visit " . $apiUiUrl; 
          return $this->createResponse($response, $config);
        }
      }
      $isAdmin = false;
      $includePrivateFields = false;
    } 
    else
    {      
      $response = "You need to provide a token to access to this API. Please visit " . $apiUiUrl; 
      return $this->createResponse($response, $config);
    }    

    $elementRepo = $em->getRepository('BiopenGeoDirectoryBundle:Element');   

    if ($elementId) 
    {
      $element = $elementRepo->findOneBy(array('id' => $elementId));
      $elementsJson = $element ? $element->getJson($ontology, $includePrivateFields, $isAdmin) : null;
      if( $elementsJson && $ontology === ElementJsonOntology::Semantic ) {
          $elementsJson = $this->appendSemanticContextAndType($elementsJson);
      }
    }
    else 
    {
      if ($request->get('bounds'))
      {
        $boxes = [];
        $bounds = explode( ';' , $request->get('bounds'));
        foreach ($bounds as $key => $bound) $boxes[] = explode( ',' , $bound);
        $elementsFromDB = $elementRepo->findWhithinBoxes($boxes, $request, $ontology, $isAdmin);
      } 
      else
      {
        $elementsFromDB = $elementRepo->findAllPublics($ontology, $isAdmin, $request);
      }  
      $elementsJson = $this->encodeElementArrayToJsonArray($elementsFromDB, $ontology, $isAdmin, $includePrivateFields, $config);
    }   

    $status = 200;
    if (!$elementsJson)
    {
      $responseJson = '{ "error": "Element does not exists" }';
      $status = 500;
    }
    else if ($jsonLdRequest)
    {
      $responseJson = $elementsJson;
    }
    else
    {
      $responseJson = '{
        "licence": "' . $config->getDataLicenseUrl() . '",
        "ontology":"'. $ontology . '"';        

      if ($ontology === ElementJsonOntology::Compact )
      {
        $mapping = ['id', $config->getMarker()->getFieldsUsedByTemplate(), 'latitude', 'longitude', 'status', 'moderationState'];
        $responseJson .= ', "mapping":' . json_encode($mapping);
      }

      $responseJson .= ', "data":' . $elementsJson . '}';
    }

    // TODO count how much a user is using the API
    // $responseSize = strlen($elementsJson);
    // $date = date('d/m/Y'); 
    
    return $this->createResponse($responseJson, $config, $status);
  }    

  public function getTaxonomyAction(Request $request, $id = null, $_format = 'json')
  {
    $em = $this->get('doctrine_mongodb')->getManager();

    $optionId = $id ? $id : $request->get('id');
    $jsonLdRequest = $this->isJsonLdRequest($request, $_format);

    if ($optionId)
    {
      $serializer = $this->get('jms_serializer');
      $option = $em->getRepository('BiopenGeoDirectoryBundle:Option')->findOneBy(array('id' => $optionId));
      $serializationContext = $jsonLdRequest ? SerializationContext::create()->setGroups(['semantic']) : null;
      $dataJson = $serializer->serialize($option, 'json', $serializationContext);
      if ($jsonLdRequest) $dataJson = '[' . $dataJson . ']';
    }
    else
    {
      $dataJson = $em->getRepository('BiopenGeoDirectoryBundle:Taxonomy')->findTaxonomyJson($jsonLdRequest);
    }    

    if ($jsonLdRequest)
      $responseJson = '{
          "@context" : "https://rawgit.com/jmvanel/rdf-convert/master/pdcn-taxonomy/taxonomy.context.jsonld",
          "@graph"   :  '. $dataJson . '
        }';
    else
      $responseJson = $dataJson;

    $config = $em->getRepository('BiopenCoreBundle:Configuration')->findConfiguration();  
    return $this->createResponse($responseJson, $config);
  }

  public function getTaxonomyMappingAction(Request $request, $id = null, $_format = 'json')
  {
    $em = $this->get('doctrine_mongodb')->getManager();
    $options = $em->getRepository('BiopenGeoDirectoryBundle:Option')->findAll();
    $result = [];
    foreach ($options as $key => $option) {
      $result[$option->getId()] = $option;
    }

    $serializer = $this->get('jms_serializer');
    $responseJson = $serializer->serialize($result, 'json');

    $config = $em->getRepository('BiopenCoreBundle:Configuration')->findConfiguration();  
    return $this->createResponse($responseJson, $config);
  }

  private function isJsonLdRequest($request, $_format)
  {
    return $_format == 'jsonld' || $request->headers->get('Accept') == 'application/ld+json';
  }

  private function createResponse($text, $config, $status = 200)
  {
    $response = new Response($text, $status);
    if ($config->getApi()->getInternalApiAuthorizedDomains())
      $response->headers->set('Access-Control-Allow-Origin', $config->getApi()->getInternalApiAuthorizedDomains());
    $response->headers->set('Content-Type', 'application/json');
    return $response;
  }

  public function getElementsFromTextAction(Request $request)
  {
    $em = $this->get('doctrine_mongodb')->getManager();
    
    $isAdmin = $this->isUserAdmin();

    $elements = $em->getRepository('BiopenGeoDirectoryBundle:Element')->findElementsWithText($request->get('text'), true, $isAdmin);

    $elementsJson = $this->encodeElementArrayToJsonArray($elements, ElementJsonOntology::Full, $isAdmin, true);
    $responseJson = '{ "data":'. $elementsJson . ', "ontology" : "' . ElementJsonOntology::Full . '"}';
    
    $config = $em->getRepository('BiopenCoreBundle:Configuration')->findConfiguration();  
    return $this->createResponse($responseJson, $config);
  }

  private function isUserAdmin() 
  {
    $securityContext = $this->container->get('security.context');
    if ($securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED'))
    {
      $user = $securityContext->getToken()->getUser(); 
      $isAdmin = $user && $user->isAdmin();
      return $isAdmin;
    }
    return false;    
  }

  private function encodeElementArrayToJsonArray($array, $ontology, $isAdmin = false, $includePrivateFields = false, $config = null )
  {
    $elementsJson = '[';
    foreach ($array as $key => $value) 
    {
        switch($ontology) {
            case ElementJsonOntology::Full:
                $elementJson = $value['baseJson'];
                if ($includePrivateFields && $value['privateJson'] != '{}') {
                    $elementJson = substr($elementJson , 0, -1) . ',' . substr($value['privateJson'],1);
                }
                if ($isAdmin && $value['adminJson'] != '{}') {
                    $elementJson = substr($elementJson , 0, -1) . ',' . substr($value['adminJson'],1);
                }
                if (key_exists('score', $value)) {
                    // remove first '{'
                    $elementJson = substr($elementJson, 1);
                    $elementJson = '{"searchScore" : ' . $value['score'] . ',' . $elementJson;
                }
                break;

            case ElementJsonOntology::Compact:
                $elementJson = $value['compactJson'];
                break;

            case ElementJsonOntology::Semantic:
                $elementJson =  $value['semanticJson'];
                $elementJson = $this->appendSemanticContextAndType($elementJson);
                break;

            default:
                throw new \Exception('Unknown ontology : ' . $ontology);
        }
        $elementsJson .=  $elementJson .  ',';
    }   

    $elementsJson = rtrim($elementsJson,",") . ']'; 
    return $elementsJson;
  }

  public function getGoGoCartoJsConfigurationAction() 
  {
    $odm = $this->get('doctrine_mongodb')->getManager();
    $config = $odm->getRepository('BiopenCoreBundle:Configuration')->findConfiguration();

    $gogocartoConf = $this->get('biopen.gogocartojs_service')->getConfig();

    return $this->createResponse(json_encode($gogocartoConf), $config);
  }

  public function appendSemanticContextAndType($semanticJson)
  {
    $em = $this->get('doctrine_mongodb')->getManager();
    $config = $em->getRepository('BiopenCoreBundle:Configuration')->findConfiguration();

    return '{
      "@context" : "' . $config->getElementFormSemanticContext() . '",
      "@type" : "' . $config->getElementFormSemanticType() . '",
      ' . substr($semanticJson, 1);
  }
}
  