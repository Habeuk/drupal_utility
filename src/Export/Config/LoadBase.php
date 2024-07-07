<?php

namespace Stephane888\DrupalUtility\Export\Config;

use Drupal\Core\Controller\ControllerBase;
use Drupal\export_import_entities\Services\ThirdPartySettings;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Serialization\Yaml;
use Stephane888\Debug\debugLog;

/**
 * Contient les fonction de bases.
 *
 * @author stephane
 *        
 */
class LoadBase extends ControllerBase {
  /**
   *
   * @var string
   */
  protected static $preffix = [];
  
  /**
   * Permet d'exporter toutes les entités en relation avec le modeles fournit :
   * Example : 1
   * $BundleEntityType = blocks_contents_type
   * $entiy_type_id = blocks_contents
   * $bundle = clothings_hero
   * Example : 2
   * $BundleEntityType = node_type
   * $entiy_type_id = node
   * $bundle = article
   */
  public function generateAllConfigAboutEntity($entiy_type_id, $bundle, $BundleEntityType = null, $id = null) {
    /**
     *
     * @var \Drupal\Core\Config\Entity\ConfigEntityType $entityTypeDefinition
     */
    // Cas des entités avec bundle.
    if ($BundleEntityType) {
      $entityTypeDefinition = $this->entityTypeManager()->getDefinition($BundleEntityType);
      $name = $entityTypeDefinition->getConfigPrefix() . '.' . $bundle;
      $this->getConfigFromName($name);
      $idTranslation = 'language.content_settings.' . $entiy_type_id . '.' . $bundle;
      $this->getConfigFromName($idTranslation);
      
      $this->getFields($entiy_type_id, $bundle);
      self::loadConfigs($entiy_type_id . '.' . $bundle, 'entity_form_display');
      self::loadConfigs($entiy_type_id . '.' . $bundle, 'entity_form_mode');
      self::loadConfigs($entiy_type_id . '.' . $bundle, 'entity_view_display');
    }
    else {
      /**
       *
       * @var \Drupal\Core\Config\Entity\ConfigEntityType $entityTypeDefinition
       */
      $entityTypeDefinition = $this->entityTypeManager()->getDefinition($entiy_type_id);
      if ($entityTypeDefinition instanceof \Drupal\Core\Config\Entity\ConfigEntityType) {
        if (!$id) {
          throw new \Exception(" Pour l'entite ($entiy_type_id) de configuration l'id doit etre definit ");
        }
        $name = $entityTypeDefinition->getConfigPrefix() . '.' . $id;
        $this->getConfigFromName($name);
        // il faudra peut etre gerer la traduction.
        
        /**
         * Les entités de configurations n'ont pas de champs.
         * Mais il faut essayer de charger la configuration de l'entite de
         * content issue de BundleOf.
         */
        $entity_content_id = $this->entityTypeManager()->getStorage($entiy_type_id)->getEntityType()->getBundleOf();
        if ($entity_content_id) {
          $this->generateAllConfigAboutEntity($entity_content_id, $id, $entiy_type_id);
        }
      }
      else {
        // Ces entites n'ont pas de données de configuration à ce niveau. Ils
        // sont fournir uniquement à partir d'un modele ou d'une configuration,
        // mais on peut en surcharger les configurations ( formDisplays et
        // viewDisplays ) qui en resulte.
        $this->getFields($entiy_type_id, $bundle);
        self::loadConfigs($entiy_type_id . '.' . $bundle, 'entity_form_display');
        self::loadConfigs($entiy_type_id . '.' . $bundle, 'entity_form_mode');
        self::loadConfigs($entiy_type_id . '.' . $bundle, 'entity_view_display');
      }
    }
  }
  
  /**
   * Permet d'exporter les configurations ajouter via l'interface utilisateur.
   * example :
   * $entiy_type_id = commerce_product_variation
   * $bundle = vetements
   *
   * @param string $entiy_type_id
   * @param string $bundle
   */
  public function getFields($entiy_type_id, $bundle) {
    $queryField = $this->entityTypeManager()->getStorage('field_config')->getQuery();
    $queryField->accessCheck(TRUE);
    $queryField->condition('entity_type', $entiy_type_id);
    $queryField->condition('bundle', $bundle);
    $ids = $queryField->execute();
    $this->getConfigFields($ids);
  }
  
  /**
   * Permet de preparer les données pour l'export des champs.
   *
   * @param array $ids
   */
  public function getConfigFields(array $ids) {
    foreach ($ids as $id) {
      $keys = explode(".", $id);
      if (isset($keys[2]))
        $this->getConfigField($keys[0], $keys[1], $keys[2]);
      else {
        $this->messenger()->addWarning(" Les champs doivent contenir : 'entity_type','bundle' et 'field_name' ");
      }
    }
  }
  
  /**
   * Permet de recuperer les configurations d'un champs.
   *
   * @param string $entity_type
   * @param string $bundle
   * @param string $fieldName
   */
  public function getConfigField($entity_type, $bundle, $fieldName) {
    /**
     *
     * @var \Drupal\field\Entity\FieldConfig $FieldConfig
     */
    $FieldConfig = $this->entityTypeManager()->getStorage('field_config')->load($entity_type . '.' . $bundle . '.' . $fieldName);
    if ($FieldConfig) {
      $definition = $this->entityTypeManager()->getDefinition('field_config');
      $name = $definition->getConfigPrefix() . '.' . $entity_type . '.' . $bundle . '.' . $fieldName;
      $this->getConfigFromName($name);
      $this->getConfig($FieldConfig->getDependencies());
    }
    
    /**
     *
     * @var \Drupal\field\Entity\FieldStorageConfig $FieldStorageConfig
     */
    $FieldStorageConfig = $this->entityTypeManager()->getStorage('field_storage_config')->load($entity_type . '.' . $fieldName);
    if ($FieldStorageConfig) {
      $definition = $this->entityTypeManager()->getDefinition('field_storage_config');
      $this->getConfigFromName($definition->getConfigPrefix() . '.' . $entity_type . '.' . $fieldName);
      $this->getConfig($FieldStorageConfig->getDependencies());
    }
  }
  
  /**
   * Permet d'exporter 'entity_view_display', 'entity_form_display',
   * 'entity_form_mode', ...
   *
   * @param string $id
   */
  static public function loadConfigs($id, $entity_type_id = 'entity_view_display') {
    if (str_contains($id, ".")) {
      $preffix = self::getPreffix($entity_type_id);
      $query = \Drupal::entityTypeManager()->getStorage($entity_type_id)->getQuery();
      $query->condition('id', $id, 'CONTAINS');
      $ids = $query->execute();
      if (!empty($ids)) {
        /**
         *
         * @var \Drupal\export_import_entities\Services\LoadConfigs $LoadConfigs
         */
        $LoadConfigs = \Drupal::service("export_import_entities.export.form.LoadConfigs");
        /**
         *
         * @var \Drupal\export_import_entities\Services\ThirdPartySettings $ThirdPartySettings
         */
        $ThirdPartySettings = \Drupal::service("export_import_entities.export.third_party_settings");
        foreach ($ids as $id) {
          if (!$LoadConfigs->hasGenerate($id)) {
            /**
             *
             * @var \Drupal\Core\Entity\Entity\EntityFormDisplay $entity
             */
            $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($id);
            $LoadConfigs->getConfigFromName($preffix . '.' . $id);
            // On se rassure que ses dependances ont été cree ou on les crées.
            $confs = $entity->getDependencies();
            $LoadConfigs->getConfig($confs);
            // On genere egalement les configurations de third_party_settings;
            $ThirdPartySettings->getConfigFromThirdParty($entity);
          }
        }
      }
    }
    else {
      \Drupal::messenger()->addWarning("L'#ID '$id' doit etre contenir un point, i.e l'entity type et le bundle");
    }
  }
  
  /**
   *
   * @return string
   */
  static protected function getPreffix($entity_type_id = 'entity_view_display') {
    if (empty(self::$preffix[$entity_type_id])) {
      /**
       *
       * @var \Drupal\Core\Config\Entity\ConfigEntityType $definition
       */
      $definition = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
      self::$preffix[$entity_type_id] = $definition->getConfigPrefix();
    }
    return self::$preffix[$entity_type_id];
  }
  
  /**
   * Crrer la configuration à partir du nom donnée.
   * Recupere egalement les dependance incluse. ( si cela respecte la logique de
   * drupal ).
   *
   * @param string $name
   * @param $override //
   *        contient les données qui doivent etre surcharger.
   */
  public function getConfigFromName(string $name, array $override = [], $merge = true) {
    $this->initExportDir();
    if (empty(self::$configEntities[$name])) {
      $defaultConfs = $this->configStorage->read($name);
      
      if ($defaultConfs) {
        if (!empty($override)) {
          if ($merge) {
            $configs = NestedArray::mergeDeepArray([
              $defaultConfs,
              $override
            ]);
          }
          else {
            // On remplace les cles
            foreach ($override as $k => $value) {
              $defaultConfs[$k] = $value;
            }
            $configs = $defaultConfs;
          }
        }
        else
          $configs = $defaultConfs;
        
        $this->removeUuid($configs);
        $string = Yaml::encode($configs);
        if (self::$saveIt)
          debugLog::logger($string, $name . '.yml', false, 'file');
        self::$configEntities[$name] = [
          'status' => true,
          'value' => $string
        ];
        $this->loadConfigsViewTerms($name);
        // On essaie de charger les configurations requises.
        $this->loadDependancyConfig($name);
      }
    }
  }
  
  protected function loadConfigsViewTerms($name) {
    /**
     * On a un soucis avec les données contenus dans les termes de references.
     * On souhaite importter uniquement les affichages des termes taxo
     * utilisés.
     */
    if (str_contains($name, 'taxonomy.vocabulary.')) {
      $type = explode("taxonomy.vocabulary.", $name);
      /**
       *
       * @var \Drupal\export_import_entities\Services\LoadViewDisplays $LoadViewDisplays
       */
      if (!empty($type[1])) {
        $bundles = [
          $type[1] => $type[1]
        ];
        $LoadViewDisplays = \Drupal::service('export_import_entities.export.view.displays');
        $LoadViewDisplays->getDisplays('taxonomy_term', $bundles);
      }
    }
  }
  
  /**
   *
   * @param string $nameConf
   */
  private function loadDependancyConfig($nameConf) {
    $this->tryGetDependencies($nameConf);
    $entity_type = null;
    $ar = explode(".", $nameConf);
    if (!empty($ar[0]))
      $entity_type = $ar[0];
    // - Determiner ses dependances.
    if ($entity_type == 'field') {
      $fieldsKeys = explode(".", $nameConf);
      if (count($fieldsKeys) == 5) {
        $entity_type = $fieldsKeys[2];
        $bundle = $fieldsKeys[3];
        $fieldName = $fieldsKeys[4];
        /**
         *
         * @var \Drupal\field\Entity\FieldConfig $FieldConfig
         */
        $FieldConfig = $this->entityTypeManager()->getStorage('field_config')->load($entity_type . '.' . $bundle . '.' . $fieldName);
        $this->getConfig($FieldConfig->getDependencies());
        /**
         *
         * @var \Drupal\field\Entity\FieldStorageConfig $FieldStorageConfig
         */
        $FieldStorageConfig = $this->entityTypeManager()->getStorage('field_storage_config')->load($entity_type . '.' . $fieldName);
        $this->getConfig($FieldStorageConfig->getDependencies());
        //
      }
    }
    /**
     * on determine les dependences lies à la variation de produit, car
     * actuelement le code ne le permet pas de maniere automatique.
     */
    elseif (str_contains($nameConf, "commerce_product.commerce_product_type.")) {
      $defaultConfs = $this->configStorage->read($nameConf);
      foreach ($defaultConfs['variationTypes'] as $bundle) {
        // On charge le type de produit.
        $this->generateAllConfigAboutEntity("commerce_product", $bundle, "commerce_product_variation_type");
      }
    }
  }
  
  /**
   * à partir de toute configuration
   *
   * @param string $nameConf
   */
  protected function tryGetDependencies(string $nameConf) {
    $conf = \Drupal::config($nameConf);
    if ($conf) {
      $dependencies = $conf->get('dependencies');
      if (!empty($dependencies['config'])) {
        $this->getConfig($dependencies);
      }
    }
  }
  
  /**
   * Generre les fichiers de configuration de maniere recurssive.
   *
   * @param array $configs
   * @param array $configEntities
   */
  public function getConfig(array $configs, $entity = null) {
    $this->initExportDir();
    if (!empty($configs['config']))
      foreach ($configs['config'] as $config) {
        if (empty(self::$configEntities[$config])) {
          $name = $config;
          if ($this->filterConfig($config)) {
            $defaultConfs = $this->configStorage->read($name);
            if (str_contains($name, 'field.field')) {
              $this->addDefaultEncodeData($defaultConfs);
              $this->removeDefaultValue($defaultConfs);
            }
            $this->removeUuid($defaultConfs);
            $string = Yaml::encode($defaultConfs);
            if (self::$saveIt)
              debugLog::logger($string, $name . '.yml', false, 'file');
            self::$configEntities[$name] = [
              'status' => true,
              'value' => $string
            ];
            $this->loadConfigsViewTerms($name);
            // On essaie de charger les configurations requises.
            $this->loadDependancyConfig($name);
          }
          else {
            self::$configEntities[$name] = 'none';
          }
        }
      }
  }
  
  /**
   * --
   */
  protected function initExportDir() {
    if (!$this->configInit) {
      $settings = $this->getSettings();
      debugLog::$debug = false;
      $pathFull = null;
      if (!empty($settings['save_data'])) {
        $path = $this->ExtensionPathResolver->getPath('profile', $settings['save_data']);
        if ($path) {
          if ($settings['config_is_required'])
            $pathFull = $path . "/config/install";
          else
            $pathFull = $path . "/config/optional";
        }
      }
      if ($pathFull) {
        debugLog::$path = DRUPAL_ROOT . "/" . $pathFull;
      }
      else
        debugLog::$path = DRUPAL_ROOT . '/../sites_exports/default_model/config/install';
      $this->configInit = true;
    }
  }
  
  public function hasGenerate($k) {
    return isset(self::$configEntities[$k]) ? true : false;
  }
  
  /**
   * Chage une ou toute la config qui a été generée.
   *
   * @param string $k
   * @return NULL|array
   */
  public function getGenerate($k = null) {
    if ($k)
      return isset(self::$configEntities[$k]) ? self::$configEntities[$k] : null;
    else
      return self::$configEntities;
  }
}