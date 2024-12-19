<?php

namespace Stephane888\DrupalUtility\Export\Config;

use Drupal\Component\Serialization\Yaml;
use Stephane888\Debug\debugLog;

/**
 * Fournit des methodes permettant d'exporter les informations de configuration.
 *
 * @author stephane
 *        
 */
class ExportConfigs extends LoadBase {
  
  public function addConfig(string $name, $string) {
    $this->initExportDir();
    $configs = Yaml::decode($string);
    if (str_contains($name, 'field.field.') || str_contains($name, 'field.storage.')) {
      $this->addDefaultEncodeData($configs);
      $this->removeDefaultValue($configs);
    }
    $this->removeUuid($configs);
    $this->addConfigModules($configs, $name);
    $string = Yaml::encode($configs);
    
    if (self::$saveIt)
      debugLog::logger($string, $name . '.yml', false, 'file');
    self::$configEntities[$name] = [
      'status' => true,
      'value' => $string
    ];
  }
}