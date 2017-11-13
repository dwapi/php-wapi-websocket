<?php
namespace Wapi\Daemon\Websocket;

use Wapi\ErrorHandler;
use Wapi\Exception\SiteInaccessible;
use Wapi\Exception\WapiException;
use Ratchet\ConnectionInterface;
use Wapi\Daemon\Websocket\ServiceManager;
use Wapi\Protocol\Protocol;

class ClientManager extends \Wapi\ClientManager {
  
  /**
   * @var User[]
   */
  public $users;
  
  /**
   * @var \Wapi\Daemon\Websocket\Client[]
   */
  public $clients;
  
  /**
   * @var Site[]
   */
  public $sites;
  
  public function __construct() {
    parent::__construct();
    $this->users = [];
    $this->sites = [];
  }
  
  public function siteAdd(Site $site) {
    $loop = ServiceManager::loop();
    $site->processTimer = $loop->addPeriodicTimer(1/$site->rps, [$site, 'processMessageQueue']);
    $site->purgeTimer = $loop->addPeriodicTimer(2, [$site, 'purgeTimedoutMessages']);
    return  $this->sites[$site->id()] = $site;
  }
  
  public function siteRemove(Site $site) {
    $loop = ServiceManager::loop();
    $loop->cancelTimer($site->processTimer);
    $loop->cancelTimer($site->purgeTimer);
    unset($this->sites[$site->id()]);
  }
  
  public function getSiteByToken($token) {
    return !empty($this->sites[$token]) ? $this->sites[$token] : NULL;
  }
  
  public function userAdd(User $user) {
    if($user) {
      $this->users[$user->token] = $user;
    }
  }
  
  public function userRemove(User $user = NULL) {
    if($user) {
      $user_id = $user->id();
      foreach($this->clients AS $i => $client) {
        if($client->user && $client->user->id() == $user_id) {
          $this->clientRemove($client);
        }
      }
      $site = $user->site;
      
      $site->purgeUserMessages($user);
  
      $path = '/';
      $method = 'user_disconnect';
      $payload = NULL;
      $secret = $site->site_secret;
      $user_token = $user->token;
  
      $additional = [
        'path' => $path,
      ];
  
      $body = Protocol::buildMessage($secret, $method, $payload, $additional);
      
      $user->site->send('session', $user_token, $body)->then(NULL, function(WapiException $e){
        ErrorHandler::logException($e);
      });
      
      $this->users[$user_id] = NULL;
      unset($this->users[$user_id]);
    }
  }
  
  public function clientRemove(\Wapi\Client $client = NULL) {
    if($client && $existing_client = $this->clients[$client->id()]) {
      /** @var \Wapi\Daemon\Websocket\Client $client */
      $user = $client->user;
      if($user && !$this->getUserClients($user)) {
        $that = $this;
        ServiceManager::loop()->addTimer(15, function() use ($that, $user) {
          if(!$that->getUserClients($user)) {
            $that->userRemove($user);
          }
        });
      }
      parent::clientRemove($client);
    }
  }
  
  public function getUserByToken($token, $by_public_token = FALSE) {
    $user = NULL;
    
    foreach($this->users AS $user_token => $existing_user) {
      if($by_public_token) {
        if($existing_user->public_token == $token) {
          $user = $existing_user;
        }
      } else {
        if($user->token == $token) {
          $user = $existing_user;
        }
      }
    }
    
    return $user;
  }
  
  /**
   * @param \Wapi\Daemon\Websocket\User $user
   * @return \Wapi\Daemon\Websocket\Client[]
   */
  public function getUserClients(User $user = NULL) {
    $clients = [];
    if($user) {
      foreach ($this->clients AS $i => $client) {
        if($client->user && $client->user->id() == $user->id()) {
          $clients[$client->id()] = $client;
        }
      }
    }
    return $clients;
  }
  
  /**
   * @param Site $site
   * @param string $group_category
   * @param array $groups
   * @return User[]
   */
  public function getUsersByGroups($site, $group_category, $groups) {
    $users = [];
    
    foreach($this->users AS $user) {
      if($user->site->id() == $site->id()) {
        foreach($groups AS $group) {
          if($user->hasGroup($group_category, $group)) {
            $users[$user->id()] = $user;
          }
        }
      }
    }
    
    return $users;
  }
  
  public function getClientFromConn(ConnectionInterface $conn) {
    $client = NULL;
    
    foreach($this->clients AS $id => $existing_client) {
      if($existing_client->conn->contains($conn)) {
        $client = $existing_client;
      }
    }
    
    return $client;
  }
  
  public function broadcast($site, $path, $method, $data, $group_category, $groups) {
    $users = $this->getUsersByGroups($site, $group_category, $groups);
    $total_sent = 0;
    foreach($users AS $user) {
      $clients = $this->getUserClients($user);
      foreach($clients AS $client) {
        if($client->getPath() == $path) {
          $body = [
            'method' => $method,
            'data' => $data,
          ];
          $client->send($body);
          $total_sent++;
        }
      }
    }
    
    return $total_sent;
  }
  
}