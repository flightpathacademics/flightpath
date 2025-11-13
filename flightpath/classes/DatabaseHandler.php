<?php


class DatabaseHandler extends stdClass
{
    
  public $pdo;
  
  
  function __construct()
  {
    
    $db_host = @$GLOBALS["fp_system_settings"]["db_host"];
    $db_port = @$GLOBALS["fp_system_settings"]["db_port"];
    $db_user = @$GLOBALS["fp_system_settings"]["db_user"];
    $db_pass = @$GLOBALS["fp_system_settings"]["db_pass"];
    $db_name = @$GLOBALS["fp_system_settings"]["db_name"];
    
    if ($db_host == "") return;  // some problem, do not proceed with the attempt to construct.
    
    $db_host_ip = $db_host;  // set as same as db_host for now.
    
    $this->pdo = $GLOBALS['pdo'];  // set in our settings.php file.
    
    ///////////////////  NOTE /////////////////////
    // The actual PDO connection happens in custom/settings.php.
    
  }
  
  
  /**
   * This is a PHP "magic" function.  Called during a serialize command.
   * Basically, we aren't trying to save any local variables.
   * In fact, we will get a fatal exception if we try to serialize our PDO connection.
   */
  function __sleep() {
    return array();
  }
  
  /**
   * This function is called when this objectis unserialized.  We want to reconnect to the database, so we'll call our constructor.
   */
  function __wakeup() {
    $this->__construct();
  }
  
  
  
  
  
  /**
   * Sets the maintenance mode.  $val should be either 0 (off) or 1 (on)
   *
   * @param integer $val
   */
  function set_maintenance_mode($val)
  {
    // Convenience function for setting maintenance mode. 0 = off, 1 = on.
    $this->set_settings_variable("maintenance_mode", $val);
  }
  
  
  
  
  function get_substitution_details($sub_id)
  {
    // Simply returns an associative array containing
    // the details of a substitution.  The subID specified
    // is the actual id of the row of the database in
    // flightpath.student_substitutions.
    
    $rtn_array = array();
    
    $res = $this->db_query("SELECT * FROM student_substitutions
                WHERE id = '?' ", $sub_id);
    if ($this->db_num_rows($res) > 0)
    {
      $cur = $this->db_fetch_array($res);
      $rtn_array["faculty_id"] = $cur["faculty_id"];
      $rtn_array["remarks"] = trim($cur["sub_remarks"]);
      $rtn_array["sub_hours"] = $cur["sub_hours"];
      $rtn_array["required_course_id"] = $cur["required_course_id"];
      $rtn_array["required_group_id"] = $cur["required_group_id"];
      $rtn_array["posted"] = $cur["posted"];
      $rtn_array["required_degree_id"] = $cur["required_degree_id"];
      $rtn_array["db_record"] = $cur;
    }
    
    return $rtn_array;
    
  }
  
  
  
  function get_developmental_requirements($student_cwid)
  {
    // returns an array which states whether or not the student
    // requires any developmental requirements.
    
    $rtn_array = array();
    
    $res = $this->db_query("SELECT * FROM student_developmentals
                       WHERE student_id = ?
                       ", $student_cwid);
    while($cur = $this->db_fetch_array($res)) {
      $rtn_array[] = $cur["requirement"];
    }
    
    return $rtn_array;
    
  }
  
  
  
  
  
  /**
   * This is a simple helper function which "escapes" the question marks (?) in
   * the string, by changing them to "??".  This makes it suitable for use
   * within db_query(), but not necessary if used as an argument.  Ex:
   * db_query("INSERT ... '" . $db->escape_question_marks($xml) . "' ");  is good.
   * db_query("INSERT ... '?' ", $xml);  is good.   This function not needed.
   *
   * @param unknown_type $str
   */
  function escape_question_marks($str) {
    $rtn = str_replace("?", "??", $str);
    return $rtn;
  }
  
  
  
  /**
   * This function is used to perform a database query. It uses PDO execute, which will
   * take automatically replace ? with variables you supply as the arguments to this function,
   * or as an array to this function.  Either will work.
   * Do this by using ?, or naming the variable like :name or :age.
   *
   * For example:
   * $result = $db->db_query("SELECT * FROM table WHERE name = ? and age = ? ", $name, $temp_age);
   * or
   * $result = $db->db_query("SELECT * FROM table WHERE name = ? AND age = ? ", array($name, $temp_age));
   * or
   * $result = $db->db_query("SELECT * FROM table WHERE name = :name ", array(":name" => $name));
   *
   * @param unknown_type $sql_query
   * @return unknown
   */
  function db_query($sql_query, $args = array()) {
    
    // If there were any arguments to this function, then we must first apply
    // replacement patterns.
    $args = func_get_args();
    
    array_shift($args);
    if (isset($args[0]) && is_array($args[0])) {
      // If the first argument was an array, it means we passed an array of values instead
      // of passing them directly.  So use them directly as our args.
      $args = $args[0];
      
      // If we were supplied an array, then we need to see if the NEW args[0] is an array...  If it is, grab the first element AGAIN.
      if (isset($args[0]) && is_array($args[0])) {
        $args = $args[0];
      }
    }
    
    
    // We need to make sure that arguments are passed without being contained in single quotes ('?').  Should be just ?
    $sql_query = str_replace("'?'", "?", $sql_query);
    
    // If $c (number of replacements performed) does not match the number of replacements
    // specified, warn the user.
    /*
     * Don't do this anymore, as it might throw off queries that don't use ?'s, but instead use :var  as the replacements.
     *
     if (substr_count($sql_query, "?") != count($args)) {
     fpm("<br><b>WARNING:</b> Replacement count does not match what was supplied to query: $sql_query<br><br>");
     }
     */
    
    //////////////////////////////////////////////
    
    // Run the sqlQuery and return the result set.
    if (!isset($this->pdo) || $this->pdo == NULL) fpm(debug_backtrace());
    
    
    try {
      
      $result = $this->pdo->prepare($sql_query);
      $result->execute($args);
      
      $_SESSION["fp_last_insert_id"] = $this->pdo->lastInsertId();  // capture last insert id, in case we ask for it later.
      
      return $result;
    }
    catch (Exception $ex) {
      // Some error happened!
      $this->db_error($ex);
    }
    
    
  } // db_query
  
  
  /**
   * Draw out the error onto the screen.
   *
   */
  function db_error(Exception $ex)
  {
    global $user;
    
    $arr = $ex->getTrace();
    
    $when_ts = convert_time(time());
    $when_english = format_date($when_ts);
    
    $message = $ex->getMessage();
    
    // If the message involves a complaint about the sql_mode, point the user to a
    // help page about setting the sql_mode.
    if (stristr($message, "sql_mode=")) {
      $message .= "<br><br><b>" . t("It appears this error is being caused because of your server's sql_mode setting.") . "</b> ";
      $message .= t("To set your sql_mode for MySQL, please see the following help page: <a href='http://getflightpath.com/node/1161' target='_blank'>http://getflightpath.com/node/1161</a>");
    }
    
    $file = $arr[2]["file"];
    if (strlen($file) > 50) {
      $file = "..." . substr($file, strlen($file) - 50);
    }
    
    
    $file_and_line = "Line " . $arr[2]["line"] . ": " . $file;
    
    @$query_and_args = print_r($arr[2]['args'], TRUE);
    
    // If we are on production, email someone!
    if (variable_get("notify_mysql_error_email_address",'') != "")
    {
      $server = @$_SERVER["SERVER_NAME"] . " - " . $GLOBALS['fp_system_settings']['base_url'];  // intentionally use the GLOBALS here, since it comes from settings.php file.
      $email_msg = t("A MYSQL error has occured in FlightPath.") . "
User: $user->name ($user->id)
Server: $server

Timestamp: $when_ts ($when_english)

*** Error: ***
$message

/-----------------------------------/

*** Location: ***
$file_and_line

/-----------------------------------/

*** Query/Args: ***
$query_and_args

/-----------------------------------/

*** Limited Backtrace: ***
" . print_r($arr, true) . "
";
fp_mail(variable_get("notify_mysql_error_email_address",''), "FlightPath MYSQL Error Reported on $server", $email_msg);
    }
    
    fpm(t("A MySQL error has occured:") . " $message<br><br>" . t("Location:") . " $file_and_line<br><br>" . t("The backtrace:"));
    fpm($arr);
    
    if (@$GLOBALS["fp_die_mysql_errors"] == TRUE) {
      print "\n<br>The script has stopped executing because of a MySQL error:
                    $message<br>
                    Location: $file_and_line<br>\n
             Please fix the error and try again.<br>\n";
                    print "<br><br>Timestamp: $when_ts ($when_english)
              <br><br>Program backtrace:
              <pre>" . print_r($arr, true) . "</pre>";
                    die;
    }
    
    // Also, check to see if the mysql_err is because of a lost connection, as in, the
    // server went down.  In that case, we should also terminate immediately, rather
    // than risk spamming an email recipient with error emails.
    if (stristr($message, "Lost connection to MySQL server")
        || stristr($message, "MySQL server has gone away")) {
          
          print "<h2 style='font-family: Arial, sans serif;'>Database Connection Error</h2>
              <br>
              <div style='font-size: 1.2em; font-family: Arial, sans serif; padding-left: 30px;
                          padding-right: 30px;'>
              Sorry, but it appears the database is currently unavailable.  This may
              simply be part of scheduled maintenance to the database server.  Please
              try again in a few minutes.  If the problem persists for longer
              than an hour, contact your technical support
              staff.
              
              </div>
              
              ";
          
          
          // DEV:  Comment out when not needed.
          // print "<pre>" . print_r($arr, TRUE) . "</pre>";
          
          die;
        }
        
        
        
  } // db_error
  
  
  
  
  
  
  
  function request_new_group_id()
  {
    // Return a valid new group_id...
    
    for ($t = 0; $t < 1000; $t++)
    {
      $id = mt_rand(1, 2147483640); // A few less than the max for a signed int in mysql.
      // Check for collisions...
      $res4 = $this->db_query("SELECT * FROM draft_group_requirements
              WHERE group_id = $id LIMIT 1");
      if ($this->db_num_rows($res4) == 0)
      { // Was not in the table already, so use it!
        return $id;
      }
    }
    
    return false;
    
  }
  
  
  
  /**
   * Generates a new advising session token and makes sure it is unique before returning it.
   */
  function request_new_advising_session_token() {
    
    for ($t = 0; $t < 1000; $t++) {  // try up to 1000 times
      
      $test_token = hash('sha256', mt_rand(0, 99999) . microtime() . mt_rand(0,99999));
      
      // check for collisions
      $res = $this->db_query("SELECT advising_session_id
                              FROM advising_sessions
                              WHERE advising_session_token = ?", array($test_token));
      if ($this->db_num_rows($res) == 0) {
        // Was not in the table, so we can use it.
        return $test_token;
      }
    }
    
    return FALSE;  // some kind of problem-- we never found an available token!
    
  }
  
  
  
  function request_new_course_id()
  {
    // Return a valid new course_id...
    
    for ($t = 0; $t < 1000; $t++)
    {
      $id = mt_rand(1, 2147483640); // A few less than the max for a signed int in mysql.
      // Check for collisions...
      $res4 = $this->db_query("SELECT * FROM draft_courses
              WHERE course_id = $id LIMIT 1");
      if ($this->db_num_rows($res4) == 0)
      { // Was not in the table already, so use it!
        return $id;
      }
    }
    
    return false;
    
  }
  
  
  
  function load_course_descriptive_data($course = null, $course_id = 0)
  {
    
    $school_id = 0;
    if ($course == NULL) {
      $school_id = $this->get_school_id_for_course_id($course_id);
    }
    else {
      $school_id = $this->get_school_id_for_course_id($course->course_id);
    }
    
    $current_catalog_year = variable_get_for_school("current_catalog_year", "2006", $school_id);
    $catalog_year = $current_catalog_year; // currentCatalogYear.
    if ($course != null)
    {
      $course_id = $course->course_id;
      $catalog_year = $course->catalog_year;
    }
    
    $array_valid_names = array();
    
    
    
    if ($course_id != 0)
    {
      $res = $this->db_query("SELECT * FROM courses
              WHERE course_id = '?'
              AND catalog_year = '?'
              AND catalog_year <= '?'
              AND delete_flag = '0'
              AND exclude = '0' ", $course_id, $catalog_year, $current_catalog_year);
      $cur = $this->db_fetch_array($res);
      
      if ($this->db_num_rows($res) < 1)
      {
        
        // No results found, so instead pick the most recent
        // catalog year that is not excluded (keeping below the
        // current catalog year from the settings)
        
        //$this2 = new DatabaseHandler();
        $res2 = $this->db_query("SELECT * FROM courses
              WHERE `course_id`='?'
              AND `subject_id`!=''
              AND `delete_flag` = '0'
              AND `exclude`='0'
              AND `catalog_year` <= '?'
              ORDER BY `catalog_year` DESC LIMIT 1", $course_id, $current_catalog_year);
        $cur = $this->db_fetch_array($res2);
        
        if ($this->db_num_rows($res2) < 1)
        {
          
          // Meaning, there were no results found that didn't have
          // the exclude flag set.  So, as a last-ditch effort,
          // go ahead and try to retrieve any course, even if it has
          // been excluded. (keeping below the
          // current catalog year from the settings)
          
          //$this3 = new DatabaseHandler();
          //
          $res3 = $this->db_query("SELECT * FROM courses
              WHERE course_id = '?'
              AND subject_id != ''
              AND delete_flag = '0'
              AND catalog_year <= '?'
              ORDER BY catalog_year DESC LIMIT 1", $course_id, $current_catalog_year);
          $cur = $this->db_fetch_array($res3);
          
        }
        
      }
      
      
      $title = $cur["title"];
      $description = trim($cur["description"]);
      $subject_id = trim(strtoupper($cur["subject_id"]));
      $course_num = trim(strtoupper($cur["course_num"]));
      $cache_catalog_year = $cur['catalog_year'];
      
      $min_hours = $cur["min_hours"];
      $max_hours = $cur["max_hours"];
      $repeat_hours = $cur["repeat_hours"];
      if ($repeat_hours*1 == 0)
      {
        $repeat_hours = $max_hours;
      }
      
      
      $db_exclude = $cur["exclude"];
      $db_school_id = $cur['school_id'];
      $data_entry_comment = $cur["data_entry_comment"];
      
      // Now, lets get a list of all the valid names for this course.
      // In other words, all the non-excluded names.  For most
      // courses, this will just be one name.  But for cross-listed
      // courses, this will be 2 or more (probably just 2 though).
      // Example: MATH 373 and CSCI 373 are both valid names for that course.
      $res = $this->db_query("SELECT * FROM courses
                    WHERE course_id = '?'
                    AND exclude = 0
                    AND delete_flag = 0 ", $course_id);
      while($cur = $this->db_fetch_array($res))
      {
        $si = $cur["subject_id"];
        $cn = $cur["course_num"];
        if (in_array("$si~$cn", $array_valid_names))
        {
          continue;
        }
        $array_valid_names[] = "$si~$cn";
      }
      
      
    }
    
    
    
    
    
    if ($description == "")
    {
      $description = "There is no course description available at this time.";
    }
    
    if ($title == "")
    {
      $title = "$subject_id $course_num";
    }
    
    
    // Now, to reduce the number of database calls in the future, save this
    // to our GLOBALS cache...
    
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["subject_id"] = $subject_id;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["course_num"] = $course_num;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["title"] = $title;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["description"] = $description;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["min_hours"] = $min_hours;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["max_hours"] = $max_hours;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["repeat_hours"] = $repeat_hours;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["db_exclude"] = $db_exclude;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["school_id"] = $db_school_id;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["array_valid_names"] = $array_valid_names;
    
    $cache_catalog_year = 0;
    
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["subject_id"] = $subject_id;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["course_num"] = $course_num;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["title"] = $title;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["description"] = $description;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["min_hours"] = $min_hours;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["max_hours"] = $max_hours;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["repeat_hours"] = $repeat_hours;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["db_exclude"] = $db_exclude;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["school_id"] = $db_school_id;
    $GLOBALS["fp_course_inventory"][$course_id][$cache_catalog_year]["array_valid_names"] = $array_valid_names;
    
    
    $GLOBALS["cache_course_inventory"] = true;  //  rebuild this cache before it closes.
    
    
    // Should we put all this into our course object?
    
    
    
    
    
    
    
  }
  
  
  
  /**
   Note that the $course object need not be a Course object, but rather a simple stdClass() with certain properties set.
   Specifically, the values we see being input into draft_courses in this function.
   */
  function duplicate_course_for_year($course = NULL, $catalog_year = 0, $bool_delete_existing = TRUE)
  {
    // Duplicate the course for the given catalog_year.
    // If it already exists for that catalog_year, delete it from the
    // table.
    // In other words, copy all course data from some valid year into this
    // new year.
    
    $c = $course;
    $course_id = $c->course_id;
    
    
    
    $min_hours = $c->min_hours;
    $max_hours = $c->max_hours;
    
    if (@$c->bool_ghost_min_hour) {
      $min_hours = 0;
    }
    
    if (@$c->bool_ghost_hour) {
      $max_hours = 0;
    }
    
    
    if ($bool_delete_existing) {
      $res = $this->db_query("DELETE FROM draft_courses
                              WHERE
                              course_id = ?
                              AND catalog_year = ?
                              AND subject_id = ?
                              AND course_num = ?
                              AND school_id = ? ", $course_id, $catalog_year, $c->subject_id, $c->course_num, $c->school_id);
    }
    
    $res2 = $this->db_query("INSERT INTO draft_courses(course_id,
                subject_id, course_num, catalog_year,
                title, description, min_hours, max_hours,
                repeat_hours, exclude, school_id) values (
                ?,?,?,?,?,?,?,?,?,?,?)
                ", $course_id, $c->subject_id,$c->course_num,$catalog_year,$c->title,$c->description,$min_hours,$max_hours,
        $c->repeat_hours,$c->db_exclude,$c->school_id);
    
    
    
  }
  
  function update_course_requirement_from_name($subject_id, $course_num, $new_course_id, $school_id = 0)
  {
    // This will convert all instances of subject_id/course_num
    // to use the newCourseID.  It looks through the requirements tables
    // that may have listed it as a requirement.  We will
    // look specifically at the data_entry_value to do some of them.
    
    // ************  IMPORTANT ****************
    // This is used only by dataentry.  It is intentionally
    // not doing the draft tables!
    
    $res = $this->db_query("UPDATE degree_requirements
                set `course_id`= ?
                where `data_entry_value`= ?
                 ", $new_course_id, "$subject_id~$course_num") ;
    
    $res = $this->db_query("UPDATE group_requirements
                SET `course_id`='?'
                WHERE `data_entry_value`= ?
                ", $new_course_id, "$subject_id~$course_num") ;
    
    
    
    // Also update substitutions....
    $res = $this->db_query("UPDATE student_substitutions
                SET `sub_course_id`='?'
                WHERE `sub_entry_value`= ?
        
                 ", $new_course_id, "$subject_id~$course_num") ;
    
    $res = $this->db_query("UPDATE student_substitutions
                SET `required_course_id`='?'
                WHERE `required_entry_value`= ?
                ", $new_course_id, "$subject_id~$course_num") ;
    
    // Also the advising histories....
    $res = $this->db_query("UPDATE advised_courses
                SET `course_id`='?'
                WHERE `entry_value`= ?
                ", $new_course_id, "$subject_id~$course_num") ;
    
    
    
    
  }
  
  function add_draft_instruction($text)
  {
    // Adds a new "instruction" to the draft_instructions table.
    // Simple insert.
    $res = $this->db_query("INSERT INTO draft_instructions
                (instruction) VALUES ('?') ", $text);
  }
  
  
  function update_course_id($from_course_id, $to_course_id, $bool_draft = false)
  {
    // This will convert *all* instances of "fromCourseID"
    // across every table that it is used, to toCourseID.
    // Use this function when you want to change a course's
    // course_id in the database.
    
    $table_array = array("advised_courses",
      "courses",
      "degree_requirements",
      "group_requirements",
      "student_unassign_group");
    
    if ($bool_draft)
    { // only do the draft tables...
      $table_array = array(
        "draft_courses",
        "draft_degree_requirements",
        "draft_group_requirements",
      );
    }
    
    
    // Do the tables where it's named "course_id"...
    foreach($table_array as $table_name)
    {
      
      $res = $this->db_query("UPDATE $table_name
                SET course_id = '?'
                WHERE course_id = '?' ", $to_course_id, $from_course_id);
    }
    
    
    $res = $this->db_query("update student_substitutions
            set `required_course_id`='?'
            where `required_course_id`='?' ", $to_course_id, $from_course_id);
    
    $res = $this->db_query("update student_substitutions
            set `sub_course_id`='?'
            where `sub_course_id`='?'
               and `sub_transfer_flag`='0' ", $to_course_id, $from_course_id);
    
    $res = $this->db_query("update transfer_eqv_per_student
            set `local_course_id`='?'
            where `local_course_id`='?' ", $to_course_id, $from_course_id);
    
    
    
  }
  
  
  
  /**
   * Given an advising_session_id, create a duplicate of it as a new session_id (and return the new session_id).
   *
   * All the values can be left blank to mean "keep what is in there".  If they have values supplied in the arguments to this function,
   * then the new values will be used.
   */
  function duplicate_advising_session($advising_session_id, $faculty_id = "", $student_id = "", $term_id = "", $degree_id = "", $is_whatif = "", $is_draft = "") {
    $now = time();
    
    // First, get the details of this particular advising session....
    $res = db_query("SELECT * FROM advising_sessions WHERE advising_session_id = ?", $advising_session_id);
    $cur = db_fetch_array($res);
    
    // Get our values....
    $db_student_id = ($student_id == "") ? $cur["student_id"] : $student_id;
    $db_faculty_id = ($faculty_id == "") ? $cur["faculty_id"] : $faculty_id;
    $db_term_id = ($term_id == "") ? $cur["term_id"] : $term_id;
    $db_degree_id = ($degree_id == "") ? $cur["degree_id"] : $degree_id;
    $db_major_code_csv = $cur["major_code_csv"];
    $db_catalog_year = $cur["catalog_year"];
    $db_posted = $now;
    $db_is_whatif = ($is_whatif == "") ? $cur["is_whatif"] : $is_whatif;
    $db_is_draft = ($is_draft == "") ? $cur["is_draft"] : $is_draft;
    $db_is_empty = $cur["is_empty"];
    $db_delete_flag = $cur['delete_flag'];
    
    // Okay, let's INSERT this record, and capture the new advising_session_id...
    $res = db_query("INSERT INTO advising_sessions
              (student_id, faculty_id, term_id, degree_id, major_code_csv, catalog_year, posted, is_whatif, is_draft, is_empty, delete_flag)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
              ", $db_student_id, $db_faculty_id, $db_term_id, $db_degree_id, $db_major_code_csv, $db_catalog_year, $db_posted, $db_is_whatif, $db_is_draft, $db_is_empty, $db_delete_flag);
    
    $new_asid = db_insert_id();
    
    // Okay, now pull out the advised_courses, and insert again under the new_asid...
    $res = db_query("SELECT * FROM advised_courses WHERE advising_session_id = ?", $advising_session_id);
    while ($cur = db_fetch_array($res)) {
      
      db_query("INSERT INTO advised_courses (advising_session_id, course_id, entry_value, semester_num, group_id, var_hours, term_id, degree_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)", $new_asid, $cur["course_id"], $cur["entry_value"], $cur["semester_num"], $cur["group_id"], $cur["var_hours"], $cur["term_id"], $cur["degree_id"]);
      
    }
    
    // Finished!
    return $new_asid;
    
    
  }
  
  
  function get_advising_session_id($faculty_id = "", $student_id = "", $term_id = "", $degree_id = "", $bool_what_if = false, $bool_draft = true, $bool_load_any_active_if_faculty_id_not_found = TRUE)
  {
    $is_what_if = "0";
    $is_draft = "0";
    $draft_line = " and `is_draft`='0' ";
    $faculty_line = " and `faculty_id`='$faculty_id' ";
    
    $advising_session_id = 0;  // init
    
    if ($faculty_id == 0 || $faculty_id == "")
    { // If no faculty is specified, just get the first one to come up.
      $faculty_line = "";
    }
    
    if ($bool_what_if == true){$is_what_if = "1";}
    if ($bool_draft == true)
    {
      $is_draft = "1";
      $draft_line = "";
      // If we are told to pull up draft, we can safely
      // assume we just want the most recent save, whether it
      // is saved as a draft or not.
    }
    
    
    
    $query = "select * from advising_sessions
                where
                    student_id = ?
                $faculty_line
                and term_id = ?
                and degree_id = ?
                and is_whatif = ?
                AND delete_flag = 0
                $draft_line
                order by `posted` desc limit 1";
                $result = $this->db_query($query, array($student_id, $term_id, $degree_id, $is_what_if)) ;
                if ($this->db_num_rows($result) > 0)
                {
                  $cur = $this->db_fetch_array($result);
                  $advising_session_id = $cur["advising_session_id"];
                  return $advising_session_id;
                }
                
                
                if (intval($advising_session_id) < 1 && $bool_load_any_active_if_faculty_id_not_found) {
                  // Meaning, we couldn't find a record for the supplied faculty_id.  Let's just load the most recent active one, regardless
                  // of faculty_id.  Meaning, we need to make sure that is_draft = 0
                  $query = "select * from advising_sessions
                  where
                      student_id = ?
                  and term_id = ?
                  and degree_id = ?
                  and is_whatif = ?
                  and is_draft = 0
                  AND delete_flag = 0
                  order by `posted` desc limit 1";
                  $result = $this->db_query($query, array($student_id, $term_id, $degree_id, $is_what_if)) ;
                  if ($this->db_num_rows($result) > 0) {
                    $cur = $this->db_fetch_array($result);
                    $advising_session_id = $cur["advising_session_id"];
                    return $advising_session_id;
                  }
                  
                }
                
                
                
                
                return 0;
                
  }
  
  
  /**
   * Returns the group_id for the given group name, or FALSE
   */
  function get_group_name($group_id) {
    $temp = explode("_", $group_id);
    $group_id = fp_trim(@$temp[0]);
    
    
    // If it's already in our static cache, just return that.
    static $group_name_cache = array();
    if (isset($group_name_cache[$group_id])) {
      return $group_name_cache[$group_id];
    }
    
    
    
    
    
    $res7 = $this->db_query("SELECT group_name FROM `groups`
              WHERE group_id = ?
              AND delete_flag = 0
               LIMIT 1 ", $group_id) ;
    if ($this->db_num_rows($res7) > 0)
    {
      $cur7 = $this->db_fetch_array($res7);
      
      // Save to our cache before returning.
      $group_name_cache[$group_id] = $cur7['group_name'];
      return $cur7['group_name'];
    }
    return FALSE;
    
    
  }
  
  
  
  function get_group_id($group_name, $catalog_year, $school_id = 0) {
    
    if ($catalog_year < variable_get_for_school("earliest_catalog_year", 2006, $school_id))
    {
      $catalog_year = variable_get_for_school("earliest_catalog_year", 2006, $school_id);
    }
    
    
    // If it's already in our static cache, just return that.
    static $group_id_cache = array();
    if (isset($group_id_cache[$group_name][$school_id][$catalog_year])) {
      return $group_id_cache[$group_name][$school_id][$catalog_year];
    }
    
    
    
    $res7 = $this->db_query("SELECT group_id FROM `groups`
              WHERE group_name = ?
              AND catalog_year = ?
              AND school_id = ?
              AND delete_flag = 0
               LIMIT 1 ", $group_name, $catalog_year, $school_id) ;
    if ($this->db_num_rows($res7) > 0)
    {
      $cur7 = $this->db_fetch_array($res7);
      
      // Save to our cache
      $group_id_cache[$group_name][$school_id][$catalog_year] = $cur7['group_id'];
      return $cur7['group_id'];
    }
    return false;
  }
  
  
  
  function request_new_degree_id()
  {
    // Return a valid new id...
    
    for ($t = 0; $t < 1000; $t++)
    {
      $id = mt_rand(1, 2147483640);  // A few less than the max for a signed int in mysql.
      // Check for collisions...
      $res4 = $this->db_query("SELECT * FROM draft_degrees
              WHERE `degree_id`='?' limit 1", $id);
      if ($this->db_num_rows($res4) == 0)
      { // Was not in the table already, so use it!
        return $id;
      }
    }
    
    return false;
    
  }
  
  
  function get_institution_name($institution_id, $school_id = 0)
  {
    // Return the name of the institution...
    
    $res = $this->db_query("SELECT * FROM transfer_institutions
                where institution_id = ?
                AND school_id = ?", $institution_id, $school_id);
    $cur = $this->db_fetch_array($res);
    if ($cur) {
      return fp_trim(@$cur['name']);
    }
    
    return ''; // nothing found, so return blank
    
  }
  
  
  
  /**
   * Retrieve a value from the variables table.
   *
   * @param string $name
   */
  function get_variable($name, $default_value = "") {
    $res = $this->db_query("SELECT value FROM variables
                           WHERE name = ? ", $name);
    $cur = $this->db_fetch_array($res);
    
    $val = $cur["value"];
    if ($val == "") {
      $val = $default_value;
    }
    
    return $val;
  }
  
  
  /**
   * Sets a variable's value in the variables table.
   *
   * @param unknown_type $name
   * @param unknown_type $value
   */
  function set_variable($name, $value) {
    
    $res2 = $this->db_query("REPLACE INTO variables (name, value)
                              VALUES (?, ?) ", $name, $value);
    
  }
  
  
  
  
  function get_school_id_for_transfer_course_id($transfer_course_id) {
    
    return intval(db_result(db_query("SELECT school_id FROM transfer_courses WHERE transfer_course_id = ?", array($transfer_course_id))));
  }
  
  
  function get_school_id_for_user_id($user_id) {
    return intval(db_result(db_query("SELECT school_id FROM users WHERE user_id = ?", array($user_id))));
  }
  
  function get_school_id_for_student_id($cwid) {
    
    // Save to cache for quick lookup
    if (isset($GLOBALS['cache_school_id_for_student_id'][$cwid])) {
      return $GLOBALS['cache_school_id_for_student_id'][$cwid];
    }
    
    $rtn = intval(db_result(db_query("SELECT school_id FROM users WHERE cwid = ? AND is_student = 1", array($cwid))));
    $GLOBALS['cache_school_id_for_student_id'][$cwid] = $rtn;
    
    return $rtn;
  }
  
  
  function get_school_id_for_faculty_id($cwid) {
    return intval(db_result(db_query("SELECT school_id FROM users WHERE cwid = ? AND is_faculty = 1", array($cwid))));
  }
  
  
  
  function get_school_id_for_course_id($course_id, $bool_use_draft = FALSE) {
    
    // Always override if the global variable is set.
    if (@$GLOBALS["fp_advising"]["bool_use_draft"] == true) {
      $bool_use_draft = true;
    }
    
    $table_name = "courses";
    if ($bool_use_draft){$table_name = "draft_$table_name";}
    
    
    // Use GLOBALS cache to make this faster.
    if (isset($GLOBALS['fp_school_id_for_course_id'][$table_name][$course_id])) {
      return $GLOBALS['fp_school_id_for_course_id'][$table_name][$course_id];
    }
    
    $val = intval(db_result(db_query("SELECT school_id FROM $table_name WHERE course_id = ?", array($course_id))));
    
    $GLOBALS['fp_school_id_for_course_id'][$table_name][$course_id] = $val;
    
    return $val;
    
  }
  
  
  function get_school_id_for_degree_id($degree_id, $bool_use_draft = FALSE) {
    
    // Always override if the global variable is set.
    if (@$GLOBALS["fp_advising"]["bool_use_draft"] == true) {
      $bool_use_draft = true;
    }
    
    $table_name = "degrees";
    if ($bool_use_draft){$table_name = "draft_$table_name";}
    
    return intval(db_result(db_query("SELECT school_id FROM $table_name WHERE degree_id = ?", array($degree_id))));
  }
  
  
  function get_school_id_for_group_id($group_id, $bool_use_draft = FALSE) {
    
    // Always override if the global variable is set.
    if (@$GLOBALS["fp_advising"]["bool_use_draft"] == true) {
      $bool_use_draft = true;
    }
    
    $table_name = "groups";
    if ($bool_use_draft){$table_name = "draft_$table_name";}
    
    return intval(db_result(db_query("SELECT school_id FROM $table_name WHERE group_id = ? ", array($group_id))));
  }
  
  
  /**
   * Returns an object from db query for a row we find with matching course_id, from the most recent catalog year.
   */
  function get_course_db_row($course_id) {
    $res = db_query("SELECT * FROM courses
                     WHERE course_id = ?
                     AND exclude != 1
                     AND delete_flag != 1
                     ORDER BY `catalog_year` DESC", array($course_id));
    $cur = db_fetch_object($res);
    
    if (!$cur) {
      // Couldn't find it, so lift the exclude requirement.
      
      $res = db_query("SELECT * FROM courses
                     WHERE course_id = ?
                     AND delete_flag != 1
                     ORDER BY `catalog_year` DESC", array($course_id));
      $cur = db_fetch_object($res);
    }
    
    
    
    return $cur;
  }
  
  
  function get_course_id($subject_id, $course_num, $catalog_year = "", $bool_use_draft = FALSE, $school_id = 0, $bool_check_allow_default_school = FALSE)
  {
    
    // If we were not sent a valid course name, return FALSE right away.
    if (!$subject_id && !$course_num) return FALSE;
    
    if (!$course_num) $course_num = ''; // Make sure it isn't FALSE or NULL
    
    // Ignore the colon, if there is one.
    if (strpos($course_num, ":") !== FALSE)
    {
      //$course_num = substr($course_num,0,-2);
      $temp = explode(":", $course_num);
      $course_num = trim($temp[0]);
    }
    
    $params = array();
    
    $school_line = " AND school_id = :school_id ";
    
    // Should we ALSO check the default school, in addition to whatever we specified?  Don't bother if what we specified was the default school.
    if ($bool_check_allow_default_school && module_enabled('schools') && variable_get('schools_allow_courses_from_default_school', 'yes') === 'yes' && $school_id != 0) {
      $school_line = " AND (school_id = :school_id OR school_id = 0) ";
    }
    
    
    // Always override if the global variable is set.
    if (@$GLOBALS["fp_advising"]["bool_use_draft"] == true) {
      $bool_use_draft = true;
    }
    
    
    
    
    $catalog_line = "";
    
    if ($catalog_year != "")
    {
      $catalog_year = intval($catalog_year);
      $catalog_line = "and catalog_year = '$catalog_year' ";
    }
    
    $table_name = "courses";
    if ($bool_use_draft){$table_name = "draft_$table_name";}
    
    $params[':subject_id'] = $subject_id;
    $params[':course_num'] = $course_num;
    $params[':school_id'] = intval($school_id);
    
    
    $res7 = $this->db_query("SELECT course_id FROM $table_name
              WHERE subject_id = :subject_id
              AND course_num = :course_num
              $school_line
              $catalog_line
               ORDER BY catalog_year DESC LIMIT 1 ", $params) ;
              if ($this->db_num_rows($res7) > 0)
              {
                $cur7 = $this->db_fetch_array($res7);
                return intval($cur7["course_id"]);
              }
              return FALSE;
  }
  
  
  function get_student_settings($student_cwid)
  {
    
    // This returns an array (from the xml) of a student's
    // settings in the student_settings table.  It will
    // return FALSE if the student was not in the table.
    
    $res = $this->db_query("SELECT settings FROM student_settings
              WHERE student_id = ?
               ", $student_cwid) ;
    if ($this->db_num_rows($res) < 1)
    {
      return false;
    }
    
    $cur = $this->db_fetch_array($res);
    
    if (!$rtn = unserialize($cur["settings"])) {
      $rtn = array();
    }
    
    return $rtn;
    
  }
  
  
  function get_student_cumulative_hours($student_cwid) {
    
    // Let's perform our queries.
    $res = $this->db_query("SELECT cumulative_hours FROM students
                      WHERE cwid = ?
                       ", $student_cwid);
    
    
    $cur = $this->db_fetch_array($res);
    return $cur["cumulative_hours"];
    
  }
  
  
  function get_student_gpa($student_cwid) {
    
    // Let's perform our queries.
    $res = $this->db_query("SELECT gpa FROM students
                      WHERE cwid = ?
                       ", $student_cwid);
    
    
    $cur = $this->db_fetch_array($res);
    return $cur["gpa"];
    
  }
  
  
  
  function get_student_catalog_year($student_cwid) {
    
    
    if (isset($GLOBALS['db_get_student_catalog_year'][$student_cwid])) {
      return $GLOBALS['db_get_student_catalog_year'][$student_cwid];
    }
    
    
    $catalog = 0;
    
    // Let's perform our queries.
    $res = $this->db_query("SELECT catalog_year FROM students
                      WHERE cwid = ?
                       ", $student_cwid);
    
    
    $cur = $this->db_fetch_array($res);
    if ($cur) {
      $catalog = intval($cur["catalog_year"]);
    }
    
    $GLOBALS['db_get_student_catalog_year'][$student_cwid] = $catalog;
    
    
    return $catalog;
  }
  
  
  /**
   * Returns whatever is in the Rank field for this student.
   * Ex: JR, SR, FR, etc.
   *
   * @param unknown_type $student_id
   * @return unknown
   */
  function get_student_rank($student_cwid) {
    
    
    // Let's perform our queries.
    $res = $this->db_query("SELECT rank_code FROM students
                      WHERE cwid = ?
                       ", $student_cwid);
    
    
    $cur = $this->db_fetch_array($res);
    $rank = $cur["rank_code"];
    
    return trim($rank);
  }
  
  
  
  
  
  /**
   * Returns the student's first and last name, put together.
   * Ex: John Smith.
   *
   * @param int $student_id
   * @return string
   */
  function get_student_name($cwid, $bool_include_cwid = FALSE) {
    
    // Let's perform our queries.
    $res = $this->db_query("SELECT f_name, l_name FROM users
                      WHERE cwid = ?
                      AND is_student = 1 ", $cwid);
    
    $cur = $this->db_fetch_array($res);
    if ($cur) {
      $name = $cur["f_name"] . " " . $cur["l_name"];
    }
    else {
      $name = t("Unknown Student");
    }
    
    // Force into pretty capitalization.
    // turns JOHN SMITH into John Smith
    $name = trim(ucwords(strtolower($name)));
    
    if ($bool_include_cwid) {
      $name .= " ($cwid)";
    }
    
    
    return $name;
  }
  
  
  
  /**
   * Returns the faculty's first and last name, put together.
   * Ex: John Smith or John W Smith.
   *
   * @param int $faculty_id
   * @return string
   */
  function get_faculty_name($cwid, $bool_include_cwid = FALSE) {
    
    $name = '';
    // Let's perform our queries.
    $res = $this->db_query("SELECT f_name, l_name FROM users
                      WHERE cwid = ?
                      AND is_faculty = 1 ", $cwid);
    
    
    $cur = $this->db_fetch_array($res);
    if ($cur) {
      $name = $cur["f_name"] . " " . $cur["l_name"];
      
      
      // Force into pretty capitalization.
      // turns JOHN SMITH into John Smith
      $name = trim(ucwords(strtolower($name)));
      
      if ($bool_include_cwid) {
        $name .= " ($cwid)";
      }
    }
    
    return $name;
  }
  
  
  
  
  
  
  /**
   * Looks in our extra tables to find out what major code, if any, has been assigned
   * to this faculty member.
   *
   */
  function get_faculty_major_code_csv($faculty_cwid) {
    
    // Let's pull the needed variables out of our settings, so we know what
    // to query, because this is a non-FlightPath table.
    
    $res = $this->db_query("SELECT major_code_csv FROM faculty WHERE cwid = ? ", $faculty_cwid);
    $cur = $this->db_fetch_array($res);
    
    return @$cur["major_code_csv"];
    
  }
  
  
  /**
   * Returns an array (or CSV string) of major_codes from the student_degrees table for this student.
   *
   * If bool_check_for_allow_dynaic is TRUE, it means that, if the student has more than one degree returned, we will make sure that they all
   * have allow_dynamic = TRUE.  If they do not, we will use the first is_editable degree we find ONLY.  We do this because that means the student
   * had a situation like we see in FlightPath 4x, where only one degree may be selected at a time, and the is_editiable degree is the track/option they
   * selected.
   *
   *
   */
  function get_student_majors_from_db($student_cwid, $bool_return_as_full_record = FALSE, $perform_join_with_degrees = TRUE, $bool_skip_directives = TRUE, $bool_check_for_allow_dynamic = TRUE) {
    // Looks in the student_degrees table and returns an array of major codes.
    $rtn = array();
    
    // Keep track of degrees which have is_editable set to 1.
    $is_editable_true = array();
    $is_editable_false = array();
    
    if ($perform_join_with_degrees) {
      
      $catalog_year = $this->get_student_catalog_year($student_cwid);
      
      $res = $this->db_query("SELECT * FROM student_degrees a, degrees b
                              WHERE student_id = ?
                              AND a.major_code = b.major_code
                              AND b.catalog_year = ?
                              ORDER BY b.advising_weight, b.major_code
                              ", $student_cwid, $catalog_year);
    }
    else {
      // No need to join with degrees table...
      $res = $this->db_query("SELECT * FROM student_degrees a
                              WHERE student_id = ?
                              ORDER BY major_code
                              ", $student_cwid);
    }
    while ($cur = $this->db_fetch_array($res)) {
      
      if ($bool_skip_directives && strstr($cur["major_code"], "~")) continue;
      
      if ($bool_return_as_full_record) {
        $rtn[$cur["major_code"]] = $cur;
        
      }
      else {
        $rtn[$cur["major_code"]] = $cur["major_code"];
      }
      
      if ($bool_check_for_allow_dynamic && !isset($cur['allow_dynamic']) && isset($cur['degree_id'])) {
        $cur['allow_dynamic'] = $this->get_degree_allow_dynamic($cur['degree_id']);
      }
      
      
      if ($cur['is_editable'] == 1) {
        $is_editable_true[] = $cur;
      }
      else {
        $is_editable_false[] = $cur;
      }
      
    }
    
    if ($bool_check_for_allow_dynamic && count($rtn) > 1) {
      
      // This means that we have more than one degree selected, and we have been asked to make sure that if any of the degrees have allow_dynamic = 0, then we will
      // only select the is_editable degree.
      
      foreach ($is_editable_false as $major) {
        if (isset($major['allow_dynamic']) && $major['allow_dynamic'] == 0) {
          // Meaning, allow dynamic is NOT allowed.  So, if we have ANYTHING in is_editable_true, then use THAT, else, use THIS.
          if (count($is_editable_true) > 0) {
            // Only get out 1 major.
            $x = $is_editable_true[0];
            $new_rtn[$x['major_code']] = $rtn[$x['major_code']];
            $rtn = $new_rtn;
          }
          else {
            $x = $major;
            $new_rtn[$x['major_code']] = $rtn[$x['major_code']];
            $rtn = $new_rtn;
          }
        }
      }
      
    } // if bool_check_for_allow_dynamic
    
    
    
    
    return $rtn;
  }
  
  
  function get_flightpath_settings()
  {
    // Returns an array of everything in the flightpath_settings table.
    $rtn_array = array();
    $res = $this->db_query("SELECT * FROM flightpath_settings ") ;
    while($cur = $this->db_fetch_array($res))
    {
      $rtn_array[$cur["variable_name"]] = trim($cur["value"]);
    }
    
    return $rtn_array;
    
  }
  
  function get_degrees_in_catalog_year($catalog_year, $bool_include_tracks = false, $bool_use_draft = false, $bool_undergrad_only = TRUE, $only_level_nums = array(1,2), $school_id = 0)
  {
    // Returns an array of all the degrees from a particular year
    // which are entered into FlightPath.
    
    $undergrad_line = $degree_class_line = "";
    
    $table_name = "degrees";
    if ($bool_use_draft){$table_name = "draft_$table_name";}
    
    // change this to be whatever the graduate code actually is.
    if ($bool_undergrad_only) $undergrad_line = " AND degree_level <> 'GR' ";
    
    $degree_class_line = "";
    if (count($only_level_nums) > 0) {
      $classes = fp_get_degree_classifications();
      foreach ($only_level_nums as $num) {
        foreach ($classes["levels"][$num] as $machine_name => $val) {
          $degree_class_line .= " degree_class = '" . addslashes($machine_name) . "' OR";
        }
      }
      // Remove training "OR" from degree_class_line
      $degree_class_line = substr($degree_class_line, 0, strlen($degree_class_line) - 2);
    }
    
    if ($degree_class_line != "") {
      $degree_class_line = "AND ($degree_class_line)";
    }
    
    $rtn_array = array();
    $res = $this->db_query("SELECT `id`, degree_id, major_code, title, degree_class , school_id, catalog_year
                            FROM $table_name
                            WHERE exclude = '0'
                              AND catalog_year = ?
                              AND school_id = ?
                              $undergrad_line
                              $degree_class_line
                            ORDER BY title, major_code ", $catalog_year, $school_id);
                              if ($this->db_num_rows($res) < 1)
                              {
                                return false;
                              }
                              
                              while ($cur = $this->db_fetch_array($res))
                              {
                                $degree_id = $cur["degree_id"];
                                $major = trim($cur["major_code"]);
                                $title = trim($cur["title"]);
                                $track_code = "";
                                $major_code = $major;
                                
                                // The major may have a track specified.  If so, take out
                                // the track and make it seperate.
                                if (strstr($major, "_"))
                                {
                                  $temp = explode("_", $major);
                                  $major_code = trim($temp[0]);
                                  $track_code = trim($temp[1]);
                                  // The major_code might now have a | at the very end.  If so,
                                  // get rid of it.
                                  if (substr($major_code, strlen($major_code)-1, 1) == "|")
                                  {
                                    $major_code = str_replace("|","",$major_code);
                                  }
                                  
                                  
                                }
                                
                                // Leave the track in if requested.
                                if ($bool_include_tracks == true)
                                {
                                  // Set it back to what we got from the db.
                                  $major_code = $major;
                                  $temp_degree = $this->get_degree_plan($major, $catalog_year, true);
                                  if ($temp_degree->track_code != "")
                                  {
                                    $title .= " - " . $temp_degree->track_title;
                                  }
                                }
                                
                                $rtn_array[$major_code]["title"] = $title;
                                $rtn_array[$major_code]["degree_id"] = $degree_id;
                                $rtn_array[$major_code]["degree_class"] = trim(strtoupper($cur["degree_class"]));
                                $rtn_array[$major_code]["school_id"] = intval($cur['school_id']);
                                $rtn_array[$major_code]["catalog_year"] = $cur['catalog_year'];
                                $rtn_array[$major_code]["db_id"] = $cur['id'];
                                
                              }
                              
                              return $rtn_array;
                              
  }
  
  function get_degree_tracks($major_code, $catalog_year, $school_id = 0)
  {
    // Will return an array of all the tracks that a particular major
    // has.  Must match the major_code in degree_tracks table.
    // Returns FALSE if there are none.
    $rtn_array = array();
    
    static $degree_tracks_data_cache = array();
    if (isset($degree_tracks_data_cache[$catalog_year][$major_code])) {
      return $degree_tracks_data_cache[$catalog_year][$major_code];
    }
    
    $res = $this->db_query("SELECT * FROM degree_tracks
                WHERE major_code = ?
                AND catalog_year = ?
                AND school_id = ?", $major_code, $catalog_year, $school_id);
    if ($this->db_num_rows($res) < 1)
    {
      $degree_tracks_data_cache[$catalog_year][$major_code] = false;
      return FALSE;
    }
    
    while($cur = $this->db_fetch_array($res))
    {
      extract($cur, 3, "db");
      $rtn_array[] = $db_track_code;
    }
    
    $degree_tracks_data_cache[$catalog_year][$major_code] = $rtn_array;
    return $rtn_array;
    
  }
  
  function get_degree_plan($major_and_track_code, $catalog_year = "", $bool_minimal = false, $school_id = 0)
  {
    // Returns a degreePlan object from the supplied information.
    
    // If catalog_year is blank, use whatever the current catalog year is, loaded from our settings table.
    if ($catalog_year == "") {
      $catalog_year = variable_get_for_school("current_catalog_year", "2006", $school_id);
    }
    
    $degree_id = $this->get_degree_id(trim($major_and_track_code), $catalog_year, FALSE, $school_id);
    $dp = new DegreePlan($degree_id,null,$bool_minimal);
    if ($dp->major_code == "")
    {
      $dp->major_code = trim($major_and_track_code);
    }
    return $dp;
  }
  
  
  /**
   * Returns the value of a degree's allow_dynamic field in the database.
   *
   * Returns boolean FALSE if it cannot find the degree.
   *
   * @param unknown_type $degree_id
   * @param unknown_type $bool_use_draft
   */
  function get_degree_allow_dynamic($degree_id, $bool_use_draft = FALSE) {
    
    $table_name = "degrees";
    if ($bool_use_draft){$table_name = "draft_$table_name";}
    
    $res7 = $this->db_query("SELECT allow_dynamic FROM $table_name
              WHERE degree_id = ?
               ", $degree_id) ;
    if ($this->db_num_rows($res7) > 0)
    {
      $cur7 = $this->db_fetch_array($res7);
      return $cur7["allow_dynamic"];
    }
    return false;
    
    
    
  }
  
  
  function get_degree_id($major_and_track_code, $catalog_year, $bool_use_draft = FALSE, $school_id = 0)
  {
    // This function expects the major_code and track_code (if it exists)
    // to be joined using |_.  Example:
    // GSBA|_123  or  KIND|EXCP_231.
    // In other words, all in one.
    
    // Always override if the global variable is set.
    if (@$GLOBALS["fp_advising"]["bool_use_draft"] == true) {
      $bool_use_draft = true;
    }
    
    
    
    if ($catalog_year < variable_get_for_school("earliest_catalog_year", 2006, $school_id))
    { // Lowest possible year.
      $catalog_year = variable_get_for_school("earliest_catalog_year", 2006, $school_id);
    }
    
    $table_name = "degrees";
    if ($bool_use_draft){$table_name = "draft_$table_name";}
    
    $res7 = $this->db_query("SELECT degree_id FROM $table_name
              WHERE major_code = ?
              AND catalog_year = ?
              AND school_id = ?
               LIMIT 1 ", trim($major_and_track_code), $catalog_year, $school_id) ;
    if ($this->db_num_rows($res7) > 0)
    {
      $cur7 = $this->db_fetch_array($res7);
      return $cur7["degree_id"];
    }
    return false;
    
  }
  
  
  // Returns a simple array of all degree_id's which match this major code, any catalog year.
  function get_degree_ids($major_code, $school_id = 0) {
    
    $rtn = array();
    
    $bool_use_draft = FALSE;
    
    // Always override if the global variable is set.
    if (@$GLOBALS["fp_advising"]["bool_use_draft"] == true) {
      $bool_use_draft = true;
    }
    
    $table_name = "degrees";
    if ($bool_use_draft){$table_name = "draft_$table_name";}
    
    $res7 = $this->db_query("SELECT degree_id FROM $table_name
                            WHERE major_code = ?
                            AND school_id = ?
                            ", trim($major_code), $school_id) ;
    
    while ($cur7 = $this->db_fetch_array($res7)) {
      $rtn[$cur7["degree_id"]] = $cur7["degree_id"];
    }
    
    
    return $rtn;
    
    
  } // get_degree_ids
  
  
  
  function db_fetch_array($result) {
    if (!is_object($result)) return FALSE;
    
    return $result->fetch(PDO::FETCH_ASSOC);
  }
  
  function db_fetch_object($result) {
    if (!is_object($result)) return FALSE;
    
    return $result->fetch(PDO::FETCH_OBJ);
  }
  
  function db_num_rows($result) {
    if (!is_object($result)) return FALSE;
    
    return $result->rowCount();
  }
  
  function db_affected_rows($result) {
    
    return db_num_rows($result);
  }
  
  function db_insert_id() {
    //fpm($this->pdo->lastInsertId());
    //return $this->pdo->lastInsertId();
    return $_SESSION["fp_last_insert_id"];
  }
  
  function db_close() {
    return $this->pdo = NULL;  // this is all you need to do to close a PDO connection.
  }
  
  
  
  /////////////////////////////////////////////
  /////////////////////////////////////////////
  /////////////////////////////////////////////
  
  
  
}
