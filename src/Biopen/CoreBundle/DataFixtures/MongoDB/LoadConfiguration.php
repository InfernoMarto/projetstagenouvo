<?php

namespace Biopen\CoreBundle\DataFixtures\MongoDB;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Biopen\CoreBundle\Document\Configuration;
use Biopen\CoreBundle\Document\Configuration\ConfigurationHome;
use Biopen\CoreBundle\Document\Configuration\ConfigurationUser;
use Biopen\CoreBundle\Document\Configuration\ConfigurationInfobar;
use Biopen\CoreBundle\Document\Configuration\ConfigurationMenu;
use Biopen\CoreBundle\Document\Configuration\ConfigurationApi;
use Biopen\CoreBundle\Document\AutomatedMailConfiguration;
use Biopen\CoreBundle\Document\FeatureConfiguration;
use Biopen\CoreBundle\Document\InteractionConfiguration;
use Biopen\GeoDirectoryBundle\Document\Coordinates;
use Biopen\CoreBundle\Document\TileLayer;

class LoadConfiguration implements FixtureInterface
{  
   public function load(ObjectManager $manager, $container = null, $configToCopy = null, $contribConfig = null)
   {  
      $configuration = new Configuration();   
      $tileLayersToCopy = null;
      
      if ($configToCopy) {
         foreach ($configToCopy as $key => $value)
         {
            if ($value && !in_array($key, ['id', "appSlug", 'appTags', 'defaultTileLayer', 'logo', 'logoInline', 'socialShareImage', 'favicon', 'tileLayers'])) {            
               // dealing with subobjects
               if (is_object($value)) { 
                  if (strpos($key, 'Feature') !== false) {
                     $object = property_exists($value, 'allow_role_anonymous_with_mail') ? new InteractionConfiguration() : new FeatureConfiguration();
                  } 
                  elseif (strpos($key, 'Mail') !== false) $object = new AutomatedMailConfiguration();
                  elseif ($key == "user") $object = new ConfigurationUser();
                  elseif ($key == "infobar") $object = new ConfigurationInfobar();
                  elseif ($key == "api") $object = new ConfigurationApi();
                  elseif ($key == "menu") $object = new ConfigurationMenu();
                  elseif ($key == "home") $object = new ConfigurationHome();

                  foreach ($value as $subkey => $subvalue) {
                     if ($subvalue && !in_array($subkey, ['id'])) {
                        $subkey = 'set' . ucfirst($subkey);
                        $object->$subkey($subvalue);
                     }                  
                  }
                  $value = $object;
               }
               $key = 'set' . ucfirst($key);
               $configuration->$key($value);
            }
         }
         $tileLayersToCopy = $configToCopy->tileLayers;
      }
      else
      {
         $configuration->setAppName("GoGoCarto");
         $configuration->setAppSlug("gogocarto");
         $configuration->setAppBaseline("Créez des cartes à GoGo");

         $activateHomePage;
         $configuration->setActivatePartnersPage(true);
         $configuration->setPartnerPageTitle("Partenaires");
         $configuration->setActivateAbouts(true);
         $configuration->setAboutHeaderTitle("A propos");

         // HOME
         $configuration->setActivateHomePage(true);
         $confHome = new ConfigurationHome();
         $confHome->setDisplayCategoriesToPick(false);
         $confHome->setAddElementHintText("Contribuez à enrichir la base de donnée !");
         $confHome->setSeeMoreButtonText("En savoir plus");
         $configuration->setHome($confHome);

         if ($container) {
            $confUser = new ConfigurationUser();
            $confUser->setLoginWithLesCommuns($container->getParameter('oauth_communs_id') != "disabled");
            $confUser->setLoginWithGoogle($container->getParameter('oauth_google_id') != "disabled");
            $confUser->setLoginWithFacebook($container->getParameter('oauth_facebook_id') != "disabled");
            $configuration->setUser($confUser);
         }

         // FEATURES
         $configuration->setFavoriteFeature(  new FeatureConfiguration(true, false, true, true, true));
         $configuration->setShareFeature(    new FeatureConfiguration(true, true,  true, true, true));
         $configuration->setExportIframeFeature(   new FeatureConfiguration(true, false, true, true, true));
         $configuration->setDirectionsFeature(new FeatureConfiguration(true, true,  true, true, true));      
         $configuration->setPendingFeature(   new FeatureConfiguration(true, false, true, true, true));
         $configuration->setSendMailFeature(   new InteractionConfiguration(true, false, true, true, true, true));
         $configuration->setCustomPopupFeature(   new FeatureConfiguration());
         $configuration->setStampFeature(   new FeatureConfiguration(true, false, true, true, true));   
         $configuration->setSearchPlaceFeature(    new FeatureConfiguration(true, true,  true, true, true));
         $configuration->setSearchGeolocateFeature(    new FeatureConfiguration(true, true,  true, true, true));
         $configuration->setLayersFeature(    new FeatureConfiguration(true, true,  true, true, true));
         $configuration->setMapDefaultViewFeature(    new FeatureConfiguration(true, true,  true, true, true));
         $configuration->setListModeFeature(    new FeatureConfiguration(true, true,  true, true, true));
         $configuration->setSearchElementsFeature(    new FeatureConfiguration(true, true,  true, true, true));

         // default bounds to France
         $configuration->setDefaultNorthEastBoundsLat(52);
         $configuration->setDefaultNorthEastBoundsLng(10);
         $configuration->setDefaultSouthWestBoundsLat(40);
         $configuration->setDefaultSouthWestBoundsLng(-5);

         // FORM
         $configuration->setElementFormGeocodingHelp("Ne mettez pas de ponctuation, les noms tout en majuscules ne sont pas reconnus non plus. Si la localisation ne fonctionne pas (il arrive que certaines adresses ne soient pas reconnues), entrez le nom de la ville/le village le plus proche, cliquez sur « Localiser », puis placer le point de localisation manuellement. Re-rentrez l’adresse complète dans la barre et passez à la suite du formulaire sans re-cliquer sur « localiser ».");

         // IMPORT
         $configuration->setFontImport('');
         $configuration->setIconImport('<link rel="stylesheet" href="../assets/fonts/fontawesome-5/css/all.css">');

         // STYLE
         $configuration->setMainFont('Ubuntu, sans-serif');
         $configuration->setTitleFont('Ubuntu, sans-serif');
         $configuration->setTextColor('#495057');
         $configuration->setPrimaryColor('#64d29b');
         $configuration->setBackgroundColor('#f4f4f4');
         $configuration->setTheme('default');
         $configuration->setCustomCSS('');
      }

      switch ($contribConfig) {
         case 'open':
            $configuration->setAddFeature(      new InteractionConfiguration(true, true,  true, true, true, true));
            $configuration->setEditFeature(     new InteractionConfiguration(true, true,  true, true, true, true));
            $configuration->setDeleteFeature(   new InteractionConfiguration(true, true, true, true, true, true));
            $configuration->setCollaborativeModerationFeature(   new InteractionConfiguration(false, false, false, false, false, false));
            $configuration->setDirectModerationFeature(          new InteractionConfiguration(true, true, true, true, true, true));
            $configuration->setReportFeature(   new FeatureConfiguration(false, false, false, false, false));
            break;
         case 'intermediate':
            $configuration->setAddFeature(      new InteractionConfiguration(true, true,  false, true, true, true));
            $configuration->setEditFeature(     new InteractionConfiguration(true, true,  false, true, true, true));
            $configuration->setDeleteFeature(   new InteractionConfiguration(true, false, false, false, false, true));
            $configuration->setCollaborativeModerationFeature(   new InteractionConfiguration(true, false, false, false, true, true));
            $configuration->setDirectModerationFeature(          new InteractionConfiguration(true, false, false, false, false, true));
            $configuration->setReportFeature(   new FeatureConfiguration(true, false, true, true, false));
            break;
         case 'closed':
            $configuration->setAddFeature(      new InteractionConfiguration(true, true,  false, false, false, true));
            $configuration->setEditFeature(     new InteractionConfiguration(true, true,  false, false, false, true));
            $configuration->setDeleteFeature(   new InteractionConfiguration(true, true,  false, false, false, true));
            $configuration->setCollaborativeModerationFeature(   new InteractionConfiguration(false, false, false, false, false, false));
            $configuration->setDirectModerationFeature(          new InteractionConfiguration(true, false, false, false, false, true));
            $configuration->setReportFeature(   new FeatureConfiguration(false, false, false, false, false));
            break;
      }

      $defaultTileLayerName = $configToCopy ? $configToCopy->defaultTileLayer : null;
      $defaultLayer = $this->loadTileLayers($manager, $tileLayersToCopy, $defaultTileLayerName);
      $configuration->setDefaultTileLayer($defaultLayer);

      $manager->persist($configuration);  
      $manager->flush();

      return $configuration;
   }

   public function loadTileLayers(ObjectManager $manager, $tileLayersToCopy = null, $defaultTileLayerName = null)
   {  
      $tileLayers = $tileLayersToCopy ? $tileLayersToCopy : array(
         array('name' => 'cartodb', 
              'url' => 'https://cartodb-basemaps-{s}.global.ssl.fastly.net/light_all/{z}/{x}/{y}.png', 
              'attribution' => '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="http://cartodb.com/attributions">CartoDB</a>'), 
         array('name' => 'hydda', 
              'url' => 'https://{s}.tile.openstreetmap.se/hydda/full/{z}/{x}/{y}.png', 
              'attribution' => 'Tiles courtesy of <a href="http://openstreetmap.se/" target="_blank">OpenStreetMap Sweden</a> &mdash; Map data &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'),
         array('name' => 'wikimedia', 
              'url' => 'https://maps.wikimedia.org/osm-intl/{z}/{x}/{y}.png',
              'attribution' => '<a href="https://wikimediafoundation.org/wiki/Maps_Terms_of_Use">Wikimedia</a> | Map data © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'),
         array('name' => 'lyrk' , 
              'url' => 'https://tiles.lyrk.org/ls/{z}/{x}/{y}?apikey=982c82cc765f42cf950a57de0d891076',
              'attribution' => '&copy Lyrk | Map data &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'), 
         array('name' => 'osmfr', 
              'url' => 'https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png',
              'attribution' => '&copy; Openstreetmap France | &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'),      
         array('name' => 'stamenWaterColor', 
              'url' => 'https://stamen-tiles-{s}.a.ssl.fastly.net/watercolor/{z}/{x}/{y}.png',
              'attribution' => 'Map tiles by <a href="http://stamen.com">Stamen Design</a>, <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a> &mdash; Map data &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'),      
      );

      $defaultTileLayer = null;
      $createdTileLayers = [];
      foreach ($tileLayers as $key => $layer) 
      {      
         $layer = (array) $layer;
         if (!in_array($layer['name'], $createdTileLayers)) {
            $tileLayer = new TileLayer();
            $tileLayer->setName($layer['name']); 
            $tileLayer->setUrl($layer['url']);
            $tileLayer->setAttribution($layer['url']); 
            $position = array_key_exists('position', $layer) ? $layer['position'] : $key;  
            $tileLayer->setPosition($key);   
            $manager->persist($tileLayer);
            $createdTileLayers[] = $layer['name'];

            if ($defaultTileLayer == null && $defaultTileLayerName == null) $defaultTileLayer = $tileLayer;
            if ($defaultTileLayer == null && $defaultTileLayerName != null && $layer['name'] == $defaultTileLayerName) $defaultTileLayer = $tileLayer;
         }
      };
      return $defaultTileLayer;
   }
}