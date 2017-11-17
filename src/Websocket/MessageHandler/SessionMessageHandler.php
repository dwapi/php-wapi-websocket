<?php
namespace Wapi\Daemon\Websocket\MessageHandler;

use Wapi\Daemon\Websocket\App;
use Wapi\Daemon\Websocket\ClientManager;
use Wapi\Exception\AccessDenied;
use Wapi\Exception\MessageInvalid;
use Wapi\Exception\MethodNotFound;
use Wapi\Exception\ParametersInvalid;
use Wapi\Exception\SiteInaccessible;
use Wapi\Exception\SiteRuntimeError;
use Wapi\Exception\WapiException;
use Wapi\Message;
use Wapi\Daemon\Websocket\Site;
use Wapi\Daemon\Websocket\Session;
use Psr\Http\Message\ResponseInterface;
use Wapi\Daemon\Websocket\ServiceManager;
use Wapi\MessageHandler\MessageHandlerBase;
use Wapi\Protocol\Protocol;

class SessionMessageHandler extends MessageHandlerBase {
  
  public function getMethods() {
    $methods = [];
    
    $methods['connect'] = [
      'callback' => [$this, 'connect'],
      'schema' => [
        'token' => [
          'type' => 'string',
          'required' => TRUE,
        ],
        'page' => [
          'type' => 'string',
          'required' => TRUE,
        ],
      ],
    ];
    
    $methods['user_request'] = [
      'callback' => [$this, 'userRequest'],
      'schema' => [
        'method' => [
          'type' => 'string',
          'required' => TRUE,
        ],
        'data' => [
          'type' => 'any',
        ],
      ],
    ];
    
    return $methods;
  }
  
  static function isApplicable(Message $message) {
    return $message->client->getRequestPath() == '/wapi/endpoint';
  }
  

  
  public function access() {
    /** @var \Wapi\Daemon\Websocket\Client $client */
    $client = $this->message->client;
    $session = $client->session;
    
    if(!$session && !($this->message->method == 'connect')) {
      throw new AccessDenied('Unregistered session.');
    }
  }
    
  public function connect($data) {
    $client_manager = ServiceManager::clientManager();
    $session = $client_manager->getSessionByToken($data['token'], TRUE);
    if(!$session) {
      $client_manager = ServiceManager::clientManager();
      $loop = ServiceManager::loop();
      $client = $this->message->client;
      $loop->addTimer(0, function() use ($client, $client_manager) {
        $client_manager->clientRemove($client);
      });
      throw new AccessDenied('Session not found.');
    }
    /** @var \Wapi\Daemon\Websocket\Client $client */
    $client = $this->message->client;
    $client->page = $data['page'];
    $client->assignSession($session);
  }
  
  public function userRequest($data) {
    /** @var \Wapi\Daemon\Websocket\Client $client */
    $client = $this->message->client;
    $session = $client->session;
    $site = $session->site;
    
    $path = $this->message->path;
    $page = $client->page;
    $method = $data['method'];
    $payload = !empty($data['data']) ? $data['data'] : NULL;
    $time = time();
    $secret = $site->site_secret;
    $session_token = $session->token;
    
    $additional = [
      'path' => $path,
      'page' => $page,
    ];
    
    $body = Protocol::buildMessage($secret, $method, $payload, $additional);
    
    $that = $this;
    
    $client->addPath($path);

    return $site->send('endpoint', $session_token, $body)
      ->then(function($body) use ($that){
        $data = !empty($body['data']) ? $body['data'] : NULL;
        $error = !empty($body['error']) ? $body['error'] : NULL;
        $errorNo = !empty($body['status']) ? $body['status'] : 0;
        
        if($errorNo) {
          throw new SiteRuntimeError($error, $errorNo);
        }

        return $data;
      });
  }
  
}