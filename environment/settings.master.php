<?php

/**
 * @file
 * Local development override configuration feature.
 *
 * To activate this feature, copy and rename it such that its path plus
 * filename is 'sites/example.com/settings.local.php', where example.com
 * is the name of your site. Then, go to the bottom of
 * 'sites/example.com/settings.php' and uncomment the commented lines that
 * mention 'settings.local.php'.
 */

$databases['default']['default'] = array (
  'database' => 'ID210976_production',
  'username' => 'ID210976_production',
  'password' => 'X7Y7LjW47S9q74cedaW1',
  'prefix' => '',
  'host' => 'ID210976_production.db.webhosting.be',
  'port' => '',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
);

// SHIELD
$config['shield.settings']['shield_enable'] = FALSE;
$config['shield.settings']['credentials']['shield']['user'] = 'wijnveiling';
$config['shield.settings']['credentials']['shield']['pass'] = 'Wyvjh9Fhx23B';
$config['shield.settings']['print'] = 'This site is protected by a username and password.';

// ENVIRONMENT INDICATOR
$config['environment_indicator.indicator']['bg_color'] = '#DD0000';
$config['environment_indicator.indicator']['fg_color'] = '#FFFFFF';
$config['environment_indicator.indicator']['name'] = 'Prod';

/* Redis config
 * Please leave the settings below untouched
 */
/*$settings['redis.connection']['interface'] = 'PhpRedis';
// Host ip address.
$settings['redis.connection']['host']      = '185.86.18.247';
$settings['cache']['default'] = 'cache.backend.redis';
// Redis port.
$settings['redis.connection']['port']      = '10027';
$settings['redis.connection']['base']      = 12;
// Password of redis updated in php.ini file.
$settings['redis.connection']['password'] = "SoSiAn20021983";
$settings['cache']['bins']['bootstrap'] = 'cache.backend.chainedfast';
$settings['cache']['bins']['discovery'] = 'cache.backend.chainedfast';
$settings['cache']['bins']['config'] = 'cache.backend.chainedfast';*/