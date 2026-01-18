<?php
/**
 * @file
 * This file handles the more complicated Major Students Progress csv export report, since
 * it is part of a batch process.
 */
/**
 * This report will let the user select a major, then see a report of students
 * in that major, and what percentages they have let to complete, as well as other
 * data which may be useful.
 *
 */






/**
 * Simply display a form letting the user select an available major.
 *
 */
function stats_report_major_students_progress_form() {
  $form = array();
  
  
  $school_id = intval(@$_REQUEST['school_id']);
  
  $school_select_title = "Major Students Progress - Select School";
  // If schools is enabled, first ask for the school.
  if (module_enabled("schools") && !isset($_REQUEST['school_id'])) {    
    fp_goto("stats/select-school", "title=$school_select_title&redirect=stats/reports/major-students-progress");
    return; 
  }
  else if (module_enabled('schools')) {
    // Display what school we selected, link to change.
  
    $form['selected_school_name'] = array(
      'value' => "<div class='current-school'>" . t("Current school: ") . "<strong>" . schools_get_school_name_for_id($school_id) . "</strong>
                     - " . l(t("Change?"), "stats/select-school", "title=$school_select_title&redirect=stats/reports/major-students-progress") . "</div>",
    
    );
      
  }  
  
  
  $form['school_id'] = array(
    'type' => 'hidden',
    'value' => $school_id,
  );  
  
  
  
  
  
  
  $last_major = @$_SESSION["stats_report_major_students_progress"][$school_id]["last_major"];
  
  $form["major"] = array(
    "label" => t("Please select a major:"),
    "type" => "select",
    "hide_please_select" => TRUE,
    "options" => stats_draw_majors_pulldown($last_major, FALSE, FALSE, t("Please select a major"), TRUE, FALSE, TRUE, $school_id),
    "value" => $last_major,  
  );  
  
  $form["submit_btn"] = array(
    "type" => "submit",
    "spinner" => TRUE,
    "value" => t("Submit and generate CSV"),
  );
  
  
  // If we have previously generated a CSV within the last hour, display it here.
  $last_timestamp = @$_SESSION["stats_report_major_students_progress"][$school_id]["last_timestamp"];
  $last_batch_id = @$_SESSION["stats_report_major_students_progress"][$school_id]["last_batch_id"];

  $html = "";
  $m = 0;
  
  if (time() < $last_timestamp + (60 * 60)) {
    
    $html .= "<hr><p>" . t("Last CSV report was generated for @major on @time.  It will remain for 1 hour.", array("@major" => $last_major, "@time" => format_date(convert_time($last_timestamp)))) . "</p>";
    $html .= "<p>" . l(t("Download CSV for ") . $last_major, "stats/download-csv-from-batch/$last_batch_id", "filename=" . urlencode("major_student_progress__" . $last_major)) . "</p>";
    
    
    $form["mark" . $m++] = array(
      "value" => $html,
    );
  }
  
  
  // If the Batch module isn't enabled, stop right here!!!
  if (!function_exists("batch_set")) {
    $form = array();
    $form["markup"] = array(
      "value" => "<p><b>" . t("To use this report, the Batch module must be enabled.  Please ask your administrator
                            to enable the Batch module.") . "</b></p>",
    );
  }
  
  
  
  return $form;
}



/**
 * Our submit handler.  We will be setting up a new batch process.
 *
 * @param unknown_type $form
 * @param unknown_type $form_state
 */
function stats_report_major_students_progress_form_submit($form, $form_state) {
  
  $major = $form_state["values"]["major"];
  
  $school_id = intval($form_state['values']['school_id']);
  
  // Okay, set up the batch....
  $batch = array(
    "operation" => array("stats_report_major_students_progress_perform_batch_operation", array($major, $school_id)),
    "title" => t("Major Students Progress - CSV Export - Major: ") . $major,
    "file" => menu_get_module_path("stats") . "/reports.major-students-progress.inc",
    "progress_message" => "Processing student @current of @total",
    "display_percent" => TRUE,
  );
 
 
  watchdog('stats', "report_major_students_progress major:$major, school_id:$school_id", array());
 
  // Set the batch...
  batch_set($batch);
  
}



/**
 * This is the actual batch process function which gets called per run of the batch.
 *
 * @param unknown_type $batch
 * @param unknown_type $major
 */
function stats_report_major_students_progress_perform_batch_operation(&$batch, $major, $school_id = 0) {
  
  
  // For later, let's get our requirement types from the pie chart config...
  $requirement_types = array();
	// Get the requested piecharts from our config...
	$temp = variable_get_for_school("pie_chart_config", "c ~ Core Requirements\nm ~ Major Requirements\ndegree ~ Degree Progress", $school_id);
	$lines = explode("\n", $temp);
	foreach ($lines as $line) {
	  if (trim($line) == "") continue;	  
	  $temp = explode("~", $line);
	  $requirement_type = trim($temp[0]);
	  $label = trim($temp[1]);		
	  $requirement_types[$requirement_type] = $label;  
	  
	}  

  
  
  
  ////////////////////////////  FIRST TIME ///////////////////////////////
  // If this is our first time through, let's init our values.
  if (!isset($batch["results"]["total"])) {
    // Our first time through.  Let's start up.    
    $batch["results"]["current"] = 0;
    $batch["results"]["finished"] = FALSE;    

    
    $batch["csv"] = "CWID, LAST, FIRST, CATALOG, MAJOR, RANK, ";
    
  	// Get the requested piecharts from our config...
  	$temp = variable_get_for_school("pie_chart_config", "c ~ Core Requirements\nm ~ Major Requirements\ndegree ~ Degree Progress", $school_id);
  	$lines = explode("\n", $temp);
  	foreach ($lines as $line) {
  	  if (trim($line) == "") continue;
  	  
  	  $temp = explode("~", $line);
  	  $requirement_type = trim($temp[0]);
  	  $label = trim($temp[1]);		
  	  $requirement_types[$requirement_type] = $label;  
  	  $batch["csv"] .= "$requirement_type FULFILLED, $requirement_type TOTAL, $requirement_type PERCENT, $requirement_type GPA, ";
  	  
  	}  
    
  	$batch["csv"] .= "\n";

  	
  	// We need the "total" number of students in the selected major.    
    $res = db_query("SELECT COUNT(*) as count FROM students a, users b, student_degrees c
                     WHERE major_code = ?
                     AND is_active = 1
                     AND is_student = 1
                     AND a.cwid = b.cwid
                     AND b.school_id = ?
                     AND a.cwid = c.student_id
                     ORDER BY l_name, f_name 
                     
                     ", array($major, $school_id));  	
    $cur = db_fetch_array($res);
    
    $batch["results"]["total"] = $cur["count"];
  	
  }
  ////////////////////////////////////////////////////////////////////////
  
  // Okay, we can now begin the actual batch process.
  $current = $batch["results"]["current"];
  $total = $batch["results"]["total"];

  $limit = 1;  // how many records to examine for THIS time through the function.
  $c = 0;  // count of records.
  
  $db = get_global_database_handler();
   

  // Find active students with this major
  $res = db_query("SELECT * FROM students a, users b, student_degrees c
                   WHERE major_code = ?
                   AND is_active = 1
                   AND is_student = 1
                   AND a.cwid = b.cwid
                   AND b.school_id = ?                   
                   AND a.cwid = c.student_id                   
                   ORDER BY l_name, f_name
                   
                   LIMIT $current, $limit", array($major, $school_id));
  while ($cur = db_fetch_array($res)) {
    
    if ($c >= $limit) {
      break;
    }
    
    
    $l_name = $cur["l_name"];
    $f_name = $cur["f_name"];
    
    // Remove possible trouble characters from names.
    $l_name = str_replace(",", "", $l_name);
    $f_name = str_replace(",", "", $f_name);
    
    $cwid = $cur["cwid"];
    $major_code = $cur["major_code"];
    $catalog_year = $cur["catalog_year"];
    $rank = $cur["rank_code"];

   
    $batch["csv"] .= "$cwid, $l_name, $f_name, $catalog_year, $major_code, $rank, ";
      
    
    // Load them up fully and calculate their percentages      
    $student = new Student($cwid, $db);
    
    
    
    $degree_id = $db->get_degree_id($major_code, $catalog_year, FALSE, $school_id);
    if ($degree_id) {
      ///////////////////
      // Fully build up a FlightPath screen in memory, as if the student
      // were looking at this on a web page....
      $degree_plan = new DegreePlan($degree_id, $db, false, $student->array_significant_courses);
      $student->load_student_substitutions();
      $student->load_unassignments();
      
      $student->list_courses_taken->sort_alphabetical_order();
      $student->list_courses_taken->sort_most_recent_first();        
      
      $fp = new FlightPath($student, $degree_plan);

      $screen = new AdvisingScreen("", $fp);
      $screen->view = "year";        
             
      $fp->assign_courses_to_semesters(); // bare degree plan. not groups.
      $fp->assign_courses_to_groups();

      $screen->build_screen_elements();
      ////////////////
      
      // Okay, perform the percentage calculations...
      $degree_plan->calculate_progress_hours(FALSE, $requirement_types);
		  $degree_plan->calculate_progress_quality_points(FALSE, $requirement_types);
      
		  $use_degree_id = $degree_id;
		  if (!isset($degree_plan->gpa_calculations[$use_degree_id])) {
		    $use_degree_id = 0;
		  }
		  
		  
  		foreach ($requirement_types as $requirement_type => $label) {
  		  
  		  // Okay, let's see if this degreeplan even has any data on this requirement type.
  		  $total_hours = $degree_plan->gpa_calculations[$use_degree_id][$requirement_type]["total_hours"]*1;
  		  $fulfilled_hours = $degree_plan->gpa_calculations[$use_degree_id][$requirement_type]["fulfilled_hours"]*1;
  		  $qpts_hours = $degree_plan->gpa_calculations[$use_degree_id][$requirement_type]["qpts_hours"]*1;
  		  $qpts = $degree_plan->gpa_calculations[$use_degree_id][$requirement_type]["qpts"]*1;
        
  		  $gpa = $percent = 0;
  		  
  		  if ($qpts_hours > 0) {
  		    $gpa = fp_truncate_decimals($qpts / $qpts_hours, 3);
  		  }
  		  if ($total_hours > 0) {
  		    $percent = round(($fulfilled_hours / $total_hours) * 100);
  		  }
  		  
  		  
        $batch["csv"] .= "$fulfilled_hours, $total_hours, $percent, $gpa, ";
          
  		}			  
		  
		  
		  
    }
      
      
        
    $batch["csv"] .= "\n";
    
    
    $c++;
    
    
    
    
  } // while $cur = db_fetch_array  
  
  
  // Update our $batch results variables
  $batch["results"]["current"] = $current + $c;
  

  if ($batch["results"]["current"] >= $total) {
    // We are finished!
    $batch["results"]["finished"] = TRUE;
  }
  
  // Add the batch_id to the SESSION so we can call it back later on.
  $_SESSION["stats_report_major_students_progress"][$school_id] = array();
  $_SESSION["stats_report_major_students_progress"][$school_id]["last_batch_id"] = $batch["batch_id"];
  $_SESSION["stats_report_major_students_progress"][$school_id]["last_timestamp"] = time();
  $_SESSION["stats_report_major_students_progress"][$school_id]["last_major"] = $major;
    
  
}


