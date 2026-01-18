/**
 * This script handles the character count for textareas with a maxlength value set.
 */


$(document).ready(function() {

  // Find all textareas with a maxlength, and attach some code when they change.
  $(".textarea-maxlength-count").each(function() {
    var id = $(this).attr("id");
    // Get the id of the textarea itself!
    var temp = id.split("___");
    var cssname = temp[1];
    
    var textarea_id = "element-" + cssname;

    // Recount the used chars whether we typed something or pasted it...
    $("#" + textarea_id).on("keyup change", function() {
      $("#" + textarea_id + "__current_count").html($(this).val().length);
    })



  });


});