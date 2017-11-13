<?php
namespace Wapi\Daemon\Websocket;

use Wapi\Exception\SiteInaccessible;
use Wapi\Exception\SiteRuntimeError;
use Psr\Http\Message\ResponseInterface;
use React\Promise\Deferred;
use React\Promise\Promise;
use Wapi\Protocol\Protocol;
use Wapi\Daemon\Websocket\ServiceManager;

class Site {
  
  const ENDPOINT_PATH = '/wapi/api/endpoint';
  
  const MESSAGE_INTERVAL = 1;
  
  /**
   * @var string
   */
  public $site_secret;
  
  /**
   * @var string
   */
  public $base_url;
  
  /**
   * @var integer
   */
  public $rps;
  
  /**
   * @var string
   */
  protected $token;
  
  /**
   * @var integer
   */
  protected $creation_time;
  
  /**
   * @var integer
   */
  protected $last_access;
  
  /**
   * @var array[]
   */
  protected $messageQueue = [];
  
  /**
   * @var \React\EventLoop\Timer\Timer
   */
  public $processTimer;
  
  /**
   * @var \React\EventLoop\Timer\Timer
   */
  public $purgeTimer;
  
  public function __construct($site_secret, $base_url, $rps = 10) {
    $this->creation_time = time();
    $this->site_secret = $site_secret;
    $this->base_url = $base_url;
    $this->rps = $rps;
  }
  
  public function id() {
    return $this->site_secret;
  }
  
  public function ping() {
    $this->last_access = time();
  }
  
  public function processMessageQueue() {
    if (!$this->messageQueue) {
      return NULL;
    }
    
    $browser = ServiceManager::service('browser');
    $that = &$this;
  
    $payload = [];
  
    $message_queue = $this->messageQueue;
  
    foreach ($message_queue AS $message_id => $message) {
      if (empty($message['sent'])) {
        $this->messageQueue[$message_id]['sent'] = TRUE;
        $payload[$message_id]['session_id'] = $message['session_id'];
        $payload[$message_id]['body'] = $message['body'];
        $payload[$message_id]['method'] = $message['method'];
      }
    }
  
    if (!$payload) {
      return;
    }
  
    $url = $this->base_url . static::ENDPOINT_PATH;
  
    $browser->post($url, ['Content-Type' => 'application/json'], Protocol::encode($payload))
      ->then(function (ResponseInterface $response) use ($message_queue, &$that) {
        $response_queue_body = Protocol::decode($response->getBody());
      
        if (!is_array($response_queue_body)) {
          throw new SiteInaccessible('Erroneous response.');
        }
        else {
          if (!empty($response_queue_body['status'])) {
            throw new SiteRuntimeError($response_queue_body['error'], $response_queue_body['status']);
          }
          else {
          
            foreach ($response_queue_body AS $message_id => $body) {
            
              /** @var Deferred $deferred */
              if(!empty($message_queue[$message_id])) {
                $deferred = $message_queue[$message_id]['deferred'];
  
                if (!isset($body['status'])) {
                  $deferred->reject(new SiteInaccessible('Erroneous response.'));
                }
                else {
                  if (!empty($body['status'])) {
                    $deferred->reject(new SiteRuntimeError($body['error'], $body['status']));
                  }
                  else {
                    $deferred->resolve($body);
                  }
                }
  
                unset($that->messageQueue[$message_id]);
              }
            
            }
          }
        }
      
      }, function (\Exception $e) use ($message_queue, &$that, $payload, $url) {
        foreach ($message_queue AS $message_id => $body) {
          /** @var Deferred $deferred */
          $deferred = $message_queue[$message_id]['deferred'];
          $deferred->reject(new SiteInaccessible($e->getMessage()));
          unset($that->messageQueue[$message_id]);
        }
        throw $e;
      });
    
  }
  
  /**
   * @param string $uri
   * @param mixed $body
   * @return Promise
   */
  public function send($method, $session_id, $body) {
    $deferred = new Deferred();
    $message_id = Protocol::randomBytesBase64(16);
    $this->messageQueue[$message_id] = [
      'method' => $method,
      'session_id' => $session_id,
      'sent' => FALSE,
      'deferred' => $deferred,
      'timestamp' => microtime(TRUE),
      'body' => $body,
    ];
    
    return $deferred->promise();
  }
  
  public function purgeTimedoutMessages() {
    foreach($this->messageQueue AS $mid => $values) {
      if($values['timestamp'] < time() - 15) {
        $this->messageQueue[$mid]['deferred']->reject(new SiteInaccessible('Site timed out.'));
        unset($this->messageQueue[$mid]);
      }
    }
  }
  
  public function purgeUserMessages(User $user) {
    foreach($this->messageQueue AS $mid => $values) {
      if($values['session_id'] == $user->token) {
        unset($this->messageQueue[$mid]);
      }
    }
  }
  
}