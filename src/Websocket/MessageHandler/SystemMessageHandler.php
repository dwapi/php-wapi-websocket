<?php
namespace Wapi\Daemon\Websocket\MessageHandler;

use Wapi\Daemon\Websocket\App;
use Wapi\Daemon\Websocket\ClientManager;
use Wapi\Daemon\Websocket\ServiceManager;
use Wapi\ErrorHandler;
use Wapi\Exception\AccessDenied;
use Wapi\Exception\ClockMismatch;
use Wapi\Exception\MessageInvalid;
use Wapi\Exception\MethodNotFound;
use Wapi\Exception\ParametersInvalid;
use Wapi\Message;
use Wapi\Daemon\Websocket\Site;
use Wapi\Daemon\Websocket\Session;
use Wapi\MessageHandler\MessageHandlerBase;

class SystemMessageHandler extends MessageHandlerBase {
  
  public function getMethods() {
    $methods = [];
  
    $methods['status'] = [
      'callback' => [$this, 'status'],
      'schema' => [],
    ];
  
    $methods['allowed_sites'] = [
      'callback' => [$this, 'allowedSites'],
      'schema' => [
        'sites' => [
          'type' => 'assoc',
          'multi' => TRUE,
          'required' => TRUE,
          'children' => [
            'site_key' => [
              'type' => 'string',
              'required' => TRUE,
            ],
            'site_token' => [
              'type' => 'string',
              'required' => TRUE,
            ],
            'address' => [
              'type' => 'string',
              'required' => TRUE,
            ],
            'rps' => [
              'type' => 'integer',
              'required' => TRUE,
            ],
          ],
        ],
      ],
    ];
  
    $methods['clear_logs'] = [
      'callback' => [$this, 'clearLogs'],
      'schema' => [],
    ];
    
    return $methods;
  }
  
  static function isApplicable(Message $message) {
    if(!($message->client->getRequestPath() == '/wapi/system')) {
      return FALSE;
    }
    return TRUE;
  }
  
  public function access() {
    $secret = ServiceManager::app()->server_secret;
  
    if(!$this->message->verifyTimestamp()) {
      throw new ClockMismatch();
    }
  
    if(!$this->message->verifyCheck($secret)) {
      throw new AccessDenied();
    }
  }
  
  public function status() {
    $client_manager = ServiceManager::clientManager();
    $cpu = static::getCpuUsage();
    
    $data = [
      'pid' => getmypid(),
      'memory_usage' => memory_get_usage(),
      'peak_memory_usage' => memory_get_peak_usage(),
      'cpu' => $cpu,
      'uptime' => ServiceManager::app()->uptime(),
      'mps' => ceil(array_sum(ServiceManager::app()->mpm)/10),
      'client_count' => count($client_manager->clients),
      'session_count' => count($client_manager->sessions),
      'sites' => [],
      'errors' => [],
    ];
    
    $error_handler = ErrorHandler::getInstance();
    if($error_handler) {
      $data['errors'] = $error_handler->getErrors();
    }
    
    foreach($client_manager->sites AS $site) {
      $session_count = count($site->sessions);
      $data['sites'][] = [
        'base_url' => $site->base_url,
        'sessions' => $session_count,
      ];
    }
    
    return $data;
  }
  
  public static function getCpuUsage() {
    static $last_cpu_usage = NULL;
    static $last_time = NULL;
    
    $pid = getmypid();
    $now = microtime(TRUE);
    $str = (string) shell_exec("cat /proc/$pid/stat");
    $array = explode(" ", $str);
    $utime = $array[13];
    $stime = $array[14];
    
    $cpu = 0;
    $current_cpu_usage = ($utime + $stime) / 100;
    
    if($last_time) {
      $cpu = ($current_cpu_usage - $last_cpu_usage) / (($now - $last_time));
    }
    
    $cpu = floor($cpu * 1000) / 10;
  
    $last_time = $now;
    $last_cpu_usage = $current_cpu_usage;
    
    return $cpu;
  }
  
  public function allowedSites($data) {
    $client_manager = ServiceManager::clientManager();
    $existing = &$client_manager->sites;
    $allowed_sites = $data['sites'];
    $allowed_keys = array_map(function($element){ return $element['site_key']; }, $allowed_sites);
    foreach($existing AS $token => $site) {
      if(!in_array($site->site_key, $allowed_keys)) {
        $client_manager->siteRemove($site);
      }
    }
    
    foreach($allowed_sites AS $allowed_site) {
      if(!($site = $client_manager->getSite($allowed_site['site_key']))) {
        $site = new Site($allowed_site['site_key'], $allowed_site['site_token'], $allowed_site['address'], $allowed_site['rps']);
        $client_manager->siteAdd($site);
      } else {
        $site->base_url = $allowed_site['address'];
        if($site->rps != $allowed_site['rps']) {
          $loop = ServiceManager::loop();
          $loop->cancelTimer($site->processTimer);
          $site->rps = $allowed_site['rps'];
          $site->processTimer = $loop->addPeriodicTimer(1/$site->rps, [$site, 'processMessageQueue']);
        }
      }
    }
  }
  
  public function clearLogs() {
    if($error_handler = ErrorHandler::getInstance()) {
      $error_handler->clearErrors();
    }
  }
  
}