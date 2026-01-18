/**
 * Used by the advising "snapshot" iframe. 
*/

$(document).ready(function() {
  $("#selected_school_id").change(function() {
    $("#snapshot-school-selector-form").submit();
  });
});