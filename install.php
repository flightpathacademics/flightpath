<?php
/**
 * @file
 * This is the initial installation file for FlightPath.
 *
 * This script will handle the initial installation of FlightPath, which
 * entails creating its database tables and settings.php file.
 */

// Set the PHP error reporting level for FlightPath.  In this case,
// only show us errors and warnings. (Hide "notice" and "strict" messages)
error_reporting(E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_WARNING);

session_start();

header("Cache-control: private");

// Load our bootstrap (skipping over loads we don't need)
$skip_flightpath_settings = TRUE;
$skip_flightpath_modules = TRUE;
require("bootstrap.inc");

// Load needed modules
require_once("modules/system/system.module");

// Check here to see if FlightPath has already been installed.
// We will do this by simply looking for the settings.php file.
if (file_exists("custom/settings.php")) {
  die("FlightPath has already been installed.  If you wish to re-install FlightPath,
        DELETE the custom/settings.php file, and drop all of the tables in FlightPath's
        database.");
}

/*
 * To begin setting up FlightPath, the user must have completed
 * two other steps-- select language, and pass the requirements
 * check.
 */
$lang = "";
if (isset($_REQUEST["lang"])) {
  $lang = $_REQUEST["lang"];
}

if ($lang == "") {
  install_display_lang_selection();
  die;
}

// If we made it here, the language must have been set.  So,
// now check the requirements, and display the results if there
// are any.
if ($req_array = install_check_requirements()) {
  install_display_requirements($req_array);
  die;
}

// Set to blank if not already there.
if (!isset($_REQUEST['perform_action'])) $_REQUEST['perform_action'] = '';

if ($_REQUEST["perform_action"] != "install") {
  // If we made it this far, it means we have no unfulfilled requirements.
  // Let's go ahead and ask the user for their database information.
  install_display_db_form();
  die;
}
else {
  // We ARE trying to install.  Let's give it a go!
  install_perform_install();
  die;
}



die;




// See: https://stackoverflow.com/questions/4356289/php-random-string-generator
function install_get_random_string($length = 99) {
  $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@^&#%*';
  $charlen = strlen($characters);
  $random_string = '';
  for ($i = 0; $i < $length; $i++) {
      $random_string .= $characters[mt_rand(0, $charlen - 1)];
  }

  return $random_string;
}



/**
 * Actually performs the installation of FlightPath
 */
function install_perform_install() {
  global $user;
  if (!isset($user)) {
    $user = new stdClass();
  }
  $user->id = 1;  // set to admin during install

  $db_name = trim($_POST["db_name"]);
  $db_host = trim($_POST["db_host"]);
  $db_port = trim($_POST["db_port"]);
  $db_user = trim($_POST["db_user"]);
  $db_pass = trim($_POST["db_pass"]);

  $admin_pass = trim($_POST["admin_pass"]);
  $admin_pass2 = trim($_POST["admin_pass2"]);
  $admin_name = trim($_POST["admin_name"]);
  $admin_email = trim($_POST["admin_email"]);

  if (strlen($admin_name) < 4) {
    return install_display_db_form("<div style='color:red;'>" . st("Please select another
                                                            username for Admin (ex: admin)
                                                            which is at least 4 characters long.") . "</div>");
  }

  if (strlen($admin_pass) < 8) {
    return install_display_db_form("<div style='color:red;'>" . st("Admin password must be at least 8 characters long.") . "</div>");
  }

  if ($admin_pass != $admin_pass2) {
    return install_display_db_form("<div style='color:red;'>" . st("You must enter the same Admin password for both the
                                                                'Admin Password' field and the 'Re-enter Password'
                                                                field.") . "</div>");
  }

  if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
    // invalid emailaddress
    return install_display_db_form("<div style='color:red;'>" . st("You must enter a valid email address for the admin user.") . "</div>");

  }



  // Place into settings so our installation procedures will work.
  $GLOBALS["fp_system_settings"]["db_host"] = $db_host;
  $GLOBALS["fp_system_settings"]["db_port"] = $db_port;
  $GLOBALS["fp_system_settings"]["db_user"] = $db_user;
  $GLOBALS["fp_system_settings"]["db_pass"] = $db_pass;
  $GLOBALS["fp_system_settings"]["db_name"] = $db_name;


  // Make sure admin information is OK.




  // We will attempt to connect to this database.  If we have any problems, we will go back to
  // the form and inform the user.
  try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $GLOBALS['pdo'] = $pdo;

  }
  catch (Exception $e) {
    // Connection failed!
    return install_display_db_form("<div style='color:red;'>" . st("Could not connect.  Please check that you have
                                    created the database already, and given the user all of the permissions
                                    (except Grant).  Then, make sure you typed the username and
                                    password correctly, as well as the database name itself.
                                    <br><br>Full exception message: " . $e->getMessage()) . "</div>");

  }

  ///////////////////////////////
  // If we have made it here, then we have been provided valid database credentials.
  // Let's write out our settings.php file.
  $settings_template = trim(install_get_settings_file_template());
  // Add in our replacements
  $settings_template = str_replace("%DB_HOST%", $db_host, $settings_template);
  $settings_template = str_replace("%DB_PORT%", $db_port, $settings_template);
  $settings_template = str_replace("%DB_NAME%", $db_name, $settings_template);
  $settings_template = str_replace("%DB_USER%", $db_user, $settings_template);
  $settings_template = str_replace("%DB_PASS%", $db_pass, $settings_template);
  $settings_template = str_replace("%CRON_SECURITY_TOKEN%", md5(time()), $settings_template);
  $settings_template = str_replace("%ENCRYPTION_KEY_STRING%", install_get_random_string(99) , $settings_template);

  // Attempt to figure out the file_system_path based on __FILE__
  $file_system_path = str_replace("install.php", "", __FILE__);
  // Convert \ to / in the file system path.
  $file_system_path = str_replace("\\", "/", $file_system_path);
  // Get rid of the last character, which should be a / at this point.
  $file_system_path = substr($file_system_path, 0, -1);

  $settings_template = str_replace("%FILE_SYSTEM_PATH%", $file_system_path, $settings_template);

  // Attempt to figure out the base URL.
  $protocol = "http";
  if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') { $protocol = "https"; }

  // This method of determining protocol no longer seems to work
  //$protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']),'https') === FALSE ? 'http' : 'https';

  $host = $_SERVER['HTTP_HOST'];
  $script = $_SERVER['SCRIPT_NAME'];
  $base_url = $protocol . "://" . $host . $script;
  $base_url = str_replace("/install.php", "", $base_url);

  $settings_template = str_replace("%BASE_URL%", $base_url, $settings_template);

  // Figure out the base_path and add it in.
  $base_path = str_replace($protocol . "://" . $host, "", $base_url);
  if ($base_path == "") $base_path = "/";  // If it is empty, then we are on a bare URL and it should just be /

  $settings_template = str_replace("%BASE_PATH%", $base_path, $settings_template);

  // Okay, we have completed all the changes to the settings template string, we can
  // write it out to a file now.
  if (!file_put_contents("custom/settings.php", $settings_template)) {
    die("There was an error trying to write out the /custom/settings.php file.  Please
         make sure the /custom directory is writable to the webserver.");
  }

  ///////////////////////////////////
  // Okay, we have just written out our settings.php file.
  // We now need to install our database.  We will do this by
  // running the system module's hook_install, as it contains all
  // of our various tables needed to run FlightPath.
  include_once("modules/system/system.install");

  $GLOBALS["fp_die_mysql_errors"] = TRUE;
  // call system_install() to perform our numerous DB table creations.

  system_install();

  // With db tables created, let's include our settings file so we can get some
  // important GLOBAL variables set up.
  include("custom/settings.php");
  // Re-establish DatabaseHandler object connection since we just re-loaded the settings file.
  $temp_db = new DatabaseHandler();

  // Get our hash of the admin password
  $new_pass = user_hash_password($admin_pass);

  // Add the admin user to the newly-created users table and the "faculty" table.
  db_query("INSERT INTO users (user_id, user_name, cwid, password, email, is_faculty, f_name, l_name)
            VALUES ('1', ?, '1', ?, ?, '1', 'Admin', 'User') ", $admin_name, $new_pass, $admin_email);

  db_query("INSERT INTO faculty (cwid) VALUES ('1') ");

  // Having made it here, we now need to call system_enable,
  // which will in turn enable all of the other modules which
  // we will need to have, as well as other database changes.
  system_enable();

  // Now that we have enabled all of the modules (and made other database changes)
  // let's re-include the bootstrap file, which will re-init our GLOBAL settings,
  // as well as load all of our new modules.
  $skip_flightpath_settings = FALSE;
  $skip_flightpath_modules = FALSE;
  include("bootstrap.inc");
  // Re-establish DatabaseHandler object connection since we just re-loaded the settings file.
  $temp_db = new DatabaseHandler();

  /////////////////////////

  // Now, we need to clear our caches and re-build the menu router.
  fp_clear_cache();

  // wipe out the SESSION to remove any extraneous messages.
  session_destroy();


  // Okay, now we are done!
  // let's re-direct to a new page.
  fp_goto("install-finished");
}


/**
 * Returns a template for a new settings file.
 *
 * The only role of this function is to provide a settings
 * template, with replacement patterns which we will use to create
 * a new settings.php file.
 */
function install_get_settings_file_template() {
  return '

<?php
/**
 * @file
 * The settings file for FlightPath, containing database and other settings.
 *
 * Once you have made changes to this file, it would be best to change
 * the permissions to "read-only" to prevent unauthorized users
 * from altering it.
 */

// Set the PHP error reporting level for FlightPath.  In this case,
// only show us errors and warnings. (Hide "notice" and "strict" messages)
error_reporting(E_ALL ^ E_NOTICE ^ E_STRICT);

// Set the PHP max time limit which any one page is allowed to take up while
// running.  The default is 30 seconds.  Change this value (or remove it)
// as needed.
set_time_limit(300);  // 300 seconds = 5 minutes.


////////////////////////////////////
// All system settings will be placed (at the end of this script)
// into a $GLOBALS variable, but for now will be placed into the
// array "$system_settings", defined below:

$system_settings = array();


////////////////////////////////////
// !!!  *** IMPORTANT !!!  ***    //
////////////////////////////////////
// If this variable is set to TRUE, then anyone who attempts to log in
// will have full, admin access.
// Only set this to TRUE if you have run into trouble, and cannot log into
// FlightPath normally!
// Otherwise, leave it set to FALSE!
$system_settings["GRANT_FULL_ACCESS"] = FALSE;
////////////////////////////////////

// This should be the actual filesystem path to the directory
// where FlightPath is installed.  Do NOT include a trailing slash!
// Ex: /var/www/public_html/flightpath  or, for Windows: C:/htdocs/flightpath
// ** Depending on your webserver, you may be required to use forward-slashes! **
// use the following line to help you figure out the fileSystemPath, by seeing
// what the path is to this file:
// print "<br>System path to settings.php: " . __FILE__ . "<br><br>";
$system_settings["file_system_path"] = "%FILE_SYSTEM_PATH%";

// The base URL is the actual URL a user would type to visit your site.
// Do NOT enter a trailing slash!
// Ex:  http://localhost/flightpath
$system_settings["base_url"] = "%BASE_URL%";

// The basePath is related to the baseURL.  It is the part of the URL which comes after
// your domain name.
// It MUST begin with a preceeding slash.
// Ex: If your site is example.com/dev/flightpath, then you should
// enter  "/dev/flightpath".  If you are hosting on a bare domain name (https://abc.example.com/)
// then simply enter "/"
$system_settings["base_path"] = "%BASE_PATH%";


////////////////////////////////////
// *** Database-related settings ***
////////////////////////////////////
$db_host = "%DB_HOST%"; // domain/ip address of the mysql host. ex: localhost, or 10.10.1.1, or db.example.com
$db_port = "%DB_PORT%"; // default for mysql/mariadb is 3306
$db_name = "%DB_NAME%"; // Name of the actual database where flightpath\'s tables are located.
$db_user = "%DB_USER%";
$db_pass = "%DB_PASS%";


////////////////////////////////////
// *** Cron-related ***           //
////////////////////////////////////
// If you wish to use cron.php (which will call every module\'s
// hook_cron() function), you may set up a cron job like this:
//      php cron.php security_token_string

// Cron Security Token:  This is something which
// must be the first argument passed to cron.php.  It can be any continuous
// string of *alpha-numeric* characters.
// This is a security measure to prevent unauthorized users (or web-users) from
// running cron.php, and is REQUIRED!
// For example, if the token is CRON_TOKEN then to run the script you would need
// to use:   https://example.com/cron.php?t=CRON_TOKEN
//
// In Linux/Unix, you can use the following in your system crontab to run the FlightPath
// cron every 10 minutes:
//    */10 * * * * wget -O - -q -t 1 https://example.com/cron.php?t=CRON_TOKEN
// See the System status page (/admin/config/status) for more instructions.
//
// The following cron_security_token has been randomly generated:

$system_settings["cron_security_token"] = "%CRON_SECURITY_TOKEN%";


////////////////////////////////////
// *** Encryption-related ***     //
////////////////////////////////////
// The encryption module is enabled by default, and requires an encryption key string to function
// correctly. It should be random characters and at least 32 characthers.
// You may also specify a key file.  See admin/config/encryption for more information.
//
// You should PRINT this encryption string, as well as the hash protocol and cipher algorithm
// in use (see admin/config/encryption) and store in a safe place.  If the encryption key string
// is lost, you will not be able to decrypt previously encrypted values/files.
//
// The encryption key string below has been randomly generated:

$GLOBALS["encryption_key_string"] = "%ENCRYPTION_KEY_STRING%";


////////////////////////////////////////////
/// *** Custom Settings? ***             ///
////////////////////////////////////////////
// If you have any custom settings you wish to add to this file, do so here.
//
// As long as you place your settings in a uniquely named $GLOBALS variable, it will be accessible on every page load
// throughout FlightPath.
//
// For example:
//     $GLOBALS["fp_my_custom_module_settings"]["secret_string"] = "Shhh... This is a secret.";
//
// If you are unsure what this might be used for, leave this section blank.





/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////
// END OF SETTINGS FILE /////////////
/////////////////////////////////////
// *** Do not alter or remove below this line!
/////////////////////////////////////
/////////////////////////////////////
/////////////////////////////////////
// This will load the contents of the variables
// table into the $system_settings variable.  These are extra settings
// which were set via the web, usually in the Admin Console.


// Have the database credentials changed since the last time we connected? If so, we need to unset parts of the SESSION
// and start over.  (It means the developer has moved the database or changed the port, etc.)

$db_fingerprint = sha1($db_host . $db_port . $db_user . $db_pass . $db_name);
if (isset($_SESSION["fp_db_fingerprint"]) && $_SESSION["fp_db_fingerprint"] !== $db_fingerprint) {
  unset($_SESSION["fp_db_host_ip"]);
}
$_SESSION["fp_db_fingerprint"] = $db_fingerprint;



// Connection by IP address is fastest, so let\'s always try to do that.
// It can be time-consuming to convert our hostname to IP address.  Cache it in our SESSION
if (isset($_SESSION["fp_db_host_ip"])) {
  $db_host_ip = $_SESSION["fp_db_host_ip"];
}
else {
  // Convert our db_host into an IP address, then save to simple SESSION cache.
  $db_host_ip = trim(gethostbyname($db_host));
  if (!$db_host_ip) $db_host_ip = $db_host;
  $_SESSION["fp_db_host_ip"] = $db_host_ip;
}

// Connect using PDO
$GLOBALS["pdo"] = new PDO("mysql:host=$db_host_ip;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass,
  array(
    PDO::MYSQL_ATTR_LOCAL_INFILE => TRUE,
  ));
// Set our error handling...  (using "silent" so I can catch errors in try/catch and display them, email, etc, if wanted.)
$GLOBALS["pdo"]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$res = $GLOBALS["pdo"]->prepare("SELECT * FROM modules WHERE enabled = 1
              ORDER BY weight, name");
$res->execute();
while ($cur = $res->fetch(PDO::FETCH_ASSOC)) {
  $system_settings["modules"][$cur["name"]] = $cur;
}


// We want to make sure the "system" module is enabled, so we will hard-code
// its values.
if ($system_settings["modules"]["system"]["enabled"] != 1) {
  $system_settings["modules"]["system"]["path"] = "modules/system";
  $system_settings["modules"]["system"]["enabled"] = 1;
}



////////////////////////////////////////////
////////////////////////////////////////////
// This must appear at the VERY end!  Nothing involving "system_settings" should come after this....
//
// Assign our system_settings to the GLOBALS array so we can access it anywhere in FlightPath.
$GLOBALS["fp_system_settings"] = $system_settings;

//////////////////////////////////////////////
//////////////////////////////////////////////
// PUT NOTHING BELOW THIS LINE ///////////////

';

}




/**
 * Displays the form to let a user set up a new database
 */
function install_display_db_form($msg = "") {
  global $lang;


  $db_name = $db_host = $db_port = $db_user = $db_pass = $admin_pass = $admin_pass2 = $admin_name = $admin_email = '';

  if (isset($_POST['db_name'])) {
    $db_name = $_POST["db_name"];
    $db_host = $_POST["db_host"];
    $db_port = $_POST["db_port"];
    $db_user = $_POST["db_user"];
    $db_pass = $_POST["db_pass"];

    $admin_pass = $_POST["admin_pass"];
    $admin_pass2 = $_POST["admin_pass2"];
    $admin_name = $_POST["admin_name"];
    $admin_email = $_POST["admin_email"];
  }


  if ($db_port == "") $db_port = "3306";

  $pC = "";

  $pC .= "<h2 class='title'>" . st("Setup Database and Admin") . "</h2>$msg
          <p>" . st("You should have already set up a database and database user
                      (with all privileges except Grant) for FlightPath.
                      <br><br>
                      <strong>Required:  The database default Character set must be 'utf8mb4'.</strong>
                      <br><br>
                      Please enter database credentials and information below.") . "</p>

          <hr>
          <form action='install.php?lang=$lang' method='POST'>
          <input type='hidden' name='perform_action' value='install'>
          <table border='0' cellpadding='3' style='margin-left: 20px;'>

            <tr>
              <td colspan='2'><b>" . st("FlightPath administrator information") . "</b></td>
            </tr>


            <tr>
              <td valign='top'>" . st("Admin Username:") . "</td>
              <td valign='top'><input type='text' name='admin_name' value='$admin_name' size='15' maxlength='50'> Ex: admin</td>
            </tr>

            <tr>
              <td valign='top'>" . st("Admin Email Address:") . "</td>
              <td valign='top'><input type='text' name='admin_email' value='$admin_email' size='20'></td>
            </tr>


            <tr>
              <td valign='top'>" . st("Admin Password:") . "</td>
              <td valign='top'><input type='password' name='admin_pass' value='$admin_pass' size='20'></td>
            </tr>

            <tr>
              <td valign='top'>" . st("Re-enter Password:") . "</td>
              <td valign='top'><input type='password' name='admin_pass2' value='$admin_pass2' size='20'></td>
            </tr>


            <tr>
              <td colspan='2'><hr>
                <b>" . st("Database information") . "</b></td>
            </tr>

            <tr>
              <td valign='top'>" . st("Database Name:") . "</td>
              <td valign='top'><input type='text' name='db_name' value='$db_name' size='50'></td>
            </tr>

            <tr>
              <td valign='top'>" . st("Database Host/IP:") . "</td>
              <td valign='top'><input type='text' name='db_host' value='$db_host' size='50'></td>
            </tr>

            <tr>
              <td valign='top'>" . st("Database Port:") . "</td>
              <td valign='top'><input type='text' name='db_port' value='$db_port' size='10'> Ex: 3306</td>
            </tr>

            <tr>
              <td valign='top'>" . st("Database Username:") . "</td>
              <td valign='top'><input type='text' name='db_user' value='$db_user' size='50'></td>
            </tr>

            <tr>
              <td valign='top'>" . st("Database Password:") . "</td>
              <td valign='top'><input type='password' name='db_pass' value='$db_pass' size='50'></td>
            </tr>

          </table>
          <br><br>
          <input type='submit' value='" . st("Install") . "'>
          <br>
          <b>" . st("Please click only once.  May take several seconds to install.") . "
          </form>";




  // Display the screen
  $page_content = $pC;
  install_output_to_browser($page_content);


}




/**
 * Check for missing requirements of the system.
 *
 * Returns an array of missing requirements which the user must fix before
 * installation can continue.
 *
 */
function install_check_requirements() {
  $rtn = array();

  // Is the /custom directory writable?
  if (!is_writable("custom")) {
    $rtn[] = st("Please make sure the <em>/custom</em> directory is writable to the web server.
               <br>Ex: chmod 777 custom");
  }


  if (count($rtn) == 0) return FALSE;
  return $rtn;
}


/**
 * Displays the requirements on screen for the user.
 */
function install_display_requirements($req_array) {
  global $lang;

  $pC = "";

  $pC .= "<h2 class='title'>" . st("Check Requirements") . "</h2>
          <p>" . st("The following requirements must be fixed before installation of FlightPath
                  can continue.") . "</p>";

  foreach ($req_array as $req) {
    $pC .= "<div style='padding: 5px; margin: 10px; border: 1px solid red;
                    font-family: Courier New, serif; font-size:0.9em'>$req</div>";
  }

  $pC .= "<p>" . st("Please fix the problems listed, then reload to try again:") . "
          <br><a href='install.php?lang=$lang'>" . st("Click here to try again") . "</a>";

  // Display the screen
  $page_content = $pC;
  install_output_to_browser($page_content);
}





function install_display_lang_selection() {
  $html = "";

  $html .= "<h2 class='title'>Install FlightPath</h2>
          Please follow the instructions on the following pages to complete
          your installation of FlightPath.

            <h3 class='title'>Select language</h3>
          Please begin by selecting an installation language.
          <ul>
            <li><a href='install.php?lang=en'>English</a></li>
          </ul>
          <br><br><br>
          <b>Please note:</b> By proceeding with this installation, you affirm that you
          have read, understand, and agree with the LICENSE.txt file and the COPYRIGHT.txt file
          included with this software package.
          Specifically, that you accept and agree with the GNU GPL license, and with the statement
          that this software is provided to you without warranty.
          <br><br>
          If you have any questions or need professional help with hosting, maintenance, or configurations,
          please visit <a href='https://flightpathacademics.com/contact/' target='_blank'>https://flightpathacademics.com/contact/</a>.";

  // Display the screen
  install_output_to_browser($html);
}





function install_output_to_browser($page_content, $page_title = "Install FlightPath 7") {
  print "
          <html>
            <head>
              <meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
              <title>$page_title</title>
              <style>

                  body
                  {
                    font-family: Arial, Helvetica, sans serif;
                    background-color: white;
                  }


                  .top-banner {
                    width: 820px;  /* the size of page-content + padding */
                    margin-left: auto;
                    margin-right: auto;
                    margin-bottom: 1.2em;
                    background-color: white;
                    border: 3px solid #ccc;
                    border-radius: 5px;

                  }

                  /* Page content */
                  .page-content {
                    width: 800px;
                    min-height: 400px;
                    margin-top: 50px;
                    margin-left: auto;
                    margin-right: auto;
                    padding-left: 10px;
                    padding-right: 10px;
                    padding-top: 5px;
                    padding-bottom: 50px;
                    border: 1px solid #ccc;
                    box-shadow: 1px 1px 50px #ccc;
                    border-radius: 5px;
                    background-color: white;
                  }
                  .page-is-popup .page-content {
                    min-height: 250px;
                    width: 90%;
                  }


              </style>
            </head>
            <body>
                    <div class='page-content'>
                          $page_content
                    </div>
            </body>
          </html>
          ";


}






