<?php

namespace Stephane888\DrupalUtility\Export\Config;

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
    $this->removeUuid($configs);
    $string = Yaml::encode($configs);
    
    if (self::$saveIt)
      debugLog::logger($string, $name . '.yml', false, 'file');
    self::$configEntities[$name] = [
      'status' => true,
      'value' => $string
    ];
  }
}