<?php

/**
 * These are hooks that other modules may take advantage of from the course_search module
*/



/**
 * This is called AFTER the regular course rotation schedule array has been built.  Notice it is passed by reference. This lets us
 * make alterations to it.
 */
function hook_course_search_get_course_rotation_schedule(&$schedule_array, $course_id, $year, $limit, $bool_include_next_five_years) {
  
  // ... Make changes to $schedule_array
  
  
  // Nothing to return, since $schedule_array is passed by reference.
}




/**
 * This is called AFTER the regular course_search_get_course_rotation_schedule_not_anticipated.  In general, we want to see if 
 * anything is going to be offered in the next 5 years.
 */
function hook_course_search_get_course_rotation_schedule_not_anticipated(&$rtn, $course_id) {
  
  // Make changes here...
  
  
  // Nothing to return since our $rtn is passed by reference
}