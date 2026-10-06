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

    var generateSummaryLink = $(".markup-element-mark_generate_summary");
    var formToken = $("#fp-form-comments_comment_form input[name=form_token]").val();
    var finishGenerating = function() {
      generateSummaryLink.removeClass("generating").removeAttr("aria-disabled");
    };

    generateSummaryLink.addClass("generating").attr("aria-disabled", "true");

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
        finishGenerating();
        return;
      }

      commentsTypeAdvisingSummary(data.summary, finishGenerating);
    })
    .fail(function() {
      fp_alert("The advising summary could not be generated. Please try again.");
      finishGenerating();
    });
  });
}

/**
 * Quickly reveal an advising summary one word at a time in the comment editor.
 */
function commentsTypeAdvisingSummary(summary, complete) {
  var editor = null;
  var textarea = $("#element-comment");
  var words = summary.match(/\S+\s*/g) || [summary];
  var displayedSummary = "";
  var wordIndex = 0;

  if (typeof tinymce !== "undefined") {
    editor = tinymce.get("element-comment");
  }

  function displayNextWord() {
    displayedSummary += words[wordIndex];
    wordIndex++;

    if (editor) {
      var summaryHtml = $("<div>").text(displayedSummary).html().replace(/\r?\n/g, "<br>");
      editor.setContent(summaryHtml);
    }
    else {
      textarea.val(displayedSummary);
    }

    if (wordIndex < words.length) {
      window.setTimeout(displayNextWord, 15);
      return;
    }

    if (editor) {
      editor.save();
      editor.focus();
    }
    else {
      textarea.trigger("change").focus();
    }

    if (typeof complete === "function") {
      complete();
    }
  }

  displayNextWord();
}
