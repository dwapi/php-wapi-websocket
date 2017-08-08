<?php
namespace Wapi\Daemon\Websocket\MessageHandler;

use Wapi\Daemon\Websocket\App;
use Wapi\Daemon\Websocket\ClientManager;
use Wapi\ErrorHandler;
use Wapi\Daemon\Websocket\ServiceManager;
use Wapi\Exception\AccessDenied;
use Wapi\Exception\ClockMismatch;
use Wapi\Exception\MessageInvalid;
use Wapi\Exception\MethodNotFound;
use Wapi\Exception\ParametersInvalid;
use Wapi\Daemon\Websocket\Site;
use Wapi\Daemon\Websocket\User;
use Wapi\Message;
use Wapi\MessageHandler\MessageHandlerBase;

class SiteMessageHandler extends MessageHandlerBase {
  
  /**
   * @var Site
   */
  public $site;
  
  static function getMethods() {
    return [
      'ping' => 'ping',
      'user_register' => 'userRegister',
      'user_remove' => 'userRemove',
      'user_groups' => 'userGroups',
      'user_send' => 'userSend',
      'broadcast' => 'broadcast'
    ];
  }
  
  static function isApplicable(Message $message) {
    if(!($message->client->getPath() == '/wapi/site')) {
      return FALSE;
    }
    return TRUE;
  }
  
  public function access() {
    $client_manager = ServiceManager::clientManager();
    $sites = $client_manager->sites;
    
    if(!($this->message->method == 'ping') && !$this->message->verifyTimestamp()) {
      throw new ClockMismatch();
    }
  
    $found = FALSE;
    foreach($sites AS $site) {
      /** @var Site $site */
      if($this->message->verifyCheck($site->site_secret)) {
        $found = $site;
      }
    }
    
    if(!$found) {
      throw new AccessDenied();
    }
    
    $this->site = $found;
  }
  
  public function ping() {
    return time();
  }
  
  public function userRegister() {
    $client_manager = ServiceManager::clientManager();
    $data = $this->message->data;
    $site = $this->site;
    
    if($site && !empty($data['private_user_token'])) {
      $language = !empty($data['language']) ? $data['language'] : 'en';
      if(!empty($client_manager->users[$data['private_user_token']])) {
        $user = $client_manager->users[$data['private_user_token']];
        $user->language = $language;
      } else {
        $user = new User($site, $data['private_user_token'], $language);
      }
      $client_manager->userAdd($user);
      $response = [
        'user_public_token' => $user->public_token,
      ];
      return $response;
    } else {
      throw new ParametersInvalid();
    }
  }
  
  public function userSend() {
    $client_manager = ServiceManager::clientManager();
    $data = $this->message->data;
    if(empty($data['method']) || empty($data['user_token']) || empty($data['path'])) {
      throw new ParametersInvalid();
    }
    
    $path = $data['path'];
    $page = empty($data['page']) ? NULL : $data['page'];
    
    $user = $client_manager->getUserByToken($data['user_token']);
    $clients = $client_manager->getUserClients($user);
    foreach($clients AS $client) {
      if(($client->getPath() == $path) && (!$page || ($client->page == $page))) {
        $body = [
          'method' => $data['method'],
          'data' => isset($data['data']) ? $data['data'] : NULL,
        ];
        $client->send($body);
      }
    }
  }
  
  public function broadcast() {
    $client_manager = ServiceManager::clientManager();
    $data = $this->message->data;
    if(empty($data['method']) || empty($data['path'])) {
      throw new ParametersInvalid();
    }
  
    $path = $data['path'];
    $page = empty($data['page']) ? NULL : $data['page'];
  
    $user = $client_manager->getUserByToken($data['user_token']);
    $clients = $client_manager->getUserClients($user);
    foreach($clients AS $client) {
      if(($client->getPath() == $path) && (!$page || ($client->page == $page))) {
        $body = [
          'method' => $data['method'],
          'data' => isset($data['data']) ? $data['data'] : NULL,
        ];
        $client->send($body);
      }
    }
  }
  
}