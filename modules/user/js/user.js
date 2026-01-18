/**
 * Javascript file containing functions for the user management module. 
 */


function userDeleteStudent() {
  var x = confirm("Are you sure you wish to delete this student?  This action cannot be undone.  \n\nAny advising sessions for this user *will be kept*.  The user will be removed from the user tables only.");
  if (x) {
    // Insert this action into our "perform action" hidden variable, then submit the form.
    $("#element-perform_action2").val("delete_student");
    
    $("#fp-form-user_edit_student_user_form").submit();
  }
}

function userDeleteFaculty() {
  var x = confirm("Are you sure you wish to delete this faculty member?  This action cannot be undone.  \n\nAny advising sessions for this user *will be kept*.  The user will be removed from the user tables only.");
  if (x) {
    // Insert this action into our "perform action" hidden variable, then submit the form.
    $("#element-perform_action2").val("delete_faculty");
    
    $("#fp-form-user_edit_user_form").submit();
  }
}