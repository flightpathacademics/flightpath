READEME for Prereqs Module


============================
Hooks
============================

Your custom module can add more prereq conditions through the hook:
  
  hook_prereqs_get_prereq_warnings(&$warnings, Course $course, Student $student)
  
$warnings is the existing array of warning messages, if any exist yet.  It is passed BY REFERENCE.
$course is the course in question.
$student is the student who is trying to take the course, which may or may not be set.

Example:

function mmymodule_prereqs_get_prereq_warnings_for_course(&$warnings, $course, $student) {
    
  if ($course->course_id == 12345) {
    $warnings["some_unique_index"] = "You cannot take this course.";
  }
  
  // We don't return anything, since $warnings was passed by reference.
  
}
