<?php

namespace Wapi\Daemon\Websocket;

class GroupsManager {
  
  /**
   * @var \Wapi\Daemon\Websocket\Group
   */
  private $rootGroup;
  
  public function __construct() {
    $this->rootGroup = new Group("root");
  }
  
  /**
   * @param \Wapi\Daemon\Websocket\Site $site
   * @param $path
   * @param array|null $groups
   *
   * @return \Wapi\Daemon\Websocket\Session[]
   */
  public function getSessions(Site $site, $path, array $groups = NULL) {
    $groups = [$site->id() => [$path => $groups]];
    return $this->rootGroup->getSessions($groups);
  }
  
  public function subscribeSession($path, Session $session, array $groups) {
    $groups = [$session->site->id() => [$path => $groups]];
    $this->rootGroup->addSession($session, $groups);
  }
  
  public function unsubscribeSession($path, Session $session, array $groups) {
    $groups = [$session->site->id() => [$path => $groups]];
    $this->rootGroup->removeSession($session, $groups);
  }
  
  public function deleteSession(Session $session) {
    foreach ($session->getGroups() AS $group) {
      $this->rootGroup->removeSession($session, static::flatToNestedArray($group->hierarchy));
    }
  }
  
  public static function flatToNestedArray(array $flat) {
    $nested = TRUE;
    for($i = count($flat) - 1; $i >= 0; $i--) {
      $nested = [$flat[$i] => $nested];
    }
    return $nested;
  }
  
}