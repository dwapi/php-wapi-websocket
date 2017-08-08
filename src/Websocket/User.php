<?php
namespace Wapi\Daemon\Websocket;


use Wapi\Protocol\Protocol;

class User {
  
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
   * @var string[][]
   */
  public $groups = [];
  
  /**
   * @var string
   */
  public $language;
  
  
  
  public function __construct(Site $site, $user_token, $language = 'en') {
    $this->language = $language;
    $this->site = $site;
    $this->token = $user_token;
    $time = time();
    $this->public_token = Protocol::randomBytesBase64();
  }
  
  public function id() {
    return $this->token;
  }
  
  public function setGroups($category, $groups) {
    if($groups) {
      $this->groups[$category] = $groups;
    } else {
      unset($this->groups[$category]);
    }
  }
  
  public function hasGroup($category, $group) {
    return !empty($this->groups[$category]) ? in_array($group, $this->groups[$category]) : FALSE;
  }
  
}