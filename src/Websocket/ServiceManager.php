<?php
namespace Wapi\Daemon\Websocket;

class ServiceManager extends \Wapi\ServiceManager {
  
  /**
   * @return \Wapi\Daemon\Websocket\App
   */
  static function app() {
    return parent::service('app');
  }
  
  /**
   * @return \Wapi\Daemon\Websocket\ClientManager
   */
  static function clientManager() {
    return parent::service('client_manager');
  }
}