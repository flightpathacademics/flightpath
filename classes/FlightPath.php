<?php

class FlightPath extends stdClass
{
  public $student, $degree_plan, $db, $bool_what_if;
  public $course_list_advised_courses;


  function __construct($student = "", $degree_plan = "", DatabaseHandler $db = null, $bool_perform_full_init = false)
  {
    if ($student != "")
    {
      $this->student = $student;
    }
    if ($degree_plan != "")
    {
      $this->degree_plan = $degree_plan;
    }

    if ($db != null)
    {
      $this->db = $db;
    } else {
      $this->db = get_global_database_handler();
    }

    if ($bool_perform_full_init == true)
    {
      $this->init(true); 
    }                                                      

    $this->course_list_advised_courses = new CourseList();

  }


  function init($bool_init_advising_variables = false, $bool_ignore_what_if_advising_variables = false, $bool_load_full = true) {
    
    global $current_student_id, $user, $student;
    // This will initialize this flightPath object
    // based on what is available in the global variables.
    // Takes the place of what was going on at the beginning
    // of advise.php.
        
    if ($bool_init_advising_variables == true)
    {
      //$temp_screen = new AdvisingScreen();
      //$temp_screen->init_advising_variables($bool_ignore_what_if_advising_variables);
      //advise_load_advising_variables_from_db($current_student_id, $user->id);
      advise_init_advising_variables();
    }

    $major_code_csv = $GLOBALS["fp_advising"]["advising_major_code"];
    
    //$track_code = $GLOBALS["fp_advising"]["advising_track_code"];

    $track_degree_ids = $GLOBALS["fp_advising"]["advising_track_degree_ids"];
            
    $student_id = $GLOBALS["fp_advising"]["advising_student_id"];
    $advising_term_id = $GLOBALS["fp_advising"]["advising_term_id"];
    @$available_terms = $GLOBALS["fp_advising"]["available_advising_term_ids"];

    // Keep in mind-- at this point, major_coe might be a CSV of major codes.

    $this->bool_what_if = false;

    // Are we in WhatIf mode?
    if ($GLOBALS["fp_advising"]["advising_what_if"] == "yes")
    {
      $major_code_csv = $GLOBALS["fp_advising"]["what_if_major_code"];
      
      $track_degree_ids = $GLOBALS["fp_advising"]["what_if_track_degree_ids"];
      
      $this->bool_what_if = true;

    }

    if ($bool_load_full == false)
    { // not trying to load anything, so return.
      return;
    }

    $db = $this->db;


    if ($bool_load_full == true)
    {
      $student = new Student($student_id);
    } else {
      $student = new Student();
      $student->student_id = $student_id;

    }

  
    $student_school_id = $db->get_school_id_for_student_id($student_id);
  

    $catalog_year = $student->catalog_year;
    if ($this->bool_what_if)
    {
      // We need to look at a "what_if_catalog_year" value.
      $catalog_year = $GLOBALS["fp_advising"]["what_if_catalog_year"];
      
      if ($catalog_year == "" || $catalog_year == 0) {
        // Some problem, default to current cat year.
        $catalog_year = variable_get_for_school("current_catalog_year",'',$student_school_id);  
      }
      
    }

    // make sure their catalog year is not past the system's current
    // year setting.
    if ($catalog_year > variable_get_for_school("current_catalog_year",'',$student_school_id) && variable_get_for_school("current_catalog_year",'',$student_school_id) > intval(variable_get_for_school("earliest_catalog_year", 2006, $student_school_id)))
    { // Make sure degree plan is blank if it is!
      $catalog_year = 99999;
    }

    if ($GLOBALS["fp_advising"]["advising_update_student_settings_flag"] != "")
    {
      
      // TODO: This is used in What If mode.  We need to make sure that these track_degree_ids
      // actually do match to the selected majors.  If not, then we should remove them.  This
      // is to make sure that when we switch What If settings, these get cleared. 
      
      $wi = "";
      if ($this->bool_what_if) {
        $wi = "what_if_";
      }
      //$student->array_settings["track_code"] = $track_code;
      $student->array_settings[$wi . "track_degree_ids"] = $track_degree_ids;
      $student->array_settings[$wi . "major_code_csv"] = $major_code_csv;
    }

    

    // Let's add the track_degree_ids to the major_code_csv...
    $major_code_csv .= "," . $track_degree_ids;    
        
    ///////////////////////////////
    //  Okay folks.  So this is basically where we will be iterating through all
    //  of the student's major_codes (if they have more than one), and squishing them together,
    //  to create a single DegreePlan object, made out of all the options.
    //  We need like a loop here.
    $degree_plans = array();
    $temparr = explode(",", $major_code_csv);
      
    foreach ($temparr as $major_code) {   
    
      $t_major_code = $major_code;
  
      if (trim($major_code) == "") continue;

      // If we are dealing with a track, then just get the degree_id directly, else, figure out the
      // degree_id from the t_major_code.
      
      if (is_numeric($t_major_code)) {
        $degree_id = $t_major_code;
      }
      else {
        $degree_id = $db->get_degree_id($t_major_code, $catalog_year, FALSE, $student_school_id);
      }
            
      // If we couldn't find the degree_id, then we should just skip it.
      if (!$degree_id || $degree_id == 0) {
        if (variable_get("warning_on_view_if_degree_not_found", "yes") == 'yes') {
          
          fp_add_message(t("Could not find degree %maj in catalog year %cat.  FlightPath will
                            skip loading this degree for now.  If this message continues to appear,
                            contact your advisor.", array("%maj" => $t_major_code, "%cat" => $student->catalog_year . "-" . ($student->catalog_year + 1))), "error", TRUE);
          
          
        }
        continue;
      }
      
       
      if ($bool_load_full == true)
      {
        $this->student = $student;
        $degree_plan = fp_load_degree($degree_id, $db, FALSE, $student->array_significant_courses);
        //$degree_plan = new DegreePlan($degree_id, $db, FALSE, $student->array_significant_courses);
        $degree_plan->add_semester_developmental($student->student_id);
        //$this->degree_plan = $degree_plan;
                
        $degree_plans[$degree_plan->degree_id] = $degree_plan;
      }


    } // foreach temparr as major_code.  The foreach loop through all our major codes.

        
    // Okay, coming out of this, we have an array of degree_plans  (maybe).  If it's just one, then set it to that one.
    if (count($degree_plans) == 1) {
      $this->degree_plan = $degree_plan;
    }
    else if (count($degree_plans) > 1) {
      // Okay folks, here's the magic.  We need to combine the degree plans in this array
      // into 1 generated degree plan.
    
      $combined_degree_plan = $this->combine_degree_plans($degree_plans, $student_id, $student_school_id);
      $combined_degree_plan->db_allow_dynamic = 1;  // since they are combined, we must be allowing dynamic.
    
      $this->degree_plan = $combined_degree_plan;
      
    } // else if count(degree_plans) > 1
    else if (count($degree_plans) < 1) {
      // Meaning, we couldn't find any degree plans for this student!  Let's just load some blank objects.
      $this->degree_plan = new DegreePlan();
      $this->student = $student;      
    }

    // Invoke a hook which lets other modules act on this student and degree plan we just loaded.
    invoke_hook('init_flightpath_degree', array($this->student->student_id, &$this->degree_plan));    

        
           
    
    $wi = "";
    if ($this->bool_what_if) $wi = "_what_if";

    if (isset($this->degree_plan) && $this->degree_plan != null) {
      // Add degree_plan to a simple cache to make our lives easier later on.
      $_SESSION["fp_simple_degree_plan_cache_for_student" . $wi] = array(
        "cwid" => $student_id,
        "degree_id" => $this->degree_plan->degree_id,
        "combined_degree_ids_array" => $this->degree_plan->combined_degree_ids_array,      
        "is_combined_dynamic_degree_plan" => $this->degree_plan->is_combined_dynamic_degree_plan,      
      );
    }

 


  }  // end of function fp_init



  /**
   * This function is responsible for combining multiple degree plans into a single unified degree plan,
   * then returning it.
   */
  function combine_degree_plans($degree_plans, $student_id, $school_id = 0) {
    
    //DEV: return $degree_plans[0];
    
    $new_degree_plan = new DegreePlan();
    $new_degree_plan->school_id = intval($school_id);
    // Loop through the degree plans one at a time...
    foreach ($degree_plans as $degree_plan) {
      
      $new_degree_plan->combined_degree_ids_array[] = intval($degree_plan->degree_id);
      
      $new_degree_plan->add_to_required_course_id_array($degree_plan->required_course_id_array);
      
      // Is there a public note to copy over?
      if (isset($degree_plan->public_notes_array[$degree_plan->degree_id])) {
        $new_degree_plan->public_notes_array[$degree_plan->degree_id] = $degree_plan->public_notes_array[$degree_plan->degree_id];
      }
      
      if ($degree_plan->catalog_year == "" || $degree_plan->catalog_year == 0) {
        $degree_plan->load_descriptive_data();
      }
      $new_degree_plan->catalog_year = $degree_plan->catalog_year;
      // Okay, now copy whatever we need to into the new_degree_plan.
      
      // First, go through its list of semesters, and copy out all the courses and groups      
      $degree_plan->list_semesters->reset_counter();
      while ($degree_plan->list_semesters->has_more()) {
        $sem = $degree_plan->list_semesters->get_next();
        
        // If the semester is the Added by Advisor (-88), or the developmental block (-55) then skip for now.
        if ($sem->semester_num == DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED || $sem->semester_num == DegreePlan::SEMESTER_NUM_FOR_DEVELOPMENTALS) {
          // This is the special one that is "added by advisor".  Skip it.  But remember to add it later to the finished product.
          // Or, it's the developmentals block.  Add that later too.
          continue;
        }
        
        // Okay, the semester contains a CourseList called list_courses, and a GroupList caled list_groups.
        $new_list_courses = $sem->list_courses->get_clone();
        
        // Now, let's add them to the new_degree_plan's semester...
        // if the new degree plan already has this semester, get that instead.
        $the_semester = $new_degree_plan->get_semester($sem->semester_num);
        
        if (!$the_semester) {
          $the_semester = new Semester($sem->semester_num);
          $new_degree_plan->list_semesters->add($the_semester);
        }
        
        
        // Should we rename the_semester's title (because the semester we are grabbing is overriding?)
        //$degree_plan->load_descriptive_data();   // Doesn't appear to be necessary at this stage.     
        $stitle = fp_trim(@$degree_plan->array_semester_titles[$sem->semester_num]);
        if ($stitle != "") {          
           // Meaning we should overwrite the semester title, because this degree isn't using the default title
           $the_semester->title = $stitle;
           $the_semester->bool_using_default_title = FALSE;
        }        
        
        
        // Okay, now add the courses to the_semester     
        $the_semester->list_courses->add_list($new_list_courses);
        
        
        ////////////////////////////
        // Getting the list of groups is going to be similar to getting our list of courses.
        // Go through the list of groups for this semester.        
        $new_list_groups = $sem->list_groups->get_clone(FALSE, FALSE, FALSE);
                
        // Okay, now we can add to our semester
        
        $the_semester->list_groups->add_list($new_list_groups); 
        // Also add this group list to the degree plan's list_groups.
        $new_degree_plan->list_groups->add_list($new_list_groups);
        
      } // while, listing semesters in degree plan.
      
      
    } // foreach degree_plans

    //////////////////////////////    
    
    // Okay, now to put the finishing touches on the $new_degree_plan

    // Add in the "Developmental Req's" if required for this student.
    $new_degree_plan->add_semester_developmental($student_id);     
        
    // Add in the "Courses added by advisor"
    $new_degree_plan->add_semester_courses_added(); 
    
    if (count($degree_plans) > 1) {
      $new_degree_plan->is_combined_dynamic_degree_plan = TRUE;
      $new_degree_plan->degree_id = DegreePlan::DEGREE_ID_FOR_COMBINED_DEGREE;  // use constant to make it easier later on.
    }
    
    // Make sure all of our groups have reloaded any missing courses.
    $new_degree_plan->list_semesters->reset_counter();
    while ($new_degree_plan->list_semesters->has_more()) {
      $sem = $new_degree_plan->list_semesters->get_next();
      $sem->list_groups->reload_missing_courses();
    }    
    
    return $new_degree_plan;
    
  } // combine_degree_plans






  /**
   *    This function will check to see if we are trying to save
   * the draft from a tab change.  It should be near the top
   *  of all of FP's "tab" pages, like Main, Comments, etc.
   *
   */
  function process_request_save_draft()
  {

    /////////////////////////////////////
    ///  Are we trying to save the draft
    ///  from a tab change?
    /////////////////////////////////////
    if ($_REQUEST["save_draft"] == "yes")
    {
      $this->init(true, false, false);
      // If we are coming from the WhatIf tab, we need to save
      // as WhatIf.  Else, save as normal.
      if ($_REQUEST["from_w_i"] == "yes")
      {
        // Yes, we are coming from WhatIf mode, so
        // save under WhatIf.

        $GLOBALS["advising_what_if"] = "yes";
        $this->init(false);

      } else {
        // NOT coming from WhatIf mode.  Save as a normal draft.
        $GLOBALS["advising_what_if"] = "no";
        $this->init(true, true);

      }
      $this->save_advising_session_from_post(0,true);
    }

  }


  function assign_courses_to_groups() {
      
                  
    // This method will look at the student's courses
    // and decide which groups they should be fit into.
        
    // We will be going through the degree plan's master list
    // of groups to decide this.
    $student = $this->student;
    $school_id = $student->school_id;
    
    $this->degree_plan->list_groups->sort_priority();
    // Now, sort by the advising weight of the degree itself.    
    $this->degree_plan->list_groups->sort_degree_advising_weight();
    
    
    $this->degree_plan->list_groups->reset_counter();
    while($this->degree_plan->list_groups->has_more())
    {
      $g = $this->degree_plan->list_groups->get_next();

      if ($g->group_id == DegreePlan::GROUP_ID_FOR_COURSES_ADDED)
      {
        // Add a course group.  Skip.
        continue;
      }

      // Does the student have any group additions for this
      // group?  Technically it is a substitution.
      // We will add them in now, because we do not take additions
      // into consideration when figuring out branches.
      if ($course_list_additions = $student->list_substitutions->find_group_additions($g))
      {       
        $course_list_additions->reset_counter();
        while($course_list_additions->has_more())
        {
          $cA = $course_list_additions->get_next();
          $new_course = new Course();
          $new_course->course_id = $cA->course_id;

          if ($cA->bool_transfer == true)
          {
            if ($cA->course_id == 0 && is_object($cA->course_transfer))
            { // This is a transfer course which has been added.
              $new_course->course_id = $cA->course_transfer->course_id;
            }
            $new_course->bool_transfer = true;
          }

          $new_course->assigned_to_semester_num = $g->assigned_to_semester_num;
          $new_course->requirement_type = $g->requirement_type;
          // Add this course as a requirement.
          //$new_course->load_descriptive_data();
          $g->list_courses->add($new_course, true);
          // Later on, when we do assign_courses_to_list, it
          // will automatically find this course and apply the
          // substitution.
        }
      }

      // First we see if there are any bare courses at this level.  If there
      // are, then this group has NO branches!  Otherwise, the courses must
      // always be contained in a branch!
      if (!$g->list_courses->is_empty)
      {
        
        // Yes, there are courses here.  So, assign them at this level.        
        $this->assign_courses_to_list($g->list_courses, $this->student, true, $g, true);        
        // Okay, if we have fulfilled our courses at this level.

        // then we can continue on to the next "top level" group.
        //continue;
      }



      if (!$g->list_groups->is_empty)
      {
        /*
        Now we've got some trouble.  This is our first level of groups.
        If this object exists, then it means that this group branches off
        at its first level.  So, instead of actually assigning courses to
        groups, we need to find out which group has the most matches, and THEN
        we will assign them.
        */

               
        
        
        $g->reload_missing_courses();

        $high_count = -1;
        $best_branch = -1;
        
        // Sort our list of groups by student's grades, if applicable.        
        $sort_policy = variable_get_for_school("initial_student_course_sort_policy", "alpha", $school_id); // will either be "alpha" or "grade"
        if ($sort_policy == "grade") {
          $g->list_groups->sort_best_grade_first_by_student_grades($student);
        }
        
        
        $g->list_groups->reset_counter();
        while($g->list_groups->has_more())
        {
          $branch_one = $g->list_groups->get_next();
          if (!$branch_one->list_courses->is_empty)
          {
            // This does not actually assign.  Just counts.
            $count = $this->get_count_of_matches($branch_one, $this->student, $g);
            $branch_one->count_of_matches = $count;

            if ($count > $high_count)
            {
              $high_count = $count;
              
              $best_branch = $g->list_groups->object_index_of($branch_one);
              
            }
          }

        }
        // Okay, coming out of that, we should know which branch has the best count (number
        // of matches).  So, let's assign courses to that branch.
        if ($best_branch != -1)
        {
          $winning_branch = $g->list_groups->get_element($best_branch);
          $winning_branch->bool_winning_branch = TRUE;          
          $this->assign_courses_to_list($winning_branch->list_courses, $this->student, TRUE, $g, TRUE);
        }

      }


    } // while 
        
  }


  function get_count_of_matches($branch, $student, $group)
  {
    return $this->assign_courses_to_list($branch->list_courses, $student, FALSE, $group, TRUE);
  }

  function flag_outdated_substitutions()
  {
    // Go through the student's substitutions and flag ones that
    // do not apply to this degree plan.  Also, unset any bool_substitution
    // variables which were set.

    $this->student->list_substitutions->reset_counter();
    while ($this->student->list_substitutions->has_more())
    {
      $substitution = $this->student->list_substitutions->get_next();

      ////////////////////////////
      // Ticket #2316, from Logan Buth, this is to better detect outdated subs for combined degrees.
      $bool_sub_valid = TRUE;
      $outdated_note = "";
      
      if ($this->degree_plan->degree_id == DegreePlan::DEGREE_ID_FOR_COMBINED_DEGREE &&
          !in_array($substitution->db_required_degree_id, $this->degree_plan->combined_degree_ids_array)) {
          //combined degree plan, degree id not in it.
          
          $bool_sub_valid = FALSE;
          $sub_degree_title = fp_get_degree_title($substitution->db_required_degree_id, TRUE, TRUE, FALSE, TRUE);
          
          $outdated_note = t("This substitution is for the degree %did
                            which is no longer in the student's degrees.", array("%did" => $sub_degree_title));
      } 
      else if ($this->degree_plan->degree_id != DegreePlan::DEGREE_ID_FOR_COMBINED_DEGREE &&
                 $this->degree_plan->degree_id != $substitution->db_required_degree_id) {
          
          $bool_sub_valid = FALSE;
          $sub_degree_title = fp_get_degree_title($substitution->db_required_degree_id, TRUE, TRUE, FALSE, TRUE);
          $outdated_note = t("This substitution is for the degree %did
                            which is no longer the student's degree", array("%did" => $sub_degree_title));
      }
      //////////////
      




      //$required_group_id = $substitution->course_requirement->assigned_to_group_id;
      $required_group_id = $substitution->course_requirement->get_first_assigned_to_group_id(); // we assume there's only one group to get

      // First check-- does this degree even have this group ID?
      if ($bool_sub_valid && ($required_group_id == 0 || $required_group_id == '' || $required_group_id == NULL)) {
        // bare degree plan.
        // Does the bare degree plan list the course_requirement
        // anywhere?
        $bool_sub_valid = FALSE;
        $this->degree_plan->list_semesters->reset_counter();
        while($this->degree_plan->list_semesters->has_more() && $bool_sub_valid == FALSE)
        {
          $sem = $this->degree_plan->list_semesters->get_next();
          if ($sem->list_courses->find_match($substitution->course_requirement))
          {            
            $bool_sub_valid = TRUE;
          } 
          else {
            // Could not find the course requirement in question.
            $bool_sub_valid = FALSE;
            $scr = $substitution->course_requirement;
            $scr->load_descriptive_data();
            $outdated_note = t("This substitution is for the course %info on the 
                      bare degree plan, but the student's current degree does
                      not specify this course.", array("%info" => "$scr->subject_id $scr->course_num (id: $scr->course_id)"));
          }
        }


      } 
      else if ($bool_sub_valid && $required_group_id != 0 && $required_group_id != '' && $required_group_id != NULL) {
        // required_group_id != 0, meaning, NOT the bare degree plan.
        // So, does this degree plan have a group with this id (required_group_id)?
        $bool_sub_valid = false;
        if ($g = $this->degree_plan->find_group($required_group_id)) {
          $bool_sub_valid = true;
        } 
        else {
          // Could not find the group in question.  Add an "outdated_note"
          // to the sub...
          $bool_sub_valid = FALSE;
          $new_group = new Group();
          $new_group->group_id = $required_group_id;
          $new_group->load_descriptive_data();
          $group_name = "";
          if (user_has_permission("can_access_data_entry")) { 
            // only show if we are a data entry administrator.
            $group_name = "<i>$new_group->group_name,</i>";
          }
          $outdated_note = t("This substitution is for the group %info,
                  but the student's current degree does not call for this 
                  specific group.", array("%info" => "$new_group->title (id: $new_group->group_id, $group_name $new_group->catalog_year)"));
        }
      }


      if ($bool_sub_valid == FALSE) {

        // Couldn't find a match, so remove this sub!
        $substitution->bool_outdated = TRUE;
        $substitution->outdated_note = $outdated_note;
        $substitution->course_list_substitutions->get_first()->bool_outdated_sub = TRUE;
        $substitution->course_list_substitutions->get_first()->set_bool_outdated_sub(0, TRUE);
        //$substitution->course_list_substitutions->get_first()->bool_substitution = false;
        $substitution->course_list_substitutions->get_first()->set_bool_substitution(0, FALSE);
        if ($substitution->course_list_substitutions->get_first()->temp_old_course_id > 0)
        { // Restore the course_id *if* it was set to 0 on purpose. (happens
          // when there is a sub of a transfer to kill the transfer eqv.  This will
          // restore it).
          $substitution->course_list_substitutions->get_first()->course_id = $substitution->course_list_substitutions->get_first()->temp_old_course_id;
        }
      }



    }



  }

  

  function assign_courses_to_list(ObjList $list_requirements, Student $student, $bool_perform_assignment = true, Group $group = null, $bool_check_significant_courses = false, $assign_to_semester_num = -1)
  {

    $count = 0;
    $school_id = 0;
    if ($student) {
      $school_id = $student->school_id;      
    }

    if ($group == NULL || $group == '' || $group == 0 || $group == FALSE)
    {
      $group = new Group();
      $group->group_id = 0;
    }
    else if ($group->min_grade != "") {
      $group->assign_min_grade($group->min_grade);  // If this is a group with a min grade value, let's make sure we assign all the courses in it to this min grade.
    }


    $sort_policy = variable_get_for_school("initial_student_course_sort_policy", "alpha", $school_id); // will either be "alpha" or "grade"
    
    
    
     
    $bool_disallow_graduate_credits = (variable_get_for_school("disallow_graduate_credits", "yes", $school_id) == "yes") ? TRUE : FALSE;
    $graduate_level_codes_array = csv_to_array(variable_get_for_school("graduate_level_codes", "GR", $school_id));   
        
    // Get the course repeat policy.
    $course_repeat_policy = variable_get_for_school("course_repeat_policy", "most_recent_exclude_previous", $school_id);
    // Set the $bool_mark_repeats_exclude variable based on the course_repeat_policy.
    $bool_mark_repeats_exclude = ($course_repeat_policy == "most_recent_exclude_previous" || $course_repeat_policy == "best_grade_exclude_others");
    
    $group_id = $group->group_id;
    // If the group_id == 0 or blank or null, we may be talking about the bare degree plan.

    $hours_required = $group->hours_required*1;
    $hours_assigned = $group->hours_assigned;

    $meet_min_hours = 999999;   // effectively infinite by default, to make logic easier later on.

    
    // If the group has min_hours, then we should allow the user to get at least the min hours before we stop trying to fill.
    if ($group->has_min_hours_allowed() && variable_get_for_school("group_full_at_min_hours", "yes", $school_id) == "yes") {
      $meet_min_hours = $group->min_hours_allowed;
    }

    if ($hours_required <= 0 || $hours_required == "")
    {
      $hours_required = 999999;
    }


    $list_requirements->sort_smallest_hours_first();
    // sort the requirement list by the best grades that the student has made?  Similar to the substitutions?
    if ($sort_policy == "grade") {        
      $list_requirements->sort_best_grade_first($student);            
    }
    else if ($sort_policy == "alpha") {      
      $list_requirements->sort_alphabetical_order();      
    }

    $list_requirements->sort_substitutions_first($student->list_substitutions, $group_id);


    $list_requirements->reset_counter();
    while($list_requirements->has_more())
    {

      $course_requirement = $list_requirements->get_next();
      $req_by_degree_id = $course_requirement->req_by_degree_id;  // what degree is requiring this course?

      // If we're dealing with a group, use it's required by degree id.
      if (($group->group_id != "" && $group->group_id != 0 && $group->group_id != NULL) && ($group->req_by_degree_id != '' && $group->req_by_degree_id != 0 && $group->req_by_degree_id != NULL)) {        
        $req_by_degree_id = $group->req_by_degree_id;
      }
     
      if ($bool_check_significant_courses == true)
      {
        // Only look for the course_requirement if it is in the student's
        // array_significant_courses array.
        if (isset($student->array_significant_courses[$course_requirement->course_id]) && $student->array_significant_courses[$course_requirement->course_id] != true)
        {// course was not in there, so skip!
          continue;
        }
      }

  
      if ($course_requirement->bool_specified_repeat == true)
      {
        // Since this requirement has specified repeats, we want
        // to make all of the student's taken courses (for this course)
        // also have specified repeats.
        $student->list_courses_taken->set_specified_repeats($course_requirement, $course_requirement->specified_repeats);
           
      }

      
      // Does the student have any substitutions for this requirement?
      if ($substitution = $student->list_substitutions->find_requirement($course_requirement, true, $group_id, $req_by_degree_id))
      {
           
        // Since the substitution was made, I don't really care about
        // min grades or the like.  Let's just put it in.

        if ($bool_perform_assignment == TRUE)
        {        
          // Check to see if this course requirement has "infinite repeats".  If it does, then we want to
          // clone it and add it back into our list of requirements.
          if ($course_requirement->specified_repeats == Group::GROUP_COURSE_INFINITE_REPEATS) {
            $temp_ds = $course_requirement->to_data_string();
            $new_course = new Course();
            $new_course->load_course_from_data_string_for_requirement_clone($temp_ds);
            $new_course->min_grade = strtoupper($course_requirement->min_grade);
            if ($assign_to_semester_num !== -1) {
              $new_course->assigned_to_semester_num = $assign_to_semester_num;
            }
            $list_requirements->add($new_course);
          }      
          
          
          // Make sure this isn't a group addition and we are *currently*
          // NOT looking at the group it is being added to.  This is to
          // correct a bug.
          if ($substitution->bool_group_addition == true)
          {
            if ($substitution->course_requirement->get_first_assigned_to_group_id() != $group_id)
            {              
              continue;
            }
  
          }
          
          // If the course_requirement's min_hours are greater than
          // the substitution's hours, then we have to split the
          // coureRequirement into 2 pieces, and add the second piece just
          // after this one in the list.
          $course_sub = $substitution->course_list_substitutions->get_first();
          if (floatval($course_requirement->min_hours) > floatval($course_sub->get_hours_awarded($req_by_degree_id)))
          { 
            // Because float math can create some very strange results, we must
            // perform some rounding.  We will round to 6 decimal places, which should
            // provide us the accuracy w/o losing precision (since we can only represent a max
            // of 4 decimals in the database anyway.
            $remaining_hours = round($course_requirement->min_hours - $course_sub->get_hours_awarded($req_by_degree_id), 6);
      
            $new_course_string = $course_requirement->to_data_string();
            $new_course = new Course();
            $new_course->load_course_from_data_string($new_course_string);
            $new_course->min_hours = $new_course->max_hours = $remaining_hours;
            $new_course->set_bool_substitution_split($req_by_degree_id, TRUE);
            $new_course->set_bool_substitution_new_from_split($req_by_degree_id, TRUE);
            $new_course->requirement_type = $course_requirement->requirement_type;
            $new_course->min_grade = strtoupper($course_requirement->min_grade);  
            $new_course->req_by_degree_id = $req_by_degree_id; 
            $new_course->assigned_to_degree_ids_array[$req_by_degree_id] = $req_by_degree_id; 
            $new_course->db_degree_requirement_id = floatval($course_requirement->db_degree_requirement_id . ".1");

            if ($assign_to_semester_num !== -1) {
              $new_course->assigned_to_semester_num = $assign_to_semester_num;
            }            
            $course_requirement->set_bool_substitution_split($req_by_degree_id, TRUE);
            
            // I am commenting this out-- if we split up a sub multiple times, then we shouldn't
            // set the old course requirement to say it WASN'T from a split.  This was causing a bug
            // where the pie charts got weird if you did more than 1 split.  Was counting total
            // hours as more, in CourseList->count_hours().
            //$course_requirement->bool_substitution_new_from_split = false;

                        
            
            
            // Only do this if we are NOT in a group!  This is to correct a bug where split additions to groups wound up
            // being displayed like available selections in the group.  It was like, weird man.  
            if ($group_id == 0 || $group_id == '' || $group_id == NULL) {
              // Now, add this into the list, right after the course_requirement.
              $current_i = $list_requirements->i;
              $list_requirements->insert_after_index($current_i, $new_course);
            }

          } // if min_hours > sub's awarded hours

          
          $course_requirement->course_list_fulfilled_by = $substitution->course_list_substitutions;

          $substitution->course_list_substitutions->assign_group_id($group_id);
          $substitution->course_list_substitutions->set_has_been_assigned(true);
          $substitution->course_list_substitutions->set_bool_substitution($req_by_degree_id, TRUE);
          $course_requirement->db_substitution_id_array[] = $substitution->db_substitution_id;
          $substitution->course_list_substitutions->set_course_substitution($course_requirement, $substitution->remarks, $req_by_degree_id);
          
          $substitution->bool_has_been_applied = TRUE;
          
          if ($group_id != "" && intval($group_id) > 0) {
            // We are going to add the course_sub's hours in as being assigned to this list, if this is for a group.
            // I am surprised this wasn't already here.  This fixes a bug with groups that allow min hours.  
            $hours_assigned += $course_sub->get_hours_awarded($req_by_degree_id);
          } 

        }
        $count++;
        continue;
      } // if student has any substitutions for this requirement

      
      
      // Has the student taken this course requirement?
      if ($c = $student->list_courses_taken->find_best_match($course_requirement, $course_requirement->min_grade, $bool_mark_repeats_exclude, $req_by_degree_id, TRUE, TRUE, $group_id))
      {

                
        $h_get_hours = $c->get_hours();
        if ($c->bool_ghost_hour) {
          // If this is a ghost hour, then $h_get_hours would == 0 right now,
          // instead, use the the adjusted value (probably 1).
          $h_get_hours = $c->get_hours_awarded($req_by_degree_id);
        }       
                         
        // Can we assign any more hours to this group?  Are we
        // out of hours, and should stop?
        if ($hours_assigned >= $hours_required || $hours_assigned >= $meet_min_hours)
        { 
          continue;
        }

        $c_hours_awarded = $c->get_hours_awarded($req_by_degree_id);

        // Will the hours of this course put us over the hours_required limit?  NOTE:  We don't care if it puts us over the min_hours, in fact, we want that.
        if ($hours_assigned + $c_hours_awarded > $hours_required )
        {
          continue;
        }

        // Do not apply substitution new_from_split courses to anything automatically.  (from any degree!)
        // They must be applied by substitutions.
        if ($c->get_bool_substitution_new_from_split() == TRUE)
        {
          continue;
        }


        // Make sure the course meets min grade requirements.
        if (!$c->meets_min_grade_requirement_of($course_requirement))
        {    
          continue;
        }


        // Has the course been unassigned from this group?
        if ($c->group_list_unassigned->find_match_with_degree_id($group, $req_by_degree_id))
        {
          continue;
        }


        // Make sure $c is not being used in a substitution (for this degree)
        if ($c->get_bool_substitution($req_by_degree_id) == TRUE) {          
          continue;
        }

        // If this is a graduate level course, and we are not allowing grad credits, then skip!
        if ($c->level_code != "" && in_array($c->level_code, $graduate_level_codes_array)) {
          if ($bool_disallow_graduate_credits) {                        
            continue;
          }
        }
        



        // Check hooks to see if this course is allowed to be assigned to the GROUP in question.
        if ($group_id != "" && $group_id != 0 && $group_id != NULL) {
          $bool_can_proceed = TRUE;

          
          $result = invoke_hook("flightpath_can_assign_course_to_group", array($group, $c));
          foreach ($result as $m => $val) {
            // If *any* module said FALSE, then we must skip this course and not assign it to this degree.
            if ($val === FALSE) $bool_can_proceed = $val;
          }
          
          if (!$bool_can_proceed) {
            continue;  // don't assign!
          }                  
          
        } // if group_id != ""

       
        // We want to see if this course has already been assigned to THIS degree...
        if (!in_array($req_by_degree_id, $c->assigned_to_degree_ids_array))
        {//Don't count courses which have already been placed in other groups.

          // Check hooks to see if this course is allowed to be assigned to the degree in question.
          $bool_can_proceed = TRUE;
          $result = invoke_hook("flightpath_can_assign_course_to_degree_id", array($req_by_degree_id, $c));
          foreach ($result as $m => $val) {
            // If *any* module said FALSE, then we must skip this course and not assign it to this degree.
            if ($val === FALSE) $bool_can_proceed = $val;
          }
           
          if (!$bool_can_proceed) {
            continue;  // don't assign!
          } 

        
          // Has another version of this course already been
          // assigned?  And if so, are repeats allowed for this
          // course?  And if so, then how many hours of the
          // repeat_hours have I used up?  If I cannot do any more
          // repeats, then quit.  Otherwise, let it continue...

          $course_list_repeats = $student->list_courses_taken->get_previous_assignments($c->course_id);

          if ($course_list_repeats->get_size() > 0)
          {
            
            // So, a copy of this course has been assigned more than once...
            // Get the total number of hours taken up by this course.
            $cc = $course_list_repeats->count_hours();
            // have we exceeded the number of available repeat_hours
            // for this course?
            if ($course_requirement->repeat_hours <= 0)
            {
              $course_requirement->load_descriptive_data();
            }

            if (($course_requirement->bool_ghost_hour != TRUE || $c->bool_ghost_hour != TRUE)
                && $cc + $h_get_hours > $course_requirement->repeat_hours*1)
            {
              // Do not allow the repeat, unless we are talking about courses worth zero hours.
              // meaning, they have a ghost hour.  In which case, allow it.
              continue;
            }



          }

          // Basically--- if the most recent attempt fails
          // a min grade check, then tag all attempts as "unuseable"
          // so that they can't be used in other groups.  --
          // unless they are able to be repeated.  BARF!

          // Inc hours_assigned, even if we aren't actually
          // performing an assignment.  This helps us accurately
          // calculate the count.
          
          $hours_assigned = $hours_assigned + $h_get_hours;



          if ($bool_perform_assignment == TRUE)
          {

            // If this course has an "infinite" number of specified repeats, then
            // let's actually clone the course and insert it as a new requirement, to replace
            // the one we're about to use. 
            if ($course_requirement->specified_repeats == Group::GROUP_COURSE_INFINITE_REPEATS) {
              $temp_ds = $course_requirement->to_data_string();
              $new_course = new Course();
              $new_course->load_course_from_data_string_for_requirement_clone($temp_ds);
              $new_course->min_grade = strtoupper($course_requirement->min_grade);

              if ($assign_to_semester_num !== -1) {                
                $new_course->assigned_to_semester_num = $assign_to_semester_num;
                $new_course->appears_in_semester_nums[] = $assign_to_semester_num;         // for combo-degrees
              }
              
              
              $list_requirements->add($new_course);
              
            } // infinite repeats?
            
            
            // Which degree is this coming from?  
            //$req_by_degree_id = $course_requirement->req_by_degree_id;
            $c->assigned_to_degree_ids_array[$req_by_degree_id] = $req_by_degree_id;    
                                    
            // Go ahead and state that the requirement was fulfilled.
            $course_requirement->course_list_fulfilled_by->add($c);
            $course_requirement->grade = $c->grade;
            $course_requirement->set_hours_awarded($req_by_degree_id, $c->get_hours_awarded($req_by_degree_id));
            $course_requirement->bool_ghost_hour = $c->bool_ghost_hour;
            if ($course_requirement->extra_attribs != "") {
              $c->extra_attribs = $course_requirement->extra_attribs;
            }

            // No longer using... using the assigned_to_degree_ids_array instead. // $c->bool_has_been_assigned = true;
            //$c->requirement_type = $course_requirement->requirement_type;
            if ($c->requirement_type == "") {
              // No requirement type given?  Perhaps we are part of a group.  If so, use that.
              //$c->requirement_type = $group->requirement_type;    
              //$course_requirement->requirement_type = $group->requirement_type;         
            }
            
            if ($assign_to_semester_num !== -1) {              
              $c->assigned_to_semester_num = $assign_to_semester_num;     
              $c->appears_in_semester_nums[] = $assign_to_semester_num;         // for combo-degrees
            }
                                    
            // Check what groups it has been assigned to already.
            //$c->assigned_to_group_id = $group_id;
            if ($group_id != '') {
              $c->assigned_to_group_ids_array[$group_id . "_" . $req_by_degree_id] = $group_id;
            }
            
            $group->hours_assigned = $hours_assigned;
            
            // Should check for:
            // Can it be assigned, based on the number of allowed course repeats?
            if ($course_requirement->bool_specified_repeat == true)
            {
              
              // $c is what they actually took.
              $c->bool_specified_repeat = true;
              $c->specified_repeats = $course_requirement->specified_repeats;
              $list_requirements->dec_specified_repeats($c);
            }
          } // bool_perform_assignment == true

          
          $count++;
        }
      }

    }
  
    return $count;
  }



  function assign_courses_to_semesters()
  {
    // This method will look at the student's courses
    // and decide if they should be assigned to degree requirements
    // which have been spelled out in each semester.  This
    // is not where it looks into groups.
    //fpm($this->degree_plan, 5);
    $this->degree_plan->list_semesters->reset_counter();
    while($this->degree_plan->list_semesters->has_more())
    {
      $semester = $this->degree_plan->list_semesters->get_next();
      $semester_num = $semester->semester_num;

      // Okay, let's look at the courses (not groups) in this
      // semester...
      $this->assign_courses_to_list($semester->list_courses, $this->student, TRUE, NULL, FALSE, $semester_num);
      
    }


  }


  /**
   * Get the plain English title of a subject, from
   * subject_id.  Ex: COSC = Computer Science.
   *
   * @param unknown_type $subject_id
   * @return unknown
   */
  function get_subject_title($subject_id, $school_id = 0)
  {
    // From the subject_id, get the title.
    // Example: COSC = Computer Science.
  
    $res = $this->db->db_query("SELECT title FROM subjects
              WHERE subject_id = ? 
              AND school_id = ?
              LIMIT 1 ", $subject_id, $school_id);
    $cur = $this->db->db_fetch_array($res);
    if ($cur) {
      return trim($cur["title"]);
    }

  }

 

  function get_all_courses_in_catalog_year($catalog_year = "2006", $bool_load_descriptive_data = false, $limit_start = 0, $limit_size = 0, $school_id = 0, $bool_only_undergrad = TRUE, $bool_skip_excluded = FALSE)
  {
    // Returns a CourseList object of all the
    // courses in the supplied catalog_year.

    $lim_line = "";
    if ($limit_size > 0)
    {
      $lim_line = " limit $limit_start, $limit_size ";
    }
    $rtn_list = new CourseList();
    $c_array = array();
    
    $params = array();
    $params[":catalog_year"] = $catalog_year;
    $params[":school_id"] = $school_id;
    
  
    $exclude_line = "";
    if ($bool_skip_excluded) {
      $exclude_line = " AND exclude = 0 ";
    }
    
    $course_num_line = "";
    if ($bool_only_undergrad) {
      $course_num_line = " AND course_num < :gradnum ";
      $params[":params"] = variable_get_for_school("graduate_level_course_num", 5000, $school_id);      
    }
    
    
    $result = $this->db->db_query("SELECT * FROM courses
              WHERE 
                catalog_year = :catalog_year
                $course_num_line
                AND school_id = :school_id
                AND delete_flag = 0
                $exclude_line
              ORDER BY subject_id, course_num
              $lim_line
              ", $params);

    while($cur = $this->db->db_fetch_array($result))
    { 


      $course = new Course();
      $course->course_id = $cur["course_id"];
      $course->subject_id = $cur["subject_id"];
      $course->course_num = $cur["course_num"];
      $course->min_hours = $cur["min_hours"];
      $course->max_hours = $cur["max_hours"];

      if ($bool_load_descriptive_data == true)
      {
        $course->load_descriptive_data();
      }

      $rtn_list->add($course);
    }

    return $rtn_list;

  }







  /**
   * This function replaces the original method of caching the course inventory, to something which uses 
   * a file instead of database.
   */
  function z_____cache_course_inventory($limit_start = 0, $limit_size = 4000)  {
    // This functionality has moved to the system module to make it easier to call.
    // This will eventually be depricated.    
    system_reload_and_cache_course_inventory();
    
  } // function







  function z__cache_course_inventory($limit_start = 0, $limit_size = 4000)
  {
    
    // Load courses from the inventory into the inventory cache...
    // Attempt to load the course inventory cache...
    if ($course_inventory = unserialize(@$_SESSION["fp_cache_course_inventory"]))
    {
      $GLOBALS["fp_course_inventory"] = $course_inventory;
    }

    $result = $this->db->db_query("SELECT DISTINCT course_id FROM courses
              WHERE 
                course_num < ?
                LIMIT $limit_start, $limit_size
              ", variable_get("graduate_level_course_num", 5000)); // just use default?

    while($cur = $this->db->db_fetch_array($result))
    {
      $course_id = $cur["course_id"];

      $this->db->load_course_descriptive_data(null, $course_id);

    }

    // Should we re-cache the course inventory?  If there have been any changes
    // to it, then we will see that in a GLOBALS variable...
    if ($GLOBALS["cache_course_inventory"] == true)
    {
      $_SESSION["fp_cache_course_inventory"] = serialize($GLOBALS["fp_course_inventory"]);
    }


  }


  function replace_missing_course_in_group($course_id, $group_id)
  {
    // Given a group in the degree plan, this will
    // make sure that course is actually in the group.  If it
    // is not, then it will add it in where it should be.
    // This is necessary because we have previously removed
    // courses which the student hadn't taken.  Well, if the
    // student was advised for a particular course in a group,
    // then that course probably was originally removed
    // from the group.  So, put it back in.

    // Were we given a valid group_id?
    if (!$group_id) return;

    // First, find the group.
    if (!$group = $this->degree_plan->find_group($group_id))
    {      
      fpm("Admin note: could not find group '$group_id' for replace_missing_course_in_group, for course '$course_id'.");
      return;
    }

    // Okay, now tell the group to replace the instance of this course
    // in the group.  This is made easy, because we have
    // the dbGroupRequirementID, which is the actual id from the
    // row in group_requirements that this course was advised from.
    $group->replace_missing_course($course_id);



  }


  function save_advising_session_from_post($faculty_id = 0, $bool_draft = true)
  {
    global $user;
    
    $catalog_year = 0;


    $bool_fp_goto_at_end = FALSE;    
    
    
    // This method will, only by looking at variables in the
    // POST, save an advising session into the database.
    $db = get_global_database_handler();
    if ($faculty_id == 0) { 
      // if none supplied, use the one from the session of
      // whomever is currently logged in.
      $faculty_id = $user->cwid;
    }

    $advising_session_token = $db->request_new_advising_session_token();

    
    // It's possible the user has simply pressed "refresh" after submitting the form.  If so,
    // there is no reason to re-submit everything, creating duplicate data in some situations.
    $post_md5 = md5(serialize($_POST));
    if (@$_SESSION["fp_previous_advising_post_md5"] == $post_md5) {     
      return array();
    }
    // We may proceed, but save the POST md5 for next time.
    $_SESSION["fp_previous_advising_post_md5"] = $post_md5; 
    
    
    $bool_found_update_match = false;
    $student_id = $this->student->student_id;
    $school_id = db_get_school_id_for_student_id($student_id);
    $degree_id = $this->degree_plan->degree_id;
    $major_code_csv = $this->degree_plan->get_major_code_csv();
    $catalog_year = $this->degree_plan->catalog_year;
    $available_terms = variable_get_for_school("available_advising_term_ids", "0", $school_id);
        
    // Do we need to update the student's settings?
    if (trim($_POST["advising_update_student_settings_flag"]) != "")
    {
      // We are to assume that the student's array_settings
      // have already been updated by this point, so we will
      // simply convert them to XML and store in the database.      
      $result = $db->db_query("REPLACE INTO student_settings
                  (student_id, settings, posted)
                  VALUES ('?','?', '?' )  ", $student_id, serialize($this->student->array_settings), time());

      watchdog("update_student_settings", "Settings updated for this student.");
  

    }

    //var_dump($_POST);
    //    die;
    // We have changed tracks, so we are to edit the student degrees table.
    if (isset($_POST["advising_update_student_degrees_flag"]) && $_POST["advising_update_student_degrees_flag"] == "true") {
      // Begin by deleting all the "editable" rows for this student
      // in student_degrees.
      db_query("DELETE FROM student_degrees 
                WHERE student_id = '?'                
                AND is_editable = '1' ", $student_id);
      // Now, go through our list of degree tracks ids we're adding back in, and add to the table.
      $temp = explode(",", $_POST["advising_track_degree_ids"]);
              
      foreach ($temp as $tdegree_id) {
        if (trim($tdegree_id) == "") continue;
        if (!is_numeric($tdegree_id)) continue;
        
        $tdegree_plan = fp_load_degree($tdegree_id, NULL, TRUE);
        
        $tmajor_code = $tdegree_plan->major_code;
        
        /*  // shouldn't need this anymore, as the major_code will contain both the major and track in one.
        $tmajor_code = $tdegree_plan->major_code;
        if (!strstr($tmajor_code, "|")) {
          $tmajor_code .= "|";
        }        
        $tmajor_code .= "_" . $tdegree_plan->track_code;
        */
               
        db_query("INSERT INTO student_degrees
                  (student_id, major_code, is_editable)
                  VALUES ('?', '?', '1')", $student_id, $tmajor_code);
        
      }      
       
      // Reset the SESSION variables and re-init, so we get the correct
      // major codes and track codes for this student.      
      $_SESSION["advising_track_degree_ids$student_id"] = "";
      $_SESSION["advising_major_code$student_id"] = "";
      $_REQUEST["advising_major_code"] = "";
      $_REQUEST["advising_track_degree_ids"] = "";
      
      $this->student->load_student_data();
      
      // force re-build of cache
      //$_REQUEST["load_from_cache"] = "no"; 
      //$_SESSION["cache_fp$student_id"] = "";
      //$_SESSION["cache_what_if$student_id"] = "";
      $GLOBALS["fp_advising"]["load_from_cache"] = "no";
      
      $this->init(TRUE);   
      $bool_fp_goto_at_end = TRUE; 
    } // editing degrees?



        
    // Is there anything in "log_addition" which we should write to the log?
    if (isset($_POST["log_addition"]) && $_POST["log_addition"])
    {
      $temp = explode("~", (string) $_POST["log_addition"]);
      if ($temp[0] == "change_term") {        
        watchdog("change_term", "$student_id," . $temp[1]);        
      }

      if ($temp[0] == "change_track"){
        watchdog("change_track", "$student_id," . $temp[1]);        
      }


    }




    // If this user cannot advise, then just return right now.
    if (!user_has_permission("can_advise_students")) {
      return;
    }


    // First, create a new entry in the advising_sessions table,
    // so we can get the advising_session_id.

    // But before we can do that, we look for an existing entry
    // which matches this.  If we find it, we delete it so the
    // new one will display instead.
    // Only delete if its a draft copy!
    $is_draft = intval($bool_draft);
    $is_what_if = intval($this->bool_what_if);

    // Since we only want one draft copy per term/per student and faculty,
    // let's delete
    // any draft copies already in existence, if we are saving a draft.
    $result = $db->db_query("DELETE FROM advising_sessions
                  WHERE student_id = ?
                  AND is_draft = 1
                  AND faculty_id = ?
                  AND degree_id = ?
                  AND is_whatif = ? ", $student_id, $faculty_id, $degree_id, $is_what_if);


    // Set all other advising sessions' "most_recent_session" flag to zero (0) for this student (but only if this is NOT an "is_draft" save!)
    if ($is_draft === 0) {
      db_query("UPDATE advising_sessions SET most_recent_session = 0 WHERE student_id = ?", array($student_id));
    }


    // The first thing we need to do is go through the available_terms,
    // create new entries for them in the table, and store what their
    // session ID's are in an array.
    $advising_session_id_array = array();
    $advising_session_id_array_count = array();
    $posted = time();
    $temp = explode(",",$available_terms);    
    foreach ($temp as $term_id)
    {
      $term_id = trim($term_id);

      if ($term_id == "") { continue; }

      // Okay, now create a new entry in the system for that term.
      // We create entries for all available terms, whether we
      // are going to use them later or not.
      $result = $db->db_query("INSERT INTO advising_sessions
                (student_id, faculty_id, term_id, degree_id,
                major_code_csv,
                catalog_year, posted, is_whatif, is_draft, advising_session_token, delete_flag, most_recent_session)
                VALUES
                (?,?,?,?,?,?,?,?,?,?, 0, 1) 
                ", $student_id, $faculty_id,$term_id,$degree_id, $major_code_csv, $catalog_year, $posted, $is_what_if, $is_draft, $advising_session_token);
      $advising_session_id = db_insert_id();
      $advising_session_id_array[$term_id] = $advising_session_id;
      $advising_session_id_array_count[$term_id] = 0;
    }
        
    
    $wi = "";
    if ($is_what_if == "1"){$wi = "_whatif";}

    if ($bool_draft) {
      watchdog("save_adv_draft$wi", "$student_id,major_code_csv:$major_code_csv~adv_session_token:$advising_session_token");
    } 
    else {
      watchdog("save_adv_active$wi", "$student_id,major_code_csv:$major_code_csv~adv_session_token:$advising_session_token");
    }

    // Go through the POST, looking for the
    // phrase "advisecourse_" in the name of the variables.
    // There should be one of these for every course that was
    // on the page.  It looks like this:
    // advisecourse_course_id_semesterNum_group_id_varHours_randomID
    foreach($_POST as $key => $value)
    {
      
      if (!strstr($key,"advisecourse_") && !(strstr($key, "advcr_")))
      { // Skip vars which don't have this as part of the name.
        // We accept either advisecourse_ or advcr_ for short.  advisecourse_ is the old way.
        // I changed to use advcr_ to save space, because some browsers will not allow long input names.
        continue;
      }
      if ($value != "true")
      { // This means the course was *not* advised to be taken,
        // so, skip it.
        continue;
      }

      // The key might contain a DoT (dot placeholder) instead of a period.  If so, let's
      // add the period back in.  This was to correct a bug where courses with dots couldn't
      // be advised.
      if (strstr($key, "DoT")) {
        $key = str_replace("DoT", ".", $key);
      }

      $temp = explode("_",$key);
      $course_id = trim($temp[1]);
      $semester_num = trim($temp[2]);
      $group_id = str_replace("U", "_", trim($temp[3]));  // replace U with _, which was required for the submission to work (couldn't use the _ in group_id.)
      $var_hours = trim($temp[4]) * 1;
      $random_id = trim($temp[5]);
      $advised_term_id = trim($temp[6]);      
      //$db_group_requirement_id = trim($temp[7]);
      $degree_id = trim($temp[7]);
      if ($degree_id == "group") {
        // Get degree_id from the group_id.
        $tt = explode("_", $group_id);
        // Fixed this bug, where originally we didn't check to see if $tt has an element [1].  If this
        // causes problems, then we will just use @$tt[1].
        if (isset($tt[1])) $degree_id = $tt[1];
      }

      // There is a possibility that the advised_term_id value doesn't exist in the array, so coalesce to '' if so.
      $advising_session_id = $advising_session_id_array[$advised_term_id] ?? '';

      $new_course = new Course($course_id);
      $new_course->load_descriptive_data();
      $entry_value = "$new_course->subject_id~$new_course->course_num";



      // Some particular course should be updated.  Possibly this one.
      // Updates happen because of a student changing the
      // variable hours, for example.
      if (isset($_POST["updatecourse"]) && trim($_POST["updatecourse"]) != "")
      {
        $temp2 = explode("~",trim($_POST["updatecourse"]));

        $tcourse_id = $temp2[0];
        $tgroup_id = $temp2[1];
        $tsemester_num = $temp2[2];
        $tvar_hours = $temp2[3];
        $trandom_id = $temp2[4];
        $tadvised_term_id = $temp2[5];
        $tdegree_id = $temp2[6];

        // Do we have a match?
        if ($course_id == $tcourse_id && $random_id == $trandom_id)
        {
          // We have a match, so update with the new information.
          $var_hours = $tvar_hours;
          $degree_id = $tdegree_id;
          $new_course = new Course($tcourse_id);
          $new_course->load_descriptive_data();
          $entry_value = "$new_course->subject_id~$new_course->course_num";
          $bool_found_update_match = true;
        }


      }


      if ($group_id != 0 && $group_id != '' && $group_id != NULL)
      {
        $this->replace_missing_course_in_group($course_id, $group_id);
      }


      // Make sure degree_id is a valid number for the database.
      if (!is_numeric($degree_id) || intval($degree_id) < 0) {
        $degree_id = 0;
      }

      // Okay, write it to the table...
      $result = $db->db_query("INSERT INTO advised_courses
                  ( advising_session_id, course_id, entry_value, semester_num,
                    group_id, var_hours, term_id, degree_id)
                  VALUES
                  (?,?,?,?,?,?,?,?)
                  ", array($advising_session_id, $course_id, $entry_value, $semester_num, $group_id, $var_hours, $advised_term_id, $degree_id));

      if (!isset($advising_session_id_array_count[$advised_term_id])) {
        $advising_session_id_array_count[$advised_term_id] = 0;
      }
      
      $advising_session_id_array_count[$advised_term_id]++;

    }

    // Did we have to perform an update-- but no course was found?
    if (isset($_POST["updatecourse"]) && trim($_POST["updatecourse"]) != "" && $bool_found_update_match == false)
    {
      // This means that the course was probably on the bare
      // degree program, and not already checked for advising.  So,
      // let's add it to the advised_courses table, so it DOES
      // get checked for advising.
      $temp2 = explode("~",trim($_POST["updatecourse"]));
      $course_id = $temp2[0];
      $group_id = $temp2[1];
      $semester_num = $temp2[2];
      $var_hours = $temp2[3];
      $advised_term_id = $temp2[5];
      $degree_id = $temp2[6];

      $new_course = new Course($course_id);
      $new_course->load_descriptive_data();
      $entry_value = "$new_course->subject_id~$new_course->course_num";
      
      $advising_session_id = $advising_session_id_array[$advised_term_id];

      $result = $db->db_query("INSERT INTO advised_courses
                  (`advising_session_id`,`course_id`,`entry_value`,`semester_num`,
                    `group_id`,`var_hours`,`term_id`,`degree_id`)
                  VALUES
                  ('?','?','?','?','?','?','?','?')
                  ", $advising_session_id,$course_id,$entry_value,$semester_num,$group_id,$var_hours,$advised_term_id,$degree_id);

      $advising_session_id_array_count[$advised_term_id]++;

      if ($group_id != 0 && $group_id != '' && $group_id != NULL)
      {
        $this->replace_missing_course_in_group($course_id, $group_id);
      }


    }


    
    //------------------------------------------------------
    //
    //             Substitutions...
    //
    //-------------------------------------------------------
    // check permissions for substitutions before saving
    if (isset($_POST["savesubstitution"]) && trim($_POST["savesubstitution"]) != "" && user_has_permission("can_substitute")) {
      $temp = explode("~",trim($_POST["savesubstitution"]));
      $course_id = $temp[0];  // required course
      $group_id = trim($temp[1]);
      $req_by_degree_id = $temp[2];
      $semester_num = $temp[3] * 1;

      $sub_course_id = $temp[4];
      $sub_term_id = $temp[5];
      $sub_transfer_flag = $temp[6];
      $sub_hours = $temp[7] * 1;
      $sub_addition = $temp[8];
      $sub_remarks = urldecode($temp[9]);

      

      if ($sub_addition == "true")
      {
        $course_id = 0;
      }

      // Figure out the entry values for the required course & sub course...
      $required_entry_value = $sub_entry_value = "";
      if ($course_id > 0)
      {
        $new_course = new Course($course_id);
        $new_course->load_descriptive_data();
        $required_entry_value = "$new_course->subject_id~$new_course->course_num";
      }

      if ($sub_transfer_flag != 1)
      {
        $new_course = new Course($sub_course_id);
        $new_course->load_descriptive_data();
        $sub_entry_value = "$new_course->subject_id~$new_course->course_num";

      }

      if (($group_id != 0 && $group_id != '' && $group_id != NULL) && $course_id != 0)
      {
        $this->replace_missing_course_in_group($course_id, $group_id);
      }

      
      // Make sure the sub_hours aren't larger than the sub_course_id's awarded hours.
      // This is to stop a bug from happening where sometimes, some people are able to substitute
      // a course for larger than the awarded hours.  I believe it is a javascript bug.      

      if ($test_c = $this->student->list_courses_taken->find_specific_course($sub_course_id, $sub_term_id, (bool) $sub_transfer_flag, true)) {
        // Are the hours out of whack?
        if (floatval($sub_hours) > floatval($test_c->get_hours_awarded($req_by_degree_id))) {
          // Yes!  Set it to the value of the hours_awarded.
          $sub_hours = floatval($test_c->get_hours_awarded($req_by_degree_id));
        }
      }     
    

      $result = $db->db_query("INSERT INTO student_substitutions
                  (`student_id`,`faculty_id`,`required_course_id`,`required_entry_value`,
                  `required_group_id`,`required_degree_id`,`required_semester_num`,`sub_course_id`,`sub_entry_value`,
                  `sub_term_id`,`sub_transfer_flag`,`sub_hours`,`sub_remarks`,`posted`)
                  VALUES
                  ('?','?','?','?','?','?','?','?','?','?','?','?','?','?')
                  ", $student_id,$faculty_id,$course_id,$required_entry_value,$group_id,$req_by_degree_id,$semester_num,$sub_course_id,$sub_entry_value,$sub_term_id,$sub_transfer_flag,$sub_hours,$sub_remarks, time());

      watchdog("save_substitution", "$student_id,group_id:$group_id,insert_id:" . db_insert_id());

    } 


    if (isset($_POST["removesubstitution"]) && trim($_POST["removesubstitution"]) != "")
    {
      $temp = explode("~",trim($_POST["removesubstitution"]));
      $sub_id = trim($temp[0]) * 1;

      $result = $db->db_query("UPDATE student_substitutions
                  SET `delete_flag`='1'
                  WHERE `id`='?'  ", $sub_id);

      watchdog("remove_substitution", "$student_id,sub_id:$sub_id");

    }



    //------------------------------------------------------
    //
    //             Group Unassignments
    //
    //-------------------------------------------------------
    if (isset($_POST["unassign_group"]) && trim($_POST["unassign_group"]) != "")
    {
      $temp = explode("~",trim($_POST["unassign_group"]));
      $course_id = $temp[0];
      $term_id = $temp[1];
      $transfer_flag = $temp[2];
      $group_id = $temp[3];
      $degree_id = $temp[4];

      $result = db_query("INSERT INTO student_unassign_group
                  (`student_id`,`faculty_id`,`course_id`,
                  `term_id`,`transfer_flag`,`group_id`,`degree_id`,
                  `posted`)
                  VALUES
                  ('?','?','?','?','?','?','?','?')
                  ", $student_id,$faculty_id,$course_id,$term_id,$transfer_flag,$group_id,$degree_id,time());

      watchdog("save_unassign_group", "$student_id,group_id:$group_id,degree_id:$degree_id");

    }

    
    if (isset($_POST["restore_unassign_group"]) && trim($_POST["restore_unassign_group"]) != "")
    {
      $temp = explode("~",trim($_POST["restore_unassign_group"]));
      $unassign_id = trim($temp[0]) * 1;


      $result = $db->db_query("UPDATE student_unassign_group
                  SET `delete_flag`='1'
                  WHERE `id`='?' ", $unassign_id);

      watchdog("restore_unassign_group", "$student_id,unassign_id:$unassign_id");

    }


    //------------------------------------------------------
    //
    //             Transfer EQV Unassignments
    //
    //-------------------------------------------------------
    if (isset($_POST["unassign_transfer_eqv"]) && trim($_POST["unassign_transfer_eqv"]) != "")
    {
      $temp = explode("~",trim($_POST["unassign_transfer_eqv"]));
      $course_id = $temp[0];

      $result = $db->db_query("INSERT INTO student_unassign_transfer_eqv
                  (`student_id`,`faculty_id`,`transfer_course_id`,
                  `posted`)
                  VALUES
                  ('?','?','?','?')
                  ", $student_id, $faculty_id, $course_id, time());

      watchdog("save_unassign_transfer", "$student_id,course_id:$course_id");

    }

    if (isset($_POST["restore_transfer_eqv"]) && trim($_POST["restore_transfer_eqv"]) != "")
    {
      $temp = explode("~",trim($_POST["restore_transfer_eqv"]));
      $unassign_id = trim($temp[0]) * 1;

      $result = $db->db_query("UPDATE student_unassign_transfer_eqv
                  SET `delete_flag`='1'
                  WHERE `id`='?' ", $unassign_id);

      watchdog("restore_unassign_transfer", "$student_id,unassign_id:$unassign_id");

    }



    ////////////////////////////////////////////////////
    ///////  Cleanup !////////////////////////////////
    ////////////////////////////////////////////////////
    // If any of the advisingSessions we created earlier
    // are blank, we should FLAG them, so they will not
    // show up under the student's history.
    // Only flag non-draft empty ones.  If they are draft,
    // let them be.
    // We just look at $advising_session_id_array_count[] to see
    // if any of the counts are still 0.  If they are, delete
    // that advisingSessionID from the table.
    if ($is_draft == 0)
    {
      foreach ($advising_session_id_array as $term_id => $advising_session_id)
      {
        if ($advising_session_id_array_count[$term_id] == 0)
        {

          // This one is blank!  Delete it!
          $res = $db->db_query("UPDATE advising_sessions
                SET is_empty = 1  
                WHERE advising_session_id = ? ", $advising_session_id);
          $advising_session_id_array[$term_id] = "";
        }
      }
    }


    watchdog("advising", "Student has been advised: @student", array("@student" => $student_id));


    // Call a hook so other modules can act when the advising session gets saved.
    invoke_hook('save_advising_session_from_post', array($student_id, $is_draft, $advising_session_id_array));

    // Instead of executing the page, we will issue a redirect using fp_goto.
    // This makes it so if the user hits "refresh", it will not re-submit a POST request.
    if ($bool_fp_goto_at_end) {
          
      // Goto the same page we are already on.  This mimics Drupal's Form API behavior.  
      $q = $_REQUEST['q'];       
      fp_goto($q, "advising_major_code=&load_from_cache=no&advising_student_id=$student_id");
      
    }

    return $advising_session_id_array;


  }



  function load_advising_session_from_database($faculty_id = 0, $term_id = "", $bool_what_if = false, $bool_draft = true, $advising_session_id = 0, $duplicate_for_faculty_id = 0)
  {
    global $user;
    // This method will load an advising session for a particular
    // student, and modify the degree plan object to reflect
    // the advisings.
  
    $db = get_global_database_handler();
    $is_what_if = "0";
    $is_draft = "0";
    
    
    if ($bool_what_if == true){$is_what_if = "1";}
    if ($bool_draft == true){$is_draft = "1";}

    $degree_id = $this->degree_plan->degree_id;
    $student_id = $this->student->student_id;
    $school_id = db_get_school_id_for_student_id($student_id);
    $available_terms = variable_get_for_school("available_advising_term_ids", "0", $school_id);

    
    // If we are pulling up an active student record, then let's
    // delete any draft sessions for this faculty user and student, so as not to cause
    // a bug later on when changing tabs.  We don't care if $faculty_id is set or not, just
    // the current user's CWID.
    if ($bool_draft == FALSE && $user->cwid != 0) {
      $res = db_query("DELETE FROM advising_sessions
                        WHERE student_id = ?
                        AND faculty_id = ?
                        AND is_draft = 1
                         ", $student_id, $user->cwid);
    }
        
    
    
    
    
    
    
    $advising_session_line = " `advising_session_id`='$advising_session_id' ";
    // First, find the advising session id...
    if ($advising_session_id < 1 && $available_terms == "")
    {
      $advising_session_id = $this->db->get_advising_session_id($faculty_id, $student_id, $term_id, $degree_id, $bool_what_if, $bool_draft, TRUE);
      $advising_session_line = " `advising_session_id`='$advising_session_id' ";

      // Create a duplicate of this session as a draft...
      if ($bool_draft == FALSE) {
        $db->duplicate_advising_session($advising_session_id, $duplicate_for_faculty_id, "", "", "", "", 1);
      }
      

    } else if ($advising_session_id < 1 && $available_terms != "")
    {
      // Meaning, we are looking for more than one term.
      $advising_session_line = "(";
      $temp = explode(",",$available_terms);
      for ($t = 0; $t < count($temp); $t++)
      {
        $t_id = trim($temp[$t]);

        $asid = $this->db->get_advising_session_id($faculty_id, $student_id, $t_id,$degree_id, $bool_what_if, $bool_draft, TRUE);
        if ($asid != 0)
        {
          $advising_session_line .= " advising_session_id='$asid' || ";
          
          // Create a duplicate of this session as a draft...
          if ($bool_draft == FALSE) {
            $db->duplicate_advising_session($asid, $duplicate_for_faculty_id, "", "", "", "", 1);
          }
          
          
        }
      }
      // Take off the last 3 chars...
      $advising_session_line = substr($advising_session_line, 0, -3);
      $advising_session_line .= ")";
      if ($advising_session_line == ")")
      {  // Found NO previously advised semesters, so just
        // use a dummy value which guarantees it pulls up nothing.
        $advising_session_line = " advising_session_id='-99999'";
      }

    }

    // Now, look up the courses they were advised to take.
    $query = "SELECT * FROM advised_courses
                WHERE 
                 $advising_session_line
                ORDER BY `id` ";
    $result = $db->db_query($query);
    while($cur = $db->db_fetch_array($result))
    {
      $course_id = trim($cur["course_id"]);
      $semester_num = trim($cur["semester_num"]);
      $group_id = trim($cur["group_id"]);
      $degree_id = $cur["degree_id"];
      $var_hours = trim($cur["var_hours"]);
      $advised_term_id = trim($cur["term_id"]);
      $id = trim($cur["id"]);

      // Add this course to the generic list of advised courses.  Useful
      // if we are using this to pull up an advising summary.
      $temp_course = new Course($course_id);
      $temp_course->advised_hours = $var_hours;
      $this->course_list_advised_courses->add($temp_course);

      if ($semester_num == DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED)
      {
        // This was a courses added by the advisor.
        $this->assign_course_to_courses_added_list($course_id, $var_hours, $id, $advised_term_id);
        continue;
      }

      // Now, we need to modify the degree_plan object to
      // show these advisings.
      if ($course_list = $this->degree_plan->find_courses($course_id, $group_id, $semester_num, $degree_id))
      {
        
        // This course may exist in several different branches of a group, so we need
        // to mark all the branches as having been advised to take.  Usually, this CourseList
        // will probably only have 1 course object in it.  But, better safe than sorry.
        $course_list->reset_counter();
        if ($course = $course_list->get_next())
        {
          // make sure the hour count has been loaded correctly.
          if ($course->get_catalog_hours() < 1)
          {
            $course->load_descriptive_data();
          }

          // Let's start by looking at the first course.  Is it
          // supposed to be repeated?
          if ($course->bool_specified_repeat==true
          && $course->specified_repeats >= 0 )
          {
            // This is a course which is supposed to be repeated.
            // We need to cycle through and find an instance
            // of this course which has not been advised yet.


            $course_list->reset_counter();
            while($course_list->has_more())
            {
              $course = $course_list->get_next();

              // make sure the hour count has been loaded correctly.
              if ($course->get_catalog_hours() < 1)
              {
                $course->load_descriptive_data();
              }


              ////////////////////
              // Logan's change: https://bytetask.com/node/2455
              
              // If we have the setting which says we should skip if it's already been completed/enrolled for this term,
              // then we should do that.
              if (variable_get_for_school("remove_advised_when_course_taken", "no", $school_id) == "yes") {
                // First, see if this advised course has been attempted already.
                if ($this->student->list_courses_taken->find_specific_course($course->course_id, $advised_term_id)) {
                  // Yep, found it!  So, skip this one.
                  continue;
                }
              }
              
              //////////////////////////



              //if ($course->bool_advised_to_take != true && !is_object($course->courseFulfilledBy))
              if ($course->bool_advised_to_take != true && $course->course_list_fulfilled_by->is_empty == true)
              {
                // Okay, this course is supposed to be taken/advised
                // more than once.  So, I will mark this one as
                // advised, and then break out of the loop, since
                // I don't want to mark all occurances as advised.
                $course->bool_advised_to_take = true;
                $course->assigned_to_semester_num = $semester_num;
                //$course->assigned_to_group_id = $group_id;                
                $course->assigned_to_group_ids_array[$group_id] = $group_id;
                
                // Make sure we assign the hours to the group, so this
                // advised courses takes up a spot in the group.  Otherwise
                // it may be missed in later logic.
                if ($g = $this->degree_plan->find_group($group_id)) {
                  $h = $var_hours;
                  if ($h == 0) {
                    $h = $course->get_catalog_hours();
                    if ($h == 0) {
                      $h = 1;  // some problem occured. Just give it a token hour so it doesn't
                               // horribly break.
                    }
                  }
                  if (!is_numeric($g->hours_assigned)) {
                    $g->hours_assigned = floatval($g->hours_assigned);
                  }
                  $g->hours_assigned += floatval($h);                 
                }
                
                
                $course->advised_hours = $var_hours;
                $course->advised_term_id = $advised_term_id;
                $course->db_advised_courses_id = $id;
                $course_list->dec_specified_repeats($course);               
                break;
              }
            }
            continue;  // Go to the next advised course.
          }

        } // if $course = $course_list->get_next();

        //////////////////////////////
        // We're here, because it was not a repeatable course.
        // ** We should only go through THIS loop once!  So,
        // we will break after we make our assignment.
        $course_list->reset_counter();
        while($course_list->has_more())
        {
          
          $course = $course_list->get_next();
          // make sure the hour count has been loaded correctly.
          if ($course->get_catalog_hours() < 1)
          {
            $course->load_descriptive_data();
          }

          // make sure it has not already been advised to take.
          // Would occur if the same course is specified more
          // than once in a semester.
          if ($course->bool_advised_to_take == true)
          {
            continue;
          }

          // If we have the setting which says we should skip if it's already been completed/enrolled for this term,
          // then we should do that.
          if (variable_get_for_school("remove_advised_when_course_taken", "no", $school_id) == "yes") {
            // First, see if this advised course has been attempted already.            
            if ($taken_course = $this->student->list_courses_taken->find_specific_course($course->course_id, $advised_term_id)) {              
              // Yep, found it!  So, skip this one.              
              continue;
            }
          }
          

          // Has this course already been fulfilled by something?
          // If so, we cannot attempt to say it's been advised!
          if (!$course->course_list_fulfilled_by->is_empty)
          {
            // meaning, this course HAS been fulfilled.
            // So, let's move this advising to the "added by advisor"
            // spot.
            
            /*  This is the original bit of code here.  It is causing a problem when there are courses which are supposed to be
             * repeated.  Example: MUSC courses which are advised one term at a time.
             * 
             * 
            $this->assign_course_to_courses_added_list($course_id, $var_hours, $id, $advised_term_id);            
            break;
             * 
             * Strategy:  Find out how many courses are in this course_list by looking at the size of the list.  If it's > 1, then just continue.  If it == 1, then
             * do the original logic.
             * 
            */
            
            if ($course_list->get_size() > 1) {
              continue;
            }
            else {
              $this->assign_course_to_courses_added_list($course_id, $var_hours, $id, $advised_term_id);            
              break;              
            }
            
            
          }
          

          $course->bool_advised_to_take = true;
          $course->assigned_to_semester_num = $semester_num;
          //$course->assigned_to_group_id = $group_id;
          $course->assigned_to_group_ids_array[$group_id] = $group_id;

          // Make sure we assign the hours to the group, so this
          // advised courses takes up a spot in the group.  Otherwise
          // it may be missed in later logic.
          if ($g = $this->degree_plan->find_group($group_id)) {
            $h = $var_hours;
            if ($h == 0) {
              $h = $course->get_catalog_hours();
              if (!$h || $h == 0 || !is_numeric($h)) {
                $h = 1;  // some problem occured. Just give it a token hour so it doesn't
                         // horribly break.
              }
            }
            
            if (is_numeric($h)) {
              $g->hours_assigned += $h;
            }
            
          }
          
          $course->advised_hours = $var_hours;
          $course->advised_term_id = $advised_term_id;
          $course->db_advised_courses_id = $id;
          if ($course->required_on_branch_id > 0)
          {
            // In other words, this course was found on a branch, so we need
            // to increment that branch's count_of_matches.
            if ($branch = $this->degree_plan->find_group($course->required_on_branch_id))
            {
              $branch->count_of_matches++;
            } else {
              fpm("Error: Could not find branch.");
            }

          }

          // We should only be in this loop once, so let's
          // break after we make our assignment.
          break;

        }

      }

    }

    // Now, what we need to do is tell the DegreePlan to re-sort its
    // group's course lists, so that the advised courses are lower
    // than the fulfilled courses.

    //$this->degree_plan->sortGroupsFulfilledFirst();
    //print_pre($this->degree_plan->list_groups->toString());
    
    
  } // function loadAdvisingSessionFromDatabase


  function split_requirements_by_substitutions()
  {
    // Go through all the required courses on the degree plan,
    // and if there is a partial substitution specified in the student's
    // list of substitutions, then split that requirement into 2 courses,
    // one with enough hours to satisfy the sub, and the remaining hours.
    $degree_plan = $this->degree_plan;
    $student = $this->student;

    $student->list_substitutions->reset_counter();
    while($student->list_substitutions->has_more())
    {
      $substitution = $student->list_substitutions->get_next();

      $course_requirement = $substitution->course_requirement;
      $course_sub = $substitution->course_list_substitutions->get_first();

      // TODO: This could be an important part right here

      // Check to see if the courseSub's hours_awarded are less than the
      // course_requirement's min hours...
      if ($course_requirement->min_hours > $course_sub->get_hours_awarded())
      {
        // Meaning the original course requirement is not being
        // fully satisfied by this substitution!  The original
        // course requirement has hours left over which must be
        // fulfilled somehow.
        $remaining_hours = round($course_requirement->min_hours - $course_sub->get_hours_awarded(), 6);
        // This means that the course requirement needs to be split.
        // So, find this course in the degree plan.
        $required_course_id = $course_requirement->course_id;
        //$required_group_id = $course_requirement->assigned_to_group_id;
        $required_group_id = $course_requirement->get_first_assigned_to_group_id();  // we assume a course requirement is only assigned to 1 group.
        $required_semester_num = $course_requirement->assigned_to_semester_num;


      }



    }


  } // split_requirements_by_substitution



  function assign_course_to_courses_added_list($course_id, $var_hours = 0, $db_advised_courses_id = 0, $advised_term_id = 0)
  {
    // Set the supplied course as "advised to take" in the degree plan's
    // special added courses group, which is number -88.

    $course = new Course($course_id, false, $this->db);
    $course->bool_advised_to_take = true;
    $course->assigned_to_semester_num = DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED;
    //$course->assigned_to_group_id = -88;
    $course->assigned_to_group_ids_array[DegreePlan::GROUP_ID_FOR_COURSES_ADDED] = DegreePlan::GROUP_ID_FOR_COURSES_ADDED;
    $course->advised_hours = $var_hours;
    $course->db_advised_courses_id = $db_advised_courses_id;
    $course->advised_term_id = $advised_term_id;

    if ($group = $this->degree_plan->find_group(DegreePlan::GROUP_ID_FOR_COURSES_ADDED))
    {
      $group->list_courses->add($course);
    }

    // Done!
  }





}
