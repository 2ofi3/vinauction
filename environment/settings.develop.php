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
  'database' => 'ID210976_develop',
  'username' => 'ID210976_develop',
  'password' => 'v37104U0w6Q2b9Yb26W8',
  'prefix' => '',
  'host' => 'ID210976_develop.db.webhosting.be',
  'port' => '',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
  'init_commands' => [
    'isolation_level' => 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED',
  ],
);

// SHIELD
$config['shield.settings']['shield_enable'] = TRUE;
$config['shield.settings']['credentials']['shield']['user'] = 'wijnveiling';
$config['shield.settings']['credentials']['shield']['pass'] = 'wijnveiling9420';
$config['shield.settings']['print'] = 'This site is protected by a username and password.';

// ENVIRONMENT INDICATOR
$config['environment_indicator.indicator']['bg_color'] = '#04a373';
$config['environment_indicator.indicator']['fg_color'] = '#FFFFFF';
$config['environment_indicator.indicator']['name'] = 'Development';