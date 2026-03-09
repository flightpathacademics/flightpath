<?php

/* This include file deals with Groups editing for FlightPath */



/**
 * Validate handler for edit group form.
 */
function admin_edit_group_form_validate(&$form, &$form_state) {
  $values = $form_state["values"];
  $group_name = trim($values["group_name"]);
  $group_id = $values["group_id"];
  $de_catalog_year = $values["de_catalog_year"];

  $school_id = 0;
  if (isset($values['schools_school'])) { // A school was set from the schools module.
    $school_id = intval($values['schools_school']);  // Use our NEW school_id for validating.
  }
  
  $school_name = $andschool = "";
  if ($school_id > 0) $school_name = "School-" . $school_id;
  if (module_enabled("schools")) {
    $defs = schools_get_school_definitions();
    if (isset($defs[$school_id])) $school_name = $defs[$school_id];
    $andschool = " and school %school";
  }
  
  
  // We want to make sure the group_name is in a machine-name format,
  // and we need to make sure it is unique for this catalog year and school_id!
  $group_name = fp_get_machine_readable(strtolower($group_name));
  
  $form_state['values']['group_name'] = $group_name;
  
    
  // Check that group_name is unique (but exclude the current group_id from the check)
  $mycount = db_query("SELECT count(*) as mycount FROM draft_groups 
                       WHERE group_name = ?
                       AND group_id != ?
                       AND delete_flag = 0
                       AND catalog_year = ?
                       AND school_id = ?", $group_name, $group_id, $de_catalog_year, $school_id)->fetchColumn();
  
  if ($mycount > 0) {    
    form_error("group_name", t("The 'Internal group machine name' %gn is already in use in FlightPath by another group
                                in the catalog year %cy$andschool.  
                                Please change to a different name, and submit again. <b>Your work has NOT been saved.</b>
                                ", array("%gn" => $group_name, "%cy" => $de_catalog_year, "%school" => $school_name)));
    return;
  }
  
  
  $db = get_global_database_handler();
  


  // Make sure that courses are for the correct school for this group.
  $courses = $values['courses'];
  // Okay, now we look at the actual "courses" box and assemble the group
  // in the database.
  $lines = explode("\n", $courses);
  for ($t = 0; $t < count($lines); $t++) {
    $line = trim($lines[$t]);
    if ($line == "") { continue; }
    // Get rid of extra whitespace.
    $line = str_replace("  ", " ", $line);
    $line = str_replace("  ", " ", $line);
    $line = str_replace("  ", " ", $line);
    $line = str_replace("  ", " ", $line);
    $line = str_replace("  ", " ", $line);
    $line = str_replace("  ", " ", $line);

    // Does this line contain at least one & symbol?  If it does,
    // then this is a subgroup (branch).  If not, then we can insert
    // the course as-is.
    if (strstr($line, "&")) {
      // This line DOES have an ampersand (&), so this is a sub group
      // within this group.
      $line = trim(str_replace("-", "", $line));  // get rid of attributes, if any.
      $line = trim(str_replace("*", "", $line));  // get rid of attributes, if any.
      
      $c_tokes = explode("&",$line);
      for ($cT = 0; $cT < count($c_tokes); $cT++)
      {        
        $tokens = explode(" ", trim($c_tokes[$cT]));
        $subject_id = trim($tokens[0]);
        $course_num = trim($tokens[1]);

        // If the subject_id had a _A_ in it, convert this back
        // to an ampersand.
        $subject_id = str_replace("_A_", "&", $subject_id);

        if ($course_id = $db->get_course_id($subject_id, $course_num, "", TRUE, $school_id, TRUE)) {
          // Course was found correctly, no need to do anything.                    
        } 
        else {
          // The course_id could not be found!

          // Different message if it's a school_id problem.
          if (module_enabled("schools")) {
            
            form_error("courses", t("<strong>Your work has NOT been saved.</strong> Course not found.  You specified the course %course as a requirement,
                            but this course could not be found.  This could be due to a typo, or due to the course not belonging to the same school
                            as the group (%schoolname).  Please check the course (and possibly change the group's school back first), then try again.", array("%course" => "$subject_id $course_num", "%schoolname" => $school_name)));
          }
          else {
            // Not related to school (possibly).
            form_error("courses", t("<strong>Your work has NOT been saved.</strong> Course not found.  You specified the course %course as a requirement,
                            but this course could not be found.  This could be due to a typo.  
                            Please check the course and try again.", array("%course" => "$subject_id $course_num")));
                      
          }          
        }    

      } // for cT (tokens)


    } // if line contains & 
    else {
      // Did NOT contain an ampersand (&), so this goes in the
      // regular course requirements.
      
      $line = trim(str_replace("-", "", $line));  // get rid of attributes, if any.
      $line = trim(str_replace("*", "", $line));  // get rid of attributes, if any.
      
      if ($line == "") continue; // blank line, so skip
            
      
      $tokens = explode(" ", $line);
      $subject_id = @trim($tokens[0]);
      $course_num = @trim($tokens[1]);

      if ($subject_id == "") continue; // blank line, or mal-formed, so skip.
      
      // If the subject_id had a _A_ in it, convert this back
      // to an ampersand.
      $subject_id = str_replace("_A_", "&", $subject_id);

      // We don't care about catalog year anymore...
      if ($course_id = $db->get_course_id($subject_id, $course_num, "", TRUE, $school_id, TRUE)) {
        // Course found normally, do nothing.        
      } 
      else {
            
          // Different message if it's a school_id problem.
          if (module_enabled("schools")) {
            
            form_error("courses", t("<strong>Your work has NOT been saved.</strong> Course not found.  You specified the course %course as a requirement,
                            but this course could not be found.  This could be due to a typo, or due to the course not belonging to the same school
                            as the group (%schoolname).  Please check the course (and possibly change the group's school back first), then try again.", array("%course" => "$subject_id $course_num", "%schoolname" => $school_name)));
          }
          else {
            // Not related to school (possibly).
            
            form_error("courses", t("<strong>Your work has NOT been saved.</strong> Course not found.  You specified the course %course as a requirement,
                            but this course could not be found.  This could be due to a typo.  
                            Please check the course and try again.", array("%course" => "$subject_id $course_num")));
                 
          }          
            

      }

    } // else (did not contain &)

  } // for t (lines of courses)  
    
  
  
} // .._validate



function admin_edit_group_form_submit(&$form, &$form_state) {
  $values = $form_state["values"];
  $de_catalog_year = $values["de_catalog_year"];

  $db = get_global_database_handler();  
  $db2 = new DatabaseHandler();

  $group_id = $values["group_id"];

  // Okay, we are trying to save the details of this group.
  // First thing we need to do is UPDATE the title, group_name,
  // priority, icon and comment.
  $group_name = filter_plain($values["group_name"]);
  $title = filter_plain($values["title"]);
  $priority = intval(trim($values["priority"]));
  $icon_filename = filter_plain($values["icon_filename"]);
  $data_entry_comment = filter_plain($values["data_entry_comment"]);
  $catalog_repeat = intval(trim($values["catalog_repeat"]));
  $public_note = filter_markup($values["public_note"]);


  $school_id = 0;
  if (isset($values['schools_school'])) { // A school was set from the schools module.
    $school_id = intval($values['schools_school']);  // Use our NEW school_id for validating.
  }



  // Save the entire post to the log.
 
  // Since we are making a change to the draft table(s), let's add a row
  // to draft instructions.
  $db->add_draft_instruction("-");


  // Are we trying to delete this group?
  if ($_POST["perform_action2"] == "delete_group" && user_has_permission("can_delete_data_entry")) {
    $res = db_query("UPDATE draft_groups
                SET delete_flag = '1'
                WHERE group_id = '?' 
                AND catalog_year = '?'
                ", $group_id, $de_catalog_year);
    fp_add_message(t("The group @title (%name) has been deleted successfully for @year", array("@title" => $title, "%name" => $group_name, "@year" => $de_catalog_year)));
    
    watchdog("admin", "Deleted group: $group_name - $group_id ($de_catalog_year), school_id = $school_id");
    
    fp_goto("admin/groups", "de_catalog_year=$de_catalog_year");    
    return;
  }

  // If the $group_id == new then create a new one.
  if ($group_id == "new") {
    $group_id = $db->request_new_group_id();
    $res = db_query("INSERT INTO draft_groups(group_id, catalog_year)
                values ('?','?') ", $group_id, $de_catalog_year);
    $values["group_id"] = $group_id;
    // Let's reset where we should redirect to after the form submits.
    $form["#redirect"] = array(
      "path" => "admin/groups/edit-group",
      "query" => "group_id=$group_id&de_catalog_year=$de_catalog_year",
    );    
    
    $form_state['values']['group_id'] = $group_id; // set the new group id back into the array, for other modules to use.
    
  }

  $res = db_query("UPDATE draft_groups
              SET group_name = ?,
              title = ?,
              priority = ?,
              icon_filename = ?,
              catalog_repeat = ?,
              public_note = ?,
              data_entry_comment = ?              
              WHERE
                group_id = ? ", 
              $group_name, $title, $priority, $icon_filename, $catalog_repeat, $public_note, $data_entry_comment, $group_id);

  // We need to delete all the existing course & subgroup requirements from this group.
  // That entails first seeing what subgroups were required and deleting them,
  // then deleting the parent group's requirements.
  // First, find and delete the branches (child groups):
  $res = db_query("SELECT * FROM draft_group_requirements
              WHERE group_id = '?'
              AND child_group_id != '0' ", $group_id);
  while ($cur = db_fetch_array($res)) {
    $cg_id = $cur["child_group_id"];
    $res2 = db_query("DELETE FROM draft_group_requirements
                WHERE group_id = '?' ", $cg_id);
  }
  // Now delete the course requirements...
  $res = db_query("DELETE FROM draft_group_requirements
                WHERE group_id = '?' ", $group_id);

  $courses = trim($values["courses"]);
  // If a definition was set, then we will ignore what is in the POST
  // for the course requrements, and instead use the definition.
  if (trim($values["set_definition"] != "")) {
    $def = urldecode(trim($values["set_definition"]));
    //$cc = trim(get_courses_from_definition($def, $de_catalog_year));
    $temp2 = admin_get_courses_from_definition($def, "", $school_id);
    $cc = trim($temp2["text"]);
    if ($cc != "") {
      $courses = $cc;
      // UPDATE this group's definition!
      $res = db_query("UPDATE draft_groups
              SET definition = '?'
              WHERE
                group_id = '?' ", $def, $group_id);

    }
    //print_pre($cc);
  }
  else {
    // In other words, the setDefinition WAS blank.
    // Let's update the table.  This is to fix a bug where they were unable
    // to clear definitions.
      $res = db_query("UPDATE draft_groups
              SET definition = ''
              WHERE
                group_id = '?' ", $group_id);    
  }



  // If catalog_repeat == 1, then we need to perform that operation on the $courses text.
  if ($catalog_repeat == 1) {
    $courses = admin_process_catalog_repeats_for_group_courses_text($courses, $de_catalog_year, $school_id);
  }

  // Okay, now we look at the actual "courses" box and assemble the group
  // in the database.
  $lines = explode("\n", $courses);
  for ($t = 0; $t < count($lines); $t++) {
    $line = trim($lines[$t]);
    if ($line == "") { continue; }
    // Get rid of extra whitespace.
    $line = str_replace("  ", " ", $line);
    $line = str_replace("  ", " ", $line);
    $line = str_replace("  ", " ", $line);
    $line = str_replace("  ", " ", $line);
    $line = str_replace("  ", " ", $line);
    $line = str_replace("  ", " ", $line);

    // Does this line contain at least one & symbol?  If it does,
    // then this is a subgroup (branch).  If not, then we can insert
    // the course as-is.
    if (strstr($line, "&")) {
      // This line DOES have an ampersand (&), so this is a sub group
      // within this group.
      // First, we need to request a new branchID for this new group.
      if (!$branch_id = $db->request_new_group_id()) {
        die ("Error.  Could not create new group (branch) ID.");
      } 
      else {
        // Add this branch to the list of requirements for this group.
        $query = "INSERT INTO draft_group_requirements
                    (group_id, child_group_id)
                    values ('?','?') ";
        $res = db_query($query, $group_id, $branch_id);

      }
      
      $attributes = "";
      if (substr($line, 0, 1) == "-") $attributes .= "-";  // hidden attribute
      if (substr($line, 0, 1) == "*") $attributes .= "*";  // recommended attribute            
      $line = trim(str_replace("-", "", $line));  // get rid of attributes, if any.
      $line = trim(str_replace("*", "", $line));  // get rid of attributes, if any.
      
      $c_tokes = explode("&",$line);
      for ($cT = 0; $cT < count($c_tokes); $cT++)
      {        
        $tokens = explode(" ", trim($c_tokes[$cT]));
        $subject_id = trim($tokens[0]);
        $course_num = trim($tokens[1]);
        $min_grade = @trim($tokens[2]);
        $course_repeats = @trim($tokens[3]);

        if (strstr($min_grade, "[")) {
          // This is actually a specified repeat, not a min grade.
          $course_repeats = $min_grade;
          $min_grade = "";
        }

        $min_grade = str_replace("(","",$min_grade);
        $min_grade = str_replace(")","",$min_grade);

        $min_grade = strtoupper($min_grade);

        $course_repeats = str_replace("[","",$course_repeats);
        $course_repeats = str_replace("]","",$course_repeats);
        $course_repeats = intval($course_repeats);
        $course_repeats--;
        if ($course_repeats < 0) { $course_repeats = 0; }

        // If the subject_id had a _A_ in it, convert this back
        // to an ampersand.
        $subject_id = str_replace("_A_", "&", $subject_id);

        
        if ($course_id = $db->get_course_id($subject_id, $course_num, "", TRUE, $school_id, TRUE)) {
                    
          $query = "INSERT INTO draft_group_requirements
                    (group_id, course_id,
                    course_min_grade, course_repeats, attributes, data_entry_value)
                    values (?,?,?,?, ?, ?) ";
          $res = db_query($query, $branch_id, $course_id, $min_grade, $course_repeats, $attributes, "$subject_id~$course_num");
        } 
        else {
          // The course_id could not be found!
          
          form_error("courses", t("Course Not Found!
              You specified the course
              <b>%course</b> as a requirement, but this course
              could not be found in the catalog.
              It was removed from the list of requirements.
              Are you sure you typed it correctly?  Please check 
              your spelling, and add the course again.", array("%course" => "$subject_id $course_num")));

        }

      }


    } 
    else {
      // Did NOT contain an ampersand (&), so this goes in the
      // regular course requirements.
      
      $attributes = "";
      if (substr($line, 0, 1) == "-") $attributes .= "-";  // hidden attribute
      if (substr($line, 0, 1) == "*") $attributes .= "*";  // recommended attribute            
      $line = trim(str_replace("-", "", $line));  // get rid of attributes, if any.
      $line = trim(str_replace("*", "", $line));  // get rid of attributes, if any.
            
      
      $tokens = explode(" ", $line);
      $subject_id = @trim($tokens[0]);
      $course_num = @trim($tokens[1]);
      $min_grade = @trim($tokens[2]);
      $course_repeats = @trim($tokens[3]);

      if (strstr($min_grade, "[")) {
        // This is actually a specified repeat, not a min grade.
        $course_repeats = $min_grade;
        $min_grade = "";
      }

      $min_grade = str_replace("(","",$min_grade);
      $min_grade = strtoupper(str_replace(")","",$min_grade));

      $course_repeats = str_replace("[","",$course_repeats);
      $course_repeats = str_replace("]","",$course_repeats);
      $course_repeats--;
      if ($course_repeats < 0) { $course_repeats = 0; }

      // If the subject_id had a _A_ in it, convert this back
      // to an ampersand.
      $subject_id = str_replace("_A_", "&", $subject_id);

      // We don't care about catalog year anymore...      
      if ($course_id = $db->get_course_id($subject_id, $course_num, "", TRUE, $school_id, TRUE)) {
        $query = "INSERT INTO draft_group_requirements
                    (group_id, course_id,
                    course_min_grade, course_repeats, attributes, data_entry_value)
                    values (?,?,?,?,?,?) ";
        $res = db_query($query, $group_id, $course_id, $min_grade, $course_repeats, $attributes, "$subject_id~$course_num");
        
      } 
      else {
        // The course_id could not be found!
        form_error("courses", t("Course Not Found!
              You specified the course
              <b>%course</b> as a requirement, but this course
              could not be found in the catalog.
              It was removed from the list of requirements.
              Are you sure you typed it correctly?  Please check 
              your spelling, and add the course again.", array("%course" => "$subject_id $course_num")));

      }



    }

  }


  fp_add_message("Group successfully updated.");


  watchdog("admin", "Updated group: $group_name - $group_id ($de_catalog_year), school_id = $school_id");

  // Clear previous values to make sure the Required Courses box gets updated,
  // even if its set to readonly.
  clear_session_form_values("admin_edit_group_form");
  
}






/**
 * This function will accept the $courses text (textarea) from a group, which spells out
 * all of the courses, and then assign specified repeats based on what is set for that course
 * in the course catalog.
 */
function admin_process_catalog_repeats_for_group_courses_text($courses, $catalog_year, $school_id = 0) {
  $rtn = "";
  
  // Okay, now we look at the actual "courses" box and assemble the group
  // in the database.
  $lines = explode("\n", $courses);
  for ($t = 0; $t < count($lines); $t++) {
    $line = trim($lines[$t]);
    if ($line == "") { continue; }
    // Get rid of extra whitespace.
    $line = str_replace("  ", " ", $line);
    $line = str_replace("  ", " ", $line);
    $line = str_replace("  ", " ", $line);

    $new_line = "";

    // Does this line contain at least one & symbol?  If it does,
    // then this is a subgroup (branch), and we need to look at all the courses
    // on that branch.
    if (strstr($line, "&")) {
      
      // If this branch contains an ampersand, I don't think I should attempt to process it at all.
      $new_line = $line;
      
      
    } 
    else {
      // Did NOT contain an ampersand (&), so this goes in the
      // regular course requirements.
      $tokens = explode(" ", $line);
      $subject_id = @trim($tokens[0]);
      $course_num = @trim($tokens[1]);
      $min_grade = @trim($tokens[2]);
      $course_repeats = @trim($tokens[3]);

      if (strstr($min_grade, "[")) {
        // This is actually a specified repeat, not a min grade.
        $course_repeats = $min_grade;
        $min_grade = "";
      }

      $min_grade = str_replace("(","",$min_grade);
      $min_grade = strtoupper(str_replace(")","",$min_grade));

      $course_repeats = str_replace("[","",$course_repeats);
      $course_repeats = str_replace("]","",$course_repeats);
      $course_repeats = intval($course_repeats);
      $course_repeats--;
      if ($course_repeats < 0) { $course_repeats = 0; }

      // If the subject_id had a _A_ in it, convert this back
      // to an ampersand.
      $subject_id = str_replace("_A_", "&", $subject_id);

      // Okay, we now have a subject_id and course_number.  Let's find out how many times
      // it can be repeated for credit.
      $max_cat_repeats = fp_get_max_catalog_repeats_for_course($subject_id, $course_num, $catalog_year, TRUE, $school_id);
      if (!$max_cat_repeats) $max_cat_repeats = 0;

      // Okay, now, let's rebuild this line.        
      $subject_id = str_replace("&", "_A_", $subject_id);
      
      $new_line .= "$subject_id $course_num";
      // Was there a min grade?
      if ($min_grade != "") {
        $new_line .= " ($min_grade)";
      }
      // Specified repeats?
      if ($max_cat_repeats > 0) {
        $new_line .= " [$max_cat_repeats]";
      }

           


    } // else (does not contain &)

    // Add to our $rtn
    $rtn .= $new_line . "\n";
    
  } // for lines...
        
    
    
  return $rtn;
} // admin_process_catalog_repeats_for_group_courses_text






/**
 * This function lets the user edit a group.
 */
function admin_edit_group_form() {
  $form = array();
  $m = 0;
  
  $group_id = $_REQUEST["group_id"];
  $db = get_global_database_handler();
  $school_id = 0;
  if ($group_id != "new") {
    $school_id = $db->get_school_id_for_group_id($group_id, TRUE);
  }
  
  $de_catalog_year = admin_get_de_catalog_year(TRUE, $school_id);
  
  
  fp_add_css(fp_get_module_path("admin") . "/css/admin.css");
  fp_add_js(fp_get_module_path("admin") . "/js/admin.js");
    
  
  $form["#redirect"] = array(
    "path" => "admin/groups/edit-group",
    "query" => "group_id=$group_id&de_catalog_year=$de_catalog_year",
  );
  
  
  $draft_setting_current_catalog_year = variable_get_for_school("current_draft_catalog_year", 2006, $school_id);

  if ($draft_setting_current_catalog_year < $de_catalog_year) {
    
    fp_add_message(t("<b>Warning:</b> The \"Current DRAFT catalog year\" advising setting is set to %dy, which is less than the catalog year
                    you are editing. This might cause issues with loading course 
                    names and data correctly.", array("%dy" => $draft_setting_current_catalog_year)), "warning");
    $draft_setting_current_catalog_year = "<b>" . $draft_setting_current_catalog_year . "</b>"; 
  }

  
  if (user_has_permission("can_view_advanced")) {
    $form["mark" . $m++] = array(
      "type" => "markup",
      "value" => " <span class='tenpt' style='background-color: lemonchiffon; margin-left: 20px; padding: 8px;'>
          adv: group_id = $group_id.
          Used by:
          <a href='javascript: adminPopupWindow(\"" . fp_url("admin/groups/popup-show-group-use", "group_id=$group_id")  . "\");'>[degrees]</a>.
           Current draft catalog year advising setting: $draft_setting_current_catalog_year.
          </span>",
      "weight" => 0,
    );
  }

  $group = new Group($group_id, null, -1, false, true);
  //print_pre($group->to_string());
  $group->load_descriptive_data();

  fp_set_title(t("Edit Group:") . " $group->title ($de_catalog_year)");

  $form["perform_action2"] = array(
    "type" => "hidden",
    "value" => "",
  );

  $definition = '';
  if (isset($group->definition)) {
    $definition = $group->definition;
  }  
  
  $form["set_definition"] = array(
    "type" => "hidden",
    "value" => urlencode($definition),
  );

  $form["scroll_top"] = array(
    "type" => "hidden",
    "value" => "",
  );

  $form["group_id"] = array(
    "type" => "hidden",
    "value" => $group_id,
  );

  $form["de_catalog_year"] = array(
    "type" => "hidden",
    "value" => $de_catalog_year,
  );


  // Actually draw the form out.
  $form["title"] = array(
    "type" => "textfield",
    "label" => t("Group title:"),
    "value" => $group->title,
    "maxlength" => 100,
    "required" => TRUE,
    "popup_description" => t("This is what FlightPath uses to refer to this group in screens and popups.
                        Ex: Free Electives, Art Electives, Core Humanities, etc."),
    "weight" => 10,
  );

  $form["group_name"] = array(
    "type" => "textfield",
    "label" => t("Internal group machine name:"),
    "value" => $group->group_name,
    "maxlength" => 100,
    "required" => TRUE,
    "popup_description" => t("The group's <b>internal machine name</b> is internal to FlightPath, and is never seen by the average user.
                        <br>
                        You may use this to distinguish between groups with the same title. 
                        <br>Ex: <i>major_electives_sr</i> and <i>major_electives_jr</i>.  
                        <br>The field must be unique within each catalog year, and
                        consist of only letters, numbers, and underscores.  FlightPath will make
                        sure it is unique when you save, and give you a chance to change it if it
                        is not."),
    "weight" => 20,
  );


  $form["priority"] = array(
    "type" => "textfield",
    "label" => t("Priority:"),
    "value" => $group->priority,
    "maxlength" => 10,
    "size" => 10,
    "popup_description" => t("This should be a number, and it is very important, because it determines the
                        order in which courses are assigned to groups in FlightPath.
                        Higher numbers fill in FIRST!  So a group with a priority of 100 would fill in before
                        a group with a priority of 30."),
    "weight" => 30,
  );

  
  if (!$group->icon_filename) {
    $group->icon_filename = "major.gif";  // set a default if none is specified!
  }

  $form["icon_filename"] = array(
    "type" => "hidden",    
    "value" => $group->icon_filename,
    "weight" => 40,
  );
  $form['icon_filename_markup'] = array(
    "label" => t("Icon:"),
    "value" => "<img src='" . fp_theme_location() . "/images/icons/$group->icon_filename' width='19'>
                  $group->icon_filename
                  &nbsp; &nbsp;
                  <a href='javascript: adminPopupWindow(\"" . fp_url("admin/groups/popup-select-icon", "group_id=$group_id") . "\");'>[select another]</a>
                ",
    "weight" => 41,
  );

    
  $form["definition"] = array(
    "type" => "markup",
    "label" => t("Definition:"),
    "value" => "<i>" . nl2br($definition) . "</i>",
    "prefix" => "<div style='overflow: auto; max-height: 150px;' class='admin-groups-show-definition'>",
    "suffix" => "</div><a href='javascript: adminPopupWindow(\"" . fp_url("admin/groups/popup-edit-definition", "de_catalog_year=$de_catalog_year&group_id=$group_id") . "\");'
                            >[" . t("edit definition") . "]</a>",
    "weight" => 50,
  );
 

  $form["mark" . $m++] = array(
    "type" => "markup",
    "value" => "<hr>",
    "weight" => 60,
  );

  
  $courses = admin_get_group_courses($group);
  $form["courses"] = array(
    "type" => "textarea",
    "label" => t("Required Courses:"),
    "rows" => 17,
    "cols" => 80,
    "value" => $courses,
    "weight" => 70,
    "popup_description" => t("Note:  You may specify <i>hidden</i> courses by placing a - (minus sign) before the course name.
                              <br> Ex:  - ART 101
                              <br> This will cause ART 101 not to show to the user as a choice, but FlightPath will still automatically fill in ART 101
                              into this group, if the student completes it.
                              <br><br>
                              You may also <i>recommend</i> a course by place a * (asterisk) before the course name.
                              <br> Ex:  * ART 101
                              <br>This will cause <b>ART 101</b> to appear as bold to the user when viewing the list of courses in this group.
                              <br><br>
                              Courses which may be repeated can be specified by placing the number of acceptible repeats in brackets after
                              the course.
                              <br>Ex:  ART 101 [2]
                              <br>This means that ART 101 may be repeated up to two times for this group.  If using this feature, make sure
                                  that repeats are permissible for this course."),
                              
  );
  // if a definition was specified, we need to disable the courses textarea.
  if (trim($definition)) {    
    $form["courses"]["attributes"] = array("readonly"=>"readonly", "style" => "background-color: #ddd;'" );
    $form["courses"]["prefix"] = "<div class='admin-groups-courses-disabled'>
                                 " . t("Note: Because a definition was specified, you cannot directly
                                      edit the Required Courses.  Manage these courses using the
                                      Edit Definition popup window.") . "</div>";
                                 
  }
  
  $form["catalog_repeat"] = array(
    "type" => "checkbox",
    "label" => t("Set Catalog Repeats"),
    "value" => $group->db_catalog_repeat,
    "popup_description" => t("If checked, this group will automatically assign specified repeats to courses
                        which are determined to be repeatable for this catalog year.  
                        
                        For example, if you enter
                        ART 101, and in the course catalog that course may be taken two times, then
                        when you save this group the line will be changed to ART 101 [2].
                        
                        This will work with definitions, but will not work in groups with branches.
                        If you are unsure what to select, leave this box unchecked."),
    "weight" => 80,
  );

  
  $form["submit"] = array(
    "type" => "submit",
    "value" => "Submit",
    "prefix" => "<hr>",
    "weight" => 100,
  );

  
  // Advanced options
  $elements = array();     
  $bool_start_closed = TRUE;
  if (fp_trim($group->public_note) != "" || fp_trim($group->data_entry_comment) != "") {
    $bool_start_closed = FALSE;
  }
     
  $elements['public_note'] = array(
    'type' => 'textarea',
    'label' => t('Public Note'),
    'rows' => 4,
    'value' => @$group->public_note,
    'description' => t("You may enter simple HTML in this field. Ex: &lt;b&gt;bold&lt;/b&gt; or &lt;i&gt;italics&lt;/i&gt;"),
    'popup_description' => t("A public note will appear at the top of the group when displayed in the dialog popup in FlightPath."),    
    "weight" => 50010,    
  ); 

  $elements["data_entry_comment"] = array(
    "type" => "textarea",
    "label" => t("Optional Admin Comment: (only seen by other FlightPath administrators)"),
    "rows" => 3,
    "cols" => 80,
    "value" => $group->data_entry_comment,
    "weight" => 90,
  );
  
  
    
  // Place in our advanced options fs.
  $form['advanced_options_fs'] = array(
    'type' => 'cfieldset',
    'label' => t('View advanced options'),
    'elements' => array($elements),
    'start_closed' => $bool_start_closed,
    "weight" => 50100,
  ); 
    
  
  
  
    
  // Only show delete option based on permission  
  if (user_has_permission("can_delete_data_entry")) {  
    $form["mark" . $m++] = array(
      "type" => "markup",
      "value" => "<div align='right' class='groups-delete-group-wrapper'>
                        " . t("Delete this group?") . " <input type='button' value='X'
                        onClick='adminDeleteGroup(\"$group_id\");'>
                      </div>",
      "weight" => 600000,
    );
 
  }
  
  return $form;
}



function admin_display_groups_popup_edit_definition() {
  $rtn = $tdef = "";

  
  fp_add_css(fp_get_module_path("admin") . "/css/admin.css");
  fp_add_js(fp_get_module_path("admin") . "/js/admin.js");

  $group_id = trim($_REQUEST["group_id"]);
  
  $group = new Group($group_id, null, -1, false, true);
  $group->load_descriptive_data();

  $school_id = intval($group->school_id);
  
  $de_catalog_year = admin_get_de_catalog_year(TRUE, $school_id);
  
  $school_name = "";
  if ($school_id > 0) $school_name = " School-" . $school_id;
  if (module_enabled("schools")) {
    $defs = schools_get_school_definitions();
    if (isset($defs[$school_id])) $school_name = $defs[$school_id];
  }    
  
  $definition = '';
  if (isset($_REQUEST["definition"])) {
    $definition = trim($_REQUEST["definition"]);
  }
  else {
    $definition = $group->definition;
  }

  $results = admin_get_courses_from_definition($definition, "", $school_id);
  
  // This text will be used to describe how the definition works in a javascript alert box:
  $tdef .= t("Definitions provide a very quick way to add or remove many courses from a group.") . "<br><br>
          " . t("To view a complete set of instructions, <a href='https://getflightpath.com/node/11941' target='_balnk'>visit the documentation page by clicking here</a>.");  
            
  
  $rtn .= "" . t("Edit Definition for %group (%cat)
                       - @name", array("%group" => $group->title, "%cat" => $de_catalog_year, "@name" => $group->title));
  if ($school_name) {
    $rtn .= "<br><br>" . t("<strong>Note:</strong> This will only search for courses in the same school as the group <em>since its last save</em>.
                        <br><strong>Searching in school: %schoolname", array("%schoolname" => $school_name)) . "</strong>";
  }
  $rtn .= "
    <br><br><form action='" . fp_url("admin/groups/popup-edit-definition", "de_catalog_year=$de_catalog_year&group_id=$group_id") . "' method='POST' id='mainform'>
      <table border='0' width='100%'>
      <tr>
          <td valign='top' align='right' width='45%'>
            <div class='tenpt' align='left'>" . t("Working Definition:") . "
             " . fp_get_js_alert_link($tdef, "<span class='pop-q-mark'><i class='fa fa-question-circle'></i></span>") . "</div>
            <textarea name='definition' id='definition' rows='10' style='min-width:100%; resize: none;' >$definition</textarea>
            <br>
            <input type='button' value='" . t("Generate ->") . "'
              onClick='showUpdate(); document.getElementById(\"mainform\").submit();'>
              <input type='hidden' name='school_id' value='$school_id'>
        </td>
        <td valign='top' align='right' class='tenpt' width='55%'>
          <div class='tenpt' align='left'>Results:" . " (" . $results["count"] . ")</div>
            <textarea rows='10' style='min-width:100%; resize: none;' readonly=readonly class='readonly'>{$results["text"]}</textarea>
              <br>";
  if ($school_name) {
    $rtn .= "<div>" . t("loads courses from all cat years in school %schoolname", array("%schoolname" => $school_name)) . "</div>";
  }
  else {
    $rtn .= "<div>" . t("loads courses from all cat years") . "</div>";
  }
  $rtn .= "
        </td>
      </tr>
      </table>
      </form>
      " . t("If you are satisfied with the results of this definition, 
      click the Save to Group button.  To cancel, simply close this window.") . "
      
      <br>
      <input type='button' value='" . t("Save to Group") . "' onClick='adminPopupSaveDefinition(\"\");'>
  
    ";    
    
    
     
  
  // Give a little padding.
  return "<body style='padding:5px;'>$rtn</body>";
  
}




function admin_get_course_array_from_course_id_array($course_idArray) {  
  
  // Convert an array of course_id's into their subject_id ~~ course_num format.
  // Pick non-excluded courses over excluded courses, when you have the option.
  $rtn_array = array();
  $db = new DatabaseHandler();


  // MUST use foreach since we used array_unique earlier.  Can't use
  // count($arr) after you use array_unique!!
  foreach($course_idArray as $t => $value) {
    $new_course = new Course();
    $new_course->bool_use_draft = true;
    $new_course->db = $db;
    $new_course->course_id = $course_idArray[$t];
    $new_course->load_descriptive_data(false);
    array_push($rtn_array, "$new_course->subject_id ~~ $new_course->course_num");
  }
  return $rtn_array;

}




function admin_get_courses_from_definition($definition, $catalog_year = "", $school_id = 0) {
  
  if (!$definition) $definition = ''; // Make sure it's a string and not FALSE or NULL. 
  
  $group_array = array();

  // Okay, first things first, let's trim this sucker and remove extra whitespace.
  $definition = trim($definition);
  $definition = str_replace("   "," ",$definition);
  $definition = str_replace("  "," ",$definition);
  $definition = str_replace("  "," ",$definition);

  // Okay, now let's break this up into lines...
  $d_lines = explode("\n",$definition);
  foreach($d_lines as $line)
  {
    $line = trim($line);
    if ($line == "") continue;  // blank line
    
    // Let's get each of the parts... the instruction, and the course data.
    $tokens = explode(" ", $line);
    $instruction = strtolower(trim($tokens[0]));
    @$course_data = trim($tokens[1]);

    // We know that the course data can also be broken up, by the .
    $c_tokens = explode(".", $course_data);
    $subject_data = trim(strtoupper($c_tokens[0]));
    @$course_numData = trim(strtoupper($c_tokens[1]));

    if ($subject_data == "") continue;  // blank or mal-formed?
    
    // Okay, so now, for this line, we have an instruction,
    // and some course data (possibly wild cards) to act on.
    //debugCT("$instruction $subject_data $course_numData");

    $t_array = admin_get_course_array_from_definition_data($subject_data, $course_numData, $catalog_year, $school_id, TRUE);
    // Okay, we got our list.  Now what do we do with them?
    if ($instruction == "add" || $instruction == "+")
    {
      $group_array = array_merge($group_array, $t_array);
      $group_array = array_unique($group_array);
    }

    if ($instruction == "remove" || $instruction == "rem" || $instruction == "-" || $instruction == "del")
    {
      //print "<pre>" . print_r($t_array) . "</pre>";
      //debug_c_t(count($group_array));
      //$group_array = array_diff($group_array, $t_array);
      $group_array = admin_array_diff($group_array, $t_array);

      $group_array = array_unique($group_array);
      //debug_c_t(count($group_array));
    }



  }

  // Here's what we need to do:
  // In groupArray, we have the subject_id and course_num of every course in this definition.
  // We need to convert them to course_id's from the table,
  // and make sure we do not have duplicates.
  // First, get an array of course_id from the groupArray...
  $course_idArray = $group_array;
  // Take out duplicate entries (caused by eqv courses)...

  $course_idArray = array_unique($course_idArray);  
  // Now, convert BACK into the "groupArray" structure (subject_id and course_num)...
  $group_array2 = admin_get_course_array_from_course_id_array($course_idArray);

  //print_r($group_array);

  // Place in alphabetical order.
  sort($group_array2);

  //var_dump($group_array2);

  $rtn = array();
  $rtn["text"] = "";
  $count = 1;
  // Now that we have the groupArray, we will turn it into a string...
  for ($t = 0; $t < count($group_array2); $t++)
  {
    $line = trim($group_array2[$t]);
    if ($line == "~~" || $line == "") continue;
    $count++;
    $temp = explode(" ~~ ", $line);
    $si = fp_trim(@$temp[0]);
    $cn = fp_trim(@$temp[1]);

    @$rtn["text"] .= "$si $cn\n";


  }
  $rtn["text"] = str_replace("&", "_A_", $rtn["text"]);
  //debug_c_t(count($group_array));
  $rtn["count"] = $count;

  return $rtn;
}



function admin_get_course_array_from_definition_data($subject_data, $course_numData, $catalog_year = "", $school_id = 0, $bool_check_allow_default_school = FALSE) {
  
   // Looks at the subjectData and course_numData fields, and constructs
  // a query to pull our every course which matches it.

  $rtn_array = array();

  $si = str_replace("*","%",$subject_data);
  $cn = str_replace("*","%",$course_numData);

  $params = array();


  $catalog_line = "";
  if ($catalog_year != "") {
    $catalog_line = "AND catalog_year = :catalog_year";
    $params[":catalog_year"] = $catalog_year;
  }

  $school_line = " AND school_id = :school_id ";
  // Should we ALSO check the default school, in addition to whatever we specified?  Don't bother if what we specified was the default school.
  if ($bool_check_allow_default_school && module_enabled('schools') && variable_get('schools_allow_courses_from_default_school', 'yes') === 'yes' && $school_id != 0) {
    $school_line = " AND (school_id = :school_id OR school_id = 0) ";
  }  

  $params[':subject_id'] = $si;
  $params[':course_num'] = $cn;
  $params[':school_id'] = $school_id;    

  
  $query = "SELECT * FROM draft_courses
              WHERE subject_id LIKE :subject_id
              AND course_num LIKE :course_num
              AND course_id > 0
              $school_line
              $catalog_line
              GROUP BY subject_id, course_num
        ";
  $res = db_query($query, $params) ;
  while ($cur = db_fetch_array($res)) {
    $course_id = $cur["course_id"];
    
    if (in_array($course_id, $rtn_array)) continue;
    $rtn_array[] = $course_id;
    
  }

  return $rtn_array;
}



/**
 * I had to create my own version of array_diff, because the built-in PHP
 * version has a nasty bug where it doesn't work after a certain number of elements.
 */
function admin_array_diff($array1, $array2) {
  
  
  // Return an array of values from array1 that
  // are NOT in array2.
  // This is my (Richard Peacock) own implementation of array_diff,
  // because something is broken with it, so I am programming
  // my own.
  $rtn = array();
  //for ($t = 0; $t < count($array1); $t++)
  // MUST use foreach instead of for(count($arr))
  // because I did array_unique on it!
  foreach($array1 as $t => $value)
  {
    if (in_array($array1[$t], $array2))
    {
      continue;
    } else {
      $rtn[] = $array1[$t];
    }
  }
  return $rtn;

}



/**
 * This popup is called from the edit group page.  It lets the user
 * select an icon to assign to a group.
 */
function admin_display_groups_popup_select_icon() {
  $rtn = "";
  
  fp_add_css(fp_get_module_path("admin") . "/css/admin.css");
  fp_add_js(fp_get_module_path("admin") . "/js/admin.js");
    
  $group_id = $_REQUEST["group_id"];
  $group = new Group();
  $group->group_id = $group_id;
  $group->bool_use_draft = true;
  $group->load_descriptive_data();

  $rtn = "<b>Please Select an Icon to use for $group->title (<i>$group->group_name</i>):</b>
      <div class='tenpt'>Current icon: <img src='" . fp_theme_location() . "/images/icons/$group->icon_filename' width='19'>
      $group->icon_filename.
      <br><br>
      Available Icons:
        <blockquote>";
  $handle = opendir(fp_theme_location(FALSE) . "/images/icons/.");
  $files = array();


  $accepted_exts = array("jpg", "gif", "png", "bmp", "JPG", "GIF", "PNG", "BMP", "jpeg");
  while($file = readdir($handle)) {
    if ($file != "." && $file != "..") {
      // Make sure it's extension is an image file.
      // Find out it's ext first.
      $temp = explode(".", $file);
      $ext = $temp[count($temp) - 1];
      if (in_array($ext, $accepted_exts)) {
        $files[] = $file;
      }
    }
  }
  // make sure they are alphabetized.
  sort($files);


  foreach($files as $file) {
    $rtn .= "<div style='padding: 5px;'>
        <input type='button' value='Select -> ' onClick='adminPopupSelectIcon(\"$file\");' >
        &nbsp; &nbsp;
        <img src='" . fp_theme_location() . "/images/icons/$file' width='19'>
        $file</div>";
  }


  $rtn .= "</blockquote></div>";
  return $rtn;
}




/**
 * Return back the courses in a group, suitable for the edit-group form.
 */
function admin_get_group_courses(Group $group) {
  
  
  // Returns a plain text list of the courses in a group's requirements
  // for use in the edit_group_form.
  $rtn = "";

  $group_sort_policy = variable_get_for_school("group_requirement_sort_policy", "alpha", $group->school_id);

  // courses not in branches...
  $courses = array();
  $c_count = 0;
  $group->list_courses->load_course_descriptive_data();
    
  if ($group_sort_policy == 'database') {
    $group->list_courses->sort_group_requirement_id();
  }
  else {
    // By default, sort alphabetical      
    $group->list_courses->sort_alphabetical_order();
  }  
      
  $group->list_courses->reset_counter();
  while($group->list_courses->has_more())
  {
    $course_line = "";
    $attributes = "";
    $c = $group->list_courses->get_next();
    if (strstr($c->subject_id , "&"))
    {
      $c->subject_id = str_replace("&", "_A_", $c->subject_id);

    }
    
    if ($c->db_group_attributes != "") {
      $attributes = $c->db_group_attributes . " ";
    }
    
    $course_line .= trim("$attributes$c->subject_id $c->course_num");
    
    if (!$course_line) {
      // Some kind of problem, just skip this because it's blank.
      continue;
    }

    if ($c->min_grade != "" && $c->min_grade != "D")
    {
      //$rtn .= " ($c->min_grade)";
      $course_line .= " ($c->min_grade)";
    }

    //$rtn .= "\n";
    if (!isset($courses[$course_line]))
    {
      $courses[$course_line] = 0;
    }
    // This is to check for specified repeats.
    $courses[$course_line]++;

  }

  // Go through the $courses array to check for specified repeats.
  foreach($courses as $course => $rep_count)
  {
    $rep_line = " [$rep_count]";
    if ($rep_count == 1)
    {
      $rep_line = "";
    }
    $rtn .= "$course$rep_line\n";
  }



  // Now, get them branches!
  if (!$group->list_groups->is_empty)
  {
    
    $group->list_groups->reset_counter();
    while ($group->list_groups->has_more())
    {
      $courses = array();
      $g = $group->list_groups->get_next();

      $g->list_courses->load_course_descriptive_data();
      $g->list_courses->sort_alphabetical_order();
      $g->list_courses->reset_counter();
      while($g->list_courses->has_more())
      {
        $course_line = $attributes = "";
        $c = $g->list_courses->get_next();
        if (strstr($c->subject_id , "&"))
        {
          $c->subject_id = str_replace("&", "_A_", $c->subject_id);
        }


        if ($c->db_group_attributes != "") {
          $attributes = $c->db_group_attributes . " ";
        }

        $course_line = trim("$attributes$c->subject_id $c->course_num");
        if (!$course_line) {
          // Some kind of problem, just skip this because it's blank.
          continue;
        }
        
        
        if ($c->min_grade != "" && $c->min_grade != "D")
        {
          $course_line .= " ($c->min_grade)";
        }

        if (!isset($courses[$course_line]))
        {
          $courses[$course_line] = 0;
        }
        // This is to check for specified repeats.
        $courses[$course_line]++;


    
      }


      // Go through the $courses array to check for specified repeats.
      foreach($courses as $course => $rep_count)
      {
        $rep_line = " [$rep_count]";
        if ($rep_count == 1)
        {
          $rep_line = "";
        }
        $rtn .= "$course$rep_line & ";
      }
      


      // Take off the last &.
      $rtn = trim($rtn);
      $rtn = substr($rtn,0,-1);
      $rtn = trim($rtn);

      $rtn .= "\n";

    }
  }


  return $rtn;
}




/**
 * This function will display a list of all our groups.
 */
function admin_display_groups() {
  $rtn = "";
  
  // Do this using $render array, so it can be altered
  // by hook_content_alter
  
  $render = array();
  $render['#id'] = 'admin_display_groups';
  
  
  
  $de_catalog_year = admin_get_de_catalog_year();
  
  fp_add_css(fp_get_module_path("admin") . "/css/admin.css");
  fp_add_js(fp_get_module_path("admin") . "/js/admin.js");
  
  $db2 = new DatabaseHandler();

  

  fp_set_title(t("Edit Groups for @de_catalog_year", array("@de_catalog_year" => $de_catalog_year)));  
  
  
  $render['upper_links'] = array(
    'value' => "        Options:
                  <ul style='margin-top: 5px;'>          
                    <li>" . l(t("Add a new group to this year"), "admin/groups/edit-group", "de_catalog_year=$de_catalog_year&group_id=new") . "</li>
                    <li>" . l(t("Process all group definitions & catalog repeats for this year"), "admin/groups/process-all-definitions", "de_catalog_year=$de_catalog_year") . "</li>
                    </li>
                  </ul>
                    <div align='center'>" . t("Hint: use CTRL-F to search groups quickly") . "</div>
                ",
  );
    
  
  
  $html = "";
  $html .= "<div class='degrees-filter'>";
  $html .= fp_render_form("admin_groups_list_filter_form");
  $html .= "</div>";
  
  
  $render['degrees_filter'] = array(
    'value' => $html,    
  );
  
  
  

  $render['groups_table_top'] = array(
    'value' => "<table border='0' cellpadding='5' cellspacing='5' width='100%' class='admin-groups-list'>
                <tr>
                  <th>" . t("Title") . "</th>
                  <th>" . t("Internal Name") . "</th>
                  <th align='center'>Pri</th>
                  <th align='center'>i</th>
                  <th align='center'>" . t("Used") . "</th>
                </tr>",
  );
  


  $on_mouse_over = " onmouseover=\"style.backgroundColor='#FFFF99'\"
              onmouseout=\"style.backgroundColor='white'\" ";


  $params = array();
  $params[":catalog_year"] = $de_catalog_year;
  
  $filter_name_value = @trim($_SESSION['groups_filter_name']);
  $filter_school_value = @intval($_SESSION['groups_filter_school']);
  $extra_where_conditions = "";
  
  // Let's adjust the query based on filter selections (if any exist)
  if ($filter_name_value) {
    $extra_where_conditions .= " AND (group_name LIKE :name1 OR title LIKE :name2) ";
    $params[":name1"] = "%$filter_name_value%";
    $params[":name2"] = "%$filter_name_value%";
  }
  
  
  
  if (module_enabled('schools')) {
    if ($filter_school_value) {
      $extra_where_conditions .= " AND school_id = :school_id ";
      $params[":school_id"] = $filter_school_value;
    }
  }
  else {
    // schools not enabled, so only search for school = 0
      $extra_where_conditions .= " AND school_id = 0 ";
  }  
  

  watchdog("admin", "Viewed admin groups list. Name filter: $filter_name_value. ($de_catalog_year), school_id: $filter_school_value", array(), WATCHDOG_DEBUG);


  $res = db_query("SELECT * FROM draft_groups
                    WHERE catalog_year = :catalog_year
                    AND delete_flag = 0
                    $extra_where_conditions
                    ORDER BY title, group_name ", $params);
  while($cur = db_fetch_array($res)) {
    extract($cur, 3, "db");

    $use_count = 0;
    // Find out how many degree plans are using this particular group...

    $res2 = db_query("SELECT count(id) AS count FROM draft_degree_requirements
                WHERE group_id = '?' ", $db_group_id);
    if (db_num_rows($res2) > 0) {
      $cur2 = db_fetch_array($res2);
      $use_count = $cur2["count"];
    }

    $def_flag = "";
    if (trim($db_definition) != "") {
      $def_flag = " (*)";
    }

    if ($db_title == "") {
      $db_title = "[" . t("NO TITLE SPECIFIED") . "]";
    }

 
 
    $render['group_row_' . $db_group_id] = array(
      'value' => "<tr $on_mouse_over>
                    <td valign='top' ><a name='group_$db_group_id'></a>
                      " . l($db_title, "admin/groups/edit-group", "group_id=$db_group_id&de_catalog_year=$de_catalog_year") . "
                    </td>
                    <td valign='top' >
                      <i>$db_group_name</i>$def_flag
                    </td>
                    <td valign='top' >
                      $db_priority
                    </td>
                    <td valign='top' >
                      <img src='" . fp_theme_location() . "/images/icons/$db_icon_filename' width='19'>
                    </td>
                    <td valign='top' >
                      $use_count <a href='javascript: adminPopupWindow(\"" . fp_url("admin/groups/popup-show-group-use", "group_id=$db_group_id") . "\");'><i class='fa fa-window-restore'></i></a>
                    </td>
          
                    
                  </tr>
                ",
                
        'data' => array(
                    'group_id' => $db_group_id,
                    'db_row' => $cur,
                    'use_count' => $use_count,
                    'def_flag' => $def_flag,
                  ),                
                
    );
  
 
 
   
  }

  $render['groups_table_bottom'] = array(
    'value' => "</table>",
  );
  
  
  
  
  $rtn .= fp_render_content($render);
  
  
  return $rtn;
}




function admin_groups_list_filter_form() {

  $form = array();

  
  $form['mark_top'] = array(
    'type' => 'markup',
    'value' => "<strong>" . t('Filter by...') . "</strong>",
    'weight' => 0,
  );
  
  $filter_name_value = '';
  if (isset($_SESSION['groups_filter_name'])) {
    $filter_name_value = fp_trim($_SESSION['groups_filter_name']);
  }
  $form['filter_name'] = array(
    'type' => 'textfield',
    'label' => '',
    'value' => $filter_name_value,
    'attributes' => array("placeholder" => t("Enter any title or name")),
    'size' => 20,
    'weight' => 10,
  );
  
  
  // If we have enabled the schools module, then have a selector for that as well
  if (module_enabled('schools')) {
  
    $filter_school_value = @intval($_SESSION['groups_filter_school']);
    $options = schools_get_schools_for_fapi(TRUE, TRUE, 'group', TRUE);
    $options[0] = t('- All / Default -');
    $form['filter_school'] = array(      
      'type' => 'select',
      'label' => t('School:'),
      'options' => $options,
      'value' => $filter_school_value,
      'weight' => 20,   
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



function admin_groups_list_filter_form_submit($form, $form_state) {
  $values = $form_state['values'];
    
  unset($_SESSION['groups_filter_name']);
  unset($_SESSION['groups_filter_school']);
    
  if ($values['submit_btn'] != '') {
    $_SESSION['groups_filter_name'] = trim($values['filter_name']);
    $_SESSION['groups_filter_school'] = @$values['filter_school'];
  }
    
}





function admin_process_all_definitions_form() {
  $form = array();
  
  
  $de_catalog_year = admin_get_de_catalog_year();

  fp_set_title(t("Process all group definitions and settings for @year", array("@year" => $de_catalog_year)));
  $m = 0;
  
  $form["mark" . $m++] = array(
    "type" => "markup",
    "value" => t("This will cause all of the groups with definitions and other settings (ex: catalog repeats) to be cleared, and their
                  course requirements re-processed.  This function can take several minutes, depending
                  on how many groups with definitions you have."), 
  );  
      

  $form["de_catalog_year"] = array(
    "type" => "hidden",
    "value" => $de_catalog_year,
  );

  $form["submit"] = array(
    "type" => "submit",
    "spinner" => TRUE,
    "value" => t("Process groups"),
    "description" => t("Can take several minutes to run. Do not navigate away from this tab or close the browser."),
  );
     


    
  return $form;
  
}

/**
 * Actually perform the refreshing of definitions.
 */
function admin_process_all_definitions_form_submit($form, $form_submit) {
  
  $values = $form_submit["values"];
  
  $db = get_global_database_handler();
  $de_catalog_year = $values["de_catalog_year"];

  
  watchdog("admin", "Processed group definitions for: $de_catalog_year");
  
  // Okay, set up the batch....
  $batch = array(
    "operation" => array("admin_process_all_definitions_perform_batch_operation", array($de_catalog_year)),
    "title" => t("Process all group definitions & settings for %year", array("%year" => $de_catalog_year)),
    "file" => menu_get_module_path("admin") . "/admin.groups.inc",
    "progress_message" => "Processing group @current of @total",
    "display_percent" => TRUE,
  );
 
 
  // Set the batch...
  batch_set($batch);  
  
  
}



/**
 * This actually is the batch operation for processing all of our group definitions.
 */
function admin_process_all_definitions_perform_batch_operation(&$batch, $de_catalog_year) {
  
  
  ////////////////////////////  FIRST TIME ///////////////////////////////
  // If this is our first time through, let's init our values.
  if (!isset($batch["results"]["total"])) {
    // Our first time through.  Let's start up.    
    $batch["results"]["current"] = 0;
    $batch["results"]["finished"] = FALSE;    

    // Change to count how many groups with definitions OR "catalog_repeat" set.
    // We need a count of how many groups in this year have definitions to process.
    $res = db_query("SELECT count(*) as `count` FROM draft_groups
                       WHERE (definition != '' OR catalog_repeat = 1) 
                       AND catalog_year = ? 
                       AND delete_flag = 0 ", $de_catalog_year);
    $cur = db_fetch_array($res);
        
    $batch["results"]["total"] = $cur["count"];
    
  } 
  ////////////////////////////////////////////////////////////////////////  
  
  
  // Okay, we can now begin the actual batch process.
  $current = $batch["results"]["current"];
  $total = $batch["results"]["total"];

  $limit = 1;  // how many definitions to process per run of THIS function
  $c = 0;  // count of records.
  
  $db = get_global_database_handler();  
  
  // Change to count how many groups with definitions OR "catalog_repeat" set.
  // First, find every group which has a definition set.
  $res = db_query("SELECT * FROM draft_groups
                       WHERE (definition != '' OR catalog_repeat = 1) 
                       AND catalog_year = ? 
                       AND delete_flag = 0 
                       limit $current, $limit", $de_catalog_year);
  while($cur = db_fetch_array($res)) {
          
    if ($c >= $limit) {
      break;
    }
      
    
    $def = trim($cur["definition"]);
    $group_id = $cur["group_id"];
    $group_name = $cur["group_name"];
    $catalog_repeat = intval($cur['catalog_repeat']);
    $school_id = intval($cur['school_id']);

    // Only do definitions if there IS a definition.
    if ($def != "") {
      $temp = admin_get_courses_from_definition($def, "", $school_id);
      $courses = trim($temp["text"]);
    }
    else {
      // If there are no definitions, assemble a $courses string. admin_get_group_courses(Group $group)
      
      $g = new Group($group_id, null, -1, false, true);  // make sure we are loading from draft.
      $courses = admin_get_group_courses($g);
            
    }

    // If the catalog_repeat flag is set, process that.
    if ($catalog_repeat == 1) {
      $courses = admin_process_catalog_repeats_for_group_courses_text($courses, $de_catalog_year, $school_id);
    }

    $ccount = 0;

    fp_add_message(t("Working on %name", array("%name" => $group_name)));




    // We need to delete all the existing course & subgroup requirements from this group.
    // That entails first seeing what subgroups were required and deleting them,
    // then deleting the parent group's requirements.
    // First, find and delete the branches (child groups):
    $res2 = db_query("SELECT * FROM draft_group_requirements
                WHERE group_id = ?
                AND child_group_id != '0' ", $group_id);
    while ($cur2 = db_fetch_array($res2)) {
      $cg_id = $cur2["child_group_id"];
      $res22 = db_query("DELETE FROM draft_group_requirements
                  WHERE group_id = ? ", $cg_id);
    }
    // Now delete the course requirements...
    $res2 = db_query("DELETE FROM draft_group_requirements
                  WHERE group_id = ? ", $group_id);



    $lines = explode("\n", $courses);
    for ($t = 0; $t < count($lines); $t++) {
      $line = trim($lines[$t]);
      if ($line == "") { continue; }
      // Get rid of extra whitespace.
      $line = str_replace("  ", " ", $line);
      $line = str_replace("  ", " ", $line);
      $line = str_replace("  ", " ", $line);

      // Does this line contain at least one & symbol?  If it does,
      // then this is a subgroup (branch).  If not, then we can insert
      // the course as-is.
      if (!strstr($line, "&")) {
        // Did NOT contain an ampersand (&), so this goes in the
        // regular course requirements.
        $tokens = explode(" ", $line);
        $subject_id = trim($tokens[0]);
        $course_num = trim($tokens[1]);
        @$min_grade = trim($tokens[2]);
        @$course_repeats = trim($tokens[3]);

        if (strstr($min_grade, "[")) {
          // This is actually a specified repeat, not a min grade.
          $course_repeats = $min_grade;
          $min_grade = "";
        }

        $min_grade = str_replace("(","",$min_grade);
        $min_grade = strtoupper(str_replace(")","",$min_grade));

        $course_repeats = str_replace("[","",$course_repeats);
        $course_repeats = str_replace("]","",$course_repeats);
        $course_repeats--;
        if ($course_repeats < 0) { $course_repeats = 0; }

        // If the subject_id had a _A_ in it, convert this back
        // to an ampersand.
        $subject_id = str_replace("_A_", "&", $subject_id);

        
        if ($course_id = $db->get_course_id($subject_id, $course_num, "", true, $school_id, TRUE)) {
          $query = "INSERT INTO draft_group_requirements
                    (`group_id`,`course_id`,
                    `course_min_grade`,`course_repeats`,`data_entry_value`)
                    values (?, ?, ?, ?, ?) ";
          $res2 = db_query($query, $group_id, $course_id, $min_grade, $course_repeats, "$subject_id~$course_num");
          $ccount++;        
        } 
        else {
          // The course_id could not be found!

          fp_add_message(t("Course not found! You specified %course as a requirement in %gname,
                            but this course could not be found in the catalog.  It was removed from
                            the list of requirements.  Are you sure you typed it correctly? Please
                            check your spelling, and add the course again.", array("%course" => "$subject_id $course_num", "%gname" => $group_name)), "error");

        }
      } // if line does not contain &
      else {
        // ELSE, this was a branch!  Insert it as well.

        // This line DOES have an ampersand (&), so this is a sub group
        // within this group.
        // First, we need to request a new branchID for this new group.
        if (!$branch_id = $db->request_new_group_id()) {
          die ("Error.  Could not create new group (branch) ID.");
        } 
        else {
          // Add this branch to the list of requirements for this group.
          $query = "INSERT INTO draft_group_requirements
                      (group_id, child_group_id)
                      values ('?','?') ";
          $res2 = db_query($query, $group_id, $branch_id);
  
        }
        $c_tokes = explode("&",$line);
        for ($cT = 0; $cT < count($c_tokes); $cT++)
        {
          $tokens = explode(" ", trim($c_tokes[$cT]));
          $subject_id = trim($tokens[0]);
          $course_num = trim($tokens[1]);
          $min_grade = @trim($tokens[2]);
          $course_repeats = @trim($tokens[3]);
  
          if (strstr($min_grade, "[")) {
            // This is actually a specified repeat, not a min grade.
            $course_repeats = $min_grade;
            $min_grade = "";
          }
  
          $min_grade = str_replace("(","",$min_grade);
          $min_grade = str_replace(")","",$min_grade);
  
          $course_repeats = str_replace("[","",$course_repeats);
          $course_repeats = str_replace("]","",$course_repeats);
          $course_repeats--;
          if ($course_repeats < 0) { $course_repeats = 0; }
  
          // If the subject_id had a _A_ in it, convert this back
          // to an ampersand.
          $subject_id = str_replace("_A_", "&", $subject_id);
  

          if ($course_id = $db->get_course_id($subject_id, $course_num, "", true, $school_id, TRUE)) {
                      
            $query = "INSERT INTO draft_group_requirements
                      (group_id, course_id,
                      course_min_grade, course_repeats, data_entry_value)
                      values (?,?,?,?,?) ";
            $res2 = db_query($query, $branch_id, $course_id, $min_grade, $course_repeats, "$subject_id~$course_num");
          } 
          else {
            // The course_id could not be found!
            
            fp_add_message(t("Course not found! You specified %course as a requirement in %gname,
                            but this course could not be found in the catalog.  It was removed from
                            the list of requirements.  Are you sure you typed it correctly? Please
                            check your spelling, and add the course again.", array("%course" => "$subject_id $course_num", "%gname" => $group_name)), "error");
              
          }
  
        } // for $cT
      } // else it does contain &      
      
      
    } // for t < count lines

    fp_add_message(t("%name processed. %count courses added.", array("%name" => $group_name, "%count" => $ccount)) . "<br><br>");    

    

    $c++;
    
  } // while cur = db_fetch_array($res)
  

  // Update our $batch results variables
  $batch["results"]["current"] = $current + $c;
  

  if ($batch["results"]["current"] >= $total) {
    // We are finished!
    $batch["results"]["finished"] = TRUE;
    fp_add_message("<br><br><br>" . t("Group definitions have been processed for %year", array("%year" => $de_catalog_year)));
  }  
    
  
} // admin_process....batch_operation






/**
 * Displays a popup showing where a particular group is being used in FlightPath.
 */
function admin_display_groups_popup_show_group_use() {
  $rtn = "";
  
  $group_id = $_REQUEST["group_id"];
  $group = new Group();
  $group->group_id = $group_id;
  $group->bool_use_draft = true;
  $group->load_descriptive_data();

  $defs = array();
  if (module_enabled("schools")) {
    $defs = schools_get_school_definitions(TRUE);
  }


  $rtn .= "<b>" . t("Degrees (from draft table) using @group_title (%group_name):", array("@group_title"=>$group->title, "%group_name" => $group->group_name)) . "</b>
    <br><br>
    <table border='0' cellspacing='5' width='100%'>
    <tr>
      <th>" . t("Degree") . "</th>
      <th>" . t("Code") . "</th>
      <th>" . t("Block") . "</th>
      <th>" . t("Year") . "</th>
      <th>" . t("School") . "</th>
    </tr>
    ";

    
  $db = get_global_database_handler();   
    
  $res = db_query("SELECT * FROM draft_degrees a,
                    draft_degree_requirements b
                  WHERE a.degree_id = b.degree_id
                  AND b.group_id = ?
                  ORDER BY a.title, a.major_code, b.semester_num ", $group_id);
  while($cur = db_fetch_array($res))
  {
    extract($cur, 3, "db");
    
    $school_id = $db->get_school_id_for_degree_id($db_degree_id, TRUE);
    $school_code = @$defs[$school_id]['school_code'];
    if ($school_id === 0) {
      $school_code = "-";
    }
    $rtn .= "<tr>
          <td valign='top' class='tenpt' >
            $db_title
          </td>
          <td valign='top' class='tenpt' >
            $db_major_code
          </td>
          <td valign='top' class='tenpt' >
            " . ($db_semester_num + 1) . "
          </td>
          <td valign='top' class='tenpt'>
            $db_catalog_year
          </td>
          
          <td valign='top' class='tenpt'>
            $school_code
          </td>
          
        </tr>
        ";

  }


  $rtn .= "</table>";  
  
  
  
  return $rtn;
}









