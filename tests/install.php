<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$skip_flightpath_settings = TRUE;
$skip_flightpath_modules = TRUE;

include(__DIR__ . "/../bootstrap.inc");

require_once(__DIR__ . "/../modules/system/system.module");
require_once(__DIR__ . "/../modules/system/system.install");

global $user;
$user = new stdClass();
$user->id = 1;
$user->cwid = 1;
$user->name = 'admin';


$skip_table_queries = TRUE;
require_once(__DIR__ . "/test-settings.php");

$GLOBALS["fp_die_mysql_errors"] = TRUE;

// Remove any existing tables from the test database.
$tables = $GLOBALS["pdo"]->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
  $GLOBALS["pdo"]->exec("DROP TABLE `" . str_replace("`", "``", $table) . "`");
}



system_install();

$new_pass = user_hash_password("password");
db_query("INSERT INTO users (user_id, user_name, cwid, password, email, is_faculty, f_name, l_name)
          VALUES ('1', ?, ?, ?, ?, '1', 'Admin', 'User')",
    $user->name, $user->cwid, $new_pass, "test@example.com");

db_query("INSERT INTO faculty (cwid) VALUES ('1')");

system_enable();

// We need to load the bootstrap.inc file again, to make sure important settings
// and modules are loaded.
$skip_flightpath_settings = FALSE;
$skip_flightpath_modules = FALSE;
include(__DIR__ . "/../bootstrap.inc");
$temp_db = new DatabaseHandler();


fp_clear_cache();

////////////////
// Load some initial data...

$fixture_file = __DIR__ . "/fixtures/sample-data.sql";
$handle = fopen($fixture_file, "r");
if ($handle === FALSE) {
  die("Unable to open test fixture: $fixture_file\n");
}
while (($sql = fgets($handle)) !== FALSE) {
  $sql = trim($sql);
  if ($sql === "") {
    continue;
  }
  db_query($sql);
}
fclose($handle);


variable_set('clean_urls', TRUE);
variable_set('current_catalog_year', 2020);
variable_set('current_draft_catalog_year', 2020);
variable_set('earliest_catalog_year', 2020);
variable_set('term_id_structure', "[Y4]60, Spring, Spring of [Y4], Spr '[Y2], [Y]\n[Y4]40, Fall, Fall of [Y4-1], Fall '[Y2-1], [Y-1]");
variable_set('available_advising_term_ids', "202060,202140");
variable_set('advising_term_id', "202140");




print "FlightPath test database installed successfully.\n";



//