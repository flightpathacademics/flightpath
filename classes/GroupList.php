<?php


require_once("ObjList.php");

class GroupList extends ObjList
{
	/*
	This class inherits mosts of its classes from ObjList
	in a similar fashion as CourseList.
	*/

	
	function reset_list_counters()
	{
		$this->reset_counter();
		// Also, go through all groups in the list and call
		// their "reset_list_counters" method.
		for ($t = 0; $t < $this->count; $t++)
		{
			$group = $this->array_list[$t];
			$group->reset_list_counters();
		}		
	}
	
	
	function contains_group_requirement_id($group_requirement_id)
	{
		// Returns true if any of the lists of courses in these groups
		// contain the group requirement ID.
		for ($t = 0; $t < count($this->array_list); $t++)
		{
			$group = $this->array_list[$t];
			if ($group->list_courses->contains_group_requirement_id($group_requirement_id))
			{
				return true;
			}
		}		
		
		return false;
		
	}
	
   /**
   * Set all the courses and branches in this group to the specified degree_id.
   */
  function set_req_by_degree_id($degree_id = 0) {
      
    // Loop through all the groups in this list...
    $this->reset_counter();
    while ($this->has_more()) {
      $g = $this->get_next();
      // Set req_by_degree_id on this group...
      $g->set_req_by_degree_id($degree_id);
    }  
    
  }
  
  
  

  /**
   * Adds the supplied GroupList to the bottom of $this's list.
   *
   */
  function add_list(GroupList $glist)
  {
    for ($t = 0; $t < count($glist->array_list); $t++)
    {
      $this->add($glist->array_list[$t]);
    }

  }  
  
  /**
   * Return back the matching group object, if it's degree_id matches.  Otherwise, FALSE
   */
  function find_match_with_degree_id($group, $degree_id) {
    for ($t = 0; $t < $this->count; $t++)
    {
      $g = $this->array_list[$t];
      if ($g->group_id == $group->group_id && $g->req_by_degree_id == $degree_id) {
        return $g;
      }
    }
        
    return FALSE;
  }
  
  
  /**
   * Return an array of course_id's for all the groups in this list.
   * will look like:  $rtn[group_id][course_id] = TRUE
   */
  function get_group_course_id_array() {
    $rtn = array();
    $this->reset_counter();
    
    for ($t = 0; $t < $this->count; $t++)
    {
      $g = $this->array_list[$t];
      
      $rtn[$g->group_id] = $g->get_course_id_array();
    }

       
    return $rtn;
      
  }
  
  
  /**
   * Calls the function "reload_missing_courses" on all groups in this list.
   */
  function reload_missing_courses() {
    for ($t = 0; $t < $this->count; $t++)
    {
      $group = $this->array_list[$t];
      $group->reload_missing_courses();
    }    
  }
  
  
  
  /**
   * Return a GroupList which is a clone of this list.
   */
  function get_clone($bool_return_new_groups = FALSE, $bool_load_groups = TRUE, $bool_reload_missing_courses = FALSE)
  {
    $rtn_list = new GroupList();
    
    for ($t = 0; $t < $this->count; $t++)
    {
      $group = $this->array_list[$t];
      
            
      if ($bool_return_new_groups == TRUE)
      {
        $new_group = new Group();
        $new_group->group_id = $group->group_id;
        
        if ($bool_load_groups) {
          $new_group->load_group();  // Make sure the group has all its courses loaded in.
        }
        
        $new_group->set_requirement_type($group->requirement_type);
        $new_group->set_req_by_degree_id($group->req_by_degree_id);                
        
        $rtn_list->add($new_group);
      } 
      else {
        
        if ($bool_load_groups) {
          $group->load_group();  // Make sure the group has all its courses loaded in.
        }
        
        if ($bool_reload_missing_courses) {          
          $group->reload_missing_courses();
        }
                
        
        $group->set_requirement_type($group->requirement_type);
        $group->set_req_by_degree_id($group->req_by_degree_id);
                       
        $rtn_list->add($group);
      } 
      
    } 
    
    return $rtn_list;
      
  }  
  
  
  
	
	function get_advised_courses_list()
	{
		// Return a courseList object of courses in THIS
		// group which have boolAdvisedToTake == true.
		$rtn_list = new CourseList();
		for ($t = 0; $t < count($this->array_list); $t++)
		{
			$group = $this->array_list[$t];
			$rtn_list->add_list($group->list_courses->get_advised_courses_list());
			
			$group->list_groups->reset_counter();
			while($group->list_groups->has_more())
			{
				$gg = $group->list_groups->get_next();
				$rtn_list->add_list($gg->list_courses->get_advised_courses_list());
			}
		}		
		
		$rtn_list->remove_duplicates();
		
		return $rtn_list;
		
	}
	
	
	function assign_min_grade($min_grade)
	{
		// Assign a min grade to every group in this grouplist.
		$count = count($this->array_list);
		for ($t = 0; $t < $count; $t++)
		{
			$group = $this->array_list[$t];
			$group->assign_min_grade($min_grade);
		}					
		
		
	}
	
  
  
  
  /**
   * Sort this list of groups by the advising weights of the degrees they belong to.
   * 
   * This solution (which allows for negative numbers) was provided by user oantby (Logan Bluth) from Morningside.
   * The original function is z__sort_degree_advising_weight()
   */
  function sort_degree_advising_weight() {
  
    $tarray = array();
    $per_major_code = array();
    
    for ($t = 0; $t < count($this->array_list); $t++)
    {
      $g = $this->array_list[$t];
      
      // Get the degree_id for this group
      $degree_id = $g->req_by_degree_id;
      
      $major_code = fp_get_degree_major_code($degree_id);
      $advising_weight = fp_get_degree_advising_weight($degree_id);
      if (!$advising_weight) $advising_weight = 0;
      
      $tarray[$major_code] = $advising_weight;
      $per_major_code[$advising_weight][$major_code][] = &$this->array_list[$t];//use a reference so we don't need extra memory/cpu overhead.
    }
    
    // Now, sort the array to get everything in the correct order
    // Use asort to preserve keys (major codes)
    asort($tarray,SORT_NUMERIC);
    
        
    // Now, convert the array back into a list of groups.
    $new_list = new GroupList();
    foreach ($tarray as $major_code => $weight) {

      // As a secondary measure, we also wanted to sort equal weighted majors alphabetically by major_code.
      ksort($per_major_code[$weight]);    
      
      foreach ($per_major_code[$weight][$major_code] as &$group) {
        $new_list->add($group);
      }
    }
    
    // Okay, now $new_list should contain the correct values.
    // We will transfer over the reference.
    $this->array_list = $new_list->array_list;
  
    
        
  } // sort_degree_advising_weight
  
  
  
  
  
  
  
  
  
  
  function z__sort_degree_advising_weight() {
    
    $tarray = array();
    
    for ($t = 0; $t < count($this->array_list); $t++)
    {
      $g = $this->array_list[$t];
      // Get the degree_id for this group
      $degree_id = $g->req_by_degree_id;
      
      $major_code = fp_get_degree_major_code($degree_id);
      $advising_weight = fp_get_degree_advising_weight($degree_id);
      if (!$advising_weight) $advising_weight = 0;
      
      // Make the number a string, uniformly long    
      $advising_weight = str_pad($advising_weight, 4, "0", STR_PAD_LEFT);
      
      
      //$new_html[$degree_advising_weight . "__" . $degree_title][$req_by_degree_id] = $content;
      $tarray[] = $advising_weight . "___" . $major_code . " ~~ " . $t;
    }
    
    // Now, sort the array to get everything in the correct order.   
     
    sort($tarray);
    
    
    // Now, convert the array back into a list of groups.
    $new_list = new GroupList();
    for($t = 0; $t < count($tarray); $t++)
    {
      $temp = explode(" ~~ ",$tarray[$t]);
      $i = $temp[1];

      $new_list->add($this->array_list[$i]);
    }

    // Okay, now $new_list should contain the correct values.
    // We will transfer over the reference.
    $this->array_list = $new_list->array_list;

    
        
  } // sort_degree_advising_weight
  
  
  
  
  
  /**
   * Sort this list of groups by their priority number.  Higher priorities appear at the top of the list.
   * 
   * This will allow negative numbers (thanks to user oantby (Logan Bluth) from Morningside.
   * The original function is z__sort_priority(), which is left in the code in case it is needed.
   */
  function sort_priority() {
    /*
      Sort this list of groups by their priority number.
      Higher priorities should appear at the
      top of the list.
    */
    $tarray = array();
    
    
    for ($t = 0; $t < count($this->array_list); $t++)
    {
      $g = $this->array_list[$t];
      $g->load_descriptive_data();
      $pri = "" . intval($g->priority) . "";
      $str = "$pri ~~ $t";
  
      array_push($tarray,$str);
    }
    
    // We use SORT_NUMERIC so that negative numbers may be used.
    rsort($tarray,SORT_NUMERIC);
    
    
    // Now, convert the array back into a list of groups.
    $new_list = new GroupList();
    for($t = 0; $t < count($tarray); $t++)
    {
      $temp = explode(" ~~ ",$tarray[$t]);
      $i = $temp[1];
  
      $new_list->add($this->array_list[$i]);
    }
  
    // Okay, now $new_list should contain the correct values.
    // We will transfer over the reference.
    $this->array_list = $new_list->array_list;
    
  
  } 
  
  
	function z__sort_priority()
	{
		/*
			Sort this list of groups by their priority number.
			Higher priorities should appear at the
			top of the list.
		*/
		$tarray = array();
		// Since I need the indexes, I will have to go through the array
		// myself...
		for ($t = 0; $t < count($this->array_list); $t++)
		{
			$g = $this->array_list[$t];
			$g->load_descriptive_data();
			$pri = "" . ($g->priority*1) . "";
			if (strlen($pri) == 1)
			{
				$pri = "0" . $pri; // padd with 0 on the front.
				// This fixes a sorting problem, because priorities
				// were being evaluated as text, not nums, so "5" seemed
				// larger than "15"  (because it was comparing the 5 to the 1).
			}
			$str = "$pri ~~ $t";

			array_push($tarray,$str);
		}


		rsort($tarray);

		// Now, convert the array back into a list of groups.
		$new_list = new GroupList();
		for($t = 0; $t < count($tarray); $t++)
		{
			$temp = explode(" ~~ ",$tarray[$t]);
			$i = $temp[1];

			$new_list->add($this->array_list[$i]);
		}

		// Okay, now $new_list should contain the correct values.
		// We will transfer over the reference.
		$this->array_list = $new_list->array_list;
		
		
	}
	
  
  
  
  /**
   * Sorts best-grade-first, as defined by the setting "grade_order", which is a CSV of
   * grades, best-first.  Ex:  A, B, C, D, F
   * 
   * We will use the student's best grade for a course, rather
   * than the actual course's grade.  Generally, this can be left as set to null.   This is only for when we are
   * trying to organize a list of courses into the grade order, based on what a student has taken.  For example, if we want
   * to order a Group's list of courses based on what the student has taken and the grades they made.
   * 
   * We will do this for each group in this group list, and the group list with the best grades overall will be sorted
   * to the top, the group with the worst grades will sort to the bottom.
   *
   */
  function sort_best_grade_first_by_student_grades(Student $student) {

    $school_id = $student->school_id;
    
    $temp = csv_to_array(variable_get_for_school("grade_order", "AMID,BMID,CMID,DMID,FMID,A,B,C,D,F,W,I", $school_id));    
    // We will use array_flip to get back an assoc array where the grades are the keys and the indexes are the values.
    $temp = array_flip($temp);
    // Go through the grades and convert the integers to strings, padd with zeros so that everything is at least 3 digits.
    $grades = array();
    foreach ($temp as $grade => $val) {
      $grades[$grade] = str_pad((string)$val, 3, "0", STR_PAD_LEFT);
    }
    
    // We now have our grades array just how we want it.  Best grade has lowest value.  Worst grade has highest value.
        
    $unknown_grade_value = "999";  // sort to the very end, in other words.   

    
    $tarray = array();
    for ($t = 0; $t < $this->count; $t++) {
      $g = $this->array_list[$t];
      
      $student_grade_score = $g->list_courses->sort_best_grade_first($student);
      // Now, we want to record their "grade score", which we will use for sorting.
      
      // Make sure all the values are uniformly 6 digits long 
      $student_grade_score = str_pad((string)$student_grade_score, 6, "0", STR_PAD_LEFT);
      
      $tarray[] = "$student_grade_score ~~ $t";
            
    }
    
    // Sort by those grade scores
    sort($tarray);    
    
    // Okay, now go back through tarray and re-construct a new GroupList
    $new_list = new GroupList();
    for($t = 0; $t < count($tarray); $t++)
    {
      $temp = explode(" ~~ ",$tarray[$t]);
      $i = $temp[1];
      $new_list->add($this->array_list[$i]);
    }

    // Okay, now $new_list should contain the correct values.
    // We will transfer over the reference.
    $this->array_list = $new_list->array_list;        
    
    // And we are done!
    
  } // function
  
  
  
  
  
  
  
  
  
  
  
	function sort_alphabetical_order($bool_reverse_order = false)
	{

		$tarray = array();
		// Since I need the indexes, I will have to go through the array
		// myself...
		for ($t = 0; $t < count($this->array_list); $t++)
		{
			$g = $this->array_list[$t];
			$g->load_descriptive_data();
			$str = "$g->title ~~ $t";

			array_push($tarray,$str);
		}

		if ($bool_reverse_order == true)
		{
			rsort($tarray);
		} else {
			sort($tarray);
		}

		// Now, convert the array back into a list of groups.
		$new_list = new GroupList();
		for($t = 0; $t < count($tarray); $t++)
		{
			$temp = explode(" ~~ ",$tarray[$t]);
			$i = $temp[1];

			$new_list->add($this->array_list[$i]);
		}

		// Okay, now $new_list should contain the correct values.
		// We will transfer over the reference.
		$this->array_list = $new_list->array_list;
		
				
	}



}

