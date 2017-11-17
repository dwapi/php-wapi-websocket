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
   * @var Session[]
   */
  public $sessions = [];
  
  /**
   * @var Session[]
   */
  public $sessionsPublic = [];
  
  /**
   * @var \Wapi\Daemon\Websocket\Client[]
   */
  public $clients = [];
  
  /**
   * @var Site[]
   */
  public $sites = [];
  
  /**
   * @var \Wapi\Daemon\Websocket\GroupsManager
   */
  public $groupsManager;
  
  public function __construct() {
    parent::__construct();
    $this->groupsManager = ServiceManager::service('groups_manager', new GroupsManager());
  }
  
  /**
   * @return \Wapi\Daemon\Websocket\GroupsManager
   */
  public function getGroupsManager() {
    return $this->groupsManager;
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
  
  public function getSite($id) {
    return !empty($this->sites[$id]) ? $this->sites[$id] : NULL;
  }
  
  public function sessionAdd(Session $session) {
    $this->sessions[$session->token] = $session;
    $this->sessionsPublic[$session->public_token] = $session;
    $session->site->addSession($session);
  }
  
  public function sessionRemove(Session $session = NULL) {
    if($session) {
      foreach($session->getClients() AS $i => $client) {
        $this->clientRemove($client);
      }
      $site = $session->site;
      
      $site->purgeSessionMessages($session);
  
      $path = '/';
      $method = 'session_disconnect';
      $payload = NULL;
      $secret = $site->site_secret;
      $session_token = $session->token;
  
      $additional = [
        'path' => $path,
      ];
  
      $body = Protocol::buildMessage($secret, $method, $payload, $additional);
      
      $session->site->send('session', $session_token, $body)->then(NULL, function(WapiException $e){
        ErrorHandler::logException($e);
      });
  
      unset($this->sessions[$session->token]);
      unset($this->sessionsPublic[$session->public_token]);
      $session->site->removeSession($session);
    }
  }
  
  public function clientRemove(\Wapi\Client $client = NULL) {
    if($client) {
      /** @var \Wapi\Daemon\Websocket\Client $client */
      $session = $client->session;
      if($session && !$session->getClients()) {
        $that = $this;
        ServiceManager::loop()->addTimer(15, function() use ($that, $session) {
          if(!$session->getClients()) {
            $that->sessionRemove($session);
          }
        });
      }
      parent::clientRemove($client);
    }
  }
  
  public function getSessionByToken($token, $by_public_token = FALSE) {
    if(!$by_public_token) {
      return !empty($this->sessions[$token]) ? $this->sessions[$token] : NULL;
    } else {
      return !empty($this->sessionsPublic[$token]) ? $this->sessionsPublic[$token] : NULL;
    }
  }
  
  public function broadcast($site, $path, $groups, $method, $data) {
    $sessions = $this->groupsManager->getSessions($site, $path, $groups);
    $total_sent = 0;
    foreach($sessions AS $session) {
      foreach($session->getClients() AS $client) {
        if($client->hasPath($path)) {
          $body = [
            'path' => $path,
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