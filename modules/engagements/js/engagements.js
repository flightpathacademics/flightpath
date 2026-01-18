/**
 * Javascript file for the engagements module.
*/

var e_original_title_val = $('#element-title').val();

// Runs on startup
$(document).ready(function() {
  
  
  // If the user is typing in the title field, keep track of it.
  $('#element-title').keypress(function() {
    e_original_title_val = $('#element-title').val();
  });
  
  // reconfigure the engagements form when we change the "type".
  $('#element-engagement_type').change(function() {
    engagementsUpdateForm();
  });
  
  // Also, run on start up no matter what.
  engagementsUpdateForm();
  
  
});



/**
 * The body of an engagement is too long.  We will "expand" it with this.
 *
 */
function engagementsExpandBody(bodyid) {  
  var p = $("#" + bodyid);  
  p.css('max-height', '9999px');  // as big as it needs to be  
  
  $("#more_" + bodyid).hide();
  $("#less_" + bodyid).show();
  
  
}

function engagementsShrinkBody(bodyid) {  
  var p = $("#" + bodyid);  
  p.css('transition', 'max-height 200ms');  // hack to make it go faster. Need a more elegant solution
  p.css('max-height', '150px');  
  
  $("#less_" + bodyid).hide();
  $("#more_" + bodyid).show();
  
  
  p.css('transition', 'max-height 1s');
  
  
}




/**
 * Hides/shows/sets various fields, based on the engagement type and other values.
 */
function engagementsUpdateForm() {
  
  // The selected engagement type.
  var etype = $('#element-engagement_type').val();
  
  if (etype != 'phone') {
    $('#element-wrapper-phone_outcome').hide();
  }
  else {
    $('#element-wrapper-phone_outcome').show();
  }
  
  
  if (etype != 'email') {
    $('#element-wrapper-title').hide();
    $('#element-title').val('Engagement: ' + etype + ' for student ' + $('#element-student_id').val());    
  }
  else {
    $('#element-title').val(e_original_title_val);
    $('#element-wrapper-title').show();
  }
  
  
}












function engagementsNewEngagementDialog(etype, title, student_id, faculty_id) {
  
  var url = FlightPath.settings.basePath + "/index.php?q=content/add/engagement";
  
  var extra = "student_id=" + student_id;
  extra += "&faculty_id=" + faculty_id;
  extra += "&engagement_type=" + etype;
   
  
  popupLargeIframeDialog(url, title, extra);
  
}