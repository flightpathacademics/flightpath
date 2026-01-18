<?php
/**
 * @file
 * This file contains examples of the hooks you may use (as a module developer) to extend the functionality
 * of the audit module.
*/




/**
 * This hook allows the module developer to modify the approval types 
 * which will be displayed on the audit tab.
 * 
 * Ordinarily, basic approvals are able to be added via the Audit settings using
 * the Admin Console.  This allows us to remove, re-arrange, or add new approvals
 * dynamically.
 * 
 * Notice that the $approvals array is passed by reference.  There is no need
 * to return anything.  
 */
function hook_audit_modify_approval_types(&$approvals, $school_id = 0) {
 
  // $approvals looks like:
  /*
   *  $approvals['machine_name'] = array(
   *    'title' => 'Some Title'
   *    'description' => 'Some Description',
   *  );
   * 
   *  To ensure uniqueness, it is best practice for the machine name to begin with the name
   *  of the module.  Ex:  mymodule_education_coursework.
   * 
   */
 
 
  $approvals['mymodule_education_coursework'] = array(
    'title' => 'Education Coursework',
    'description' => 'The student has completed all Education coursework with a GPA of at least 2.5.',
  );
 
 
  // Do not return anything.  $approvals is passed by reference.  
  
}




/**
 * This hook allows other modules to add to the "overall" calculations table near the top of the Audit tab.
 * 
 * Items added here will appear ABOVE the "footnotes & messages" section.
 * 
 * @return $rtn   An array that contains the additional row information.  Ex:
 *                $rtn[] = array(
 *                  'title' => 'Education Courses:',
 *                  'section_1_html' => $some_html_here
 *                  'section_2_html' => $some_html_here_also
 *                  'raw_data' => $arr, // optional array of "raw data" formatted however you like, for use later in other modules.
 *                );
 */
function hook_audit_get_additional_overall_calculations($student, $school_id = 0) {

  $rtn = array();
  
  
  // IF student is in the Education major....
  $rtn[] = array(
    'title' => 'Education Courses:',
    'section_1_html' => '<b>This goes in section 1</b>',
    'section_2_html' => '<b>This goes in section 2</b>',
    'raw_data' => array(1,2,3,4),
  );
  
  
  return $rtn;
}


