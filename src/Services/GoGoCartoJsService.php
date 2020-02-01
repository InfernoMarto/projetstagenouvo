<?php

namespace App\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;

class GoGoCartoJsService
{
  public function __construct(DocumentManager $dm, TokenStorageInterface $securityContext, RouterInterface $router, SessionInterface $session, $baseProtocol)
  {
    $this->odm = $dm;
    $this->securityContext = $securityContext;
    $this->router = $router;
    $this->session = $session;
    $this->base_protocol = $baseProtocol;
  }

  public function getConfig()
  {
    $taxonomyRep = $this->odm->getRepository('App\Document\Taxonomy');
    $elementsRep = $this->odm->getRepository('App\Document\Element');

    $tileLayers = $this->odm->getRepository('App\Document\TileLayer')->findAll();

    $taxonomyJson = $taxonomyRep->findTaxonomyJson();

    $config = $this->odm->getRepository('App\Document\Configuration')->findConfiguration();

    $user = $this->securityContext->getToken() ? $this->securityContext->getToken()->getUser() : null;

    $roles = is_object($user) ? $user->getRoles() : [];
    $userGogocartoRole = is_object($user) && $user->isAdmin() ? 'admin' : (in_array('ROLE_USER', $roles) ? 'user' : 'anonymous');
    $userGogocartoRole = [$userGogocartoRole];
    $userEmail = is_object($user) ? $user->getEmail() : $this->session->get('userEmail');

    $allowedStamps = [];
    if ($config->getStampFeature()->getActive())
    {
        $allowedStamps = is_object($user) ? $user->getAllowedStamps()->toArray() : [];
        foreach ($allowedStamps as $stamp) {
            $result = $elementsRep->findStampedWithId($stamp->getId());
            $elementIds = [];
            foreach ($result as $obj) $elementIds[] = $obj['_id'];
            $stamp->setElementIds($elementIds);
        }
    }

    $result = [
      "security" =>
      [
          "userRoles" => $userGogocartoRole,
          "userEmail" => $userEmail,
          "loginAction" => '$("#popup-login").openModal();'
      ],
      "language" => "fr",
      "translations" =>
      [
          "element" => $config->getElementDisplayName() ,
          "element.definite" => $config->getElementDisplayNameDefinite(),
          "element.indefinite" => $config->getElementDisplayNameIndefinite(),
          "element.plural" => $config->getElementDisplayNamePlural(),
          "collaborative.moderation.paragaphs" => $config->getCollaborativeModerationExplanations(),
      ],
      "menu" =>
      [
          "width" => $config->getMenu()->getWidth(),
          "smallWidthStyle" => $config->getMenu()->getSmallWidthStyle(),
          "showOnePanePerMainOption" => $config->getMenu()->getShowOnePanePerMainOption(),
          "showCheckboxForMainFilterPane" => $config->getMenu()->getShowCheckboxForMainFilterPane(),
          "showCheckboxForSubFilterPane" => $config->getMenu()->getShowCheckboxForSubFilterPane(),
          "displayNumberOfElementForEachCategory" => $config->getMenu()->getDisplayNumberOfElementForEachCategory(),
          "displayNumberOfElementRoundResults" => $config->getMenu()->getDisplayNumberOfElementRoundResults()
      ],
      "infobar" =>
      [
          "width" => $config->getInfobar()->getWidth(),
          "headerTemplate" => [
              "content" => $config->getInfobar()->getHeaderTemplate(),
              "isMarkdown" => $config->getInfobar()->getHeaderTemplateUseMarkdown()
          ],
          "bodyTemplate" => [
              "content" => $config->getInfobar()->getBodyTemplate(),
              "isMarkdown" => $config->getInfobar()->getBodyTemplateUseMarkdown()
          ]
      ],
      "map" =>
      [
          "defaultBounds" => $config->getDefaultBounds(),
          "defaultTileLayer" => $config->getDefaultTileLayer()->getName(),
          "tileLayers"  => $tileLayers  ,
          "saveViewportInCookies" => $config->getSaveViewportInCookies()  ,
          "saveTileLayerInCookies" => $config->getSaveTileLayerInCookies()  ,
          "useClusters" => $config->getMarker()->getUseClusters()
      ],
      "marker" =>
      [
          "displayPopup" => $config->getMarker()->getDisplayPopup(),
          "popupAlwaysVisible" => $config->getMarker()->getPopupAlwaysVisible(),
          "popupTemplate" => [
              "content" => $config->getMarker()->getPopupTemplate(),
              "isMarkdown" => $config->getMarker()->getPopupTemplateUseMarkdown()
          ]
      ],
      "theme" => $config->getTheme(),
      "colors" =>
      [
          "text" => $config->getTextColor() ,
          "primary" => $config->getPrimaryColor() ,

          // Optional colors
          "secondary" => $config->getDefaultSecondaryColor() ,
          "background" => $config->getDefaultBackgroundColor() ,
          "searchBar" => $config->getDefaultSearchBarColor() ,
          "disabled" => $config->getDefaultDisableColor(),
          "pending" => $config->getDefaultPendingColor() ,
          "contentBackground" => $config->getDefaultContentBackgroundColor() ,
          "textDark" => $config->getDefaultTextDarkColor() ,
          "textDarkSoft" => $config->getDefaultTextDarkSoftColor() ,
          "textLight" => $config->getDefaultTextLightColor() ,
          "textLightSoft" => $config->getDefaultTextLightSoftColor() ,
          "interactiveSection" => $config->getDefaultInteractiveSectionColor(),
          "contentBackgroundElementBody" => $config->getDefaultContentBackgroundElementBodyColor(), // by default calculated from contentBackground

          // Non implemented colors
          // infoBarHeader => undefined, // by default auto colored with main option color, except for transiscope theme
          // infoBarMenu => undefined,   // by default auto colored with main option color, except for transiscope theme


          // menuOptionHover => undefined, // by default calculated from contentBackground
          // lineBorder => undefined, // by default calculated from contentBackground

          // mapControlsBgd => undefined,
          // mapControls => undefined,
          // mapListBtn => undefined,

      ],
      "fonts" => [
          "mainFont" => $config->getMainFont() ,
          "titleFont" => $config->getTitleFont() ,
      ],
      "features" =>
      [
          "listMode" => $this->getConfigFrom($config->getListModeFeature()) ,
          "searchPlace" => $this->getConfigFrom($config->getSearchPlaceFeature()) ,
          "searchElements" => $this->getConfigFrom($config->getSearchElementsFeature(), 'gogo_api_elements_from_text') ,
          "searchGeolocate" => $this->getConfigFrom($config->getSearchGeolocateFeature()) ,
          "share" =>    $this->getConfigFrom($config->getShareFeature()) ,
          "report" =>   $this->getConfigFrom($config->getReportFeature(), 'gogo_report_error_for_element') ,
          "favorite" => $this->getConfigFrom($config->getFavoriteFeature()) ,
          "export" =>   $this->getConfigFrom($config->getExportIframeFeature()) ,
          "pending" =>  $this->getConfigFrom($config->getPendingFeature()) ,
          "directModeration" =>  $this->getConfigFrom($config->getDirectModerationFeature()) ,
          "moderation" =>  $this->getConfigFrom($config->getDirectModerationFeature(), 'gogo_resolve_reports_element') ,
          "elementHistory" => $this->getConfigFrom($config->getDirectModerationFeature()) ,
          "directions" => $this->getConfigFrom($config->getDirectionsFeature()) ,
          "layers" => $this->getConfigFrom($config->getLayersFeature()) ,
          "mapdefaultview" => $this->getConfigFrom($config->getMapDefaultViewFeature()) ,

          // overwrite roles so even if edit is just allowed to user or admin, an anonymous will see
          // the edit button in the element info menu
          "edit" =>     $this->getConfigFrom(
                          $config->getEditFeature(),
                          'gogo_element_edit',
                          $config->getEditFeature()->isOnlyAllowedForAdmin() ? [] : ["roles" => ['anonymous', 'user', 'admin']]
                     ) ,
          "delete" =>   $this->getConfigFrom($config->getDeleteFeature(), 'gogo_delete_element') ,

          "vote" =>     $this->getConfigFrom($config->getCollaborativeModerationFeature(), 'gogo_vote_for_element') ,
          "sendMail" => $this->getConfigFrom($config->getSendMailFeature(), 'gogo_element_send_mail') ,
          "stamp" => $this->getConfigFrom(
                  $config->getStampFeature(),
                  'gogo_element_stamp',
                  [ "options" => [ "allowedStamps" => $allowedStamps ] ]
              ) ,
          "customPopup" => $this->getConfigFrom(
                  $config->getCustomPopupFeature(),
                  null,
                  [ "options" => [
                      "text" => $config->getCustomPopupText(),
                      "showOnlyOnce" => $config->getCustomPopupShowOnlyOnce(),
                      "id" => $config->getCustomPopupId()
                  ] ]
              ),
      ],
      "data" =>
      [
          "taxonomy" => json_decode($taxonomyJson),
          "elements" => $this->getAbsolutePath('gogo_api_elements_index'),
          "requestByBounds" => true,
      ],
    ];

    if ($config->getTheme() == "transiscope")
    {
      $result["images"] = [
        "buttonOpenMenu" => $config->getFavicon() ? $config->getFavicon()->getImageUrl() : ($config->getLogo() ? $config->getLogo()->getImageUrl() : null)
      ];
    }

    return $result;
  }

  private function getConfigFrom($feature, $route = null, $overwrite = [])
  {
    if (!$feature) return null;
    $result = [];
    $result['active'] = array_key_exists('active', $overwrite) ? $overwrite['active'] : $feature->getActive();
    $result['inIframe'] = $feature->getActiveInIframe();

    if ($route == 'gogo_element_edit')
      $url = str_replace('fake', '', $this->getAbsolutePath('gogo_element_edit', ['id'=>'fake']));
    elseif ($route)
      $url = $this->getAbsolutePath($route);
    else
      $url = '';
    $result['url'] = $url;

    $result['roles'] = array_key_exists('roles', $overwrite) ? $overwrite['roles'] : $feature->getAllowedRoles();
    if (array_key_exists('options', $overwrite)) $result['options'] = $overwrite['options'];

    return $result;
  }

  private function getAbsolutePath($route, $params = [])
  {
    return $this->base_protocol . ':' . $this->router->generate($route, $params, UrlGeneratorInterface::NETWORK_PATH);
  }
}