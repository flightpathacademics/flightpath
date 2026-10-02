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

/**
 * Confirm and generate a summary from the logged-in advisor's most recent
 * advising session for the student currently being viewed.
 */
function commentsGenerateAdvisingSummary() {
  var msg = "This will clear anything currently in the comment box and replace it with a summary of your most recent advising session for this student.\n\nDo you wish to continue?";

  DayPilot.Modal.confirm(msg).then(function(modal) {
    if (!modal.result) {
      return;
    }

    var button = $("input[name=generate_advising_summary]");
    var originalValue = button.val();
    var formToken = $("#fp-form-comments_comment_form input[name=form_token]").val();

    button.prop("disabled", true).val("Generating...");

    $.ajax({
      url: FlightPath.settings.basePath + "/index.php?q=comments/ajax-generate-advising-summary",
      type: "POST",
      dataType: "json",
      data: {
        current_student_id: FlightPath.settings.currentStudentId,
        form_token: formToken
      }
    })
    .done(function(data) {
      if (!data || data.error || !data.success || typeof data.summary !== "string") {
        fp_alert((data && data.error) ? data.error : "The advising summary could not be generated.");
        return;
      }

      var editor = null;
      if (typeof tinymce !== "undefined") {
        editor = tinymce.get("element-comment");
      }
      if (editor) {
        var summaryHtml = $("<div>").text(data.summary).html().replace(/\r?\n/g, "<br>");
        editor.setContent(summaryHtml);
        editor.save();
        editor.focus();
      }
      else {
        $("#element-comment").val(data.summary).trigger("change").focus();
      }
    })
    .fail(function() {
      fp_alert("The advising summary could not be generated. Please try again.");
    })
    .always(function() {
      button.prop("disabled", false).val(originalValue);
    });
  });
}
