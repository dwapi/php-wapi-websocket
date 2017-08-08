<?php
namespace Wapi\Daemon\Websocket;

use GuzzleHttp\Psr7\Request;
use Ratchet\ConnectionInterface;

class Client extends \Wapi\Client {
  
  /**
   * @var User|NULL
   */
  public $user;
  
  /**
   * @var string
   */
  public $page;
  
  public function __construct(ConnectionInterface $conn, Request $request, User $user = NULL) {
    parent::__construct($conn, $request);
    $this->user = $user;
  }
  
  public function isReady() {
    return $this->user ? TRUE : FALSE;
  }
  
  public function assignUser(User $user) {
    $this->user = $user;
  }
  
}