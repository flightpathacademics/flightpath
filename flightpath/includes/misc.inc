<?php
/**
 * @file
 * This file contains misc functions for FlightPath
 */

 

 /**
  * Returns back the "friendly" timezone string if we have one.
  */
function friendly_timezone($str) {
  
  $arr = array(
                'America/Chicago' => 'Central Time - US & Canada',
                'America/Los_Angeles' => 'Pacific Time - US & Canada',
                'America/New_York' => 'Eastern Time - US & Canada',
                'America/Denver' => 'Mountain Time - US & Canada',
                'America/Phoenix' => 'Arizona Time',
                'America/Anchorage' => 'Alaska Time',
                'America/Adak' => 'Hawaii Time',
                'Pacific/Honolulu' => 'Hawaii Time no DST',        
              ); 
  
  
  if (isset($arr[$str])) return $arr[$str];
  
  return $str;
  
  
} 




/**
 * Convenience function for getting an array value by the specified key,
 * or, return $default_val (default empty string) if it doesn't exist.
 * Meant to be similar to the @see variable_get() functionality.
 */
function array_get($arr, $key, $default_val = "") {
  
  if (isset($arr[$key])) return $arr[$key];
  
  return $default_val;
}



 

// From https://php.watch/versions/8.2/utf8_encode-utf8_decode-deprecated
// In PHP 8.2, utf8_encode and _decode are deprecated.  This code replicates
// the same functionality as a drop-in replacement.
function fp_utf8_encode(string $s) {
    $s .= $s;
    $len = \strlen($s);

    for ($i = $len >> 1, $j = 0; $i < $len; ++$i, ++$j) {
        switch (true) {
            case $s[$i] < "\x80": $s[$j] = $s[$i]; break;
            case $s[$i] < "\xC0": $s[$j] = "\xC2"; $s[++$j] = $s[$i]; break;
            default: $s[$j] = "\xC3"; $s[++$j] = \chr(\ord($s[$i]) - 64); break;
        }
    }

    return substr($s, 0, $j);
}

/**
 * @see fp_utf8_encode for an explanation of why this function exists. 
 */
function fp_utf8_decode(string $string) {
    $s = (string) $string;
    $len = \strlen($s);

    for ($i = 0, $j = 0; $i < $len; ++$i, ++$j) {
        switch ($s[$i] & "\xF0") {
            case "\xC0":
            case "\xD0":
                $c = (\ord($s[$i] & "\x1F") << 6) | \ord($s[++$i] & "\x3F");
                $s[$j] = $c < 256 ? \chr($c) : '?';
                break;

            case "\xF0":
                ++$i;
                // no break

            case "\xE0":
                $s[$j] = '?';
                $i += 2;
                break;

            default:
                $s[$j] = $s[$i];
        }
    }

    return substr($s, 0, $j);
}


 
 
 
// source: Laravel Framework  
// (helper functions if we are not running PHP 8.)
// https://github.com/laravel/framework/blob/8.x/src/Illuminate/Support/Str.php
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle !== '' && substr($haystack, -strlen($needle)) === (string)$needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
} 
 
 


/**
 * Returns back a FAPI-compatible array of all term codes for the specified years, inclusive 
 */
function fp_get_terms_by_year_range($start_year, $end_year, $school_id = 0, $bool_include_term_id_in_description = TRUE) {
  
  // Let the user select a term for this school (starting at now - $min years, and going out to now + max years)
  $options = array();    
  $temp = get_term_structures($school_id);
    
  for ($year = $start_year; $year <= $end_year; $year++) {
    foreach ($temp as $k => $details) {
      $term_id = $year . $k;
      
      // Adding year as the first element causes it to separate out the terms by year in the pulldown.  Makes it cleaner.
      $options[$year][$term_id] = "[$term_id] &nbsp;" . get_term_description($term_id, FALSE, $school_id);
      
      if (!$bool_include_term_id_in_description) {
        $options[$year][$term_id] = get_term_description($term_id, FALSE, $school_id);
      }
      
    }
    
  } //for t
  
  ksort($options);
    
  return $options;

  
  
  
  
} 



/**
 * Returns an array (suitable for form api) of departments on campus which
 * faculty/staff can be members of.
 */
function fp_get_departments($school_id = 0, $bool_get_all_by_schools = FALSE) {
    
  $rtn = array();
  
  
  $cache_key = md5(serialize(func_get_args()));
  
  // Get from cache if already loaded once this page load
  if (isset($GLOBALS['fp_cache_departments'][$cache_key])) {    
    return $GLOBALS['fp_cache_departments'][$cache_key];
  }
    
  
  if (!$bool_get_all_by_schools) {
    $val = variable_get_for_school('departments', '', $school_id);  
    $lines = explode("\n", $val);
    foreach ($lines as $line) {
      $line = trim($line);
      if (!$line) continue;
      
      $temp = explode("~", $line);    
      $rtn[trim($temp[0])] = trim($temp[1]);    
    }
  }
  else if (module_enabled('schools') && $bool_get_all_by_schools == TRUE) {
    // We should return a multi-dimensional array where the school name is first, followed by dept_code.
    // Ex: $rtn['SCHOOL_ABC']['ENGL'] = "English";
    // We would need to go through all of our schools in a for loop first.    
    $schools = schools_get_school_definitions();
    foreach ($schools as $school_id => $throw_away) {
      $school_code = schools_get_school_code_for_id($school_id);
      
      if ($school_id == 0) $school_code = "- Default -";
      
      $val = variable_get_for_school('departments', '', $school_id);  
      $lines = explode("\n", $val);
      foreach ($lines as $line) {
        $line = trim($line);
        if (!$line) continue;
        
        $temp = explode("~", $line);    
        $rtn[$school_code][trim($temp[0])] = trim($temp[1]);    
      }
      
      
    }  
    
  }   
    
  
  
  
  
  $GLOBALS['fp_cache_departments'][$cache_key] = $rtn;  // store in cache
  
  return $rtn;  
  
}







 
 

/**
 * This function will use the "Numeric to Letter Grade" setting in the School settings to
 * translate the given grade (if it is numeric) to a letter grade.  Otherwise, it will return
 * the grade as-is.
 */
function fp_translate_numeric_grade($grade, $school_id = 0) {
  
  
  $only_grade = $grade;
  
  // We may have included MID at the end of our numeric grade, and if so, it's a midterm grade.
  
  $bool_midterm = FALSE;
  if (strstr($grade, "MID")) {
    $bool_midterm = TRUE;
    $only_grade = trim(str_replace("MID", "", $grade));
  }
    
    
  if (!is_numeric($only_grade)) return $grade; // already a letter, return the original grade.

  
  $translate = array();
  
      
  // Get our translation array from globals cache, if there.
  if (isset($GLOBALS['fp_translate_numeric_grade'][$school_id])) {
    $translate = $GLOBALS['fp_translate_numeric_grade'][$school_id];
  }
  else {
    $temp = trim(variable_get_for_school("numeric_to_letter_grades", "", $school_id));
    if ($temp === '') return $grade;
    
    $temp = explode("\n", $temp);
    foreach ($temp as $line) {
      $line = trim($line);
      if ($line == "") continue;
  
      $tokens = explode("~", $line);
      $low = floatval(trim($tokens[0]));
      $high = floatval(trim($tokens[1]));
      $letter = trim($tokens[2]);
      
      $translate[$low][$high] = $letter;
          
    } // foreach
    
    $GLOBALS['fp_translate_numeric_grade'][$school_id] = $translate;
  }
  
  
  // Okay, now that we have our "translate" array, we can do that with the grade.
  $grade = floatval($grade);  
  foreach ($translate as $low => $details) {
    foreach ($translate[$low] as $high => $letter) {      
      if ($grade >= $low && $grade <= $high) {
        if ($bool_midterm) $letter .= "MID";
        return $letter;
      }
    }
  }
  
  // Else, return back the original grade.
  return $grade;
}


 
 
 
 
/**
 * Re-order the _FILES array for multiple files, to make it easier to work with.  From:
 * http://php.net/manual/en/features.file-upload.multiple.php
 * 
 * To use:
 *   $myfiles = fp_re_array_files($_FILES['fieldname'])
 * 
 * 
 */
function fp_re_array_files($file_post) {

  $file_ary = array();
  $file_count = count($file_post['name']);
  $file_keys = array_keys($file_post);

  for ($i=0; $i<$file_count; $i++) {
      foreach ($file_keys as $key) {
          $file_ary[$i][$key] = $file_post[$key][$i];
      }
  }

  return $file_ary;
} 
 
 
// From: https://www.php.net/manual/en/function.timezone-offset-get.php
/**    Returns the offset from the origin timezone to the remote timezone, in seconds.
*    @param $remote_tz;
*    @param $origin_tz; If null the servers current timezone is used as the origin.
*    @return int;
*/
function get_timezone_offset($remote_tz, $origin_tz = null) {
    if($origin_tz === null) {
        if(!is_string($origin_tz = date_default_timezone_get())) {
            return false; // A UTC timestamp was returned -- bail out!
        }
    }
    $origin_dtz = new DateTimeZone($origin_tz);
    $remote_dtz = new DateTimeZone($remote_tz);
    $origin_dt = new DateTime("now", $origin_dtz);
    $remote_dt = new DateTime("now", $remote_dtz);
    $offset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt);
    return $offset;
} 
 
 
 
/**
 * 
 * From:  https://stackoverflow.com/questions/1369936/check-to-see-if-a-string-is-serialized
 * 
 *
 * Check if a string is serialized
 *
 * @param string $string
 *
 * @return bool
 */
function is_serialized_string($string)
{
    return ($string == 'b:0;' || @unserialize($string) !== false);
} 
 



// From: https://gist.github.com/ryanboswell/cd02add580ddce012469
/**
 * The point of this function is to convert between UTC (what we expect all times to start with.).  If we're coming
 * from the the database or a time() function, it's UTC.  The "end_timezone_string" should be the user's preferred timezone.
 * 
 * if end_timezone_string == null, then we will use the user's selected timezone.  If that isn't set, we use they system's.
 * 
 * As a convenenience, if the data_format we will get back a formatted date.  Otherwise we'll get back a timestamp. 
 */
function convert_time($time_to_convert = 0, $start_timezone_string = "UTC", $end_timezone_string = NULL, $date_format = null ) {
  
  // We require a start time
  if( empty( $time_to_convert ) ){
    return false;
  }
  
  if ($end_timezone_string == NULL) {
    $end_timezone_string = fp_get_user_timezone();
  }
    
  // If the two timezones are different, find the offset
  if( $start_timezone_string != $end_timezone_string ) {
    // Create two timezone objects, one for the start and one for
    // the end
    $dateTimeZoneStart = new DateTimeZone( $start_timezone_string );
    $dateTimeZoneEnd = new DateTimeZone( $end_timezone_string );
    
    // Create two DateTime objects that will contain the same Unix timestamp, but
    // have different timezones attached to them.
    $dateTimeStart = new DateTime("now", $dateTimeZoneStart );
    $dateTimeEnd = new DateTime("now", $dateTimeZoneEnd );
    
    // Calculate the UTC offset for the date/time contained in the $dateTimeStart
    // object, but using the timezone rules as defined for the end timezone ($dateTimeEnd)
    $timeOffset = $dateTimeZoneEnd->getOffset($dateTimeStart);
    
    // If we are converting FROM non-utc TO UTC, then this logic doesn't work!
    // We need to basically grab the reverse logic...
    if ($start_timezone_string != 'UTC' && $end_timezone_string == 'UTC') {
      $x = $dateTimeZoneStart->getOffset($dateTimeEnd);      
      $timeOffset = -$x;
    }
        
  } else {
    // If the timezones are the same, there is no offset    
    $timeOffset = 0;
  }
  
  // Convert the time by the offset
  $converted_time = $time_to_convert + $timeOffset;
  
  // If we have no given format, just return the time
  if( empty( $date_format ) ) {
    return $converted_time;
  }
  
  // Convert to the given date format
  return date( $date_format, $converted_time );
}




/**
 * Returns an array of all timezones PHP recognizes.
 * Inspired by code from: https://stackoverflow.com/questions/1727077/generating-a-drop-down-list-of-timezones-with-php
 * 
 * This code will return the common US timezones first, followed by the rest of the timezones that PHP is aware of.
 * 
 */
function get_timezones($bool_include_offset = FALSE) {

  $timezones = array();
  
  // These are the common names for the US timezones.  
  $us_desc_timezones = array(
    'America/New_York' => 'Eastern',
    'America/Chicago' => 'Central',
    'America/Denver' => 'Mountain',
    'America/Phoenix' => 'Mountain no DST',
    'America/Los_Angeles' => 'Pacific',
    'America/Anchorage' => 'Alaska',
    'America/Adak' => 'Hawaii',
    'Pacific/Honolulu' =>  'Hawaii no DST',  
  );
  
  
  $us_timezones = array_keys($us_desc_timezones);
  

  $timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);

  // Place the US timezones at the top of the list.
  $timezones = array_merge($us_timezones, $timezones);   

       
  $timezone_offsets = array();
  foreach( $timezones as $timezone )
  {
      $tz = new DateTimeZone($timezone);
      $timezone_offsets[$timezone] = $tz->getOffset(new DateTime);
  }


  $timezone_list = array();
  foreach( $timezone_offsets as $timezone => $offset )
  {
    $offset_prefix = $offset < 0 ? '-' : '+';
    $offset_formatted = gmdate( 'H:i', abs($offset) );

    $pretty_offset = $extra = "";
    if ($bool_include_offset) $pretty_offset = "(UTC${offset_prefix}${offset_formatted}) ";

    if (isset($us_desc_timezones[$timezone])) {
      $extra = "  - (" . $us_desc_timezones[$timezone] . ")";
    }
    
    $disp_timezone = str_replace("_", " ", $timezone);
    $timezone_list[$timezone] = "$pretty_offset$disp_timezone$extra";
  }

  return $timezone_list;  
}


 
 
/**
 * This is our custom error handler, which will intercept PHP warnings, notices, etc, and let us
 * display them, log them, etc.
 * 
 * See https://www.php.net/manual/en/function.set-error-handler.php
 */ 
function _fp_error_handler($error_level, $message, $filename, $line, $context = array()) {
  global $user;

  // In case we have not loaded bootstrap.inc yet.  
  @define ('WATCHDOG_NOTICE', 5);
  @define ('WATCHDOG_ALERT', 1);
  @define ('WATCHDOG_ERROR', 3);
  @define ('WATCHDOG_DEBUG', 7);  
  
  $PHP_8_0_SUPPRESSED_ERROR = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_PARSE;
  $er = error_reporting();
  if ($er === 0 || $er === $PHP_8_0_SUPPRESSED_ERROR) { return false;} // suppressed with @-operator  (0 for pre-php8, the variable for 8.0)
  
  
   
  $err_name = _fp_map_php_error_code($error_level); 
  if (is_string($err_name) && stristr($err_name, 'notice')) return FALSE; // don't care about Notices. 

  $watchdog_type = "php_error";
  $watchdog_severity = WATCHDOG_ERROR;
    
  
  if (is_string($err_name) && stristr($err_name, 'warning')) {
    $watchdog_type = "php_warning";
    $watchdog_severity = WATCHDOG_ALERT;
  } 

  if (is_string($err_name) && stristr($err_name, 'recoverable error')) {
    $watchdog_type = "php_warning";
    $watchdog_severity = WATCHDOG_ALERT;
  } 
  
  $arr = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 10);  // limit of 10 levels deep so as not to eat up all the memory.
  
  // fpm() only displays for privileged users
  
  // We don't want to bother repeating the same message more than once for errors.  The following code
  // will prevent that.
  // TODO: Make this a setting?
  $errmsg = $err_name . ": $message<br>... ($line) $filename";
  $errmsg_hash = hash('sha256', $errmsg);
  
  if (!isset($GLOBALS['fp_error_handler__already_shown_error'][$errmsg_hash])) {  
    fpm($errmsg);
    fpm($arr);
    $GLOBALS['fp_error_handler__already_shown_error'][$errmsg_hash] = TRUE;
  }
  else {
    return;  // we've already displayed this error message; we can harmlessly return.
  }
  
  

  // Before we watchdog or mail this backtrace, make sure no field called "password" is in plain text.
  foreach ($arr as $c => $trace) {
    if (is_array($trace) && isset($trace['args'])) {
      foreach ($trace['args'] as $k => $details) {
        if (is_array($details)) {
          foreach ($details as $j => $val) {
            if (stristr($j, 'password')) {
              $arr[$c]['args'][$k][$j] = "--PASSWORD HIDDEN IN LOG--";
            }
          }
        }
      }
    }
  }
  

  $hostname = php_uname('n') . ' - ' . $GLOBALS['fp_system_settings']['base_url'];
  
  $user_name = '';
  $user_id = 0;
  if (!$user) {
    $user_name = 'Anonymous';    
  }
  else {
    $user_name = $user->name;
    $user_id = $user->id;
  }
  
  $msg = "";
  $msg .= "USER:   $user_name ($user_id) \n";
  $msg .= "SERVER:   $hostname \n";
  $msg .= "DATE:     " . format_date(convert_time(time())) . "\n";
  $msg .= "SEVERITY: $err_name \n";
  $msg .= "--------------------------\n\n";
  $msg .= "$err_name: $message \n\n";
  $msg .= "... ($line) $filename\n\n";
  
  $emsg = $msg;   // We don't need the full backtrace for our "email" message.
  
  $msg .= "Backtrace: <pre>\n";
  $msg .= print_r($arr, TRUE);
  $msg .= "\n\n</pre>";


  // Because we can have EXTREMELY long $msg due to the backtrace, limit it to a reasonable number.
  if (strlen($msg) > 10000) {
    $msg = substr($msg, 0, 10000) . "\n\n\n... truncated to 10,000 characters to save space.  Full length was: " . strlen($msg);
  }

  watchdog($watchdog_type, $msg, array(), $watchdog_severity);
  
  if (@intval($user_id) !== 1) {
    // We are NOT the admin user (user id 1).  (No need to email, since it would appear on screen with the fpm() calls earlier.
    
    // Should we email someone of php errors?
    $tomail = trim(variable_get("notify_php_error_email_address", ""));
      
    if ($tomail != "") {
      //fp_mail($tomail, "PHP Error in FlightPath", $msg);
      
      // We should add this to our "to email" file, which will get emailed to this mail address later on.
      $error_files_dir = fp_get_files_path() . "/" . variable_get("private_files_directory", "private");
      if (!is_dir($error_files_dir)) {
        $t = mkdir($error_files_dir);
        if (!$t) {
          watchdog('system', "Unable to create private files directory @priv", array("@priv" => $error_files_dir), WATCHDOG_ERROR);
        }
      }

      $x = file_put_contents($error_files_dir . '/fp_php_errors_to_email_log.txt', $emsg . "\n------------------------------------------------\n\n", FILE_APPEND);
      if (!$x) {
        watchdog('system', "Unable to write PHP error to email log file", array(), WATCHDOG_ERROR);
      }

      // Check to see if there is a .htaccess file there; create if not.
      if (!file_exists($error_files_dir . '/.htaccess')) {
        $x = file_put_contents($error_files_dir . '/.htaccess', system_get_private_htaccess());
        if (!$x) {
          watchdog('system', "Unable to create .htaccess file in Private Files Directory. Permission issue?", array(), WATCHDOG_ERROR);
        }
        
      }
                  
    }
    
  }
  
} // _fp_error_handler
  
 
 

/**
* Map an error code into an Error word
*
* @param int $code Error code to map
* @return array Array of error word, and log location.
*/
function _fp_map_php_error_code($code) {
    $error = '';
    switch ($code) {
        case E_PARSE:
        case E_ERROR:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_USER_ERROR:
            $error = 'Fatal Error';            
            break;
        case E_WARNING:
        case E_USER_WARNING:
        case E_COMPILE_WARNING:                      
        case E_RECOVERABLE_ERROR:
            $error = 'Warning';            
            break;          
        case E_NOTICE:
        case E_USER_NOTICE:
            $error = 'Notice';            
            break;
        case E_STRICT:
            $error = 'Strict';            
            break;
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
            $error = 'Deprecated';
            
            break;
        default :
            break;
    }
    
    return $error;
}

 
 

/**
 * Send an email.  Drop-in replacement for PHP's mail() command,
 * but can use SMTP protocol if enabled.
 * 
 * For attachments (only for use in SMTP), the array can be one of two methods:
 * 
 * (1)
 *   $arr["full_filename"] => "filename"
 * or
 * (2)
 *   $arr['filename'] = "string that makes up attachment"
 * 
 * For method #2, $bool_string_attachment must be set to TRUE. 
 * 
 */
function fp_mail($to, $subject, $msg, $bool_html = FALSE, $attachments = array(), $bool_string_attachment = FALSE) {
  
    
  // TODO: In the future, check to see if there are any other modules which invoke a hook to intercept mail.
  
  
  // The reason we do this md5 check is so that we don't try to send identical emails over and over.  This can happen if we are trying
  // to email regarding the mysql server being down, and when we try to do a "watchdog", which has to use the mysql server,
  // we try to send ANOTHER error, then we are right back here and try to send ANOTHER email, etc, etc.  
  $md5_check = md5($to . $subject . $msg . time());
  if (isset($_SESSION['fp_mail_last_sent_md5'])) {
    if ($_SESSION['fp_mail_last_sent_md5'] == $md5_check) {
      return;
    }
  }  
  $_SESSION['fp_mail_last_sent_md5'] = $md5_check;
  
  
  watchdog('fp_mail', t("Sending mail to @to, subject: @subject.  Last sent md5: @md5", array("@to" => $to, "@subject" => $subject, "@md5" => $md5_check)), array(), WATCHDOG_DEBUG);
  
  
  if (module_enabled('smtp')) {
    smtp_mail($to, $subject, $msg, $bool_html, $attachments, $bool_string_attachment);
    return;
  }
  else {
    
    $headers = array();
    if ($bool_html) {
      // To send HTML mail, the Content-type header must be set
      $headers[] = 'MIME-Version: 1.0';
      $headers[] = 'Content-type: text/html; charset=iso-8859-1';      
    }    
    
    mail($to, $subject, $msg, implode("\r\n", $headers));
  }
  
  
  
} // fp_mail

 
 
 
/**
 * Returns the component of the page's path.
 * 
 * When viewing a page at the path "admin/structure/types", for example, arg(0) returns "admin", arg(1) returns "structure", and arg(2) returns "types".
 * 
 */ 
function arg($index) {
  
  $q = $_REQUEST["q"];
  $temp = explode("/", $q);

  $rtn = fp_trim(@$temp[$index]);
  
  return $rtn;
  
} 
 
 

/**
 * This function uses CURL to get the simple contents of a URL, whether http or https.
 */
function fp_url_get_contents($url) {
  
  /*
  $ch = curl_init();
  
  curl_setopt( $ch, CURLOPT_AUTOREFERER, TRUE );
  curl_setopt( $ch, CURLOPT_HEADER, 0 );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
  curl_setopt( $ch, CURLOPT_URL, $url );
  curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, TRUE );

  $data = curl_exec( $ch );
  curl_close( $ch );

  return $data;  
  */
  
  // Make use of the superior fp_http_request instead.
  
  $res = fp_http_request($url);
  if (is_object($res) && isset($res->data)) {
    return $res->data;
  }
  
  return FALSE;
  
}


  
 
/**
 * Send a request through the Internet and return the result as an object.
 * 
 * This is a modified copy of the Drupal 6 function drupal_http_request(),
 * taken from here: http://api.drupal.org/api/drupal/includes!common.inc/function/drupal_http_request/6
 */
function fp_http_request($url, $headers = array(), $method = 'GET', $data = NULL, $retry = 3, $timeout = 30.0) {
  global $db_prefix;

  $result = new stdClass();

  // Parse the URL and make sure we can handle the schema.
  $uri = parse_url($url);

  if ($uri == FALSE) {
    $result->error = 'unable to parse URL';
    $result->code = -1001;
    return $result;
  }

  if (!isset($uri['scheme'])) {
    $result->error = 'missing schema';
    $result->code = -1002;
    return $result;
  }

  timer_start(__FUNCTION__);

  switch ($uri['scheme']) {
    case 'http':
    case 'feed':
      $port = isset($uri['port']) ? $uri['port'] : 80;
      $host = $uri['host'] . ($port != 80 ? ':' . $port : '');
      $fp = @fsockopen($uri['host'], $port, $errno, $errstr, $timeout);
      break;
    case 'https':
      // Note: Only works for PHP 4.3 compiled with OpenSSL.
      $port = isset($uri['port']) ? $uri['port'] : 443;
      $host = $uri['host'] . ($port != 443 ? ':' . $port : '');
      $fp = @fsockopen('ssl://' . $uri['host'], $port, $errno, $errstr, $timeout);
      break;
    default:
      $result->error = 'invalid schema ' . $uri['scheme'];
      $result->code = -1003;
      return $result;
  }

  // Make sure the socket opened properly.
  if (!$fp) {
    // When a network error occurs, we use a negative number so it does not
    // clash with the HTTP status codes.
    $result->code = -$errno;
    $result->error = trim($errstr);

    // Log that this failed.
    watchdog("http_request", "fp_http_request failed! Perhaps the server cannot make requests?", array(), WATCHDOG_ERROR);

    return $result;
  }

  // Construct the path to act on.
  $path = isset($uri['path']) ? $uri['path'] : '/';
  if (isset($uri['query'])) {
    $path .= '?' . $uri['query'];
  }

  // Create HTTP request.
  $defaults = array(
    // RFC 2616: "non-standard ports MUST, default ports MAY be included".
    // We don't add the port to prevent from breaking rewrite rules checking the
    // host that do not take into account the port number.
    'Host' => "Host: $host", 
    'User-Agent' => 'User-Agent: FlightPath (+https://getflightpath.com/)',
  );

  // Only add Content-Length if we actually have any content or if it is a POST
  // or PUT request. Some non-standard servers get confused by Content-Length in
  // at least HEAD/GET requests, and Squid always requires Content-Length in
  // POST/PUT requests.
  $content_length = 0;
  if ($data) {
    $content_length = strlen($data);
  }
  if ($content_length > 0 || $method == 'POST' || $method == 'PUT' || $method == 'DELETE') {
    $defaults['Content-Length'] = 'Content-Length: ' . $content_length;
  }

  // If the server url has a user then attempt to use basic authentication
  if (isset($uri['user'])) {
    $defaults['Authorization'] = 'Authorization: Basic ' . base64_encode($uri['user'] . (!empty($uri['pass']) ? ":" . $uri['pass'] : ''));
  }

  foreach ($headers as $header => $value) {
    $defaults[$header] = $header . ': ' . $value;
  }

  $request = $method . ' ' . $path . " HTTP/1.0\r\n";
  $request .= implode("\r\n", $defaults);
  $request .= "\r\n\r\n";
  $request .= $data;

  $result->request = $request;

  // Calculate how much time is left of the original timeout value.
  $time_left = $timeout - timer_read(__FUNCTION__) / 1000;
  if ($time_left > 0) {
    stream_set_timeout($fp, floor($time_left), floor(1000000 * fmod($time_left, 1)));
    fwrite($fp, $request);
  }

  // Fetch response.
  $response = '';
  while (!feof($fp)) {
    // Calculate how much time is left of the original timeout value.
    $time_left = $timeout - timer_read(__FUNCTION__) / 1000;
    if ($time_left <= 0) {
      $result->code = HTTP_REQUEST_TIMEOUT;
      $result->error = 'request timed out';
      return $result;
    }
    stream_set_timeout($fp, floor($time_left), floor(1000000 * fmod($time_left, 1)));
    $chunk = fread($fp, 1024);
    $response .= $chunk;
  }
  fclose($fp);

  // Parse response headers from the response body.
  // Be tolerant of malformed HTTP responses that separate header and body with
  // \n\n or \r\r instead of \r\n\r\n.  See http://drupal.org/node/183435
  list($split, $result->data) = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);
  $split = preg_split("/\r\n|\n|\r/", $split);

  list($protocol, $code, $status_message) = explode(' ', trim(array_shift($split)), 3);
  $result->protocol = $protocol;
  $result->status_message = $status_message;

  $result->headers = array();

  // Parse headers.
  while ($line = trim((string) array_shift($split))) {
    list($header, $value) = explode(':', $line, 2);
    if (isset($result->headers[$header]) && $header == 'Set-Cookie') {
      // RFC 2109: the Set-Cookie response header comprises the token Set-
      // Cookie:, followed by a comma-separated list of one or more cookies.
      $result->headers[$header] .= ',' . trim($value);
    }
    else {
      $result->headers[$header] = trim($value);
    }
  }

  $responses = array(
    100 => 'Continue', 
    101 => 'Switching Protocols', 
    200 => 'OK', 
    201 => 'Created', 
    202 => 'Accepted', 
    203 => 'Non-Authoritative Information', 
    204 => 'No Content', 
    205 => 'Reset Content', 
    206 => 'Partial Content', 
    300 => 'Multiple Choices', 
    301 => 'Moved Permanently', 
    302 => 'Found', 
    303 => 'See Other', 
    304 => 'Not Modified', 
    305 => 'Use Proxy', 
    307 => 'Temporary Redirect', 
    400 => 'Bad Request', 
    401 => 'Unauthorized', 
    402 => 'Payment Required', 
    403 => 'Forbidden', 
    404 => 'Not Found', 
    405 => 'Method Not Allowed', 
    406 => 'Not Acceptable', 
    407 => 'Proxy Authentication Required', 
    408 => 'Request Time-out', 
    409 => 'Conflict', 
    410 => 'Gone', 
    411 => 'Length Required', 
    412 => 'Precondition Failed', 
    413 => 'Request Entity Too Large', 
    414 => 'Request-URI Too Large', 
    415 => 'Unsupported Media Type', 
    416 => 'Requested range not satisfiable', 
    417 => 'Expectation Failed', 
    500 => 'Internal Server Error', 
    501 => 'Not Implemented', 
    502 => 'Bad Gateway', 
    503 => 'Service Unavailable', 
    504 => 'Gateway Time-out', 
    505 => 'HTTP Version not supported',
  );
  // RFC 2616 states that all unknown HTTP codes must be treated the same as the
  // base code in their class.
  if (!isset($responses[$code])) {
    $code = floor($code / 100) * 100;
  }

  switch ($code) {
    case 200: // OK
    case 304: // Not modified
      break;
    case 301: // Moved permanently
    case 302: // Moved temporarily
    case 307: // Moved temporarily
      $location = $result->headers['Location'];
      $timeout -= timer_read(__FUNCTION__) / 1000;
      if ($timeout <= 0) {
        $result->code = HTTP_REQUEST_TIMEOUT;
        $result->error = 'request timed out';
      }
      elseif ($retry) {
        $result = fp_http_request($result->headers['Location'], $headers, $method, $data, --$retry, $timeout);
        $result->redirect_code = $result->code;
      }
      $result->redirect_url = $location;

      break;
    default:
      $result->error = $status_message;
  }

  $result->code = $code;
  return $result;
}
 

/**
 * Begin a microtime timer for later use.
 */
function timer_start($name) {
  global $timers;

  list($usec, $sec) = explode(' ', microtime());
  $timers[$name]['start'] = (float) $usec + (float) $sec;
  $timers[$name]['count'] = isset($timers[$name]['count']) ? ++$timers[$name]['count'] : 1;
}

/**
 * Works with the timer_start() function to return how long
 * it has been since the start.
 */
function timer_read($name) {
  global $timers;

  if (isset($timers[$name]['start'])) {
    list($usec, $sec) = explode(' ', microtime());
    $stop = (float) $usec + (float) $sec;
    $diff = round(($stop - $timers[$name]['start']) * 1000, 2);

    if (isset($timers[$name]['time'])) {
      $diff += $timers[$name]['time'];
    }
    return $diff;
  }
}


/**
 * Returns a random string of length len.
 */
function fp_get_random_string($len = 7, $alpha = TRUE, $numeric = TRUE, $symbols = FALSE) {
  
  $base = "";
  if ($alpha) {
    $base .= "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
  }
  if ($numeric) {
    $base .= "12345678901234567890";
  }
  if ($symbols) {
    $base .= "!@#$%^&*()_+!@#$%^&*()-=";
  }
  
  $str = "";
  for ($t = 0; $t < $len; $t++) {
    $base = str_shuffle($base);
    $str .= $base[0];
  }
  
  return $str;
  
}


 
/**
 * Call all modules which implement hook_clear_cache
 */
function fp_clear_cache() {
  // Find modules which implement hook_clear_cache
  $modules = modules_implement_hook("clear_cache");
  foreach ($modules as $module) {
    call_user_func($module . '_clear_cache');
  }  
} 
 
 
 
 
 
 
 
/**
Remove any possiblilty of a malicious attacker trying to inject
nonsense.
From:  https://paragonie.com/blog/2015/06/preventing-xss-vulnerabilities-in-php-everything-you-need-know
*/
function fp_no_html_xss($string) {
  return htmlentities($string, ENT_QUOTES, 'UTF-8');  
} 
 
 

 
// From: https://gist.github.com/hubgit/1322324
function repair_html($html){
  // hide DOM parsing errors
  libxml_use_internal_errors(true);
  libxml_clear_errors();

  // load the possibly malformed HTML into a DOMDocument
  $dom = new DOMDocument();
  $dom->recover = true;
  $rnd = mt_rand(9, 9999) . time();  // just in case we have something else with ID "repair"
  $dom->loadHTML('<?xml encoding="UTF-8"><body id="repair' . $rnd . '">' . $html . '</body>'); // input UTF-8

  // copy the document content into a new document
  $doc = new DOMDocument();
  foreach ($dom->getElementById('repair' . $rnd)->childNodes as $child) {
    $doc->appendChild($doc->importNode($child, true));
  }
  
  // output the new document as HTML
  $doc->encoding = 'UTF-8'; // output UTF-8
  $doc->formatOutput = false;
  return trim($doc->saveHTML());
}

 
 
/**
 * Convenience function for @see filter_markup($str, "plain")
 */
function filter_plain($str, $bool_trim = TRUE) {
  $x = filter_markup($str, "plain");
  if ($bool_trim) {
    $x = fp_trim($x);
  }
  return $x;
}


 
/**
 * Filter string with possible HTML, allowing only certain tags, and removing dangerous attributes.
 * 
 * $type can be:
 *    - "plain" - No HTML tags are allowed. Safest.
 *    - "basic" - Only certain tags allowed, no attributes. Safest. New lines are converted to HTML break tags.
 *    - "full" - All HTML is allowed through.
 * 
 */
function filter_markup($str, $type = "basic") {

  if (!$str) return $str;

  if (!is_string($str)) {
    return $str;  
  }

  if ($type == 'plain') {
    $str = strip_tags($str);
    return $str;
  }

  // If we are here, we're doing something with HTML...

  
  // Fix mismatched HTML (without adding new tags).
  $str = repair_html($str);   
  
  if ($type == "basic") {
    
    // To reduce extra newlines, remove any newline which is at the END of an existing <br> tag.
    $str = str_ireplace("<br>\n", "<br>", $str);
    $str = str_ireplace("<br />\n", "<br>", $str);
     
    $allowed_tags = array('a', 'em', 'strong', 'cite', 
                          'blockquote', 'code', 'ul', 'ol', 'li', 
                          'dl', 'dt', 'dd', 'span', 'div', 
                          'b', 'i', 'u', 'br', 'p', 'table', 'tr', 
                          'td', 'th', 'tbody', );
    
    $str = filter_xss($str, $allowed_tags);
    
        
    $str = trim($str);
        
  }
  
  if ($type == "full") {
    
    // Essentially, do nothing. All HTML is allowed through.
    
  }
  
  
  
  return $str;
  
} 
 

/**
 * When receiving certain input from the user, filter out potential trouble characters
 * @param $type   This is the type of input we are expecting. For example, the default is "major_code", which means
 *                the input should exclude any characters not normally found in a major_code.
 */
function filter_untrusted_input($string, $type = 'major_code') {
  
  
  if ($type == 'major_code') {
    $string = filter_xss($string, array());
   
    $string = str_replace('"', '', $string);
    $string = str_replace("'", '', $string);
    $string = str_replace("(", '', $string);
    $string = str_replace(")", '', $string);
    $string = str_replace("#", '', $string);
    $string = str_replace("=", '', $string);
    $string = str_replace(" ", '', $string);
    $string = str_replace(";", '', $string);
   
  } 
  
  return $string;
} // filter_untrusted_input


/**
 * This function is taken almost directly from Drupal 7's core code.  It is used to help us filter out
 * dangerous HTML which the user might type.
 * From the D7 documentation:
 * 
 * Filters HTML to prevent cross-site-scripting (XSS) vulnerabilities.
 * Based on kses by Ulf Harnhammar, see http://sourceforge.net/projects/kses. For examples of various XSS attacks, see: http://ha.ckers.org/xss.html.
 * This code does four things:
 *     Removes characters and constructs that can trick browsers.
 *     Makes sure all HTML entities are well-formed.
 *     Makes sure all HTML tags and attributes are well-formed.    
 *     Makes sure no HTML tags contain URLs with a disallowed protocol (e.g. javascript:).
 * 
 */ 
function filter_xss($string, $allowed_tags = array('a', 'em', 'strong', 'cite', 'blockquote', 'code', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'span', 'div')) {
  // Only operate on valid UTF-8 strings. This is necessary to prevent cross
  // site scripting issues on Internet Explorer 6.
  if (!fp_validate_utf8($string)) {
    return '';
  }
  // Store the text format.
  filter_xss_split($allowed_tags, TRUE);
  // Remove NULL characters (ignored by some browsers).
  $string = str_replace(chr(0), '', $string);
  // Remove Netscape 4 JS entities.
  $string = preg_replace('%&\s*\{[^}]*(\}\s*;?|$)%', '', $string);



  // Defuse all HTML entities.
  $string = str_replace('&', '&amp;', $string);
  // Change back only well-formed entities in our whitelist:
  // Decimal numeric entities.
  $string = preg_replace('/&amp;#([0-9]+;)/', '&#\1', $string);
  // Hexadecimal numeric entities.
  $string = preg_replace('/&amp;#[Xx]0*((?:[0-9A-Fa-f]{2})+;)/', '&#x\1', $string);
  // Named entities.
  $string = preg_replace('/&amp;([A-Za-z][A-Za-z0-9]*;)/', '&\1', $string);


  return preg_replace_callback('%
    (
    <(?=[^a-zA-Z!/])  # a lone <
    |                 # or
    <!--.*?-->        # a comment
    |                 # or
    <[^>]*(>|$)       # a string that starts with a <, up until the > or the end of the string
    |                 # or
    >                 # just a >
    )%x', 'filter_xss_split', $string);
    
} 
 
/**
 * Like the filter_xss function, this is taken from D7's
 * _filter_xss_split function
 */
function filter_xss_split($m, $store = FALSE) {
  static $allowed_html;

  if ($store) {
    $allowed_html = array_flip($m);
    return;
  }

  $string = $m[1];

  if (substr($string, 0, 1) != '<') {
    // We matched a lone ">" character.
    return '&gt;';
  }
  elseif (strlen($string) == 1) {
    // We matched a lone "<" character.
    return '&lt;';
  }

  if (!preg_match('%^<\s*(/\s*)?([a-zA-Z0-9]+)([^>]*)>?|(<!--.*?-->)$%', $string, $matches)) {
    // Seriously malformed.
    return '';
  }

  $slash = trim($matches[1]);
  $elem = &$matches[2];
  $attrlist = &$matches[3];
  $comment = &$matches[4];

  if ($comment) {
    $elem = '!--';
  }

  if (!isset($allowed_html[strtolower($elem)])) {
    // Disallowed HTML element.
    return '';
  }

  if ($comment) {
    return $comment;
  }

  if ($slash != '') {
    return "</$elem>";
  }

  // Is there a closing XHTML slash at the end of the attributes?
  $attrlist = preg_replace('%(\s?)/\s*$%', '\1', $attrlist, -1, $count);
  $xhtml_slash = $count ? ' /' : '';

  // Clean up attributes.
  $attr2 = implode(' ', filter_xss_attributes($attrlist));
  $attr2 = preg_replace('/[<>]/', '', $attr2);
  $attr2 = strlen($attr2) ? ' ' . $attr2 : '';

  return "<$elem$attr2$xhtml_slash>";
}

function filter_xss_attributes($attr) {
  $attrarr = array();
  $mode = 0;
  $attrname = '';
  $skip = FALSE;
  while (strlen($attr) != 0) {
    // Was the last operation successful?
    $working = 0;

    switch ($mode) {
      case 0:
        // Attribute name, href for instance.
        if (preg_match('/^([-a-zA-Z]+)/', $attr, $match)) {
          $attrname = strtolower($match[1]);
          $skip = ($attrname == 'style' || substr($attrname, 0, 2) == 'on');
          $working = $mode = 1;
          $attr = preg_replace('/^[-a-zA-Z]+/', '', $attr);
        }
        break;

      case 1:
        // Equals sign or valueless ("selected").
        if (preg_match('/^\s*=\s*/', $attr)) {
          $working = 1;
          $mode = 2;
          $attr = preg_replace('/^\s*=\s*/', '', $attr);
          break;
        }

        if (preg_match('/^\s+/', $attr)) {
          $working = 1;
          $mode = 0;
          if (!$skip) {
            $attrarr[] = $attrname;
          }
          $attr = preg_replace('/^\s+/', '', $attr);
        }
        break;

      case 2:
        // Attribute value, a URL after href= for instance.
        if (preg_match('/^"([^"]*)"(\s+|$)/', $attr, $match)) {
          $thisval = filter_xss_bad_protocol($match[1]);

          if (!$skip) {
            $attrarr[] = "$attrname=\"$thisval\"";
          }
          $working = 1;
          $mode = 0;
          $attr = preg_replace('/^"[^"]*"(\s+|$)/', '', $attr);
          break;
        }

        if (preg_match("/^'([^']*)'(\s+|$)/", $attr, $match)) {
          $thisval = filter_xss_bad_protocol($match[1]);

          if (!$skip) {
            $attrarr[] = "$attrname='$thisval'";
          }
          $working = 1;
          $mode = 0;
          $attr = preg_replace("/^'[^']*'(\s+|$)/", '', $attr);
          break;
        }

        if (preg_match("%^([^\s\"']+)(\s+|$)%", $attr, $match)) {
          $thisval = filter_xss_bad_protocol($match[1]);

          if (!$skip) {
            $attrarr[] = "$attrname=\"$thisval\"";
          }
          $working = 1;
          $mode = 0;
          $attr = preg_replace("%^[^\s\"']+(\s+|$)%", '', $attr);
        }
        break;
    }

    if ($working == 0) {
      // Not well formed; remove and try again.
      $attr = preg_replace('/
        ^
        (
        "[^"]*("|$)     # - a string that starts with a double quote, up until the next double quote or the end of the string
        |               # or
        \'[^\']*(\'|$)| # - a string that starts with a quote, up until the next quote or the end of the string
        |               # or
        \S              # - a non-whitespace character
        )*              # any number of the above three
        \s*             # any number of whitespaces
        /x', '', $attr);
      $mode = 0;
    }
  }

  // The attribute list ends with a valueless attribute like "selected".
  if ($mode == 1 && !$skip) {
    $attrarr[] = $attrname;
  }
  return $attrarr;
} 

function filter_xss_bad_protocol($string) {
  // Get the plain text representation of the attribute value (i.e. its meaning).
  $string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
  return htmlspecialchars(fp_strip_dangerous_protocols($string), ENT_QUOTES, 'UTF-8');
}

function fp_strip_dangerous_protocols($uri) {
  static $allowed_protocols;

  if (!isset($allowed_protocols)) {
    $allowed_protocols = array_flip(array('ftp', 'http', 'https', 'irc', 'mailto', 'news', 'nntp', 'rtsp', 'sftp', 'ssh', 'tel', 'telnet', 'webcal'));
  }

  // Iteratively remove any invalid protocol found.
  do {
    $before = $uri;
    $colonpos = strpos($uri, ':');
    if ($colonpos > 0) {
      // We found a colon, possibly a protocol. Verify.
      $protocol = substr($uri, 0, $colonpos);
      // If a colon is preceded by a slash, question mark or hash, it cannot
      // possibly be part of the URL scheme. This must be a relative URL, which
      // inherits the (safe) protocol of the base document.
      if (preg_match('![/?#]!', $protocol)) {
        break;
      }
      // Check if this is a disallowed protocol. Per RFC2616, section 3.2.3
      // (URI Comparison) scheme comparison must be case-insensitive.
      if (!isset($allowed_protocols[strtolower($protocol)])) {
        $uri = substr($uri, $colonpos + 1);
      }
    }
  } while ($before != $uri);

  return $uri;
}

 
function fp_validate_utf8($text) {
  if (strlen($text) == 0) {
    return TRUE;
  }
  // With the PCRE_UTF8 modifier 'u', preg_match() fails silently on strings
  // containing invalid UTF-8 byte sequences. It does not reject character
  // codes above U+10FFFF (represented by 4 or more octets), though.
  return (preg_match('/^./us', $text) == 1);
} 
 
/**
 * Simple function to convert a string into a machine-readable string.
 * 
 * Useful for making possibly unsafe text work as an array index, a CSS class, etc.  Replaces
 * "bad" characters, or characters which might not be allowed for variables, for example,
 * into underscores (_).
 *
 * @param string $str
 * @return string
 */
function fp_get_machine_readable($str) {
 if (!$str) return (string) $str;
 return preg_replace('@[^a-zA-Z0-9_]+@', '_', $str);  
}

 
/////////////////////////////////////////////////////////////////////

/**
 * Return back an assoc array of our set degree classifications, separated by "level"
 */
function fp_get_degree_classifications() {
  $rtn = array();
  
  // Level 1
  $temp = explode("\n", variable_get("degree_classifications_level_1", "MAJOR ~ Major"));
  foreach ($temp as $line) {
    $temp2 = explode("~", $line);
    $machine_name = fp_trim(@$temp2[0]);
    $title = fp_trim(@$temp2[1]);
    if ($machine_name != "") {
      $rtn["levels"][1][$machine_name] = $title;
      $rtn["machine_names"][$machine_name] = $title;
      $rtn["machine_name_to_level_num"][$machine_name] = 1;
    }
  }
  
  // Level 2
  $temp = explode("\n", variable_get("degree_classifications_level_2", "MINOR ~ Minor"));
  foreach ($temp as $line) {
    $temp2 = explode("~", $line);
    $machine_name = fp_trim(@$temp2[0]);
    $title = fp_trim(@$temp2[1]);
    if ($machine_name != "") {
      $rtn["levels"][2][$machine_name] = $title;
      $rtn["machine_names"][$machine_name] = $title;
      $rtn["machine_name_to_level_num"][$machine_name] = 2;
    }
  }
  
  
  // Level 3
  $temp = explode("\n", variable_get("degree_classifications_level_3", "CONC ~ Concentration"));
  foreach ($temp as $line) {
    $temp2 = explode("~", $line);
    $machine_name = fp_trim(@$temp2[0]);
    $title = fp_trim(@$temp2[1]);
    if ($machine_name != "") {
      $rtn["levels"][3][$machine_name] = $title;
      $rtn["machine_names"][$machine_name] = $title;
      $rtn["machine_name_to_level_num"][$machine_name] = 3;
    }
  }
  
  
  
  return $rtn;
}


/**
 * Returns back an assoc array for the supplied code.  Looks like:
 * $arr["level_num"] = number
 * $arr["title"] = the title
 * 
 * 
 */
function fp_get_degree_classification_details($degree_class = "MAJOR", $bool_return_class_code_as_title_if_not_found = TRUE) {
  $rtn = array();
  
  if ($bool_return_class_code_as_title_if_not_found) {
    // Use the degree_class as title for default, if we can't find it otherwise.
    $rtn["level_num"] = 0;
    $rtn["title"] = $degree_class;
    $rtn["degree_class"] = $degree_class;
  }
  
  $degree_classifications = fp_get_degree_classifications();
  foreach ($degree_classifications["levels"] as $num => $details) {
    if (isset($details[$degree_class])) {
      $rtn["level_num"] = $num;
      $rtn["title"] = $details[$degree_class];
      $rtn["degree_class"] = $degree_class;   
      break;   
    }
  }
  
  
  return $rtn;
}



/**
 * Return an array version of the term_id_structure field from the admin settings
 *
 */
function get_term_structures($school_id = 0) {
  $rtn = array();

  $temp = variable_get_for_school("term_id_structure", "", $school_id);
  $structures = explode("\n", $temp);
    
  foreach ($structures as $structure) {      
    $tokens = explode(",", $structure);
    $term_def = trim($tokens[0]);
    
    // Get rid of the replacement pattern.
    // Looks like:  [Y4]40.  We want the 40.
    // Simply explode on "]"
    $temp = explode("]", $term_def);
    
    $term_suffix = trim($temp[1]);
    
    $rtn[$term_suffix] = array(
      "term_suffix" => $term_suffix,
      "term_def" => $term_def,
      "short" => fp_trim(@$tokens[1]),      
      "full" => fp_trim(@$tokens[2]),
      "abbr" => fp_trim(@$tokens[3]),
      "disp_adjust" => fp_trim(@$tokens[4]),
    );
  
  }
  
  
  return $rtn;
}


/**
 * Returns back an array of all the available requirement types (by code) that
 * have been defined.
 *
 */
function fp_get_requirement_types($school_id) {
  $rtn = array();
  
  if (isset($GLOBALS['fp_temp_cache']['fp_get_requirement_types'][$school_id])) {
    return $GLOBALS['fp_temp_cache']['fp_get_requirement_types'][$school_id];
  }
  
  
  $temp = explode("\n", variable_get_for_school("requirement_types", "g ~ General Requirements\nc ~ Core Requirements\ne ~ Electives\nm ~ Major Requirements\ns ~ Supporting Requirements\nx ~ Additional Requirements", $school_id));
  foreach ($temp as $line) {
    $line = trim($line);
    if ($line == "") continue;
    
    $temp = explode("~", $line);
    $code = trim(strtolower($temp[0]));
    $desc = trim($temp[1]);

    $rtn[$code] = $desc;   
    
  }
  
  // Make sure that code 'x' is set.
  if (!isset($rtn["x"])) {
    $rtn["x"] = t("Additional Requirements");
  }
  
  // Make sure code 'e' for Electives is set.
  if (!isset($rtn["e"])) {
    $rtn["e"] = t("Electives");
  }
  
  // Make sure code 'm' is set, for Major Requirements, our default type.
  if (!isset($rtn["m"])) {
    $rtn["m"] = t("Major Requirements");
  }
  
  $GLOBALS['fp_temp_cache']['fp_get_requirement_types'][$school_id] = $rtn;
  
  return $rtn;
  
}


/**
 * This function provides a pass-thru to $d = new DegreePlan(args).
 * However, it allows for quick caching look-up, so it should be used when possible instead of $x = new DegreePlan.
 */
function fp_load_degree($degree_id = "", DatabaseHandler $db = NULL, $bool_load_minimal = false, $array_significant_courses = false, $bool_use_draft = false) {
  
  // Create a "cache key" based on the arguments, so we can look this degree up faster later.
  $cache_key = md5(serialize(func_get_args()));
  //fpm("degree_id: $degree_id . cache key:" . $cache_key . "+++++++++++++");
  if (isset($GLOBALS['fp_temp_cache']['fp_load_degree'][$cache_key])) {
    $degree = $GLOBALS['fp_temp_cache']['fp_load_degree'][$cache_key];    
    //fpm(" ...  returning cache");
    return $degree;
  }
    
  $degree = new DegreePlan($degree_id, $db, $bool_load_minimal, $array_significant_courses, $bool_use_draft);
  
  // Save to our cache
  $GLOBALS['fp_temp_cache']['fp_load_degree'][$cache_key] = $degree;
  
  return $degree;
  
}




/**
 * If this function is called, it will override any other page tabs
 * which might be getting constructed.  This lets the programmer,
 * at run-time, completely control what tabs are at the top of the page.
 */
function fp_set_page_tabs($tab_array) {
  $GLOBALS["fp_set_page_tabs"] = $tab_array;
}

/**
 * Allows the programmer to define subtabs at the top of the page.
 *
 * @param unknown_type $tab_array
 */
function fp_set_page_sub_tabs($tab_array) {
  $GLOBALS["fp_set_page_sub_tabs"] = $tab_array;
}




/**
 * Set our breadcrumbs array.
 * 
 * We expect the array to look like this:
 * [0]['text'] = "Alerts";
 * [0]['path'] = "my/alerts";
 * [0]['query'] (optional)
 * [0]['attributes']  (optional.  Used exactly as in the l() function.  @see l() )
 * 
 * [1] .... etc.
 * @see fp_render_breadcrumbs();
 * 
 * 
 * Here is a practical example of how to call this function:
 * 
 *    $crumbs = array();
 *    $crumbs[] = array(
 *      'text' => 'Alerts',
 *      'path' => 'alerts',
 *    );
 * 
 *    fp_set_breadcrumbs($crumbs);
 * 
 */
function fp_set_breadcrumbs($arr = array()) {  
  $GLOBALS['fp_breadcrumbs'] = $arr;
}

/**
 * Allows the programmer to set the title of the page, overwriting any default title.
 *
 * @param unknown_type $title
 */
function fp_set_title($title) {  
  $GLOBALS["fp_set_title"] = $title;
  if ($title == "") {
    fp_show_title(FALSE);  // No title to show!
  }
  else {
    fp_show_title(TRUE);  // If we are calling this function, we clearly want to display the title.
  }
}

/**
 * Add a CSS class to the body tag of the page.  Useful for themeing later on.
 *
 * @param String $class
 */
function fp_add_body_class($class) {
  
  // Let's sanitize the "class" to make sure it doesn't contain any trouble characters.
  $class = str_replace("'", '', $class);
  $class = str_replace('"', '', $class);
  $class = str_replace('(', '', $class);
  $class = str_replace(')', '', $class);
  $class = str_replace(';', '', $class);
  $class = str_replace('.', '', $class);
  $class = str_replace('<', '', $class);
  $class = str_replace('>', '', $class);
  $class = str_replace('/', '', $class);
  $class = str_replace('\\', '', $class);
  $class = str_replace('#', '', $class);
  $class = str_replace('&', '', $class);
  
  @$GLOBALS["fp_add_body_classes"] .= " " . $class;
}


/**
 * Returns back the site's "token", which is a simply md5 of some randomness.
 * It is used primarily with forms, to ensure against cross-site forgeries.
 * The site's token gets saved to the variables table, for later use.  The idea
 * is that every installation of FlightPath has a semi-unique token.
 */
function fp_token() {
  $site_token = variable_get("site_token", "");
  if ($site_token == "") {
    $site_token = md5("" . time() . rand(1,9999));
    variable_set("site_token", $site_token);
  }
  
  return $site_token;
  
}



/**
 * This function will provide the session_id as a string, as well as a secret token we can
 * use to make sure the session_id is authentic and came from us and not a hacker.
 */
function fp_get_session_str() {
  $session_id = session_id();  // Get the PHP session_id
  $ip = @$_SERVER["REMOTE_ADDR"];
  if ($ip == "") $ip = "000";
  
  // NOTE: We cannot use fp_token() here, since the get function (below) is called before the various bootstrap files are loaded.
    
  // Create a string where we can confirm the ip and server name the session came from.
  // TODO: Might be able to add more entropy later on, as long as it does not involve the database, since bootstrap isn't loaded yet when validating.  
  $str = $session_id . "~_" . md5($session_id . $ip . php_uname('n')); 
  
    
  return $str;  
}

  
 
/**
 * This will validate the session str (@see fp_get_session_str()) and return back either FALSE
 * or the session_id.
 */
function fp_get_session_id_from_str($str) {
  
  // We expect $str to look like this:
  // session_id~_md5(session_id . ip . php_uname('n'))

  $temp = explode("~_", $str);
    
  $session_id = trim($temp[0]);
  $hash = trim($temp[1]);
    
  $ip = @$_SERVER["REMOTE_ADDR"];
  if ($ip == "") $ip = "000";  
  
  
  $test_hash = md5($session_id . $ip . php_uname('n'));
  
  if ($test_hash === $hash) {
    // Success!
    return $session_id;
  }
  
  return FALSE;
    
}


/**
 * Simple function that adds spaces after commas in CSV strings.  Makes them easier to read.
 */
function fp_space_csv($str) {  
  $str = str_replace(",", ", ", $str);
  // Get rid of double spaces we might have introduced.
  $str = str_replace(",  ", ", ", $str);
  $str = str_replace(",  ", ", ", $str);
  $str = str_replace(",  ", ", ", $str);
    
  
  $str = trim($str);
  return $str;
}


/**
 * Simple function to split a basic CSV string, trim all elements, then return
 * the resulting array.
 */
function csv_to_array($csv_string) {
  $temp = explode(",", $csv_string);
  $temp = array_map("trim", $temp);
  return $temp;
}

/**
 * Splits a basic csv but returns an array suitable for the form_api, retuns assoc array.
 */
function csv_to_form_api_array($csv_string, $delimeter = ",", $bool_make_keys_machine_readable = TRUE) {
  $rtn = array();  
  $temp = explode($delimeter, $csv_string);
  foreach ($temp as $line) {
    $line = trim($line);
    if (!$line) continue;
    
    $key = strtolower(fp_get_machine_readable($line));
    $rtn[$key] = $line;
    
  }  
  
  return $rtn;
  
}



/**
 * From https://www.php.net/manual/en/function.str-getcsv.php#117692
 */
function csv_multiline_to_array($csv_str, $bool_first_row_is_headers = TRUE) {
  $csv = array_map('str_getcsv', explode("\n", trim($csv_str)));
  array_walk($csv, function(&$a) use ($csv) {
    if (count($a) == count($csv[0])) {  
      $a = array_combine($csv[0], $a);
    }
    else {
      fpm("Warning: issue converting multiline CSV to an array within FlightPath.  Not the same number of elements.");
    }
  });
  if (!$bool_first_row_is_headers) {
    array_shift($csv); # remove column header
  }
    
  return $csv;    
      
}


/**
 * Add a "message" to the top of the screen.  Useful for short messages like "You have been logged out"
 * or "Form submitted successfully."
 *
 * @param String $msg
 *          This is the string message itself.
 * @param String $type
 *          The "type" of message.  This string is added as a CSS class to the message, for theming later.
 *          Examples: "status", "warning", "error"
 * @param boolean $bool_no_repeat
 *          Boolean.  Should the message show more than once per page view?  Set to TRUE if it should NOT.  
 */
function fp_add_message($msg, $type = "status", $bool_no_repeat = FALSE) {

  $md5 = md5($type . $msg);  

  if ($bool_no_repeat && isset($_SESSION["fp_messages"]) && is_array($_SESSION["fp_messages"])) {
    // Make sure this message isn't already in the session.
    foreach($_SESSION["fp_messages"] as $s) {
      if ($s["md5"] == $md5) return;
    }
  }

    
  $_SESSION["fp_messages"][] = array("type" => $type, "msg" => $msg, "md5" => $md5);
}


/*
  Does the string end with the provided needed?
*/
function fp_str_ends_with ($haystack, $needle) {
    return substr_compare($haystack, $needle, -strlen($needle)) === 0;
}



/**
 * Add an extra CSS file to the page with this function.
 * Ex:    fp_add_css(fp_get_module_path("admin") . '/css/admin.css');
 *
 * @param String $path_to_css
 */
function fp_add_css($path_to_css) {
  
  // Init if needed
  if (!isset($GLOBALS['fp_extra_css'])) $GLOBALS['fp_extra_css'] = array();  
  
  
  if (!in_array($path_to_css, $GLOBALS['fp_extra_css'])) {
    $GLOBALS["fp_extra_css"][] = $path_to_css;
  }
}



/**
 * Add extra javascript to the page.
 * 
 * - type = file... $js is expected to be the path to a javascript file.
 * - type = setting... $js is expected to be an associative array of settings.
 *            For example:  array("my_path" => "blah", "my_color" => "red").
 *            They will be available in javascript in the object FlightPath like so:
 *              FlightPath.settings.my_color;
 * 
 * Ex: fp_add_js(fp_get_module_path("admin") . '/js/admin.js');
 * 
 * @see fp_add_css()
 * 
 */
function fp_add_js($js, $type = "file") {
  
  // Init if needed
  if (!isset($GLOBALS['fp_extra_js'])) $GLOBALS['fp_extra_js'] = array();  
    
    
  if ($type == "file") {
    if (!in_array($js, $GLOBALS['fp_extra_js'])) {
      $GLOBALS["fp_extra_js"][] = $js;
    }
  }
  
  if ($type == "setting") {
    if (!isset($GLOBALS["fp_extra_js_settings"])) $GLOBALS["fp_extra_js_settings"] = array();
    
    // Instead of using array_merge_recursive, this guarantees that keys are not re-used.    
    foreach ($js as $key => $val) {
      $GLOBALS["fp_extra_js_settings"][$key] = $val;
    }
    
    
  }
  
}


/**
 * This function will create a string from a 1 dimensional assoc array.
 * Ex:   arr = array("pet" => "dog", "name" => "Rex")
 *      will return:    pet_S-dog,name_S-Rex    under the default settings.
 * 
 * The separator is meant to be a string extremely unlikely to be used in the key or values.
 * 
 * Use the fp_explode_assoc function to piece it back together.
 * @see fp_explode_assoc
 */
function fp_join_assoc($arr, $glue = ",", $assign_sep = "_S-") {
  $rtn = "";
  
  foreach ($arr as $key => $val) {
    $rtn .= $key . $assign_sep . $val . $glue;
  }
  
  // Should be an extra glue character at the end we need to trim off.
  $rtn = rtrim($rtn, $glue);
  
  return $rtn;
}

/**
 * Takes a string (created by fp_join_assoc()) and re-creates the 1 dimensional assoc array.
 * 
 * The separator is meant to be a string extremely unlikely to be used in the key or values.
 * 
 * @see fp_join_assoc()
 */
function fp_explode_assoc($string, $delim = ",", $assign_sep = "_S-") {
  $rtn = array();
  
  $temp = explode($delim, $string);
  foreach($temp as $line) {
    $line = trim($line);
    if ($line == "") continue;
    
    $temp2 = explode($assign_sep, $line);
    if (is_numeric($temp2[1])) {
      $temp2[1] = $temp2[1] * 1;  // if its numeric anyway, make it have a numeric type.      
    }
    
    $rtn[$temp2[0]] = $temp2[1];
    
  }
  
  return $rtn;
}



/**
 * Return the filepath to the module
 *
 * @param unknown_type $module
 * @param unknown_type $bool_include_file_system_path
 * @param unknown_type $bool_include_base_path
 * @return unknown
 */
function fp_get_module_path($module, $bool_include_file_system_path = FALSE, $bool_include_base_path = TRUE) {
  
  $p = menu_get_module_path($module, $bool_include_file_system_path);
  
  if ($bool_include_file_system_path == FALSE && $bool_include_base_path == TRUE) {
    $p = base_path() . "/" . $p;
  }
    
  return $p;
}



/**
 * Convenience function to return the /files system path.  Does NOT end with a trailing slash.
 *
 */
function fp_get_files_path() {
  return $GLOBALS["fp_system_settings"]["file_system_path"] . "/custom/files";
}


/**
 * Convenience function to return the temporary directory path. Does NOT end with a trailing slash.
 * Ex:  /tmp
 */
function fp_get_tmp_path() {  
  return variable_get("tmp_path", "/tmp");
}



/**
 * Simply returns TRUE or FALSE if the user is a student.  (has the is_student == 1
 * 
 * If account is null, the global user will be used.
 */
function fp_user_is_student($account = null) {
  global $user;
  if ($account == NULL) {
    $account = $user;
  }
  
  return (bool)$account->is_student;
  
}




/**
 * Simply returns the module's row from the modules table, if it exists.
 *
 * @param unknown_type $module
 */
function fp_get_module_details($module) {
  
  // Special case if we are looking up flightpath itself
  if ($module == "flightpath") {
    $rtn = array(
      "info" => array("name" => t("FlightPath (Core)")),
      "version" => FLIGHTPATH_VERSION,
    );
    return $rtn;
  }
  
  $res = db_query("SELECT * FROM modules WHERE name = '?' ", $module);
  $cur = db_fetch_array($res);
  if ($test = unserialize($cur["info"])) {
    $cur["info"] = $test;
  }
  
  return $cur;
}



/**
 * This function will facilitate translations by using hook_translate()
 * 
 * Allows variable replacements.  Use like this:
 * t("@name's blob", array("@name" => "Richard"));
 * or simply
 * t("My blob");  if you don't need replacements.
 * 
 * Not implimented yet.
 */
function t($str, $vars = array()) {

  // Note: at the moment, this isn't being used, but should one day be set.  
  @$langcode = isset($langcode) ? $langcode : $GLOBALS["fp_system_settings"]["language"]; 
  
  // First, change $str if any other modules implement hook_translate().
  invoke_hook("translate", array(&$str, $langcode, $vars));  // str is passed by ref, so no return needed.
    
  if (is_array($vars) && count($vars) > 0) {
    foreach ($vars as $var => $val) {
      
      // If var begins with %, it means we want to italicize the val.
      if (strstr($var, "%")) {
        $val = "<em>$val</em>";
      }
      
      if ($val === NULL || $val === FALSE) $val = "";  // change to empty string for PHP 8x
      
      $str = str_replace($var, $val, $str);
    }
  }

  return $str;
}


/**
 * Provides translation functionality when database is not available.
 * 
 * TODO: Not implemented yet
 */
function st($str, $vars = array()) {
  // Not implemented yet.  For now, just replicate t().
  if (is_array($vars) && count($vars) > 0) {
    foreach ($vars as $var => $val) {
      
      // If var begins with %, it means we want to italicize the val.
      if (strstr($var, "%")) {
        $val = "<em>$val</em>";
      }
      
      $str = str_replace($var, $val, $str);
    }
  }
    
  return $str;
}

/**
 * Shortcut for getting the base_url variable from the global system settings.
 */
function base_url() {
  $p = $GLOBALS["fp_system_settings"]["base_url"];
 
  return $p;
}

/**
 * Shortcut for getting the base_path variable from the global system settings.
 */
function base_path() {
  $p = $GLOBALS["fp_system_settings"]["base_path"];
  
  // the base_path setting isn't set, so just use '.', meaning, start
  // at the currect directory by default.
  if ($p == "") {
    $p = ".";
  }
  
  // if our base_path is simply "/" (meaning, we are hosted on a bare domain), then we should
  // actually return nothing, so as not to cause errors with other systems.
  if ($p == "/") {
    $p = "";
  }
  
  return $p;
}


/**
 * Convert a term ID into a description.  Ex: 20095 = Spring of 2009.
 */
function get_term_description($term_id, $bool_abbreviate = FALSE, $school_id = 0) {
    // Describe the term in plain english, for displays.
    // Ex:  "Fall of 2002."
    $rtn = "";

    if (!$term_id) $term_id = '';

    // If already in our GLOBALS cache, then just return that.
    if (isset($GLOBALS['fp_cache_get_term_description'][$term_id][intval($bool_abbreviate)][$school_id])) {
      return $GLOBALS['fp_cache_get_term_description'][$term_id][intval($bool_abbreviate)][$school_id];
    }


    // See if any modules would like to act on the term_id before we proceed.
    invoke_hook("alter_term_id_prior_to_description", array(&$term_id, &$bool_abbreviate, $school_id));


    if (strstr($term_id, "1111")) {
      return "(data unavailable at this time)";
    }

    $year4 = intval(trim(substr($term_id, 0, 4)));
    $year2 = intval(trim(substr($term_id, 2, 2)));
    $ss = trim(substr($term_id, 4, strlen($term_id) - 4));
    
    $year4p1 = $year4 + 1;
    $year4m1 = $year4 - 1;
    
    // left-pad these with 0's if needed.
    $year2p1 = @fp_number_pad($year2 + 1, 2);
    $year2m1 = @fp_number_pad($year2 - 1, 2);
        
    // Let's look at the term_idStructure setting and attempt to match
    // what we have been supplied.
    // We expect this structure to look something like:
    // [Y4]60, Spring, Spring of [Y4], Spr '[Y2]
    // [Y4]40, Fall, Fall of [Y4-1], Fall '[Y2-1]
    
    $temp = @variable_get_for_school("term_id_structure", '', $school_id);
    $structures = explode("\n", $temp);
    
    foreach ($structures as $structure) {      
      // Perform the necessary replacement patterns on the structure.
      $structure = str_replace("[Y4]", $year4, $structure);
      $structure = str_replace("[Y2]", $year2, $structure);
      $structure = str_replace("[Y4-1]", $year4m1, $structure);
      $structure = str_replace("[Y2-1]", $year2m1, $structure);
      $structure = str_replace("[Y4+1]", $year4p1, $structure);
      $structure = str_replace("[Y2+1]", $year2p1, $structure);
      
      // Now, break up the structure to make it easier to work with.
      $tokens = explode(",", $structure);
      $term_def = @trim($tokens[0]);
      $full_description = @trim($tokens[2]);
      $abbr_description = @trim($tokens[3]);
      
      // Does our term_id match the termDef?
      if ($term_def == $term_id) {
        if ($bool_abbreviate) {
          return $abbr_description;
        }
        else {
          return $full_description;
        }
      }
      
    }

    // No descr could be found, so just display the term_id itself.
    if (trim($rtn) == "") {
      $rtn = $term_id;
    }

    // Save to our GLOBALS cache
    $GLOBALS['fp_cache_get_term_description'][$term_id][intval($bool_abbreviate)][$school_id] = $rtn;

    return $rtn;
  }





/**
 * Redirect the user's browser to the specified internal path + query.
 * 
 * We will automatically add the current_student_id variable, if it is not present
 * in the query.
 * 
 * Example uses:
 *  - fp_goto("admin");
 *  - fp_goto("test/1234");
 *  - fp_goto("test/123", "selection=yes&fruit=apple");
 */
function fp_goto($path, $query = "") {
  global $current_student_id;
  
  
  // Were we sent an array instead of separate values?  That's OK if so, let's separate them back out.
  if (is_array($path)) {
    $path = @$path[0];  
    $query = (string) @$path[1];
    
  }
  
    
  if ($current_student_id != "" && !strstr($query, "current_student_id=")) {
    // If the query doesn't contain the current_student_id, then add it in.
    $query .= "&current_student_id=$current_student_id";
  }

  // Close the seesion before we try to redirect.
  session_write_close();
  
  if ($path == "<front>") {
    $path = variable_get("front_page", "main");
  }

  $location = fp_url($path, $query);
  
  
  if (str_starts_with($path, "http://") || str_starts_with($path, "https://")) {
    // We are going to an external address.
    if (str_starts_with($query, "&")) {
      // Need to add ? to the query.
      $query = "?fprnd=" . mt_rand(9,999999) . $query;
    }
    $location = $path . $query;
  }
  
      
  header('Location: ' . $location);
  exit();
  
}

/**
 * This works like Drupal's l() function for creating links.
 * Ex:  l("Click here for course search!", "tools/course-search", "abc=xyz&hello=goodbye", array("class" => "my-class"));
 * Do not include preceeding or trailing slashes.
 */
function l($text, $path, $query = "", $attributes = array()) {
  $rtn = "";

  if (!$query) $query = "";  // For compatibility with PHP 8.2, make sure it isn't NULL or FALSE

  if ($path == "<front>") {
    $path = variable_get("front_page", "main");
  }

  
  // Does the path contain possible replacement patterns? (look for %)
  if (strpos($path, "%") !== 0) {
    $path = menu_convert_replacement_pattern($path);    
  }
  
  // Does the query contain possible replacement patterns? (look for %)
  if (strpos($query, "%") !== 0) {
    $query = menu_convert_replacement_pattern($query);    
  }
  
  $rtn .= '<a href="' . fp_url($path, $query) . '" ';
  
  foreach ($attributes as $key => $value) {
    $rtn .= $key . '="' . $value . '" ';
  }
  
  $rtn .= ">$text</a>";



  return $rtn;
}



/**
 * This convenience function returns the absolute URL to the path requested.
 * @see fp_url()
 */
function fp_url_absolute($path, $query = "") {
  return base_url() . '/' . fp_url($path, $query, FALSE);
}








/**
 * This function will take a path, ex: "admin/config/module"
 * and a query, ex: "nid=5&whatever=yes"
 * And join them together, respecting whether or not clean URL's are enabled.
 */
function fp_url($path, $query = "", $include_base_path = TRUE) {
  
  if (!$query) $query = "";  // For compatibility with PHP 8.2, make sure it isn't NULL or FALSE
  
  if ($path == "<front>") {    
    $path = variable_get("default_home_path", "main");
  }
  
  
  // If clean URLs are enabled, we should begin with a ?, if not, use an &
  
  $rtn = "";
  if ($include_base_path) {
    $rtn .= base_path() . "/";
  }  
 
 
  // Make sure that $rtn isn't now "//".  This can happen if our
  // site is hosted on a bare domain.  Ex:  http://fp.example.com
  // And we have set the base_path to simply "/"
  if ($rtn == "//") $rtn = "/";
 
  $bool_clean_urls = variable_get("clean_urls", FALSE);
  if (!$bool_clean_urls) {
    // Clean URLs are NOT enabled!  Let's make sure the URL contains "index.php?q="
    $rtn .= "index.php?q=";    
  }
 
 
  $rtn .= $path;
  
  if ($query != "") {
    // Is there a ? already in the $rtn?  If not, add a ?.  If so, use a &.
    if (!strstr($rtn, "?")) {  
      $rtn .= "?";  
    }
    else {
      $rtn .= "&";
    }
    
    $rtn .= $query;
  }
 
  return $rtn;
          
}




  /**
   * This function will attempt to determine automatically
   * if we are on a mobile device, and should therefor use the mobile
   * theme and layout settings.
   *
   */
function fp_screen_is_mobile(){
  
  depricated_message("calling fp_screen_is_mobile is no longer used as of FP 6.x");
  
  if (isset($GLOBALS["fp_page_is_mobile"])) {
    return $GLOBALS["fp_page_is_mobile"];
  }
  
  $user_agent = $_SERVER['HTTP_USER_AGENT']; 

  $look_for = array(
    "ipod", 
    "iphone", 
    "android", 
    "opera mini", 
    "blackberry",
    "(pre\/|palm os|palm|hiptop|avantgo|plucker|xiino|blazer|elaine)",
    "(iris|3g_t|windows ce|opera mobi|windows ce; smartphone;|windows ce; iemobile)",
    "(smartphone|iemobile)",
    );
  
  $is_mobile = FALSE;
  
  foreach ($look_for as $test_agent) {   
    if (preg_match('/' . $test_agent . '/i',$user_agent)) {
       $is_mobile = TRUE;
      break;
    }
  }  
  
  
  $GLOBALS["fp_page_is_mobile"] = $is_mobile;
  return $is_mobile;
  
} // ends function mobile_device_detect






/**
 * Simple function that returns TRUE if the module is enabled, FALSE otherwise.
 * 
 * We also will use our existing GLOBALS cache. 
 */
function module_enabled($module_name) {
    
  return (isset($GLOBALS["fp_system_settings"]["modules"][$module_name]));
  
}



 
/**
 * Return an array of enabled modules which implement the provided hook.
 * Do not include the preceeding "_" on the hook name!
 * 
 * We can optionally provide the names of modules to skip.  Ex:  array('my_module', 'other_module', ... etc)
 *  
 */
function modules_implement_hook($hook = "example_hook_here", $skip_modules = array()) {
  
  // Going to use a global array to keep track of what hooks exist.  
  if (!isset($GLOBALS['hook_cache'])) $GLOBALS['hook_cache'] = array();  
  
  // Have we already cached this list previously?
  if (isset($GLOBALS['hook_cache'][$hook])) return $GLOBALS['hook_cache'][$hook];  
    
  // We have not already cached this, so let's look for it fresh...  
    
  $rtn = array();

  // If we are in the install script, the GLOBALS array won't be set up, since there is no
  // settings file yet.  If that's the case, create a blank array so we don't have an issue.
  if (!isset($GLOBALS['fp_system_settings'])) $GLOBALS['fp_system_settings'] = array();
  if (!isset($GLOBALS['fp_system_settings']['modules'])) $GLOBALS['fp_system_settings']['modules'] = array();
   

  foreach ($GLOBALS["fp_system_settings"]["modules"] as $module => $value) {
    if (isset($value["enabled"]) && $value["enabled"] != "1") {
      // Module is not enabled.  Skip it.
      continue;
    }
    
    if (in_array($module, $skip_modules)) continue;
    
    if (function_exists($module . '_' . $hook)) {
      $rtn[] = $module;
    }
  }
  
  $GLOBALS['hook_cache'][$hook] = $rtn;
  
  return $rtn;  
}



/**
 * Invokes a hook to get numbers on the total, read, and unread values from our modules, to find out
 * if we need to place a badge on the bell icon at the top of the screen.
 */
function fp_recalculate_alert_count_by_type($account = NULL) {
  global $user;
  if ($account === NULL) $account = $user;  
  
  if ($account->id == 0) return FALSE;
  
    
  $res = invoke_hook("get_alert_count_by_type", array($account));  
  $_SESSION['fp_alert_count_by_type'] = $res;  
  $_SESSION['fp_alert_count_by_type_last_check'] = time();  
}



/**
 * This is a simple function which attempts to trim a String.  However, if $val is NOT a string, it will
 * cast it to string.
 * 
 * This is to prevent deprecated warnings in PHP 8+, and can be used as
 * a drop-in replacement in place of trim()
 */
function fp_trim($val = NULL) {
  
  if (is_string($val)) {
    return trim($val);
  }

  if (is_numeric($val)) {
    $val = "" . $val;
    return trim($val);
  }
  
  if ($val === NULL || $val === FALSE) {
    return '';
  }


  // if $val is an array or object, intentionally return the trim() so it will cause a warning.
  return trim($val);  
      
}


/**
 * Returns back the total, read, and unread numbers previously calculated to see if we need to place
 * a badge next to the bell icon at the top of the screen.  If unset, we will call the recalculate function.
 */
function fp_get_alert_count_by_type($account = NULL) {
  global $user;
  if ($account === NULL) $account = $user;

  if ($account->id == 0) return FALSE;

  if (!isset($_SESSION['fp_alert_count_by_type_last_check'])) {
    $_SESSION['fp_alert_count_by_type_last_check'] = 0;
  }

  // Should we recalculate again?  
  $test = time() - intval(variable_get('recalculate_alert_badge_seconds', 30));

  if ($_SESSION['fp_alert_count_by_type_last_check'] < $test) {
    unset($_SESSION['fp_alert_count_by_type']);  // this will force us to recalculate.    
  }

  if (!isset($_SESSION['fp_alert_count_by_type'])) {
    fp_recalculate_alert_count_by_type($account);
  }
  
  return $_SESSION['fp_alert_count_by_type'];
  
}



/**
 * Invoke all module hooks for the supplied hook.
 */
function invoke_hook($hook = "example_hook_here", $params = array()) {
  $rtn = array();
  $modules = modules_implement_hook($hook);  
  foreach($modules as $module) {
    $rtn[$module] = call_user_func_array($module . "_" . $hook, $params);
  }
  return $rtn;
}



/**
 * This is a convenience function which simply passes through to fpm(), due entirely because
 * of typos with fpm().  
 * 
 * @see fpm()
 */
function dpm($var, $max_levels = 15) {
  fpm("You typed 'dpm()' instead of 'fpm()'. Make sure to use fpm() next time.");
  fpm($var, $max_levels);
}

/**
 * Uses fp_add_message, but in this case, it also adds in the filename and line number
 * which the message came from!
 * 
 * Most useful for developers, tracking down issues.  It's also only visible to administrators
 * with the "view_fpm_debug" permission. So if you need to display a message only to admins,
 * you can use fpm() as a shortcut.
 * 
 * Note: If you attempt to fpm() an array
 * or object with too many levels of nesting, it may run out of memory and your script will die.
 */
function fpm($var, $max_levels = 15) {
  
  if (!user_has_permission("view_fpm_debug")) {
    return;
  }
  
  // Complex variable?  Change it to print_r.
  $str = $var;
  if (is_array($str) || is_object($str)) {
    $str = "<div class='fp-html-print-r-wrapper'>" . fp_html_print_r($str, "", 0, $max_levels) . "</div>";
  }    

  $arr = debug_backtrace();
  //pretty_print($arr);
  
  $t = 0;
  
  if (@$arr[1]['function'] == 'fpmct') {
    $t = 1;
  }
  
  $file = $arr[$t]["file"];
  if (strlen($file) > 70) {
    $file = "..." . substr($file, strlen($file) - 70);
  }
  $str .= "<div class='fp-message-backtrace'>line {$arr[$t]["line"]}: $file</div>";
    
  fp_add_message("&bull; " . $str);

    
}

/**
 * Displays a depricated message on screen.  Useful for tracking down 
 * when depricated functions are being used.
 */
function depricated_message($str = "A depricated function has been called.") {
  fpm($str);
  fpm(debug_backtrace());
}


/**
 * Convenience function, will use fp_debug_ct() to display
 * a message, and the number of miliseconds since its last call.
 */
function fpmct($val, $var = "") {
  fpm(fp_debug_ct($val, $var));
}




/**
 * Similar to print_r, this will return an HTML-friendly
 * click-to-open system similar in design to Krumo.
 */
function fp_html_print_r($var, $name = "", $cnt = 0, $max_levels = 20) {  
  $rtn = "";
  
  if ($cnt > $max_levels) {
    // Max levels deep.  Deeper, and PHP might run
    // out of memory or complain.
    $rtn .= "<div class='fp-html-print-r-too-deep'>
              " . t("Depth too great. To view deeper,
                     rephrase your fpm() call, starting at this depth.") . "
             </div>";
    return $rtn;
  }
  
  $type = gettype($var);
  $rnd = md5(mt_rand(0, 999999) . microtime() . $type . $name);
  
  if ($type == "boolean") {
    $var = ($var == TRUE) ? "TRUE" : "FALSE";
  }  
  
  $count = "";
  if ($type == "string") {
    $count = " - " . strlen($var) . " " . t("chars");
  }
  
  if ($type == "array" || $type == "object") {
      
    
    if ($type == "array") {
      $count = " - " .  count($var) . " " . t("elements");
    }
    
    if ($type == "object") {
      $count = " - " . get_class($var);
    }
    
    $rtn .= "<div class='fp-html-print-r-multi-row'>
               <div class='fp-html-print-r-selector'
                  onClick='\$(\"#fp-html-print-r-var-value-$rnd\").toggle(\"medium\");'
                  >
                  <span class='fp-html-print-r-var-name'>$name</span>
                  <span class='fp-html-print-r-var-type'>($type$count)</span>
                  </div>
               <div class='fp-html-print-r-var-value' id='fp-html-print-r-var-value-$rnd' style='display: none;'>";
    foreach ($var as $key => $value) {
      $rtn .= fp_html_print_r($value, $key, ($cnt + 1), $max_levels);
    }
    
    $rtn .= "</div>
             </div>";
  }
  else if ($type == "string" && strlen($var) > 50) {
    // If the variable is fairly long, we want to also make it a hide-to-show type field.
    $rtn .= "<div class='fp-html-print-r-multi-row'>
               <div
                  onClick='\$(\"#fp-html-print-r-var-value-$rnd\").toggle(\"medium\");'
                  >
                    <span class='fp-html-print-r-var-name'>$name</span>
                    <span class='fp-html-print-r-var-type'>($type$count)</span>
                    <span class='fp-html-print-r-var-value-abbr'>" . htmlentities(substr($var, 0, 50)) . "...</span>
                  </div>
               <div class='fp-html-print-r-var-value' id='fp-html-print-r-var-value-$rnd' style='display: none;'>
               ";
    $rtn .= htmlentities($var);
    $rtn .= "</div></div>";    
  }
  else { 
    
    $html_val = $var;
    if ($type != "resource") {
      $html_val = htmlentities("" . $var);
    }
    
    $rtn .= "<div class='fp-html-print-r-single-row'>                
                <span class='fp-html-print-r-var-name'>$name</span>
                <span class='fp-html-print-r-var-type'>($type$count)</span>
                <span class='fp-html-print-r-var-value'>$html_val</span>
           </div>";
  }  
  
  return $rtn;
}



/**
 * This is used usually when being viewed by a mobile device.
 * It will shorten a catalog year range of 2008-2009 to just
 *  "08-09" or "2008-09"  or even "09-2009".
 *  
 *
 * @param string $cat_range - Ex:  2006-2007
 */
function get_shorter_catalog_year_range($cat_range, $abbr_first = true, $abbr_second = true) {
  
  $temp = explode("-", $cat_range);
  
  $first = $temp[0];
  $second = $temp[1];
  
  if ($abbr_first) {
    $first = substr($first, 2, 2);
  }
  if ($abbr_second) {
    $second = substr($second, 2, 2);
  }
  
  return "$first-$second";
}



/**
 * This will find and include the module in question, calling
 * it's hook_init() function if it has one.  
 *
 * Will return TRUE or FALSE for success or failure to include
 * the module.
 *
 * If the use_module_path is set to some value, we will not attempt to use
 * the setting for this module's path.  Useful if we do not have the module in our
 * modules table yet.
 * 
 * Example use:  include_module("course_search");
 * 
 * @param string $module
 */
function include_module($module, $bool_call_init = TRUE, $use_module_path = "") {

  $system_path = trim($GLOBALS["fp_system_settings"]["file_system_path"]);
  
  $module_path = "";  
  if (isset($GLOBALS["fp_system_settings"]["modules"][$module])) {  
    $module_path = $GLOBALS["fp_system_settings"]["modules"][$module]["path"];
  }
  
  if ($use_module_path != "") {
    $module_path = $use_module_path;
  }
  
  if ($module_path != "") {
    $path = $module_path . "/$module.module";
    
        
    if (file_exists($system_path . "/" . $path)) {
      require_once($system_path . "/" . $path);
    }
    else {
      print "<br><b>Could not find module '$module' at '$system_path/$path'</b><br>";
    }
    // Now that we have included it, call the module's hook_init() method.
    if ($bool_call_init) {      
      if (function_exists($module . "_init")) {
        call_user_func($module . "_init");
      }
    }    
    return TRUE;
  }
  
  return FALSE;  
}


/**
 * Find and include the module's .install file, if it exists.
 * Returns TRUE or FALSE if it was able to find & include the file.
 */
function include_module_install($module, $path) {
  $system_path = trim($GLOBALS["fp_system_settings"]["file_system_path"]);
    
  $install_path = $path . "/$module.install";
  if (file_exists($system_path . "/" . $install_path)) {
    require_once($system_path . "/" . $install_path);
    return TRUE;
  }
  
  return FALSE;  
}



/**
 * Creates a javascript "confirm" link, so when clicked it asks the user a question, then proceeds
 * if they select OK.  The main reason I want to do this is so I can pass the $question through
 * my t() function. (do it when you call this function)
 */
function fp_get_js_confirm_link($question, $action_if_yes, $link_text, $extra_class = "", $link_title = "") {
  
  $rtn = "";  

  $question = fp_reduce_whitespace($question);
  
  //$question = htmlentities($question, ENT_QUOTES);
  //$question = str_replace("\n", "\\n", $question);
  
  $question = str_replace("\\n", "<br>", $question);
  $question = str_replace("\n", "<br>", $question);
  $question_64 = base64_encode($question);  // convert to base_64 so we can have HTML
  
  
  $link_title = htmlentities($link_title, ENT_QUOTES);
  
  //$rtn .= "<a href='javascript: if(fp_confirm(\"$question\")) { $action_if_yes;  }' class='$extra_class' title='$link_title'>$link_text</a>";
  
  // Using new fp_confirm code
  $action_if_yes_64 = base64_encode($action_if_yes);
  $rtn .= "<a href='javascript: fp_confirm(\"$question_64\",\"base64\",\"$action_if_yes_64\");' class='$extra_class' title='$link_title'>$link_text</a>";

      
  return $rtn;
}

/**
 * Creates a javascript "prompt" link, which will ask the user a question.
 * 
 * Similar to the fp_get_js_confirm_link function, but this is a prompt box
 * which lets the user type in a response.
 * 
 * 
 * @see fp_get_js_confirm_link
 */
function fp_get_js_prompt_link($question, $default, $action_if_yes, $link_text, $extra_class = "") {
  
  $rtn = "";  

  $question = fp_reduce_whitespace($question);
  
  $question = htmlentities($question, ENT_QUOTES);
  $question = str_replace("\n", "\\n", $question);
  
  $rtn .= "<a href='javascript: var response = prompt(\"$question\", \"$default\");
                                if (response != null) 
                                {
                                   $action_if_yes ;  
                                }
                                ' class='$extra_class'>$link_text</a>";

      
  return $rtn;
}


/**
 * Creates a javascript "alert" link, which tells the user some message with javascript alert().
 * 
 * Similar to the fp_get_js_confirm_link function, but this is a simple alert message,
 * with no user input.
 * 
 * 
 * @see fp_get_js_confirm_link
 */
function fp_get_js_alert_link($message, $link_text = NULL, $extra_css_class = "", $link_title = "View help for this item") {
  
  $rtn = "";  

  if ($link_text == "" || $link_text == NULL) {

    $link_text = "<span class='pop-q-mark'><i class='fa fa-question-circle'></i></span>";
  }
  
  $message = str_replace("\n", " ", $message);
  $message = fp_reduce_whitespace($message);


  $rtn .= "<a href='javascript: fp_alert(\"" . base64_encode($message) . "\",\"base64\");' title='$link_title' class='fp-alert-link $extra_css_class'>$link_text</a>";
  
      
  return $rtn;  
  
}


/**
 * Simple helper function to reduce whitespace (like double-spaces) 
 *
 * @param string $str
 */
function fp_reduce_whitespace($str) {
  // Cheap hack to get rid of whitespace    
  for ($t = 0; $t < 5; $t++) {
    $str = str_replace("  ", " ", $str);
    $str = str_replace("\n ", "\n", $str);
  }  



  return $str;  
}



/**
 * Does the user have the specified role?
 */
function user_has_role($role, $account = NULL) {
  global $user;
  
  if ($account == NULL) $account = $user;
  
  // Admin always = TRUE
  if ($account->id == 1) return TRUE;
  
  // Check for other users...
  if (in_array($role, $account->roles)) return TRUE;
  
  return FALSE;
  
}


/**
 * Returns TRUE or FALSE if the logged in user has access based on the
 * permission supplied.
 *
 * @param String $permission
 */
function user_has_permission($permission = "", $account = NULL) {
  global $user;  
  
  if ($account == NULL) $account = $user;
  //fpm("checking permission $permission");
  
  if ($account == NULL) {
    // The account is STILL null, so return FALSE.
    // Most likely anonymous user.
    return FALSE;
  }
  
  // If the user is admin (id == 1) then they always have access.
  if ($account->id == 1) return TRUE;
  
  if (!isset($account->permissions) || !is_array($account->permissions)) return FALSE;   // not set up yet; anonymous user most likely.
  
  // Otherwise, simply check their permissions array.
  if (in_array($permission, $account->permissions)) {
    return TRUE;
  }
  
    
  return FALSE;
  
}




/**
 * This function will read through all the modules' permissions and
 * return back an array.  Specifically, it retrieves arrays from each
 * modules' hook_perm() function.
 *
 */
function get_modules_permissions() {
  $rtn = array();
  
  
  foreach ($GLOBALS["fp_system_settings"]["modules"] as $module => $value) {
    
    if (isset($value["disabled"]) && $value["disabled"] == "yes") {
      // Module is not enabled.  Skip it.
      continue;
    }
    
    
    if (function_exists($module . "_perm")) {
      $rtn[$module][] = call_user_func($module . "_perm");
    }
  }
    
  return $rtn;
}



/**
 * Similar to get_modules_permissions, this will scan through all installed
 * modules' hook_menu() functions, and assemble an array which is sorted
 * by "location" and then by "weight".
 *
 */
function get_modules_menus() {
  
  $menus = array();
  foreach ($GLOBALS["fp_system_settings"]["modules"] as $module => $value) {    
    if (isset($value["disabled"]) && $value["disabled"] == "yes") {
      // Module is not enabled.  Skip it.
      continue;
    }    
    if (function_exists($module . "_menu")) {
      $menus[] = call_user_func($module . "_menu");      
    }
  }
  
  // Let's re-order based on weight...
  // Convert to a single dimensional array for easier sorting.
  $temp = array();
  foreach ($menus as $c => $value) {
    foreach ($menus[$c] as $d => $menu_data) {
      $w = $menu_data["weight"];
      if ($w == "") $w = "0";
    
      // We need to front-pad $w with zeros, so it is the same length
      // for every entry.  Otherwise it will not sort correctly.
      $w = fp_number_pad($w, 10);      
      
      $temp[] = "$w~~$c~~$d";    
    }
  }
  
  //var_dump($temp);
  // Now, sort $temp...
  sort($temp);
  //var_dump($temp);
  // Now, go back through $temp and get our new array...
  $new_array = array();
  
  foreach ($temp as $t) {
    $vals = explode("~~", $t);
    $c = $vals[1];
    $d = $vals[2];
    
    // Place them into subarrays indexed by location
    $new_array[$menus[$c][$d]["location"]][] = $menus[$c][$d];    
  }
  
  return $new_array;
  
}


/**
 * Simple function to left padd numbers with 0's.
 * 1 becomes 001
 * 20 becomes 020 
 * and so on.
 *
 * @param int $number
 * @param int $n
 * @return String
 */
function fp_number_pad($number, $len) {
  return str_pad((int) $number, $len, "0", STR_PAD_LEFT);
}


/**
 * This simple function will take a number and truncate the number of decimals
 * to the requested places.  This can be used in place of number_format(), which *rounds*
 * numbers.
 * 
 * For example, number_format(1.99999, 2) gives you 2.00.
 * But THIS function gives:
 *   fp_truncate_decimals(1.999999, 2) = 1.99
 * 
 *
 * @param unknown_type $places
 */
function fp_truncate_decimals($num, $places = 2) {
    
  // does $num contain a .?  If not, add it on.
  if (!strstr("" . $num, ".")) {
    $num .= ".0";
  }
  
  // Break it by .
  $temp = explode (".", "" . $num);
  
  // Get just the decimals and trim 'em
  $decimals = trim(substr($temp[1], 0, $places));
  if (strlen($decimals) < $places) {
    // Padd with zeros on the right!
    $decimals = str_pad($decimals, $places, "0", STR_PAD_RIGHT);
  }
  
  $new_num = $temp[0] . "." . $decimals; 
  
  return $new_num;
}

/**
 * Adapted from https://api.drupal.org/api/drupal/includes%21common.inc/function/drupal_query_string_encode/6.x
 */
function fp_query_string_encode($query, $exclude = array(), $parent = '') {
  $params = array();
  foreach ($query as $key => $value) {
    $key = rawurlencode($key);
    if ($parent) {
      $key = $parent . '[' . $key . ']';
    }
    if (in_array($key, $exclude)) {
      continue;
    }
    if (is_array($value)) {
      $params[] = fp_query_string_encode($value, $exclude, $key);
    }
    else {
      $params[] = $key . '=' . rawurlencode($value);
    }
  }
  return implode('&', $params);
}




/**
 * Shortcut to fp_debug_current_time_millis()
 * 
 * @see fp_debug_current_time_millis()
 *
 * @param unknown_type $debug_val
 * @param unknown_type $var
 * @return unknown
 */
function fp_debug_ct($debug_val = "", $var = "")
{ // Shortcut to the other function.
  return fp_debug_current_time_millis($debug_val, false, $var);
}



/**
 * When called repeatedly, this function will display a message along with a milisecond count
 * out to the side.  Very useful for developers to time function calls or queries, to see how long they
 * are taking.
 * 
 * For example:
 * fp_debug_ct("starting query");
 * db_query(".........") // whatever
 * fp_debug_ct("finished query");
 * 
 * On screen, that would display our messages, with time values, so we can see how many milliseconds
 * it took to execute between calls of fp_debug_ct().
 *
 * @param String $debug_val
 *          The message to display on screen.
 * @param boolean $show_current_time
 *          Should we display the current time as well?
 * @param String $var
 *          Optional.  Include a variable name so you can have more than one timer running
 *          at the same time.
 * @return unknown
 */
function fp_debug_current_time_millis($debug_val = "", $show_current_time = true, $var = "")
{
  // Display the current time in milliseconds, and, if available,
  // show how many milliseconds its been since the last time
  // this function was called.  This helps programmers tell how
  // long a particular function takes to run.  Just place a call
  // to this function before and after the function call.
  
  $rtn = "";
  
  $debug_string = $debug_val;
  if (is_array($debug_val) || is_object($debug_val)) {
    $debug_string = "<pre>" . print_r($debug_val, true) . "</pre>";
  }

  $last_time = @($GLOBALS["current_time_millis" . $var]) * 1;  //*1 forces numeric

  $cur_time = microtime(true) * 1000;

  $debug_string = "<span style='color:red;'>DEBUG:</span>
            <span style='color:green;'>$debug_string</span>";

  $rtn .= "<div style='background-color: white;'>$debug_string";

  if ($last_time > 1)
  {
    $diff = round($cur_time - $last_time,2);
    $rtn .= "<span style='color: blue;'> ($diff" . t("ms since last call") . "</span>";
  } else {
    // Start of clock...
    $rtn .= "<span style='color: blue;'> --- </span>";
  }

  $rtn .= "</div>";
  $GLOBALS["current_time_millis" . $var] = $cur_time;
  $GLOBALS["current_time_millis"] = $cur_time;

  return $rtn;
}

