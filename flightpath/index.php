<?php

/**
 * @file
 * The primary entry point for FlightPath.
 * 
 * This script will determine which page the user is trying to view, 
 * and display it for them.
*/


// Load all of the classes, as well as the custom classes.
require_once("classes/all_classes.php");
  
// Make sure our cookies are the most secure possible:
ini_set('session.cookie_httponly', 'On');
if( (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443 ){
  //enable secure cookies, since we are on HTTPS.  
  ini_set('session.cookie_secure', 'On');    
}



 
// Should we init the session using a specific session_id?
if (@$_GET['fp_session_str'] != '') {
  
  // For security, the fp_session_str is made of several pieces so we can be sure it is authentic
  // and not a hacker trying to imitate a known user's session_id.
  
  require_once("includes/misc.inc"); // Bring in the functions we need so we can validate the fp_session_str
  
  // We will validate now and retrieve the PHP session_id from it, or FALSE.
  $session_id = fp_get_session_id_from_str($_GET['fp_session_str']);
  if ($session_id) {
    session_id($session_id);
  }
  else {
    // The session did not validate.  This might be a hacking attempt.  Kill the script.
    die("Error: Session could not be validated by FlightPath (index.php).  If this continues, contact your IT administrator.");
  }  
}

session_start();

// Set headers for maximum security
header("Cache-control: no-cache, no-store, must-revalidate");  // HTTP 1.1
header("Pragma: no-cache");  // HTTP 1.0
header("X-XSS-Protection: 1");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");  // Date in the past, to ensure it expires when we close the browser.
header('X-Frame-Options: SAMEORIGIN');  // No iframes except from the same website origins.


// If the user is requesting a "clean URLs" check, display a simple success message.
if (isset($_REQUEST["q"]) && $_REQUEST["q"] == "test-clean-urls/check") {
  print "CLEAN URLS CHECK SUCCESSFUL";
  die;
}


// If the settings.php file doesn't exist, then FlightPath must not be installed,
// and we should redirect to install.php.
if (!file_exists("custom/settings.php")) {
  header ("Location: install.php");
  die;
}


require_once("bootstrap.inc");


// For development reasons only:
// To rebuild the cache on every page load, un-comment the following line
// menu_rebuild_cache();

// FlightPath will now look at the request in the query to decide what page we are going to display.
$page = menu_execute_page_request();

if (!is_int($page)) {
  // Display the page!
  fp_display_page($page);
}
else {  
  if ($page == MENU_NOT_FOUND) {
    display_not_found();
  }
  else if ($page == MENU_ACCESS_DENIED) {
    
    display_access_denied();
  }
}

// Call hook_exit as we leave the page.
invoke_hook("exit"); 
 
 
 
 
 

 