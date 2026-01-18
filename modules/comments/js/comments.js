/*
 * Javascript file for comments module
 */


function deleteComment(id) {
  var x = confirm("Are you sure you wish to delete this comment?\nThis action cannot be undone.");
  if (x) {
    //window.location=FlightPath.settings.basePath + "/comments/delete-comment&current_student_id=" + FlightPath.settings.currentStudentId + "&comment_id=" + id;
    
    // To make compatible with non-clean URL sites, we will use the "unclean" URL...
    var url = FlightPath.settings.basePath + "/index.php?q=" + "comments/delete-comment&current_student_id=" + FlightPath.settings.currentStudentId + "&comment_id=" + id;;
    window.location = url;
    
  }
} 