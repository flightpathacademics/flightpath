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


// First, let's check to see if we are banning any IPs from our site...
$remote_ip = $_SERVER['REMOTE_ADDR'];
$blocklist_file = __DIR__ . '/custom/files/private/banned_ips.txt';
$cache_key = 'banned_ips';

// Load blocked IPs
if (function_exists('apcu_fetch')) {
  $blocked_ips_assoc = apcu_fetch($cache_key);
  if ($blocked_ips_assoc === FALSE) {
    // Not in cache, load from file, then store in cache
    $blocked_ips_assoc = array();
    if (file_exists($blocklist_file)) {
      $lines = file($blocklist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $lines = array_map('trim', $lines);
      $blocked_ips_assoc = array_flip($lines); // fast lookup
      apcu_store($cache_key, $blocked_ips_assoc, 120); // cache X seconds
    }
  }
} // if apcu is installed
else {
  // APCu not installed: fallback to reading file every request
  $blocked_ips_assoc = array();
  if (file_exists($blocklist_file)) {
    $lines = file($blocklist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_map('trim', $lines);
    $blocked_ips_assoc = array_flip($lines);
  }
}

// Check if IP is blocked
if (isset($blocked_ips_assoc[$remote_ip])) {
  error_log("Banned IP: $remote_ip tried to access " . $_SERVER['REQUEST_URI']); // optional
  header('HTTP/1.1 403 Forbidden');
  echo "403: Access denied";
  exit;
}


/////////////
// Okay, we can now proceed with running cron.php



require_once("bootstrap.inc");

$token = (string) trim($_REQUEST["t"] ?? '');
if ($token == '' || $token != @$GLOBALS["fp_system_settings"]["cron_security_token"]) {  
  
  watchdog('access_denied', 'Cron security token does not match this site\'s settings', array(), WATCHDOG_DEBUG);  
  
  system_check_should_ban_ip();
  
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





