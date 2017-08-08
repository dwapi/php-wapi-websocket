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
use Wapi\Daemon\Websocket\User;
use Psr\Http\Message\ResponseInterface;
use Wapi\Daemon\Websocket\ServiceManager;
use Wapi\MessageHandler\MessageHandlerBase;

class UserMessageHandler extends MessageHandlerBase {
  
  static function getMethods() {
    return [
      'user_request' => 'userRequest',
      'connect' => 'connect',
    ];
  }
  
  static function isApplicable(Message $message) {
    return $message->client->getPath() == '/wapi/endpoint';
  }
  

  
  public function access() {
    /** @var \Wapi\Daemon\Websocket\Client $client */
    $client = $this->message->client;
    $user = $client->user;
    
    if(!$user && !($this->message->method == 'connect')) {
      throw new AccessDenied('Unregistered user.');
    }
  }
    
  public function connect() {
    $data = $this->message->data  ;
    if($data && !empty($data['token']) && isset($data['page'])) {
      $client_manager = ServiceManager::clientManager();
      $user = $client_manager->getUserByToken($data['token'], TRUE);
      if(!$user) {
        $client_manager = ServiceManager::clientManager();
        $loop = ServiceManager::loop();
        $client = $this->message->client;
        $loop->addTimer(0, function() use ($client, $client_manager) {
          $client_manager->clientRemove($client);
        });
        throw new AccessDenied('User not found.');
      }
      /** @var \Wapi\Daemon\Websocket\Client $client */
      $client = $this->message->client;
      $client->page = $data['page'];
      $client->assignUser($user);
    } else {
      throw new ParametersInvalid();
    }
  }
  
  public function userRequest() {
    $data = $this->message->data;
    if($data && !empty($data['method'])) {
      /** @var \Wapi\Daemon\Websocket\Client $client */
      $client = $this->message->client;
      $user = $client->user;
      $site = $user->site;
      
      $path = $this->message->path;
      $page = $client->page;
      $method = $data['method'];
      $payload = !empty($data['data']) ? $data['data'] : NULL;
      $time = time();
      $secret = $site->site_secret;
      $user_token = $user->token;
      
      $body = [
        'path' => $path,
        'page' => $page,
        'method' => $method,
        'timestamp' => $time,
        'data' => $payload,
        'check' => Message::sign("$secret:$user_token:$time:$path:$method:", $payload),
      ];
      
      $that = $this;
  
      return $site->send('endpoint', $user_token, $body)
        ->then(function($body) use ($that){
          $data = !empty($body['data']) ? $body['data'] : NULL;
          $error = !empty($body['error']) ? $body['error'] : NULL;
          $errorNo = !empty($body['status']) ? $body['status'] : 0;
          
          if($errorNo) {
            throw new SiteRuntimeError($error, $errorNo);
          }
  
          return $data;
        });
    } else {
      throw new ParametersInvalid();
    }
  }
  
}