<?php


class Student extends stdClass
{
  public $student_id, $name, $major_code_array, $major_code_csv, $gpa, $cumulative_hours, $catalog_year;
  public $list_courses_taken, $list_courses_advised, $list_courses_added, $db, $rank, $db_rank, $is_active;
  public $list_standardized_tests, $list_substitutions;
  public $list_transfer_eqvs_unassigned;
  public $array_settings, $array_significant_courses, $array_hide_grades_terms, $school_id;
  
  function __construct($student_id = "", DatabaseHandler $db = NULL, $school_id = NULL)
  {
    
    $this->student_id = $student_id;
    
    if ($school_id === NULL) {
      // Lookup this student's school_id if it was not supplied.
      $school_id = intval(db_result(db_query("SELECT school_id FROM users WHERE cwid = ? AND is_student = 1", array($student_id))));
    }
    
    
    $this->school_id = $school_id;
    $this->array_hide_grades_terms = array();
    $this->array_significant_courses = array();  // array of course_ids
    // the student has taken, or has subs for (or transfer eqv's).
    // used later to help speed up assignCoursesToList in FlightPath.

    $this->db = $db;
    if ($db == NULL)
    {
      $this->db = get_global_database_handler();
    }
    
    // Go ahead and load and assemble
    // all information in the database on this student.
    $this->load_student();    
                 
  }

  
  /**
   * This is a stub function.  If you are planning on hiding course grades
   * for a term at a time, you should override this method in /custom/classes
   * and place that logic here.
   * 
   * For example,
   * at ULM, students cannot see their final grades for a term until they
   * have completed their course evaluations for every course they took that
   * term, OR, until 2 weeks have passed.  
   * 
   * 
   *
   */
  function determine_terms_to_hide_grades()
  {
    return;
  }   
  
  
  function load_student()
  {

    $this->list_transfer_eqvs_unassigned = new CourseList();
    $this->list_courses_taken = new CourseList();
    $this->list_courses_added = new CourseList();

    $this->list_substitutions = new SubstitutionList();

    $this->list_standardized_tests = new ObjList();
    $this->array_settings = array();

    if ($this->student_id != "")
    {
      $this->determine_terms_to_hide_grades();      
      $this->load_transfer_eqvs_unassigned();
      $this->load_courses_taken();
      $this->load_student_data();
      $this->load_test_scores();
      $this->load_settings();
      $this->load_significant_courses();
      //$this->load_unassignments();
      //$this->load_student_substitutions();
      
      // If we are supposed to set cumulative hours and gpa, perform that
      // operation now.
      if (variable_get_for_school("calculate_cumulative_hours_and_gpa", 'no', $this->school_id) == 'yes') {     
       $arr = $this->calculate_cumulative_hours_and_gpa();
       $this->cumulative_hours = $arr["cumulative_total_hours"];    
       $this->gpa = $arr["cumulative_gpa"];   
      }
      
      
    }
    
    
    // When we load this student, let's also check for any hooks relating
    // to loading this student.
    // Since this class might be used outside of FP, only do this if we know
    // that the bootstrap.inc file has been executed.
    if ($GLOBALS["fp_bootstrap_loaded"] == TRUE) {      
      invoke_hook("student_load", array(&$this));
    }       
    
  }

  function load_significant_courses()
  {
    // This will attempt to add as much to the array_significant_courses
    // as we can, which was not previously determined.
    // For example: courses we were advised to take and/or
    // substitutions.

    // Now, when this gets called, it's actually *before* we
    // write any subs or advisings to the database, so what we
    // actually need to do is go through the POST
    // and add any course_id's we find.
    // In the future, perhaps it would be more efficient
    // to have just one POST variable to look at, perhaps
    // comma-seperated.
    

    // Look in the database of advised courses for ANY course advised in
    // the range of advisingTermIDs.
    $advising_term_ids = variable_get("available_advising_term_ids", "0", $this->school_id);;

    $temp = explode(",",$advising_term_ids);
    foreach ($temp as $term_id)
    {
      $term_id = trim($term_id);
      $res = $this->db->db_query("SELECT * FROM advising_sessions a,
              advised_courses b
              WHERE a.student_id = ?
              AND a.advising_session_id = b.advising_session_id
              AND a.term_id = ?             
              AND a.is_draft = 0 ", $this->student_id, $term_id);
      while ($cur = $this->db->db_fetch_array($res))
      {
        $this->array_significant_courses[$cur["course_id"]] = true;
      }
    }


    // Now, look for any course which a substitution might have been
    // performed for...
    $res = $this->db->db_query("SELECT * FROM student_substitutions
                    WHERE student_id = ? ", $this->student_id);
    while ($cur = $this->db->db_fetch_array($res)) {
      $this->array_significant_courses[$cur["required_course_id"]] = true;
    }


  }

  function load_significant_courses_from_list_courses_taken() {
    // Build the array_significant_courses
    // entriely from list_courses_taken.
    $this->list_courses_taken->reset_counter();
    while($this->list_courses_taken->has_more())
    {
      $c = $this->list_courses_taken->get_next();
      $this->array_significant_courses[$c->course_id] = true;
    }
  }
  
  
  function load_settings() {
    // This will load & set up the array_settings variable for this
    // student.
    $res = $this->db->db_query("SELECT settings FROM student_settings
                  WHERE 
                  student_id = ? 
                  ", $this->student_id);
    $cur = $this->db->db_fetch_array($res);

    if ($cur) {
      if ($arr = unserialize($cur["settings"])) {
        $this->array_settings = $arr;
      }
    }
        
  }

  function load_transfer_eqvs_unassigned()
  {
    $res = $this->db->db_query("SELECT `id`, transfer_course_id FROM student_unassign_transfer_eqv
                  WHERE student_id = ?
                  AND delete_flag=0
                  ORDER BY id ", $this->student_id);
    while($cur = $this->db->db_fetch_array($res))
    {
      extract ($cur, 3, "db");
      $new_course = new Course();
      $new_course->bool_transfer = true;      
      $new_course->course_id = $db_transfer_course_id;
      $new_course->school_id = $this->db->get_school_id_for_transfer_course_id($db_transfer_course_id);
      $new_course->db_unassign_transfer_id = $db_id;
    
      $this->list_transfer_eqvs_unassigned->add($new_course);

    }
  }


  function init_semester_courses_added()
  {
    // The "Add a Course" box on screen is really just a
    // semester, with the number -88, with a single group,
    // also numbered -88.
    $this->semester_courses_added = new Semester(DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED);
    $this->semester_courses_added->title = t("Courses Added by Advisor");

    // Now, we want to add the Add a Course group...
    $g = new Group();
    $g->group_id = DegreePlan::GROUP_ID_FOR_COURSES_ADDED;
    // Since it would take a long time during page load, we will
    // leave this empty of courses for now.  It doesn't matter anyway,
    // as we will not be checking this group for course membership
    // anyway.  We only need to load it in the popup.
    $g->hours_required = 99999;  // Nearly infinite selections may be made.
    $g->assigned_to_semester_num = DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED;
    $g->title = t("Add an Additional Course");

    $this->semester_courses_added->list_groups->add($g);

  }


  function load_unassignments()
  {
    // Load courses which have been unassigned from groups
    // or the bare degree plan.
    $res = db_query("SELECT * FROM student_unassign_group
                     WHERE 
                      student_id = ?                      
                      AND delete_flag='0' ", $this->student_id);
    while($cur = db_fetch_array($res))
    {
      extract ($cur, 3, "db");
      

      if ($taken_course = $this->list_courses_taken->find_specific_course($db_course_id, $db_term_id, (bool) $db_transfer_flag, TRUE, NULL, $db_degree_id))
      {
        // Add the group_id to this courses' list of unassigned groups.
        $new_group = new Group();
        $new_group->group_id = $db_group_id;
        $new_group->db_unassign_group_id = $db_id;
        $new_group->req_by_degree_id = $db_degree_id;

        $taken_course->group_list_unassigned->add($new_group);
      }

    }



  }



  function load_test_scores()
  {
    // If the student has any scores (from standardized tests)
    // then load them here.

    $st = null;

    $c = 0;
    $old_row = "";

    $res = db_query("SELECT * FROM student_tests
                     WHERE student_id = ?     
                     AND school_id = ?            
                     ORDER BY date_taken DESC, test_id, category_id ", $this->student_id, $this->school_id);    
    while($cur = db_fetch_array($res)) {
      
      $c++;
      
      extract($cur, 3, "db");
      
      if (!isset($db_position)) $db_position = 0;
      
      // Get the test's description, if available.
      $db_test_description = $db_category_description = "";      
      $res2 = db_query("SELECT * FROM standardized_tests
                        WHERE test_id = ?
                        AND category_id = ?
                        AND school_id = ?
                        ORDER BY position", $db_test_id, $db_category_id, $this->school_id);
      $cur2 = db_fetch_array($res2);
      if ($cur2) {
        $db_test_description = fp_trim($cur2["test_description"]);
        $db_category_description = fp_trim($cur2["category_description"]);
      }
      
      // Did we find anything in the table?  If not, just use the codes themselves
      if ($db_test_description == "") $db_test_description = t("Test code:") . " " . $db_test_id;
      if ($db_category_description == "") $db_category_description = $db_category_id;
                  
      if (!(($db_date_taken . $db_test_id) == $old_row))
      {
        // We are at a new test.  Add the old test to our list.
        if ($st != null) {          
          $this->list_standardized_tests->add($st);

        }

        $st = new StandardizedTest();
        $st->test_id = $db_test_id;
        $st->date_taken = $db_date_taken;
        
        // Is the date unavailable?
        if ($st->date_taken == "" || $st->date_taken == NULL || strstr($st->date_taken, "0000")) {
          $st->bool_date_unavailable = TRUE;
        }
        
        $st->description = $db_test_description;
        $old_row = $db_date_taken . $db_test_id;

      }

      $st->categories[$db_position . $c]["description"] = $db_category_description;
      $st->categories[$db_position . $c]["category_id"] = $db_category_id;
      $st->categories[$db_position . $c]["score"] = $db_score;

    }

    // Add the last one created.
    if ($st != null) {
      $this->list_standardized_tests->add($st);
    }


  }


  function load_student_substitutions()
  {
    // Load the substitutions which have been made for
    // this student.
    
    // Meant to be called AFTER load_courses_taken.
    $this->list_substitutions = new SubstitutionList();
    
    $res = $this->db->db_query("SELECT * FROM
            student_substitutions
            WHERE student_id = ?            
            AND delete_flag='0' ", $this->student_id);
    while($cur = $this->db->db_fetch_array($res))
    {

      $sub_id = $cur["id"];
      $sub_course_id = $cur["sub_course_id"];
      $sub_term_id = $cur["sub_term_id"];
      $sub_bool_transfer = (bool) $cur["sub_transfer_flag"];
      $sub_hours = $cur["sub_hours"] * 1;
      $sub_remarks = trim($cur["sub_remarks"]);
      $faculty_id = $cur["faculty_id"];
      $req_by_degree_id = $cur["required_degree_id"];
      $db_required_degree_id = $req_by_degree_id;
      $db_required_group_id = $cur["required_group_id"];
            
      if (strstr($sub_term_id, "9999"))
      {
        // was an unknown semester.  Let's set it lower so
        // it doesn't screw up my sorting.
        $sub_term_id = Course::COURSE_UNKNOWN_TERM_ID;
      }


      // Okay, look to see if we can find the course specified by this
      // courseSubstitution within the list of courses which the student
      // has taken.  If the subHours is less than the hours_awarded for the
      // particular course, it means the course has been split up!
            
      if($taken_course = $this->list_courses_taken->find_specific_course($sub_course_id, $sub_term_id, $sub_bool_transfer, true, null, $req_by_degree_id))
      { 
                                
        // If this takenCourse is a transfer credit, then we want to remove
        // any automatic eqv it may have set.
        // We can do this easily by setting its course_id to 0.
        if ($sub_bool_transfer == true)
        {
          $taken_course->temp_old_course_id = $taken_course->course_id;
          $taken_course->course_id = 0;
          $taken_course->course_id = 0;
        }

        if ($sub_hours == 0)
        { // If none specified, assume its the full amount.         
          $sub_hours = $taken_course->get_hours_awarded($req_by_degree_id);
        }


        if (($taken_course->get_hours_awarded($req_by_degree_id) > $sub_hours))
        {
          
          // Okay, now this means that the course which we are
          // using in the substitution-- the course which the student
          // has actually taken-- is being split up in the substitution.
          // We are only using a portion of its hours.
          // We MUST round, because if there is a decimal place, we might run into
          // trouble.  Because, for example, 2.001 - 2 actually gets .00009999999 instead of .001.
          // The most decimals we can have is 4, so let's round to 6 decimal places, just to give
          // us some breathing room.  That should take care of us without losing too much precision.
          $remaining_hours = round(($taken_course->get_hours_awarded($req_by_degree_id) - $sub_hours), 6);
          
          // Create a clone of the course with the leftover hours, and add
          // it back into the list_courses_taken.
          $new_course_string = $taken_course->to_data_string();
          $new_course = new Course();
          $new_course->load_course_from_data_string($new_course_string);
          $new_course->random_id = mt_rand(1,9999);
          
          
          $new_course->set_details_by_degree($req_by_degree_id, "bool_substitution_split", TRUE);
          $new_course->set_details_by_degree($req_by_degree_id, "bool_substitution_new_from_split", TRUE);
          
          
          $new_course->subject_id = $taken_course->subject_id;
          $new_course->course_num = $taken_course->course_num;
          
          $new_course->set_hours_awarded($req_by_degree_id, $remaining_hours);          
          $new_course->req_by_degree_id = $req_by_degree_id;
          
          if (is_object($new_course->course_transfer))
          {
            $new_course->course_transfer->set_hours_awarded($req_by_degree_id, $remaining_hours);
          }

          //$taken_course->bool_substitution_split = true;
          $taken_course->set_details_by_degree($req_by_degree_id, "bool_substitution_split", TRUE);
          
          $taken_course->set_hours_awarded($req_by_degree_id, $sub_hours);
          
          if (is_object($taken_course->course_transfer))
          {
            $taken_course->course_transfer->set_hours_awarded($req_by_degree_id, $sub_hours);
          }
                              
          // Add the newCourse back into the student's list_courses_taken.
          $this->list_courses_taken->add($new_course);

        }

        // Place in details_by_degree_array...        
        //$taken_course->substitution_hours = $sub_hours;
        $taken_course->set_details_by_degree($req_by_degree_id, "substitution_hours", $sub_hours);
                
        $taken_course->set_bool_substitution($req_by_degree_id, TRUE);
        $taken_course->display_status = "completed";
        $taken_course->db_substitution_id_array[$req_by_degree_id] = $sub_id;

        $substitution = new Substitution();
        $substitution->school_id = $this->school_id;
 
        if ($cur["required_course_id"] > 0)
        {
          $course_requirement = new Course($cur["required_course_id"]);
          
          $this->array_significant_courses[$course_requirement->course_id] = true;

        } else 
        {
          // This is a group addition!
                    
          $course_requirement = new Course($sub_course_id, $sub_bool_transfer);
          $this->array_significant_courses[$sub_course_id] = true;          
          $substitution->bool_group_addition = true;
        }

        //$course_requirement->assigned_to_group_id = $cur["required_group_id"];
        $course_requirement->assigned_to_group_ids_array[$cur["required_group_id"]] = $cur["required_group_id"];
        
        $course_requirement->assigned_to_semester_num = $cur["required_semester_num"];
        $course_requirement->req_by_degree_id = $req_by_degree_id;

        //$taken_course->assigned_to_group_id = $cur["required_group_id"];
        $taken_course->assigned_to_group_ids_array[$cur["required_group_id"]] = $cur["required_group_id"];
        $taken_course->assigned_to_semester_num = $cur["required_semester_num"];
        $taken_course->req_by_degree_id = $req_by_degree_id;

        $substitution->course_requirement = $course_requirement;

        
        $substitution->course_list_substitutions->add($taken_course);
        

        $substitution->remarks = $sub_remarks;
        $substitution->faculty_id = $faculty_id;
        $substitution->db_substitution_id = $sub_id;
        $substitution->assigned_to_degree_id = $req_by_degree_id;
        $substitution->db_required_degree_id = $req_by_degree_id;
        $substitution->db_required_group_id = $db_required_group_id;
        
        $this->list_substitutions->add($substitution);

      }

    }   

    
        
    
  } //load_student_substitutions


  /**
   * This loads a student's personal data, like name and so forth.
   *
   */
  function load_student_data() {

    $cur = null;
    if (isset($GLOBALS['load_student_data'][$this->student_id])) {
      $cur = $GLOBALS['load_student_data'][$this->student_id];
    } 
    else {
      $res = $this->db->db_query("SELECT * FROM students WHERE cwid = ? ", array($this->student_id));
      $cur = $this->db->db_fetch_array($res);
      $GLOBALS['load_student_data'][$this->student_id] = $cur;
    }
    
    if ($cur) {    
      $this->is_active = intval($cur['is_active']);
      $this->cumulative_hours = $cur['cumulative_hours'];
      $this->school_id = $this->db->get_school_id_for_student_id($this->student_id);
      $this->gpa = $cur['gpa'];
      $this->db_rank = $cur['rank_code'];
      $this->catalog_year = $cur['catalog_year'];
      $this->rank = $this->get_rank_description($this->db_rank);
      $this->name = $this->db->get_student_name($this->student_id);
    }
    
    $this->major_code_array = fp_get_student_majors($this->student_id, FALSE);
    $this->major_code_csv = '';
    foreach ($this->major_code_array as $k => $v) {
      $this->major_code_csv .= $k . ',';
    }
    $this->major_code_csv = rtrim($this->major_code_csv,',');


   
  }

  /**
   * This function will look at the courses which the student has taken, to calculate
   * the cumulative hours and gpa, rather than just load them from the db table.
   * 
   * It will then return the values in an assoc array for later use.  For example, you
   * may want to set $this->cumulative_hours and $this->gpa to them.
   *
   */
  function calculate_cumulative_hours_and_gpa() {
    
    $cumulative_hours = 0;
    $cumulative_points = 0;
    
    $cumulative_total_hours = $this->list_courses_taken->count_credit_hours("", FALSE, TRUE, FALSE);
    $cumulative_quality_hours = $this->list_courses_taken->count_credit_hours("", FALSE, TRUE, TRUE);
    $cumulative_quality_points = $this->list_courses_taken->count_credit_quality_points("", FALSE, TRUE);
    
    $cgpa = FALSE;
    if ($cumulative_quality_hours > 0) {
      $cgpa = fp_truncate_decimals($cumulative_quality_points / $cumulative_quality_hours, 3);
    } 
    
      
    return array(
      "cumulative_total_hours" => $cumulative_total_hours,
      "cumulative_quality_hours" => $cumulative_quality_hours,
      "cumulative_quality_points" => $cumulative_quality_points,
      "cumulative_gpa" => $cgpa,
    );
    
  }
  
  
  
  /**
   * Given a rank_code like FR, SO, etc., get the english
   * description. For example: Freshman, Sophomore, etc.
   *   
   */
  function get_rank_description($rank_code = "") {

    // Get our rank descriptions from our setting.        
    $temp = variable_get("rank_descriptions", "FR ~ Freshman\nSO ~ Sophomore\nJR ~ Junior\nSR ~ Senior\nPR ~ Professional\nGR ~ Graduate");
    $lines = explode("\n", $temp);
    foreach ($lines as $line) {
      $temp = explode("~", $line);
      $rank_array[trim($temp[0])] = fp_trim(@$temp[1]);
    }            
        
    $rank_desc = @$rank_array[$rank_code]; 
    
    // If a description isn't found, just return the code itself.
    if ($rank_desc == '') $rank_desc = $rank_code;
    
    return $rank_desc;
        
  }
  
  

  

  /**
   * Enter description here...
   * Returns the major code and trackCode, if it exists in this form:
   *  MAJOR|CONC_TRACK
   *  Though usually it will be:
   * MAJR|_TRCK
   * Asumes you have already called "load_settings()";
   */
  function get_major_and_track_code($bool_ignore_settings = false)
  {

    $rtn = "";
    $major_code = "";

    if (@$this->array_settings["major_code_csv"] != "") {
      $rtn = $this->array_settings["major_code_csv"];
    }
    
    /*
    if ($this->array_settings["major_code"] != "")
    { // If they have settings saved, use those...
      if ($this->array_settings["track_code"] != "")
      {
        // if it does NOT have a | in it already....
        if (!strstr($this->array_settings["major_code"], "|"))
        {
          $rtn = $this->array_settings["major_code"] . "|_" . $this->array_settings["track_code"];
        } else {
          // it DOES have a | already, so we join with just a _.  This would
          // be the case if we have a track AND an concentration.
          $rtn = $this->array_settings["major_code"] . "_" . $this->array_settings["track_code"];
        }
      } else {
        $rtn = $this->array_settings["major_code"];
      }
      $major_code = $this->array_settings["major_code"];
    } else {
      $rtn = $this->major_code;
    }
    */
    
    if ($bool_ignore_settings == true) {
      $rtn = $this->major_code;
    }


    return $rtn;

  }

  
  
  function load_courses_taken($bool_load_transfer_credits = true)
  {

    $retake_grades = csv_to_array(variable_get_for_school("retake_grades",'', $this->school_id));
    
    $not_released_grades_terms = csv_to_array(variable_get_for_school("not_released_grades_terms", '', $this->school_id));
    
    // This will create and load the list_courses_taken list.
    // contains SQL queries to fully create the list_courses_taken.
    $res = $this->db->db_query("SELECT *  FROM student_courses                  
                               WHERE 
                                student_id = ? 
                                ", $this->student_id);
  
    while($cur = $this->db->db_fetch_array($res)) {

      // Create a course object for this course...
      $is_transfer = false;
      $course_id = $this->db->get_course_id($cur["subject_id"], $cur["course_num"], '', FALSE, $this->school_id, TRUE);

      if (!$course_id) {
        fpm("Course not found while trying to load student data: {$cur["subject_id"]} {$cur["course_num"]}");
        continue;
      }

      // Are these grades (terms) not released yet?
      if (in_array($cur["term_id"], $not_released_grades_terms)) {
        $cur["grade"] = "";
      }

      $new_course = new Course();
      $new_course->course_id = $course_id;

      // Load descriptive data for this course from the catalog (so we can get min, max, and repeat hours)
      $new_course->load_descriptive_data();

      // Now, over-write whatever we got from the descriptive data with what the course was called
      // when the student took it.
      $new_course->subject_id = $cur["subject_id"];
      $new_course->course_num = $cur["course_num"];
      $new_course->db_grade = $cur["grade"];
      $new_course->grade = fp_translate_numeric_grade($cur['grade'], $this->school_id);
      $new_course->term_id = $cur["term_id"];
      $new_course->level_code = $cur["level_code"];
      
      // Is this grade supposed to be hidden from students (and this user is probably
      // a student)
      if (in_array($new_course->term_id, $this->array_hide_grades_terms)
        && !user_has_permission("can_advise_students")) 
      {
        $new_course->bool_hide_grade = true;
      }     
      
      $new_course->set_hours_awarded(0, $cur["hours_awarded"] * 1);
      $new_course->display_status = "completed";
      $new_course->bool_taken = true;
      
      // Was this course worth 0 hours but they didn't fail it?
      // If so, we need to set it to actually be 1 hour, and
      // indicate this is a "ghost hour."
      if (!in_array($new_course->grade, $retake_grades) 
           && $new_course->get_hours_awarded() == 0)      
      {
        $new_course->set_hours_awarded(0, 1);
        $new_course->bool_ghost_hour = TRUE;
      }     
      
      // Now, add the course to the list_courses_taken...
      $this->list_courses_taken->add($new_course);
      $this->array_significant_courses[$course_id] = true;
      
    }


    
    if ($bool_load_transfer_credits == false) {
      return;
    }
    
    ////////////////////////////////////////////////////////////
    // Tranfer credits?  Get those too...
    
    $res = $this->db->db_query("
                        SELECT *
                        FROM student_transfer_courses a, 
                             transfer_courses b 
                        WHERE a.transfer_course_id = b.transfer_course_id                       
                        AND a.student_id = ?", $this->student_id);

    while($cur = $this->db->db_fetch_array($res))
    {
      $transfer_course_id = $cur['transfer_course_id'];
      $institution_id = $cur["institution_id"];

      $new_course = new Course();

      
      
      // Find out if this course has an eqv.
      if ($course_id = $this->get_transfer_course_eqv($transfer_course_id, FALSE, "", $cur["hours_awarded"]))
      {
        $new_course = new Course($course_id);       
        $this->array_significant_courses[$course_id] = true;
        
      }



      $t_course = new Course();
      $t_course->subject_id = $cur['subject_id'];
      $t_course->course_num = $cur['course_num'];
      $t_course->level_code = $cur['level_code'];
      $t_course->course_id = $transfer_course_id;
      $t_course->school_id = intval($cur['school_id']);
      $t_course->bool_transfer = true;
      $t_course->institution_id = $institution_id;

      $new_course->bool_transfer = true;

      $new_course->course_transfer = $t_course;
      $new_course->db_grade = $cur['grade'];
      $new_course->grade = fp_translate_numeric_grade($cur['grade'], $this->school_id);
      $new_course->school_id = intval($cur['school_id']);
      $t_course->db_grade = $cur['grade'];
      $t_course->grade = fp_translate_numeric_grade($cur['grade'], $this->school_id);
      
      $new_course->set_hours_awarded(0, $cur['hours_awarded'] * 1);
      $t_course->set_hours_awarded(0, $cur['hours_awarded'] * 1);
      
      $new_course->level_code = $t_course->level_code;
      
      // Was this course worth 0 hours but they didn't fail it?
      // If so, we need to set it to actually be 1 hour, and
      // indicate this is a "ghost hour."
      if (!in_array($new_course->grade, $retake_grades) 
           && $new_course->get_hours_awarded() == 0)      
      {
        $new_course->set_hours_awarded(0, 1);
        $new_course->bool_ghost_hour = TRUE;
        $t_course->set_hours_awarded(0, 1);
        $t_course->bool_ghost_hour = TRUE;
      }           
      
      $new_course->bool_taken = true;
      $t_course->bool_taken = true;
      

      $new_course->term_id = $cur['term_id'];
      if (strstr(fp_trim(@$new_course->term_id), "9999")) {
        // was an unknown semester.  Let's set it lower so
        // it doesn't screw up my sorting.
        $new_course->term_id = Course::COURSE_UNKNOWN_TERM_ID;
      }
      $t_course->term_id = $new_course->term_id;
      $new_course->display_status = "completed";

      $this->list_courses_taken->add($new_course);
    }
    //    print_pre($this->list_courses_taken->to_string());

  }



  /**
   * This function will find the best grade the student made on a particular course, and return it.
   * 
   * It will return FALSE if the student never took the course.
   *
   * @param unknown_type $course
   */
  function get_best_grade_for_course(Course $course) {
      
    $rtn = FALSE;
    
    // To help speed things up, let's see if we have cached this course already.
    if (isset($GLOBALS['fp_temp_cache_' . $this->student_id][$this->school_id]['best_grade_for_course'][$course->course_id])) {
      return $GLOBALS['fp_temp_cache_' . $this->student_id][$this->school_id]['best_grade_for_course'][$course->course_id];
    }
        
    $c = $this->list_courses_taken->find_best_grade_match($course);
    
    if ($c) {
      $rtn = $c->grade;
    }
        
    // Set into our cache
    $GLOBALS['fp_temp_cache_' . $this->student_id][$this->school_id]['best_grade_for_course'][$course->course_id] = $rtn;    
    
        
    return $rtn;
    
  }
  
  
  
  
  
  
  /**
   * Find a transfer eqv for this student, for this course in question.
   *
   */
  function get_transfer_course_eqv($transfer_course_id, $bool_ignore_unassigned = false, $require_valid_term_id = "", $require_hours = -1)
  {
    
    // First, make sure that this transfer course hasn't
    // been unassigned.  Do this by checking through
    // the student's courseListUnassignedTransferEQVs.
    $temp_course = new Course();
    $temp_course->course_id = $transfer_course_id;
    if ($bool_ignore_unassigned == false && $this->list_transfer_eqvs_unassigned->find_match($temp_course)) {
      // The transfer course in question has had its eqv removed,
      // so skip it!      
      return false;
    }

    $require_hours = floatval($require_hours);
    
    $valid_term_line = "";
    if ($require_valid_term_id != "") {
      // We are requesting eqv's only from a particular valid term, so, amend
      // the query.
      $valid_term_line = "AND valid_term_id = $require_valid_term_id ";
    }
    
        
    // Does the supplied transfer course ID have an eqv?
    $res = $this->db->db_query("
                        SELECT local_course_id FROM transfer_eqv_per_student
                        WHERE transfer_course_id = ?
                        AND student_id = ?                        
                        AND broken_id = 0
                        $valid_term_line  ", $transfer_course_id, $this->student_id);

    if ($cur = $this->db->db_fetch_array($res)) {
      $local_course_id = $cur['local_course_id'];
      
      // If we require that the local course have the same number of hours
      // as the transfer, then check that now.
      if ($require_hours != -1) {
        $temp_course = new Course($local_course_id);
        $temp_course->max_hours = floatval($temp_course->max_hours);
        $temp_course->min_hours = floatval($temp_course->min_hours);
        
        if (($temp_course->max_hours < $require_hours) || ($temp_course->min_hours > $require_hours)) {          
          return FALSE;
        }
        else {
          return $local_course_id;
        }
      } 
      else {
        return $local_course_id;
      }   
      
      
    }
 
    return false;

  }
  
  
  function to_string()  {
    $rtn = "Student Information:\n";
    $rtn .= " Courses Taken:\n";
    $rtn .= $this->list_courses_taken->to_string();
    return $rtn;
  }

} // end class Student
