<?php

/**
 * This file defines the hooks we recognize from this module.
*/



/**
 * Lets us modify an array of calculation tests, either adding to it, or altering the existing ones.
 * Notice that $arr is passed by reference.
 */
function hook_define_calculation_tests(&$arr) {
  
  // METHOD ONE:  A function runs for each test to get the result.
  
  
  $arr['my_module__something_test'] = array(   // the key is also the callback function to call!  By convention, use module name, 2 underscores, then test name.
    'title' => 'This is the title of this test.',
    'description' => 'This is a description of this test.',
    'result_scores' => array(  // Based on the returned 'result' value, what "score" should we use?  The default can be overridden in the UI.
      'Y' => 0,
      'N' => 0.5
    ),
    'file' => array('my_module', 'my_modules.calculations.inc'),  // module name first, the file next (assuming it is within a module folder.        
    'group' => 'Custom Group A',  // groups multiple tests together this way    
    'weight' => 1010,   // lighter weights float to the top (get evaluated first).
  );
  
  
  
  // METHOD TWO:  You specifically look at a user's attribute for the test to get the result.  No need for a custom function.
  // In this example, it assumes there is an attribute called student_is_good, whose data is saved
  // in the attributes table as student_is_good__value.
  $arr['mymodule2__student_is_good'] = array(
      'title' => 'Student is good?', 
      'result_scores' => array(
        'Y' => 0,
        'N' => 1,
      ),
      'options' => array(   // optional, but might display nicer on screen.
        'Y' => 'Yes',
        'N' => 'No',
      ),
      'weight' => $w,
      'result_from_user_attribute' => 'student_is_good__value',      
    );
  
  
  
  // No need to return anything, since $arr is passed by reference.
  
}





/**
 * Implements hook_define_calculation_tests, "METHOD ONE" from above.
 * Accept an array so we can alter.  Then, we are just going to add to array (or modify).
 */
function example_define_calculation_tests(&$arr) {
    
  
  $arr['example__does_student_work_on_campus'] = array(  
    'title' => 'Does the student work on campus?',
    'description' => 'If the student has a campus job, they are generally more engaged
                      in campus life and their own success.',        
    'result_scores' => array(  // Based on the returned 'result' value, what "score" should we use?
      'Y' => 0,
      'N' => 0.5
    ),    
    'file' => array('example', 'example.calculations.inc'),        
    'group' => 'FlightPath Core',  // groups multiple tests together this way    
    'weight' => 100,   // lighter weights float to the top (get evaluated first)
  );
   

  $arr['example__is_student_gpa_above_250'] = array(  
    'title' => "Is the student's GPA above 2.50?",
    'result_scores' => array(
      'Y' => 0,
      'N' => 0.9
    ),    
    'file' => array('example', 'example.calculations.inc'),        
    'group' => 'FlightPath Core',  // groups multiple tests together this way    
    'weight' => 110,
  );

  $arr['example__does_student_have_more_than_2_d_or_f'] = array(  
    'title' => "Does the student have more than 2 D's or F's?",
    'result_scores' => array(
      'Y' => 1,
      'N' => 0,
    ),    
    'file' => array('example', 'example.calculations.inc'),        
    'group' => 'FlightPath Core',  // groups multiple tests together this way    
    'weight' => 130,
  );



  $arr['example__does_student_have_more_than_2_w'] = array(  
    'title' => "Does the student have more than 2 W's?",
    'result_scores' => array(
      'Y' => 0.8,
      'N' => 0,
    ),    
    'file' => array('example', 'example.calculations.inc'),        
    'group' => 'FlightPath Core',  // groups multiple tests together this way    
    'weight' => 140,
  );



  $arr['example__did_student_score_below_b_on_engl_1001'] = array(  
    'title' => "Did the student score below a 'B' in ENGL 1001?",
    'result_scores' => array(
      'Y' => 0.8,
      'N' => 0,      
      'N/A' => 'SKIP',
    ),    
    'file' => array('example', 'example.calculations.inc'),        
    'group' => 'FlightPath Core',  // groups multiple tests together this way    
    'weight' => 150,
  );


  $arr['example__does_student_have_federal_financial_aid'] = array(  
    'title' => "Does the student have federal financial aid?",
    'result_scores' => array(
      'Y' => 0,
      'N' => 0.2,            
    ),    
    'file' => array('example', 'example.calculations.inc'),        
    'group' => 'FlightPath Core',  // groups multiple tests together this way    
    'weight' => 105,
  );


  // No need to return the $arr, since it was passed by reference.
  

  
} // example_define_calculation_tests()








/*
 * Example of a particular test being run.  Notice it has the same name as defined
 * in our hook_define_calculation_tests()
 * 
 * Notice that the $student object is always passed to the function!
 * 
 * Based on our hook_define_calculation_tests above, this function would appear in a file called "example.calculations.inc".  
 * It is displayed here for simplicity.  All of the tests from that hook would be similarly defined as functions.
 *  
 */
function example__is_student_gpa_above_250(Student $student) {

  $rtn = array();
  
  // For this test, we will simply examine the student's cumulative GPA
  if (floatval($student->gpa) > 2.5) {
    $rtn['result'] = 'Y';    
  }
  else {
    $rtn['result'] = 'N';
  }
  
  $rtn['extra'] = 'Current cumulative GPA is ' . floatval($student->gpa);
  
  return $rtn;  
} // example__is_student_gpa_above_250








