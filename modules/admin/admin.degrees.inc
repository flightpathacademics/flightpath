<?php




function admin_display_degrees_popup_add_group2() {
  $semester_num = trim($_GET["semester_num"]);
  $school_id = intval($_GET["school_id"]);  
  $de_catalog_year = admin_get_de_catalog_year(TRUE, $school_id);
  
  $school_name = "";
  if ($school_id > 0) $school_name = " School-" . $school_id;
  if (module_enabled("schools")) {
    $defs = schools_get_school_definitions();
    if (isset($defs[$school_id])) $school_name = $defs[$school_id];
  }
  
  fp_add_js(fp_get_module_path("admin") . "/js/admin.js");
  
  $rtn = "";
   
  $rtn .= "<b>" . t("Add an elective group to block number: @semester_num in catalog year @de_catalog_year", array("@semester_num" => ($semester_num + 1), "@de_catalog_year" => $de_catalog_year)) . "</b><br>";
  
  if ($school_name) {
    $rtn .= "<div class='add-group-popup-school-line'><strong>" . t("Groups in School: %school", array("%school" => $school_name)) . "</strong></div>";
  }
  
  $rtn .="<span class='tenpt'>" . t("Use keyboard shortcut CTRL-F to find groups quickly.") . "</span>
        <br><br>
        " . t("First, select a group (from  @cat)", array("@cat" => $de_catalog_year));
  if ($school_name) {
    $rtn .= " " . t(" and school <b>%school</b>", array("%school" => $school_name));
    
  }
  $rtn .= ":      
        <div class='tenpt' 
          style='height:200px; overflow-y: scroll; border: 1px solid black;
          margin:5px;'>
        <table border='0' cellspacing='5'>";

  $res = db_query("SELECT * FROM draft_groups
              WHERE catalog_year = ?
              AND delete_flag = 0
              AND school_id = ?
              ORDER BY title ", $de_catalog_year, $school_id);
  while($cur = db_fetch_array($res)) {
    extract($cur, 3, "db");
    $rtn .= "<tr><td valign='middle'>
                <input type='radio' name='rgroups' value='$db_group_id'></td>
          <td valign='top' class='tenpt'>
          $db_title<br><i>$db_group_name</i>
          </td>
        </tr>
          
        "; 
  }                                                         

  $min_grades = csv_to_array(variable_get_for_school("group_min_grades", "A,B,C,D", $school_id));


  $rtn .= "</table></div>
      " . t("Next, select properties for this group:") . "
      <a href='javascript: adminPopupAlertHelp(\"group_properties\");'>?</a>
      <div class='tenpt' style='padding-top: 5px;'>
      " . t("Hrs:") . " <input type='text' name='hours' id='hours' size='2'>
      " . t("Min Grade:") . " <select name='min_grade' id='min_grade'>
              <option value=''>--</option>
              ";
  foreach ($min_grades as $g) {
    $rtn .= "<option value='$g'>$g</option>";
  }
  
  $rtn .= "              
            </select>
      Type: <select name='type' id='type'>";
  // Get our list of requirement types from our settings.
  $types = fp_get_requirement_types($school_id);
  foreach ($types as $code => $desc) {
    $rtn .= "<option value='$code'>$code - $desc</option>";
  }
  $rtn .= "
          </select> 
        &nbsp; &nbsp;
        " . fp_render_button("Add group &raquo;", "adminPopupAddGroup2(\"$semester_num\");") . "
      </div>";


  return $rtn;
}




/**
 * This form lets the user copy a degree and all of it's tracks & concentrations.
 */
function admin_copy_degree_form() {
  
  
  $de_catalog_year = admin_get_de_catalog_year();
  
  fp_set_title(t("Copy Degree for") . " $de_catalog_year");
  
  $form = array();

  $m = 0;

  $form["mark" . $m++] = array(
    "type" => "markup",
    "value" => t("Use this form to duplicate a degree plan in this catalog year."),
    "weight" => 10,
  );


  $form["de_catalog_year"] = array(
    "type" => "hidden",
    "value" => $de_catalog_year,
  );  


  $form["source_major_code"] = array(
    "type" => "textfield",
    "size" => 35,
    "label" => t("Enter the SOURCE major code you wish to copy:"),
    "required" => TRUE,
    "description" => t("Ex: ART or GBUS. Do not enter any track or concentration codes here."),
    "weight" => 20,
  );


  /* use default school_id */
  $form['school_id'] = array(
    'type' => 'hidden',
    'value' => '0',
  );

  
  

  $form["include_tracks"] = array(
    "type" => "checkboxes",
    "label" => t("Include tracks/level-3 degrees?"),
    "options" => array("yes" => "Yes, include all associated tracks (level-3 degrees)."),
    "description" => t("Check this box if you wish to also copy any tracks/level-3 degrees 
                        this major code may have associated with it. If you do not check this box, 
                        only the base degree will be copied. If the major does not have 
                        tracks/level-3 degrees, leave this unchecked."),
    "weight" => 30,                        
  );

  
  $form['destination_school_id'] = array(
    'type' => 'hidden',
    'value' => '0',
  );  
  
  $form["destination_major_code"] = array(
    "type" => "textfield",
    "size" => 35,    
    "label" => t("Enter the DESTINATION major code you wish to create/overwrite:"),
    "required" => TRUE,
    "description" => t("Ex: CHEM or XYZ. Do not enter any track or concentration codes here."),
    "weight" => 40,    
  );
  
  
  $form["allow_overwrite"] = array(
    "type" => "radios",
    "label" => t("Overwrite if major code already exists?"),
    "options" => array(
                  "no" => t("No, DO NOT delete and overwrite any existing major codes & associated tracks/level-3 degrees.  Instead, return here with a warning message."),
                  "yes" => t("Yes, if my destination major already exists, overwrite <b>and delete all associated tracks/level-3 degrees</b> for this year.  (Use caution with this option!)"),
                ),
    "value" => "no",
    "weight" => 50,    
  );
  
  
  
  // Our submit button.
  $form["submit"] = array(
    "type" => "submit",
    "value" => "Submit",
    "prefix" => "<hr>",
    "weight" => 200,    
  );
    
  
  
  return $form;
}


/**
 * Validate handler.
 * Make sure our allow_overwrite setting is working.  Check for existing major code.
 */
function admin_copy_degree_form_validate($form, $form_state) {
      
  $values = $form_state["values"];  

  $de_catalog_year = $values["de_catalog_year"];
  $destination_major_code = trim(strtoupper($values["destination_major_code"]));
  $school_id = intval($values['school_id']);
  $destination_school_id = intval($values['destination_school_id']);

  $source_major_code = trim(strtoupper($values["source_major_code"]));
  $include_tracks = @$values["include_tracks"]["yes"];  
  
  $for_school = $school_name = "";
  if (module_enabled("schools")) {
    $for_school = " for school %school";
    $source_school_name = schools_get_school_name_for_id($school_id);
    $dest_school_name = schools_get_school_name_for_id($destination_school_id);
  }  
  
  
  if (admin_string_contains_illegal_chars($destination_major_code)) {
    form_error("destination_major_code", t("Sorry, the destination major code must contain only numbers, letters, and dashes (-)."));
    return;
  }
  
  
  if ($values["allow_overwrite"] == "no") {
    // Check to see if destination major code already exists.
    
    // First thing's first.  Make sure the sourceMajorCode exists.
    $res = db_query("SELECT * FROM draft_degrees 
                      WHERE major_code = ?                      
                      AND catalog_year = ? 
                      AND school_id = ? ", $destination_major_code, $de_catalog_year, $destination_school_id) ;
    if (db_num_rows($res) != 0) {
      // Meaning, it WAS be found.
      form_error("destination_major_code", t("The destination major, %dest, was found for %year$for_school.  Since you selected not to overwrite
                        existing majors, your submission was not processed.  Please try again.", array("%dest" => $destination_major_code, "%year" => $de_catalog_year, "%school" => $dest_school_name)));
   
    }
        
  } // allow_overwrite == no
  
  
  
  // First thing's first.  Make sure the sourceMajorCode exists.
  $res = db_query("SELECT * FROM draft_degrees 
                    WHERE (major_code = ?
                    OR major_code LIKE ?)
                    AND catalog_year = ? 
                    AND school_id = ? ", $source_major_code, "$source_major_code|%", $de_catalog_year, $school_id) ;
  if (db_num_rows($res) == 0) {
    // Meaning, it could not be found.    

    form_error("source_major_code", t("The source major, %source, could not be found for %year$for_school.", array("%source" => $source_major_code, "%year" => $de_catalog_year, "%school" => $source_school_name)));
   
  }  
      
  
} // validate


/*
From: https://stackoverflow.com/questions/32138089/how-to-check-if-a-string-contains-certain-characters-using-an-array-in-php

We will use this function to make sure a major code doesn't contain any "illegal" characters.
*/
function admin_string_contains_illegal_chars($testString) {
  // These are characters we will allow:  
  static $MsgAry1 = array("a","b","c","d",
         "e","f","g","h","i","j","k","l","m","n",
         "o","p","q","r","s","t","u","v","w","x",
         "y","z","1","2","3","4","5","6","7","8",
         "9","0","-");
   
  $test = str_ireplace($MsgAry1, '', $testString);
  
  if (strstr($test, " ")) return TRUE; // contains an illegal character!
  
  // test should be empty if we only used valid chars!
  if (trim($test) == "") return FALSE;
  
  return TRUE;
  
}



function admin_copy_degree_form_submit($form, &$form_state) {
    
  global $db;
  
  $values = $form_state["values"];

  $de_catalog_year = filter_plain($values["de_catalog_year"]);  
  $school_id = intval($values['school_id']);
  $destination_school_id = intval($values['destination_school_id']);
    
  $source_major_code = filter_plain(strtoupper($values["source_major_code"]));
  $destination_major_code = filter_plain(strtoupper($values["destination_major_code"]));
  $include_tracks = @$values["include_tracks"]["yes"];
  


  // Alright, if we got to here, we can proceed.  We need to 
  // delete everything involving the destination major.
  // First, get the degree_id's in a select...
  $res = db_query("SELECT * FROM draft_degrees 
                    WHERE (major_code = ?
                    OR major_code LIKE ?)
                    AND catalog_year = ? 
                    AND school_id = ? ", $destination_major_code, "$destination_major_code|%", $de_catalog_year, $destination_school_id ) ;
  if (db_num_rows($res) > 0) {
    while ($cur = db_fetch_array($res)) {
      $degree_id = $cur["degree_id"];
      $res2 = db_query("DELETE FROM draft_degree_requirements
                           WHERE degree_id='?' ", $degree_id) ;
      
      $res2 = db_query("DELETE FROM draft_degrees
                           WHERE degree_id = '?' ", $degree_id) ;      
    }
    // Now, delete the tracks.
    $res2 = db_query("DELETE FROM draft_degree_tracks
                          WHERE major_code = ? 
                          AND catalog_year = ? 
                          AND school_id = ?", $destination_major_code, $de_catalog_year, $destination_school_id) ;
  }

  // Okay, with the destination major good and deleted, we can proceed with
  // the copy.
  
  // Let's build up an array of all the degrees we will be copying.
  $source_array = array();
  // First, the base degree...
  $res = db_query("SELECT * FROM draft_degrees 
                    WHERE major_code = ?
                    AND catalog_year= ? 
                    AND school_id = ? ", $source_major_code, $de_catalog_year, $school_id) ;
  $cur = db_fetch_array($res);
  $source_array[] = $cur;
  
  // Now, any tracks or concentrations?
  if ($include_tracks == "yes") {
    $res = db_query("SELECT * FROM draft_degrees 
                      WHERE major_code LIKE ?
                      AND catalog_year = ? 
                      AND school_id = ? ", "$source_major_code|%", $de_catalog_year, $school_id) ;
    while ($cur = db_fetch_array($res)) {
      $source_array[] = $cur;
    }

    // While we're here, let's go ahead and make a copy of the tracks.
    $res = db_query("SELECT * FROM draft_degree_tracks
                        WHERE (major_code = ?
                        OR major_code LIKE ? )
                        AND catalog_year = ?
                        AND school_id = ? ", $source_major_code, "$source_major_code|%", $de_catalog_year, $school_id) ;
    while($cur = db_fetch_array($res)) {
      extract($cur, 3, "db");
      $dest_code = $destination_major_code;
      if (strstr($db_major_code, "|")) {
        // We need to adjust the destCode to match
        //the source.
        $dest_code = str_replace("$source_major_code|", "$destination_major_code|", $db_major_code);
      }
      
      $res2 = db_query("INSERT INTO draft_degree_tracks
                          (catalog_year, major_code, track_code, 
                          track_title, track_short_title, track_description, school_id)
                          VALUES
                          (?, ?, ?, ?, ?, ?, ?) ",
                          $de_catalog_year, $dest_code, $db_track_code, 
                          $db_track_title, $db_track_short_title, 
                          $db_track_description, $destination_school_id) ;
                          
    }       
  }
  
  $db = get_global_database_handler();
  
  // Okay, now it's time to go through the sourceArray
  // and duplicate them.
  foreach ($source_array as $src) {
    extract($src, 3, "src");
    
    $dest_code = $destination_major_code;
    if (strstr($src_major_code, "|")) {
      // We need to adjust the destCode to match
      //the source.
      $dest_code = str_replace("$source_major_code|", "$destination_major_code|", $src_major_code);
    }

    //var_dump($dest_code);
    $dest_degree_id = $db->request_new_degree_id();

    // Let's save our src_degree_id and dest_degree_id in $form_state, for other possible modules to use.
    $form_state['degrees'][] = array(
      'src' => $src_degree_id,
      'src_catalog_year' => $de_catalog_year,
      'school_id' => $school_id,
      'dest' => $dest_degree_id,
      'dest_major_code' => $dest_code,
      'dest_catalog_year' => $de_catalog_year,
    );

    // Create the entry in the degrees table.
    $res = db_query("INSERT INTO draft_degrees
                        (degree_id, major_code, degree_type, degree_level, degree_class, title,
                         public_note, semester_titles_csv,
                         catalog_year, exclude, allow_dynamic, advising_weight, override_degree_hours,
                         min_tracks, max_tracks, default_tracks, track_selection_config, school_id)
                         VALUES   
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ",
                         $dest_degree_id, $dest_code, $src_degree_type, $src_degree_level, $src_degree_class, $src_title,
                         $src_public_note, $src_semester_titles_csv,
                         $de_catalog_year, $src_exclude, $src_allow_dynamic, $src_advising_weight, $src_override_degree_hours,
                         $src_min_tracks, $src_max_tracks, $src_default_tracks, $src_track_selection_config, $destination_school_id);

    // Now, go through the source's degree requirements and copy those over.
    $res = db_query("SELECT * FROM draft_degree_requirements
                         WHERE degree_id = ?", array($src_degree_id));
    while ($cur = db_fetch_array($res)) {
      extract($cur, 3, "db");

      $res2 = $db->db_query("INSERT INTO draft_degree_requirements
                          (degree_id, semester_num, group_id,
                           group_requirement_type,
                           group_hours_required,
                           group_min_hours_allowed,
                           group_min_grade, course_id,
                           course_min_grade,
                           course_requirement_type,
                           data_entry_value)
                           VALUES
                          (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ",
                            $dest_degree_id, $db_semester_num, $db_group_id,
                           $db_group_requirement_type,
                           $db_group_hours_required,
                           $db_group_min_hours_allowed,
                           $db_group_min_grade, $db_course_id,
                           $db_course_min_grade,
                           $db_course_requirement_type,
                           $db_data_entry_value);
           
    }  
    
  }
  
  // Make a - entry into the draft_instruction table so it will
  // remind the administrator to apply draft changes.
  $res = db_query("INSERT INTO draft_instructions
                        (instruction) VALUES ('-') ");
   

  $for_school = $school_name = "";
  if (module_enabled("schools")) {
    $for_school = " for school %school";
    $school_name = schools_get_school_name_for_id($destination_school_id);
  }     
  
  watchdog("admin", "Copied degree $source_major_code to $destination_major_code,school id: $school_id, ($de_catalog_year) successfully. 
                      Source degree id: $src_degree_id.  New degree id = $dest_degree_id");
  
   
  fp_add_message(t("Degree %source has been copied to %dest for %year$for_school.",
                  array("%source" => $source_major_code, "%dest" => $destination_major_code, "%year" => $de_catalog_year, "%school" => $school_name)));
                   
                    
}



/**
 * This form lets the user add a degree to the database. 
 */
function admin_add_degree_form() {
  
        
  $de_catalog_year = admin_get_de_catalog_year();
  
  fp_set_title(t("Add Degree for") . " " . $de_catalog_year);
  
  $form = array();
  $m = 0;
  
  $form["markup" . $m++] = array(
    "type" => "markup",
    "value" => t("You may use this screen to add a new degree,
                  by entering a new degree code (Level 1 or 2) or <em>track</em> (Level-3 degree) code.
                  <br><br><em>Note: For simplicity, FlightPath refers to Level-1 and -2 degree codes as \"major codes\" and Level-3 degree codes as \"track codes\".</e>"),
  );
  
  $bool_legacy_concentrations = variable_get("enable_legacy_concentrations", FALSE);
  $legacy_text = "";
  if ($bool_legacy_concentrations) {
    $legacy_text = " [and concentration] ";
  }

  $form["de_catalog_year"] = array(
    "type" => "hidden",
    "value" => $de_catalog_year,
  );  

  $form["new_major"] = array(
    "type" => "radios",
    "label" => t("Are you entering a new major code $legacy_text,<br> 
                  or an existing major code $legacy_text (so you can add a new track)?"),
    "options" => array(
      "new" => t("Entering a <b>new</b> major code"),
      "existing" => t("Entering an <b>existing</b> major code (only adding a new Level-3 track)"),
    ),
    "required" => TRUE,
    "weight" => 10,    
  );

  
  
  $desc = "Enter the Level-1 or -2 Major Code here.  
           Do not enter any Level 3 codes (ex: Tracks or Concentrations).
           <br>For example, enter ACCT or ENGL.  <i><b>But not ENGL|_TRKA, etc</b></i>";
  if ($bool_legacy_concentrations) {
    $desc = "To enter a concentration code, use MAJOR|CONC. 
                      The | character is call the pipe, and it should under the backspace key. 
                      If adding a new concentration to an existing major, you still put this in as a 
                      NEW major code. Do not have any spaces in this box. The concentration code 
                      is optional. If the major does not have a concentration, 
                      then simply enter the major code by itself.  The combined major code and optional track
                      code can only be 18 characters maximum.";
  }
  
  $form["major_code"] = array(
    "type" => "textfield",
    "size" => 15,
    "label" => t("Level-1 or -2 Major Code:"),
    "description" => t($desc),
    "required" => TRUE,
    "weight" => 20,
    "description" => t("Enter only letters, numbers, or dash (-).  NO other symbols of any kind."),
  );


  $form["new_track"] = array(
    "type" => "radios",
    "label" => t("Are you entering a new Level-3 track code?"),
    "options" => array(
      "new" => t("Entering a <b>new</b> track code"),
      "none" => t("None - Not adding a track.  Leave blank"),
    ),
    "required" => TRUE,
    "weight" => 30,
  );


  $form["track_code"] = array(
    "type" => "textfield",
    "size" => 15,
    "label" => t("Level-3 Track code:"),
    "description" => t("Leave blank if you selected None above.  This code must not contain spaces, 
                        and combined with the major code it must not exceed 18 characters."),
    "weight" => 40,       
    "description" => t("Enter only letters, numbers, or dash (-).  NO other symbols of any kind."),                 
  );



  // Our submit button.
  $form["submit"] = array(
    "type" => "submit",
    "value" => "Submit",
    "prefix" => "<hr>",
    "weight" => 999,
  );
  
  
  return $form;
   
}


/**
 * Validate handler for add_degree_form
 */
function admin_add_degree_form_validate($form, $form_submit) {
  $values = $form_submit["values"];
  
  // Make sure neither major code nor track code contains any spaces.
  $de_catalog_year = $values["de_catalog_year"];
  $major_code = trim(strtoupper($values["major_code"]));
  $track_code = trim(strtoupper($values["track_code"]));
  $school_id = intval(@$values["school_id"]);

  $new_major = $values["new_major"];
  $new_track = $values["new_track"];
  
   
      
      
  if ($major_code != "" && admin_string_contains_illegal_chars($major_code)) {
    form_error("major_code", t("The major code may not contain spaces or symbols.  Please enter a new one containing only letters, numbers, and -."));
   
  }
  if ($track_code != "" && admin_string_contains_illegal_chars($track_code)) {
    form_error("track_code", t("The track code may not contain spaces or symbols.  Please enter a new one containing only letters, numbers, and -."));
   
  }      

    // Make sure the major + |_ + track_code doesn't exceed 20 characters.
  if (strlen("$major_code|_$track_code") > 20) {
    form_error("major_code", t("The major and track code combined may not exceed 18 characters.  Please shorten one or the other."));
   
  }
       
  if ($new_track == "new" && $track_code == "") {   
    form_error("track_code", t("You selected to add a track, but did not specify a track code.")); 
  }


  $for_school = $school_name = "";
  if (module_enabled("schools")) {
    $for_school = " for school %school";
    $school_name = schools_get_school_name_for_id($school_id);
  }


  // Check to see if it already exists...
  $res = db_query("SELECT * FROM draft_degrees
          WHERE catalog_year = ?
          AND major_code = ? 
          AND school_id = ?", $de_catalog_year, $major_code, $school_id);
  if (db_num_rows($res) > 0 && $new_major == "new") { 
    // Meaning, it already exists, yet we are trying to add it as a new
    // major.  This is an error!
    
    form_error('major_code', t("The major code %major_code already exists for %year$for_school.  You cannot add it as a new major.", array("%major_code" => $major_code, "%year" => $de_catalog_year, "%school" => $school_name)));
    
    
  }
  if (db_num_rows($res) == 0 && $new_major == "existing") { 
    // This is another error.  We are trying to add a track to an existing
    // major code, but none was found.
    form_error('major_code', t("The major code %major_code could not be found in the system for %year$for_school.  Perhaps you need to add it first?", array("%major_code" => $major_code, "%year" => $de_catalog_year, "%school" => $school_name)));    
    return;
  }



          
  
}


/**
 * Submit handler for the add_degree_form.
 */
function admin_add_degree_form_submit($form, $form_submit) {
  $values = $form_submit["values"];

  $degree_id = -1;

  $de_catalog_year = filter_plain($values["de_catalog_year"]);
  // This will be used to add a new degree (and possibly track)
  // to the database.
  $major_code = filter_plain(strtoupper($values["major_code"]));
  $track_code = filter_plain(strtoupper($values["track_code"]));
  $school_id = intval(@$values["school_id"]);
  
  $new_major = filter_plain($values["new_major"]);
  $new_track = filter_plain($values["new_track"]);

  $allow_dynamic = variable_get("admin_degrees_default_allow_dynamic", "1");


  $for_school = $school_name = "";
  if (module_enabled("schools")) {
    $for_school = " for school %school";
    $school_name = schools_get_school_name_for_id($school_id);
  }


  // First, deal with the major/concentration.
  if ($new_major == "new") {
    // This means we are trying to add a new major to the degrees table.
    // We may proceed with this.
    $db2 = new DatabaseHandler();
    $degree_id = $db2->request_new_degree_id();
    $db2->db_query("INSERT INTO draft_degrees
            (degree_id, major_code, catalog_year, allow_dynamic, title, school_id)
            values (?, ?, ?, ?, ?, ?) ", $degree_id, $major_code, $de_catalog_year, $allow_dynamic, $major_code, $school_id);
  }


  if ($new_track == "new") {
    //////////////////////////////////////////////////
    // Now, let's see about adding ourself a track...
    // First, check to see if it exists...
    $res = db_query("SELECT * FROM draft_degree_tracks
          WHERE catalog_year = ?
          AND major_code = ? 
          AND track_code = ? ", $de_catalog_year, $major_code, $track_code);  

    if (db_num_rows($res) > 0) {
      // Meaning, it already existed, so we can't create it.      
      fp_add_message(t("The major and track code %major_code already exists for %year.  You cannot add it as a new major/track code.", array("%major_code" => "$major_code $track_code", "%year" => $de_catalog_year)), "error");
      return;      
      
    } 
    else {
      // We can add it to the tracks table...
      $db2 = new DatabaseHandler();
      $db2->db_query("INSERT INTO draft_degree_tracks
              (catalog_year, major_code, track_code)
              values ('?', '?', '?') ", $de_catalog_year, $major_code, $track_code);

      // Now, we also need to add this major & track code to the degrees table.
      $new_major_code = $major_code;
      if (strstr($major_code, "|")) {
        // Already has a pipe, so it has a concentration.
        $new_major_code .= "_$track_code";
      } 
      else {
        // No concentration...
        $new_major_code .= "|_$track_code";
      }

      $degree_id = $db2->request_new_degree_id();
      $db2->db_query("INSERT INTO draft_degrees
            (degree_id, major_code, catalog_year, allow_dynamic, title, school_id)
            values (?, ?, ?, ?, ?, ?) ", $degree_id, $new_major_code, $de_catalog_year, $allow_dynamic, $new_major_code, $school_id);

    }

  }


  // Let's add some default semester blocks for this degree (0 - 3).
  if ($degree_id > 0) {
    for ($t = 0; $t <= 3; $t++) {
      db_query("INSERT INTO draft_degree_requirements
                (degree_id, semester_num, course_id, course_min_grade, course_requirement_type, data_entry_value)
                values (?,?,?,?,?,?) ",  $degree_id, $t, 0, '', '', "#");
    }
    
  }

 
  watchdog("admin", "Degree $major_code $track_code,school id: $school_id, ($de_catalog_year) has been created successfully. Degree id = $degree_id");
 
  // Success!  We are done.
  fp_add_message(t("The new degree %major_code was added successfully for %year$for_school.  
                      You may add another degree, or use the menu at the top of the page to return to your list
                      of degrees, so you may begin editing the degree.", array("%major_code" => "$major_code $track_code", "%year" => $de_catalog_year, "%school" => $school_name)));
  
  clear_session_form_values("admin_add_degree_form");
  
}


/*
 * This function lists all of our degrees on the screen, for a given year
 */
function admin_display_degrees() {
  
  // Do this using $render array, so it can be altered
  // by hook_content_alter
  
  $render = array();
  $render['#id'] = 'admin_display_degrees';
  
  $de_catalog_year = admin_get_de_catalog_year();
  $rtn = "";    
    
  fp_add_css(fp_get_module_path("admin") . "/css/admin.css");

  
    
  fp_set_title(t("Degrees for @year", array("@year" => $de_catalog_year)));

    
  

  $render['upper_links'] = array(
    'value' => "<div class='admin-degrees-upper-links'>  
                " .  l("Add new degree plan (major, degree plan, or track)", "admin/degrees/add-degree", "de_catalog_year=$de_catalog_year") . "
                &nbsp; &nbsp; | &nbsp; &nbsp;
                " . l("Copy a degree plan", "admin/degrees/copy-degree", "de_catalog_year=$de_catalog_year") . "</div>",
  );

  
  
  
  
  $html = "";
  $html .= "<div class='degrees-filter'>";
  $html .= fp_render_form("admin_degrees_list_filter_form");
  $html .= "</div>";
  
  
  $render['degrees_filter'] = array(
    'value' => $html,    
  );
  
  
    
  $render['degrees_table_top'] = array(
    'value' => "<table class='degrees-table' border='0' width='100%' cellpadding='5' cellspacing='0'>
                  <tr>
                    <th width='8%'>Type</th>
                    <th width='8%'>Level</th>
                    <th width='15%'>Class</th>
                    <th width='60%'>Title</th>
                    <th>Code</th>
                  </tr>",
  );
  

  
  
  $params = array();
  $params[":catalog_year"] = $de_catalog_year;
  
  $filter_class_value = @$_SESSION['degrees_filter_class'];
  $filter_level_value = @$_SESSION['degrees_filter_level'];
  $filter_school_value = @intval($_SESSION['degrees_filter_school']);
  $extra_where_conditions = "";
    
  watchdog("admin", "Viewed admin degree list. Class option: $filter_class_value ($de_catalog_year), School id: $filter_school_value", array(), WATCHDOG_DEBUG);
  
  // Let's adjust the query based on filter selections (if any exist)
  if ($filter_class_value) {
    $extra_where_conditions .= " AND degree_class = :degree_class ";
    $params[":degree_class"] = $filter_class_value;
  }
  
  if ($filter_level_value) {
    $extra_where_conditions .= " AND degree_level = :degree_level ";
    $params[":degree_level"] = $filter_level_value;
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
  
    
  $res = db_query("SELECT * FROM draft_degrees
                    WHERE catalog_year = :catalog_year
                    $extra_where_conditions
                    ORDER BY degree_type, major_code, title ", $params);

  while($cur = db_fetch_array($res)) {

    $db_exclude = 0;
    extract($cur, 3, "db");

    $disp_level = strtoupper($db_degree_level);

    if ($db_degree_type == "NA" && strstr($db_major_code, "|")) {
      $db_degree_type = " -- ";
    }

    $extra_class = "";
    if ($db_exclude == "1") {
      $extra_class = "list-degree-row-excluded";
    }
    $degree_title = $db_title;
    
    // get JUST the major code...
    $temp = explode("|", $db_major_code);
    $just_major = trim((string) @$temp[0]);
    $just_conc = trim((string) @$temp[1]);
    $outside = "";

    //if ($just_conc != "" && strstr($just_conc, "_"))
    if (strstr($just_conc, "_")) {      
      // If the concentration has an underscore, it's actually
      // a track.  Let's get the track title...
      $temp2 = explode("_",$just_conc);
      $just_track = trim($temp2[1]);
      // Might need to add the first part BACK onto the major...
      if (trim($temp2[0]) != "")
      {
        $just_major .= "|" . trim($temp2[0]);
      }
      

      $res2 = db_query("SELECT * FROM draft_degree_tracks
                WHERE catalog_year = ?
                AND major_code = ?                
                AND track_code = ?
                AND school_id = ? ", $de_catalog_year, $just_major, $just_track, $db_school_id);
      if (db_num_rows($res2) > 0) {
        $cur2 = db_fetch_array($res2);

        $db_title = trim($cur2["track_title"]);
        $outside = "$degree_title &raquo; ";
        if (strstr($just_major, "|")) { 
          // both a conc AND a track. Denote it special.
          $outside = ">>" . $outside;
        }
        $db_degree_type = "";
      }
    } 
    else if($just_conc != "") {
      // Meaning, this is a concentration, NOT a track.
      $db_degree_type = "";
      $outside = "&gt;&gt;";
    }

        
    $url = fp_url("admin/degrees/edit-degree/$db_degree_id/$de_catalog_year", "de_catalog_year=$de_catalog_year", array("class" => "degree-$db_degree_class"));
    
            
    $class_details = fp_get_degree_classification_details($db_degree_class);
    $disp_class = $class_details["title"];            

    $on_mouse_over = "
            onmouseover='$(this).addClass(\"selection_highlight\");'
            onmouseout='$(this).removeClass(\"selection_highlight\");'
          ";      
      

    $render['degree_row_' . $db_degree_id] = array(
      'value' => "<tr
                  
                  $on_mouse_over
                  
                  style='cursor:pointer;'
                  onClick='window.location=\"$url\"'
                  class='$extra_class class-$db_degree_class class-level-" . $class_details["level_num"] . " '
             >
              <td valign='top' class='degree-type'>$db_degree_type</td>
              <td valign='top' class='degreee-level'>$disp_level</td>
              <td valign='top' class='degreee-class'>$disp_class</td>
              <td valign='top' class='degree-title'>$outside$db_title</td>
              <td valign='top' class='degree-major-code'>$db_major_code</td>    
            </tr>
                ",
                
        'data' => array(
                    'degree_id' => $db_degree_id,
                    'major_code' => $db_major_code,
                    'title' => $db_title,
                    'catalog_year' => $db_catalog_year,
                    'degree_type' => $db_degree_type,
                    'degree_class' => $db_degree_class,
                    'db_row' => $cur,
                  ),                
                
    );


  }    
 
 
  
  //$rtn .= "</table>";
 
  $render['degrees_table_bottom'] = array(
    'value' => "</table>",
  );
  
  
  
  
  $rtn .= fp_render_content($render);
  
  
  return $rtn;
    
}


function admin_degrees_list_filter_form() {
  $form = array();
  
  $form['mark_top'] = array(
    'type' => 'markup',
    'value' => "<strong>" . t('Filter by...') . "</strong>",
    'weight' => 0,
  );
  
  $filter_class_options = array('' => t("- All -"));  
  $degree_classes = fp_get_degree_classifications();
  foreach ($degree_classes["levels"] as $level => $details) {
    foreach ($degree_classes["levels"][$level] as $machine_name => $title) {
      $filter_class_options[$machine_name] = "$level - $title";  
    }
  }
      
  $filter_class_value = @$_SESSION['degrees_filter_class'];
  
  $form['filter_class'] = array(
    'type' => 'select',
    'label' => t('Class:'),
    'options' => $filter_class_options,
    'value' => $filter_class_value,
    'hide_please_select' => TRUE,
    'weight' => 10,
  );
  
  
  $filter_level_options = array('' => t("- All -"), "UG" => t("Under Grad"), "GR" => t("Grad"));
  $filter_level_value = @$_SESSION['degrees_filter_level'];
  $form['filter_level'] = array(
    'type' => 'select',
    'label' => t('Level:'),
    'options' => $filter_level_options,
    'value' => $filter_level_value,
    'hide_please_select' => TRUE,
    'weight' => 15,
  );  
  
  
  $filter_school_value = 0;
  // If we have enabled the schools module, then have a selector for that as well
  if (module_enabled('schools')) {
    $filter_school_value = @intval($_SESSION['degrees_filter_school']);
    $options = schools_get_schools_for_fapi(TRUE, TRUE, 'degree', TRUE);
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




function admin_degrees_list_filter_form_submit($form, $form_state) {
  $values = $form_state['values'];
    
  unset($_SESSION['degrees_filter_class']);
  unset($_SESSION['degrees_filter_school']);
  unset($_SESSION['degrees_filter_level']);
    
  if ($values['submit_btn'] != '') {
    $_SESSION['degrees_filter_class'] = @$values['filter_class'];
    $_SESSION['degrees_filter_school'] = @$values['filter_school'];
    $_SESSION['degrees_filter_level'] = @$values['filter_level'];
  }
    
}










/**
 * Meant to replace the old-fashioned display_edit_degree function...
 */
function admin_edit_degree_form($degree_id, $de_catalog_year) {
  $form = array();
  
  $degree = new DegreePlan($degree_id, null, false, false, true);
  $degree->load_descriptive_data();  
  $school_id = $degree->school_id;
  $major_code = $degree->major_code;
    
  fp_set_title(t("Edit Degree") . " $major_code - $de_catalog_year");

 
  // Add in our CSS and JS
  fp_add_js(fp_get_module_path("admin") . "/js/admin.js");
  fp_add_css(fp_get_module_path("admin") . "/css/admin.css");

  $button_msg = fp_trim(addslashes((string) @$_REQUEST["button_msg"]));  
  
  $db = get_global_database_handler();

  
  $draft_setting_current_catalog_year = variable_get_for_school("current_draft_catalog_year", 2006, $school_id);

  if ($draft_setting_current_catalog_year < $de_catalog_year) {
    
    fp_add_message(t("<b>Warning:</b> The \"Current DRAFT catalog year\" advising setting is set to %dy, which is less than the catalog year
                    you are editing. This might cause issues with loading course 
                    names and data correctly.", array("%dy" => $draft_setting_current_catalog_year)), "warning");
    $draft_setting_current_catalog_year = "<b>" . $draft_setting_current_catalog_year . "</b>"; 
  }
  
  if (user_has_permission("can_view_advanced")) {
    $form["markup_adv"] = array(
      "value" => "<span class='tenpt deg-advanced-msg' style='background-color: lemonchiffon; margin-left: 20px; padding: 8px;'>
          Advanced: degree_id = $degree_id. catalog_year: $de_catalog_year. Current draft catalog year advising setting: $draft_setting_current_catalog_year.
          </span>",
    ); 
  }


  $form["degree_id"] = array(
    "type" => "hidden",
    "value" => $degree_id,
  );
  
  $form["major_code"] = array(
    "type" => "hidden",
    "value" => $major_code,
  );
  
  $form["de_catalog_year"] = array(
    "type" => "hidden",
    "value" => $de_catalog_year,
  );
  
  $form["scroll_top"] = array(
    "type" => "hidden",
    "value" => '',
  );

  $form["perform_action2"] = array(
    "type" => "hidden",
    "value" => '',
  );
  
      
  
  $form["markup_degree_header"] = array(
    "value" => "<h3>$degree->degree_type $degree->title<br>$major_code ($de_catalog_year)</h3>",
    "weight" => 1010,
  );
  
  //////////////////////////////////////////////////////  
  // Top part of degree entry....
  $form["degree_type"] = array(
    "type" => "textfield",
    "label" => t("Degree Type:"),
    "size" => 3,
    "value" => $degree->degree_type,
    "popup_description" => t("For example: BS, BA, Ph.D, etc."),
    "weight" => 1020,    
  );
  
  // Display degree_class pulldown.
  $options = array();
  $degree_classes = fp_get_degree_classifications();
  foreach ($degree_classes["levels"] as $level => $details) {
    foreach ($degree_classes["levels"][$level] as $machine_name => $title) {
      //$rtn .= "<option value='$machine_name' $sel>$level - $title</option>";
      $options[$machine_name] = "$level - $title";
    }
  }          
  
  $form["degree_class"] = array(
    "type" => "select",
    "label" => t("Classification:"),
    "options" => $options,
    "value" => $degree->degree_class,
    "hide_please_select" => TRUE,
    "popup_description" => t("Select which classification this degree plan is listed under.  The number beside the title is the 'level' of the degree classification.
                            <br>
                            <br>1 - Degrees which a student may graduate in. Ex: Majors.
                            <br>2 - Degree plans which are secondary to top level degree plans. Ex: Minors.
                            <br>3 - Degree plans which are only selectable as add-ons to an existing degree plan. Ex: Concentrations.
                            <br><br>
                            You may edit these classifications in the Configure School Data form, from the Admin Console."), 
    "weight" => 1030,    
  );
  
  
  $form["degree_level"] = array(
    "type" => "select",
    "label" => t("Level:"),
    "size" => 1,
    "options" => array('UG' => 'UG - Under Grad', 'GR' => 'GR - Grad'),
    "value" => $degree->degree_level,
    "hide_please_select" => TRUE,
    "popup_description" => t("Select the degree level code in this box.  By default, it is assumed to be an \"Undergraduate\" (UG) degree.
                              
                              Select \"GR\" for a degree which should only be accessible to Graduate students in What If mode.                              
                              "),
    "weight" => 1040,    
  );
    
  
  $form["advising_weight"] = array(
    "type" => "textfield",
    "label" => t("Advising Weight:"),
    "size" => 1,
    "value" => $degree->db_advising_weight,
    "popup_description" => t("If this degree is ever combined with another degree, this value, the advising weight, determines
                              the order in which the degrees will be displayed on the View tab and in What If mode.
                              <br><br>
                              The default is zero (0).  To place this degree above normal degrees, set it to a negative value (ex: -1, -10, etc.).
                              <br><br>
                              Set it higher to place it below normal degrees.  (Ex: 10, 20, 30, etc).
                              <br><br>
                              To prevent confusion, it's recommended that a degree's tracks have the same advising weight as the main degree.
                              <br><br>
                              If you are unsure what to enter, leave it set to 0 (zero)."),
    "weight" => 1050,    
  );
  
  
  $form["title"] = array(
    "type" => "textfield",
    "label" => t("Title:"),
    "size" => 80,
    "maxlength" => 100,
    "value" => $degree->title,
    "popup_description" => t("This is the full title of the degree.  Ex: Computer Science, Accounting, etc."),
    "weight" => 1060,    
  );

  $form["exclude"] = array(
    "type" => "textfield",
    "label" => t("Exclude:"),
    "size" => 1,
    "value" => intval($degree->db_exclude),
    "popup_description" => t("If the Exclude value is set to 1 (the number one), then this degree will show up in gray on the list of degrees.  It will also not be selectable in What If mode in FlightPath.
                              <br><br>
                              If you are not sure what to enter, either leave it blank or enter a 0 (zero)."),
    "weight" => 1070,    
  );
  
  $form["allow_dynamic"] = array(
    "type" => "textfield",
    "label" => t("Allow Dynamic:"),
    "size" => 1,
    "value" => intval($degree->db_allow_dynamic),
    "popup_description" => t("If this value is set to 1 (the number one), then this degree plan is able to be combined with other degrees which are also set to Allow Dynamic.
                              <br><br>
                              If you are unsure what to enter, either leave blank or enter a 0 (the number zero)."),
    "weight" => 1080,    
  );
    
    
  $form["override_degree_hours"] = array(
    "type" => "textfield",
    "label" => t("Override Degree Hours:"),
    "size" => 1,
    "value" => $degree->db_override_degree_hours,
    "popup_description" => t("You may specify a number of degree hours here (ex: 125).  FlightPath will use this value, instead of calculating the number of hours
                              required for this degree.  If you are unsure what to put here, leave it blank, or enter zero, for FlightPath to calculate hours normally."),
    "weight" => 1090,    
  );

  $form['below_degree_details_markup'] = array(
    'value' => "<div style='clear: both;'></div>",
    "weight" => 1110,
  );

  
  
  // Is this a "track"?
  if (strstr($major_code, "_"))
  {
     
    $form['track_info_markup'] = array(
      'value' => "<h3>" . t("Track (Level-3) Information") . "</h3>",
    "weight" => 1120,
    );
            
    $form['track_title'] = array(
      'type' => 'textfield',
      'label' => t("Track (Level-3) Title:") . "&nbsp;",
      'size' => 60,
      'maxlength' => 100,
      'value' => $degree->track_title,
    "weight" => 1130,
    );
    
    $form['track_description'] = array(
      'type' => 'textarea',
      'label' => t("Track (Level-3) Description:"),
      'rows' => 4,
      'cols' => 60,
      'value' => $degree->track_description,
      'description' => t("You may enter simple HTML in this field. Ex: &lt;b&gt;bold&lt;/b&gt; or &lt;i&gt;italics&lt;/i&gt;"),
      'popup_description' => t("This is where you can enter a short description of this Level-3 degree track (also called a Degree Option) which will display for the user in a pop-up when they select to change degree options.
                                <br><br>
                                To enter a default message, which will display at the top of the selection window, begin the description with:
                                <br><b>DEFAULT:</b>                               
                                (It must be in all caps, and you must have the colon (:) after it.)
                                <br>By doing this in ANY of the track descriptions for a major, FP will ignore all other track descriptions and ONLY display the default.
                                <br><br>Example of usage:  DEFAULT: You may select any of these degree options."),
    "weight" => 1140,
    );
      
  }  
  
  
  
  
      
        
  ///////////////////////////////////////
  ///////////////////////////////////////
  // Semester Blocks //
  //////////////////////////////////////
  ///////////////////////////////////////
  $groups_counter = 0;  // used to create unique variables for groups 
  $js_existing_semesters = array();
  $degree_sort_policy = variable_get_for_school("degree_requirement_sort_policy", "alpha", $degree->school_id);
  $form['blocks_top_markup'] = array(
    'type' => 'markup',
    'value' => "<div style='clear: both;'></div>
                <hr>
                <div class='add-new-sem-block' style='float: right; margin-top: 5px;'>" . fp_render_button(t("Add New Semester Block"), "adminAddNewSemesterBlock();") . "</div>                
                <h2>Semester Blocks</h2>
                
                ",
    "weight" => 2010,
  );
  
  $base_weight = 30000;
   
  $first_semester_num = -999;
            
  $degree->list_semesters->reset_counter();
  while ($degree->list_semesters->has_more()) {
    $semester = $degree->list_semesters->get_next();
    if ($semester->semester_num < 0) {
      continue;
    }
    
    $js_existing_semesters["semester_" . $semester->semester_num] = $semester->semester_num;
    
    // Record what is the first semester num we have...
    if ($first_semester_num == -999) {
      $first_semester_num = $semester->semester_num;
    } 
     
    $sem_default_title = admin_get_semester_name($semester->semester_num);
    if ($semester->title == $sem_default_title) {
      $semester->title = "";
    }

    $form["semester_{$semester->semester_num}_very_top_markup"] = array(
      "value" => "",
      "weight" => $base_weight + ($semester->semester_num * 100) + 10,
    );
    
    
    $form['semester_title_' . $semester->semester_num] = array(
      'type' => 'textfield',
      'prefix' => "<div style='padding-bottom: 3px; margin-top:20px;'><b>" . t("Block number: ") . ($semester->semester_num + 1) . "</b> 
                                    <a href='javascript:adminEditBlockNumber($semester->semester_num);' title='" . t("Edit block number") . "'><i class='fa fa-pencil'></i></a> 
                    &nbsp; &nbsp;
                    <a href='javascript:adminDeleteSemesterBlock($semester->semester_num);'>Delete?</a>
                    &nbsp; &nbsp; &nbsp; Default Title: <em>$sem_default_title</em></div>",
      'label' => 'Override Title:',
      'value' => $semester->title,
      'size' => 20,
      'popup_description' =>  t("You may override the default title for a block.  For example, if instead of Freshman Year you want it to read Pre-Pharmacy Year 1 in FlightPath,
                                 then you would enter that in this box and hit save.  
                                 <br><br>
                                 To change a title back to the default, just leave it blank."),
      "weight" => $base_weight + ($semester->semester_num * 100) + 20,      
    );


    // Show the courses for this semester...
    $contents = "";
    if ($degree_sort_policy == 'database') {      
      $semester->list_courses->sort_degree_requirement_id();
    }
    else {
      // Default, sort alphabetically
      $semester->list_courses->sort_alphabetical_order();
    }
    $semester->list_courses->reset_counter();
    while($semester->list_courses->has_more()) {
      $course = $semester->list_courses->get_next();
      $course->load_descriptive_data(); 
      $contents .= "$course->subject_id $course->course_num $course->requirement_type";
      if ($course->min_grade != variable_get_for_school("minimum_passing_grade", "D", $degree->school_id) && $course->min_grade != "") {
        $contents .= " ($course->min_grade)";
      }
      $contents .= "\n";
    }
    

    $form["semester_{$semester->semester_num}_top_table_markup"] = array(      
      "value" => "<div class='edit-degree-semester-block'>
                   <table border='1' width='100%' class='edit-degree-semester-block-table'>
                    <td valign='top' width='30%'>",
      "weight" => $base_weight + ($semester->semester_num * 100) + 30,
    );
    
        
    $form['courses_' . $semester->semester_num] = array(
      'type' => 'textarea',
      'prefix' => "",      
      'label' => 'Courses: ',
      'value' => $contents,
      'rows' => 10,
      'cols' => 20,
      'popup_description' => t("Enter course requirements in this format: 
                                  <br><br> &nbsp; &nbsp; SUBJECT COURSENUM type (MINGRADE)
                              <br><br>&bull; type - lowercase character denoting the requirement type of the course.  Make sure you have a space between it and the course number.  If no type is specified, it is understood to be a major requirement.\n
                              <br>&bull; min grade - Place the min grade (if there is one) in parenthesis after the type.  Make sure there is a space between the min grade and the type (or course number, if there is no type specified).\n   Example:  ACCT 110 s (C)\n\n
                              
                              <br><br>Repeats require no special characters or symbols.  Simply enter the course again."),

      'suffix' => "",
                  
      "weight" => $base_weight + ($semester->semester_num * 100) + 40,      
    );
    
    
    
    // Show the groups (and option for adding groups) for this semester...
    $contents = "";
    $contents .= "</td>
                  <td valign='top'>
          <label>" . t("Groups:") . "</label>
          <table width='100%' border='0' cellspacing='5' class='edit-degree-semester-groups-table'>
          <tr>
            <th valign='top' class='tenpt' width='1'>&nbsp;</td>
            <th valign='top' class='tenpt'>" . t("Group") . "</td>
            <th valign='top' class='tenpt' width='10'>" . t("Hrs") . "</td>
            <th valign='top' class='tenpt' width='10'>" . t("Grd") . "</td>
            <th valign='top' class='tenpt' width='10'>" . t("Type") . "</td>
          </tr>    
    ";
    $semester->list_groups->sort_alphabetical_order();
    $semester->list_groups->reset_counter();
    while($semester->list_groups->has_more()) {
      $group = $semester->list_groups->get_next();
      $group->load_descriptive_data();
      
      $ghours = $group->hours_required;
      
      // If min hours are specified, set to MIN-MAX.
      if ($group->has_min_hours_allowed()) {
        $ghours = $group->min_hours_allowed . "-" . $ghours;
      }

      

      $contents .= "<tr>
                      <td valign='middle'>      
                        <a href='javascript: adminDelGroup(\"" . $group->get_db_group_id() . "\",\"$semester->semester_num\");' class='delgroup-icon' title='" . t("Remove group?") . "'><i class='fa fa-times-circle'></i></a>
                      </td>
                      <td valign='top' class='tenpt'>
                        $group->title<br><i>$group->group_name</i>
                      </td>
                      <td valign='top' class='tenpt'>$ghours</td>
                      <td valign='top' class='tenpt'>$group->min_grade</td>
                      <td valign='top' class='tenpt'>$group->requirement_type                        
                      </td>
                    </tr>";

      // Let's also add a hidden form element here, for the existing group.
      $groups_counter++;      
      $form['group_' . $group->get_db_group_id() . '_' . $groups_counter] = array(
        'type' => 'hidden',
        'value' => $group->get_db_group_id() . "_" . $semester->semester_num . "_" . $ghours . "_" . $group->requirement_type . "_" . $group->min_grade, 
      );


    } // while semester->list_groups    
    
    $url = fp_url("admin/degrees/popup-add-group2", "semester_num=$semester->semester_num&de_catalog_year=$de_catalog_year&school_id=$degree->school_id");
    
    $contents .= "</table>
        <div style='margin-top: 10px; margin-left: 20px; font-size:0.8em;'>
          <a href='javascript: adminPopupWindow(\"$url\");'>" . t("Add an elective group") . "</a>
        </div>";
    
    
    // Display our contents in a markup element for this semester
    $form["semester_groups_" . $semester->semester_num . "_markup"] = array(    
      'value' => $contents, 
      "weight" => $base_weight + ($semester->semester_num * 100) + 50,
      'attributes' => array('class' => 'groups-inner'),        
    ); 

 
 
    $form["semester_{$semester->semester_num}_table_bottom_markup"] = array(      
      "value" => "</table>
                   </div> <!-- semester block -->",
      "weight" => $base_weight + ($semester->semester_num * 100) + 60,
    );

    $form["semester_{$semester->semester_num}_below_table_markup"] = array(
      "value" => "",
      "weight" => $base_weight + ($semester->semester_num * 100) + 70,
    );

 
    $form["semester_{$semester->semester_num}_save_draft_markup"] = array(      
      "value" => fp_render_button(t("Save Draft for @year", array("@year" => $de_catalog_year)), "adminSubmitDegreeForm2();"),
      "weight" => $base_weight + ($semester->semester_num * 100) + 80,
    );
 
  
  
  } // while degree->list_semesters
     

  // Add our js_existing_semesters array to the javascript on this page, so we can check it later.
  fp_add_js($js_existing_semesters, "setting");
  
  
  
  ////////////////////////////////////////////////////////////
  ////////////////////////////////////////////////////////////
  ////////////////////////////////////////////////////////////
  ////////////////////////////////////////////////////////////
  
  $bool_start_closed = TRUE;
     
  if (@$degree->public_notes_array[$degree->degree_id] != "") {
    $bool_start_closed = FALSE;
  }
     
  // Advanced options
  $elements = array();     
     
  $elements['public_note'] = array(
    'type' => 'textarea',
    'label' => t('Public Note'),
    'rows' => 4,
    'value' => @$degree->public_notes_array[$degree->degree_id],
    'description' => t("You may enter simple HTML in this field. Ex: &lt;b&gt;bold&lt;/b&gt; or &lt;i&gt;italics&lt;/i&gt;"),
    'popup_description' => t("A public note will appear at the top of a degree plan when pulled up in FlightPath.  
                              Use this to pass messages to all students and advisors who pull up this degree plan.
                              <br><br>It will begin with the text <b>Important Message:</b> automatically."),
    
    "weight" => 50010,    
  );

  
  // min/max/default tracks
  $elements['adv_if_tracks_markup'] = array(
    'type' => 'markup',
    'value' => t('If this degree has tracks, you may enter rules for track selection below, for each
                  type of level 3 degree (tracks). <b>You may skip classifications which do not pertain to this degree.</b>'),
    "weight" => 50020,
  );
  $c = 10;
  $degree_classes = fp_get_degree_classifications();
  foreach ($degree_classes["levels"] as $level => $details) {
    $c = $c + 10;  
    if (intval($level) != 3) continue;  // we only care about level 3...
    
    foreach ($degree_classes["levels"][$level] as $machine_name => $title) {
          
      $min_tracks = @intval($degree->track_selection_config_array[$machine_name]["min_tracks"]);  
      $max_tracks = @intval($degree->track_selection_config_array[$machine_name]["max_tracks"]);  
      $default_tracks = @trim($degree->track_selection_config_array[$machine_name]["default_tracks"]);  
  
      if ($min_tracks > 0 || $max_tracks > 0 || $default_tracks != "") {
        $bool_start_closed = FALSE;
      }
  
  
      $elements["tsc_{$machine_name}_min_tracks"] = array(
        'type' => 'textfield',
        'label' => t('Min:'),
        'size' => 2,
        'value' => $min_tracks,
        'prefix' => "<table width='100%' class='admin-degrees-edit-track-rules-table'>
                     <tr class='admin-degrees-edit-track-selection-config track-selection-config-$machine_name'>
                      <td valign='top' width='30%'>                        
                        <span class='track-selection-config-title'>$title</span>:
                       </td>
                       <td valign='top'>",
        'suffix' => "</td>",
        'popup_description' => t("This is the minimum number of tracks THIS degree must select.  For example, if the degree requires at least one track, enter a 1 (one) in this box.
                                  
                                  <br><br>The default is zero (0), which means that no track is required for this degree.  The user is not required to select any at all.
                                  
                                  <br><br>If you are unsure what to enter, leave it set to 0 (zero)."),
        "weight" => 60000 + $c + 1,    
      );
      
      
      $elements["tsc_{$machine_name}_max_tracks"] = array(
        'type' => 'textfield',
        'label' => t('Max:'),
        'size' => 2,
        'value' => $max_tracks,
        'prefix' => "<td valign='top'>",
        'suffix' => "</td>",
        'popup_description' => t("This is the maximum number of tracks THIS degree may select.  For example, if the degree requires the student to have NO MORE than 1 track selected, you would enter 1 (one) in this box.
                                  
                                  <br><br>The default is zero (0), which means that there is no maximum; the user may select as many of this degree's tracks as they wish.
                                  
                                  <br><br>If you are unsure what to enter, leave it set to 0 (zero)."),
        "weight" => 60000 + $c + 5,
      );
      
      $elements["tsc_{$machine_name}_default_tracks"] = array(
        'type' => 'textfield',
        'label' => t('Default Tracks:'),
        'size' => 25,
        'value' => $default_tracks,
        'prefix' => "<td valign='top'>",
        'suffix' => "</td>
                     </tr>
                     </table>",
        'popup_description' => t("If THIS degree has one or more 'default' tracks associated with it (ex: for What If mode), enter the FULL major codes here, separated by comma.
                                  <br><br>For example: ART|_SCULP, ART|_PAINT
                                  <br><br>If you are unsure what to enter, leave it blank."),
        "weight" => 60000 + $c + 7,
      );
                  
      
    
    } // foreach levels[$level]
  } // foreach levels as level => details  
  
  
  
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
  
    $form['markup_bottom_delete'] = array(
      "value" => "        <div align='right' class='degrees-delete-degree-wrapper'>
            Delete this degree? <input type='button' value='X'
                    onClick='adminDeleteDegree(\"$degree_id\");'>
          </div>      
          ", 
      "weight" => 90000,
    );
  
    

  }
  
  
  
  
  
  
  return $form;
} // admin_edit_degree_form




function admin_edit_degree_form_validate($form, &$form_state) {
  $values = $form_state['values'];
  $perform_action2 = trim($values["perform_action2"]);
  
  $semester_nums_in_use = array();

  $school_id = 0;
  if (isset($values['schools_school'])) { // A school was set from the schools module.
    $school_id = intval($values['schools_school']);  // Use our NEW school_id for validating.
  }

  // What courses should have their type overwritten as "x"?
  $ignore_courses = csv_to_array(variable_get_for_school("ignore_courses_from_hour_counts", "", $school_id));
  
  
  // One of the things we want to do is make sure that the courses & groups specified actually belong to the school_id for this degree.
  $de_catalog_year = $values["de_catalog_year"];
  $db = get_global_database_handler();
    
  $degree_id = @intval($form_state['values']['degree_id']);
      
  // Each degree_id is unique to the catalog year, so we should be good with just the id here.  
  if ($degree_id) {  
    $degree = new DegreePlan($degree_id, null, false, false, TRUE);
    $degree->load_descriptive_data();
  }
  else {
    // Couldn't find degree_id!
    form_error("", t("There was a problem trying to save this degree.  The degree_id could not be found.  Contact FlightPath admin if
                      this problem persists."));
    return;
  } // else from if(degree_id)   
  
  
  // At this point, we have a school_id and degree_id.
  $school_name = t('Default');
  if (module_enabled("schools")) {            
    $defs = schools_get_school_definitions();          
    if (isset($defs[$school_id])) {
      $school_name = $defs[$school_id];
    }
    else {
      $school_name = t('Not Found');
    }
  }  
  
  $highest_semester_num = 0;
  
  
  // Let's check that all of the courses also belong to this degree and/or can be found at all (no typos).  
  foreach($values as $key => $value) {
    if (!strstr($key, "courses_")) {
      continue;
    }
    // Only look at the groups...
    $temp = explode("_", $key);
    $semester_num = trim($temp[1]);
    $semester_nums_in_use[] = $semester_num;

    if ($semester_num > $highest_semester_num) {
      $highest_semester_num = $semester_num;
    }
    
      
    $courses = fp_trim(@$values["courses_$semester_num"]);

    // Does it contain a single #?  Or, is blank?  If so, insert and move on.
    // We do this, so we can have blank semesters until someone deletes them.
    if ($courses == "#" || $courses == "") {
      continue;         
    }

    $course_rows = explode("\n",$courses);
    for ($t = 0; $t < count($course_rows); $t++) {
      $line = trim($course_rows[$t]);
      if ($line == "") {
        continue;
      }
      
      // Take out extra whitespace between tokens.
      $line = str_replace("   ", " ", $line);
      $line = str_replace("  ", " ", $line);
      $line = str_replace("  ", " ", $line);
      $line = str_replace("  ", " ", $line);

      $tokens = explode(" ", $line);
      $subject_id = $tokens[0];
      $course_num = $tokens[1];
      $requirement_type = strtolower(fp_trim(@$tokens[2]));

      if ($requirement_type == "") { 
        // major type by default.
        $requirement_type = "m";
      }

      // If this course is in our ignore list, override it's type
      // to be 'x'
      if (in_array("$subject_id $course_num", $ignore_courses)) {
        $requirement_type = "x";
      }
      
      
      $min_grade = strtoupper((string) @$tokens[3]);

      if (strstr($requirement_type, "(")) {
        // This means there was no requirement_type specified, so it's "m",
        // and a min_grade was found in its place.
        $min_grade = strtoupper($requirement_type);
        $requirement_type = "m";
      }

      $min_grade = str_replace("(","",$min_grade);
      $min_grade = str_replace(")","",$min_grade);

      $min_grade = strtoupper($min_grade);

      /////////////////////////////////////////////
      // Find out what the course_id is.
      if ($course_id = $db->get_course_id($subject_id, $course_num, "", TRUE, $school_id, TRUE))  
      {
        // This was found correctly, do nothing.
      } 
      else {
        // The course_id could not be found!
        
        // Different message if it's a school_id problem.
        if (module_enabled("schools")) {          
          form_error("courses_" . $semester_num, t("<strong>Your work has NOT been saved.</strong> Course not found.  In block %blocknum, you specified the course %course as a requirement,
                          but this course could not be found.  This could be due to a typo, or due to the course not belonging to the same school
                          as the degree (%schoolname).  Please check the course and try again.", array("%blocknum" => ($semester_num+1), "%course" => "$subject_id $course_num", "%schoolname" => $school_name)));
        }
        else {
          // Not related to school (possibly).
          form_error("courses_" . $semester_num, t("<strong>Your work has NOT been saved.</strong> Course not found.  In block %blocknum, you specified the course %course as a requirement,
                          but this course could not be found.  This could be due to a typo.  
                          Please check the course and try again.", array("%blocknum" => ($semester_num+1), "%course" => "$subject_id $course_num")));
                    
        }
      }

    } // for course_rows

  } // foreach courses
    
  
  

  
        
  // Are we trying to ADD a group?  
  if (strstr($perform_action2,"addGroup")) {
    $temp = explode("_",$perform_action2);
    $group_id = $temp[1];
    $semester_num = $temp[2];
    $semester_nums_in_use[] = $semester_num;
    $hours = trim($temp[3]);
    $min_hours = $hours;
    if (strstr($hours, "-")) {
      // Hours has a min hour value specified.  let's grab it.
      $tt = explode("-", $hours);
      $min_hours = $tt[0];
      $hours = $tt[1];
    }    
    
    
    $type = $temp[4];
    $min_grade = trim($temp[5]);
    
    $min_grade = strtoupper($min_grade);
        
    $adding_group = new Group($group_id);
    
    // If this group is already saved in this degree elsewhere, and it has a different type or min_grade,
    // then we must display a warning message to the user.
    $res = db_query("SELECT * FROM draft_degree_requirements
                     WHERE degree_id = '?'
                     AND group_id = '?'
                     AND (group_min_grade <> '?' OR group_requirement_type <> '?')", $degree_id, $group_id, $min_grade, $type);
    while ($cur = db_fetch_array($res)) {
      form_error("semester_groups_" . $semester_num . "_markup", t("<strong>Your work has NOT been saved.</strong> In block %blocknum, you specified the group %group with a min grade of %mingrade and a requirement type of %req.  
                                However, the group was previously specified in this degree plan in block %otherblocknum wih a different requirement type or min grade.
                                FlightPath will not perform calculations correctly for courses assigned to this group.  The duplicate occurance of the group should have the same
                                type/min grade, or, you need to create a new group to handle these alternate requirements.</strong>"
                                , array("%blocknum" => ($semester_num+1), "%group"=> $adding_group->title, "%mingrade" => $min_grade, "%req" => $type, "%otherblocknum" => ($cur['semester_num'] + 1))));

                
    } // while
  } // addGroup
   
    
  
  
    
  // Now, let's check the groups.
  
  // Are we trying to DELETE a group, but there might be an error with it?  If so, we won't worry about any errors later.
  $del_group = NULL;
  if (strstr($perform_action2, "delGroup")) {
    $temp = explode("_",$perform_action2);
    $del_group = new Group();
    $del_group->bool_use_draft = TRUE;
    $del_group->group_id = $temp[1];
    $del_group->assigned_to_semester_num = $temp[2];
  }  
    
  // Get the groups....
  foreach($values as $key => $value) {
    if (!strstr($key, "group_")) {
      continue;
    }
    // Only look at the groups...
    $temp = explode("_", $value);
        
    $group_id = $temp[0];
    
    $semester_num = $temp[1];
    $semester_nums_in_use[] = $semester_num;

    if ($semester_num > $highest_semester_num) {
      $highest_semester_num = $semester_num;
    }
        
    $hours = $temp[2];
    $min_hours = $hours;
    
    if (strstr($hours, "-")) {
      // Hours has a min hour value specified.  let's grab it.
      $tt = explode("-", $hours);
      $min_hours = $tt[0];
      $hours = $tt[1];
    }    
    
    $type = $temp[3];
    $min_grade = trim($temp[4]);

    // Do not process any further, if we are supposed to be deleting this group!
    if (isset($del_group) && $del_group != NULL && is_object($del_group)) {      
      if ($del_group->group_id == $group_id && $del_group->assigned_to_semester_num == $semester_num) {        
        continue;
      }
    }

    $adding_group = new Group($group_id);
    
    // Is this in the right school?
    if ($adding_group->school_id != $school_id && $adding_group->school_id !== 0) {
      
      form_error("semester_groups_" . $semester_num . "_markup", t("<strong>Your work has NOT been saved.</strong> In block %blocknum, you specified the group %group as a requirement,
                          but this group could not be found in the degree's school (%schoolname).<br>You may need to change the degree's school back to its previous value, then remove this group,
                          in order to continue.", array("%blocknum" => ($semester_num+1), "%group" => $adding_group->title, "%schoolname" => $school_name)));
      
    }
    
    
    // If this group is already saved in this degree elsewhere, and it has a different type or min_grade,
    // then we must display a warning message to the user.
    $res = db_query("SELECT * FROM draft_degree_requirements
                     WHERE degree_id = ?
                     AND group_id = ?
                     AND (group_min_grade <> ? OR group_requirement_type <> ?)", $degree_id, $group_id, $min_grade, $type);
    while ($cur = db_fetch_array($res)) {
  
      // Is the group we found the group we are trying to delete?  If so, skip it; we're going to delete it later.
      if (isset($del_group) && is_object($del_group)) {          
        if (intval($del_group->group_id) == intval($cur['group_id']) && intval($del_group->assigned_to_semester_num) == intval($cur['semester_num'])) {
          continue;
        }
      } 
     
      form_error("semester_groups_" . $semester_num . "_markup", t("<strong>Your work has NOT been saved.</strong> In block %blocknum, you specified the group %group with a min grade of %mingrade and a requirement type of %req.  
                                However, the group was previously specified in this degree plan in block %otherblocknum wih a different requirement type or min grade.
                                FlightPath will not perform calculations correctly for courses assigned to this group.  The duplicate occurance of the group should have the same
                                type/min grade, or, you need to create a new group to handle these alternate requirements.</strong>"
                                , array("%blocknum" => ($semester_num+1), "%group"=> $adding_group->title, "%mingrade" => $min_grade, "%req" => $type, "%otherblocknum" => ($cur['semester_num'] + 1))));

            
    }
            
  } // foreach groups
         
  
  
  // Are we trying to change a semester block number?
  if (strstr($perform_action2,"editSemesterBlockNum")) {
    // Make sure we don't already have this number in existence elsewhere in the degree.
    $temp = explode("_",$perform_action2);
    $old_semester_num = $temp[1];
    $new_semester_num = $temp[2];    
    
    if (in_array($new_semester_num, $semester_nums_in_use)) {
      form_error('', t("<strong>Your work has NOT been saved.</strong> Sorry, but you requested to change block number %old to %new, but block number %new
                        is already in use.  Please make the appropriate changes and try again.", array("%old" => ($old_semester_num + 1), "%new" => ($new_semester_num + 1))));
    }    
    
  }  // editSemesterBlockNum
    
  
  
  
  
  
  
} // edit_degree_form_validate


function admin_edit_degree_form_submit($form, $form_state) {
    
  $values = $form_state['values'];
  
  // Set the scroll_top position, if it is set.
  $scroll_top = $form_state['values']['scroll_top'];  
  if (@$scroll_top != "" && intval($scroll_top) > 0) {
    $_SESSION['scroll_top'] = intval($scroll_top);
  }
  
  // Perform actual saves to database
  $de_catalog_year = $values["de_catalog_year"];
  $db = get_global_database_handler();

  
  // This will UPDATE a degree in the system with the courses
  // and groups that the user selected.
  $perform_action2 = filter_plain($values["perform_action2"]);

  if (strstr($perform_action2, "delGroup")) {
    $temp = explode("_",$perform_action2);
    $del_group = new Group();
    $del_group->bool_use_draft = TRUE;
    $del_group->group_id = $temp[1];
    $del_group->assigned_to_semester_num = $temp[2];

  }


  $major_code = filter_plain($values["major_code"]);
  if ($major_code == ""){ die("Fatal error:  major_code not found.");}

  // Since we are making a change to the draft table(s), let's add a row
  // to draft instructions.
  $db->add_draft_instruction("-");

  // Let's assemble the track selection config values into one string.  We need to loop through all of the POST
  // values, looking for each type of track.
  $db_track_selection_config = "";
  $degree_classes = fp_get_degree_classifications();
  foreach ($degree_classes["levels"] as $level => $details) {
    if (intval($level) != 3) continue;  // we only care about level 3...
    foreach ($degree_classes["levels"][$level] as $machine_name => $title) {
      // Got the machine name, now let's see if we have any values for
      // the track selections...
      $min_tracks = @intval($values["tsc_{$machine_name}_min_tracks"]);
      $max_tracks = @intval($values["tsc_{$machine_name}_max_tracks"]);
      $default_tracks = @trim($values["tsc_{$machine_name}_default_tracks"]);
      
      $db_track_selection_config .= $machine_name . "~" . $min_tracks . "~" . $max_tracks . "~" . $default_tracks . "\n";   
    }
  }

  
  $school_id = 0;
  if (isset($form_state['values']['school_id'])) {
    $school_id = intval($form_state['values']['school_id']);
  }
  $degree_id = @intval($form_state['values']['degree_id']);
  // If this degree already exists in this
  // catalog year, then we need to delete it first.
  //if ($degree_id = $db->get_degree_id($major_code, $de_catalog_year, true, $school_id)) {
      
  // Each degree_id is unique to the catalog year, so we should be good with just the id here.  
  if ($degree_id) {  
    $degree = new DegreePlan($degree_id, null, false, false, true);
    $degree->load_descriptive_data();    
    $oschool_id = intval($degree->school_id); // original school_id
    
    // Delete from degree_requirements WHERE this degree_id exists.
    $res = db_query("DELETE FROM draft_degree_requirements
                  WHERE degree_id = ? ", $degree_id);

    ///////////////////////////////
    // Are we trying to DELETE this degree?  If so, keep deleting!
    if ($perform_action2 == "delete_degree" && user_has_permission("can_delete_data_entry")) {
      $res = db_query("DELETE FROM draft_degrees
                  WHERE  degree_id = ? ", $degree_id);

      // Also need to get rid of the track, if there is one for this
      // degree.
      
      $temp = explode("_", $degree->major_code);  // get only the "major" part from the full major_code (might look like ACCT|FINA_TRACK1
      $just_major_part = trim($temp[0]);
      $just_major_part = rtrim($just_major_part, "|"); // if there is now a trailing |, get rid of it.  Ex:  ACCT|
            
      $res = db_query("DELETE FROM draft_degree_tracks
                  WHERE major_code = ? 
                  AND track_code = ?
                  AND catalog_year = ? 
                  AND school_id = ? 
                  LIMIT 1", $just_major_part, $degree->track_code, $de_catalog_year, $oschool_id);


      // Okay, we have deleted everything.  We need to go back to
      // just the list of degrees.
      //display_edit_degrees("<font color='green'><b>The degree $major_code ($de_catalog_year) has been deleted.</b></font>");
      //die;
      
      fp_add_message("The degree $major_code ($de_catalog_year) has been deleted.");
      watchdog("admin", "The degree $major_code ($de_catalog_year) has been deleted.");
      unset($_SESSION['scroll_top']);
      unset($_REQUEST['scroll_top']);
      fp_goto("admin/degrees", "de_catalog_year=$de_catalog_year");
      return; 
      
    } // if perform_action2 == delete_degree


  } // if degree->id
  else {
    // We need to generate a new degreeID for this major_code and catalog_year,
    // because one does not already exist!
    if (!$degree_id = $db->request_new_degree_id()) {
      watchdog("admin", "Could not create new degree.");
      die ("Error.  Could not create new degree_id.");      
    }
  } // ELSE



  // What courses should have their type overwritten as "x"?
  $ignore_courses = csv_to_array(variable_get_for_school("ignore_courses_from_hour_counts", "", $school_id));




  ///////////////////////////////////////
  // Are we trying to add/edit/delete a semester block?
  
  if (strstr($perform_action2, "editSemester")) {
    $temp = explode("_",$perform_action2);
    $act = filter_plain($temp[1]);
    $semester_num = filter_plain($temp[2]);

    if ($act == "new") {
      // We will add a comment to the courses box, just to keep it alive, which will be ignored later.
      $values["courses_$semester_num"] = "#"; 
    }  // if act == new

    if ($act == "del") {
      // We will remove data for this semester block.
      unset($values["courses_$semester_num"]);
      unset($values["semester_title_$semester_num"]);
      foreach($values as $key => $value) {
        if (!strstr($key, "group_")) {
          continue;
        }
        // Only look at the groups...
        $temp = explode("_", $value);          
        $g_semester_num = $temp[1];
        if ($g_semester_num == $semester_num) {
          unset($values[$key]);
        }
      }
      
    } // if act == del
    

  } // if perform_action2 contains editSemester
  



  ///////////////////////////////////////
  // Okay, time to start actually inserting our data into the db.

  $errors = "";
  $semester_titles_csv = "";
  $highest_semester_num = 0;   // What is the largest semester_num in the system?
  // Get the COURSES for each semester
  foreach($values as $key => $value) {
    if (!strstr($key, "courses_")) {
      continue;
    }
    // Only look at the groups...
    $temp = explode("_", $key);
    $semester_num = trim($temp[1]);

    if ($semester_num > $highest_semester_num) {
      $highest_semester_num = $semester_num;
    }
    
      
    $courses = filter_plain(@$values["courses_$semester_num"]);

    // Does it contain a single #?  Or, is blank?  If so, insert and move on.
    // We do this, so we can have blank semesters until someone deletes them.
    if ($courses == "#" || $courses == "") {
      $query = "INSERT INTO draft_degree_requirements
                  (degree_id, semester_num, course_id, course_min_grade, course_requirement_type, data_entry_value)
                  values (?,?,?,?,?,?) ";
      $res = db_query($query, $degree_id, $semester_num, 0, '', '', "#");
      continue;         
    }

    $course_rows = explode("\n",$courses);
    for ($t = 0; $t < count($course_rows); $t++) {
      $line = trim($course_rows[$t]);
      if ($line == "") {
        continue;
      }
      
      // Take out extra whitespace between tokens.
      $line = str_replace("   ", " ", $line);
      $line = str_replace("  ", " ", $line);
      $line = str_replace("  ", " ", $line);
      $line = str_replace("  ", " ", $line);

      $tokens = explode(" ", $line);
      $subject_id = $tokens[0];
      $course_num = $tokens[1];
      $requirement_type = strtolower(fp_trim(@$tokens[2]));

      if ($requirement_type == "") { 
        // major type by default.
        $requirement_type = "m";
      }

      
      // If this course is in our ignore list, override it's type
      // to be 'x'
      if (in_array("$subject_id $course_num", $ignore_courses)) {
        $requirement_type = "x";
      }
      
      
      $min_grade = strtoupper((string) @$tokens[3]);

      if (strstr($requirement_type, "(")) {
        // This means there was no requirement_type specified, so it's "m",
        // and a min_grade was found in its place.
        $min_grade = strtoupper($requirement_type);
        $requirement_type = "m";
      }

      $min_grade = str_replace("(","",$min_grade);
      $min_grade = str_replace(")","",$min_grade);

      /////////////////////////////////////////////
      // Okay, we now have enough information to insert the course.
      // Find out what the course_id is.
      if ($course_id = $db->get_course_id($subject_id, $course_num, "", TRUE, $school_id, TRUE)) 
      {        
        $query = "INSERT INTO draft_degree_requirements
                    (degree_id, semester_num, course_id, course_min_grade, course_requirement_type, data_entry_value)
                    values (?,?,?,?,?,?) ";
        $res = db_query($query, $degree_id, $semester_num, $course_id, $min_grade, $requirement_type, "$subject_id~$course_num");
      } 
      else {
        // The course_id could not be found!
        $errors .= "<br><span style='color:red;'><b>Course Not Found!</b>
              In Block " . ($semester_num+1) . ", you specified the course
              <b>$subject_id $course_num</b> as a requirement, but this course
              could not be found in the degree's school.
              It was removed from that block.
              Are you sure you typed it correctly?  Please go to this
              semester, check your spelling, and add the course again.</span>";

      }

    }


  } // foreach values, looking for courses_
  
  
  
  
  // Get the groups....
  foreach($values as $key => $value) {
    if (!strstr($key, "group_")) {
      continue;
    }
    // Only look at the groups...
    $temp = explode("_", filter_plain($value));
        
    $group_id = $temp[0];
    
    $semester_num = $temp[1];

    if ($semester_num > $highest_semester_num) {
      $highest_semester_num = $semester_num;
    }
        
    $hours = $temp[2];
    $min_hours = $hours;
    
    if (strstr($hours, "-")) {
      // Hours has a min hour value specified.  let's grab it.
      $tt = explode("-", $hours);
      $min_hours = $tt[0];
      $hours = $tt[1];
    }    
    
    $type = $temp[3];
    $min_grade = trim($temp[4]);

    // Do not add if we are supposed to be deleting this group!
    if (isset($del_group) && is_object($del_group)) {
      if ($del_group->group_id == $group_id && $del_group->assigned_to_semester_num == $semester_num) {
        continue;
      }
    }

    $adding_group = new Group($group_id);
    
    // If this group is already saved in this degree elsewhere, and it has a different type or min_grade,
    // then we must display a warning message to the user.
    $res = db_query("SELECT * FROM draft_degree_requirements
                     WHERE degree_id = '?'
                     AND group_id = '?'
                     AND (group_min_grade <> '?' OR group_requirement_type <> '?')", $degree_id, $group_id, $min_grade, $type);
    while ($cur = db_fetch_array($res)) {
      $errors .= "<br><span style='color:red;'><b>Group type or min grade mismatch!</b>
                  In Block " . ($semester_num + 1) . ", you specified the group <em>" . $adding_group->title . "</em> ($adding_group->group_name) with a min grade of \"<em>$min_grade</em>\"
                  and a requirement type of \"<em>$type</em>\".  However, the group was previously specified in this degree plan in Block " . ($cur["semester_num"] + 1) . "
                  with a different requirement type and/or min grade requirement.  FlightPath will not perform calculations correctly for courses assigned to this group.
                  You should either set the duplicate occurances of the group to the same type/min grade, or create a new group to handle these alternate requirements.</span>";
    }
    
    
    
    
    // We now have enough information to insert this group.

    $query = "INSERT INTO draft_degree_requirements
                    (degree_id, semester_num, group_id,
                    group_requirement_type, group_hours_required, group_min_hours_allowed, group_min_grade)
                    values (?,?,?,?,?,?,?) ";
    $res = db_query($query, $degree_id, $semester_num, $group_id, $type, $hours, $min_hours, $min_grade);


  }  // for each values, looking for _group  




  // Was there a group added?
  if (strstr($perform_action2,"addGroup")) {
    $temp = explode("_",filter_plain($perform_action2));
    $group_id = $temp[1];
    $semester_num = $temp[2];
    $hours = trim($temp[3]);
    $min_hours = $hours;
    if (strstr($hours, "-")) {
      // Hours has a min hour value specified.  let's grab it.
      $tt = explode("-", $hours);
      $min_hours = $tt[0];
      $hours = $tt[1];
    }    
    
    
    $type = $temp[4];
    $min_grade = trim($temp[5]);
    
    $min_grade = strtoupper($min_grade);
    
    $adding_group = new Group($group_id);
    
    // If this group is already saved in this degree elsewhere, and it has a different type or min_grade,
    // then we must display a warning message to the user.
    $res = db_query("SELECT * FROM draft_degree_requirements
                     WHERE degree_id = '?'
                     AND group_id = '?'
                     AND (group_min_grade <> '?' OR group_requirement_type <> '?')", $degree_id, $group_id, $min_grade, $type);
    while ($cur = db_fetch_array($res)) {
      $errors .= "<br><span style='color:red;'><b>Group type or min grade mismatch!</b>
                  In Block " . ($semester_num + 1) . ", you specified the group <em>" . $adding_group->title . "</em> ($adding_group->group_name) with a min grade of \"<em>$min_grade</em>\"
                  and a requirement type of \"<em>$type</em>\".  However, the group was previously specified in this degree plan in Block " . ($cur["semester_num"] + 1) . "
                  with a different requirement type and/or min grade requirement.  FlightPath will not perform calculations correctly for courses assigned to this group.
                  You should either set the duplicate occurances of the group to the same type/min grade, or create a new group to handle these alternate requirements.</span>";
    }
    
    
    
    
    $query = "INSERT INTO draft_degree_requirements
                    (degree_id,semester_num,group_id,
                    group_requirement_type,group_hours_required,group_min_hours_allowed,group_min_grade)
                    VALUES (?,?,?,?,?,?,?) ";
    $res = db_query($query, $degree_id, $semester_num, $group_id, $type, $hours, $min_hours, $min_grade);

  } // if perform_action2 == addGroup


  // Are we trying to change a semester block number?
  if (strstr($perform_action2,"editSemesterBlockNum")) {
    // Make sure we don't already have this number in existence elsewhere in the degree.
    $temp = explode("_",$perform_action2);
    $old_semester_num = $temp[1];
    $new_semester_num = $temp[2];    
    
    // Since we have already passed validation, we should be able to simply update the draft table.
    db_query("UPDATE draft_degree_requirements SET semester_num = ? 
              WHERE degree_id = ? 
              AND semester_num = ?", array($new_semester_num, $degree_id, $old_semester_num));    
    
    // We also need to adjust the semester_title_$semester_num value in the $values, so the title moves over correctly.
    $temp = $values["semester_title_$old_semester_num"];
    unset($values["semester_title_$old_semester_num"]);
    $values["semester_title_$new_semester_num"] = $temp;
    
  }  // editSemesterBlockNum




  // Make the semester_titles_csv...
  for ($semester_num = 0; $semester_num <= 99; $semester_num++) {
      
    if ($semester_num > 99) break;  // some problem.  Give up.  Don't try to go past 99 semesters!
    
    $semester_titles_csv .= trim((string) @$values["semester_title_$semester_num"]) . ",";
  }


  

  // Before we UPDATE, also grab the degree title, degree_type,
  // and exclude value, etc....
  $degree_title = filter_plain($values["title"]);
  $degree_type = filter_plain($values["degree_type"]);
  $degree_class = filter_plain($values["degree_class"]);
  $degree_level = strtoupper(filter_plain($values["degree_level"]));
  $exclude = intval($values["exclude"]);
  $allow_dynamic = intval($values["allow_dynamic"]);
  $advising_weight = intval($values["advising_weight"]);
  $override_degree_hours = filter_plain($values["override_degree_hours"]);
  $public_note = filter_markup($values["public_note"]);
  
  $res = db_query("UPDATE draft_degrees
              SET semester_titles_csv = ?,
                  title = ?,
                  degree_type = ?,
                  degree_class = ?,
                  degree_level = ?,
                  exclude = ?,
                  allow_dynamic = ?,
                  advising_weight = ?,
                  override_degree_hours = ?,
                  track_selection_config = ?,
                  public_note = ?,
                  school_id = ?
              WHERE degree_id = ? ",
                $semester_titles_csv, $degree_title, $degree_type, $degree_class, $degree_level, $exclude, $allow_dynamic, $advising_weight, $override_degree_hours, 
                $db_track_selection_config, $public_note, $school_id, $degree_id);

  ////  Was there a track title/description?  If so, UPDATE that in the tracks
  // table...
  
  $major = $track = "";  
  if (strstr($major_code, "_"))  {
    // There was a track. Update track description.
    $temp = explode("_",$major_code);
    $major = trim($temp[0]);
    // major might now have a | at the end.  If so, take it out.
    if (substr($major, strlen($major)-1, 1) == "|") {
      $major = str_replace("|","",$major);
    }


    $track = trim($temp[1]);
    $track_description = filter_markup($values["track_description"]);
    $track_title = filter_plain($values["track_title"]);
    
    // Make sure the track exists in our draft_degree_tracks table...
    $track_id = db_result(db_query("SELECT track_id FROM draft_degree_tracks
                     WHERE major_code = ?
                     AND track_code = ?
                     AND catalog_year = ?
                     AND school_id = ?", $major, $track, $de_catalog_year, $oschool_id));
    
    if (!$track_id) {
      // Didn't already exist. Let's insert.
      db_query("INSERT INTO draft_degree_tracks (major_code, track_code, catalog_year, school_id)
                VALUES (?, ?, ?, ?)", $major, $track, $de_catalog_year, $school_id);
      $track_id = db_insert_id();
    }
    
    $res = db_query("UPDATE draft_degree_tracks
                      SET track_description = ?,
                          track_title = ?,
                          school_id = ?
                      WHERE track_id = ? ", $track_description, $track_title, $school_id, $track_id);
          
          
  }






    







  fp_add_message(t("Degree updated succesfully."));
  watchdog("admin", "$major_code $track ($de_catalog_year) has been updated successfully.");
  $button_msg = urlencode("Degree updated successfully at " . date("H:i:s"));
  $bool_scroll = $bool_button_msg = true;
  if ($errors != "")
  {
    fp_add_message("ERRORS/WARNINGS: $errors");
    $bool_scroll = $bool_button_msg = false;

    $button_msg = urlencode("The degree plan was saved, but an error or warning has occured.  Please see error message at the top of the page.");
    $_REQUEST["scroll_top"] = 0;
    $_SESSION["scroll_top"] = 0;
    
  }


  ///////////////////////////////////////////////////////
  ///////////////////////////////////////////////////////
  ///////////////////////////////////////////////////////
  ///////////////////////////////////////////////////////

  // And we are done with the saving!
  
  
  
  
  
} //edit_degree_form_submit







