<?php

if (getenv('COMPOSER_VENDOR_DIR') && is_file(__DIR__ . '/../' . getenv('COMPOSER_VENDOR_DIR') . '/autoload.php')) {
  require(__DIR__ . '/../' . getenv('COMPOSER_VENDOR_DIR') . '/autoload.php');
} elseif (is_file(__DIR__ . '/../vendor/autoload.php')) {
  require(__DIR__ . '/../vendor/autoload.php');
} elseif (is_file(__DIR__ . '/../../../autoload.php')) {
  require(__DIR__ . '/../../../autoload.php');
}

$params = \Wapi\Daemon::parseArgs($argv);
$daemon = new \Wapi\Daemon($params, '\Wapi\Daemon\Websocket\App');

$daemon->run();