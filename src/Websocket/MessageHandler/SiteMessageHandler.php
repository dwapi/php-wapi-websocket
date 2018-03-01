<?php
namespace Wapi\Daemon\Websocket\MessageHandler;

use Wapi\Daemon\Websocket\App;
use Wapi\Daemon\Websocket\ClientManager;
use Wapi\Daemon\Websocket\ServiceManager;
use Wapi\Exception\AccessDenied;
use Wapi\Exception\ClockMismatch;
use Wapi\Exception\MessageInvalid;
use Wapi\Exception\MethodNotFound;
use Wapi\Exception\ParametersInvalid;
use Wapi\Daemon\Websocket\Site;
use Wapi\Daemon\Websocket\Session;
use Wapi\Message;
use Wapi\MessageHandler\MessageHandlerBase;

class SiteMessageHandler extends MessageHandlerBase {
  
  /**
   * @var Site
   */
  public $site;
  
  public function getMethods() {
    $methods = [];
  
    $methods['ping'] = [
      'callback' => [$this, 'ping'],
      'schema' => [],
    ];
  
    $methods['session_register'] = [
      'callback' => [$this, 'sessionRegister'],
      'schema' => [
        'private_session_token' => [
          'type' => 'string',
          'required' => TRUE,
        ],
        'language' => [
          'type' => 'string',
        ],
      ],
    ];
  
    $methods['session_remove'] = [
      'callback' => [$this, 'sessionRemove'],
      'schema' => [
        'token' => [
          'type' => 'string',
          'required' => TRUE,
        ],
      ],
    ];
  
    $methods['session_send'] = [
      'callback' => [$this, 'sessionSend'],
      'schema' => [
        'path' => [
          'type' => 'string',
          'required' => TRUE,
        ],
        'method' => [
          'type' => 'string',
          'required' => TRUE,
        ],
        'page' => [
          'type' => 'string',
        ],
        'session_tokens' => [
          'type' => 'string',
          'required' => TRUE,
          'multi' => TRUE,
        ],
      ],
    ];
  
    $methods['subscribe'] = [
      'callback' => [$this, 'subscribe'],
      'schema' => [
        'path' => [
          'type' => 'string',
          'required' => TRUE,
        ],
        'session_tokens' => [
          'type' => 'string',
          'required' => TRUE,
          'multi' => TRUE,
        ],
        'groups' => [
          'type' => 'array',
          'required' => TRUE,
        ],
      ],
    ];
  
    $methods['unsubscribe'] = [
      'callback' => [$this, 'unsubscribe'],
      'schema' => [
        'path' => [
          'type' => 'string',
          'required' => TRUE,
        ],
        'session_tokens' => [
          'type' => 'string',
          'required' => TRUE,
          'multi' => TRUE,
        ],
        'groups' => [
          'type' => 'array',
          'required' => TRUE,
        ],
      ],
    ];
  
    $methods['broadcast'] = [
      'callback' => [$this, 'broadcast'],
      'schema' => [
        'path' => [
          'type' => 'string',
          'required' => TRUE,
        ],
        'method' => [
          'type' => 'string',
          'required' => TRUE,
        ],
        'data' => [
          'type' => 'any',
        ],
        'groups' => [
          'type' => 'array',
          'required' => TRUE,
        ],
      ],
    ];
    
    return $methods;
  }
  
  static function isApplicable(Message $message) {
    if(!($message->client->getRequestPath() == '/wapi/site')) {
      return FALSE;
    }
    return TRUE;
  }
  
  public function access() {
    $client_manager = ServiceManager::clientManager();
    
    if(!($this->message->method == 'ping') && !$this->message->verifyTimestamp()) {
      throw new ClockMismatch();
    }
    
    $site_key = $this->message->get('site_key');
    $found = $client_manager->getSite($site_key);
    
    if(!$found) {
      throw new AccessDenied('Site not found');
    }
    
    $this->site = $found;
  }
  
  public function ping() {
    return time();
  }
  
  public function sessionRegister($data) {
    $client_manager = ServiceManager::clientManager();
    $app = ServiceManager::app();
    $site = $this->site;
    
    $language = !empty($data['language']) ? $data['language'] : 'en';
    if($session = $client_manager->getSessionByToken($data['private_session_token'])) {
      $session->language = $language;
    } else {
      $session = new Session($site, $data['private_session_token'], $language);
    }
    $client_manager->sessionAdd($session);
    $response = [
      'session_public_token' => $session->public_token,
      'websocket_address' => $app->address,
    ];
    return $response;
  }
  
  public function sessionSend($data) {
    $client_manager = ServiceManager::clientManager();
    
    $path = $data['path'];
    $page = empty($data['page']) ? NULL : $data['page'];
    
    foreach($data['session_tokens'] AS $token) {
      if($session = $client_manager->getSessionByToken($token)) {
        foreach ($session->getClients() AS $client) {
          if ($client->hasPath($path) && (!$page || ($client->page == $page))) {
            $body = [
              'path' => $path,
              'method' => $data['method'],
              'data' => isset($data['data']) ? $data['data'] : NULL,
            ];
            $client->send($body);
          }
        }
      }
    }
  }
  
  public function subscribe($data) {
    $client_manager = ServiceManager::clientManager();
    
    $path = $data['path'];
    $groups = $data['groups'];
    
    foreach($data['sessions'] AS $token) {
      if($session = $client_manager->getSessionByToken($token)) {
        $client_manager->getGroupsManager()->subscribeSession($path, $session, $groups);
      }
    }
  }
  
  public function unsubscribe($data) {
    $client_manager = ServiceManager::clientManager();
    
    $path = $data['path'];
    $groups = $data['groups'];
  
    foreach($data['sessions'] AS $token) {
      if($session = $client_manager->getSessionByToken($token)) {
        $client_manager->getGroupsManager()->unsubscribeSession($path, $session, $groups);
      }
    }
  }
  
  public function broadcast($data) {
    $client_manager = ServiceManager::clientManager();
  
    $path = $data['path'];
    $groups = $data['groups'];
    $method = $data['method'];
    $data = !empty($data['data']) ? $data['data'] : NULL;
    
    return $client_manager->broadcast($this->site, $path, $groups, $method,  $data);
  }
  
}