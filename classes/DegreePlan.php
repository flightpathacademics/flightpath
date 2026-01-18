<?php


class DegreePlan extends stdClass
{
  
  const DEGREE_ID_FOR_COMBINED_DEGREE = -1001;
  const SEMESTER_NUM_FOR_COURSES_ADDED = -88;
  const GROUP_ID_FOR_COURSES_ADDED = -88;
  const SEMESTER_NUM_FOR_DEVELOPMENTALS = -55;
  
  
  public $major_code, $title, $degree_type, $degree_level, $degree_class, $short_description, $long_description;
  public $list_semesters, $list_groups, $db, $degree_id, $catalog_year, $is_combined_dynamic_degree_plan, $combined_degree_ids_array;
  public $track_code, $track_title, $track_description, $student_array_significant_courses;
  public $bool_has_tracks, $array_semester_titles, $db_exclude, $db_allow_dynamic, $db_override_degree_hours, $db_advising_weight;
  public $public_notes_array, $school_id;

  public $total_major_hours, $total_core_hours, $total_degree_hours;
  public $fulfilled_major_hours, $fulfilled_core_hours, $fulfilled_degree_hours;
  public $major_qpts_hours, $core_qpts_hours, $degree_qpts_hours;
  public $major_qpts, $degree_qpts, $core_qpts;

  public $db_track_selection_config, $track_selection_config_array;

  public $required_course_id_array;  // We will keep track of every course which this degree lists as a requirement, even in groups.
                                    // looks like: required_course_id_array[$course_id][$degree_id][$group_id] = TRUE;

  public $gpa_calculations, $bool_calculated_progess_hours;
  
  
  public $bool_use_draft, $bool_loaded_descriptive_data;
  
  public $extra_data_array;  // This is an array meant for any generic "extra data" we want to include, from custom modules.
  

  /**
  * $major_code   ACCT, CSCI, etc.
  * $title      Accounting, Computer Science, etc.
  * $degree_type    BBA, BS, AS, etc.
  * $short_description  These are a text description of this degree plan.  Useful
  *         for descriptions of "Tracks" or "Options." The short
  * $long_description one appears in a pull down, the long one is a more
  *         complete text description.  Will probably be unused
  *         by most degrees.
  * $list_semesters A list of semesters that this DegreePlan requires.
  * $list_degree_plans  If this degree plan has multiple tracks or options, then
  *         they would be spelled out as other degree plans, and listed
  *         here.  For example, Biology has multiple "tracks" which,
  *         internally, should be treated as different degree plans.
  **/


  function __construct($degree_id = "", DatabaseHandler $db = NULL, $bool_load_minimal = FALSE, $array_significant_courses = FALSE, $bool_use_draft = FALSE) {
    $this->list_semesters = new ObjList();
    $this->list_groups = new GroupList();
    $this->bool_use_draft = $bool_use_draft;
    // Always override if the global variable is set.
    if (@$GLOBALS["fp_advising"]["bool_use_draft"] == true) {
      $this->bool_use_draft = true;
    }

    $this->school_id = 0;  //default

    $this->required_course_id_array = array();

    $this->public_notes_array = array();
    $this->extra_data_array = array();

    $this->db_advising_weight = 0;
    $this->track_selection_config_array = array();

    $this->db = $db;
    if ($db == NULL)
    {
      $this->db = get_global_database_handler();
    }


    $this->student_array_significant_courses = $array_significant_courses;

    if ($degree_id != "")
    {
    
      $this->degree_id = $degree_id;
      $this->load_descriptive_data();      
      if (!$bool_load_minimal)
      {            
        $this->load_degree_plan();                 
      }
      // Add the "Add a Course" semester to the semester list.
      $this->add_semester_courses_added();

    }

     
    if ($this->degree_level == "") {
      $this->degree_level = "UG";  // undergrad by default
    }


  } // __construct


  
  /**
   * Given a group_id, find out if this group contains a course which appears in other degrees.  Return the max number.
   */
  function get_max_course_appears_in_degrees_count($test_group_id) {
    
    // array is sectioned like:  course_id | degree_id | group_id.    Group id = 0 means "on the bare degree plan"
    // ex:  $this->required_course_id_array[$course_c->course_id][$this->degree_id][0] = TRUE;      
    
    $exclude_degree_ids = system_get_exclude_degree_ids_from_appears_in_counts($this->school_id);
    
    $courses = array();
    
    foreach ($this->required_course_id_array as $course_id => $temp1) {
      foreach ($this->required_course_id_array[$course_id] as $degree_id => $temp2) {
        
        // Is this an excluded degree?  If so, skip it.
        if (in_array($degree_id, $exclude_degree_ids)) continue;
        
        foreach ($this->required_course_id_array[$course_id][$degree_id] as $group_id => $val) {
          
          // Is this the group we are looking for?
          if ($group_id != $test_group_id) continue;
          
          // Otherwise, yes, we are in the right group.  Let's keep track of what courses we have in this group:                    
          $courses[$course_id] = TRUE;
          
        }
      }
    }
    
    $course_count = array();

    // Okay, now what we want to do is find out, how many different degrees do these courses appear in?
    foreach ($this->required_course_id_array as $course_id => $temp1) {
      
      if (!isset($courses[$course_id])) continue; // wasn't in our list, so skip.
      $course_count[$course_id] = 0;
      
      foreach ($this->required_course_id_array[$course_id] as $degree_id => $temp2) {
        
        $course_count[$course_id]++;
                
      }
    }
    
    
        
    // Okay, coming out of this, we can sort the courses_degrees array, and return the highest number.
    rsort($course_count);        
    
    //fpm($course_count);
    
    return @$course_count[0];  // first element should be the highest value.
    
  } //get_max_course_appears_in_degrees_count




  /**
   * Calculate and store progress hour information.  Stores in the $this->gpa_calculations array.
   * 
   * 
   * @param $bool_get_local_only_hours - If set to TRUE, then "local" courses (non-transfer) will be separated into their own indexes.
   * @param $types - the type codes we care about.  If left as an emtpy array, it will get all the types defined + "degree" for degree total.
   *                 The array structure should be "code" => "code".  Ex:  array('a' => 'a')
   * 
   */
  function calculate_progress_hours($bool_get_local_only_hours = FALSE, $types = array())
  {
    
    // Let's go through our requirement types by code, and collect calcuations on them
    // in the gpa_calculations array.
    if (count($types) == 0) {
      // Wasn't set, so use ALL of the known requirement types.
      $types = fp_get_requirement_types($this->school_id);
    }

    // Add a pseudo-code in for "degree", which the functions will convert into a blank.
    $types["degree"] = t("Degree (total)");

    // We want to do this for all possible degrees.
    $all_degree_ids = array();
    
    $all_degree_ids[0] = 0;  // Add in the default "0" degree, meaning, don't look for a specific degree_id.
    
    if ($this->degree_id != DegreePlan::DEGREE_ID_FOR_COMBINED_DEGREE) {
      
      // Not a combined degree, just use the current degree_id.
      // $all_degree_ids[] = $this->degree_id;  // comment out... not needed?
    }
    else {
        
      // Add in all the degrees we are combined with.      
      $all_degree_ids = array_merge($all_degree_ids, $this->combined_degree_ids_array);
    }
        
        
    foreach ($all_degree_ids as $degree_id) {        
        
      foreach ($types as $code => $desc) {
        // Make sure to skip appropriate codes we don't care about.      
        
        if ($code == 'x') continue;      
        
        $this->gpa_calculations[$degree_id][$code]["total_hours"] = $this->get_progress_hours($code, TRUE, FALSE, FALSE, $degree_id);      
        $this->gpa_calculations[$degree_id][$code]["fulfilled_hours"] = $this->get_progress_hours($code, FALSE, FALSE, FALSE, $degree_id);      
        $this->gpa_calculations[$degree_id][$code]["qpts_hours"] = $this->get_progress_hours($code, FALSE, TRUE, FALSE, $degree_id);      
        
        
        if ($bool_get_local_only_hours) {
          // Get ONLY local hours, too....
          $this->gpa_calculations[$degree_id][$code . "_local"]["total_hours"] = $this->get_progress_hours($code, TRUE, FALSE, TRUE, $degree_id);
          $this->gpa_calculations[$degree_id][$code . "_local"]["fulfilled_hours"] = $this->get_progress_hours($code, FALSE, FALSE, TRUE, $degree_id);
          $this->gpa_calculations[$degree_id][$code . "_local"]["qpts_hours"] = $this->get_progress_hours($code, FALSE, TRUE, TRUE, $degree_id);     
        }
        
      } // foreach types as code
    
    } //foreach all_degree_ids
    
      
    // Note that we have run this function for this degree.
    $this->bool_calculated_progess_hours = TRUE;
     
  }// calculate progress hours

  /**
   * Calculate the quality points of our completed courses, so we can use
   * that to figure out GPA.
   *
   */
  function calculate_progress_quality_points($bool_get_local_only_hours = FALSE, $types = array()) {      
    
    // Let's go through our requirement types by code, and collect calcuations on them
    // in the gpa_calculations array.
    if (count($types) == 0) {
      $types = fp_get_requirement_types($this->school_id);
    }
    
    // Add a pseudo-code in for "degree", which the functions will convert into a blank.
    $types["degree"] = "Degree (total)";
    
    // We want to do this for all possible degrees.
    $all_degree_ids = array();
    $all_degree_ids[0] = 0;  // Add in the default "0" degree, meaning, don't look for a specific degree_id.
    
    if ($this->degree_id != DegreePlan::DEGREE_ID_FOR_COMBINED_DEGREE) {
      // Not a combined degree, just use the current degree_id.
      // $all_degree_ids[] = $this->degree_id;   // Not needed?
    }
    else {
      $all_degree_ids = array_merge($all_degree_ids, $this->combined_degree_ids_array);
    }    
    
    foreach ($all_degree_ids as $degree_id) {
      foreach ($types as $code => $desc) {
        // Make sure to skip appropriate codes we don't care about.
        if ($code == 'x') continue;
        
        $this->gpa_calculations[$degree_id][$code]["qpts"] = $this->get_progress_quality_points($code, FALSE, $degree_id);
        
        if ($bool_get_local_only_hours) {
          // Get only local courses, too...
          $this->gpa_calculations[$degree_id][$code . "_local"]["qpts"] = $this->get_progress_quality_points($code, TRUE, $degree_id);
        }
      } //foreach types as code
      
    } //foreach all_degree_ids
            
                
    
  } // calculate progess quality points
  

  /**
   * Returns the number of hours required (or fulfilled) in a degree plan
   * for courses & groups with the specified requirement_type.
   * ex:  "m", "s", etc.  leave blank for ALL required hours.
   * if boolRequiredHours is FALSE, then we will only look for the courses
   * which got fulfilled.
   * 
   * 
   *  
   */
  function get_progress_hours($requirement_type = "", $bool_required_hours_only = TRUE, $bool_qpts_grades_only = FALSE, $bool_exclude_all_transfer_credits = FALSE, $req_by_degree_id = 0)
  {
    
    
    if ($requirement_type == "degree") $requirement_type = "";

        
    
    $hours = 0;
    
    $this->list_semesters->reset_counter();
    while ($this->list_semesters->has_more())
    {
      $sem = $this->list_semesters->get_next();

      if ($bool_required_hours_only == TRUE)
      {
                
        $hours += $sem->list_courses->count_hours($requirement_type, TRUE, FALSE, FALSE, FALSE, $req_by_degree_id);  // do not exclude transfer credits, since this is for required hours only.
        
      } else {
        $temp = $sem->list_courses->count_credit_hours($requirement_type, TRUE, TRUE, $bool_qpts_grades_only, $bool_exclude_all_transfer_credits, $req_by_degree_id);
                
        $hours += $temp;
      }
    }                       

    
    // Also, add in groups matching this requirement type.
    $this->list_groups->reset_counter();
    while ($this->list_groups->has_more())
    {
      $g = $this->list_groups->get_next();
      if ($g->group_id < 0)                           
      { // Skip Add a course group.
        continue;
      }

      // Make sure the group doesn't have a type of 'x' assigned to it, which means we should
      // skip it.
      if ($g->requirement_type == 'x') continue;
      
      // If req_by_degree_id is set, make sure this group is assigned to that degree.
      if ($req_by_degree_id != 0 && $g->req_by_degree_id != $req_by_degree_id) continue;


      $g_hours = $g->hours_required;
      
      // use the min hours if it is set.
      if ($g->min_hours_allowed > 0) {
        $g_hours = $g->min_hours_allowed;
      }
      
      if ($bool_required_hours_only == false)
      { // only count the fulfilled hours, then.        
        $g_hours = $g->get_fulfilled_hours(true, false, true, -1, true, $bool_qpts_grades_only, $requirement_type, $bool_exclude_all_transfer_credits);        

      }

      if ($requirement_type == "")
      {
        $hours += $g_hours;
      } 
      else {
        // A requirement is specified, so make sure
        // the group is of this requirement.

        if (!isset($g->hours_required_by_type[$requirement_type])) $g->hours_required_by_type[$requirement_type] = 0;
        
        if ($bool_required_hours_only == true)
        {  // make sure it's of the right type.
          //$g_hours = $g->hours_required_by_type[$requirement_type]*1;

          // it should just be any hours_required, instead of by type, since a group can only have 1 type.
          if ($g->requirement_type == $requirement_type) {
            $g_hours = $g->hours_required;
            
            // use the min hours if it is set.
            if ($g->min_hours_allowed > 0) {
              $g_hours = $g->min_hours_allowed;
            }            
              
            $hours += $g_hours;
            continue;
          }
          
        }
        
        if ($g->requirement_type == $requirement_type)
        {            
          $hours += $g_hours;
        }
        
      
        
        
      }
    }
    
    return $hours;

  }
  
  
  /**
   * Similar to get_progress_hours, this will return back the quality points a student has earned
   * towards this degree.  It can then be used to calculate GPA.
   *
   * @param unknown_type $requirement_type
   * @param unknown_type $bool_required_hours_only
   * @return unknown
   */
  function get_progress_quality_points($requirement_type = "", $bool_exclude_all_transfer_credits = FALSE, $req_by_degree_id = 0) {
    // Returns the number of hours required (or fulfilled) in a degree plan
    // for courses & groups with the specified requirement_type.
    // ex:  "m", "s", etc.  leave blank for ALL required hours.
    // if boolRequiredHours is FALSE, then we will only look for the courses
    // which got fulfilled.

    if ($requirement_type == "degree") $requirement_type = "";
    
    $points = 0;

    $this->list_semesters->reset_counter();
    while ($this->list_semesters->has_more())
    {
      $sem = $this->list_semesters->get_next();

      $p = $sem->list_courses->count_credit_quality_points($requirement_type, true, true, $bool_exclude_all_transfer_credits, $req_by_degree_id);
      $points = $points + $p;
      
    }

    
    // Also, add in groups matching this requirement type.
    $this->list_groups->reset_counter();
    while ($this->list_groups->has_more())
    {
      $g = $this->list_groups->get_next();
      if ($g->group_id < 0)
      { // Skip Add a course group.
        continue;
      }
            
      // Make sure the group doesn't have a type of 'x' assigned to it, which means we should
      // skip it.
      if ($g->requirement_type == 'x') continue;
      
      // if req_by_degree_id is set, make sure the group belongs to that degree id!
      if ($req_by_degree_id != 0 && $g->req_by_degree_id != $req_by_degree_id) continue;      
      
      
      $g_points = $g->get_fulfilled_quality_points(TRUE, -1, TRUE, TRUE, $requirement_type, $bool_exclude_all_transfer_credits);
      $points = $points + $g_points;       

      
    }
    

    
    return $points;

  }  
  
  
  
  /**
   * Loads the "ancillary" information about our degree plan, including advising weight, track selection config, etc.
   *
   */
  function load_degree_plan_ancillary() {
    $degree_id = $this->degree_id;

    
    $old_semester = "";
    $table_name1 = "degrees";
    $table_name2 = "degree_requirements";
    if ($this->bool_use_draft) {
      $table_name1 = "draft_$table_name1";
      $table_name2 = "draft_$table_name2";
    }
    
    
    // We want to get some of the data for this degree.
    $res = db_query("SELECT * FROM $table_name1 WHERE degree_id = ?", $this->degree_id);
    if ($res) {
      $cur = db_fetch_array($res);
  
      $this->title = @$cur["title"];
      $this->major_code = @$cur["major_code"];
      $this->degree_level = @strtoupper(trim($cur["degree_level"]));
      
      if ($this->degree_level == "") {
        $this->degree_level = "UG";  // undergrad by default
      }      
      
      $this->degree_class = @$cur["degree_class"];
      $this->db_override_degree_hours = @$cur["override_degree_hours"];
      $this->db_advising_weight = @intval($cur["advising_weight"]);
      $data_entry_value = @trim($cur['data_entry_value']);
  
      $this->db_track_selection_config = @trim($cur["track_selection_config"]);
      $this->parse_track_selection_config();  // load into the track_selection_config_array as needed.
    }
    
  }  // load_degree_plan_ancillary
  
  
  
  
  /**
   * Load our complete degree plan, including all courses and groups.
   *
   */
  function load_degree_plan() {
    
        
    // Load this degree plan from the database and fully
    // assemble it.
    $degree_id = $this->degree_id;

    $old_semester = "";
    $table_name1 = "degrees";
    $table_name2 = "degree_requirements";
    if ($this->bool_use_draft) {
      $table_name1 = "draft_$table_name1";
      $table_name2 = "draft_$table_name2";
    }

    // Degrees we should exclude from the "appears in" counts.  Used later...
    $exclude_degree_ids = NULL;


    $res = $this->db->db_query("SELECT * FROM $table_name1 a, $table_name2 b
                          WHERE a.degree_id = ?
                          AND a.degree_id = b.degree_id 
                          ORDER BY semester_num ", array($this->degree_id));
    while ($cur = $this->db->db_fetch_array($res))
    {
      $this->title = $cur["title"];
      $this->major_code = $cur["major_code"];
      $this->school_id = intval($cur['school_id']);
      if (!$exclude_degree_ids) {
        $exclude_degree_ids = system_get_exclude_degree_ids_from_appears_in_counts($this->school_id);
      }
      
      $this->degree_level = strtoupper(trim($cur["degree_level"]));
      if ($this->degree_level == "") {
        $this->degree_level = "UG";  // undergrad by default
      }      
      $this->degree_class = $cur["degree_class"];
      $this->db_override_degree_hours = $cur["override_degree_hours"];
      $this->db_advising_weight = intval($cur["advising_weight"]);
      $data_entry_value = trim($cur['data_entry_value']);

      $this->db_track_selection_config = trim((string) $cur["track_selection_config"]);
      $this->parse_track_selection_config();  // load into the track_selection_config_array as needed.

      $semester_num = $cur["semester_num"];
      if ($semester_num != $old_semester)
      {
        // This is a new semester object we are dealing with.
        $old_semester = $semester_num;
        $obj_semester = new Semester($semester_num);
        $obj_semester->title = trim((string) @$this->array_semester_titles[$semester_num]);
        if ($obj_semester->title == "") {
           $obj_semester->assign_title();
        }
        $this->list_semesters->add($obj_semester);
      }

      if ($cur["course_id"]*1 > 0)
      {
        // A course is the next degree requirement.
        
        //if ($this->bool_use_draft) $cat_year = $this->catalog_year;        
        $cat_year = $this->catalog_year;        
        
        $course_c = new Course($cur["course_id"], false, $this->db, false, $cat_year, $this->bool_use_draft);
        $course_c->assigned_to_semester_num = $semester_num;
        $course_c->min_grade = trim(strtoupper($cur["course_min_grade"]));
        if ($course_c->min_grade == "")
        { // By default, all courses have a
          // min grade requirement of the lowest passing grade.          
          $course_c->min_grade = strtoupper(variable_get_for_school("minimum_passing_grade", "D", $this->school_id));
          
        }
        $course_c->requirement_type = trim($cur["course_requirement_type"]);

        // Set which degree_id this course is a requirement of (for multiple degrees)
        $course_c->req_by_degree_id = $this->degree_id;
        $course_c->db_degree_requirement_id = $cur['id']; 

        //adminDebug($course_c->to_string() . $course_c->getCatalogHours());
        
        $obj_semester->list_courses->add($course_c);
        
        if (!in_array($this->degree_id, $exclude_degree_ids)) {
          // array is sectioned like:  course_id | degree_id | group_id.    Group id = 0 means "on the bare degree plan"
          $this->required_course_id_array[$course_c->course_id][$this->degree_id][0] = TRUE;
        }        
        
      } // if course_id > 0

      
      
      
      if ($cur["group_id"]*1 > 0)
      {
        // A group is the next degree requirement.
                
        
        $title = "";
        $icon_filename = "";
        // Add the real Group (with all the courses, branches, etc)
        // to the DegreePlan's group list!
        // First, see if this group already exists.  If it does,
        // simply add the number of hours required to it.  If not,
        // create it fresh.
        
        if ($new_group = $this->find_group($cur["group_id"] . '_' . $this->degree_id))   // group_id's will always have db_group_id _ degree_id from now on...
        {
          // Was already there (probably in another semester),
          // so, just increment the required hours.
          
          if (!isset($new_group->hours_required_by_type[$cur["group_requirement_type"]])) {
            $new_group->hours_required_by_type[$cur["group_requirement_type"]] = 0;
          }
          
          $new_group->hours_required = $new_group->hours_required + ($cur["group_hours_required"] * 1);
          $new_group->hours_required_by_type[$cur["group_requirement_type"]] += ($cur["group_hours_required"] * 1);
          //Set which degree_id this is required by.
          $new_group->req_by_degree_id = $this->degree_id;
          
          $title = $new_group->title;
          $icon_filename = $new_group->icon_filename;
        } 
        else {          
          // Was not already there; insert it.
          $group_n = new Group($cur["group_id"] . '_' . $this->degree_id, $this->db, $semester_num, $this->student_array_significant_courses, $this->bool_use_draft, $cur["group_requirement_type"]);
          
          $group_n->hours_required = $cur["group_hours_required"] * 1;          
          
          if (!isset($group_n->hours_required_by_type[$cur["group_requirement_type"]])) $group_n->hours_required_by_type[$cur["group_requirement_type"]] = 0;
          $group_n->hours_required_by_type[$cur["group_requirement_type"]] += $group_n->hours_required;
          $group_n->set_req_by_degree_id($this->degree_id);
          if (trim($cur["group_min_grade"]) != "")
          {
            $group_n->assign_min_grade(trim(strtoupper($cur["group_min_grade"])));
          }
          $title = $group_n->title;
          $icon_filename = $group_n->icon_filename;
          
          $this->list_groups->add($group_n);
        }


        // Add a placeholder to the Semester....
        $group_g = new Group();
        $group_g->bool_use_draft = $this->bool_use_draft;
        $group_g->group_id = $cur["group_id"] . '_' . $this->degree_id;
        $group_g->set_req_by_degree_id($this->degree_id);
        $group_g->load_descriptive_data();
        $group_g->set_requirement_type($cur["group_requirement_type"]);
        if (trim($cur["group_min_grade"]) != "")
        {
          $group_g->assign_min_grade(trim(strtoupper($cur["group_min_grade"])));
        }
        $group_g->assigned_to_semester_num = $semester_num;
        $group_g->title = "$title";
        $group_g->icon_filename = $icon_filename;
        $group_g->hours_required = floatval($cur["group_hours_required"]);
        $group_g->min_hours_allowed = floatval($cur["group_min_hours_allowed"]);
        $group_g->bool_placeholder = true;
        $obj_semester->list_groups->add($group_g);
        
      }// if group_id > 0

    } // while db results


    $this->list_groups->sort_priority();
    
    if (!is_array($exclude_degree_ids)) $exclude_degree_ids = array();
    
    if (!in_array($this->degree_id, $exclude_degree_ids)) {
      $group_course_id_array = $this->list_groups->get_group_course_id_array();
      // Add to our required_course_id_array.      
      foreach($group_course_id_array as $group_id => $details) {
        foreach ($group_course_id_array[$group_id] as $course_id => $val) {
          $this->required_course_id_array[$course_id][$this->degree_id][$group_id] = $val;
        }
      }
    }

      
      
      
      
    // When we load this degree plan, let's also check for any hooks.
    // Since this class might be used outside of FP, only do this if we know
    // that the bootstrap.inc file has been executed.
    if ($GLOBALS["fp_bootstrap_loaded"] == TRUE) {      
      invoke_hook("degree_plan_load", array(&$this));
    }         
      
      
      
      
  } // load_degree_plan


  /**
   * Add another degree's required_course_id_array onto this one's.
   */
  function add_to_required_course_id_array($req_course_id_array) {
    
    foreach ($req_course_id_array as $course_id => $details) {
      foreach ($req_course_id_array[$course_id] as $degree_id => $details2) {
        foreach ($req_course_id_array[$course_id][$degree_id] as $group_id => $val) {
            
          $this->required_course_id_array[$course_id][$degree_id][$group_id] = $val;
        }
      }
    }
    
    
  } // add_to_required_course_id_array


  /**
   * This function will parse through the db_track_selection_config string and
   * populate the track_selection_config_array.
   * 
   * We assume the string looks like this:
   * 
   * CLASS ~ MIN ~ MAX ~ DEFAULT_CSV
   * 
   * ex:
   * CONCENTRATION ~ 0 ~ 1 ~
   * EMPHASIS ~ 1 ~ 1 ~ ART|_SCULT, ART|_PAINT
   * 
   * 
   * 
   */
  function parse_track_selection_config() {
    $lines = explode("\n", $this->db_track_selection_config);
    foreach ($lines as $line) {
      $line = trim($line);
      if ($line == "") continue;  // blank line, skip it.
      if (substr($line, 0, 1) == "#") continue; // this is a comment, skip it.
      
      $temp = explode("~", $line);
      $machine_name = @trim($temp[0]);
      $min = @intval($temp[1]);
      $max = @intval($temp[2]);
      $default_csv = @trim($temp[3]);
      
      $this->track_selection_config_array[$machine_name] = array(
        "machine_name" => $machine_name,
        "min_tracks" => $min,
        "max_tracks" => $max,
        "default_tracks" => $default_csv,
      );
      
      
      
    }      
        
      
    
  } // parse... config



  function get_title($bool_include_track = false)
  {
    // This will return the title of this degree, possibly
    // including the track's title as well.

    $rtn = $this->title;
    if ($bool_include_track == true)
    {
      if ($this->track_title != "")
      {
        $rtn .= " with " . $this->track_title . "";
      }
    }

    return $rtn;

  }

  /**
   * Returns back a CSV of all the major codes that this degree comprises
   */
  function get_major_code_csv() {
    if (!$this->is_combined_dynamic_degree_plan) return $this->major_code;  // just a basic single non-combined degree.
    
    // Otherwise, we should assume this is a combined dynamic degree.
    $rtn = "";
    
    foreach ($this->combined_degree_ids_array as $degree_id) {
      $t_degree_plan = new DegreePlan($degree_id);
      $rtn .= $t_degree_plan->major_code . ",";
    }
    
    $rtn = rtrim($rtn, ","); // remove last comma, if its there.
    
    return $rtn;
    
  }



  function get_track_title($bool_include_classification = FALSE) {
    $this->load_descriptive_data();
    
    $ttitle = fp_trim($this->track_title);
    if ($ttitle == "") return FALSE;  // there is no track?
            
    if ($bool_include_classification) {
      
      $details = fp_get_degree_classification_details($this->degree_class);
      
      $ttitle .= " (" . $details["title"] . ")";
    }

    return $ttitle;

            
  }


  function get_title2($bool_include_classification = FALSE, $bool_include_track_title = FALSE, $bool_include_html = TRUE)
  {
    // This will simply return the degree's title.  If it does not
    // exist, it will try to find another degree with the same major_code.
    // This is to fix the problem with students with catalog years outside
    // of FlightPath's database, but with major codes that have titles.

    if (!$this->bool_loaded_descriptive_data) {
      $this->load_descriptive_data();
    }
    
    $dtitle = "";

    if ($this->title) {
      $dtitle = $this->title;
      if ($bool_include_html) {
        $dtitle = "<span class='deg-title'>$this->title</span>";
      }      
    }
    else {


      // Still no title?  Try to load ANY degree title with this degree's
      // major_code.
      $this->title = '';
      $table_name = "degrees";
      if ($this->bool_use_draft) {$table_name = "draft_$table_name";}
  
      $res = $this->db->db_query("SELECT title FROM $table_name
                              WHERE major_code = ? 
                              ORDER BY catalog_year DESC LIMIT 1", $this->major_code);
      $cur = $this->db->db_fetch_array($res);
      if ($cur) {
        $this->title = trim($cur["title"]);
      }
      if ($bool_include_html && $this->title) {
        $dtitle = "<span class='deg-title'>$this->title</span>";
      }
      else {
        $dtitle = $this->title;
      }
    }


    if ($bool_include_track_title && $this->track_title != "") {
      if ($bool_include_html && $this->title) {  
        $dtitle .= "<span class='level-3-raquo'>&raquo;</span>";
        $dtitle .= "<span class='deg-track-title'>$this->track_title</span>";
      }
      else {        
        $dtitle .= $this->track_title;
      }
    }
    
    if ($bool_include_classification && $this->degree_class != "") {
      $details = fp_get_degree_classification_details($this->degree_class);
      if ($bool_include_html) {
        $dtitle .= " <span class='deg-class-title'>(" . $details["title"] . ")</span>";
      }
      else {
        $dtitle .= " (" . $details["title"] . ")";
      }
    }


    return $dtitle;
  }


  function load_descriptive_data()
  {
    
    $this->bool_loaded_descriptive_data = TRUE;
   
    
    // If we already have this in our cache, then look there... 
    $cache_name = 'degreeplan_cache';
    if ($this->bool_use_draft) {
      $cache_name = 'degreeplan_cache_draft';
    }
    if (isset($GLOBALS[$cache_name][$this->degree_id])) {
      $this->array_semester_titles = $GLOBALS[$cache_name][$this->degree_id]['array_semester_titles'];
      $this->bool_has_tracks = $GLOBALS[$cache_name][$this->degree_id]['bool_has_tracks'];
      $this->catalog_year = $GLOBALS[$cache_name][$this->degree_id]['catalog_year'];
      $this->db_advising_weight = $GLOBALS[$cache_name][$this->degree_id]['db_advising_weight'];
      $this->db_allow_dynamic = $GLOBALS[$cache_name][$this->degree_id]['db_allow_dynamic'];
      $this->db_exclude = $GLOBALS[$cache_name][$this->degree_id]['db_exclude'];
      $this->db_override_degree_hours = $GLOBALS[$cache_name][$this->degree_id]['db_override_degree_hours'];
      $this->degree_class = $GLOBALS[$cache_name][$this->degree_id]['degree_class'];
      $this->degree_level = $GLOBALS[$cache_name][$this->degree_id]['degree_level'];
      $this->school_id = $GLOBALS[$cache_name][$this->degree_id]['school_id'];
      
      if ($this->degree_level == "") {
        $this->degree_level = "UG";  // undergrad by default
      }      
      
      $this->degree_type = $GLOBALS[$cache_name][$this->degree_id]['degree_type'];
      $this->major_code = $GLOBALS[$cache_name][$this->degree_id]['major_code'];
      $this->public_notes_array = $GLOBALS[$cache_name][$this->degree_id]['public_notes_array'];
      $this->title = $GLOBALS[$cache_name][$this->degree_id]['title'];
      $this->track_code = $GLOBALS[$cache_name][$this->degree_id]['track_code'];
      $this->track_description = $GLOBALS[$cache_name][$this->degree_id]['track_description'];
      $this->track_title = $GLOBALS[$cache_name][$this->degree_id]['track_title'];
      return;
    }    
    
    
    
    $table_name = "degrees";
    if ($this->bool_use_draft) {$table_name = "draft_$table_name";}

   
    $res = $this->db->db_query("SELECT * FROM $table_name
                               WHERE degree_id = ? ", $this->degree_id);

    if ($this->db->db_num_rows($res) > 0)
    {
      $cur = $this->db->db_fetch_array($res);
      $this->major_code = $cur["major_code"];
      $this->degree_level = strtoupper(trim($cur["degree_level"]));

      if ($this->degree_level == "") {
        $this->degree_level = "UG";  // undergrad by default
      }      
      
      $this->degree_class = $cur["degree_class"];
      $this->db_override_degree_hours = $cur["override_degree_hours"];
      
      $this->title = $cur["title"];
      $this->school_id = intval($cur['school_id']);
      $this->public_notes_array[$this->degree_id] = $cur["public_note"];
      $this->catalog_year = $cur["catalog_year"];
      $this->degree_type = trim($cur["degree_type"]);
      $this->db_exclude = trim($cur["exclude"]);
      $this->db_allow_dynamic = trim($cur["allow_dynamic"]);
      $this->db_advising_weight = intval($cur["advising_weight"]);

      // Get the semester titles.
      $temp = trim((string) $cur["semester_titles_csv"]);
      $this->array_semester_titles = explode(",",$temp);

      $just_major_code = $this->major_code;
      
      if (strstr($this->major_code, "_"))
      {
        // This means that there is a track.  Get all the information
        // you can about it.
        $temp = explode("_", $this->major_code);
        $this->track_code = trim($temp[1]);
        
        $just_major_code = trim($temp[0]);  // Don't change major_code value-- causes a bug in FP5

        // The major_code might now have a | at the very end.  If so,
        // get rid of it.
        $just_major_code = rtrim($just_major_code, "|");
        
        // Now, look up information on the track.
        $table_name = "degree_tracks";
        if ($this->bool_use_draft) {$table_name = "draft_$table_name";}


        static $degree_track_cache = array();
        $cur = null;
        if (isset($degree_track_cache[$table_name][$this->major_code][$this->track_code][$this->catalog_year])) {
          $cur = $degree_track_cache[$table_name][$this->major_code][$this->track_code][$this->catalog_year];
        } 
        else {
              $res = $this->db->db_query("SELECT track_title, track_description FROM $table_name
                                      WHERE major_code = ?
                                      AND track_code = ?
                                      AND catalog_year = ? ", $just_major_code, $this->track_code, $this->catalog_year);
              $cur = $this->db->db_fetch_array($res);
              $degree_track_cache[$table_name][$just_major_code][$this->track_code][$this->catalog_year] = $cur;
        }

        $this->track_title = $cur["track_title"];
        $this->track_description = $cur["track_description"];

      }

      // Does this major have any tracks at all?  If so, set a bool.
      if ($this->db->get_degree_tracks($just_major_code, $this->catalog_year))
      {
        $this->bool_has_tracks = true;
      }

    }

    
    // Add to our GLOBALS cache for this degree's descriptive data.
    $GLOBALS[$cache_name][$this->degree_id] = array(
      'array_semester_titles' => $this->array_semester_titles,
      'bool_has_tracks' => $this->bool_has_tracks,
      'catalog_year' => $this->catalog_year,
      'db_advising_weight' => $this->db_advising_weight,
      'db_allow_dynamic' => $this->db_allow_dynamic,
      'db_exclude' => $this->db_exclude,
      'db_override_degree_hours' => $this->db_override_degree_hours,
      'degree_class' => $this->degree_class,
      'degree_level' => $this->degree_level,
      'degree_type' => $this->degree_type,
      'major_code' => $this->major_code,
      'public_notes_array' => $this->public_notes_array,
      'title' => $this->title,
      'track_code' => $this->track_code,
      'track_description' => $this->track_description,
      'track_title' => $this->track_title,
      'school_id' => $this->school_id,
    );    




  }


  function get_advised_courses_list()
  {
    // Return a courseList object containing every course
    // in this degreePlan which is marked as boolAdvisedToTake=true.
    $rtn_list = new CourseList();

    $this->list_semesters->reset_counter();
    while ($this->list_semesters->has_more())
    {
      $semester = $this->list_semesters->get_next();
      $rtn_list->add_list($semester->list_courses->get_advised_courses_list());
    }
    $rtn_list->add_list($this->list_groups->get_advised_courses_list());

    return $rtn_list;
  }


  /**
   * Look through our list of semesters for the one with this semester_num, and return it, or return FALSE
   */
  function get_semester($semester_num) {
    $this->list_semesters->reset_counter();
    while ($this->list_semesters->has_more()) {
      $sem = $this->list_semesters->get_next();
      if ($sem->semester_num == $semester_num) {
        return $sem;
      }
    }
    
    return FALSE;
  }



  /**
   * Returns a simple array with values seperated by " ~~ "
   * in this order: track_code ~~ track_title ~~ trackDesc ~~ track's degree id
   *
   * @return array
   */
  function get_available_tracks()
  {
    $rtn_array = array();

    $rtn_array[] = "  ~~ None ~~ Select this option to display
            the base degree plan (may not be available for all majors).";
    $table_name = "degree_tracks";
    $table_name2 = "degrees";
    if ($this->bool_use_draft) {$table_name = "draft_$table_name";}
    if ($this->bool_use_draft) {$table_name2 = "draft_$table_name2";}

    $res = db_query("SELECT track_code, track_title, track_description FROM $table_name
                              WHERE major_code = ?
                              AND catalog_year = ? 
                              AND school_id = ?
                              ORDER BY track_title ", $this->major_code, $this->catalog_year, $this->school_id);
    while($cur = db_fetch_array($res))
    {

      $track_code = $cur["track_code"];
      $track_title = $cur["track_title"];
      $track_description = $cur["track_description"];
      

      // Let's also get the degree_id for this particular track.
      $track_degree_id = $this->db->get_degree_id($this->major_code . "|_" . $track_code, $this->catalog_year, $this->bool_use_draft, $this->school_id);
      
      // Also find out what is the degree_class for this degree_id.
      $degree_class = @trim(db_result(db_query("SELECT degree_class FROM $table_name2
                        WHERE degree_id = ?", $track_degree_id))); 
  
      
      $rtn_array[] = "$track_code ~~ $track_title ~~ $track_description ~~ $track_degree_id ~~ $degree_class";
    }

    if (count($rtn_array) > 1)  // we're going to have at least 1 because of the "none" option.  Let's skip that one.
    {
      return $rtn_array;
    } else {
      return false;
    }


  }


  function add_semester_developmental($student_id)
  {
    // This will add the developmental courses in as
    // a semester.  Will check the studentID to see if any
    // developmentals are required.
    // -55 is the developmental semester.
    $sem = new Semester(DegreePlan::SEMESTER_NUM_FOR_DEVELOPMENTALS);
    $sem->title = variable_get_for_school("developmentals_title", t("Developmental Requirements", $this->school_id));
    $is_empty = true;

    
    $temp_array = $this->db->get_developmental_requirements($student_id, $this->school_id);
    // We expect this to give us back an array like:
    // 0 => ART~101
    // 1 => MATH~090
    foreach($temp_array as $temp_course_name) {
      $temp = explode("~", $temp_course_name);
      $c = new Course($this->db->get_course_id($temp[0], $temp[1], '', FALSE, $this->school_id, TRUE));
      $c->min_grade = "C";
      $c->requirement_type = "dev";
      $sem->list_courses->add($c);
      
      $is_empty = false;      
    }
    
    $sem->notice = variable_get_for_school("developmentals_notice", t("According to our records, you are required to complete the course(s) listed above. For some transfer students, your record may not be complete. If you have any questions, please ask your advisor.", $this->school_id));

    if (!$is_empty)
    {
      $this->list_semesters->add($sem);
    }

  }

  function add_semester_courses_added()
  {
    // The "Add a Course" box on screen is really just a
    // semester, with the number -88, with a single group,
    // also numbered -88.
    $semester_courses_added = new Semester(DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED);
    $semester_courses_added->title = t("Courses Added by Advisor");

    // Now, we want to add the Add a Course group...
    $g = new Group();
    $g->group_id = DegreePlan::GROUP_ID_FOR_COURSES_ADDED;
    // Since it would take a long time during page load, we will
    // leave this empty of courses for now.  It doesn't matter anyway,
    // as we will not be checking this group for course membership
    // anyway.  We only need to load it in the popup.
    $g->hours_required = 99999;  // Nearly infinite selections may be made.
    $g->assigned_to_semester_num = DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED;

    $semester_courses_added->list_groups->add($g);

    $this->list_semesters->add($semester_courses_added);

    // Also, add it to the list of groups OUTSIDE of semesters.
    $this->list_groups->add($g);

  }


  

  function find_group($group_id)
  {
    // Locate the group with group_id in the
    // list of groups, and return it.
    $this->list_groups->reset_counter();
    while($this->list_groups->has_more())
    {
      $group = $this->list_groups->get_next();
      if ($group->group_id == $group_id)
      {
        return $group;
      }

      if (!$group->list_groups->is_empty)
      {
        $group->list_groups->reset_counter();
        while($group->list_groups->has_more())
        {
          $branch = $group->list_groups->get_next();
          if ($branch->group_id == $group_id)
          {
            return $branch;
          }
        }
      }

    }

    return false;
  }



  function find_placeholder_group($group_id, $semester_num)
  {
    // Locate the group within the semesters that matches
    // this group_id and semesterNum.  The assumption here
    // is that no one semester will list the same
    // group twice.  In other words, Core Fine Arts
    // can only have 1 entry for Freshman Year.

    // Create a dummy semester with the correct semesterNum...
    $new_semester = new Semester($semester_num);
    // Create dummy group as well... don't use the constructor, just
    // set the group_id manually to same time. (no DB calls)
    $new_group = new Group();
    $new_group->group_id = $group_id;

    //print_pre($this->list_semesters->to_string());
    // Find the semester in the list of semesters with this same semesterNum...
    if (!$semester = $this->list_semesters->find_match($new_semester))
    {
      // The semester wasn't found!
      return false;
    }


    // Okay, now go through $semester and find the group_id...
    if (!$group = $semester->list_groups->find_match($new_group))
    {
      // It wasn't found in the top-level groups.  Look one deeper...
      if (!$semester->list_groups->is_empty)
      {
        $semester->list_groups->reset_counter();
        while($semester->list_groups->has_more())
        {
          $group = $semester->list_groups->get_next();
          if ($g = $group->list_groups->find_match($new_group))
          {
            //$g->assign_to_semester($semester_num);
            return $g;
          }
        }
      }
    } else {
      // Meaning, we found it!
      //$group->assign_to_semester($semester_num);
      return $group;
    }

    return false;

  }



  /**
   * If degree_id != 0, then we will remove any course from the finished list that is NOT in the degree plan.
   *   0 means "give me all of matches back"
   */
  function find_courses($course_id, $group_id = 0, $semester_num = 0, $degree_id = 0)
  {
    // This will locate a course within the degree plan, and return
    // back either that course object, or FALSE.
    $new_course = new Course($course_id);
    $new_semester = new Semester($semester_num);
    $rtn_course_list = new CourseList();
    
    // Okay, if the course is within a group, then
    // we can first use the find_group method.
    if ($group_id != "" && $group_id != 0 && $group_id != NULL)
    {
      if ($group = $this->find_group($group_id))
      {

        if (!($group->list_courses->is_empty))
        {
          if ($cL = $group->find_courses($new_course))
          {

            $rtn_course_list->add_list($cL);

          }
        }
        if (!($group->list_groups->is_empty))
        {
          // Look within each sub group for the course...
          $group->list_groups->reset_counter();
          while($group->list_groups->has_more())
          {
            $branch = $group->list_groups->get_next();
            if (!$branch->list_courses->is_empty)
            {
              if ($cL = $branch->find_courses($new_course))
              {
                $rtn_course_list->add_list($cL);
              }
            }
            // Here we can look for groups within groups...

          }
        }
      }

      return $rtn_course_list;

    } else if ($semester_num != -1) {
      // No group specified.  This course is on the
      // bare degree plan.  We were given a specific semester,
      // so try to find it there...
      if ($semester = $this->list_semesters->find_match($new_semester))
      {

        if ($cL = $semester->list_courses->find_all_matches($new_course))
        {
            
          if ($degree_id != 0) {
            // Trim $cL of any courses NOT in our supplied degree_id.
            $cL->remove_courses_not_in_degree($degree_id);
            if ($cL->get_size() == 0) return FALSE;  // we removed them all!
          }
          
          $rtn_course_list->add_list($cL); 
          return $rtn_course_list;
        }
      }

    } else if ($semester_num == -1)
    {
      // Meaning, we do not know which semester it goes in, so
      // attempt all semesters, and return with the first instance.
      $this->list_semesters->reset_counter();
      while($this->list_semesters->has_more())
      {
        $sem = $this->list_semesters->get_next();
        if ($cL = $sem->list_courses->find_all_matches($new_course))
        {
          $rtn_course_list->add_list($cL);
          return $rtn_course_list;
        }

      }
    }

    return FALSE;


  }


  function to_string()
  {
    // Output this degree plan object in a helpful manner.
    $rtn = "";

    $rtn .= "Degree Plan: $this->title ($this->major_code) \n";
    $rtn .= $this->list_semesters->to_string();
    $rtn .= "----------------------------------------- \n";
    $rtn .= "--  ALL GROUPS   \n";
    $rtn .= $this->list_groups->to_string();



    return $rtn;
  }

} // end class DegreePlan