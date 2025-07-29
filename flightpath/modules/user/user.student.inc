<?php

/**
 * @file
 * Keep track of functions dealing specifically with student user management
*/




/**
 * Similar to user_display_users, except only for student users.
 */
function user_display_student_users() {
  global $db, $screen;
  $de_catalog_year = @$GLOBALS["de_catalog_year"];

  if (!$de_catalog_year) {
    $de_catalog_year = @intval($_REQUEST['de_catalog_year']);
  }  
  
  if ($db == NULL) {
    $db = get_global_database_handler();
  }
    
  $cc = 1;

  // Do this using $render array, so it can be altered
  // by hook_content_alter
  $render = array();
  $render['#id'] = 'user_display_users';  
  
  
  fp_add_css(fp_get_module_path("admin") . "/css/admin.css");
  fp_add_css(fp_get_module_path("user") . "/css/user.css");
  
  
  if (@$_REQUEST['clear_filters'] == 'true') {
    // Meaning, a letter was specified, so let's reset the filter values.
    unset($_SESSION['users_filter_name']);
    
    foreach ($_SESSION as $key => $val) {
      if ($key == 'users_filter_active') continue; // we preserve the "active" filter setting
      
      if (strstr($key,"users_filter_")) {
        unset($_SESSION[$key]);
      }
    }     
  }
  
  
      
  
  $html = "";

  $html .= "<div class='add-new-student-user'>" . l("<i class='fa fa-plus'></i> " . t("Create a new student user"), "admin/users/edit-student-user", "student_cwid=new&de_catalog_year=$de_catalog_year") . "</div>";
    

  $letter_ranges = array(
    "A" => array("A", "AZZZZ"),
    "B" => array("B", "BZZZZ"),
    "C" => array("C", "CZZZ"),
    "D" => array("D", "DZZZZ"),
    "E" => array("E", "EZZZZ"),
    "F" => array("F", "FZZZZ"),
    "G" => array("G", "GZZZZ"),
    "H" => array("H", "HZZZZ"),
    "I" => array("I", "IZZZ"),
    "J" => array("J", "JZZZ"),
    "K" => array("K", "KZZZ"),
    "L" => array("L", "LZZZ"),    
    "M" => array("M", "MZZZ"),
    "N" => array("N", "NZZZ"),
    "O" => array("O", "OZZZ"),    
    "P" => array("P", "PZZZ"),
    "Q-R" => array("Q", "RZZZZ"),
    "S" => array("S", "SZZZ"),
    "T" => array("T", "TZZZZ"),
    "U" => array("U", "UZZZ"),
    "V-Z" => array("V", "ZZZZ"),        
  );



  
  
  $ur = fp_trim(@$_GET["ur"]);
  $lr = fp_trim(@$_GET["lr"]);

  if ($ur == "" && !isset($_SESSION['users_filter_submit'])) {
  //if ($ur == "") {
    $ur = "A";
    $lr = "AZZZZZ";  // first time through.
  }  
  
  // If we are going by filters, show that as option and select it.
  if (isset($_SESSION['users_filter_submit'])) {
    $letter_ranges[t(" - Filter -")] = array("filter", "filter");
  }  
  
  
  $html .= "<div class='user-select-letter-bar' style='padding-top: 20px;'>
          ";
  foreach($letter_ranges as $disp => $vals) {
    $selected_class = "";  
    if ($ur == $vals[0]) {
      $selected_class = "selected";
    }    
    
    if ($vals[0] == 'filter') {
      $selected_class = "selected";
      $html .= "<a href='javascript:void(0);' class='admin-courses-letter-link filter-indicator $selected_class'>$disp</a> &nbsp; ";  
      continue;
    }
    
    
    $html .= l($disp, "admin/users/students", "de_catalog_year=$de_catalog_year&clear_filters=true&ur=" . $vals[0] . "&lr=" . $vals[1], array("class" => "admin-courses-letter-link $selected_class")) . " &nbsp; ";
    
  }


  $html .= "</div>";
  
  $render['upper_links'] = array(
    'value' => $html,
  );






  if ($ur == "")
  { // meaning, no range was set.  Use A - C
    $ur = @$_SESSION["ur"];
    $lr = @$_SESSION["lr"];
    if ($ur == "")
    { // if still blank, assign it..
      $ur = "A";
      $lr = "AZZZZ";
    }

  }
  $_SESSION["ur"] = $ur;
  $_SESSION["lr"] = $lr;


   

  $mark = "";
  $mark .= "<div class='degrees-filter'>";
  $mark .= fp_render_form("user_student_list_filter_form");
  $mark .= "</div>";
  
  
  $render['users_filter'] = array(
    'value' => $mark,    
  );    


  
  
  $extra_where_conditions = "";
  $params = array();
  $params[":ur"] = $ur;
  $params[":lr"] = $lr;

  
  if (isset($_SESSION['users_filter_submit'])) {
    // We clicked to filter, so the letter range should be ignored.
    $params[":ur"] = "A";
    $params[":lr"] = "ZZZZZZZZZZZZ";
  }
 
 
  $filter_name = @trim($_SESSION['users_filter_name']);
  $filter_school = @intval($_SESSION['users_filter_school']);
  $filter_major = @trim($_SESSION['users_filter_major']);
  $filter_active = @trim($_SESSION['users_filter_active']);  




  if ($filter_name) {
    $extra_where_conditions .= " AND (l_name LIKE :search1 OR f_name LIKE :search2 OR u.cwid LIKE :search3) ";
    $params[":search1"] = "%$filter_name%";
    $params[":search2"] = "%$filter_name%";
    $params[":search3"] = "%$filter_name%";
    // If we are searching by name, then we do not care about what letters we selected.
    $params[":ur"] = "A";
    $params[":lr"] = "ZZZZZZZZZZZZZZ";
  }
  
  
  $extra_where_conditions .= " AND u.school_id = :school_id ";
  $params[":school_id"] = $filter_school;
  

  if ($filter_major != '') {    
    $extra_where_conditions .= " AND major_code = :major_code ";
    $params[":major_code"] = $filter_major;
  }
  
    
  if ($filter_active) {
    $vals = array();
    if ($filter_active == 'active') {
      $vals[] = 1;
    }
    if ($filter_active == 'inactive') {
      $vals[] = 0;
    }
    if ($filter_active == 'all') {
      $vals = array(1, 0);
    }
    $extra_where_conditions .= " AND is_active IN (" . join(",", $vals) . ") ";    
  }
    
  watchdog("user", "Viewed admin student user list. Range: $ur - $lr. Name: $filter_name. Major: $filter_major. Active: $filter_active. School: $filter_school", array(), WATCHDOG_DEBUG);
  
    
  $html = "";
  $html .= "
      <table border='0' width='100%' cellpadding='3' cellspacing='0' class='user-list'>
    
        <tr>
          <th width='5%'>Actions</th>
          <th>CWID</th>
          <th>Name</th>
          <th></th>
          <th>Major code(s)</th>
          <th>Active?</th>
          <th>Last Login</th>
          
        </tr>  
    ";
    
  $render['users_table_top'] = array(
    'value' => $html,
  );  

    
  $limit = 30;     
  $result = pager_query("SELECT * FROM (users u, students s)
                        LEFT JOIN student_degrees d ON (s.cwid = d.student_id)
                      WHERE 
                        u.is_student = 1
                        AND u.cwid = s.cwid
                        AND l_name BETWEEN :ur AND :lr
                        $extra_where_conditions                        
                        GROUP BY u.cwid
                        ORDER BY l_name, f_name
                            ", $params, $limit, 0, NULL, "SELECT COUNT(DISTINCT(u.cwid))");
  while ($cur = db_fetch_array($result)) {

    $user_id = $cur["user_id"];
    $l_name = trim(ucwords(strtolower($cur["l_name"])));
    $f_name = trim(ucwords(strtolower($cur["f_name"])));    
    $student_cwid = trim($cur["cwid"]);
    
    //$disp_major_code = trim($cur["major_code"]);
    $disp_major_code = join(", ", $db->get_student_majors_from_db($student_cwid, FALSE, FALSE));
      
    $is_active = ($cur["is_active"] == "1") ? "Y" : "N";
    
    $last_login = intval($cur['last_login']);
    if ($last_login == 0) {
      $last_login = t("Never");
    }
    else {
      $last_login = format_date(convert_time($last_login), 'short');
    }    
    
    

    
    
    $ast = "";
    $reason = "";


    $fgcol = "black";


    $render['user_row_' . $student_cwid] = array(
      'value' => "<tr class='is-active-$is_active'>          
                    <td valign='top'>" . l("<i class='fa fa-pencil' title='Edit'></i>", "admin/users/edit-student-user", "student_cwid=$student_cwid&de_catalog_year=$de_catalog_year") . "</td>              
                    <td valign='top' width='15%'>$student_cwid</td>
                    <td valign='top' width='15%'>$f_name</td>
                    <td valign='top' width='15%'>$l_name</td>
                    <td valign='top'>$disp_major_code</td>
                    <td valign='top'>$is_active</td>
                    <td valign='top'>$last_login</td>                    
                  </tr>",     

           'data' => array(
                    'is_student' => 1,
                    'cwid' => $student_cwid,
                    'db_row' => $cur,                    
                  ),                
                
    );


  } // while
  
  
  $render['users_table_bottom'] = array(
    'value' => "</table>",
  );


  $render['users_pager'] = array(
    'value' => theme_pager(),
  );


  $rtn = fp_render_content($render);
  
  
  return $rtn;
  
}
 



function user_student_list_filter_form() {

  $form = array();

  
  $form['mark_top'] = array(
    'type' => 'markup',
    'value' => "<strong>" . t('Filter by...') . "</strong>",
    'weight' => 0,
  );
  
   
  $form['de_catalog_year'] = array(
    'type' => 'hidden',
    'value' => @$_REQUEST['de_catalog_year'],
  );
  
  
  
  $filter_name_value = @trim($_SESSION['users_filter_name']);
  $form['filter_name'] = array(
    'type' => 'textfield',
    'label' => '',
    'attributes' => array("placeholder" => t("Name or CWID")),    
    'value' => $filter_name_value,
    'size' => 20,
    'weight' => 10,
  );
  
  

  // Show list of majors (which are also in FlightPath, and in the selected school, if any)
  $options = array();
  $test_school_id = @intval($_SESSION['users_filter_school']);
  $test_school_options = array();
  if (module_enabled('schools')) {
    $test_school_options = schools_get_schools_for_fapi(TRUE, TRUE, 'user', TRUE);
  }

  $res = db_query("SELECT DISTINCT(b.major_code) FROM students a, student_degrees b, `degrees` c
                        WHERE a.cwid = b.student_id
                        AND b.major_code = c.major_code
                        AND school_id = ?
                        ORDER BY b.major_code ", array($test_school_id));
  while ($cur = db_fetch_array($res)) {
    if (trim($cur["major_code"]) == "")
    {// skip if blank
      continue;
    }    
    

    if (count($test_school_options) <= 1) {
      $options[$cur['major_code']] = trim(ucwords($cur["major_code"]));
    }
    else {
      $options[$test_school_options[$test_school_id]][$cur['major_code']] = trim(ucwords($cur["major_code"]));
    }
  }  
  
  $filter_major_value = @trim($_SESSION['users_filter_major']);
  $form['filter_major'] = array(      
    'type' => 'select',
    'label' => t('Major:'),
    'options' => $options,
    'value' => $filter_major_value,
    'weight' => 20,
  );      


  
  // Show list of active/inactive options
  $options = array();
  $options['active'] = t('Show Active');
  $options['inactive'] = t('Show Inactive');
  $options['all'] = t('Show All');
    
  $filter_active_value = @$_SESSION['users_filter_active'];
  $form['filter_active'] = array(      
    'type' => 'select',
    'label' => t('Active:'),
    'options' => $options,
    'hide_please_select' => TRUE,
    'value' => $filter_active_value,
    'weight' => 30,
  );      
  
  
  
  // If we have enabled the schools module, then have a selector for that as well
  if (module_enabled('schools')) {
  
    $filter_school_value = @intval($_SESSION['users_filter_school']);
    $options = schools_get_schools_for_fapi(TRUE, TRUE, 'user', TRUE);
    $options[0] = t('- Default -');
    $form['filter_school'] = array(      
      'type' => 'select',
      'label' => t('School:'),
      'options' => $options,
      'value' => $filter_school_value,
      'weight' => 40,   
      'hide_please_select' => TRUE,   
    );       
      
  } // if schools enabled  

  
  
  $form['submit_btn'] = array(
    'type' => 'submit',
    'value' => t('Apply'),
    'weight' => 100,
  );
  
  $form['reset_btn'] = array(
    'type' => 'submit',
    'value' => t('Reset'),
    'weight' => 110,
  );
  
  
  return $form;
}  




function user_student_list_filter_form_submit($form, $form_state) {

  $values = $form_state['values'];
    
  unset($_SESSION['users_filter_name']);
  unset($_SESSION['users_filter_school']);
  unset($_SESSION['users_filter_major']);
  unset($_SESSION['users_filter_active']);  
  unset($_SESSION['users_filter_submit']);  
    
  if ($values['submit_btn'] != '') {
    $_SESSION['users_filter_name'] = fp_trim(@$values['filter_name']);
    $_SESSION['users_filter_school'] = fp_trim(@$values['filter_school']);
    $_SESSION['users_filter_major'] = fp_trim(@$values['filter_major']);
    $_SESSION['users_filter_active'] = fp_trim(@$values['filter_active']);
    
    $_SESSION['users_filter_submit'] = "yes"; // simply notes that we did indeed press the submit btn.

    // If "active" was the only one changed, then we do not set this users_filter_submit
    if (fp_trim(@$values['filter_name']) == "" && intval(@$values['filter_school']) === 0 && fp_trim(@$values['filter_major']) == "") {
      unset($_SESSION['users_filter_submit']);
    }      
    
        
  }
    
    
  fp_goto("admin/users/students", "de_catalog_year=" . $values['de_catalog_year']);  
    
}








/**
 * This is the form which we will use to manage a student's courses they've taken.
 */
function user_student_edit_student_courses_form() {
    
  fp_add_js(fp_get_module_path("user") . "/js/user.js");

  $m = 0;
  $form = array();
  $student_cwid = $_REQUEST["student_cwid"];  // now getting it from argument
  $user_id = db_get_user_id_from_cwid($student_cwid, "student");

  $de_catalog_year = @$_REQUEST["de_catalog_year"];

  // Figure out what the page's sub-tabs should be, and set them.
  $tab_array = array();
  $tab_array[0]["title"] = t("Edit Student");
  $tab_array[0]["active"] = FALSE;
  $tab_array[0]["on_click"] = "window.location=\"" . fp_url("admin/users/edit-student-user", "student_cwid=$student_cwid&de_catalog_year=$de_catalog_year") . "\"";
      
  $tab_array[1]["title"] = t("Edit Student Courses");
  $tab_array[1]["active"] = TRUE;
  $tab_array[1]["on_click"] = "window.location=\"" . fp_url("admin/users/edit-student-user/courses", "student_cwid=$student_cwid&de_catalog_year=$de_catalog_year") . "\"";
      
      
  // If there are attributes for a student, then show the tab.
  $attributes = user_get_registered_attributes();
  
  foreach ($attributes as $def) {
    if (@$def['settings']['user_type'] == 'student' || @$def['settings']['user_type'] == 'all') {
      $tab_array[2]["title"] = t("Edit User Attributes");
      $tab_array[2]["active"] = FALSE;
      $tab_array[2]["on_click"] = "window.location=\"" . fp_url("admin/users/edit-student-user/attributes", "user_id=$user_id&user_type=student&student_cwid=$student_cwid&de_catalog_year=$de_catalog_year") . "\"";      
      break;
    } 
  }       
      
  fp_set_page_sub_tabs($tab_array);




  
  if ($student_cwid != "new") {  
    $name = fp_get_student_name($student_cwid);  
    fp_set_title(t("Edit Student Courses @name (@id)", array("@name" => $name, "@id" => $student_cwid)));
  }
  else {
    // A new student!  We can't create a new student until a CWID is assigned.
    fp_set_title(t("Create New Student User"));
    $form["mark_sorry"] = array(
      "type" => "markup",
      "value" => "<p>" . t("Sorry, but you cannot add student courses until the student
                      has been fully created (and given a CWID).  Use the Edit Student button above
                      to return to that screen.") . "</p>",
    );
    return $form;
  }

  $form["user_id"] = array(
    "type" => "hidden",
    "value" => $user_id,
  );

  $form["perform_action2"] = array(
    "type" => "hidden",
    "value" => "",
  );

  $form["student_cwid"] = array(
    "type" => "hidden",
    "value" => $student_cwid,
  );
  
  
  
  // Okay, let's create a table in which we will place the student's courses.
  $form["mark" . $m++] = array(
    "value" => "<p>
                  " . ("Enter the student's local (non-transfer) courses they have enrolled in at your institution.") . "
                  <ul>
                    <li>" . t("Course:  Enter the full course subject ID and num, separated by a space.  Ex:  ACCT 101") . "</li>
                    <li>" . t("Term:  Enter the term code that this student enrolled in this course. Ex:  201540") . "</li>
                    <li>" . t("Grade: Enter the grade they earned. Ex: B") . "</li>
                    <li>" . t("Hrs: Enter the hours awarded.  Ex: 3") . "</li>
                    <li>" . t("Lvl: (optional), enter the level code for this course. Ex: UG") . "</li>
                  </ul>
                  " . t("<b>Note:</b> If you have any routines which might overwrite this data (in the student_courses table), then this
                               data may be ignored or deleted!") . "
                </p>",
  );
  
  $form["warn_me"] = array(
    "type" => "checkbox",
    "label" => t("Warn me if I enter a course which doesn't exist in draft_courses (good for catching typos)"),    
    "value" => "yes",
  );
  
  
  $form["mark" . $m++] = array(
    "value" => "
                <table border='0' width='100%' cellpadding='3' cellspacing='0'>
                  <tr>
                    <th>" . t("Course") . "</th>
                    <th>" . t("Term") . "</th>
                    <th>" . t("Grade") . "</th>
                    <th>" . t("Hrs") . "</th>
                    <th>" . t("Lvl") . "</th>
                  </tr>",
  );
  
  
  // Let's get an array of what courses the student has already taken (if any)
  $courses = array();
  $res = db_query("SELECT * FROM student_courses 
                   WHERE student_id = '?'
                   ORDER BY subject_id, course_num", $student_cwid);
  while ($cur = db_fetch_array($res)) {
    $courses[] = array(
      "course" => $cur["subject_id"] . " " . $cur["course_num"],
      "term" => $cur["term_id"],
      "grade" => $cur["grade"],
      "hrs" => $cur["hours_awarded"] * 1, // will trim excess zeroes if there
      "lvl" => $cur["level_code"],
    );
  }                   
              
  // Let's add 10 additional (blank) lines to the courses array.
  for ($t = 0; $t < 10; $t++) {
    $courses[] = array(
      "course" => "",
      "term" => "",
      "grade" => "",
      "hrs" => "",
      "lvl" => "",
    );    
  }
  
  
  // Okay, now let's go through the courses array and display in the table...
  
  $stripe = "";  
  foreach ($courses as $t => $val) {
        
    if ($stripe == "") {
      $stripe = "style='background-color: beige;'";
    }
    else {
      $stripe = "";
    }  
        
    $form["course_$t"] = array(
      "prefix" => "<tr $stripe >
                      <td valign='top'>",
      "type" => "textfield",
      "size" => 12,      
      "suffix" => "</td>",
      "value" => $val["course"],
    );    

    $form["term_$t"] = array(
      "prefix" => "<td valign='top'>",
      "type" => "textfield",
      "size" => 8,      
      "suffix" => "</td>",
      "value" => $val["term"],
    );    

    $form["grade_$t"] = array(
      "prefix" => "<td valign='top'>",
      "type" => "textfield",
      "size" => 4,      
      "suffix" => "</td>",
      "value" => $val["grade"],
    );    
    
    $form["hrs_$t"] = array(
      "prefix" => "<td valign='top'>",
      "type" => "textfield",
      "size" => 4,      
      "suffix" => "</td>",
      "value" => $val["hrs"],
    );    
                
    $form["lvl_$t"] = array(
      "prefix" => "<td valign='top'>",
      "type" => "textfield",
      "size" => 4,      
      "suffix" => "</td>
                   </tr>",
      "value" => $val["lvl"],                   
    );    
                    
    
  } // for loop
   
  
  $form["number_of_courses"] = array(
    "type" => "hidden",
    "value" => count($courses),
  );
  
  
  
  // close table.
  $form["mark" . $m++] = array(
    "value" => "</table>
                <p>" . t("Rows without Course entered will be skipped.
                    <br><br><b>Note:</b> To add more blank rows, save your work.  When the page reloads, there will be additional
                   blank rows to add new courses.") . "</p>",
  );
  

  $form["submit_btn"] = array(
    "type" => "submit",
    "value" => "Submit",
  );  
  
  
  return $form;
} // user_student_edit_student_courses_form


function user_student_edit_student_courses_form_validate($form, $form_state) {
  // If the user requested it, check to see if the course exists (in the draft_courses table)
   
  if ($form_state["values"]["warn_me"] === TRUE) {
    // Yes, the user wants to be warned if they entered a course which doesn't exist.
    $number_of_courses = intval($form_state["values"]["number_of_courses"]);
    for ($t = 0; $t < $number_of_courses; $t++) {
      $course = trim($form_state["values"]["course_$t"]);
      if ($course == "") continue;
      
      $temp = explode(" ", $course);
      $subject_id = trim($temp[0]);
      $course_num = trim($temp[1]);
      
      // Check to see that this course exists.
      $res = db_query("SELECT subject_id FROM draft_courses
                       WHERE subject_id = '?'
                       AND course_num = '?' ", $subject_id, $course_num);
      $cur = db_fetch_array($res);
      if ($cur["subject_id"] != $subject_id) {
        form_error("course_$t", t("The course %subject_id %course_num could not be found in the draft_courses table.  A typo?  Your data has NOT been saved.", 
                                                  array("%subject_id" => $subject_id, "%course_num" => $course_num)));
      }                       
                
    }
  }
  
  
  
}





function user_student_edit_student_courses_form_submit($form, $form_state) {
    
  $student_id = $form_state["values"]["student_cwid"];
  
  
  // Trim and sanitize as needed  
  foreach ($form_state['values'] as $key => $val) {
    if (!is_array($val)) {
      $form_state['values'][$key] = fp_trim($val);
      if (!strstr($key, "password")) {
        $form_state['values'][$key] = filter_plain($val);
      }
    }
  }
  
  $db = get_global_database_handler();
  
  // Erase what's there already:
  db_query("DELETE FROM student_courses WHERE student_id = '?' ", $student_id);  
  
  $school_id = $db->get_school_id_for_student_id($student_id);
  
  $number_of_courses = intval($form_state["values"]["number_of_courses"]);
  for ($t = 0; $t < $number_of_courses; $t++) {
    $course = trim($form_state["values"]["course_$t"]);
    if ($course == "") continue;
    
    $temp = explode(" ", $course);
    $subject_id = trim($temp[0]);
    $course_num = trim($temp[1]);      
    
    $course_id = intval($db->get_course_id($subject_id, $course_num, "", FALSE, $school_id));
    
    $term_id = trim($form_state["values"]["term_$t"]);
    $grade = trim($form_state["values"]["grade_$t"]);
    $hours_awarded = trim($form_state["values"]["hrs_$t"]);
    $level_code = trim($form_state["values"]["lvl_$t"]);

    // Add to table
    db_query("INSERT INTO student_courses (student_id, subject_id, course_num, hours_awarded, grade, term_id, level_code, course_id)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)", array($student_id, $subject_id, $course_num, $hours_awarded, $grade, $term_id, $level_code, $course_id));
   
  }  

  watchdog("user_edit_courses", "Update student (@cwid) courses values: @other", array("@cwid" => $student_id, "@other" => ppm($form_state['values'], TRUE)));
   
  
  fp_add_message(t("Student courses updated."));
  
    
} // student_courses_form_submit






/**
 * Let the user edit a studentuser's information.
 */
function user_edit_student_user_form() {

  fp_add_js(fp_get_module_path("user") . "/js/user.js");

  $form = array();
  $m = 0;
  $student_cwid = @$_REQUEST["student_cwid"];  // now getting it from argument
  $de_catalog_year = @$_REQUEST["de_catalog_year"];
  $user_id = db_get_user_id_from_cwid($student_cwid, "student");

  $db = get_global_database_handler();

  // Figure out what the page's sub-tabs should be, and set them.
  $tab_array = array();
  $tab_array[0]["title"] = t("Edit Student");
  $tab_array[0]["active"] = TRUE;
  $tab_array[0]["on_click"] = "window.location=\"" . fp_url("admin/users/edit-student-user", "student_cwid=$student_cwid&de_catalog_year=$de_catalog_year") . "\"";
      
  $tab_array[1]["title"] = t("Edit Student Courses");
  $tab_array[1]["active"] = FALSE;
  $tab_array[1]["on_click"] = "window.location=\"" . fp_url("admin/users/edit-student-user/courses", "student_cwid=$student_cwid&de_catalog_year=$de_catalog_year") . "\"";
      
  // If there are attributes for a student, then show the tab.
  $attributes = user_get_registered_attributes();
  
  foreach ($attributes as $def) {
    if (@$def['settings']['user_type'] == 'student' || @$def['settings']['user_type'] == 'all') {
      $tab_array[2]["title"] = t("Edit User Attributes");
      $tab_array[2]["active"] = FALSE;
      $tab_array[2]["on_click"] = "window.location=\"" . fp_url("admin/users/edit-student-user/attributes", "user_id=$user_id&user_type=student&student_cwid=$student_cwid&de_catalog_year=$de_catalog_year") . "\"";      
      break;
    } 
  }        
      
      
      
  fp_set_page_sub_tabs($tab_array);





  
  if ($student_cwid != "new") {  
    $name = fp_get_student_name($student_cwid);  
    fp_set_title(t("Edit Student User @name (@id)", array("@name" => $name, "@id" => $student_cwid)));
  }
  else {
    // A new student!
    fp_set_title(t("Create New Student User"));
  }

  $form["user_id"] = array(
    "type" => "hidden",
    "value" => $user_id,
  );

  $form["perform_action2"] = array(
    "type" => "hidden",
    "value" => "",
  );

  $form["student_cwid"] = array(
    "type" => "hidden",
    "value" => $student_cwid,
  );
    
  
  // TODO: At the moment, only faculty can be assigned roles in FP.  However, this could change
  // one day, so I am going to leave this code in place for students, but commented out.
  /*
  $user_roles = system_get_roles_for_user($user_id);
  //fpm($user_roles);
  $default_values = array();
  foreach ($user_roles as $rid => $val) {
    $default_values[$rid] = $rid;
  }

  // Show a list of roles in the system which we may select from, and check the ones
  // all ready assigned to this user.
  
  $options = array();
      
  $res = db_query("SELECT * FROM roles ORDER BY rid");
  while ($cur = db_fetch_array($res)) {
    
    $key = $cur["rid"];
    $value = $cur["name"];

    if ($key > 2) {
      $options[$key] = $value;
    } 
      
  }  
//fpm($default_values);
  $form["roles"] = array(
    "label" => t("Check which roles this user should have."),
    "type" => "checkboxes",
    "options" => $options,
    "value" => $default_values,
  );
  */  


  // Let's present the form elements to allow some basic editing of this user.
  
  // Only if we are making a new student...
  if ($student_cwid == "new") {
    $form["new_student_cwid"] = array(
      "label" => t("Enter a new CWID, unique to students:"),
      "type" => "textfield",
      "size" => 20,
      "required" => TRUE,     
      "description" => t("Enter an alpha-numeric ID for this student.  It may be the same
                          as a faculty member, but may not be the same as any existing
                          student.  You will not be able to edit this value, once saved."),
      "weight" => 10,
    );
    
    $form["new_user_name"] = array(
      "label" => t("Enter a new username, unique to all users:"),
      "type" => "textfield",
      "size" => 20,     
      "required" => TRUE,
      "description" => t("Enter a username for this user.  This is what the user will
                          use to log in.  It must be unique to all users (cannot have both
                          a faculty and a student with the same username).  You will not
                          be able to edit this value, once saved."),
      "weight" => 10,
    );
        
    
    $cur = array();  
  }
  else {
    // NOT a new student.  Load their information normally.
    
    $res = db_query("SELECT * FROM users u, students s
                     WHERE u.cwid = '?'
                     AND u.is_student = '1'
                     AND u.cwid = s.cwid", $student_cwid);
    $cur = db_fetch_array($res);
  }

  $user_name = @$cur["user_name"];
  if ($user_name != "") {
    $form["mark" . $m++] = array(
      "value" => "<p><b>Username:</b> $user_name</p>",
    );
  }
  
  $form["new_password"] = array(
    "label" => t("Enter a new password for this user:"),
    "type" => "textfield",
    "size" => 20,
    "required" => ($student_cwid == "new") ? TRUE : FALSE,
    "description" => t("If you enter any value here, it will change the
                        user's password in FlightPath.  If you are using a single-sign-on or similar module (CAS, SAML, LDAP, etc)
                        for external user password management, the password will be <strong>unaffected</strong> by this setting."),
    "weight" => 20,                        
  );

  $form["email"] = array(
    "label" => t("Email:"),
    "type" => "textfield",
    "value" => @$cur["email"],
    "weight" => 30,    
  );  

  $form["f_name"] = array(
    "label" => t("First name:"),
    "type" => "textfield",
    "value" => @$cur["f_name"],
    "weight" => 40,
  );  

  $form["l_name"] = array(
    "label" => t("Last name:"),
    "type" => "textfield",
    "value" => @$cur["l_name"],
    "weight" => 50,
  );  

  $form["is_disabled"] = array(
    "label" => t("Is disabled:"),
    "type" => "textfield",
    "value" => @$cur["is_disabled"],
    "size" => 5,
    "description" => t("Enter only 1 or 0 (number one for 'yes', or number zero for 'no').  This setting means the user will
                        be ignored by FlightPath, and they will not be able to log in or be searched for.
                        It is safer to disable a user, than delete them."),
    "weight" => 60,
  );  
  
  
  // Unique to students...
  $form["cumulative_hours"] = array(
    "label" => t("Cumulative hours:"),
    "type" => "textfield",
    "value" => @$cur["cumulative_hours"],
    "size" => 5,
    "description" => t("How many hours has the student earned?  NOTE: If FlightPath is set to
                        calculate this, rather than read from the database, whatever you enter here
                        will be ignored, and instead this value will be calculated on the fly when
                        the student is loaded."),
    "weight" => 70,
  );  
    
  $form["gpa"] = array(
    "label" => t("GPA:"),
    "type" => "textfield",
    "value" => @$cur["gpa"],
    "size" => 5,
    "description" => t("What is the student's GPA?  NOTE: If FlightPath is set to
                        calculate this, rather than read from the database, whatever you enter here
                        will be ignored, and instead this value will be calculated on the fly when
                        the student is loaded."),
    "weight" => 80,                            
  );  
    
  $form["rank_code"] = array(
    "label" => t("Rank or Classification:"),
    "type" => "textfield",
    "value" => @$cur["rank_code"],
    "size" => 5,
    "description" => t("For example, FR, SO, JR, SR, GR for Freshman, Sophomore, Junior, Senior, and Graduate.") . "
                      <br><b>" . t("Important:") . "</b> " . t("You must enter a code from the Allowed Student Ranks field
                      on the System Settings form.  For example, FR.  If you do not do this, you will not be able to search
                      for this student in FlightPath.") . "<br>&nbsp; &nbsp; &nbsp;" . t("Current value for Allowed Student Ranks:") . " 
                      <i>" . variable_get("allowed_student_ranks", "") . "</i>",
    "weight" => 90,
  );  

  
  $major_code_array = $db->get_student_majors_from_db($student_cwid, TRUE, FALSE, FALSE);

  $temp = "";
  foreach ($major_code_array as $details) {
    if ((string)$details["is_editable"] == "1") {
      $temp .= "* ";
    }
    
    $temp .= $details["major_code"] . "\n";
  }      
  
  $form["major_codes"] = array(
    "label" => t("Major code(s) and Directives:"),
    "type" => "textarea",
    "value" => $temp,
    "cols" => 40,
    "rows" => 5,
    "description" => t("Enter the student's major codes, one per line.
                          <br>Ex:
                          <br>&nbsp; &nbsp; ACCT
                          <br>&nbsp; &nbsp; MATH
                        <br>Advanced: If any of the level 3 (add-on) major codes can be 'editable' by the advisor or another
                        privileged user during normal advising, add an asterisk
                        at the beginning.  For example: <b>*</b> ACCT|_COSC.  If you are unsure what this means, do not enter an asterisk.
                        <br>
                        <br>Available directives (advanced):
                        <ul>
                          <li>LOCKED ~ level_3_class_code
                          <ul>
                            <li>
                              This will tell FlightPath not to allow a user to make any changes (except in What If mode) to this student's degrees for the specified
                              level 3 classification.  For example, to prevent additions or removals from Concentrations (CONC) for this student, you would enter:
                              <br> &nbsp; &nbsp; &nbsp; LOCKED ~ CONC
                              <br>(notice the single tilde ~ character between the LOCKED keyword and the code for the degree class).                              
                            </li> 
                          </ul>
                          </li>
                        </ul>  
                            
                            
                            "),
    "weight" => 100,
  );  
    
    
  $form["catalog_year"] = array(
    "label" => t("Catalog year:"),
    "type" => "textfield",
    "value" => @$cur["catalog_year"],
    "size" => 10,
    "description" => t("Only the leading year is used.  For example, for the
                        catalog year 2008-2009, you would just enter 2008."),
    "weight" => 110,
  );  

  $form["is_active"] = array(
    "label" => t("Active?:"),
    "type" => "select",
    "options" => array(1 => t("Y - Active"), 0 => t("N - Inactive")),
    "hide_please_select" => TRUE,
    "value" => @$cur["is_active"],
    "size" => 5,
    "description" => t("This setting means the student
                      will not appear in searches automatically, unless the advisor specifies to search
                      for inactive students."),
    "weight" => 120,
  );  
        
      
      
  
  $form["submit"] = array(
    "type" => "submit",  
    "value" => "Submit",    
    "prefix" => "<hr>",
    "weight" => 500,
  );
  
  
  if ($student_cwid != "new" && user_has_permission("delete_users")) {
    
    $form["mark" . $m++] = array(
      "type" => "markup",
      "value" => "<div align='right'>
                        " . t("Delete this student?") . " <input type='button' value='X'
                        onClick='userDeleteStudent();'>
                      </div>",
      "weight" => 600, 
    );  
  }
  
  
  
  
  
  return $form;    
  
}


/**
 * Validate handler for editing student users.
 */
function user_edit_student_user_form_validate($form, $form_state) {
  
  $values = $form_state["values"];
  
  // If a password was given, make sure it is appropriate.
  // TODO: Make sure it conforms to our complexity
  if (trim($values["new_password"]) != "") {
    if (strlen(trim($values["new_password"])) < 8) {
      form_error("new_password", t("Please enter a password that is at least 8 characters long."));
      return;
    }
  }
  
  // If creating a new user, make sure new_student_cwid and new_user_name are not
  // already in use.
  if ($values["student_cwid"] == "new") {
    $new_cwid = trim($values["new_student_cwid"]);
    $new_user_name = trim($values["new_user_name"]);

    

    // Check that username is at least 4 characters
    if (strlen($new_user_name) < 4) {
      form_error("new_user_name", t("The username you entered is too short.  It must be at least 4 characters.
                                      Please select a different username."));
      return;
      
    }

    
    // Check cwid isn't already in use.
    $test = db_result(db_query("SELECT cwid FROM users WHERE cwid = '?' AND is_student = '1'", $new_cwid));    
    if ($test == $new_cwid) {
      form_error("new_student_cwid", t("The cwid you entered is already in use.  Please select a different cwid."));
      return;
    }
   
    // Check user_name isn't already in use.
    $test = db_result(db_query("SELECT user_name FROM users WHERE user_name = '?' ", $new_user_name));    
    if ($test == $new_user_name) {
      form_error("new_user_name", t("The username you entered is already in use.  Please select a different username."));
      return;
    }
      
   
    
  }
    
  
}


/**
 * Submit handler for editing student users.
 */
function user_edit_student_user_form_submit($form, $form_state) {
      
    
  // Trim and sanitize as needed  
  foreach ($form_state['values'] as $key => $val) {
    if (!is_array($val)) {
      $form_state['values'][$key] = fp_trim($val);
      if (!strstr($key, "password")) {
        $form_state['values'][$key] = filter_plain($val);
      }
    }
  }
        
  
  $values = $form_state["values"];

  //fpm($values);
  $user_id = $values["user_id"];
  $student_cwid = $values["student_cwid"];
  
  // Are we supposed to DELETE a student?
  if ($values["perform_action2"] == "delete_student" && user_has_permission("delete_users")) {

    
    db_query("DELETE FROM students WHERE cwid = '?' ", $student_cwid);
    db_query("DELETE FROM users WHERE cwid = '?' AND is_student = '1' ", $student_cwid);      
    
    watchdog("user_edit", "Deleted student user $user_id (cwid $student_cwid)");
    
    
    fp_add_message(t("User has been deleted."));
    fp_goto("admin/users/students");
    return;
  }
  
  $bool_is_new = FALSE;
  
  if ($student_cwid != "new") {
    // NOT a new student!  Insert values normally.
    // First-- was there a password given?  If so, insert that separate.
    if (trim($values["new_password"]) != "") {
      $new_pass = user_hash_password(trim($values["new_password"]));
      db_query("UPDATE users 
                SET password = '?' 
                WHERE cwid = '?' 
                AND is_student = '1' ", $new_pass, $student_cwid);
                
      unset($values['new_password']);
      watchdog("user_edit", "Update student user with cwid @cwid with new password", array("@cwid" => $student_cwid));
    }  
    // Okay, now we can just update everything else.
    // Update users table first...
    db_query("UPDATE users
              SET email = '?',                  
                  f_name = '?',
                  l_name = '?',
                  is_disabled = '?'
               WHERE cwid = '?'
               AND is_student = '1' ", trim(strtolower($values["email"])), $values["f_name"], 
                                       $values["l_name"], $values["is_disabled"],
                                       $student_cwid);
   
   // Now, update the students table entry.
   db_query("UPDATE students
             SET cumulative_hours = '?',
                 gpa = '?',
                 rank_code = '?',                 
                 catalog_year = '?',
                 is_active = '?'                 
             WHERE cwid = '?'", $values["cumulative_hours"], $values["gpa"], $values["rank_code"],
                                $values["catalog_year"], 
                                $values["is_active"], $student_cwid);             
    watchdog("user_edit", "Update student user with various values: @other", array("@other" => ppm($values, TRUE)));
    fp_add_message(t("User updated successfully."));

  }
  else {
    // This is a NEW user!  We need to perform inserts.  Thanks to our validate handler,
    // we know all of the values we have are valid.
    
    $bool_is_new = TRUE;
    
    if (trim($values["l_name"]) == "") {
      $values["l_name"] = $values["new_user_name"];  // force a last name if none supplied
    }
    
    db_query("INSERT INTO users (user_name, password, is_student, email, cwid, f_name, l_name, is_disabled)
              VALUES ('?', '?', '1', '?', '?', '?', '?', '?')
              ", $values["new_user_name"], user_hash_password($values["new_password"]), trim(strtolower($values["email"])), $values["new_student_cwid"],
                 $values["f_name"], $values["l_name"], intval($values["is_disabled"]));
    
    db_query("INSERT INTO students (cwid, cumulative_hours, gpa, rank_code, catalog_year, is_active)
              VALUES ('?', '?', '?', '?', '?', '?')
             ", $values["new_student_cwid"], $values["cumulative_hours"], $values["gpa"], $values["rank_code"],
                $values["catalog_year"], intval($values["is_active"]));
                                  
    fp_add_message(t("User created successfully."));
    
    $student_cwid = $values["new_student_cwid"];
    
    unset($values['new_password']);
    watchdog("user_edit", "Create new student user with various values: @other", array("@other" => ppm($values, TRUE)));
        
  }
  
  
  // Now, regardless if this was a new student or not, we need to update the student_degrees table.
  // First, delete what's there for this CWID.
  db_query("DELETE FROM student_degrees WHERE student_id = ?", $student_cwid);
  // Now, insert.
  $temp = explode("\n", $values["major_codes"]);
  foreach ($temp as $mc) {
    $mc = trim($mc);
    if ($mc == "") continue;
    
    $is_editable = "0";
    if (strstr($mc, "*")) {
      $is_editable = "1";
      $mc = trim(str_replace("*", "", $mc));  // remove the asterisk.
    }
    
    // Now, insert into the table.
    db_query("INSERT INTO student_degrees (student_id, major_code, is_editable)
              VALUES (?, ?, ?) ", array($student_cwid, $mc, $is_editable));    
    
  }
  
  
  
  if ($bool_is_new) {
    // If new, we need to go to the regular edit form for this newly created student.
    fp_goto("admin/users/edit-student-user", "student_cwid=" . $values["new_student_cwid"]);
  }
  
  // If not new, it just reloads the form normally.
  
}

