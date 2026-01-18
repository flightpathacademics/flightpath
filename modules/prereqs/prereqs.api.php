<?php
/**
 * @file This file describes the hook(s) available to module developers for the prereqs module.
 * 
*/



/**
 * If implemented, this hook has the option to modify or add more warnings after the original prereqs function is run.
 * @see prereqs_get_prereq_warnings_for_course()
 * 
 * 
 * Look at prereqs_get_prereq_warnings_for_course() in prereqs.module file as a starting place for implementation.
 * 
 * 
 */
function hook_prereqs_get_prereq_warnings_for_course(&$warnings, $course, $student = null) {
  
  
  // Code goes here.
  
  
  // We do not return anything, since $warnings is passed by reference
  
  
}
