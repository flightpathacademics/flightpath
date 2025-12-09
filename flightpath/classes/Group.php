<?php

class Group extends stdClass
{
  
  const GROUP_COURSE_INFINITE_REPEATS = 9999;
  const GROUP_COURSE_INFINITE_REPEATS_THRESHOLD = 50;
  
  
	public $title, $icon_filename, $group_id, $requirement_type, $min_grade, $group_name;
	public $hours_required, $min_hours_allowed, $hours_remaining, $hours_fulfilled, $hours_fulfilled_for_credit;
	public $hours_required_by_type, $req_by_degree_id;
	public $assigned_to_semester_num, $bool_placeholder, $data_entry_comment, $public_note;
	public $list_courses, $list_groups, $db, $count_of_matches, $bool_winning_branch;
	public $catalog_year, $school_id;
	public $priority, $definition;
	//////////////////
	///  From the database...
	public $db_unassign_group_id, $db_delete_flag, $db_catalog_repeat;

	///////////////
	///  Used with in-system logic....
	public $hours_assigned;
	public $bool_use_draft;


	/**
	* $title			"Free Electives","Core Fine Arts", etc.
	* $icon_filename		monalisa.gif, tower1.gif, etc.
	* $group_id		ID of the group in the db table.
	*
	* $type			Major, Supporting, Core, etc.
	* $min_grade		This is if the group itself has a min grade requirement.
	* 				Ex: B,C etc.
	* $list_courses	This is a CourseList of courses.  These are
	* 				the courses which are actually required by the group.
	* 				If individual courses have their own min grade requirements,
	* 				or what have you, that only refer to this group, then they
	* 				would be put in here.
	* $list_groups	This is a list of groups that belong within this group.
	* 				Used when you have branching.  Potentially can be quite
	* 				complicated, since each group in the list can also have
	*				subgroups.
	**/


	function __construct($group_id = "", DatabaseHandler $db = NULL, $semester_num = -1, $array_significant_courses = false, $bool_use_draft = false, $requirement_type = "")
	{
		$this->group_id = $group_id;
		$this->assigned_to_semester_num = $semester_num;
		$this->count_of_matches = 0;
		$this->hours_assigned = 0;
    $this->min_hours_allowed = 0;
    $this->school_id = 0;
		$this->list_courses = new CourseList();
		$this->list_groups = new GroupList();
		$this->bool_use_draft = $bool_use_draft;
		$this->hours_required_by_type = array();
		$this->requirement_type = $requirement_type;
		// Always override if the global variable is set.
		if (@$GLOBALS["fp_advising"]["bool_use_draft"] == true) {
			$this->bool_use_draft = true;
		}

		
		$this->db = $db;
		if ($db == NULL || !is_object($db) || !is_resource($db->pdo)) {		  
			$this->db = get_global_database_handler();			
		}


		if ($group_id != "")
		{		  
			$this->bool_placeholder = false;
			$this->load_group(true, $array_significant_courses);
      $this->set_requirement_type($this->requirement_type);
		}
    
	}



	function assign_to_semester($semester_num)
	{
		$this->assigned_to_semester_num = $semester_num;
		$temp_i = $this->list_courses->i;
		$this->list_courses->reset_counter();
		while($this->list_courses->has_more())
		{
			$c = $this->list_courses->get_next();
			$c->assigned_to_semester_num = $semester_num;
		}
		$this->list_courses->i = $temp_i;
	}


	function reset_list_counters()
	{
		// Resets the counters on all groups and course lists
		// in this group.
		$this->list_courses->reset_counter();
		$this->list_groups->reset_counter();
	}

	function assign_min_grade($min_grade)
	{
		// Assign every course in the group to have this particular min grade.
		
		$min_grade = strtoupper($min_grade);
		
		$this->min_grade = $min_grade;

		$this->list_courses->assign_min_grade($min_grade);
		$this->list_groups->assign_min_grade($min_grade);

	}


  /**
   * Returns an array of course_id's in this group, as well as any sub-group (branches).
   * // Key = course_id, val = TRUE.
   */
  function get_course_id_array($bool_exclude_assigned_courses = FALSE) {
    
    $rtn = array();
    
    $this->list_courses->reset_counter();
    while($this->list_courses->has_more()) {
      $c = $this->list_courses->get_next();
      
      if (!$bool_exclude_assigned_courses) {
        $rtn[$c->course_id] = TRUE;  
      }
      else {
        // Look to see if this course has already been assigned or not. If it has, then we
        // should skip it. If it has NOT, then it's OK to add.
        
        if ($c->course_list_fulfilled_by->is_empty == TRUE) {
          $rtn[$c->course_id] = TRUE;
        }
        else {
          //$c->load_descriptive_data();
          //fpm("Not doing $c->subject_id $c->course_num");
        }
      }
      
      
      
    }

    // Also get any sub-group's courses...
    $this->list_groups->reset_counter();
    while($this->list_groups->has_more()) {
      $g = $this->list_groups->get_next();
      
      // $rtn = array_merge($rtn, $g->get_course_id_array());      
      // Fix by Logan Buth: https://bytetask.com/node/2456
      $rtn = $rtn + $g->get_course_id_array();
      
    }

    return $rtn;
    
  } // get_course_id_array.


  
  function has_min_hours_allowed() {
    
    return ($this->min_hours_allowed > 0 && $this->min_hours_allowed != $this->hours_required);
    
  }


	function get_hours_remaining($semester_num = -1)
	{
		// returns how many hours are left for this group.

		return ($this->hours_required - $this->get_fulfilled_hours(true, true, false, $semester_num));
	}

  /**
   * Return TRUE or FALSE if we've fulfilled the min hour allowed value, if it's set.
   */
  function get_is_min_hours_allowed_fulfilled($semester_num = -1) {
    if (floatval($this->min_hours_allowed) == 0) return TRUE;
    
    $v = $this->get_fulfilled_hours(true, true, false, $semester_num);
    
    if ($v >= $this->min_hours_allowed) return TRUE;
    
    return FALSE;
    
  }

 
  /**
   * We expect the group_id to be our db_group_id + _ + degree_id, to ensure
   * that groups are unique to a degree.  Let's return just the database group_id portion (the number
   * which we can look up in the db groups table with.)
   */
  function get_db_group_id() {
    // We expect our group_id to actually be the db_group_id + _ + degree_id.
    $temp = explode("_", $this->group_id);
    $db_group_id = trim($temp[0]);
    
    return $db_group_id;    
  }


	function load_group($bool_load_significant_only = true, $array_significant_courses = false, $bool_reload_missing_only = false)
	{
		$this->load_descriptive_data();
		if ($this->db_delete_flag == 1)
		{
			return;
		}

		$bool_significant_courses_empty = true;
		if (is_array($array_significant_courses))
		{
			$bool_significant_courses_empty = false;
		}

		if ($bool_reload_missing_only == true)
		{
			// We are only going to load the *missing* courses from
			// this group.  So, begin by getting an array of what is
			// not missing.

			$array_group_requirement_ids = $this->list_courses->get_group_requirement_id_array();
			
		}

		$table_name = "group_requirements";
		if ($this->bool_use_draft) {$table_name = "draft_$table_name";}
		
		$res = db_query("SELECT * FROM `$table_name`
							WHERE group_id = ?	", $this->get_db_group_id());
		while ($cur = db_fetch_array($res))
		{

			$id = $cur["id"];      
			$course_id = intval($cur["course_id"]); 
      
      // The group requirements table doesn't have a school_id.
      //$this->school_id = intval($cur['school_id']);

			if ($cur["course_id"]*1 > 0)
			{
				if ($bool_load_significant_only == true && $bool_significant_courses_empty == false)
				{
					// If this course_id is NOT in the array of significant courses
					// (that the student took or has transfer credit or subs for)
					// then skip it.  Never add it to the group.					
					if (isset($array_significant_courses[$cur["course_id"]]) && $array_significant_courses[$cur["course_id"]] != true)
					{// course was not in there, so skip!
						continue;
					}


				}


				// A course is the next requirement.
				
				// Is this more than XX repeats?  If so, we consider it "infinite"
				if ($cur['course_repeats'] <= Group::GROUP_COURSE_INFINITE_REPEATS_THRESHOLD) {
				  // Less than XX repeats, so treat it like a normal course.
  				for ($t = 0; $t <= $cur["course_repeats"]; $t++)
  				{ // Add in the specified repeats for this group...
  					// This will usually only go through the loop once.
  
  					$use_id = $id . "_rep_$t";
  
  					if ($bool_reload_missing_only == true)
  					{
  						// Only load this course if it is missing from the group.
  						// Read the reload_missing_courses() method for an explanation
  						// of why we should want to do this.
  						// Basically, check all the courses in the current
  						// list_courses object for a db_group_requirement_id of $id.
  						// Only proceed if $id was NOT found.
  
  						if (@$array_group_requirement_ids[$use_id] == true)
  						{
  							continue;
  						}
  					}
            
  					$course_c = new Course();
  					$course_c->bool_use_draft = $this->bool_use_draft;
  					$course_c->course_id = $cur["course_id"];
  					$course_c->db_group_attributes = $cur['attributes'];
  					$course_c->db_group_requirement_id = $use_id;
  					$course_c->db = $this->db;
  					$course_c->catalog_year = $this->catalog_year;
  					$course_c->assigned_to_group_id = $this->group_id;
  					$course_c->assigned_to_semester_num = $this->assigned_to_semester_num;
  					
  
  					$course_c->specified_repeats = $cur["course_repeats"];
  					if ($cur["course_repeats"] > 0)
  					{
  						$course_c->bool_specified_repeat = true;
  					}
  
  					$course_c->min_grade = trim(strtoupper($cur["course_min_grade"]));
  					if ($course_c->min_grade == "")
  					{ // By default, all courses have a
  						// min grade requirement of D.
  						$course_c->min_grade = "D";
  					}
  
  
  					$this->list_courses->add($course_c);
  				} // for t <= cur['course_repeats']
				}
        else {
          // We are dealing with a course with "infinite" repeats.
          $use_id = $id . "_rep_999";

          if ($bool_reload_missing_only == true)
          {
            // Only load this course if it is missing from the group.
            // Read the reload_missing_courses() method for an explanation
            // of why we should want to do this.
            // Basically, check all the courses in the current
            // list_courses object for a db_group_requirement_id of $id.
            // Only proceed if $id was NOT found.
            if (@$array_group_requirement_ids[$use_id] == true)
            {
              continue;
            }
          }

          $course_c = new Course();
          $course_c->bool_use_draft = $this->bool_use_draft;
          $course_c->course_id = $cur["course_id"];
          $course_c->db_group_attributes = $cur['attributes'];
          $course_c->db_group_requirement_id = $use_id;
          $course_c->db = $this->db;
          $course_c->catalog_year = $this->catalog_year;
          $course_c->assigned_to_group_id = $this->group_id;
          $course_c->assigned_to_semester_num = $this->assigned_to_semester_num;
          

          $course_c->specified_repeats = Group::GROUP_COURSE_INFINITE_REPEATS;
          $course_c->bool_specified_repeat = true;
          
          $course_c->min_grade = trim(strtoupper($cur["course_min_grade"]));
          if ($course_c->min_grade == "")
          { // By default, all courses have a
            // min grade requirement of D.
            $course_c->min_grade = "D";
          }


          $this->list_courses->add($course_c);
                    
        }



			} // if cur['course_id']
			
			
			if ($cur["child_group_id"]*1 > 0)
			{
			  
        $temp_add_as_new_group = FALSE;
			  
				// Another group is the next requirement (its a branch)
				if ($bool_reload_missing_only == true)
				{ // Since we are reloading courses, this subgroup is (probably) already
					// part of this group, so do not re-create it, just find it
					// and reload it's missing courses.
					$temp_g = new Group();
          $temp_g->school_id = $this->school_id;
					$temp_g->bool_use_draft = $this->bool_use_draft;
					$temp_g->group_id = $cur["child_group_id"] . '_' . $this->req_by_degree_id;
					$temp_g->requirement_type = $this->requirement_type;
					if ($group_g = $this->list_groups->find_match($temp_g)) {
						$group_g->reload_missing_courses();						
					} 
					else {
					  // We didn't find the child group, possibly because this is the first time we are loading it.  If that is
					  // the case, then we should just add it fresh.   
				    $temp_add_as_new_group = TRUE;
					}
				} 
				else {
          $temp_add_as_new_group = TRUE;
        }
        
        
        if ($temp_add_as_new_group) {        
  				// Add this as a brand-new sub group, so create it
  				// and add it to this group.
  				$group_g = new Group($cur["child_group_id"] . "_" . $this->req_by_degree_id,null,$this->assigned_to_semester_num, $array_significant_courses, $this->bool_use_draft);
  				$group_g->requirement_type = $this->requirement_type;
  				$this->list_groups->add($group_g);
        }
        
        
			} // if child_group_id > 0
			
		} // while cur = db_fetch_array(res)

						
    // When we load this group, let's also check for any hooks.
    // Since this class might be used outside of FP, only do this if we know
    // that the bootstrap.inc file has been executed.
    if ($GLOBALS["fp_bootstrap_loaded"] == TRUE) {      
      invoke_hook("group_load", array(&$this));
    }         
				
		
    
		
	} // load_group


	function reload_missing_courses()
	{
		// This function will go through the group and reload
		// any courses which are missing from the group object,
		// but are spelled out in the database table.
		// This is used after we have loaded a group from
		// cache (because the cached group only contains
		// courses which the student has taken).

		$this->load_group(FALSE, FALSE, TRUE);

	}

	function replace_missing_course($course_id, $db_group_requirement_id="")
	{
		// replace course_id in this group, if it is missing.

		$table_name = "group_requirements";
		if ($this->bool_use_draft) {$table_name = "draft_$table_name";}

		// Look for all instances of this course in the group's base list...
		$res = db_query("SELECT `id`, course_repeats FROM `$table_name`
									WHERE group_id = ?
									AND course_id = ? ", $this->get_db_group_id(), $course_id);
		while ($cur = db_fetch_array($res))
		{
			$id = $cur["id"];

			for ($t = 0; $t <= $cur["course_repeats"]; $t++)
			{
				$course = new Course($course_id,false,$this->db, false, "", $this->bool_use_draft);
				$use_id = $id . "_rep_$t";
				// Make sure the group does not already have this requirementID...
				if ($this->list_courses->contains_group_requirement_id($use_id))
				{
					continue;
				}

				$course->assigned_to_group_id = $this->group_id;
        $course->requirement_type = $this->requirement_type;
				$course->db_group_requirement_id = $use_id;
        $course->db_group_attributes = $cur['attributes'];
				$course->specified_repeats = $cur["course_repeats"];
				if ($cur["course_repeats"] > 0)
				{
					$course->bool_specified_repeat = true;
				}

				$this->list_courses->add($course);
			}
		}

		// Now, go through all of the group's branches and
		// do the same thing.
		$this->list_groups->reset_counter();
		while($this->list_groups->has_more())
		{
			$g = $this->list_groups->get_next();
			$g->replace_missing_course($course_id);
		}




	}

  /**
   * Set all the courses and branches in this group to the specified degree_id.
   */
  function set_req_by_degree_id($degree_id = 0) {
    $this->req_by_degree_id = $degree_id;
    $this->list_courses->reset_counter();
    $this->list_courses->set_req_by_degree_id($degree_id);
    
    // go through sub-groups and do the same...
    $this->list_groups->reset_counter();
    while($this->list_groups->has_more())
    {
      $g = $this->list_groups->get_next();
      $g->set_req_by_degree_id($degree_id);
    }
    
  }


  /**
   * Sets the requirement type for the group and all its courses (including sub-groups)
   */
  function set_requirement_type($requirement_type = "") {
    $this->requirement_type = $requirement_type;
    $this->list_courses->reset_counter();
    $this->list_courses->set_requirement_type($requirement_type);
    
    // go through sub-groups and do the same...
    $this->list_groups->reset_counter();
    while($this->list_groups->has_more())
    {
      $g = $this->list_groups->get_next();
      $g->set_requirement_type($requirement_type);
    }    
  }


	function load_descriptive_data()
	{	  
    $cur = null;
    static $group_descriptive_data_cache = array();
    if (isset($group_descriptive_data_cache[$this->get_db_group_id()])) {
      $cur = $group_descriptive_data_cache[$this->get_db_group_id()];
    } 
    else {
      $table_name = "groups";
      if ($this->bool_use_draft) {$table_name = "draft_$table_name";}
      // Load information about the group's title, icon, etc.
      $res = db_query("SELECT * 
                       FROM `$table_name`
                       WHERE group_id = ? ", $this->get_db_group_id());
      $cur = db_fetch_array($res);
      $group_descriptive_data_cache[$this->get_db_group_id()] = $cur;
    }    
       
    if ($cur) {

  		$this->title = trim($cur["title"]);
  		$this->icon_filename = trim($cur["icon_filename"]);
      if ($this->icon_filename == "") $this->icon_filename = "major.gif"; // set a default!
  		$this->group_name = trim($cur["group_name"]);
  		$this->data_entry_comment = trim((string) $cur["data_entry_comment"]);
  		$this->priority = trim($cur["priority"]);
  		$this->definition = trim((string) $cur["definition"]);
  		$this->db_delete_flag = trim($cur["delete_flag"]);
  		$this->db_catalog_repeat = trim($cur["catalog_repeat"]);
  		$this->catalog_year = trim($cur["catalog_year"]);
  		$this->public_note = trim((string) $cur["public_note"]);
  		$this->school_id = intval($cur["school_id"]);
      
    }

		if ($this->group_id == DegreePlan::GROUP_ID_FOR_COURSES_ADDED)
		{
			$this->title = "Add an Additional Course";
		}


	}


	function get_fulfilled_hours($bool_check_subgroups = true, $bool_count_advised = true, $bool_require_has_been_displayed = false, $only_count_semester_num = -1, $bool_ignore_enrolled = false, $bool_qpts_grades_only = FALSE, $requirement_type = "", $bool_exclude_all_transfer_credits = FALSE)
	{
		// Returns how many hours have been used by the
		// course fulfillments for this group...
		$count = 0;
		// if onlyCountSemesterNum != -1, then we will only count courses
		// who have their "assigned_to_semester_num" = $only_count_semester_num.		
		
		
		
		
		//print_pre($this->to_string());
		$this->list_courses->reset_counter();
		while($this->list_courses->has_more())
		{
			$c = $this->list_courses->get_next();
			if ($only_count_semester_num != -1 && $c->assigned_to_semester_num != $only_count_semester_num)
			{
				// Only accept courses assigned to a particular semester.
				continue;
			}

			
			if (is_object($c->course_list_fulfilled_by) && !($c->course_list_fulfilled_by->is_empty))
			{
				if ($bool_ignore_enrolled == true)
				{
					// Only allow it if it has been completed.
					if ($c->course_list_fulfilled_by->get_first()->is_completed() == false)
					{
						continue;
					}
				}



				if (!$bool_require_has_been_displayed)
				{ // The course does not have to have been displayed on the page yet.					
					$count = $count + $c->course_list_fulfilled_by->count_credit_hours($requirement_type, false, false, $bool_qpts_grades_only, $bool_exclude_all_transfer_credits);
				} else {
					if ($c->course_list_fulfilled_by->get_first()->get_has_been_displayed() == true)
					{
						
						$h = $c->course_list_fulfilled_by->count_credit_hours($requirement_type, false, false, $bool_qpts_grades_only, $bool_exclude_all_transfer_credits);
						$count = $count + $h;
						
					}
				}
			} else if ($c->bool_advised_to_take && $bool_count_advised == true)
			{
        $h = $c->get_hours(); 
				$count = $count + $h;
			}

		}

		if ($bool_check_subgroups == true)
		{
			// If there are any subgroups for this group, then run
			// this function for each group as well.
			$this->list_groups->reset_counter();
			while($this->list_groups->has_more())
			{

				$g = $this->list_groups->get_next();
				$gc = $g->get_fulfilled_hours(true, $bool_count_advised, $bool_require_has_been_displayed, $only_count_semester_num, $bool_ignore_enrolled, $bool_qpts_grades_only, $requirement_type, $bool_exclude_all_transfer_credits);
				$count = $count + $gc;
			}
		}

		
		//if ($this->group_id == 533404) {
		  //fpm($this);
		  //fpm("returning $count for group $this->group_id, $this->title");
		//}
		
		
		return $count;

	}

	
  
  
  
  

	/**
	 * Returns the quality points earned for all of the courses in this group
	 *
	 */
	function get_fulfilled_quality_points($bool_check_subgroups = true, $only_count_semester_num = -1, $bool_ignore_enrolled = false, $bool_require_has_been_displayed = false, $requirement_type = "", $bool_exclude_all_transfer_credits = FALSE)
	{
		$points = 0;
		// if onlyCountSemesterNum != -1, then we will only count courses
		// who have their "assigned_to_semester_num" = $only_count_semester_num.

		$this->list_courses->reset_counter();
		while($this->list_courses->has_more())
		{
			$c = $this->list_courses->get_next();
			if ($only_count_semester_num != -1 && $c->assigned_to_semester_num != $only_count_semester_num)
			{
				// Only accept courses assigned to a particular semester.
				continue;
			}

			
			if (is_object($c->course_list_fulfilled_by) && !($c->course_list_fulfilled_by->is_empty))
			{
				if ($bool_ignore_enrolled == true)
				{
					// Only allow it if it has been completed.
					if ($c->course_list_fulfilled_by->get_first()->is_completed() == false)
					{
						continue;
					}
				}

				$p = 0;
				
				// Are we requiring that the course has been displayed?
        if (!$bool_require_has_been_displayed || ($bool_require_has_been_displayed && $c->course_list_fulfilled_by->get_first()->get_has_been_displayed() == TRUE))
				{
					$p = $c->course_list_fulfilled_by->count_credit_quality_points($requirement_type, TRUE, TRUE, $bool_exclude_all_transfer_credits);
				}				
  		  
  		  $points = $points + $p;
  				    					
			}
			
		}

		if ($bool_check_subgroups == TRUE)
		{
			// If there are any subgroups for this group, then run
			// this function for each group as well.
			$this->list_groups->reset_counter();
			while($this->list_groups->has_more())
			{

				$g = $this->list_groups->get_next();
				$gp = $g->get_fulfilled_quality_points(TRUE, $only_count_semester_num, $bool_ignore_enrolled, $bool_require_has_been_displayed, $requirement_type, $bool_exclude_all_transfer_credits);
				$points = $points + $gp;
			}
		}
		

		return $points;

	}	
	
	
	

	function equals(Group $group, $bool_ignore_degree_id = FALSE)
	{
	  
    $our_group_id = $this->group_id;
    $test_group_id = $group->group_id;
    
    if ($bool_ignore_degree_id) {
      $our_group_id = $this->get_db_group_id();
      $test_group_id = $group->get_db_group_id();      
    }
    
		if ($our_group_id == $test_group_id)
		{
			return true;
		}

		return false;

	}



	function to_string()
	{
		$rtn = "";

		$rtn .= "    Group: $this->group_id | $this->title $this->catalog_year ($this->hours_required hrs req.)\n    {\n";
		if (!$this->list_courses->is_empty)
		{
			$rtn .= $this->list_courses->to_string();
		}

		if (!$this->list_groups->is_empty)
		{
			$rtn .= $this->list_groups->to_string();
		}

		$rtn .= "    } \n";

		return $rtn;
	}


	function find_courses(Course $course)
	{
		// Return a CourseList of all the Course objects
		// which are in this group that match
		$rtn_course_list = new CourseList();
		
		if ($obj_list = $this->list_courses->find_all_matches($course))
		{
			$obj_list->reset_counter();
			while($obj_list->has_more())
			{
				$c = $obj_list->get_next();
				$c->required_on_branch_id = $this->group_id;
			}
			$rtn_course_list->add_list($obj_list);
			return $rtn_course_list;
		}

		return false;

	}



} // end class Group
