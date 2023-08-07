<?php

namespace Stephane888\DrupalUtility;

use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;

class HttpResponse {
  /**
   * Cache is use by default
   *
   * @var array
   */
  public static $useCache = true;
  
  /**
   * Default cache
   *
   * @var array
   */
  public static $cacheMetadatas = [
    'max-age' => 600,
    'contexts' => [
      'url'
    ]
  ];
  
  /**
   *
   * @param Array|string $configs
   * @param number $code
   * @param string $message
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   * @see https://freelance-drupal.com/blog/mettre-en-cache-la-reponse-dun-callback-json-avec-drupal
   */
  static public function response($configs, int $code = null, $message = null, $cacheMetadatas = []) {
    if ($message) {
      $message = trim($message);
      if (strlen($message) > 150) {
        $message = substr($message, 0, 145) . ' ...';
      }
    }
    
    if (!is_string($configs))
      $configs = Json::encode($configs);
    if (self::$useCache) {
      $reponse = new JsonResponse();
    }
    else {
      if (!$cacheMetadatas)
        $cacheMetadatas = self::$cacheMetadatas;
      $reponse = new CacheableJsonResponse();
      $reponse->addCacheableDependency(CacheableMetadata::createFromRenderArray($cacheMetadatas));
      \Drupal::messenger()->addStatus('utilisation du cache');
    }
    if ($code)
      $reponse->setStatusCode($code, $message);
    $reponse->setContent($configs);
    // Si on utilise le protocole, http/2
    // on authorise les navigateurs à afficher "CustomStatusText"
    $reponse->headers->set('Access-Control-Expose-Headers', "CustomStatusText");
    // Le protocole http/2.0 ne supporte pas le message sattus, alors on
    // le transfert via un entete personnalisé "CustomStatusText".
    $reponse->headers->set('CustomStatusText', $message);
    return $reponse;
  }
  
  /**
   *
   * @deprecated
   * @param mixed $configs
   * @param int $code
   * @param string $message
   */
  static public function reponse($configs, int $code = null, $message = null) {
    return self::response($configs, $code, $message);
  }
  
}