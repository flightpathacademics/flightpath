<?php
/**
 * This file is meant to be used entirely via the command-line interface.  It will not function for web browsers.
 * 
 * Example use:  php cli.php clear-cache   (clears all cache)
 * 
*/


if (!flightpath_is_cli()) {
  print "\n\n==========================\n\n";
  print "Sorry, this script is only available as a command-line tool.";
  print "\n\n==========================\n\n";
  die;
}

session_start();


print "\n\n--------------------------------------------\n";
print "FlightPath Command-Line Interface Script\n";
print "--------------------------------------------\n\n";


// Keep the script from timing out prematurely...
set_time_limit(300);


// Include the FlightPath bootstrap file, which will load the minimum files and modules
// to run FlightPath from the command line.
require_once("bootstrap.inc");
$GLOBALS["fp_die_mysql_errors"] = TRUE;


// Get the command being issued to the script.  Ex: php cli.php COMMAND
$command_one = trim($argv[1]);

if ($command_one == "" || $command_one == "-h" || $command_one == "--help" || $command_one == "help" || $command_one == "?") {
  // show instructions.
  print "USAGE:  sudo -u www-data php cli.php <option>";
  print "\n  Note:  The 'sudo -u www-data' prefix ensures this is run as your webserver's user.";
  print "\n         If www-data is not the name of your webserver's user, replace with whatever it is.";
  print "\n         Some actions may not work correctly unless being run as the webserver's user.";
  
  print "\n\nOPTIONS:";
  print "\n   clear-cache   -  Clear system cache.";
  print "\n   run-updates   -  Run db updates for modules and system.  Will also clear cache when done.";
  
  print "\n\n";  
}





// Based on command, perform action.

if ($command_one == 'clear-cache') {
  print "\n - Clearing cache...";  
  fp_clear_cache();
  print "\n --> Cache has been cleared.";
}


if ($command_one == 'run-updates') {
  print "\n - Executing module DB updates...";
  system_confirm_db_updates_form_submit(array(), array());
  $batch_id = $_SESSION['fp_batch_id'];
  $batch = batch_get($batch_id);
  
  if ($batch && is_array($batch) && isset($batch['operation'][1][0]) && count($batch['operation'][1][0]) > 0) {
    $modules = $batch['operation'][1][0];
    while (true) {
      system_perform_db_updates_perform_batch_operation($batch, $modules);
      print "\n -- Updated " . @$modules[$batch['results']['current'] - 1]['module'] . "";
      if ($batch['results']['finished']) {
        break;
      }        
    } // while
  }
  else {
    print "\n -- No modules have DB updates to perform.";
  }
  
  
  print "\n --> DB Update of modules completed.";

  print "\n - Clearing cache...";  
  fp_clear_cache();
  print "\n --> Cache has been cleared.";
  
   
}









print "\n\n Finished execution.";
print "\n\n----------------------\n";

die;  // Finished with the script.  Functions go below to make it more tidy.
///////////////////////////////////////////////////
///////////////////////////////////////////////////
///////////////////////////////////////////////////
/**
 *  Returns TRUE or FALSE if we are in CLI mode.  Borrowed code from Drupal 7: https://api.drupal.org/api/drupal/includes!bootstrap.inc/function/drupal_is_cli/7.x
 */
function flightpath_is_cli() {
  return !isset($_SERVER['SERVER_SOFTWARE']) && (php_sapi_name() == 'cli' || is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0);
}














