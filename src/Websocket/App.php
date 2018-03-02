<?php
namespace Wapi\Daemon\Websocket;

use Clue\React\Buzz\Browser;
use Wapi\Daemon\Websocket\MessageHandler\SystemMessageHandler;
use Wapi\Daemon\Websocket\ServiceManager;

class App extends \Wapi\App {
  
  /** @var \Wapi\Daemon\Websocket\Client */
  public $client_manager;
  
  /**
   * Messages per minute
   *
   * @var integer[]
   */
  public $mpm = [];
  
  /**
   * Messages per second
   *
   * @var integer
   */
  public $mps = 0;
  
  public function getMessageHandlers() {
    return [
      '\Wapi\Daemon\Websocket\MessageHandler\SystemMessageHandler',
      '\Wapi\Daemon\Websocket\MessageHandler\SiteMessageHandler',
      '\Wapi\Daemon\Websocket\MessageHandler\SessionMessageHandler'
    ];
  }
  
  public function init() {
    $this->client_manager = ServiceManager::service('client_manager', new ClientManager());
    ServiceManager::service('browser', new Browser(ServiceManager::loop()));
    SystemMessageHandler::getCpuUsage();
    ServiceManager::loop()->addPeriodicTimer(1, function(){
      $app = ServiceManager::app();
      $app->mpm[] = $app->mps;
      if(count($app->mpm) > 10) {
        array_shift($app->mpm);
      }
      $app->mps = 0;
    });
  }
  
}