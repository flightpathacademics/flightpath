<?php

/**
 * @file
 * The cron.php file for FlightPath, which should be run periodically.
 *
 * This file will invoke hook_cron on all available modules.  It should be
 * accessed in a similar method as
 * wget http://url/cron.php?t=CRON_TOKEN
 *
 * You can find your site's cron token (and change it if you wish)
 * in your /custom/settings.php file.
 */

require_once("bootstrap.inc");

$token = (string) trim($_REQUEST["t"] ?? '');
if ($token == '' || $token != @$GLOBALS["fp_system_settings"]["cron_security_token"]) {

  watchdog('access_denied', 'Cron security token does not match this site\'s settings', array(), WATCHDOG_DEBUG);

  header('HTTP/1.1 403 Forbidden', TRUE, 403);
  die("Sorry, cron security token does not match this site's settings.");

}

// If we made it here, we can clear any bannable offenses from the counter.
unset($_SESSION['fp_should_ban_counter']);
unset($_SESSION['fp_should_ban_counter_init_ts']);


// Keep the script from timing out prematurely...
set_time_limit(99999);  // around 27 hours.


watchdog("cron", "Cron run started", array(), WATCHDOG_DEBUG);
invoke_hook("cron");
watchdog("cron", "Cron run completed", array(), WATCHDOG_DEBUG);


variable_set("cron_last_run", time());





