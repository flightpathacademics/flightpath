<?php

require_once("ObjList.php");

class CourseList extends ObjList
{
  // This inherits most of its classes from ObjList,
  // but, it has to be able to do special functions
  // specific to Courses.  Use parent:: to access
  // a parent function within ObjList.
  // Example: parent::indexOf();
  //public $arrayCourseIDList = array();

  /**
   * Used to cast a regular ObjList object into a CourseList.
   *
   * @param ObjList $obj
   * 
   * @return CourseList
   */
  static public function cast(ObjList $obj)
  { // This can be used to cast a regular ObjList
    // into a CourseList object.
    // Use the syntax:  CourseList::cast($x);
    $new_c_l = new CourseList();
    $new_c_l->array_list = $obj->array_list;
    $new_c_l->is_empty = $obj->is_empty;
    $new_c_l->reset_counter();

    

    return $new_c_l;

  }


  /**
   * Give every course in the list a minimum grade.
   *
   * @param string $min_grade
   */
  function assign_min_grade($min_grade)
  {
    // Go through the list and give every course the specified
    // min grade.
    $min_grade = strtoupper($min_grade);
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      $course->min_grade = $min_grade;
    }


  }


  /**
   * Go through the list and find any course whose hours are greater
   * than $hours.  Make that course "unselectable."  Used in the groups.
   * 
   * For example, if a student may only select 3 hours from a group, we don't
   * want to give them the option of selecting a 5 hour course.  But we also
   * don't want to remove that course either.  We want to display it so they
   * know it was an option (and possibly need to substitute or move things
   * around if they need it).
   *
   * Returns TRUE if anything got assigned, FALSE if nothing got assigned.
   * 
   * @param int $hours
   * 
   * @return bool
   */
  function assign_unselectable_courses_with_hours_greater_than($hours)
  {
    // Go through the list and assign bool_unselectable courses whose minHour
    // is greater than $hours.
    // Returns TRUE if it did assign something,
    // false if it didn't.

    $bool_assigned = false;

    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      if ($course->subject_id == "")
      {
        $course->load_descriptive_data();
      }
      
      if ($course->min_hours > $hours)
      {
        $course->bool_unselectable = true;
        $bool_assigned = true;
      }
    }

    return $bool_assigned;

  }

  /**
   * Find and return a specific course from the list.
   *
   * @param int $course_id
   *       - The course_id to look for.  Do not set if using
   *         $use_course.
   * 
   * @param int $term_id
   *       - The term_id for the course to look for.  Do not set if using
   *         $use_course.
   * 
   * @param bool $bool_transfer
   *       - Is the course we are looking for a transfer course?  Do not
   *         use if using $use_course.
   * 
   * @param bool $bool_exclude_substitutions
   *       - If TRUE, we will not consider courses which have been used
   *         in a substitution.
   * 
   * @param Course $use_course
   *       - Optional.  If you already have a course object which can be used
   *         as a template to search for, specify it here.  Otherwise, set to
   *         NULL.  If using this, then $course_id, $term_id, and $bool_transfer
   *         will be ignored.
   * 
   * @param Int $sub_req_by_degree_id
   *      - Optional.  If set, we will only exclude substituted courses if they were substitutions made for this degree_id.  Leave 0 if not sure
   *        what to use. 
   * 
   * 
   * @return Course
   */
  function find_specific_course($course_id = 0, $term_id = 0, $bool_transfer = false, $bool_exclude_substitutions = true, Course $use_course = null, $sub_req_by_degree_id = 0)
  {
    if ($use_course != null && is_object($use_course))
    {
      $course_id = $use_course->course_id;
      $term_id = $use_course->term_id;
      $bool_transfer = $use_course->bool_transfer;
    }
    // Look through the array for a course with this id, termId, and
    // transfer credit status.
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];

      $check_course_id = $course->course_id;
      if ($bool_transfer == true && is_object($course->course_transfer))
      {
        $check_course_id = $course->course_transfer->course_id;
      }

      if ($check_course_id == $course_id && $course->term_id == $term_id && $course->bool_transfer == $bool_transfer)
      {

        if ($bool_exclude_substitutions == true)
        {
          if ($course->get_bool_substitution($sub_req_by_degree_id) == TRUE)
          {
            continue;
          }

        }

        return $course;
      }
    }

    return false;
  }


  /**
   * Call the $course->load_course_descriptive_data() on 
   * every course in the list.
   *
   */
  function load_course_descriptive_data()
  {
    // Call the load_descriptive_data() method
    // for every course in the list.

    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      $course->load_descriptive_data();
    }
  }


  /**
   * Call the $course->load_descriptive_transfer_data() on 
   * every course in the list.  Meant for transfer courses.
   *
   */
  function load_descriptive_transfer_data($student_id = 0)
  {
    
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      $course->load_descriptive_transfer_data($student_id);
    }
  } 
  
  
  /**
   * Using the parent's function of find_all_matches, this
   * will return a CourseList of all courses which match
   * the Course object.
   *
   * @param Course $course_c
   * @return CourseList
   */
  function find_all_matches(stdClass $course_c)
  {
    if (!$list_matches =  parent::find_all_matches($course_c))
    {
      return false;
    }


    $list_matches = CourseList::cast($list_matches);
    return $list_matches;
  }


  /**
   * Returns a match to the Course courseC which does
   * not have any courses fulfilling it.  Usefull for finding
   * course requirement matches in a list which have not
   * yet been assigned.
   *
   * @param Course $course_c
   * @return Course
   */
  function find_first_unfulfilled_match(Course $course_c)
  {
    // Returns match to courseC which does not have
    // any courses fulfilling it.  Useful for finding
    // course requirement matches in a list which have not
    // yet been assigned.

    for ($t = 0; $t < $this->count; $t++)
    {
      if ($this->array_list[$t]->equals($course_c) && $this->array_list[$t]->course_list_fulfilled_by->is_empty == true)
      {
        return $this->array_list[$t];
      }
    }

    return false;
  }


  /**
   * Returns a CourseList of courses with this specific grade.
   */
  function find_courses_with_grade($grade = 'C') {
    $rtn = new CourseList();
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      if ($course->grade == $grade) {
        $rtn->add($course);
      }
    }
    
    return $rtn;    
  }



  /**
   * Go through the list and set the $bool_exclude_repeat flag to TRUE
   * for all matches of $course in this list.
   *
   * Returns FALSE if no matches could be found.
   * 
   * @param Course $course
   * @return bool
   */
  function mark_repeats_exclude(Course $course, $degree_id = 0, Course $except_for_course = NULL)
  {
    // Set the bool_exclude_repeat flag to TRUE for all
    // occurances of $course in THIS list.

    if (!$list_matches =  parent::find_all_matches($course))
    {
      return false;
    }

    $list_matches = CourseList::cast($list_matches);
    $list_matches->reset_counter();
    while($list_matches->has_more())
    {
      $c = $list_matches->get_next();
      
      if ($except_for_course != NULL) {
        if ($c == $except_for_course) {
          // Skip it.
          continue;
        }
      }
      
      $c->set_bool_exclude_repeat($degree_id, TRUE);
    }

    return true;

  }

  /**
   * Find a list of matches to Course courseC, which fulfill 
   * the min_grade requirement, ordered by most recently taken.
   *
   * Returns FALSE if no matches were found, else it will 
   * return the matched Course object.
   * 
   * @return Course
   */
  function find_most_recent_match(Course $course_c, $min_grade = "", $bool_mark_repeats_exclude = false, $degree_id = 0, $bool_skip_already_assigned_to_degree = TRUE, $bool_skip_subs = FALSE, $group_id = 0)
  {
    // Get a list of all matches to courseC, and
    // then order them by the most recently taken course
    // first.
    // We should, too, check for minimum grades here
    // as well.

    $min_grade = strtoupper($min_grade);
    
    if (!$list_matches =  parent::find_all_matches($course_c))
    {
      return false;
    }

    $list_matches = CourseList::cast($list_matches);

    if ($list_matches->is_empty)
    {
      return false;
    }

    // If we are here, then we have at least one match.
    // Meaning, we have at least one class which might fit
    // into this course requirement.

    // Sort the courses into most recently taken first.
    $list_matches->sort_most_recent_first();


    $withdrew_grades = csv_to_array(variable_get_for_school("withdrew_grades", "W", $course_c->school_id));
    
    
    // So, now that it's sorted, we should look through the list,
    // checking the min grade requirements (if any).  When we find
    // a good one, we will select it.

    $list_matches->reset_counter();
    while($list_matches->has_more())
    {
      $c = $list_matches->get_next();
      
      if ($c->get_bool_exclude_repeat($degree_id, TRUE))
      {
        continue;
      }
      
      
     if ($bool_skip_subs && $c->get_bool_substitution($degree_id) == TRUE) {
        // It is already being used in a substitution for this degree id, so we skip it.
        continue;
      }      
      
      //////////////////////////////////////////
      ///  Check for min grade, etc, here.      
      if (!$c->meets_min_grade_requirement_of(null, $min_grade))
      {
        //if ($min_grade == "B-") fpm("[did not meet min grade requirement of $min_grade :: $c->subject_id $c->course_num $c->grade");
        if ($bool_mark_repeats_exclude == true)
        {
          // Since this course does not meet the min_grade,
          // check to see if it may be repeated.  If it can't,
          // then we must mark ALL previous attempts at this
          // course as being excluded from further consideration.
          // 
          // We don't do this consideration if they simply
          // withdrew from a course...
          if (in_array($c->grade, $withdrew_grades)) { continue; }

          if (floatval($c->min_hours) == 0 || $c->min_hours == "") {
            $c->load_descriptive_data();  // make sure we get hour data for this course.
          }         
          
          if ($c->repeat_hours <= $c->min_hours)
          {
            // No repeats.            
            $this->mark_repeats_exclude($c, $degree_id);
            return false;

          } else {
            // Repeats allowed, so just continue.
            continue;
          }

        } // if bool_mark_repeats_exclude == true 
        else {
          // We did NOT meet the min_grade requirement!
          //if ($min_grade == "B-") fpm("[did not meet min grade requirement of $min_grade :: $c->subject_id $c->course_num $c->grade");
          $c = FALSE;
          continue;
        }
      } // course did NOT meet the min_grade requirement
      else {
        // The course DID meet the min grade requirement.
        // Are we supposed to exclude repeats?
        if ($bool_mark_repeats_exclude) {
          // Make sure the course isn't allowed to be repeated...
          if ($c->repeat_hours <= $c->min_hours) {
            // No repeats allowed.
            $this->mark_repeats_exclude($c, $degree_id, $c);
          }        
        }
        
      }

      // Has the course already been assigned [to this degree]?
      if ($bool_skip_already_assigned_to_degree && $c->get_has_been_assigned_to_degree_id($degree_id)) {
        // Yes, it's been assigned, so we can just skip it.
        continue;
      }


      // At this point, we are going to invoke a hook, to give add-on modules
      // one last chance to "skip" the course or not.
      $bool_can_proceed = TRUE;
      $result = invoke_hook("courselist_find_match_allow_course", array($c, $course_c, $list_matches, $degree_id, $group_id));
      foreach ($result as $m => $val) {
        // If *any* module said FALSE, then we must skip this course and not assign it to this degree.
        if ($val === FALSE) $bool_can_proceed = $val;
      }            
      
      if (!$bool_can_proceed) {
        continue;
      }



      return $c;
    }

  
    return FALSE;

  }

  
  
  
  
  
  /**
   * Find a list of matches to Course courseC, which fulfill 
   * the min_grade requirement, ordered by most best grade first.
   *
   * Returns FALSE if no matches were found, else it will 
   * return the matched Course object.
   * 
   * 
   * @return Course
   */
  function find_best_grade_match(Course $course_c, $min_grade = "", $bool_mark_repeats_exclude = false, $degree_id = 0, $bool_skip_already_assigned_to_degree = TRUE, $bool_skip_subs = FALSE, $group_id = 0)
  {
    
    $min_grade = strtoupper($min_grade);
        
    $list_matches = parent::find_all_matches($course_c);
    if (!$list_matches) {
      return false;
    }
    
    $list_matches = CourseList::cast($list_matches);
    
    //sort the courses into largest hours first, so equal grades will be sorted by hours
    $list_matches->sort_largest_hours_first();
    
    // Sort the courses into best grade first.
    $list_matches->sort_best_grade_first(NULL, $course_c->school_id);
    

    if (!$list_matches || $list_matches->is_empty)
    {                      
      return false;
    }

    // If we are here, then we have more than one match.
    // Meaning, we have more than one class which might fit
    // into this course requirement.


    $withdrew_grades = csv_to_array(variable_get_for_school("withdrew_grades", "W", $course_c->school_id));
    
    
    // So, now that it's sorted, we should look through the list,
    // checking the min grade requirements (if any).  When we find
    // a good one, we will select it.

    $list_matches->reset_counter();
    while($list_matches->has_more())
    {
      $c = $list_matches->get_next();
      
      if ($c->get_bool_exclude_repeat($degree_id) == TRUE)
      {
        continue;
      }
      
      
      // Has the course already been assigned [to this degree]?
      if ($bool_skip_already_assigned_to_degree && $c->get_has_been_assigned_to_degree_id($degree_id)) {
        // Yes, it's been assigned, so we can just skip it.
        continue;
      }
      
      if ($bool_skip_subs && $c->get_bool_substitution($degree_id) == TRUE) {
        // It is already being used in a substitution for this degree id, so we skip it.
        continue;
      }
      
      //////////////////////////////////////////
      ///  Check for min grade, etc, here.      
      if (!$c->meets_min_grade_requirement_of(null, $min_grade))
      {
                
        if ($bool_mark_repeats_exclude == true)
        {
          // Since this course does not meet the min_grade,
          // check to see if it may be repeated.  If it can't,
          // then we must mark ALL previous attempts at this
          // course as being excluded from further consideration.
          // (ULM policy on repeats).
          // We don't do this consideration if they simply
          // withdrew from a course...
          if (in_array($c->grade, $withdrew_grades)) { continue; }

          if (floatval($c->min_hours) == 0 || $c->min_hours == "") {
            $c->load_descriptive_data();  // make sure we get hour data for this course.
          }         
          
          if ($c->repeat_hours <= $c->min_hours)
          {
            // No repeats.
            $this->mark_repeats_exclude($c, $degree_id);

            return false;

          } else {
            // Repeats allowed, so just continue.
            continue;
          }

        } // if bool_mark_repeats_exclude == true 
        else {
          // We did NOT meet the min_grade requirement!
          $c = FALSE;
          continue;
        }
      } // course did NOT meet the min_grade requirement
      else {
        // The course DID meet the min grade requirement.
        
        // Are we supposed to exclude repeats?
        if ($bool_mark_repeats_exclude) {
          // Make sure the course isn't allowed to be repeated...
          if ($c->repeat_hours <= $c->min_hours) {
            // No repeats allowed.
            $this->mark_repeats_exclude($c, $degree_id, $c);
          }        
        }        
        
      }


      // At this point, we are going to invoke a hook, to give add-on modules
      // one last chance to "skip" the course or not.
      $bool_can_proceed = TRUE;
      $result = invoke_hook("courselist_find_match_allow_course", array($c, $course_c, $list_matches, $degree_id, $group_id));
      foreach ($result as $m => $val) {
        // If *any* module said FALSE, then we must skip this course and not assign it to this degree.
        if ($val === FALSE) $bool_can_proceed = $val;
      }            
      
      if (!$bool_can_proceed) {
        continue;
      }

      return $c;
    } // while
            
    return FALSE;

  } // find_best_grade_match   
   
   
   
   
  
  
  
  
  
  /**
   * Sorts best-grade-first, as defined by the setting "grade_order", which is a CSV of
   * grades, best-first.  Ex:  A, B, C, D, F
   * 
   * If the student object is set to a student, we will use that's student's best grade for a course, rather
   * than the actual course's grade.  Generally, this can be left as set to null.   This is only for when we are
   * trying to organize a list of courses into the grade order, based on what a student has taken.  For example, if we want
   * to order a Group's list of courses based on what the student has taken and the grades they made.
   *
   */
  function sort_best_grade_first(Student $student = NULL, $school_id = 0) {

    if ($student) {
      $school_id = $student->school_id;
    }    
    
    $temp = csv_to_array(variable_get_for_school("grade_order", "AMID,BMID,CMID,DMID,FMID,A,B,C,D,F,W,I", $school_id));    
    // We will use array_flip to get back an assoc array where the grades are the keys and the indexes are the values.
    $temp = array_flip($temp);
    // Go through the grades and convert the integers to strings, padd with zeros so that everything is at least 3 digits.
    $grades = array();
    foreach ($temp as $grade => $val) {
      $grades[$grade] = str_pad((string)$val, 3, "0", STR_PAD_LEFT);
    }
    
    // We now have our grades array just how we want it.  Best grade has lowest value.  Worst grade has highest value.
        
    $unknown_grade_value = 999;  // sort to the very end, in other words.   
    $student_grade_score = 0;
    
        
    // We are going to go through our courses and, based on the grade, assign them a value.
    $tarray = array();
    for ($t = 0; $t < $this->count; $t++) {
      // $t is the index for the array_list, keep in mind.
      
      $c = $this->array_list[$t];
      
      $use_grade = $c->grade;
      
      if ($student != null) {       
        $use_grade = $student->get_best_grade_for_course($c);        
        if (!$use_grade) $use_grade = "";
      }
      
      @$grade_value = $grades[$use_grade];
      if ($grade_value == "") {
        // Couldn't find this grade in our array, so give it the unknown value.
        $grade_value = $unknown_grade_value;
      }
      
      $student_grade_score += intval($grade_value);
      // Add to a string in array so we can sort easily using a normal sort operation.
      $tarray[$grade_value][] = $c;
      
    }
    
    // Sort best-grade-first:
    ksort($tarray,SORT_NUMERIC);    
    
    // Okay, now go back through tarray and re-construct a new CourseList
    $new_list = new CourseList();
    foreach ($tarray as $per_grade_courses) {
      foreach ($per_grade_courses as $course) {
        $new_list->add($course);
      }
    }
    
    // Okay, now $new_list should contain the correct values.
    // We will transfer over the reference.
    $this->array_list = $new_list->array_list;        

    
    // And we are done!
    if ($student != NULL) {
      // Return the "student grade score" for this list of courses.
      return $student_grade_score;
    }
    
    
  }  
   
   
    
   
   
  
  
  /**
   * Remove courses from THIS list which appear in listCourses under
   * these conditions:
   *   - the listCourses->"assigned_to_group_id" != $group_id
   * This function is being used primarily with $list_courses being the
   * list of courses that students have taken.
   * Also checking substitutions for courses substituted into groups.

   * @param CourseList $list_courses
   * @param int $group_id
   * @param bool $bool_keep_repeatable_courses
   * @param SubstitutionList $list_substitutions
   * @param $degree_id  The degree id to look for.  If it's -1, then ignore it. If it's 0, use the course's req_by_degree_id.
   */
  function remove_previously_fulfilled(CourseList $list_courses, $group_id, $bool_keep_repeatable_courses = true, $list_substitutions = NULL, $degree_id = 0)
  {

    $rtn_list = new CourseList();

    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];

      if ($bool_keep_repeatable_courses == true)
      { // We can always keep repeatable courses in the list.
        if ($course->repeat_hours > $course->min_hours)
        {
          $rtn_list->add($course);
          continue;
        }
      }

      // Has the course been substituted?
      if ($list_substitutions != NULL && $test_sub = $list_substitutions->find_requirement($course, false, -1))
      {
        // it WAS substituted, so we should NOT add it to our
        // rtnList.       
        // We should only skip it if the test_sub's degree_id matches the one supplied...
        if ($degree_id >= 0) {
          if ($test_sub->assigned_to_degree_id == $degree_id) {
            continue;
          }  
        }
        else if ($degree_id < 0) {
          // degree_id is -1, so we don't care what degree it was assigned to.
          continue;
        }
      }
            
      // Okay, now check if $course is anywhere in $list_courses
      //if ($test_course = $list_courses->find_match($course))
      // Instead of using simply find_match(), which does not allow for any hook interventions, switch to using find_best_match.
      // Thanks for Logan Buth for this.
      if ($test_course = $list_courses->find_best_match($course, '', FALSE, $degree_id, TRUE, FALSE, $group_id))
      {
        // Yes, it found a match.
        // I am taking out this part where I say if it is in
        // this group then we can keep it.  I think that shouldn't
        // be in.
        // This course is in another group, so do nothing
        // and skip it.
        
        // perhaps the course is on the degreePlan in excess with a W
        // or F?
        if (!$test_course->meets_min_grade_requirement_of(null, "D"))
        {
          // Meaning, this was a failed attempt, so we can add
          // our original course back in.
          $rtn_list->add($course);
          continue;
        }
        
        // perhaps this course was purposefully excluded from
        // this list because it did not meet the min grade
        // requirements?  If this is the case, $course should
        // still appear in THIS list.
        if (!$test_course->meets_min_grade_requirement_of($course))
        {
          // Meaning, this was attempt did not meet the
          // min grade of the original requirement, so we can add
          // our original requirement back in.
          $rtn_list->add($course);
          continue;
        }
                              
      } else {
        // The course was NOT found in the courseList,
        // so its safe to add it back in.
        $rtn_list->add($course);
      }

    }


    $this->array_list = $rtn_list->array_list;
    $this->reset_counter();

  }


  /**
   * Return an array of all the course_id's in this course_list.
   */
  function get_course_id_array() {
    
    $rtn = array();
    
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
            
      $rtn[$course->course_id] = TRUE;  
    }

    return $rtn;

  }
  
  
  
  
  

  /**
   * Returns an array containing the unique subject_id's of
   * the courses in this list.  Its assumed to be ordered
   * already!
   *
   * @param bool $bool_ignore_excluded
   * @return array
   */
  function get_course_subjects($bool_ignore_excluded = true)
  {
    // returns an array containing the unique subject_id's
    // of the courses in this list.

    // IMPORTANT:  The list is assumed to be ordered already!  Either
    // alphabetically or reverse alphabetically.
    $old_subject_id = "";
    $rtn_array = array();

    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      if ($course->subject_id == "")
      {
        $course->load_descriptive_data();
      }

      
      // Go through all valid names for this course.
      for ($x = 0; $x < count($course->array_valid_names); $x++)
      {
        $temp = explode("~",$course->array_valid_names[$x]);
        $subj = strtoupper($temp[0]);

        if (in_array($subj, $rtn_array))
        { // skip ones with subjects we have already looked at.
          continue;
        }

        if (intval($course->db_exclude) === 1) {
          continue;
        }

        // probably because it was excluded.
        if ($subj != $course->subject_id) {
          continue;
        }


        // We have a new subject.  Add it to the array.
        $rtn_array[] = $subj;
      }

    }

    return $rtn_array;
  }

  /**
   * Go through the courseList and take out any course
   * which does not have the $subject as its subject_id.
   *
   * @param string $subject
   * @param bool $bool_reassign_valid_name
   *     - If set to TRUE, we will look at other possible valid names
   *       for this course.  If we find one, we will reassign the course's
   *       subject_id and course_num to the new valid name.
   * 
   */
  function exclude_all_subjects_except($subject, $bool_reassign_valid_name = true)
  {

    $new_course_list = new CourseList();

    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      if ($course->subject_id == $subject)
      {
        $new_course_list->add($course);
        continue;
      }
      
      // Not the right subject-- but perhaps the course has another
      // valid name with this subject?  Ex: CSCI 373 and MATH 373.
      
      if ($bool_reassign_valid_name == true && count($course->array_valid_names) > 1)
      {
        for ($x = 0; $x < count($course->array_valid_names); $x++)
        {
          if (strstr($course->array_valid_names[$x], $subject))
          {
            $temp = explode("~",$course->array_valid_names[$x]);
            $course->subject_id = $temp[0];
            $course->course_num = $temp[1];

            $new_course_list->add($course);
            continue;
          }
        }
        
      }
      
      
    }

    // Now, transfer ownership of the arraylist.
    $this->array_list = $new_course_list->array_list;

  }


  /**
   * This re-sorts the CourseList so that fulfilled courses
   * are first, in alphabetical order, followed by
   * unfulfilled courses, in alphabetical order.
   * This is most useful for making the groups
   * show up correctly.
   * 
   */
  function sort_fulfilled_first_alphabetical()
  {

    $tarray = array();
    for ($t = 0; $t < $this->count; $t++)
    {
      //if (!is_object($this->array_list[$t]->courseFulfilledBy))
      if ($this->array_list[$t]->course_list_fulfilled_by->is_empty == true)
      { // Skip if not fulfilled.
        continue;
      }

      $c = $this->array_list[$t];
      $str = "$c->subject_id ~~ $c->course_num ~~ $t";
      array_push($tarray,$str);
    }

    sort($tarray);

    $new_list = new CourseList();
    for($t = 0; $t < count($tarray); $t++)
    {
      $temp = explode(" ~~ ",$tarray[$t]);
      $i = $temp[2];

      $new_list->add($this->array_list[$i]);
    }


    // Alright, now we do it again, but with unfulfilled courses.
    $tarray = array();
    for ($t = 0; $t < $this->count; $t++)
    {
      //if (is_object($this->array_list[$t]->courseFulfilledBy))
      if ($this->array_list[$t]->course_list_fulfilled_by->is_empty != true)
      { // Skip if fulfilled.
        continue;
      }

      $c = $this->array_list[$t];
      $str = "$c->subject_id ~~ $c->course_num ~~ $t";
      array_push($tarray,$str);
    }

    sort($tarray);

    $new_list2 = new CourseList();
    for($t = 0; $t < count($tarray); $t++)
    {
      $temp = explode(" ~~ ",$tarray[$t]);
      $i = $temp[2];

      $new_list2->add($this->array_list[$i]);
    }

    // Now, combine the two lists.
    $new_list->add_list($new_list2);

    // And, transfer the newList into this list.
    $this->array_list = $new_list->array_list;


  }



  /**
   * This re-sorts the CourseList so that advised courses
   * are last, in alphabetical order, preceeded by
   * unfulfilled courses, in alphabetical order.
   * 
   *
   */
  function sort_advised_last_alphabetical()
  {

    $tarray = array();
    for ($t = 0; $t < $this->count; $t++)
    {
      if ($this->array_list[$t]->bool_advised_to_take == true)
      { // Skip if not fulfilled.
        continue;
      }

      $c = $this->array_list[$t];
      $str = "$c->subject_id ~~ $c->course_num ~~ $t";
      array_push($tarray,$str);
    }

    sort($tarray);


    // Alright, now we do it again, but with advised courses.
    $t2array = array();
    for ($t = 0; $t < $this->count; $t++)
    {
      if ($this->array_list[$t]->bool_advised_to_take == false)
      { // Skip if not advised
        continue;
      }

      $c = $this->array_list[$t];
      $str = "$c->subject_id ~~ $c->course_num ~~ $t";
      array_push($t2array,$str);
    }

    sort($t2array);

    $t3array = array_merge($tarray, $t2array);

    $new_list = new CourseList();
    for($t = 0; $t < count($t3array); $t++)
    {
      $temp = explode(" ~~ ",$t3array[$t]);
      $i = $temp[2];

      $new_list->add($this->array_list[$i]);
    }

    // And, transfer the newList into this list.
    $this->array_list = $new_list->array_list;


  }


  /**
   * This function will resort this courselist for which a substitution
   * has been made in listSubstitutions.
   *
   * @param SubstitutionList $list_substitutions
   * @param int $group_id
   */
  function sort_substitutions_first($list_substitutions, $group_id = 0)
  {
    // This will sort courses in a list for which
    // a substitution has been made in listSubstitutions.
    // It will place those courses at the top of the list.

    $top_array = array();

    // Since I need the indexes, I will have to go through the array
    // myself...
    
    // array to keep track of all the substitutions that have been used for this list (Logan's change ticket 2291)
    $used_substitution_ids = array();
    
    for ($t = 0; $t < $this->count; $t++) {
      $c = $this->array_list[$t];
      // So-- does this course have a substitution somewhere in
      // the list (for the supplied groupID) ?
      // supply the list of used ids, so they are not reused in this list. (Logan's change ticket 2291)
      if ($substitution = $list_substitutions->find_requirement($c, true, $group_id,0,$used_substitution_ids))
      {
        // yes, there is a sub for this group (or bare degree plan)
        
        //mark the substitution so it doesn't get used again (Logan's change ticket 2291)
        $used_substitution_ids[] = $substitution->db_substitution_id;

        $top_array[] = $t;
      }

    }

    // Okay, we now have, in the topArray, a list of indexes which should
    // appear at the top.
    $new_list = new CourseList();
    for ($j = 0; $j < count($top_array); $j++)
    {
      $new_list->add($this->array_list[$top_array[$j]]);
    }

    // Now, add everything else in the array (except indecies
    // appearing in topArray)

    for ($t = 0; $t < $this->count; $t++)
    {
      if (in_array($t, $top_array))
      {
        continue;
      }
      $new_list->add($this->array_list[$t]);
    }

    $this->array_list = $new_list->array_list;
    $new_list->reset_counter();

  }


  /**
   * This will sort so that courses with the smallest hours
   * (first trying hours_awarded, then min_hours)
   * are at the top of the list.  If the list contains more
   * than one course with a set of hours (like there are 30   
   * courses all worth 3 hours) then it orders those as
   * most-recently-taken first. 
   *
   */
  function sort_smallest_hours_first()
  {
    
    $tarray = array();

    // Since I need the indexes, I will have to go through the array
    // myself...
    for ($t = 0; $t < $this->count; $t++)
    {
      $c = $this->array_list[$t];
      $hours = $c->get_hours_awarded();
      if (floatval($hours) == 0)
      {
        $hours = $c->min_hours*1;
      }
      $str = "$hours ~~ $t";
      array_push($tarray,$str);
    }

    // Now, sort the array...
    //print_pre(print_r($tarray));
    sort($tarray);
    //print_pre(print_r($tarray));

    // Now, convert the array back into a list of courses.
    $new_list = new CourseList();
    for($t = 0; $t < count($tarray); $t++)
    {
      $temp = explode(" ~~ ",$tarray[$t]);
      $i = $temp[1];

      $new_list->add($this->array_list[$i]);
    }

    // Okay, now $new_list should contain the correct values.
    // We will transfer over the reference.
    $this->array_list = $new_list->array_list;


  }  // sort_smallest_hours_first
  
  
  /*
   * Sort the courses of this list by number of hours descending.
   * Order of courses with the same number of hours is preserved.
   */
  function sort_largest_hours_first() {
    $sort_list = array();
    
    // go through the courses, creating an array keyed by hours.
    for ($t = 0; $t < count($this->array_list); $t++) {
      $hrs = $this->array_list[$t]->get_hours();
      if ($this->array_list[$t]->bool_ghost_hour) $hrs = 0;
      $hrs = "$hrs";  // force into a string so hours with decimals are preserved. Prevents warning in PHP 8
      $sort_list[$hrs][] = $this->array_list[$t];
    }
    
    //sort the array in order of hours descending
    krsort($sort_list,SORT_NUMERIC);
    
    //clear the internal array, then refill it with the now sorted courses.
    $this->array_list = array();
    foreach ($sort_list as $courses) {
      foreach ($courses as $course) {
        $this->array_list[] = $course;
      }
    }
    $this->reset_counter();
  } 
  


  /**
   * This method will sort by the most recently taken
   * courses (determined by the term_id).
   * The easiest way I can think to do this is to temporarily
   * put their term_id's and index#'s into an array, and then
   * have PHP sort the array itself.  PHP's sorting algorithm
   * is faster than anything I can program right now, anyway.
   *
   * @param bool $bool_maintain_alpha_order
   */
  function sort_most_recent_first($bool_maintain_alpha_order = true)
  {
    $tarray = array();

    
    // Since I need the indexes, I will have to go through the array
    // myself...
    for ($t = 0; $t < $this->count; $t++)
    {
      $c = $this->array_list[$t];
      $cn = "";
      if ($bool_maintain_alpha_order == true)
      {
        // We say 1000- the course number in order to give
        // us the complement of the number.  That is so it will
        // reverse-sort in the correct order.  Strange, but it fixes
        // a small display issue where PHYS 207 and PHYS 209, taken at
        // the same time, causes PHYS 209 to be displayed first.
        // We also reverse the subject_id, again, so that
        // MATH will be sorted above ZOOL, when taken at the same time.
        // This might not work at all, though...
        
        // TODO:  The 1000 - course_num bit doesn't work (throws a warning) if the course_num
        // is non-numeric.  Ex:  301A.  Let's instead use the same strrev() function on course_num, but we put it in quotes
        // to guarantee it gets evaluated as a string.
        //$cn = strrev($c->subject_id) . "," . (1000 - $c->course_num);
        $cn = strrev($c->subject_id) . "," . strrev("$c->course_num");

      }
      $str = "$c->term_id ~~ $cn ~~ $t";

      array_push($tarray,$str);
    }

    // Now, sort the array...
    rsort($tarray);

    // Now, convert the array back into a list of courses.
    $new_list = new CourseList();
    for($t = 0; $t < count($tarray); $t++)
    {
      $temp = explode(" ~~ ",$tarray[$t]);
      $i = $temp[2];

      $new_list->add($this->array_list[$i]);
    }

    // Okay, now $new_list should contain the correct values.
    // We will transfer over the reference.
    $this->array_list = $new_list->array_list;

  }

  /**
   * Convienence function.  It simply calls sort_alphabetical_order(), but
   * passes the boolean value to make it be reversed.
   *
   */
  function sort_reverse_alphabetical_order()
  {
    $this->sort_alphabetical_order(true);
  }



  /**
  * Sorts the courses based on their db_degree_requirement_id value
  */
  function sort_degree_requirement_id() {
    
    $sort_list = array();
    
    // go through the courses, creating an array keyed by db_degree_requirement_id.
    for ($t = 0; $t < count($this->array_list); $t++) {
      $id = intval($this->array_list[$t]->db_degree_requirement_id);
            
      $sort_list[$id][] = $this->array_list[$t];
    }
    
    //sort the array in order ascending
    ksort($sort_list,SORT_NUMERIC);
    
    //clear the internal array, then refill it with the now sorted courses.
    $this->array_list = array();
    foreach ($sort_list as $courses) {
      foreach ($courses as $course) {
        $this->array_list[] = $course;
      }
    }
    $this->reset_counter();    
    
    
  }


  /**
  * Sorts the courses based on their db_group_requirement_id value
  */
  function sort_group_requirement_id() {
    
    $sort_list = array();
    
    // go through the courses, creating an array keyed by db_degree_requirement_id.
    for ($t = 0; $t < count($this->array_list); $t++) {
      $id = (string) $this->array_list[$t]->db_group_requirement_id;   // not necessarily an integer, don't cast as one.
      
      // make sure that $id is at least 20 digits, padding 0's on the left, so that the sort works even if the id is a different
      // number of digits.
      $id = str_pad($id, 20, "0", STR_PAD_LEFT);            
      $sort_list[$id][] = $this->array_list[$t];
    }
    
    //sort the array in order ascending
    ksort($sort_list);
    //clear the internal array, then refill it with the now sorted courses.
    $this->array_list = array();
    foreach ($sort_list as $courses) {
      foreach ($courses as $course) {
        $this->array_list[] = $course;
      }
    }
    $this->reset_counter();    
    
    
  }



  /**
   * Sorts the course list into alphabetical order.  If load_descriptive_data()
   * has not already been called for each course, it will call it.
   *
   * @param bool $bool_reverse_order
   *         - If set to TRUE, the list will be in reverse order.
   * 
   * @param unknown_type $bool_only_transfers
   *         - Only sort the transfer courses.
   * 
   * @param unknown_type $bool_set_array_index
   *         - If set to true, it will set the $course->array_index value
   *           to the index value in $this's array_list array.
   * @param new_split_subs_higher_priority_in_degree_id
   *         - If the course is a split substitution for the supplied degree_id, then  give it a higher "priority" so it will
   *           sort above courses with identical names.
   * 
   */
  function sort_alphabetical_order($bool_reverse_order = false, $bool_only_transfers = false, $bool_set_array_index = false, $subs_higher_prority_in_degree_id = 0, $bool_include_degree_sort = FALSE)
  {
    // Sort the list into alphabetical order, based
    // on the subject_id and course_num.
    $tarray = array();
    // Since I need the indexes, I will have to go through the array
    // myself...
    for ($t = 0; $t < $this->count; $t++)
    {
      $c = NULL;
      if (isset($this->array_list[$t])) {  
        $c = $this->array_list[$t];
      }
      
      if ($c == NULL) continue;
      

      // If this is a transfer credit, make sure we load it's information
      if ($c->bool_transfer == TRUE && is_object($c->course_transfer)) {
        if ($c->course_transfer->subject_id == '') {
          $c->course_transfer->load_descriptive_transfer_data();
        }
        if ($c->subject_id == '') {
          $c->subject_id = $c->course_transfer->subject_id;
          $c->course_num = $c->course_transfer->course_num;
        }  
      }
      
            
      if ($c->subject_id == "")
      {       
        $c->load_descriptive_data();
      }


      $priority = 5;  // default sort priority for courses with identical names.
      if ($subs_higher_prority_in_degree_id > 0) {
        if (@$c->details_by_degree_array[$subs_higher_prority_in_degree_id]["bool_substitution_new_from_split"] == TRUE
            || @$c->details_by_degree_array[$subs_higher_prority_in_degree_id]["bool_substitution_split"] == TRUE
            || @$c->details_by_degree_array[$subs_higher_prority_in_degree_id]["bool_substitution"] == TRUE) {
          //fpm("here for $c->subject_id $c->course_num");
          $priority = 3;  // lower priority so it sorts higher in the list.
        }
      }
      

      // Make $t at least 5 characters long, padded with zeroes on the left, so sorting works correctly.  We are using it to
      // find out our index later, but it is throwing off the sorting when courses have the same name.  For example,
      // if a course is from a split sub.
      $tpad = str_pad("$t",5,"0",STR_PAD_LEFT);

      $degree_title = "n";  // Default.
      $degree_advising_weight = "0000";
      
      if ($bool_include_degree_sort) {
        // Find the actual degree title for this course.
        if (intval($c->req_by_degree_id) > 0) {        
          // Get the degree title...         
          $dtitle = @$GLOBALS["fp_temp_degree_titles"][$c->req_by_degree_id];
          $dweight = intval(@$GLOBALS["fp_temp_degree_advising_weights"][$c->req_by_degree_id]);
          
          if ($dtitle == "" || $dweight == "" || $dweight == 0) {
            $t_degree_plan = new DegreePlan($c->req_by_degree_id);
            $t_degree_plan->load_descriptive_data();        
            $dtitle = $t_degree_plan->get_title2(TRUE, TRUE);
            $dweight = $t_degree_plan->db_advising_weight;
            $GLOBALS["fp_temp_degree_titles"][$c->req_by_degree_id] = $dtitle . " "; //save for next time.
            $GLOBALS["fp_temp_degree_advising_weights"][$c->req_by_degree_id] = $dweight . " "; //save for next time.
          }
          
          $degree_title = fp_get_machine_readable($dtitle);  // make it machine readable.  No funny characters.
          $degree_advising_weight = str_pad($dweight, 4, "0", STR_PAD_LEFT);
        } 
      }


      if ($bool_only_transfers == true)
      {
        // Rarer.  We only want to sort the transfer credits.  If the course doesn not
        // have transfers, don't skip, just put in the original.  Otherwise, we will be using
        // the transfer credit's SI and CN.
        if (is_object($c->course_transfer))
        {
          $str = $degree_advising_weight . " ~~ " . $degree_title . " ~~ " . $c->course_transfer->subject_id . " ~~ " . $c->course_transfer->course_num ." ~~ $priority ~~ $tpad";
        } else {
          // There was no transfer!
          $str = "$degree_advising_weight ~~ $degree_title ~~ $c->subject_id ~~ $c->course_num ~~ $priority ~~ $tpad";
        }
      } else {

        // This is the one which will be run most often.  Just sort the list
        // in alphabetical order.

        $str = "$degree_advising_weight ~~ $degree_title ~~ $c->subject_id ~~ $c->course_num ~~ $priority ~~ $tpad";
      }
      array_push($tarray,$str);
    }

    // Now, sort the array...
    //print_pre(print_r($tarray));

    if ($bool_reverse_order == true)
    {
      rsort($tarray);
    } else {
      sort($tarray);
    }
    //print_pre(print_r($tarray));

    // Now, convert the array back into a list of courses.
    $new_list = new CourseList();
    for($t = 0; $t < count($tarray); $t++)
    {
      $temp = explode(" ~~ ",$tarray[$t]);
      $i = intval($temp[5]);
      
      if ($bool_set_array_index == true)
      {
        $this->array_list[$i]->array_index = $i;
      }
      $new_list->add($this->array_list[$i]);
    }

    // Okay, now $new_list should contain the correct values.
    // We will transfer over the reference.
    $this->array_list = $new_list->array_list;

  }


  /**
   * Returns an array of db_group_requirement_id's from the courses
   * in this list.
   *
   * @return array
   */
  function get_group_requirement_id_array()
  {
    // Return an array of db_group_requirement_id's
    // from the courses in this list, indexed by the
    // id's.

    $rtn_array = array();
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      $rtn_array[$course->db_group_requirement_id] = true;
    }

    return $rtn_array;
  }

  
  
  /**
   * Returns TRUE if this list has a course which contains
   * $id for it's db_group_requirement_id property.
   *
   * @param int $id
   *         - This is the id to test for.
   * 
   * @return bool
   */
  function contains_group_requirement_id($id)
  {
    // Returns true if the list has a course
    // which contains $id for it's db_group_requirement_id.
    // False if it cannot be found.
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      if ($course->db_group_requirement_id == $id)
      {
        return true;
      }
    }

    return false;

  }

  
  /**
   * Returns the first course in the list
   * which the user may select for advising.  This
   * method is used by the popup window to determine
   * what exactly is the first element of the course list.
   * (so it can make that radio button checked).
   * 
   * Returns FALSE if it cannot find a selectable course.
   *
   * @return Course
   */
  function find_first_selectable()
  {
    /*

    */
    $temp_i = $this->i;  // preserve the counter.
    $this->reset_counter();

    while($this->has_more())
    {
      $c = $this->get_next();
      if ($c->bool_advised_to_take == true)
      {
        continue;
      }
      if (!$c->course_list_fulfilled_by->is_empty)
      {
        continue;
      }
      if ($c->bool_unselectable == true)
      {
        continue;
      }

      // $c is our valid course...
      $this->i = $temp_i;
      //print_pre($c->to_string());
      return $c;


    }

    $this->i = $temp_i;
    return false;

  }


  /**
   * Returns TRUE if there is at least one course in this list which
   * is selected (for advising).
   *
   * @return bool
   */
  function has_any_course_selected()
  {
    /*
    Returns TRUE if there is at least one course
    in this list which is "selected."  FALSE, otherwise.
    */
    $temp_i = $this->i;  // preserve the counter.
    $this->reset_counter();
    $rtn = false;
    while($this->has_more())
    {
      $c = $this->get_next();
      if ($c->bool_selected == true)
      {
        $rtn = true;
        break;
      }
    }

    $this->i = $temp_i;
    return $rtn;
  }


  /**
   * Mark every course in this list as bool_has_been_displayed = true.
   * Used for making sure we don't display the same course twice on
   * screen.
   * 
   * Returns FALSE if we did not mark any courses.
   *
   * @param int $semester_num
   *         - If > -1, we will first make sure the course
   *           falls into this semesterNum.  This way we can only
   *           perform this operation on a particular semester.
   * 
   * @return bool
   */
  function mark_as_displayed($semester_num = -1)
  {

    $temp_i = $this->i;  // preserve the counter.
    $this->reset_counter();
    $rtn = false;
    while($this->has_more())
    {
      $c = $this->get_next();
      if ($semester_num != -1)
      { // A semesterNum was specified.
        // Make sure the course is in the correct semester.
        if ($c->assigned_to_semester_num != $semester_num)
        {
          continue;
        }
      }

      $c->bool_has_been_displayed = true;
      $rtn = true;

    }

    $this->i = $temp_i;
    return $rtn;
  }


  /**
   * Returns a CourseList of all the courses matching course_id
   * that has bool_has_been_assigned == TRUE for the requested degree
   *
   * @param int $course_id
   * @return CourseList
   */
  function get_previous_assignments($course_id, $degree_id = 0)
  {
    // Return a courseList of all the times a course matching
    // course_id has the bool_has_been_assigned set to TRUE.

    $rtn_list = new CourseList();

    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      if ($course->course_id == $course_id && $course->get_has_been_assigned_to_degree_id($degree_id) == true)
      {
        $rtn_list->add($course);
      }
    }

    return $rtn_list;

  }

  /**
   * Find the "best" match for this course, based on what
   * the university considers a best match.
   * This largely has to do with repeats.
   * If the student has more than one credit, what is
   * the "best" match?
   * 
   * For example, at ULM we consider the best match to be the
   * most recent that meets the minimum requirements.
   * Other schools might simply take the best grade.
   *
   *
   * @return Course
   */
  function find_best_match(Course $course_c, $min_grade = "", $bool_mark_repeats_exclude = false, $degree_id = 0, $bool_skip_already_assigned_to_degree = TRUE, $bool_skip_subs = FALSE, $group_id = 0)
  {
    $rtn = FALSE;
    
    $min_grade = strtoupper($min_grade);
    
    $school_id = $course_c->school_id;    
    
    // We will look at the course_repeat_policy to determine which type of search to do on this list.
    $course_repeat_policy = variable_get_for_school("course_repeat_policy", "most_recent_exclude_previous", $school_id);
    
    if ($course_repeat_policy == "best_grade_exclude_others") {
      // Search for best grade, exclude other attempts.
      $rtn = $this->find_best_grade_match($course_c, $min_grade, TRUE, $degree_id, $bool_skip_already_assigned_to_degree, $bool_skip_subs, $group_id);
    }
    else {
      // Search for most recent first, possibly mark previous as excluded.      
      $rtn = $this->find_most_recent_match($course_c, $min_grade, $bool_mark_repeats_exclude, $degree_id, $bool_skip_already_assigned_to_degree, $bool_skip_subs, $group_id);
    }

    return $rtn;
  }



  /**
   * Adds the supplied CourseList to the bottom of $this's list.
   *
   * @param CourseList $course_l
   */
  function add_list(CourseList $course_l) {
          
    $c = count($course_l->array_list);
    
    for ($t = 0; $t < $c; $t++)
    {
      $this->add($course_l->array_list[$t]);
    }

  }


  /**
   * Returns hour many hours are in $this CourseList.
   *
   * @param string $requirement_type
   *         - If specified, we will only count courses which match this
   *           requirement_type.
   * 
   * @param bool $bool_use_ignore_list
   * @return int
   */
  function count_hours($requirement_type = "", $bool_use_ignore_list = false, $bool_correct_ghost_hour = true, $bool_force_zero_hours_to_one_hour = false, $bool_exclude_all_transfer_credits = FALSE, $degree_id = 0)
  {
    // Returns how many hours are being represented in this courseList.
    // A requirement type of "uc" is the same as "c"
    // (university capstone is a core requirement)

    $count = 0;
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];

      // Does this course belong to the same degree we are interested in?  If not, skip it.
      if ($degree_id > 0) {
        if ($course->req_by_degree_id != $degree_id && $course->get_has_been_assigned_to_degree_id($degree_id) != TRUE) continue;
      }
      
      $school_id = $course->school_id;


      if ($bool_use_ignore_list == true)
      {
        // Do ignore some courses...
        $temp_course_name = $course->subject_id . " " . $course->course_num;
        // Check in our settings to see if we should ignore this course
        // (configured in /custom/settings.php)
        if (in_array($temp_course_name, csv_to_array(@variable_get_for_school("ignore_courses_from_hour_counts", "", $school_id)))) {
          continue;
        }
        
        // Also, if the course's requirement_type is "x" it means we should ignore it.
        if ($course->requirement_type == 'x') continue;
        
        
      }
      
      if ($course->get_bool_substitution_new_from_split($degree_id) == TRUE)      
      {
        // Do not count the possible fragments that are created
        // from a new substitution split.  This is causing problems
        // in getting accurate numbers on the pie charts.
        
        // BUT-- only skip if this new fragment isn't also being
        // substituted somewhere else!
        if ($course->get_bool_substitution($degree_id) == FALSE)
        { // not being used in another sub, so skip it.         
          continue;
        }
      }

      $h_get_hours = $course->get_hours($degree_id);
            
      if ($bool_correct_ghost_hour) {
        // If this course has a ghosthour, then use the
        // hours_awarded (probably 1).  However, if it was substituted,
        // then we actually want the 0 hour.  Confusing, isn't it?
        if ($course->bool_ghost_hour) {
          $h_get_hours = $course->get_hours_awarded($degree_id);
        }
      }
      
      if ($bool_force_zero_hours_to_one_hour) {       
        // We want to force anything with a 0 hour to be 1 hour.
        // Helps when selecting 0 hour courses from groups.
        if ($h_get_hours == 0) {          
          $h_get_hours = 1;
        }
      }              
      
      
      // Make sure we aren't trying to exclude any transfer credits.
      if ($bool_exclude_all_transfer_credits) {        
        if ($course->bool_transfer) {
          continue;
        }
        // Is this a requirement which has been fulfilled by a course?  And if so, is THAT course a transfer?
        if ($course->course_list_fulfilled_by->is_empty == false) {
          $cc = $course->course_list_fulfilled_by->get_first();
          if ($cc->bool_transfer) {
            continue;
          }
        }               
      }                             
                                                    
      
      
      
      
      if ($requirement_type == "")
      {
        $count = $count + $h_get_hours;
      } 
      else {
        // Requirement Type not blank, so only count these hours
        // if it has the set requirement type.
        if ($course->requirement_type == $requirement_type)
        {
          $count = $count + $h_get_hours;
          continue;
        }

        // For specifically "university capstone" courses (which have a 'u' in front)...
        if ($course->requirement_type == "u" . $requirement_type)
        {
          $count = $count + $h_get_hours;
        }



      }
    }

    return $count;
  }

  
  /**
   * Removes courses which have neither been fulfilled or advised.
   *
   */
  function remove_unfulfilled_and_unadvised_courses()
  {
    // remove courses from THIS list
    // which have not been fulfilled AND
    // are not currently advised.
    $rtn_list = new CourseList();
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      if ($course->course_list_fulfilled_by->is_empty == false)
      {
        // something IS fulfilling it!
        $rtn_list->add($course);

      } else if ($course->bool_advised_to_take == true)
      {
        // Was not being fulfilled, but, it was advised
        // to take.
        $rtn_list->add($course);
      }



    }

    $this->array_list = $rtn_list->array_list;
    $this->reset_counter();
  }


  /**
   * Removes courses from this list which have not been fulfilled
   * (ther course_list_fulfilled_by is empty).
   *
   */
  function remove_unfulfilled_courses()
  {
    // remove courses in THIS list
    // which have nothing in their course_list_fulfilled_by
    // object.
    $rtn_list = new CourseList();
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      if ($course->course_list_fulfilled_by->is_empty == false)
      {
        $rtn_list->add($course);
      }
    }

    $this->array_list = $rtn_list->array_list;
    $this->reset_counter();

  }

  
  /**
   * Returns a clone CourseList of $this.
   *
   * @param bool $bool_return_new_courses
   *         - If set to TRUE, it will create new Course objects
   *           based on the course_id's of the ones in $this's list.
   *           If set to FALSE, this will add the exact same Course
   *           objects by reference to the new list.
   * 
   * @return CourseList
   */
  function get_clone($bool_return_new_courses = FALSE)
  {
    // This will return a clone of this list.
    // If boolReturnNewCourses is true, then it will
    // return a new list of new instances of courses
    // from this list.
    $rtn_list = new CourseList();
    
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      
      if ($bool_return_new_courses)
      {
        $new_course = new Course();
        $new_course->course_id = $course->course_id;
        $rtn_list->add($new_course);
      } else {
        $rtn_list->add($course);
      } 
      
    } 
    
    return $rtn_list;
      
  }
  

  /**
   * Returns a CourseList of all the fulfilled or advised courses
   * in $this's list.
   *
   * @param bool $bool_return_new_courses
   *         - Works the same as get_clone()'s boolReturnNewCourses
   *           variable.
   * 
   * @return Course
   */
  function get_fulfilled_or_advised($bool_return_new_courses = false)
  {
    
    $rtn_list = new CourseList();
    
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      $add_course = $course;
      
      if ($bool_return_new_courses == true)
      {
        $add_course = new Course();
        $add_course->course_id = $course->course_id;
      }
      
      if ($course->bool_advised_to_take == true)
      {
        $rtn_list->add($add_course);
      }
      
      // Several ways to tell if a course is here by credit...
      if (!$course->course_list_fulfilled_by->is_empty) {
        $rtn_list->add($add_course);
      } 
      else if ($course->grade != "") {
        $rtn_list->add($add_course);
      } 
      else if ($course->get_bool_substitution() == true) {
        $rtn_list->add($add_course);
      }     
    }
    
    return $rtn_list;
    
  }
  
  /**
   * Returns the number of courses in this list which have either
   * been fulfilled or advised to take.  It does not count hours,
   * just the courses themselves.
   *
   * @return int
   */
  function count_fulfilled_or_advised()
  {
    // This function returns the number of courses in this
    // courseList which is either fulfilled or has been advised
    // to take.  It does care about hours, just the number of
    // courses themselves.
    $count = 0;
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      if ($course->bool_advised_to_take == true)
      {
        $count++;
      }
      
      // Several ways to tell if a course is here by credit...
      if (!$course->course_list_fulfilled_by->is_empty)
      {
        $count++;
      } else if ($course->grade != "") {
        $count++;
      } else if ($course->bool_substitution == true)
      {
        $count++;
      }     
    }
    
    return $count;
    
  }
  
  
  /**
   * Returns a CourseList of courses which have bool_advised_to_take == true.
   *
   * @return CourseList
   */
  function get_advised_courses_list()
  {
    // Return a courseList object of courses in THIS
    // list which have bool_advised_to_take == true.
    $rtn_list = new CourseList();
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      if ($course->bool_advised_to_take == true)
      {
        $rtn_list->add($course);
      }
    }

    return $rtn_list;

  }


  /**
   * Similar to count_hours, but this will only count courses
   * which have been taken and have a grade.
   * 
   * @param string $requirement_type
   *         - If set, we will only look for courses matching this requirement_type.
   * 
   * @param bool $bool_use_ignore_list
   * @param bool $bool_ignore_enrolled
   * @return int
   */
  function count_credit_hours($requirement_type = "", $bool_use_ignore_list = false, $bool_ignore_enrolled = false, $bool_qpts_grades_only = FALSE, $bool_exclude_all_transfer_credits = FALSE, $degree_id = 0)
  {
    // Similar to count_hours, but this will only
    // count courses which have been taken (have a grade).


    $count = 0;

    
    $qpts_grades = NULL;
    $retake_grades = $enrolled_grades = NULL;
    
    
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      
      // Does this course belong to the same degree we are interested in?  If not, skip it.
      if ($degree_id > 0) {
        if ($course->req_by_degree_id != $degree_id && $course->get_has_been_assigned_to_degree_id($degree_id) != TRUE) continue;
      }
      
      $school_id = $course->school_id;

      // Now that we have a school_id, let's figure out some variables....

      if (!$retake_grades || !$enrolled_grades) {
        $retake_grades = csv_to_array(variable_get_for_school("retake_grades", 'F,W,I', $school_id));
        $enrolled_grades = csv_to_array(variable_get_for_school("enrolled_grades",'E', $school_id));        
      }
      
      if (!$qpts_grades) {
        $tlines = explode("\n", variable_get_for_school("quality_points_grades", "A ~ 4\nB ~ 3\nC ~ 2\nD ~ 1\nF ~ 0\nI ~ 0", $school_id));
        foreach ($tlines as $tline) {
          $temp = explode("~", trim($tline));      
          if (trim($temp[0]) != "") {
            $qpts_grades[trim($temp[0])] = trim($temp[1]);
          }
        }        
      }
      
      
      
      
      if ($bool_use_ignore_list == true)
      {
        // Do ignore some courses...
        $temp_course_name = $course->subject_id . " " . $course->course_num;
        // Check in our settings to see if we should ignore this course
        // (configured in /custom/settings.php)
        if (in_array($temp_course_name, csv_to_array(@variable_get_for_school("ignore_courses_from_hour_counts", "", $school_id)))) {
          continue;
        }       

        // Also, if the course's requirement_type is "x" it means we should ignore it.
        if ($course->requirement_type == 'x') continue;
        
      }
      

      
      if ($bool_ignore_enrolled == true)
      {
        if (in_array($course->grade, $enrolled_grades)) {
          continue;
        }
      }

      // Only allowing grades which we have quality points for?
      if ($bool_qpts_grades_only) {
        if ($course->grade != "" && !isset($qpts_grades[$course->grade])) {
          continue;
        }
      }
      else {
        // Is this grade a "retake" grade?  If so, skip it.
        if (in_array($course->grade, $retake_grades)) continue;
      }

      
      // Correct the course's requirement type, if needed (remove the "u")
      $cr_type = $course->requirement_type;
      $cr_type = str_replace("u", "", $cr_type);
      
      
      if ($course->grade != "")// || !($course->course_list_fulfilled_by->is_empty))
      {       

                
        // Make sure we aren't trying to exclude any transfer credits.
        if ($bool_exclude_all_transfer_credits) {        
          if ($course->bool_transfer) {
            continue;
          }
          // Is this a requirement which has been fulfilled by a course?  And if so, is THAT course a transfer?
          if ($course->course_list_fulfilled_by->is_empty == false) {
            $cc = $course->course_list_fulfilled_by->get_first();
            if ($cc->bool_transfer) {
              continue;
            }
          }
        }       
        
        
        
        
                
        // If we require the grade to be a qpts_grade, then check that now.
        if ($bool_qpts_grades_only && !isset($qpts_grades[$course->grade])) {
          continue;
        }
        
        // Do our requirement types match?
        if ($requirement_type == "" || ($requirement_type != "" && $requirement_type == $cr_type))
        {
          $h = $course->get_hours();
          $count = $count + $h;
        } 
        
      } 
      else {

        // maybe it's a substitution?
        if ($requirement_type == "" || ($requirement_type != "" && $requirement_type == $cr_type))
        {
          if ($course->course_list_fulfilled_by->is_empty == false)
          {
            $cc = $course->course_list_fulfilled_by->get_first();
            if ($cc->get_bool_substitution())
            {
              
              // If we require the grade to be a qpts_grade, then check that now.
              if ($bool_qpts_grades_only && !isset($qpts_grades[$cc->grade])) {               
                continue;
              }
              

              // Make sure we aren't trying to exclude any transfer credits.
              if ($bool_exclude_all_transfer_credits && $cc->bool_transfer) {     
                //fpm($requirement_type);
                //fpm($cc);   
                continue;                           
              }               
              
              
              $h = $cc->get_substitution_hours();
                            
              
              if ($cc->bool_ghost_hour) {
                $h = 0;
              }
              
              $count = $count + $h;             
            }
          }
        }
        
      }
    }

    
        
    return $count;

  }

  

  /**
   * Similar to count_credit_hours, but this will only count courses
   * which have been taken and have a grade.  We will return back
   * a sum of their quality points.
   * 
   * @param string $requirement_type
   *         - If set, we will only look for courses matching this requirement_type.
   * 
   * @param bool $bool_use_ignore_list
   * @param bool $bool_ignore_enrolled
   * @return int
   */
  function count_credit_quality_points($requirement_type = "", $bool_use_ignore_list = false, $bool_ignore_enrolled = false, $bool_exclude_all_transfer_credits = FALSE, $degree_id = 0)
  {

    $points = 0;
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];

      // Does this course belong to the same degree we are interested in?  If not, skip it.
      if ($degree_id > 0) {
        if ($course->req_by_degree_id != $degree_id && $course->get_has_been_assigned_to_degree_id($degree_id) != TRUE) continue;
      }

      $school_id = $course->school_id;

      // Correct the course's requirement type, if needed (remove the "u")
      $cr_type = $course->requirement_type;
      $cr_type = str_replace("u", "", $cr_type);
      
      
      if ($bool_use_ignore_list == true)
      {
        // Do ignore some courses...
        $temp_course_name = $course->subject_id . " " . $course->course_num;
        // Check in our settings to see if we should ignore this course
        // (configured in /custom/settings.php)
        if (in_array($temp_course_name, csv_to_array(@variable_get_for_school("ignore_courses_from_hour_counts", "", $school_id)))) {
          continue;
        }       

        // Also, if the course's requirement_type is "x" it means we should ignore it.
        if ($course->requirement_type == 'x') continue;       
        
      }


      if ($bool_ignore_enrolled == true)
      {
        if ($course->is_completed() == false)
        {
          if ($course->course_list_fulfilled_by->is_empty)
          {
            continue;
          } else {
            if ($course->course_list_fulfilled_by->get_first()->is_completed() == false)
            {
              continue;
            }
          }
        }
      }

      if ($course->grade != "")
      {

        // Make sure we aren't trying to exclude any transfer credits.
        if ($bool_exclude_all_transfer_credits) {        
          if ($course->bool_transfer) {
            continue;
          }
          // Is this a requirement which has been fulfilled by a course?  And if so, is THAT course a transfer?
          if ($course->course_list_fulfilled_by->is_empty == false) {
            $cc = $course->course_list_fulfilled_by->get_first();
            if ($cc->bool_transfer) {
              continue;
            }
          }
        }       
                
        
        if ($requirement_type == "")
        {
          $p = $course->get_quality_points($degree_id);
          $points = $points + $p;
        } else {
          if ($cr_type == $requirement_type)
          {
            $p = $course->get_quality_points($degree_id);
            $points = $points + $p;
            continue;
          }

        }
      } 
      else {

        // maybe it's a substitution?
                
        
        if (($requirement_type == "") || ($requirement_type != "" && $requirement_type == $cr_type))
        {
          if ($course->course_list_fulfilled_by->is_empty == false)
          {
            $cc = $course->course_list_fulfilled_by->get_first();
            if ($cc->get_bool_substitution($degree_id))
            {
              
              
              // Make sure we aren't trying to exclude any transfer credits.
              if ($bool_exclude_all_transfer_credits) {
                if ($cc->bool_transfer) {
                 //fpm($course);
                 continue;
                }         
                
              }

              
              
              //$h = $cc->substitution_hours;
              
              //if ($cc->bool_ghost_hour) {
              //  $h = 0;
              //}
              
              // What are the quality points for this course?           
              $p = $cc->get_quality_points($degree_id);
              
              $points = $points + $p;
            }
          }

        }
      }
    }

    return $points;

  } 
  
  

  /**
   * Assign a groupID to every course in the list.
   *
   * @param int $group_id
   */
  function assign_group_id($group_id)
  {
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      //$course->assigned_to_group_id = $group_id;
      $course->assigned_to_group_ids_array[$group_id] = $group_id;
    }
  }


  /**
   * Assign a semesterNum to every course in the list.
   *
   * @param int $semester_num
   */
  function assign_semester_num($semester_num)
  {
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      $course->assigned_to_semester_num = $semester_num;
    }
  }

  /**
   * Sets the bool_has_been_assigned property of every course in
   * the list.
   *
   * @param bool $bool_has_been_assigned
   *         - What to set each course's->boolhasBeenAssigned property
   *           to.
   * 
   */
  function set_has_been_assigned($bool_has_been_assigned = true)
  {
    // Set the bool_has_been_assigned for all items
    // in this list.
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      $course->bool_has_been_assigned = $bool_has_been_assigned;
    }

  }


  /**
   * Set's each course's bool_substitution value.
   *
   * @param bool $bool_s
   *         - What to set each course's bool_substitution value to.
   */
  function set_bool_substitution($degree_id = 0, $bool_s = true)
  {
    // Set the bool_substitution for all items
    // in this list.
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      $course->set_bool_substitution($degree_id, $bool_s);
    }

  }


  /**
   * Set all the courses in this list to have the same "req_by_degree_id" value.
   */
  function set_req_by_degree_id($degree_id = 0)
  {
    // Set the bool_substitution for all items
    // in this list.
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      $course->req_by_degree_id = $degree_id;
    }

  }


  /**
   * Set all the courses in this list to have the same "requirement_type" value.
   */
  function set_requirement_type($requirement_type = "")
  {
    // Set the bool_substitution for all items
    // in this list.
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      $course->requirement_type = $requirement_type;
    }

  }





  /**
   * Sets each course's $course_substitution value to the supplied
   * Course object.
   *
   * @param Course $course_s
   * @param string $sub_remarks
   */
  function set_course_substitution(Course $course_s, $sub_remarks = "", $degree_id = 0)
  {
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      $course->set_course_substitution($degree_id, $course_s);
      $course->req_by_degree_id = $course_s->req_by_degree_id;  // match it up to the degree its being subbed for
      $course->sub_remarks = $sub_remarks;
    }

  }



  /**
   * Go through the list and decrement the specified_repeats
   * value for all instances of Course $course.
   *
   * @param Course $course
   */
  function dec_specified_repeats(Course $course, $bool_ignore_infinite_repeats = TRUE) {
    // Go through the list, and decrement the specified_repeats
    // value for all instances of $course.
    for ($t = 0; $t < $this->count; $t++)
    {
      $course2 = $this->array_list[$t];
      if ($course2->course_id == $course->course_id)
      {
          
        if ($bool_ignore_infinite_repeats && $course2->specified_repeats == Group::GROUP_COURSE_INFINITE_REPEATS) continue;
        
        $course2->specified_repeats--;
      }
    }

  }


  /**
   * Go through the list and set the specified_repeats value to $num
   * for all instances of $course.
   *
   * @param Course $course
   * @param int $num
   */
  function set_specified_repeats(Course $course, $num)
  {
    for ($t = 0; $t < $this->count; $t++)
    {
      $course2 = $this->array_list[$t];
      if ($course2->course_id == $course->course_id)
      {
        $course2->specified_repeats = $num;
        $course2->bool_specified_repeats = true;
      }

    }

  }


  /**
   * Removes excluded courses from the list (courses that
   * have db_exclude == 1)
   *
   */
  function remove_excluded()
  {
    // Removes courses from the list that have a db_exclude == 1.
    $new_list = new CourseList();
    // Do this by adding elements to an array.
    // course_id => index in list.
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      if ($course->subject_id == "")
      { // load descriptive data (include exclude info)
        $course->load_descriptive_data();
      }
      
      if ($course->db_exclude == 1)
      {
        continue;
      }

      $new_list->add($course);

    }

    $this->array_list = $new_list->array_list;
    $this->reset_counter();

  }



  /**
   * Remove occurances of the course in the course list.
   * 
   * If a limit is higher than zero, we will STOP after that limit has been reached.
   */
  function remove_course_with_course_id($course_id, $limit = 0) {
    
    // If the limit is 0, set it to an int's max, which is around 2 billion (32bit) or 9 quintillion (64 bit).
    // Either way, it's effectively infinite for FlightPath in this purpose.
    
    if ($limit == 0) $limit = PHP_INT_MAX;
    
    $new_list = new CourseList();
    $c = 0;  
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      if ($course == null)
      {
        continue;
      }
      
      if ($c < $limit && intval($course->course_id) == intval($course_id)) {
        // We found the course.  Since we do not want it to be part of the list,
        // we will skip adding it to the new_list.
        $c++;
        continue;
      }
      
      
      // Otherwise, let's add it to the new_list.
      $new_list->add($course);
    }

    // Switch over the reference.
    $this->array_list = $new_list->array_list;
    $this->reset_counter();      
    
  }


  /**
   * Removes all courses which are not assigned to this degree_id.
   */
  function remove_courses_not_in_degree($degree_id) {
      
    $new_list = new CourseList();  
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      if ($course == null)
      {
        continue;
      }
      
      if ($course->req_by_degree_id != $degree_id) {
        continue;
      }
      
      // Otherwise, let's add it to the new_list.
      $new_list->add($course);
    }

    // Switch over the reference.
    $this->array_list = $new_list->array_list;
    $this->reset_counter();      
        
  }
  
  


  /**
   * Removes null's and duplicate courses from the list.
   *
   */
  function remove_duplicates()
  {
    // Go through and remove duplicates from the list.
    // Also remove null's

    $tarray = array();
    $new_list = new CourseList();
    // Do this by adding elements to an array.
    // course_id => index in list.
    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      if ($course == null)
      {
        continue;
      }

      $tarray[$course->course_id] = -1;
    }

    for ($t = 0; $t < $this->count; $t++)
    {
      $course = $this->array_list[$t];
      if ($course == null)
      {
        continue;
      }

      //if (is_object($course->courseFulfilledBy))
      if (!($course->course_list_fulfilled_by->is_empty))
      {
        $tarray[$course->course_id] = $t;
        continue;
      }

      if ($tarray[$course->course_id]*1 < 0)
      {
        $tarray[$course->course_id] = $t;
      }

    }

    // Now, go through tarray and rebuild the newList.
    foreach($tarray as $course_id => $i)
    {
      $new_list->add($this->array_list[$i]);
    }

    // Switch over the reference.
    $this->array_list = $new_list->array_list;
    $this->reset_counter();

  }

} // end class CourseList

