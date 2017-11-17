<?php

namespace Wapi\Daemon\Websocket;


class Group {
  
  private $name;
  
  /**
   * @var \Wapi\Daemon\Websocket\Session[]
   */
  private $sessions = [];
  
  /**
   * @var Group[]
   */
  private $subGroups = [];
  
  /**
   * @var string[]
   */
  public $hierarchy = [];
  
  public function __construct($name, $hierarchy = NULL) {
    $this->name = $name;
    if(is_array($hierarchy)) {
      $this->hierarchy = $hierarchy + [$name];
    }
  }
  
  public function getName() {
    return $this->name;
  }
  
  public function id() {
    return spl_object_hash($this);
  }
  
  public function isEmpty() {
    $empty = TRUE;
    foreach($this->subGroups AS $sub_group) {
      $empty = $empty && $sub_group->isEmpty();
    }
    return $empty && empty($this->sessions);
  }
  
  public function createSubGroup($name) {
    if(empty($this->subGroups[$name])) {
      $this->subGroups[$name] = new Group($name, $this->hierarchy);
    }
    return $this->subGroups[$name];
  }
  
  public function deleteSubGroup($name) {
    unset($this->subGroups[$name]);
    return empty($this->sessions) && empty($this->subGroups);
  }
  
  public function addSession(Session $session, $sub_groups = FALSE) {
    if(is_array($sub_groups)) {
      foreach($sub_groups AS $name => $sub_group) {
        $this->createSubGroup($name)
             ->addSession($session, $sub_group);
      }
    } else {
      $this->sessions[$session->id()] = $session;
      $session->addGroup($this);
    }
  }
  
  public function removeSession(Session $session, $sub_groups = FALSE) {
    if(is_array($sub_groups)) {
      foreach($sub_groups AS $name => $sub_group) {
        if(!empty($this->subGroups[$name])) {
          if($this->subGroups[$name]->removeSession($session, $sub_group)) {
            $this->deleteSubGroup($name);
          }
        }
      }
    } else {
      unset($this->sessions[$session->id()]);
      $session->removeGroup($this);
    }
    
    return empty($this->sessions) && empty($this->subGroups);
  }
  
  /**
   * @param bool $sub_groups
   *
   * @return \Wapi\Daemon\Websocket\Session[]
   */
  public function getSessions($sub_groups = FALSE) {
    /** @var \Wapi\Daemon\Websocket\Session[] $sessions */
    $sessions = [];
    
    if(is_array($sub_groups)) {
      foreach($sub_groups AS $name => $sub_group) {
        if(!empty($this->subGroups[$name])) {
          $sessions += $this->subGroups[$name]->getSessions($sub_group);
        }
      }
    } else {
      $sessions = $this->sessions;
      foreach($this->subGroups AS $sub_group) {
        $sessions += $sub_group->getSessions();
      }
    }
    
    return $sessions;
  }
}