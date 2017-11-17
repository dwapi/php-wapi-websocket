<?php
namespace Wapi\Daemon\Websocket;

use GuzzleHttp\Psr7\Request;
use Ratchet\ConnectionInterface;

class Client extends \Wapi\Client {
  
  /**
   * @var Session|NULL
   */
  public $session;
  
  /**
   * @var string
   */
  public $page;
  
  public function __construct(ConnectionInterface $conn, Request $request, Session $session = NULL) {
    parent::__construct($conn, $request);
    $this->session = $session;
  }
  
  public function isReady() {
    return $this->session ? TRUE : FALSE;
  }
  
  public function assignSession(Session $session) {
    $session->addClient($this);
    $this->session = $session;
  }
  
}