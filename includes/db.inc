<?php

/**
 * @file
 * This file contains mostly db shortcuts.
 */


/**
 * This method will return a globally-set DatabaseHandler object,
 * creating it if it does not already exist.  This is for efficiency
 * reasons, so every module or method does not need to keep creating
 * databasehandler objects (and re-connecting to the database).
 *
 */
function get_global_database_handler() {

  $db = NULL;

  if (isset($GLOBALS["fp_global_database_handler"])) {
    $db = $GLOBALS["fp_global_database_handler"];
  }

  if (!is_object($db) || !is_object($db->pdo)) {
    // Something isn't right, or it wasn't set correctly.  Create a new connection.
    $GLOBALS["fp_global_database_handler"] = new DatabaseHandler();
  }

  return $GLOBALS["fp_global_database_handler"];

}


/**
 * Add a log entry to the watchdog (log) table.
 *
 * This is adapted from Drupal 6's watchdog system.
 *
 * @param string $type
 *    Generally, this is the name of the module, or some other short text
 *    which can be used to categorize this log entry. Ex:  "system" or "routines".
 * @param string $message
 *    This is the actual log message itself.  It can be any length, and contain replacement
 *    patterns (very similar to the t() function.) Ex: "The user @user has logged in."
 * @param array $variables
 *    If replacement patterns exist in the $message, this is where they are defined, similar
 *    to the t() function. Ex:  array("@user" => $user->name) *
 * @param int $severity
 *    One of several constant values, denoting the severity level.
 *    Available values:
 *      - WATCHDOG_DEBUG    (for development)
 *      - WATCHDOG_NOTICE   (default)
 *      - WATCHDOG_ALERT    (a step above "notice")
 *      - WATCHDOG_ERROR    (highest level of severity)
 * @param string $extra_data
 *    Any extra bit of text you want to add on. Must be 255 characters or less.  Good for adding
 *    extra codes and such which can be queried for later more easily.
 */
function watchdog($type, $message, $variables = array(), $severity = WATCHDOG_NOTICE, $extra_data = "") {
  global $user;


  $user_id = 0;
  $cwid = 0;
  $user_name = "";
  $is_student = 0;
  $is_faculty = 0;
  $school_id = 0;
  if (is_object($user)) {
    $user_id = @$user->id;
    $cwid = @$user->cwid;
    $user_name = @$user->name;
    $is_student = (int) @$user->is_student;
    $is_faculty = (int) @$user->is_faculty;
    $school_id = (int) @$user->school_id;
  }

  $is_mobile = 0;   //TODO:  not used anymore! //(int) fp_screen_is_mobile();

  $ip = @$_SERVER["REMOTE_ADDR"];
  $location = @$_SERVER["REQUEST_URI"];
  $referer = @$_SERVER['HTTP_REFERER'];

  $ser_variables = "";
  if (is_array($variables) && count($variables) > 0) {
    $ser_variables = serialize($variables);
  }

  db_query("INSERT INTO watchdog
            (user_id, user_name, cwid, type, message, variables, severity, extra_data, location, referer, ip, is_mobile, is_student, is_faculty, timestamp, school_id)
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", $user_id, $user_name, $cwid, $type, $message, $ser_variables, $severity, $extra_data, $location, $referer, $ip, $is_mobile, $is_student, $is_faculty, time(), $school_id);


}






/**
 * Figure out the maximum number of times this course can be repeated for credit, based on what is stored
 * in the course catalog.
 *
 * We will do this by directly querying the database, for speed reasons.
 *
 */
function fp_get_max_catalog_repeats_for_course($subject_id, $course_num, $catalog_year, $bool_draft = TRUE, $school_id = 0) {
  $table_name = "courses";
  if ($bool_draft) {
    $table_name = "draft_courses";
  }

  $res = db_query("SELECT * FROM $table_name
                      WHERE subject_id = ?
                      AND course_num = ?
                      AND catalog_year = ?
                      AND school_id = ? ", $subject_id, $course_num, $catalog_year, $school_id);
  $cur = db_fetch_array($res);

  if (!$cur) {
    // Meaning, this query didn't find the course we were looking for.  Return NULL.
    return NULL;
  }

  $min_hours = $cur['min_hours'];
  $max_hours = $cur['max_hours'];
  $repeat_hours = $cur['repeat_hours'];

  if ($repeat_hours <= $min_hours) {
    // Meaning, this course cannot be repeated for anything.  So, just return 1, meaning, it can be taken only once.
    return 1;
  }

  // Okay, so what we want to do is figure out, if this is a 3 hour course, and it can be repeated for 9 hours, that means it
  // can be taken (repeats) 3 times.

  // We will use the min_hours for this calculation.  If zero, then change it to 1.
  if ($min_hours <= 0) $min_hours = 1;

  // Use intval so we don't have decimals.  Whole attempts only.
  $repeats = intval($repeat_hours / $min_hours);

  return $repeats;

} // fp_get_max_catalog_repeats_for_course



/**
 * Code adapted from Drupal 6: https://api.drupal.org/api/drupal/includes%21pager.inc/function/pager_query/6.x
 *
 * This helps allow us to create a paginated query (where we have Prev and Next links for the results).
 *
 * NOTE!!!!!!!!!  To render the pager, call the function theme_pager() after getting the results of this query
 *
 * Description from the Drupal API page:
 *
 * Use this function when doing select queries you wish to be able to page. The pager uses LIMIT-based queries to fetch only the records
 * required to render a certain page. However, it has to learn the total number of records returned by the query to compute the number
 * of pages (the number of records / records per page). This is done by inserting "COUNT(*)" in the original query.
 * For example, the query "SELECT nid, type FROM node WHERE status = '1' ORDER BY sticky DESC, created DESC" would be
 * rewritten to read "SELECT COUNT(*) FROM node WHERE status = '1' ORDER BY sticky DESC, created DESC". Rewriting the query is accomplished
 * using a regular expression.
 *
 * Unfortunately, the rewrite rule does not always work as intended for queries that already have a "COUNT(*)" or a "GROUP BY" clause,
 * and possibly for other complex queries. In those cases, you can optionally pass a query that will be used to count the records.
 *
 * For example, if you want to page the query "SELECT COUNT(*), TYPE FROM node GROUP BY TYPE", pager_query()
 * would invoke the incorrect query "SELECT COUNT(*) FROM node GROUP BY TYPE". So instead,
 * you should pass "SELECT COUNT(DISTINCT(TYPE)) FROM node" as the optional $count_query parameter.
 *
 * $query: The SQL query that needs paging.
 * $limit: The number of query results to display per page.
 * $element: An optional integer to distinguish between multiple pagers on one page.
 * $count_query: An SQL query used to count matching records.
 *
 *
 */
function pager_query($query, $args = array(), $limit = 10, $element = 0, $count_query = NULL, $count_query_select_from = NULL) {
  global $pager_page_array, $pager_total, $pager_total_items;
  $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : '';

  // Construct a count query if none was given.
  if (!isset($count_query)) {

    $select_from_replace = 'SELECT COUNT(*) ';
    if ($count_query_select_from) {
      $select_from_replace = $count_query_select_from;
    }


    $count_query = preg_replace(array(
      '/SELECT.*?FROM /As',
      '/ORDER BY .*/',
      '/GROUP BY .*/',
    ), array(
      $select_from_replace . " FROM ",
      '',
      '',
    ), $query);
  }

  // Convert comma-separated $page to an array, used by other functions.
  $pager_page_array = explode(',', $page);

  // We calculate the total of pages as ceil(items / limit).
  $pager_total_items[$element] = db_result(db_query($count_query, $args));
  $pager_total[$element] = ceil($pager_total_items[$element] / $limit);
  $pager_page_array[$element] = max(0, min((int) $pager_page_array[$element], (int) $pager_total[$element] - 1));

  return db_query_range($query, $args, $pager_page_array[$element] * $limit, $limit);
}



/**
 * Adds a simple LIMIT statement to a query.  Adapted from Drupal 6: https://api.drupal.org/api/drupal/includes%21database.mysql.inc/function/db_query_range/6.x
 */
function db_query_range($query, $args = array(), $from = 0, $count = 10) {

  $query .= ' LIMIT ' . (int) $from . ', ' . (int) $count;

  return db_query($query, $args);
}






/**
 * Returns the major code for a given degree_id
 */
function fp_get_degree_major_code($degree_id, $bool_reset_cache = FALSE) {

  if ($bool_reset_cache) {
    unset($GLOBALS['fp_temp_degree_major_codes']);
  }

  // We will cache in a GLOBALS variable, to save lookup time.
  if (isset($GLOBALS['fp_temp_degree_major_codes'][$degree_id])) {
    return $GLOBALS['fp_temp_degree_major_codes'][$degree_id];
  }

  $major_code = '';

  $res = db_query("SELECT major_code FROM degrees WHERE degree_id = ?", $degree_id);
  $cur = db_fetch_array($res);
  if ($cur) {
    $major_code = trim($cur['major_code']);
  }

  if ($major_code) {
    $GLOBALS['fp_temp_degree_major_codes'][$degree_id] = $major_code;
  }

  return $major_code;

}

function fp_get_degree_advising_weight($degree_id, $bool_reset_cache = FALSE) {

  if ($bool_reset_cache) {
    unset($GLOBALS['fp_temp_degree_advising_weights']);
  }

  // We will cache in a GLOBALS variable, to save lookup time.
  if (isset($GLOBALS['fp_temp_degree_advising_weights'][$degree_id])) {
    return $GLOBALS['fp_temp_degree_advising_weights'][$degree_id];
  }

  $advising_weight = 0;

  $res = db_query("SELECT advising_weight FROM degrees WHERE degree_id = ?", $degree_id);
  $cur = db_fetch_array($res);
  if ($cur) {
    $advising_weight = intval(trim($cur['advising_weight']));
  }

  if ($advising_weight) {
    $GLOBALS['fp_temp_degree_advising_weights'][$degree_id] = $advising_weight;
  }

  return $advising_weight;

}


/**
 * Quick method to look up title for a degree.
 */
function fp_get_degree_title($degree_id, $bool_include_track_title = FALSE, $bool_include_html = TRUE, $bool_use_draft = FALSE, $bool_include_major_code = FALSE) {

  // TODO:  Check cache.
  $dtitle = "";
  $track_title = "";


  // Still no title?  Try to load ANY degree title with this degree's
  // major_code.
  $table_name = "degrees";
  if ($bool_use_draft) {$table_name = "draft_$table_name";}

  $res = db_query("SELECT * FROM $table_name
                          WHERE degree_id = ?
                          ORDER BY catalog_year DESC LIMIT 1", $degree_id);
  $cur = db_fetch_array($res);
  if (!$cur) return '';

  $dtitle = $cur["title"];
  $major_code = $cur['major_code'];
  $o_major_code = $cur['major_code'];
  $catalog_year = $cur['catalog_year'];

  if ($bool_include_track_title) {

    // Get track title
    if (strstr($major_code, "_"))
    {
      // This means that there is a track.  Get all the information
      // you can about it.
      $temp = explode("_", $major_code);
      $track_code = fp_trim(@$temp[1]);
      $major_code = fp_trim(@$temp[0]);

      // The major_code might now have a | at the very end.  If so,
      // get rid of it.
      if (substr($major_code, strlen($major_code)-1, 1) == "|")
      {
        $major_code = str_replace("|","",$major_code);
      }
      // Now, look up information on the track.
      $table_name = "degree_tracks";
      if ($bool_use_draft) {$table_name = "draft_$table_name";}

      $res = db_query("SELECT track_title FROM $table_name
                              WHERE major_code = '?'
                              AND track_code = '?'
                              AND catalog_year = '?' ", $major_code, $track_code, $catalog_year);
      $cur = db_fetch_array($res);

      $track_title = $cur["track_title"];
    }

    if ($track_title != "") {
      if ($bool_include_html) {
        $dtitle .= "<span class='level-3-raquo'>&raquo;</span>";
      }
      $dtitle .= $track_title;
    }


  }


  if ($bool_include_major_code) {
    $dtitle .= " [$major_code]";
  }

  return $dtitle;


}





/**
 * Returns the faculty member's name based on the ID provided.
 */
function fp_get_faculty_name($cwid) {

  // Already cached?
  if (isset($GLOBALS['fp_cache_get_faculty_name'][$cwid])) {
    return $GLOBALS['fp_cache_get_faculty_name'][$cwid];
  }


  $db = get_global_database_handler();
  $name = $db->get_faculty_name($cwid, FALSE);
  if (!$name) $name = t("Unknown Advisor");

  $GLOBALS['fp_cache_get_faculty_name'][$cwid] = $name;

  return $name;
}




/**
 * Returns back a user object for this user_id.
 * If the user is not found in the users table, it will return NULL.
 * If the user_id requested is 0, the anonymous user object is returned.
 */
function fp_load_user($user_id) {

  $rtn = new stdClass();

  if ($user_id <= 0) {
    // Return the anonymous user.
    $rtn->id = 0;
    $rtn->name = t("Anonymous");
    $rtn->f_name = '';
    $rtn->l_name = '';
    $rtn->roles = array(1 => "anonymous user");
    $rtn->email = '';
    $rtn->cwid = '';
    $rtn->school_id = 0;
    $rtn->is_student = FALSE;
    $rtn->is_faculty = FALSE;
    $rtn->is_disabled = FALSE;
    $rtn->last_login = 0;
    $rtn->roles = array();
    $rtn->settings = array();
    $rtn->attributes = array();
    $rtn->permissions = fp_get_permissions_for_role(1);
    return $rtn;
  }

  $res = db_query("SELECT * FROM users WHERE user_id = ? ", $user_id);

  if (db_num_rows($res) == 0) return NULL;
  $cur = db_fetch_object($res);
  $rtn->id = $cur->user_id;
  $rtn->name = $cur->user_name;
  $rtn->f_name = $cur->f_name;
  $rtn->l_name = $cur->l_name;
  $rtn->school_id = intval($cur->school_id);
  $rtn->email = trim(strtolower($cur->email));
  $rtn->cwid = $cur->cwid;
  $rtn->is_student = (bool) $cur->is_student;
  $rtn->is_faculty = (bool) $cur->is_faculty;
  $rtn->is_disabled = (bool) $cur->is_disabled;
  $rtn->last_login = (int) $cur->last_login;
  $rtn->roles = array();
  $rtn->permissions = array();

  // Load the user's roles and
  $res = db_query("SELECT * FROM user_roles a,
                                roles c
                  WHERE a.user_id = ?
                  AND a.rid = c.rid ", array($user_id));
  while($cur = db_fetch_array($res)) {
    if (!isset($rtn->roles[$cur['rid']])) {
      $rtn->roles[$cur["rid"]] = $cur["name"];
    }
  }


  // Let's make sure we get the authenticated user role as well, #2.
  $rtn->roles[2] = "authenticated user";
  // Go through each role and add in the permissions for each role.
  foreach ($rtn->roles as $rid => $val) {
    $perms = fp_get_permissions_for_role($rid);

    // Merge the arrays while KEEPING the original's key.  So don't
    // use array_merge, use the + operator.
    $rtn->permissions = $rtn->permissions + $perms;
  }

  // Load settings from the user_settings table (if any exist)
  $rtn->settings = array();
  $res = db_query("SELECT * FROM user_settings WHERE user_id = ?", array($user_id));
  while ($cur = db_fetch_array($res)) {
    $rtn->settings[$cur['name']] = $cur['value'];
  }




  // Set the user image url...
  // See if there are any other modules which might be overriding how we look up user images.
  $image_url = @$cur->settings['image_url'];
  $type = 'student';
  if ($rtn->is_faculty) $type = 'faculty';
  $modules = invoke_hook("get_user_image_url", array($rtn->cwid, $type));
  // Although there might be several modules which will return an image url, we will only use the last one (even if it's blank or FALSE, meaning no picture).
  foreach ($modules as $val) {
    $image_url = trim($val);
  }
  $rtn->settings['image_url'] = $image_url;



  // load attributes from user_attributes table (if any exist)
  $rtn->attributes = array();
  $res = db_query("SELECT * FROM user_attributes WHERE user_id = ?", array($user_id));
  while ($cur = db_fetch_array($res)) {
    $rtn->attributes[$cur['name']] = $cur['value'];
  }


  return $rtn;
}


/**
 * Returns back the timezone for this user, if selected.  If not, we return back the system timezone.
 */
function fp_get_user_timezone($account = NULL) {
  global $user;

  if (is_numeric($account)) {
    $account = fp_load_user($account);
  }

  if ($account == NULL) {
    $account = $user;
  }


  $utz = NULL;
  if (isset($account->settings['timezone'])) {
    $utz = fp_trim($account->settings['timezone']);
  }
  if ($utz) return $utz;

  // Else...

  $system_timezone = variable_get('system_timezone', 'America/Chicago');
  return $system_timezone;


}






/**
 * Look up the user_id based on the the user_name.  Returns FALSE if it cannot find it.
 *
 * @param unknown_type $user_name
 */
function db_get_user_id($user_name) {
  $user_id = db_result(db_query("SELECT user_id FROM users WHERE user_name = ?", $user_name));

  if ($user_id) {
    return $user_id;
  }

  return FALSE;

}


function db_get_cwid_from_user_id($user_id) {
  return db_result(db_query("SELECT cwid FROM users WHERE user_id = ?", array($user_id)));
}


function db_get_user_id_from_cwid($cwid, $type = "faculty") {

  $type_line = " is_faculty='1' ";
  if ($type == "student") {
    $type_line = " is_student='1' ";
  }

  $user_id = db_result(db_query("SELECT user_id FROM users WHERE cwid = ?
                                    AND $type_line ", $cwid));

  if ($user_id) {
    return $user_id;
  }

  return FALSE;

}


function db_get_user_id_from_email($email, $type = "faculty") {

  $type_line = " is_faculty='1' ";
  if ($type == "student") {
    $type_line = " is_student='1' ";
  }

  // Force email to be lowercase
  $email = trim(strtolower($email));

  $user_id = db_result(db_query("SELECT user_id FROM users WHERE email = ? AND $type_line ", $email));

  if ($user_id) {
    return $user_id;
  }

  return FALSE;

}


function db_get_user_id_from_user_name($user_name, $type = "faculty") {

  $type_line = " is_faculty='1' ";
  if ($type == "student") {
    $type_line = " is_student='1' ";
  }

  // Force to be lowercase
  $user_name = trim(strtolower($user_name));

  $user_id = db_result(db_query("SELECT user_id FROM users WHERE user_name = ? AND $type_line ", $user_name));

  if ($user_id) {
    return $user_id;
  }

  return FALSE;

}






/**
  Return back the codes or records for a student's degrees, based on what is in the
  student_degrees table (thanks to system.module), as well as what we get from hooks.
*/
function fp_get_student_majors($student_cwid, $bool_return_as_csv = FALSE, $bool_return_as_full_record = FALSE, $perform_join_with_degrees = TRUE, $bool_skip_directives = TRUE, $bool_check_for_allow_dynamic = TRUE) {

  $params = array($student_cwid, $bool_return_as_full_record, $perform_join_with_degrees, $bool_skip_directives, $bool_check_for_allow_dynamic);

  // Execute hook for this too.
  $arr = invoke_hook("fp_get_student_majors", $params);

  // Results will appear in an array, with each module as the index.
  // Ex:
  //   system
  //      MAJOR1 => MAJOR1
  //      MAJOR2 => MAJOR2
  //   custom_module
  //      MAJOR1 => MAJOR1
  //      XYZ => XYZ


  $new_arr = array();
  $csv = "";
  // Go through our modules, combine the identical ones, then prepare as a possible CSV.
  foreach ($arr as $module => $results) {
    foreach ($results as $k => $v) {
      $new_arr[$k] = $v;
    }
  }

  $rtn = $new_arr;


  // Returning as a CSV?

  if ($bool_return_as_csv) {
    foreach ($new_arr as $k => $v) {
      $csv .= $k . ",";
    }
    $csv = rtrim($csv, ",");
    $rtn = $csv;
  }




  return $rtn;

}




function fp_get_student_name($cwid, $bool_include_cwid = FALSE ) {
  if (is_numeric($cwid) && ($cwid === 0 || $cwid === '0')) {
    return t("Anonymous");
  }

  // Already cached?
  if (isset($GLOBALS['fp_cache_get_student_name'][$cwid][intval($bool_include_cwid)])) {
    return $GLOBALS['fp_cache_get_student_name'][$cwid][intval($bool_include_cwid)];
  }

  $db = get_global_database_handler();
  $name = $db->get_student_name($cwid, $bool_include_cwid);
  if (!$name) $name = t("Unknown Student");


  $GLOBALS['fp_cache_get_student_name'][$cwid][intval($bool_include_cwid)] = $name;

  return $name;
}



function fp_get_student_email($cwid) {
  $email = db_result(db_query("SELECT email FROM users
                               WHERE cwid = ?
                               AND is_student = 1", array($cwid)));

  if (trim($email)) {
    return trim(strtolower($email));
  }

  // else, email wasn't set.
  return FALSE;

}



function fp_get_faculty_email($cwid) {
  $email = db_result(db_query("SELECT email FROM users
                               WHERE cwid = ?
                               AND is_faculty = 1", array($cwid)));

  if (trim($email)) {
    return trim(strtolower($email));
  }

  // else, email wasn't set.
  return FALSE;

}




function fp_get_permissions_for_role($rid) {
  $rtn = array();
  $res = db_query("SELECT pid, perm FROM role_permissions
                   WHERE rid = ?
                   GROUP BY perm
                   ORDER BY perm", array($rid));
  while($cur = db_fetch_array($res)) {
    if (!in_array($cur['perm'], $rtn)) {
      $rtn[$cur["pid"]] = $cur["perm"];
    }
  }
  return $rtn;
}


/**
 * Returns back the first result from a resource_handler.
 */
function db_result($res) {

  if (is_object($res)) {
    return $res->fetchColumn();  // fetches the first value returned.
  }

  return NULL;
}


function db_insert_id() {
  $db = get_global_database_handler();
  return $db->db_insert_id();
}


/**
 * Return the array from the user_settings table.
 *
 * @param unknown_type $user_id
 */
function db_get_user_settings($user_id) {
  $db = get_global_database_handler();

  return $db->get_user_settings($user_id);
}

function db_get_school_id_for_student_id($student_id) {
  $db = get_global_database_handler();
  return $db->get_school_id_for_student_id($student_id);
}


function db_get_school_id_for_user_id($user_id) {
  $db = get_global_database_handler();
  return $db->get_school_id_for_user_id($user_id);
}


/**
 * Return a specific setting's value, based on the var_name given.
 *
 * @param unknown_type $user_id
 * @param unknown_type $var_name
 */
function db_get_user_setting($user_id, $var_name = "", $default_value = "") {
  $settings = db_get_user_settings($user_id);
  $val = @$settings[$var_name];
  if (!$val) {
    $val = $default_value;
  }

  return $val;
}


function db_set_user_setting($user_id, $var_name, $value) {
  $settings = db_get_user_settings($user_id);
  $settings[$var_name] = $value;

  $ser = serialize($settings);

  db_query("DELETE FROM user_settings WHERE user_id = '?' ", $user_id);
  db_query("INSERT INTO user_settings (user_id, settings, posted)
            VALUES ('?', '?', '?') ", $user_id, $ser, time());
}





function db_query($query) {
  // Capture arguments to this function, to pass along to our $db object.

  $args = func_get_args();

  array_shift($args);

  $db = get_global_database_handler();

  $res = $db->db_query($query, $args);

  return $res;
}

function db_fetch_array($result_handler) {
  $db = get_global_database_handler();
  return $db->db_fetch_array($result_handler);
}

function db_fetch_object($result_handler) {
  $db = get_global_database_handler();
  return $db->db_fetch_object($result_handler);
}

function db_num_rows($result_handler) {
  $db = get_global_database_handler();
  return $db->db_num_rows($result_handler);
}

function db_affected_rows($result_handler) {
  $db = get_global_database_handler();
  return $db->db_affected_rows($result_handler);
}


/**
 * Returns TRUE if the table specified exists or not.
 */
function db_table_exists($table_name) {
  $res = db_query("SHOW TABLES LIKE ? ", $table_name);
  $cur = db_fetch_array($res);
  if ($cur[0] == $table_name) {
    return TRUE;
  }

  return FALSE;

}



/**
 * Returns TRUE or FALSE if the variable exists in the variables table.
 */
function variable_exists($name) {
  if (isset($GLOBALS["fp_system_settings"][$name])) {
    return TRUE;
  }

  $count = intval(db_result(db_query("SELECT COUNT(name) as my_count
                     FROM variables
                     WHERE name = ?", array($name))));

  if ($count > 0) return TRUE;

  // else....
  return FALSE;

}


/**
 * Get a variable from the database.  We will first look in our GLOBALS array,
 * to see that it hasn't already been retrieved this page load.
 *
 * @param string $name
 * @param mixed $default_value
 * @return mixed
 */
function variable_get($name, $default_value = "") {

  $val = null;
  // First, check in our GLOBALS array, like a cache...
  if (isset($GLOBALS["fp_system_settings"][$name])) {
    $val = $GLOBALS["fp_system_settings"][$name];
  }
  else {
    // Not found-- look in the database for it.
    $res = db_query("SELECT value FROM variables
                           WHERE name = ? ", array($name));
    $cur = db_fetch_array($res);
    @$val = unserialize($cur["value"]);

    // Save back to our cache...
    $GLOBALS["fp_system_settings"][$name] = $val;

  }


  if (!$val) {
    $val = $default_value;
  }

  // We must have this down here again, just in case what got stored in the GLOBALS
  // array was this placeholder.  This can happen, because the settings file doesn't do
  // this check when assembling this variable on page load.  It's something that needs
  // to be fixed.
  if ($val === "BOOLEAN_FALSE_PLACEHOLDER") {
    $val = FALSE;
  }

  if ($val === "NULL_PLACEHOLDER") {
    $val = NULL;
  }

  return $val;
}


/**
 * Get a value for a particular school.
 * if bool_ignore_override is TRUE, then we won't worry if it's being overwritten or not.
 */
function variable_get_for_school($name, $default_value = "", $school_id = 0, $bool_ignore_override = FALSE) {
  $school_id = intval($school_id);

  // If this is the default school, OR, we are using NOT overriding the default vals for this school, then use variable_get normally
  if ($school_id === 0 || ($bool_ignore_override == FALSE && variable_get("school_override__$name~~school_" . $school_id, '') !== 'yes')) {
    return variable_get($name, $default_value);  // for default school, just work normally.
  }

  if ($bool_ignore_override == TRUE && !variable_exists("school_override__$name~~school_" . $school_id)) {
    return variable_get($name, $default_value);  // Can't find the override anyway, so get the default.
  }


  // If we are here, we need to get using a fieldname suffix with the school id.
  return variable_get($name . "~~school_" . $school_id, $default_value);

}






/**
 * Set a variable value, so we can retrieve it later on.
 *
 * This will write to our variables database table, as well as store in a cache
 * array for quick look-up later.
 *
 * @param unknown_type $name
 * @param unknown_type $value
 */
function variable_set($name, $value) {

  // Save back to our "cache" GLOBALS array:
  $GLOBALS["fp_system_settings"][$name] = $value;


  // Boolean FALSE presents unusual problems when we try to tell if it got unserialized correctly.
  // We will convert it to a placeholder so we can positively store it.
  if ($value === FALSE) {
    $value = "BOOLEAN_FALSE_PLACEHOLDER";
  }
  // Same for NULL value
  if ($value === NULL) {
    $value = "NULL_PLACEHOLDER";
  }


  db_query("DELETE FROM variables WHERE name = ?", $name);

  db_query("INSERT INTO variables (name, value)
              VALUES (?, ?) ", $name, serialize($value));


}


/**
 * Remove a variable entirely from the table
 */
function variable_delete($name) {
  unset($GLOBALS["fp_system_settings"][$name]);
  db_query("DELETE FROM variables WHERE name = ?", $name);
}




/**
 * Re-query the modules table and re-add to our global array.
 */
function fp_rebuild_modules_list($reinclude = TRUE) {
  unset($GLOBALS["fp_system_settings"]["modules"]);

  $res = db_query("SELECT * FROM modules WHERE enabled = 1
                      ORDER BY weight");
  while ($cur = db_fetch_array($res)) {

    $GLOBALS["fp_system_settings"]["modules"][$cur["name"]] = $cur;

    if ($reinclude) {
      include_module($cur["name"], FALSE);
    }

  }


}

