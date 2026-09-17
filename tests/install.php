<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$skip_flightpath_settings = TRUE;
$skip_flightpath_modules = TRUE;

include(__DIR__ . "/../bootstrap.inc");

require_once(__DIR__ . "/../modules/system/system.module");

global $user;
$user = new stdClass();
$user->id = 1;

$skip_table_queries = TRUE;
require_once(__DIR__ . "/test-settings.php");

$GLOBALS["fp_die_mysql_errors"] = TRUE;

system_install();

$temp_db = new DatabaseHandler();

$new_pass = user_hash_password("TestPassword123!");

db_query("INSERT INTO users (user_id, user_name, cwid, password, email, is_faculty, f_name, l_name)
          VALUES ('1', ?, '1', ?, ?, '1', 'Admin', 'User')",
          "testadmin", $new_pass, "test@example.com");

db_query("INSERT INTO faculty (cwid) VALUES ('1')");

system_enable();

$skip_flightpath_settings = FALSE;
$skip_flightpath_modules = FALSE;

require_once(__DIR__ . "/../bootstrap.inc");

$temp_db = new DatabaseHandler();

fp_clear_cache();

print "FlightPath test database installed successfully.\n";