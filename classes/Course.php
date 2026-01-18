<?php


/*
Class definition for the Course object.
*/

class Course extends stdClass
{
  // Some public variables and what they are used for.

  
  const COURSE_UNKNOWN_TERM_ID = 11111;
  
  
  // Database & misc related:
  public $random_id = 0, $db_advised_courses_id = 0;
  public $bool_placeholder, $db, $db_substitution_id_array, $db_unassign_transfer_id;
  public $db_exclude, $data_entry_comment, $array_index, $data_entry_value, $db_group_attributes;
  public $db_group_requirement_id;  // the id from the group_requirements table where this was specified.
  public $db_degree_requirement_id;  // the id from the degree_requirements table where this was specified.
  public $extra_attribs = '';

  // Course catalog data related:
  public $subject_id = '', $course_num = '', $course_id = 0, $requirement_type = '', $catalog_year = '';
  public $min_hours = 0, $max_hours = 0, $list_prereqs = '', $repeat_hours = 0;
  public $array_valid_names, $school_id = 0;

  // Student record related:
  public $bool_taken, $term_id = '', $section_number = '', $quality_points = 0, $grade = '', $db_grade = '', $level_code = '';
  public $bool_transfer, $institution_id = '', $institution_name = '', $course_transfer;
  public $transfer_eqv_text, $transfer_footnote;
  
  public $details_by_degree_array;  // meant to hold all details about a substitution, or anything else, keyed by degree id.
  
  // Major/Degree or Group Requirement related:
  public $min_grade = '', $specified_repeats = '', $bool_specified_repeat, $required_on_branch_id;
  public $assigned_to_semester_num = '', $appears_in_semester_nums, $req_by_degree_id;
  public $assigned_to_degree_ids_array, $assigned_to_group_ids_array;


  // advising & in-system logic related:
  public $advised_hours, $bool_selected, $bool_advised_to_take;
  public $course_list_fulfilled_by;
  public $bool_added_course, $group_list_unassigned;
  public $advised_term_id, $temp_old_course_id;
  public $bool_use_draft;


  // Display related:
  public $display_status = '', $icon_filename = '', $description = '', $title = '';
  public $title_text = '', $temp_flag, $disp_for_group_id;
  public $bool_unselectable;
  public $bool_hide_grade, $bool_ghost_hour, $bool_ghost_min_hour;
  public $unique_id = '';

  
/**
 * The constructor for a Course object.
 *
 * @param int $course_id
 *        - Numeric course_id of the course to try to load.  Leave blank
 *          if you simply wish to instantiate a course object.
 * 
 * @param bool $is_transfer
 *        - Is this course a transfer course?  Meaning, from another
 *          school.
 * 
 * @param DatabaseHandler $db
 * @param bool $is_blank
 * @param int $catalog_year
 *        - What catalog_year does this Course belong to?  This is
 *          used later when we call load_descriptive_data() to get its 
 *          description, hour count, etc.
 * 
 * @param bool $bool_use_draft
 */
  function __construct($course_id = "", $is_transfer = false, DatabaseHandler $db = NULL, $is_blank = false, $catalog_year = "", $bool_use_draft = false)
  {
    
    $this->advised_hours = -1;
        
    if ($is_blank == true)
    { // Do nothing if this is a "blank" course.
      return;
    }

    $array_valid_names = array();  // will hold all "valid" names for this course (non excluded names).
    $this->course_id = intval($course_id);  // Force it to be numeric.
    $this->appears_in_semester_nums = array();  // for combo-degrees
    $this->temp_old_course_id = 0;  // Used in case we delete the course_id, we can get it back (good with substitutions of transfers that are outdated).
    $this->catalog_year = $catalog_year;
    $this->assigned_to_semester_num = -1;
    //$this->assigned_to_group_id = 0;  // deprecated
    $this->assigned_to_group_ids_array = array();
    $this->bool_advised_to_take = false;
    $this->bool_added_course = false;
    $this->specified_repeats = 0;
    $this->bool_specified_repeat = false;    
    // Give this course instance a "random" numeric id, meaning, it doesn't really mean anything.
    // It used to actually be random, but we will use a simple increment instead to ensure it is unique.
    if (!isset($GLOBALS['fp_courses_random_ids'])) {
      $GLOBALS['fp_courses_random_ids'] = 10;
    }

    $this->random_id = $GLOBALS['fp_courses_random_ids']++;
    
    
    $this->display_status = "eligible";
    $this->course_list_fulfilled_by = new CourseList();
    $this->group_list_unassigned = new GroupList();
    $this->bool_use_draft = $bool_use_draft;
    $this->disp_for_group_id = "";
    $this->req_by_degree_id = 0;
    
    //$this->bool_has_been_displayed_by_degree_array = array();
    
    //$this->bool_substitution_by_degree_array = array();
    
    $this->db_substitution_id_array = array();
    //$this->course_substitution_by_degree_array = array();
    
    $this->details_by_degree_array = array();
    $this->details_by_degree_array[-1] = array();  // to keep notices from showing up.    


    $this->assigned_to_degree_ids_array = array();

    // Always override if the global variable is set.
    if (@$GLOBALS["fp_advising"]["bool_use_draft"] == true) {
      $this->bool_use_draft = true;
    }

    $this->db = $db;
    if ($db == NULL)
    {
      $this->db = get_global_database_handler();;
    }

    if ($course_id != "")
    {
      $this->load_course($course_id, $is_transfer);
    }
  }




  function set_bool_substitution_split($degree_id = 0, $val = '') {
    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
    
    $this->set_details_by_degree($degree_id, "bool_substitution_split", $val);
  }


  function get_bool_substitution_split($degree_id = 0) {

    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
    
    if ($degree_id > 0) {
      //return $this->bool_substitution_by_degree_array[$degree_id];
      return $this->get_details_by_degree($degree_id, "bool_substitution_split");
    }
    else {
      // Any degree?
      
      if ($this->get_count_details_by_degree("bool_substitution_split", TRUE) > 0) {
        return TRUE;
      }
    }
    
    return FALSE;
    
  }




  function set_bool_outdated_sub($degree_id = 0, $val = '') {
    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
    
    $this->set_details_by_degree($degree_id, "bool_outdated_sub", $val);
  }

  
  

  function get_bool_outdated_sub($degree_id = 0) {

    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
    
    if ($degree_id > 0) {
      //return $this->bool_substitution_by_degree_array[$degree_id];
      return $this->get_details_by_degree($degree_id, "bool_outdated_sub");
    }
    else {
      // how about for ANY degree?
      
      if ($this->get_count_details_by_degree("bool_outdated_sub", TRUE) > 0) {
        return TRUE;
      }
    }
    
    return FALSE;
    
  }







  function set_bool_substitution_new_from_split($degree_id = 0, $val = '') {
    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
    
    $this->set_details_by_degree($degree_id, "bool_substitution_new_from_split", $val);
  }


  function get_bool_substitution_new_from_split($degree_id = 0) {

    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
    
    if ($degree_id > 0) {
      //return $this->bool_substitution_by_degree_array[$degree_id];
      return $this->get_details_by_degree($degree_id, "bool_substitution_new_from_split");
    }
    else {
      // how about for ANY degree?
      
      if ($this->get_count_details_by_degree("bool_substitution_new_from_split", TRUE) > 0) {
        return TRUE;
      }
    }
    
    return FALSE;
    
  }






  function set_substitution_hours($degree_id = 0, $val = '') {
      
    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
    
    $this->set_details_by_degree($degree_id, "substitution_hours", $val);
    
  }

  /**
   * Similar to the functions regarding display-- get the course substitution based on supplied degree_id.
   * Set degree_id to -1 to just get the first one, if available.
   */
  function get_substitution_hours($degree_id = 0) {
    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;

    if ($degree_id > 0) {      
      return $this->get_details_by_degree($degree_id, "substitution_hours");
    }
    else {
      if (isset($this->details_by_degree_array[$degree_id]["substitution_hours"])) {      
        $x = $this->details_by_degree_array[$degree_id]["substitution_hours"];
        if ($x) return $x;
      }
    }
    
    // Else, return boolean FALSE
    return FALSE;
    
    
  }



  function set_hours_awarded($degree_id = 0, $val = 0) {
    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
        
    $this->set_details_by_degree($degree_id, "hours_awarded", floatval($val)*1);  // *1 to force to a number and to trim extra zeroes.
    
  }



  function set_course_substitution($degree_id = 0, Course $course = NULL) {
    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
    
    //$this->course_substitution_by_degree_array[$degree_id] = $course;
    $this->set_details_by_degree($degree_id, "course_substitution", $course);
    
  }


  /**
   * Similar to the functions regarding display-- get the course substitution based on supplied degree_id.
   * Set degree_id to -1 to just get the first one, if available.
   */
  function get_course_substitution($degree_id = 0) {
    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;

    if ($degree_id > 0) {
      //return $this->course_substitution_by_degree_array[$degree_id];
      return $this->get_details_by_degree($degree_id, "course_substitution");
    }
    else {
      //$x = reset($this->course_substitution_by_degree_array);
      if (isset($this->details_by_degree_array[$degree_id]["course_substitution"])) {
        $x = reset($this->details_by_degree_array[$degree_id]["course_substitution"]);
        if ($x) return $x;
      }
    }
    
    // Else, return boolean FALSE
    return FALSE;
    
    
  }


  /**
   * If the boolean is set, it means if the supplied degree_id isn't set, then use the first found value.
   */
  function get_hours_awarded($degree_id = 0, $bool_use_first_found_if_not_found_by_degree = TRUE) {
    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;

    if ($degree_id > 0) {
      //return $this->course_substitution_by_degree_array[$degree_id];
      if (isset($this->details_by_degree_array[$degree_id]["hours_awarded"])) {
        $x = floatval($this->get_details_by_degree($degree_id, "hours_awarded")) * 1;  // *1 forces numeric and trimes extra zeroes.
      }
      else if ($bool_use_first_found_if_not_found_by_degree) {
        // It wasn't set, so get the first value that WAS set.
        $x = floatval($this->get_first_value_from_any_degree("hours_awarded"));
      }
      if ($x) return $x;                  
                  
    }
    else {
      
      // We just want the first value of ANY degree returned.
      $x = floatval($this->get_first_value_from_any_degree("hours_awarded"));      
      if ($x) return $x;
      
    }
    
    // Else, return zero
    return 0;
    
    
  }

  
  /**
   * Goes through the details_by_degree array and returns the first 
   * valid value for the supplied key, any degree., or return NULL if not found.
   */
  function get_first_value_from_any_degree($key) {
    
    foreach ($this->details_by_degree_array as $d => $v) {
      if (isset($v[$key])) return $v[$key];
    }
    
    // Found nothing, return null
    return NULL;
  }





  /**
   * Similar to the functions regarding display, these will say if this course has been used in a substitution
   * for a particular degree.
   */
  function get_bool_substitution($degree_id = 0) {

    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
    
    if ($degree_id > 0) {
      //return $this->bool_substitution_by_degree_array[$degree_id];
      return $this->get_details_by_degree($degree_id, "bool_substitution");
    }
    else {
      // has the course been substituted by ANY degree?      
      if ($this->get_count_details_by_degree("bool_substitution", TRUE) > 0) {        
        return TRUE;
      }
    }
    
    return FALSE;
    
  }

  /**
   * Return back a count from ANY degree for this property name.
   */
  function get_count_details_by_degree($property_name, $check_for_specific_value = NULL) {
    $c = 0;
    foreach ($this->details_by_degree_array as $degree_id => $temp) {
      if (isset($temp[$property_name])) {
          
        if ($check_for_specific_value !== NULL) {
          // We want to SKIP if this property does not have this specific value
          if ($temp[$property_name] !== $check_for_specific_value) continue;
        }  
        
        $c++;
      }
    }
    return $c;
  }



  function set_bool_substitution($degree_id = 0, $val = TRUE) {

    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
    
    //$this->bool_substitution_by_degree_array[$degree_id] = $val;
    $this->set_details_by_degree($degree_id, "bool_substitution", $val);
    
  }





  /**
   * Returns TRUE or FALSE if this course has been displayed.  Specify a degree_id to be more specific.
   * Use -1 to mean "ANY" degree?
   */
  function get_has_been_displayed($degree_id = 0) {
    
    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
    
    if ($degree_id > 0) {
      //return $this->bool_has_been_displayed_by_degree_array[$degree_id];
      return $this->get_details_by_degree($degree_id, "bool_has_been_displayed");
    }
    else {
      // has the course been displayed by ANY degree?
      if ($this->get_count_details_by_degree("bool_has_been_displayed", TRUE) > 0) {
        return TRUE;
      }
    }
    
    return FALSE;
  }

  
  
  /**
   * Counterpart to get_has_been_displayed.
   * @see get_has_been_displayed()
   */
  function set_has_been_displayed($degree_id = 0, $val = TRUE) {

    // If degree_id is zero, then use the course's currently req_by_degree_id.
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
    
    //$this->bool_has_been_displayed_by_degree_array[$degree_id] = $val;
    $this->set_details_by_degree($degree_id, "bool_has_been_displayed", $val);
  }



  function set_bool_exclude_repeat($degree_id = 0, $val = TRUE) {
    // If degree_id is zero, then use the course's currently req_by_degree_id.
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
    
    //$this->bool_has_been_displayed_by_degree_array[$degree_id] = $val;
    $this->set_details_by_degree($degree_id, "bool_exclude_repeat", $val);    
  }


  /**
   * Returns TRUE or FALSE if this course has been marked as exclude_repeat.  Specify a degree_id to be more specific.
   * Use -1 to mean "ANY" degree?
   */
  function get_bool_exclude_repeat($degree_id = 0) {
    
    // If degree_id is zero, then use the course's currently req_by_degree_id.    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
    
    if ($degree_id > 0) {
      //return $this->bool_has_been_displayed_by_degree_array[$degree_id];
      return $this->get_details_by_degree($degree_id, "bool_exclude_repeat");
    }
    else {
      // has the course been displayed by ANY degree?
      if ($this->get_count_details_by_degree("bool_exclude_repeat", TRUE) > 0) {
        return TRUE;
      }
    }
    
    return FALSE;
  }




  
  /**
   * Convenience function to set a value into our details_by_degree_array.
   */
  function set_details_by_degree($degree_id, $key, $val) {
    $this->details_by_degree_array[$degree_id][$key] = $val;
  }

  /**
   * @see set_details_by_degree()
   */
  function get_details_by_degree($degree_id, $key) {
    return @$this->details_by_degree_array[$degree_id][$key];
  }


  /**
   * This function will create a "data string" of the course.
   * Think of it as a poor man's serialize.  I can't actually use
   * serialize, as I have to call this for every course on the screen,
   * and the page load time was too long when using serialize, probably
   * because of all the extra fields which I did not need.
   * 
   * The string returned will be used to send information about this
   * course to a popup window.
   * 
   * Important details about the course are put into a particular order,
   * separated by commas.  Booleans are converted to either 1 or 0.
   * 
   * This function is the mirror of load_course_from_data_string().
   *
   * @return string
   */
  function to_data_string()
  {
    $rtn = "";

    $rtn .= $this->course_id . "~";
    $rtn .= $this->assigned_to_semester_num . "~";
    $rtn .= fp_join_assoc($this->assigned_to_group_ids_array) . "~";
    $rtn .= intval($this->bool_advised_to_take) . "~";
    $rtn .= $this->specified_repeats . "~";
    $rtn .= intval($this->bool_specified_repeat) . "~";
    
    $grd = (string) $this->grade;
    if (strstr($grd, "+")) {
      $grd = str_replace("+", "_pls_", $grd);
    }
    
    $rtn .= $grd . "~";
    
    $rtn .= $this->get_hours_awarded() * 1 . "~";
    $rtn .= $this->term_id . "~";
    $rtn .= $this->advised_hours * 1 . "~";

    $rtn .= intval($this->bool_transfer) . "~";

    // If this is a transfer, then we will put in various information
    // about the original transfer course...
    if ($this->bool_transfer == true)
    {
      $rtn .= $this->course_transfer->course_id . "~";
    } else {
      // Just enter blank.
      $rtn .= "~";
    }

    $rtn .= intval($this->bool_added_course) . "~";
    $rtn .= $this->db_advised_courses_id . "~";
    $rtn .= $this->random_id . "~";

    $rtn .= fp_join_assoc($this->get_degree_details_data_string("bool_substitution")) . "~";
    // If this is a substitution, what is the original requirement?
    if ($this->get_bool_substitution(-1))
    {
      // Create a simple assoc array for our course substitutions, where all we need to keep track of
      // is their course_id, not the entire course object.
      
      $temp = $this->get_degree_details_data_string("course_substitution");
      
      $arr = array();
      foreach ($temp as $key => $c) {
        $arr[$key] = $c->course_id;
      }
            
      //$rtn .= $this->course_substitution->course_id . "," . $this->req_by_degree_id . "~";
      $rtn .= fp_join_assoc($arr) . "~";
      
    } 
    else {
      // Just enter blank.
      $rtn .= "~";
    }
    ///////////////////


    $rtn .= fp_join_assoc($this->db_substitution_id_array) . "~";
    $rtn .= floatval($this->min_hours)  . "~";
    $rtn .= floatval($this->max_hours)  . "~";

    //$rtn .= intval($this->get_bool_substitution_new_from_split()) . "~";
    $rtn .= fp_join_assoc($this->get_degree_details_data_string("bool_substitution_new_from_split")) . "~";
    //$rtn .= intval($this->get_bool_substitution_split()) . "~";
    $rtn .= fp_join_assoc($this->get_degree_details_data_string("bool_substitution_split")) . "~";
    
    // no longer used
    //$rtn .= intval($this->bool_has_been_assigned) . "~";
    $rtn .= "0~";  // temporary just to keep place.  Should probably just be removed.
    
    
    $rtn .= $this->display_status . "~";
    
    $rtn .= intval($this->bool_ghost_hour) . "~";
    
    $rtn .= fp_join_assoc($this->assigned_to_degree_ids_array) . "~";

    $rtn .= $this->req_by_degree_id . "~";
    $rtn .= $this->disp_for_group_id . "~";
    $rtn .= $this->school_id . "~";
    $rtn .= $this->db_grade . "~";
    $rtn .= $this->extra_attribs . "~";
    $rtn .= $this->unique_id . "~";    

    return $rtn;
  }


  /**
   * Returns back an array from our details_by_degree array, for a given property name,
   * like:  $arr[DEGREE_ID] = $val
   * Useful in the to_data_string function
   */
  function get_degree_details_data_string($property_name) {
    $arr = array();
    
    foreach ($this->details_by_degree_array as $degree_id => $temp) {
      if (isset($temp[$property_name])) {  
        $arr[$degree_id] = $temp[$property_name];
      }
    }
    
    return $arr;
  }

  /**
   * This is the reverse function of:
   * @see get_degree_details_data_string
   */
  function set_degree_details_from_data_array($arr, $property_name) {
    foreach ($arr as $degree_id => $val) {
      $this->details_by_degree_array[$degree_id][$property_name] = $val;
    }
  }


  /**
   * This function uses load_course_from_data_string, but it removes any elements which might interfere with
   * the course object then being used as a requirement.  This is used specifically by the FlightPath object,
   * when creating a clone of a repeatable course.
   */
  function load_course_from_data_string_for_requirement_clone($str) {
      
    // Begin by loading THIS course from the supplied data string.  
    $this->load_course_from_data_string($str);
    
    
    // Now, let's overwrite the values we don't need, since this is to be used as
    // a course requirement.
    
    $this->random_id = $GLOBALS['fp_courses_random_ids']++;
    
    $this->set_hours_awarded(0, 0);
    $this->grade = "";
    $this->advised_hours = 0;
    $this->bool_added_course = FALSE;
    $this->db_advised_courses_id = 0;
    $this->details_by_degree_array = array();
    $this->assigned_to_degree_ids_array = array();
    $this->db_substitution_id_array = array();
    $this->display_status = "eligible";  // match the default constructor so we don't cause issues.    
    $this->disp_for_group_id = "";
    
    
  } 


  /**
   * This will take a data string, as created by
   * the function to_data_string(), and make $this object
   * match the original object.  It is a poor man's
   * unserialize.  See to_data_string()'s description for a fuller
   * picture of what is going on.
   * 
   * To use:  
   *  - $newCourse = new Course();
   *  - $newCourse->load_course_from_data_string($data);
   *           
   *
   * @param string $str
   */
  function load_course_from_data_string($str)
  {
    $temp = explode("~",$str);

    $this->course_id =        $temp[0];

    $this->load_course($this->course_id);

    $this->assigned_to_semester_num =   $temp[1];
    $this->assigned_to_group_ids_array  =   fp_explode_assoc($temp[2]);
    
    $this->bool_advised_to_take   =     (bool) $temp[3];
    $this->specified_repeats    =   $temp[4];
    $this->bool_specified_repeat  =   (bool) $temp[5];
    $this->grade          =   $temp[6];
    
    if (strstr($this->grade, "_pls_")) {
      $this->grade = str_replace("_pls_", "+", $this->grade);
    }
    
    
    $this->set_hours_awarded(0,floatval($temp[7]) * 1);  // *1 to force numeric, and trim extra zeros.
    $this->term_id        =   $temp[8];
    $this->advised_hours      = $temp[9] * 1;

    $this->bool_transfer    =   (bool) $temp[10];

    // Was this a transfer course?
    if ($this->bool_transfer == true)
    {
      $t_course = new Course($temp[11], true);
      $t_course->term_id = $this->term_id;
      $this->course_transfer = $t_course;
    }

    $this->bool_added_course    =   (bool) $temp[12];
    $this->db_advised_courses_id  =   $temp[13];
    $this->random_id        =   $temp[14];

    //$this->bool_substitution_by_degree_array    =   fp_explode_assoc($temp[15]);
    $this->set_degree_details_from_data_array(fp_explode_assoc($temp[15]), "bool_substitution");

    // Was this a substitution course?
    if (trim($temp[16]) != "")
    {
      $arr = fp_explode_assoc($temp[16]);
      
      foreach ($arr as $did => $cid) {
        $t_course = new Course($cid); // original course requirement.
        $t_course->req_by_degree_id = $did;  
        
        $this->set_course_substitution($did, $t_course);        
      }
      
      /*
      $temp2 = explode(",", $temp[16]);  // contains course_id,req_by_degree_id.  Need to split the values up.
      $t_course = new Course($temp2[0]); // original course requirement.
      $t_course->req_by_degree_id = $temp2[1];  
      
      $this->course_substitution = $t_course;
       */
    }

    $this->db_substitution_id_array   =   fp_explode_assoc($temp[17]);
    
    $this->min_hours        =   $temp[18] * 1;
    $this->max_hours        =   $temp[19] * 1;

    //$this->bool_substitution_new_from_split =   (bool) $temp[20];
    $this->set_degree_details_from_data_array(fp_explode_assoc($temp[20]), "bool_substitution_new_from_split");    
    
    //$this->bool_substitution_split  =   (bool) $temp[21];
    $this->set_degree_details_from_data_array(fp_explode_assoc($temp[21]), "bool_substitution_split");
    
    // No longer used.  Using assigned_to_degree_ids instead.
    //$this->bool_has_been_assigned =   (bool) $temp[22];
    $throw_away = $temp[22];  // throw-away value.  Can probably just remove entirely.
        
    $this->display_status =   $temp[23];

    $this->bool_ghost_hour  =   (bool) $temp[24];

    $this->assigned_to_degree_ids_array = fp_explode_assoc($temp[25]);

    $this->req_by_degree_id = intval($temp[26]);

    $this->disp_for_group_id = trim($temp[27]);
    $this->school_id = trim($temp[28]);
    $this->db_grade = trim($temp[29]);
    $this->extra_attribs = trim($temp[30]);
    $this->unique_id = trim($temp[31]);

  }


  /**
   * This function will return a CSV string of all the possible
   * names for this course, in alphabetical order.
   * 
   * This function is used by DataEntry primarily.
   *
   * @param bool $bool_add_white_space
   * @param bool $bool_add_exclude
   * @return string
   */
  function get_all_names($bool_add_white_space = false, $bool_add_exclude = true)
  {
    $rtn = "";

    $used_array = array();

    $table_name = "courses";
    if ($this->bool_use_draft) {$table_name = "draft_$table_name";}
    // took out: and `catalog_year`='$this->catalog_year'
    // because we don't care what catalog year it comes from...
    $res = $this->db->db_query("SELECT * FROM $table_name
            WHERE course_id = ?
            AND delete_flag = '0' 
            ORDER BY subject_id, course_num ", $this->course_id);
    while($cur = $this->db->db_fetch_array($res))
    {

      if (in_array($cur["subject_id"] . "~" . $cur["course_num"], $used_array))
      { // skip ones we have already seen.
        continue;
      }

      $used_array[] = $cur["subject_id"] . "~" . $cur["course_num"];

      $rtn .= $cur["subject_id"] . " " . $cur["course_num"];

      if ($cur["exclude"] != '0' && $bool_add_exclude == true)
      {
        $rtn .= " exclude";
      }

      $rtn .= ",";
      if ($bool_add_white_space == true)
      {
        $rtn .= " ";
      }
    }

    $rtn = trim($rtn);
    // remove last comma.
    $rtn = substr($rtn,0,-1);

    return $rtn;
  }


  /**
   * The function returns either an integer of the the number of
   * hours the course is worth, or, a range in the form of 
   * min-max (if the course has variable hours)
   * 
   * Examples: 3 or 1-6
   *
   * @return string
   */
  function get_catalog_hours()
  {
    
    if (!$this->has_variable_hours())
    {
      // Normal course, no var hours.
      $h = $this->min_hours * 1;
      // check for ghost hours.
      if ($this->bool_ghost_min_hour) {
        $h = 0;
      }
      return $h;
    } else {
      // Meaning this does course have variable hours.

      $min_h = $this->min_hours*1;
      $max_h = $this->max_hours*1;
      
      
      // Convert back from ghosthours.
      if ($this->bool_ghost_min_hour) {
        $min_h = 0;
      }
      
      if ($this->bool_ghost_hour) {
        $max_h = 0;
      }    
        
      
      return "$min_h-$max_h";
    }
  }




  /**
   * Returns how many hours this course has been advised for.
   * This is used with courses which have variable hours.  If
   * the course has not been advised for any particular number
   * of hours, then it's min_hours are returned.
   *
   * @return unknown
   */
  function get_advised_hours()
  {
    if ($this->advised_hours > -1)
    {
      return $this->advised_hours * 1;
    } else {
      // No, the user has not selected any hours yet.  So,
      // just display the min_hours.
      
      // Correct for ghost hours, if any.
      $min_h = $this->min_hours * 1;
      if ($this->bool_ghost_min_hour) {
        $min_h = 0;
      }
      
      return $min_h;
    }

  }

  /**
   * This will assign the $this->display_status string
   * based on the grade the student has made on the course.
   * The display_status is used by other display functions to decide
   * what color the course should show up as.
   *
   */
  function assign_display_status()
  {
    // Assigns the display status, based on grade.
    $grade = $this->grade;
    // Get these grade definitions from our system settings
    // Configure them in custom/settings.php
    $retake_grades = csv_to_array(variable_get_for_school("retake_grades", 'F,W,I', $this->school_id));
    $enrolled_grades = csv_to_array(variable_get_for_school("enrolled_grades",'E', $this->school_id));


    if (in_array($grade, $retake_grades))
    {
      $this->display_status = "retake";
    }

    if (in_array($grade, $enrolled_grades))
    {
      $this->display_status = "enrolled";
    }
  }

  
  /**
   * Returns TRUE if the student has completed the course 
   * (and did not make a failing grade on it).
   * 
   * 
   *
   * @return bool
   */  
  function is_completed()
  {
    // returns true if the course has been completed.
    $grade = $this->grade;

    // Get these grade definitions from our system settings
    // Configure them in custom/settings.php
    $retake_grades = csv_to_array(variable_get_for_school("retake_grades", 'F,W,I', $this->school_id));
    $enrolled_grades = csv_to_array(variable_get_for_school("enrolled_grades",'E', $this->school_id));
    
    if ($grade == "") {
      return false;
    }

    if (in_array($grade, $enrolled_grades)) {
      return false;
    }

    if (in_array($grade, $retake_grades)) {
      return false;
    }

    return true;

  }


  /**
   * Does $this meed the minimum grade requirement of the
   * supplied course requirement?  You may specify either
   * a Course object, or just enter the min_grade in the mGrade
   * variable.
   *
   * @param Course $course_req
   *      - The Course object who has the min grade requirement.
   *        Set to NULL if using $m_grade.
   * 
   * @param string $m_grade
   *      - The min grade which $this must meet.  Do not use if using
   *        $course_req.
   * 
   * @return bool
   */
  function meets_min_grade_requirement_of(Course $course_req = NULL, $m_grade = "", $bool_exclude_W_and_F = TRUE)
  {
    // Does $this course meet the min grade requirement
    // of the supplied course requirement?

    $m_grade = strtoupper($m_grade);
    
    // Get these grade definitions from our system settings
    // Configure them in custom/settings.php    
    $enrolled_grades = csv_to_array(variable_get_for_school("enrolled_grades", 'E', $this->school_id));
    $retake_grades = csv_to_array(variable_get_for_school("retake_grades",'', $this->school_id));
    $withdrew_grades = csv_to_array(variable_get_for_school("withdrew_grades", "W", $this->school_id));
    
    $min_grade = '';
    if ($course_req != null) {
      $min_grade = strtoupper($course_req->min_grade);
    } else {
      $min_grade = $m_grade;
    }



    if ($min_grade == "")
    { // There is no min grade requirement for this course.
      return true;
    }


    // If the student is currently enrolled, return true.
    if (in_array($this->grade, $enrolled_grades))
    {
      return true;
    }
    
    if ($bool_exclude_W_and_F) {
      if (in_array($this->grade, $retake_grades) || in_array($this->grade, $withdrew_grades)) {
        return FALSE;
      }
    }
    
    // Okay, let's check those min grade requirements...
    $grade_order = csv_to_array(strtoupper(variable_get_for_school("grade_order", "E,AMID,BMID,CMID,DMID,FMID,A,B,C,D,F,W,I", $this->school_id)));    
  
    // Make it so the indexes are the grades, their numeric values are values.    
    $grade_order = array_flip($grade_order);
      
    // Get the "weight" of the min_grade_requirement, and $this->grade
    $req_weight = intval(@$grade_order[$min_grade]);
    $this_weight = intval(@$grade_order[$this->grade]);  


    
    
    if ($this_weight <= $req_weight) return TRUE;  // yay, we have the min grade!


    return false;
  }


  /**
   * Simply returns TRUE if $this has variable hours.
   *
   * @return bool
   */
  function has_variable_hours()
  {
    
    $min_h = $this->min_hours;
    $max_h = $this->max_hours;
    
    
    // Convert back from ghosthours, for the comparison.
    if ($this->bool_ghost_min_hour) {
      $min_h = 0;
    }
    
    if ($this->bool_ghost_hour) {
      $max_h = 0;
    }
    
    
    if ($min_h == $max_h)
    {
      return false;
    } else {
      return true;
    }
  }


  /**
   * Figure out the number of hours this particular
   * instance of the course is worth.  In the case
   * of variable hours, it will return the number
   * of hours selected.  If that does not exist,
   * it will return the MIN HOURS.
   *
   * @return int
   */
  function get_hours($degree_id = 0)
  {

    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;

    // This course might be set to 1 hour, but be a "ghost hour",
    // meaning the student actually earned 0 hours, but we recorded 1
    // to make FP's math work out.  So, let's return back 0 hours.
    if ($this->bool_ghost_hour)
    {
      $h = 0;
      return $h;
    }
    
    // Was this course used in a substitution?  If so, use the substitution hours.
    if ($this->get_substitution_hours($degree_id) > 0) {
      return floatval($this->get_substitution_hours($degree_id));
    }
    
       
    // Do they have any hours_awarded? (because they completed
    // the course)
    if ($this->get_hours_awarded($degree_id) > 0)
    {
      $h = $this->get_hours_awarded($degree_id);
      return floatval($h);
    }

    
    if ($this->has_variable_hours() && $this->advised_hours > -1) {
      return floatval($this->advised_hours);
    }
    

    // No selected hours, but it's a variable hour course.
    // So, return the min_hours for this course.
    return floatval($this->min_hours);

  }


  
  /**
   * Calculate the quality points for this course's grade and hours.
   *
   * @param string $grade
   * @param int $hours
   * @return int
   */
  function get_quality_points($degree_id = 0){

    $hours = $this->get_hours($degree_id);
    $grade = $this->grade;
    
    $pts = 0;
    $qpts_grades = array();
    
    // Let's find out what our quality point grades & values are...
    if (isset($GLOBALS["qpts_grades"])) {
      // have we already cached this?
      $qpts_grades = $GLOBALS["qpts_grades"];
    } 
    else {
      $tlines = explode("\n", variable_get_for_school("quality_points_grades", "A ~ 4\nB ~ 3\nC ~ 2\nD ~ 1\nF ~ 0\nI ~ 0", $this->school_id));
      foreach ($tlines as $tline) {
        $temp = explode("~", trim($tline));      
        if (trim($temp[0]) != "") {
          $qpts_grades[trim($temp[0])] = trim($temp[1]);
        }
      }
    
      $GLOBALS["qpts_grades"] = $qpts_grades;  // save to cache
    }
    
    // Okay, find out what the points are by multiplying value * hours...
    
    if (isset($qpts_grades[$grade])) {
     $pts = $qpts_grades[$grade] * $hours;
    }
    
    
    return $pts;

  }  
  
  
  
  
  
  
  /**
   * This function is used for comparing a course name to the subject_id
   * and course_num of $this.  
   * We expect a space between the subject_id and CourseNum in $str.
   * 
   * For example: MATH 1010
   * 
   * You may also ONLY specify a subject, ex: BIOL.  If you do that,
   * then only the subject will be compared.
   * 
   * Example of use:  if ($c->name_equals("ART 101")) then do this etc.
   *
   * @param string $str
   * @return bool
   */
  function name_equals($str)
  {
    // We expect the str to be given to us
    // with a space b/t the subject_id and course_num.
    // ex:  MATH 111
    // may also ONLY specify the subject. ex:  BIOL

    // If our $subject_id is not populated, then call load_descriptive_data()
    if ($this->subject_id == "") {
      $this->load_descriptive_data();
    }
    
    // TODO: We should check ALL names for this course. Use get_all_names to do that.
    
    
    $temp = explode(" ",$str);
    if ($this->subject_id == $temp[0] && ($this->course_num == $temp[1] || trim($temp[1]) == ""))
    {
      return TRUE;
    }

    return FALSE;

  }

  
  /**
   * Convienience function.  Simply compare the course_id of
   * another course to $this to see if they are equal.
   * 
   * This is also used by CourseList and ObjList to determine
   * matches.
   * 
   * Usage:  if ($newCourse.equals($otherCourse)) { ... }
   *
   * @param Course $course_c
   * @return bool
   */
  function equals(Course $course_c = null)
  {
    if ($course_c != null && $this->course_id == $course_c->course_id)
    {
      return true;
    }

    return false;
  }

  
  
  
    

  /**
   * Loads $this as a new course, based on course_id.
   *
   * @param int $course_id
   * @param bool $is_transfer
   */
  function load_course($course_id, $is_transfer = false)
  {

    if ($this->db == NULL)
    {
      $this->db = get_global_database_handler();
    }


    $catalog_line = "";
    if ($this->catalog_year != "") {
      $catalog_line = " AND catalog_year = '$this->catalog_year' ";      
    }

    if ($is_transfer == false) {      
      $this->load_descriptive_data();
    } 
    else {
      // This is a transfer course.  
      
      
      $res = $this->db->db_query("SELECT * FROM
                    transfer_courses a,
                    transfer_institutions b
                    WHERE 
                     a.transfer_course_id = '?' 
                     AND a.institution_id = b.institution_id ", $course_id);
      $cur = $this->db->db_fetch_array($res);
      if ($cur) {
        $this->subject_id = $cur["subject_id"];
        $this->course_num = $cur["course_num"];      
        $this->course_id = $course_id;
        $this->school_id = $cur['school_id'];
        $this->bool_transfer = true;
        $this->institution_id = $cur["institution_id"];
        $this->institution_name = $cur["name"];
      }
    }

    $this->assign_display_status();
    
    
        
    
    // When we load this course, let's also check for any hooks.
    // Since this class might be used outside of FP, only do this if we know
    // that the bootstrap.inc file has been executed.
    if ($GLOBALS["fp_bootstrap_loaded"] == TRUE) {      
      invoke_hook("course_load", array(&$this));
    }         
    
    
    
    
  } // load_course


  /**
   * This function will correct capitalization problems in course titles.
   * 
   * @param string $str
   * 
   * @return string
   * 
   */
  function fix_title($str = "")
  {

    if ($str == "")
    {
      $str = $this->title;
    }
        
    // Should we do this at all?  We will look at the "autocapitalize_course_titles" setting.
    $auto = variable_get_for_school("autocapitalize_course_titles", 'yes', $this->school_id);
    if ($auto == "no") {                    
      // Nope!  Just return.
      $this->title = $str;
      return $str;
    }
    
    // Otherwise, we may continue with the capitalization scheme:
   
    $str = str_replace("/", " / ", $str);
    $str = str_replace("/", " / ", $str);
    $str = str_replace("-", " - ", $str);
    $str = str_replace(":", ": ", $str);
    $str = str_replace("(", "( ", $str);

    // Only pad an ampersand if we are not talking about
    // an HTML character.
    if (!strstr($str,"&#"))
    {
      $str = str_replace("&", " & ", $str);
    }

    // Let's also get rid of extra spaces.
    $str = str_replace("   ", " ", $str);
    $str = str_replace("  ", " ", $str);

    // convert to ucwords and fix some problems introduced by that.
    $str = trim(ucwords(strtolower($str)));
    
    $str = str_replace("Iii", "III", $str);
    $str = str_replace("Ii", "II", $str);
    $str = str_replace(" Iv"," IV",$str);
    $str = str_replace(" Vi"," VI",$str);
    $str = str_replace(" Of "," of ",$str);
    $str = str_replace(" The "," the ",$str);
    $str = str_replace(" In "," in ",$str);
    $str = str_replace(" And "," and ",$str);
    $str = str_replace(" An "," an ",$str);
    $str = str_replace(" A "," a ",$str);
    $str = str_replace(" To "," to ",$str);
    $str = str_replace(" For "," for ",$str);

    // Strange words and abreviations which should be changed.
    $str = str_replace("Afrotc","AFROTC",$str);
    $str = str_replace("Gis","GIS",$str);
    $str = str_replace("Dna","DNA",$str);
    $str = str_replace(" Cpr","CPR",$str);
    $str = str_replace(" Rn"," RN",$str);
    $str = str_replace(" Micu"," MICU",$str);
    $str = str_replace(" Sicu"," SICU",$str);
    $str = str_replace(" Picu"," PICU",$str);
    $str = str_replace(" Nicu"," NICU",$str);
    $str = str_replace("Uas ","UAS ",$str);
    $str = str_replace(" Uas"," UAS",$str);


    // Cleanup
    $str = str_replace("( ", "(", $str);
    $str = str_replace(" - ", "-", $str);


    // Is this just a course name by itself?  If so, it should
    // all be capitalized.
    $temp = explode(" ", $str);

    if (count($temp) == 2
    && strlen($temp[0]) <= 4
    && strlen($temp[1]) <= 4)
    {// We could also test to see if there are numbers starting the
      // second token.
      $str = strtoupper($str);
    }

    // If this contains the word "formerly" then we need to pull out what's
    // there and make it all uppercase, except for the word Formerly.
    if (strstr(strtolower($str), strtolower("formerly "))) 
    {

      $formline = preg_replace("/.*\((formerly .*)\).*/i", "$1", $str);
      $str = str_replace($formline, strtoupper($formline), $str);
      $str = str_replace("FORMERLY ", "Formerly ", $str);
    }
    

    $this->title = $str;

    return $str;
  }

  
  /**
   * This function will load $this will all sorts of descriptive data
   * from the database.  For example, hours, title, description, etc.
   * 
   * It must be called before any attempts at sorting (by alphabetical order)
   * are made on lists of courses.
   * 
   * It will by default try to load this information from cache.  If it cannot
   * find it in the cache, it will query the database, and then add what it finds
   * to the cache.
   * 
   *
   * @param bool $bool_load_from_global_cache
   *        - If set to TRUE, this will attempt to load the course data
   *          from the "global cache", that is, the cache which is held in the
   *          GLOBALS array.  This should usually be set to TRUE, since this is
   *          much faster than querying the database.
   * 
   * @param bool $bool_ignore_catalog_year_in_cache
   *        - If set to TRUE, we will grab whatever is in the cache for this
   *          course's course_id, regardless of if the catalog years match.
   *          If set to FALSE, we will try to match the course's catalog year
   *          in the cache as well.
   * 
   * @param bool $bool_limit_current_catalog_year
   *        - If set to TRUE, then we will only *query* for the course's
   *          catalog_year in the db, and those before it (if we do not find
   *          the exact catalog_year).  We will not look for any catalog years
   *          after it.  If set to FALSE, we will look through any 
   *          valid catalog year.
   * 
   * @param bool $bool_force_catalog_year
   *        - If set to TRUE, we will only look for the course's catalog
   *          year in the database.
   * 
   * @param bool $bool_ignore_exclude
   *        - If set to TRUE, we will ignore courses marked as "exclude" in the
   *          database.
   * 
   */
  function load_descriptive_data($bool_load_from_global_cache = true, $bool_ignore_catalog_year_in_cache = true, $bool_limit_current_catalog_year = true, $bool_force_catalog_year = false, $bool_ignore_exclude = false, $bool_reset_ghost_hours = TRUE)
  {
    
    if ($this->db == null)
    {
      $this->db = get_global_database_handler();
    }

    $db = $this->db;

    if ($this->catalog_year == "")
    {
      $this->catalog_year = variable_get_for_school("current_catalog_year", 2006, $this->school_id);  // current catalog_year.
    }

    $setting_current_catalog_year = variable_get_for_school("current_catalog_year", 2006, $this->school_id) * 1;
    if ($this->bool_use_draft) {
      $setting_current_catalog_year = variable_get_for_school("current_draft_catalog_year", 2006, $this->school_id) * 1;
    }
    
    $earliest_catalog_year = variable_get_for_school("earliest_catalog_year", 2006, $this->school_id);
    
    
    if ($setting_current_catalog_year < $earliest_catalog_year)
    { // If it has not been set, assume the default.
      $setting_current_catalog_year = $earliest_catalog_year;
    }

    if ($bool_limit_current_catalog_year == true && $setting_current_catalog_year > $earliest_catalog_year)
    {
      if ($this->catalog_year*1 > $setting_current_catalog_year)
      {

        $this->catalog_year = $setting_current_catalog_year;  // current catalog_year.
      }
    }

    if ($this->catalog_year < $earliest_catalog_year && $this->catalog_year != 1900)
    {
      // Out of range, so set to default
      $this->catalog_year = $earliest_catalog_year;
    }

    $cat_line = "";
    if ($bool_force_catalog_year == true)
    {
      $cat_line = " AND catalog_year = '$this->catalog_year' ";
    }


    $cache_catalog_year = $this->catalog_year;
    if ($bool_ignore_catalog_year_in_cache == true)
    {
      $cache_catalog_year = 0;
    }

    if (!isset($this->array_valid_names))
    {
      $this->array_valid_names = array();
    }


    // First-- is this course in our GLOBALS cache for courses?
    // If it is, then load from that.
    if ($bool_load_from_global_cache == true && $this->course_id != 0 &&
        @$GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["subject_id"] != "")
    {
      $this->subject_id = $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["subject_id"];
      $this->course_num = $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["course_num"];
      $this->title = $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["title"];
      $this->description = $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["description"];      
      $this->min_hours = $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["min_hours"];
      $this->school_id = $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["school_id"];
                 
      if ($bool_reset_ghost_hours) {
        // Reset the ghosthours to default.
        $this->bool_ghost_hour = $this->bool_ghost_min_hour = FALSE;
      }
      
      if ($this->min_hours <= 0) {
        $this->min_hours = 1;
        if ($bool_reset_ghost_hours) {        
          $this->bool_ghost_min_hour = TRUE;
        }
      }
      
      $this->max_hours = $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["max_hours"];
      
      if ($this->max_hours <= 0) {
        $this->max_hours = 1;
        if ($bool_reset_ghost_hours) {
          $this->bool_ghost_hour = TRUE;
        }
      }
      
      
      $this->repeat_hours = $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["repeat_hours"];
      $this->db_exclude = $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["db_exclude"];
      $this->array_valid_names = $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["array_valid_names"];
      return;
    }


    if ($this->course_id != 0)
    {
      
      $exclude_line = " AND exclude = '0' ";
      if ($bool_ignore_exclude) {
        $exclude_line = "";
      }
      
      $table_name = "courses";
      if ($this->bool_use_draft) {$table_name = "draft_$table_name";}
      $res = $this->db->db_query("SELECT * FROM $table_name
                    WHERE course_id = ? 
                    AND catalog_year = ?
                    AND delete_flag = 0 
                    $exclude_line ", $this->course_id, $this->catalog_year);
      $cur = $this->db->db_fetch_array($res);


      if ($this->db->db_num_rows($res) < 1)
      {
        // No results found, so instead pick the most recent
        // entry.

        $table_name = "courses";
        if ($this->bool_use_draft) {$table_name = "draft_$table_name";}
        $res2 = $db->db_query("SELECT * FROM $table_name
              WHERE course_id = ? 
              AND subject_id != '' 
              AND delete_flag = 0 
              $exclude_line
              AND catalog_year <= '$setting_current_catalog_year'
              $cat_line
              ORDER BY `catalog_year` DESC LIMIT 1", $this->course_id);
        $cur = $db->db_fetch_array($res2);

        if ($db->db_num_rows($res2) < 1)
        {
          // Meaning, there were no results found that didn't have
          // the exclude flag set.
          // So, try to retrieve any course, even if it has
          // been excluded (but within our catalog year range)
          //$db3 = new DatabaseHandler();
          $table_name = "courses";
          if ($this->bool_use_draft) {$table_name = "draft_$table_name";}
          $res3 = $db->db_query("SELECT * FROM $table_name
              WHERE course_id = ? 
              AND subject_id != '' 
              AND delete_flag = 0
              AND catalog_year <= '$setting_current_catalog_year'
              $cat_line
              ORDER BY `catalog_year` DESC LIMIT 1", $this->course_id);
          $cur = $db->db_fetch_array($res3);

        }

      }

      if ($cur) {
        // Meaning, we DID find something.

        $this->title = $this->fix_title($cur["title"]);
        $this->description = trim($cur["description"]);      
        $this->subject_id = trim(strtoupper($cur["subject_id"]));
        $this->course_num = trim(strtoupper($cur["course_num"]));
        $this->school_id = intval($cur["school_id"]);
  
  
        $this->min_hours = $cur["min_hours"] * 1;  //*1 will trim extra zeros from end of decimals
        $this->max_hours = $cur["max_hours"] * 1;
  
              
        
        if ($bool_reset_ghost_hours) {
          // Reset the ghosthours to default.
          $this->bool_ghost_hour = $this->bool_ghost_min_hour = FALSE;
        }
        
        if ($this->min_hours <= 0) {
          $this->min_hours = 1;
          if ($bool_reset_ghost_hours) {
            $this->bool_ghost_min_hour = TRUE;
          }
        }
        if ($this->max_hours <= 0) {
          $this->max_hours = 1;
          if ($bool_reset_ghost_hours) {
            $this->bool_ghost_hour = TRUE;
          }
        }
        
        
        $this->repeat_hours = $cur["repeat_hours"] * 1;
        if ($this->repeat_hours <= 0)
        {
          $this->repeat_hours = $this->max_hours;
        }
  
        $this->db_exclude = $cur["exclude"];
        $this->data_entry_comment = $cur["data_entry_comment"];
        
      } // if cur
      
      
      // Now, lets get a list of all the valid names for this course.
      // In other words, all the non-excluded names.  For most
      // courses, this will just be one name.  But for cross-listed
      // courses, this will be 2 or more (probably just 2 though).
      // Example: MATH 373 and CSCI 373 are both valid names for that course.
      $table_name = "courses";
      if ($this->bool_use_draft) {$table_name = "draft_$table_name";}

      $res = $this->db->db_query("SELECT * FROM $table_name
                    WHERE course_id = ?
                    AND exclude = 0 ", $this->course_id);
      while($cur = $this->db->db_fetch_array($res))
      {
        $si = $cur["subject_id"];
        $cn = $cur["course_num"];
        if (in_array("$si~$cn", $this->array_valid_names))
        {
          continue;
        }
        $this->array_valid_names[] = "$si~$cn";
      }


    } else if ($this->bool_transfer == true)
    {
      // This is a transfer credit which did not have a local
      // course eqv.  At the moment, the subject_id and
      // course_num are empty.  So, let's fill them in with the
      // transfer credit's information.
      if ($this->course_transfer != null)
      {

        $this->subject_id = $this->course_transfer->subject_id;
        $this->course_num = $this->course_transfer->course_num;
        if ($this->course_transfer->get_hours_awarded() > 0)
        {
          $this->set_hours_awarded(0, $this->course_transfer->get_hours_awarded());
        }
      }


    }


    if ($this->description == "")
    {      
      $this->description = "There is no course description available at this time.";
    }

    if ($this->title == "")
    {
      $this->title = "$this->subject_id $this->course_num";
    }


    // Now, to reduce the number of database calls in the future, save this
    // to our GLOBALS cache...

    
    // We do need to go back and correct the ghost hours, setting them
    // back to 0 hrs, or else this will be a problem.
    $min_hours = $this->min_hours;
    $max_hours = $this->max_hours;
    if ($bool_reset_ghost_hours) {    
      if ($this->bool_ghost_min_hour) $min_hours = 0;
      if ($this->bool_ghost_hour) $max_hours = 0;
    }
    
    
    // Since we may have trouble characters in the description (like smart quotes) let's
    // do our best to try to clean it up a little.
    //$this->description = utf8_encode($this->description);
    // utf8_encode is deprecated in php 8.2 and will be removed in PHP 9.  Let's use a more modern approach:
    $this->description = fp_utf8_encode($this->description);
        
    
    $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["subject_id"] = $this->subject_id;
    $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["course_num"] = $this->course_num;
    $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["title"] = $this->title;
    $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["description"] = $this->description;
    $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["school_id"] = $this->school_id;
    $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["min_hours"] = $min_hours;
    $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["max_hours"] = $max_hours;
    $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["repeat_hours"] = $this->repeat_hours;
    $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["db_exclude"] = $this->db_exclude;
    $GLOBALS["fp_course_inventory"][$this->course_id][$cache_catalog_year]["array_valid_names"] = $this->array_valid_names;

    $GLOBALS["cache_course_inventory"] = true;  //  rebuild this cache before it closes.


  }


  /**
   * Similar to load_descriptive_data(), this will load whatever we have
   * for $this transfer course.
   *
   * @param int $student_id
   *        - If > 0, we will look for the course data which has been
   *          assigned for this particular student.  If it == 0, we will
   *          just use the first bit of data we find.
   * 
   */
  function load_descriptive_transfer_data($student_id = "", $term_id = NULL)
  {

    // This method should be called to load transfer course data
    // into THIS object.  It assumes that $this->course_id is a transfer
    // course's ID, which can be looked up in flightpath.transfer_courses.

    // If a student_id is specified, it will load eqv information.
    if ($this->db == null)
    {
      $this->db = get_global_database_handler();
    }

    
    
    $res = $this->db->db_query("SELECT * FROM transfer_courses
                               WHERE transfer_course_id = ? ", $this->course_id);
    $cur = $this->db->db_fetch_array($res);

    $this->subject_id = $cur["subject_id"];
    $this->course_num = $cur['course_num'];
    $this->title = $this->fix_title($cur['title']);
    $this->min_hours = $cur["min_hours"] * 1;
    $this->max_hours = $cur["max_hours"] * 1;
    $this->institution_id = $cur["institution_id"];
    $this->school_id = intval($cur['school_id']);
    // Try to figure out the institution name for this course...
    $this->institution_name = $this->db->get_institution_name($this->institution_id);

    if ($student_id != "")
    {
      // Because transfer credit titles may differ from student
      // to student, let's look up the title in the per-student transfer courses table...
      $res = $this->db->db_query("SELECT * FROM student_transfer_courses
                  WHERE student_id = ?
                  AND transfer_course_id = ? 
                   ", $student_id, $this->course_id);
      $cur = $this->db->db_fetch_array($res);
      if ($cur) {
        if (fp_trim(@$cur["student_specific_course_title"]) != "") {
          $this->title = trim($cur["student_specific_course_title"]);
        }
        // Also assign hours_awarded and other values while we are here
        $this->set_hours_awarded(0, floatval($cur["hours_awarded"]));
        $this->grade = $cur["grade"];
        $this->term_id = $cur["term_id"];
        
        ///////////////////
        // If a term_id was specified, then let's try to see if we can get more specific information for this course.
        if ($term_id != NULL) {
          $res = $this->db->db_query("SELECT * FROM student_transfer_courses
                      WHERE student_id = ?
                      AND transfer_course_id = ?
                      AND term_id = ? 
                       ", $student_id, $this->course_id, $term_id);
          if ($res) {                     
            $cur = $this->db->db_fetch_array($res);
            if ($cur) {
              if (trim($cur["student_specific_course_title"]) != "") {
                $this->title = trim($cur["student_specific_course_title"]);
              }
              // Also assign hours_awarded and other values while we are here
              if ($cur["grade"] != "" || $cur["hours_awarded"] != "") {
                $this->set_hours_awarded(0, $cur["hours_awarded"] * 1);
                $this->grade = $cur["grade"];
              }
              $this->term_id = $cur["term_id"];
            }      
          }
        } // if term_id != NULL
        
        
        
  
        $already = array();  // to prevent duplicates from showing up, keep up with
                             // eqv's we've already recorded.
        
     
        $res2 = $this->db->db_query("SELECT * FROM transfer_eqv_per_student
                        WHERE student_id = ?  
                        AND transfer_course_id = ? 
                         ", $student_id, $this->course_id);
        while($cur2 = $this->db->db_fetch_array($res2))
        {        
  
          if (!in_array($cur2["local_course_id"], $already)) {
            $c = new Course($cur2["local_course_id"]);
            $this->transfer_eqv_text .= "$c->subject_id $c->course_num
                  (" . $c->get_catalog_hours() . " " . t("hrs") . ") ";
            $already[] = $cur2["local_course_id"];
          }
        }

      } // if cur
    } // if student_id


  }

  
  /**
   * Based on $this->term_id, set what catalog year should go with
   * the course.
   *
   */
  function set_catalog_year_from_term_id()
  {

    if ($this->db == null)
    {
      $this->db = get_global_database_handler();
    }

    if (strstr($this->term_id, "1111"))
    {
      
      $this->catalog_year = variable_get_for_school("earliest_catalog_year", 2006, $this->school_id);
    }

    $this->catalog_year = trim(substr($this->term_id,0,4));

    // If the catalog year is greater than the currentCatalogYear
    // setting, then set it to that.


    if ($this->catalog_year > variable_get_for_school("current_catalog_year","", $this->school_id))
    {
      $this->catalog_year = variable_get_for_school("current_catalog_year","", $this->school_id);
    }



  }

  /**
   * Based on $this->term_id, returns a plain english description
   * of the term.  For example, 20061 would return "Spring of 2006".
   *
   * @param bool $bool_abbreviate
   *        - If set to TRUE, abbreviations will be used.  For example,
   *          Spring will be "Spr" and 2006 will be '06.
   * 
   * 
   * @return unknown
   */
  function get_term_description($bool_abbreviate = FALSE)
  {
    // Let's use the built-in FP function
    return get_term_description($this->term_id, $bool_abbreviate, $this->school_id);
  }

  /**
   * Basically, this is a comparator function that will return true
   * if $this equals many of the attributes of $course_c.  Useful for
   * seeing if $this is an "instance of" a particular course, but not
   * necessairily the course that the student took.  Example: if you want
   * to test if MATH 101 is part of a group.  You wouldn't use ==, since
   * all the attributes might not be the same.
   * 
   * @param Course $course_c
   * 
   * @return bool
   */
  function equals_placeholder(Course $course_c)
  {

    // First, see if the courses are identical.
   
    if ($this->equals($course_c)) 
    {
      return true;
    }
    
    // Okay, now we go through and test for particular attributes
    // to be equal.
    if ($this->subject_id == $course_c->subject_id
    && $this->course_num == $course_c->course_num
    && $this->institution == $course_c->institution)
    {
      return true;
    }


    return false;
  }

  
  /**
   * This is the to_string method for Course.  Because we want to pass it
   * values, we are not using the magic method of "__to_string".  So, to use,
   * invoke this method directly.  Ex:
   * 
   * $x = $newCourse->to_string("", true);
   *
   * @param string $pad
   *        - How much padding to use.  Specified in the form of a string
   *          of spaces.  Ex:  "   "
   * 
   * @param bool $bool_show_random
   *        - Display the randomly assigned number which goes with
   *          this course.
   * 
   * @return string
   */
  function to_string($pad = "      ", $bool_show_random = false)
  {
    $rtn = "";

    if ($this->subject_id == "") {
      $this->load_descriptive_data();
    }

    if ($bool_show_random) {$x = "rnd:$this->random_id -";}

    $rtn = $pad . "$this->course_id $x- $this->subject_id $this->course_num (" . $this->get_hours_awarded() . ") $this->grade $this->term_id";

    if ($this->course_list_fulfilled_by->is_empty != true) {
      // In other words, if this is a requirement, and it is
      // being fulfilled by one of the student's courses,
      // then let's see it.
      $rtn .= " ->fulfilled by " . $this->course_list_fulfilled_by->get_first()->to_string("");
    }

    if ($this->bool_transfer == true && is_object($this->course_transfer))
    {
      $rtn .= " - XFER eqv to " . $this->course_transfer->to_string("");
    } else if ($this->bool_transfer == true){
      $rtn .= " - XFER no eqv ";
    }


    if ($this->bool_advised_to_take) {
      $rtn .= " - adv in sem " . $this->assigned_to_semester_num . ".";
    }

    if ($this->bool_substitution) {
      $rtn .= " - substitution.";
    }


    if ($this->db_exclude > 0) {
      $rtn .= " - db_exclude = $this->db_exclude";
    }

    if ($this->specified_repeats > 0) {
      $rtn .= " reps: $this->specified_repeats";
    }


    $rtn .= "\n";
    return $rtn;
  }


  /**
   * Return TRUE or FALSE if this this course was ever assigned to the supplied group_id. 
   * if $group_id == -1, then return TRUE if it was assigned to ANY group.
   */
  function get_bool_assigned_to_group_id($group_id) {
    
    // Trim and force NULL or 0 to be ''.  This is for PHP 8 compatibility.
    $group_id = fp_trim($group_id);
    if (is_numeric($group_id) && intval($group_id) == 0) {
      $group_id = '';
    } 
            
    $bool_yes_specific_group = FALSE;
    $bool_yes_any_group = FALSE;
    
    foreach ($this->assigned_to_group_ids_array as $k => $v) {
      if (intval($v) > 0 || strlen($v) > 0) {
        $bool_yes_any_group = TRUE;  
      } 
      
      // Convert 0 and NULL to '' for PHP 8 compatibility 
      if (is_numeric($v) && intval($v) == 0) $v = '';
      if ($v == NULL) $v = '';
      
      if ($group_id == $v) {
        $bool_yes_specific_group = TRUE;        
      }
      
    }
    
    
    if ($group_id == -1) {
      return $bool_yes_any_group;
    }
    
    // Else...
    return $bool_yes_specific_group;
    
  }


  /**
   * Return the first element in our assigned_to_group_ids_array, or FALSE
   */
  function get_first_assigned_to_group_id() {
      
    if (count($this->assigned_to_group_ids_array) < 1) return FALSE;
    
    // Otherwise, get it.
    $x = reset($this->assigned_to_group_ids_array);  // returns the first element.
    if ($x == 0) {  // not a valid group_id number
      return FALSE;
    }    

    // Otherwise, return x   
    return $x;     
    
  }


  /**
   * Has the course been assigned to ANY degree?
   */
  function get_has_been_assigned_to_any_degree() {
    if (isset($this->assigned_to_degree_ids_array) && count($this->assigned_to_degree_ids_array) > 0) return TRUE;
    
    return FALSE;
  }


  function get_has_been_assigned_to_degree_id($degree_id = 0) {
    
    if ($degree_id == 0) $degree_id = $this->req_by_degree_id;
    
    if (in_array($degree_id, $this->assigned_to_degree_ids_array)) {
      return TRUE;
    }    
    
    return FALSE;
  }



  /**
   * This is the magic method __sleep().  PHP will call this method any time
   * this object is being serialized.  It is supposed to return an array of
   * all the variables which need to be serialized.
   * 
   * What we are doing in it is skipping
   * any variables which we are not using or which do not need to be
   * serialized.  This will greatly reduce the size of the final serialized
   * string.
   * 
   * It may not seem worth it at first, but consider that we may be serializing
   * an entire degree plan, with a dozen groups, each with every course in the
   * catalog.  That could easily be 10,000+ courses which get serialized!
   *
   * @return array
   */
  function __sleep()
  {
    // This is supposed to return an array with the names
    // of the variables which are supposed to be serialized.

    $arr = array(
    "db_advised_courses_id", "random_id",
    "db_substitution_id_array", "db_unassign_transfer_id",
    "db_exclude", "array_index", "db_group_requirement_id", "db_degree_requirement_id", "array_valid_names",
    "data_entry_value", "db_group_attributes", "appears_in_semester_nums", "extra_attribs",
 
    "subject_id", "course_num", "course_id", "requirement_type", "catalog_year",
    "min_hours", "max_hours", "repeat_hours", "bool_outdated_sub",

    "bool_taken", "term_id", "section_number", "grade", "db_grade", "quality_points",
    "bool_transfer", "institution_id", "institution_name", "course_transfer", "transfer_footnote",    
    "substitution_footnote",    

    "min_grade", "specified_repeats", "bool_specified_repeat", "required_on_branch_id",
    "assigned_to_group_id", "assigned_to_semester_num", "level_code", "req_by_degree_id",
    "assigned_to_degree_ids_array", "assigned_to_group_ids_array", "school_id",

    "advised_hours", "bool_selected", "bool_advised_to_take", "bool_use_draft",
    "course_list_fulfilled_by",
    "bool_added_course", "group_list_unassigned", 
    
    "details_by_degree_array",

    "display_status", "disp_for_group_id",
    "bool_hide_grade", "bool_ghost_hour",
    "bool_ghost_min_hour",
    );

    // Okay, remove any variables we are not using
    // from the array.
    $rtn = array();
    foreach($arr as $var)
    {
      if (isset($this->$var))  // This checks to see if we are using
      {           // the variable or not.
        $rtn[] = $var;
      } 
    }

    return $rtn;
  }




} // end of Course class.
