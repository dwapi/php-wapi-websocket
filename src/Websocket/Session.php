<?php
namespace Wapi\Daemon\Websocket;


use Wapi\Protocol\Protocol;

class Session {
  
  /**
   * @var Site
   */
  public $site;
  
  /**
   * @var string
   */
  public $token;
  
  /**
   * @var string
   */
  public $public_token;
  
  /**
   * @var string
   */
  public $language;
  
  /**
   * @var \Wapi\Daemon\Websocket\Group[]
   */
  public $groups = [];
  
  /**
   * @var \Wapi\Daemon\Websocket\Client[]
   */
  public $clients = [];
  
  public function __construct(Site $site, $session_token, $language = 'en') {
    $this->language = $language;
    $this->site = $site;
    $this->token = $session_token;
    $this->public_token = Protocol::randomBytesBase64();
  }
  
  public function id() {
    return $this->token;
  }
  
  public function getClients() {
    return $this->clients;
  }
  
  public function addClient(Client $client) {
    $this->clients[$client->id()] = $client;
  }
  
  public function removeClient(Client $client) {
    unset($this->clients[$client->id()]);
  }
  
  public function getGroups() {
    return $this->groups;
  }
  
  public function addGroup(Group $group) {
    $this->groups[$group->id()] = $group;
  }
  
  public function removeGroup(Group $group) {
    unset($this->groups[$group->id()]);
  }
  
}