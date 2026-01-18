<?php


/**
 * This class is the View by Type view for FlightPath.  As such, it
 * inherits most of it's classes from __advising_screen.
 *
 *	The biggest difference with View by Type from the default
 *	View by Year is that we don't care about the original semesters
 *	that were spelled out in the degree plan.  We need to re-organize them
 *	into new semesters for Major, Core, Supporting, and Electives.  So,
 *	most of the methods here will be about doing that.
 *
 */
class AdvisingScreenTypeView extends AdvisingScreen
{


  /**
   * In __advising_screen, this method simply displays the degree plan's
   * semesters to the screen.  But here, we need to go through the 
   * type categories: ex: Core, Major, Supporting, and Electives,
   * and only display courses and groups from each semester fitting
   * that type.
   *
   */
	function build_semester_list()
	{

    

		$list_semesters = $this->degree_plan->list_semesters;
		// Go through each semester and add it to the screen...
		$list_semesters->reset_counter();

		
		// We want to go through our requirement types, and create a box for each one, if available.
		$types = fp_get_requirement_types($this->student->school_id);
		foreach ($types as $code => $desc) {
		  $temp = $this->display_semester_list($list_semesters, $code, $desc, TRUE);
		  if ($temp) {
		    $this->add_to_screen($temp, "SEMESTER_TYPE_" . $code);
		  }
		}
		
		
		$temp_d_s = new Semester(DegreePlan::SEMESTER_NUM_FOR_DEVELOPMENTALS); // developmental requirements.
		if ($dev_sem = $list_semesters->find_match($temp_d_s))
		{
			$this->add_to_screen($this->display_semester($dev_sem), "SEMESTER_" . DegreePlan::SEMESTER_NUM_FOR_DEVELOPMENTALS);
		}
		    			
		
	}


	/**
	 * Does the testType match the reqType?  This function is used
	 * to make sure that courses or groups with a certain requirement_type
	 * are placed in the correct semester blocks on screen.
	 *
	 * @param string $test_type
	 * @param string $req_type
	 * @return bool
	 */
	function match_requirement_type($test_type, $req_type)
	{
		// Does the testType match the reqType?
		
		if ($test_type == $req_type)
		{
			return true;
		}
		
		// Does it match if there's a u in front?
		if ($test_type == ("u" . $req_type))
		{  // university captone type.
			return true;
		}

  	if ($req_type == "e")
		{
		  // type "elective."  We will make sure the test_type isn't in
			// one of our defined types already.
			
			// Also, make sure it doesn't begin with a 'u'.  Ex:  um, for University Capstone + m.  That would be undefined as well.
			$no_u_test_type = ltrim($test_type, 'u');
      
			$types = fp_get_requirement_types($this->student->school_id);
			
			if (!isset($types[$test_type]) && !isset($types[$no_u_test_type])) {
			  // Yes-- the user is using a code NOT defined, so let's just call it
			  // an "elective" type.
			  
			  return TRUE;
			}
			
			
		}
		
		return false;
		
	}
	
  
  
  /**
   * Display contents of a semester list as a single semester,
   * only displaying courses matching the requirement_type.
   * If the requirement_type is "e", then we will also look for anything
   * not containing a defined requirement_type.
   *
   * @param SemesterList $list_semesters
   * @param string $requirement_type
   * @param string $title
   * @param bool $bool_display_hour_count
   * @return string
   */
  function display_semester_list($list_semesters, $requirement_type, $title, $bool_display_hour_count = false)
  {
    
    // TODO: convert to using render array.
    

    // Display the contents of a semester object
    // on the screen (in HTML)
    $pC = "";
    $pC .= $this->draw_semester_box_top($title);

    $is_empty = TRUE;
    
    $degree_sort_policy = variable_get_for_school("degree_requirement_sort_policy", "alpha", $this->student->school_id);
    
    $count_hours_completed = 0;
    $list_semesters->reset_counter();
    while($list_semesters->has_more())
    {
      $semester = $list_semesters->get_next();
      if ($semester->semester_num == DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED)
      { // These are the "added by advisor" courses.  Skip them.
        continue;
      }

      $last_req_by_degree_id = -1;
            
      // First, display the list of bare courses.
      if ($degree_sort_policy == 'database') {
        $semester->list_courses->sort_degree_requirement_id();
      }
      else {
        // By default, sort alphabetical      
        $semester->list_courses->sort_alphabetical_order();  // sort, including the degree title we're sorting for.
      }
      $semester->list_courses->reset_counter();
      $sem_is_empty = true;
      $html = array();
      $sem_rnd = rand(0,99999);
      $pC .= "<tr><td colspan='4' class='tenpt'>
          <span class='advise-type-view-sem-title'><!--SEMTITLE$sem_rnd--></span></td></tr>";
      while($semester->list_courses->has_more())
      {
        $course = $semester->list_courses->get_next();
        // Make sure the requirement type matches!
        if (!$this->match_requirement_type($course->requirement_type, $requirement_type))
        {
          continue;
        }
    
        $is_empty = FALSE;
      
        if (!isset($html[$course->req_by_degree_id])) {
          $html[$course->req_by_degree_id] = "";
        }
        
        // Is this course being fulfilled by anything?
        //if (is_object($course->courseFulfilledBy))
        if (!($course->course_list_fulfilled_by->is_empty))
        { // this requirement is being fulfilled by something the student took...
                     
          $c = $course->course_list_fulfilled_by->get_first();
          $c->req_by_degree_id = $course->req_by_degree_id;   // make sure we assign it to the current degree_id.
          
          $html[$course->req_by_degree_id] .= $this->draw_course_row($c);
          
          $c->set_has_been_displayed($course->req_by_degree_id);
          
          if ($c->display_status == "completed")
          { // We only want to count completed hours, no midterm or enrolled courses.            
            $h = $c->get_hours_awarded();
            if ($c->bool_ghost_hour == TRUE) {
             $h = 0;
            }
            $count_hours_completed += $h;           
          }
        } else {
          // This requirement is not being fulfilled...
          $html[$course->req_by_degree_id] .= $this->draw_course_row($course);
        }
        
        
        
                
        $sem_is_empty = false;
      } //while list_courses


      // Now, draw all the groups.
      $semester->list_groups->sort_alphabetical_order();
      $semester->list_groups->reset_counter();
      while($semester->list_groups->has_more())
      {
        
        $group = $semester->list_groups->get_next();
     
        if (!$this->match_requirement_type($group->requirement_type, $requirement_type))
        {
          continue;
        }

        if (!isset($html[$group->req_by_degree_id])) {
          $html[$group->req_by_degree_id] = "";
        }




        $html[$group->req_by_degree_id] .= "<tr><td colspan='8'>";
        $html[$group->req_by_degree_id] .= $this->display_group($group);
        $count_hours_completed += $group->hours_fulfilled_for_credit;
        $html[$group->req_by_degree_id] .= "</td></tr>";
        $sem_is_empty = false;
        $is_empty = FALSE;

      } // while list_groups
      

      
      if ($sem_is_empty == false)
      {
        // There WAS something in this semester, put in the title.
        
        //debugCT("replacing $sem_rnd with $semester->title");
        $pC = str_replace("<!--SEMTITLE$sem_rnd-->",$semester->title,$pC);
      }

      // Okay, let's plan to put it all on the screen for this semester....      

      // Sort by degree's advising weight
      $new_html = array();
      foreach($html as $req_by_degree_id => $content) {
        
        $dtitle = @$GLOBALS["fp_temp_degree_titles"][$req_by_degree_id];
        $dweight = intval(@$GLOBALS["fp_temp_degree_advising_weights"][$req_by_degree_id]);
        
        if ($dtitle == "") {
          $t_degree_plan = new DegreePlan();
          $t_degree_plan->degree_id = $req_by_degree_id;                  
          $dtitle = $t_degree_plan->get_title2(TRUE, TRUE);
          $dweight = $t_degree_plan->db_advising_weight;
          $dtype = $t_degree_plan->degree_type;
          $dclass = $t_degree_plan->degree_class;
          $dlevel = $t_degree_plan->degree_level;
        
          $GLOBALS["fp_temp_degree_types"][$req_by_degree_id] = $dtype; //save for next time.
          $GLOBALS["fp_temp_degree_classes"][$req_by_degree_id] = $dclass; //save for next time.
          $GLOBALS["fp_temp_degree_levels"][$req_by_degree_id] = $dlevel; //save for next time.
                    
          $GLOBALS["fp_temp_degree_titles"][$req_by_degree_id] = $dtitle . " "; //save for next time.
          $GLOBALS["fp_temp_degree_advising_weights"][$req_by_degree_id] = $dweight . " "; //save for next time.
        }
        
        $degree_title = fp_get_machine_readable($dtitle);  // make it machine readable.  No funny characters.
        $degree_advising_weight = str_pad($dweight, 4, "0", STR_PAD_LEFT);
        
        
        $new_html[$degree_advising_weight . "__" . $degree_title][$req_by_degree_id] = $content;
        
      }
      
      // Sort by the first index, the advising weight.
      ksort($new_html);
      
      
      
      
      //////////////////////////
      // Okay, now let's go through our HTML array and add to the screen....
      foreach ($new_html as $w => $html) {
        foreach($html as $req_by_degree_id => $content) {
          
          // Get the degree title...        
          $dtitle = @$GLOBALS["fp_temp_degree_titles"][$req_by_degree_id];
          $css_dtitle = @$GLOBALS["fp_temp_degree_css_titles"][$req_by_degree_id];
          $dtype = @$GLOBALS["fp_temp_degree_types"][$req_by_degree_id];
          $dclass = @$GLOBALS["fp_temp_degree_classes"][$req_by_degree_id];
          $dlevel = @$GLOBALS["fp_temp_degree_levels"][$req_by_degree_id];
                    
          if ($dtitle == "" || $css_dtitle == "") {
            $t_degree_plan = new DegreePlan();
            $t_degree_plan->degree_id = $req_by_degree_id;                
            $dtitle = $t_degree_plan->get_title2(TRUE, TRUE);
            $css_dtitle = $t_degree_plan->get_title2(TRUE, TRUE, FALSE);
            
            $dtype = $t_degree_plan->degree_type;
            $dclass = $t_degree_plan->degree_class;
            $dlevel = $t_degree_plan->degree_level;          
          
          
            $GLOBALS["fp_temp_degree_types"][$req_by_degree_id] = $dtype; //save for next time.
            $GLOBALS["fp_temp_degree_classes"][$req_by_degree_id] = $dclass; //save for next time.
            $GLOBALS["fp_temp_degree_levels"][$req_by_degree_id] = $dlevel; //save for next time.
                      
            $GLOBALS["fp_temp_degree_titles"][$req_by_degree_id] = $dtitle; //save for next time.
            $GLOBALS["fp_temp_degree_css_titles"][$req_by_degree_id] = $css_dtitle; //save for next time.
          }
    
          $css_dtitle = fp_get_machine_readable($css_dtitle);
          
    
    
          $theme = array(
            'classes' => array('tenpt', 'required-by-degree', 
                              "required-by-degree-$css_dtitle",
                              "required-by-degree-type-" . fp_get_machine_readable($dtype), 
                              "required-by-degree-class-" . fp_get_machine_readable($dclass), 
                              "required-by-degree-level-" . fp_get_machine_readable($dlevel),                                
                                ),
            'css_dtitle' => $css_dtitle,
            'degree_id' => $req_by_degree_id,
            'required_by_html' => "<span class='req-by-label'>" . t("Required by") . "</span> <span class='req-by-degree-title'>$dtitle</span>",
            'view_by' => 'type',
          );
  
          invoke_hook("theme_advise_degree_header_row", array(&$theme));        
            
    
          // Don't display if we are in the Courses Added semester, or if we are NOT a "combined" degree.
          if ($semester->semester_num == DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED || (is_object($this->degree_plan)) && !$this->degree_plan->is_combined_dynamic_degree_plan) {
            $theme['required_by_html'] = '';
          }
    
          if ($theme['required_by_html']) {          
            $pC .= "<tr><td colspan='8'>
                      <div class='" . implode(' ',$theme['classes']) ."'>{$theme['required_by_html']}</div>
                    </td></tr>";      
          }
          
          $pC .= $content;
        }
      }    
        
      
      
      
    } // while list_semester
    
    
    if ($is_empty == TRUE) {
      // There was nothing in this box.  Do not return anything.
      return FALSE;
    }
    
    
    
    // Add hour count to the bottom...
    if ($bool_display_hour_count == true && $count_hours_completed > 0)
    {
      $pC .= "<tr><td colspan='8'>
        <div class='tenpt advise-completed-hours' style='text-align:right; margin-top: 10px;'>
        <span class='completed-hours-label'>" . t("Completed hours:") . "</span> <span class='count-hours-completed'>$count_hours_completed</span>
        </div>
        ";
      $pC .= "</td></tr>";
    }

    $pC .= $this->draw_semester_box_bottom();

    return $pC;

  }





















} //class

