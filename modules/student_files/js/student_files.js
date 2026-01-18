/**
 * @file
 * This is the javascript file which accompanies the student_files module. 
 */


/**
 * Run on page load 
 */
$(document).ready(function() {
  
  
  // For the Student Upload Any Files form, we want the Manual CWID field disabled unless we select "manual" as our radio button. 
  
  // When we change the manual radio button, update our funciton.
  $("input[name='student_method']").change(function () {
    studentFilesUpdateManualCWIDField();
  });
  
  // Call it once to check the status of the field:
  studentFilesUpdateManualCWIDField();
  
  
});

/**
 * Updates the manual cwid field, whether the manual radio button is checked or not. 
 */
function studentFilesUpdateManualCWIDField() {  
  if ($("input[name='student_method']:checked").val() == "manual") {    
    $("#element-manual_cwid").prop("disabled", false);  // NOT disabled
  }
  else {
    $("#element-manual_cwid").prop("disabled", true);  // set to disabled
  }  
  
  
}