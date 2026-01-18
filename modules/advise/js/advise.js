/* Javascript for the Advise module */


/**
 * Run this code on the page, after everything has finished loading.  
 */
$(document).ready(function() {

  // Update the varHours field for the selected course row in the group_select_popup screen.
  if ($('body').hasClass('page-advise-popup-group-select')) {
    
    // Run once on startup, in case we are returning to a screen with already selected options
    popupUpdateVarHoursBasedOnSelectedRadio();
    
    // we need to
    // attach a behavior the radio buttons for each course row, to call a function when they are
    // selected.  This function will update the varHours field, if that course has var hours.
    
    $(".page-advise-popup-group-select input.cb-course").change(function() {
      
      // Update the varHours field for the selected course row in the group_select_popup screen.
      popupUpdateVarHoursBasedOnSelectedRadio();
       
    });
    
    
  }
  
  
});



function popupUpdateVarHoursBasedOnSelectedRadio() {
  
  // Get all the radios on the page, to find out which (if any) is currently selected.
  // Then, set the varHours variable to THAT course's min_var_hours.
  
  var course_id = $(".page-advise-popup-group-select input.cb-course:checked").val();
  
  if (course_id != undefined && course_id > 0) {
    // Yes, a course_id was found.  Let's see if it has any min_var_hours selected.
    
    // Var hours?
    var min_var_hours = $("#" + course_id + "_min_var_hours").val();
    if (min_var_hours != undefined && min_var_hours != "") {

      $("#varHours").val(min_var_hours);
    }
    
  }
  
  
}




function changeTerm(term_id) {
  document.getElementById("advising_term_id").value = term_id;
  document.getElementById("log_addition").value = "change_term~" + term_id;
  
  // rebuild the cache.
  document.getElementById("load_from_cache").value="no";
  
  
  submitForm(true);
}


function changeView(view) {
  document.getElementById("advising_view").value = view;
  
  submitForm(true);
}

function z__popupChangeTrack(track_code) {
  var x = confirm("Are you sure you wish to change degree options?");
  if (x) {
    parent.changeTrack(track_code);
    fpCloseSmallIframeDialog();
  } 
}


function popupChangeWhatIfTrackNonDynamicDegree(major_and_track_code, question) {
  var x = confirm(question);
  if (x) {
    parent.document.getElementById("what_if_major_code").value = major_and_track_code;
    parent.document.getElementById("load_from_cache").value = "no";
    parent.document.getElementById("log_addition").value = "change_track~" + major_and_track_code;
    
    parent.submitForm(true);
    fpCloseSmallIframeDialog();
  }
}



/**
 * As a non-dynamic degree, we will add the track as an "editable" option, and we will know
 * to use THAT option when loading.
 **/
function changeTrackNonDynamicDegree(track_degree_ids) {
  
  document.getElementById("advising_update_student_degrees_flag").value = "true";
  document.getElementById("advising_track_degree_ids").value = track_degree_ids;    

  document.getElementById("log_addition").value = "change_track~" + track_degree_ids;
  
  // rebuild the cache.
  document.getElementById("load_from_cache").value="no";

  submitForm(true);
}



/**
 * This function is meant to replace the changeTrack and popupChangeWhatIfTrack functions.
 * It needs to handle multiple track additions, not just one at a time.
 * 
 * is_whatif is either 1 or 0
 * 
 */
function popupChangeTrackSelections(is_whatif) {
  
  /////////// Validation.  
  // We need to look through our degrees and make sure that
  // the user has selected between the correct range of min and max tracks.
   
  for (var key in FlightPath.settings) {
    
    var val = FlightPath.settings[key];
    
    var min_tracks = 0;
    var max_tracks = 0;
    
    if (key.indexOf("degree_min_max_tracks") > -1) {
      // Get the degree_id and track machine name from the key...
      var temp = str_replace("degree_min_max_tracks_", "", key).split("_split_");
      
      
      var degree_id = temp[0];
      var machine_name = temp[1];
      var degree_name = FlightPath.settings["degree_name_" + degree_id];
      var class_title = FlightPath.settings["class_title_" + machine_name];
      
      // Get the min and max tracks.
      var temp = val.split("~");
      min_tracks = temp[0];
      max_tracks = temp[1];
      
      
            
      // if the min and max are both 1 or 0, skip, since that is handled in the HTML.
      if (min_tracks == max_tracks && min_tracks == 1) continue;
      if (min_tracks == max_tracks && min_tracks == 0) continue;

      // Count how many are picked for THIS degree...           
      var c = 0;
      // Make sure the number selected for this degree does not exceed max_tracks.  If it does, fail.
      $("input[degree_id_class=" + degree_id + "_class_" + machine_name + "]:checked").each(function() {
        c++;
      })        
        
      
      
      // Is c > the max_tracks? or < than min_tracks?  If max_tracks is 0 that means "infinite"
      if ((c > max_tracks && max_tracks != 0) || c < min_tracks) {
        var msg = "Sorry, but you are only allowed to select between " + min_tracks + " and " + max_tracks + " " + class_title + " options for this degree (" + degree_name + ").\n\nPlease try again.";
        
        if (max_tracks == 0) {
          msg = "Sorry, but you must select at least " + min_tracks + " " + class_title + " option(s) for this degree (" + degree_name + ").\n\nPlease try again.";
        }
        
        fp_alert(msg);
        return;
      }


            
      
    } // if key contains degree_min_max_tracks
    
  } // for var in settings
  
  
  // We will look through the page for any checkboxes (or radio buttons) that have been checked.
  var track_degree_ids = ",";  // give it *something* initially, so it isn't an empty string and might get overlooked.
  $("input[is_editable=1]:checked").each(function() {
    track_degree_ids += ($(this).val()) + ",";
  });
  
  // Okay, we now have the track_degree_id's, separated by comma, that the user wishes to apply to
  // their degree plan.  We will now set our "opener" variables, submitForm, and close this popup window.
    
  if (is_whatif == 0) {
    // NOT what if mode.
    parent.document.getElementById("advising_update_student_degrees_flag").value = "true";
    parent.document.getElementById("advising_track_degree_ids").value = track_degree_ids;    
  }
  else {
    // Yes, this IS what-if mode.
    parent.document.getElementById("advising_update_student_settings_flag").value = "true";
    parent.document.getElementById("what_if_track_degree_ids").value = track_degree_ids;
    
    // Set the what_if_major_code to be only our top-level major codes, so we can change the tracks.  This is
    // to fix a bug where if you load an advising session directly what was what-if, the major codes include the tracks,
    // and you can't unselect tracks then.
    
    //return false;
    parent.document.getElementById("what_if_major_code").value = $("#top_level_majors_csv").val();
  }
  
  parent.document.getElementById("log_addition").value = "change_track~" + track_degree_ids;
  
  // rebuild the cache.
  parent.document.getElementById("load_from_cache").value="no";

  parent.submitForm(true);
  fpCloseSmallIframeDialog();
    
    
} // popupChangeTrackSelections






function alertSplitSub()
{
  var x = "";
  
  x = x + "You college advisor has chosen to split this course into ";
  x = x + "several pieces so that it can be subsituted more easily. ";
  x = x + "Your original course, as it appears on your transcript, has not ";
  x = x + "been altered.\n\n";
  x = x + "If you have any questions about why this course was split, ";
  x = x + "please ask your advisor.";
  
  fp_alert(x);
}

function alertSubAddition()
{
  var x = "";
  x = x + "By checking the \"Addition only\" box, you are ";
  x = x + "indicating you wish to simply add a course ";
  x = x + "to the elective group, and NOT perform a course-for-course substitution. ";
  x = x + "\n\nIf you are unsure which kind of substitution to make, ";
  x = x + "then check this box.";
      
  fp_alert(x);
}   

function alertSubGhost() {
  var x = "";
  x = x + "This course has a \"ghost hour\" associated with it. ";
  x = x + "This means that the student actually earned zero hours for this course, or that it is actually worth 0 hours, ";
  x = x + "but in order for FlightPath to use the course, it must internally be recorded ";
  x = x + "as being worth 1 hour.  This is a limitation of FlightPath, and should not ";
  x = x + "affect the student\'s GPA or other hour calculations.";
      
  fp_alert(x);

}



function popupUnassignTransferEqv(course_id) {
  var x = confirm("Are you sure you wish to remove this transfer course equivalency?\n\nThis action will only affect the current student.  It will not impact any other student\'s records.");
  if (x) {
    parent.unassignTransferEqv(course_id);
    fpCloseSmallIframeDialog();
  }
}


function unassignTransferEqv(course_id) {

  var hiddenElements = document.getElementById("hidden_elements");
  var e = document.createElement("input");
  e.setAttribute("name","unassign_transfer_eqv");
  e.setAttribute("type","hidden");
  e.setAttribute("value","" + course_id + "~" + "");
  hiddenElements.appendChild(e);      
  
  // rebuild the cache.
  document.getElementById("load_from_cache").value="no";
  
  submitForm(true);
  
}


function popupUnassignFromGroup(course_id, term_id, transferFlag, group_id, degree_id) {
  var x = confirm("Are you sure you wish to remove this course?");
  if (x)
  {
    parent.unassignFromGroup(course_id, term_id, transferFlag, group_id, degree_id);
    fpCloseSmallIframeDialog();
  }
}

 

function unassignFromGroup(course_id, term_id, transferFlag, group_id, degree_id) {

  var hiddenElements = document.getElementById("hidden_elements");
  var e = document.createElement("input");
  e.setAttribute("name","unassign_group");
  e.setAttribute("type","hidden");
  e.setAttribute("value","" + course_id + "~" + term_id + "~" + transferFlag + "~" + group_id + "~" + degree_id + "");
  hiddenElements.appendChild(e);      
  
  // rebuild the cache.
  document.getElementById("load_from_cache").value="no";
  
  
  submitForm(true);
  
}






/* Toggle an advising checkbox */
function toggleSelection(uniqueID, display_status, warningMsg) {
  // We expect this to be the graphic of the checkbox.
  
  //var imgPath = FlightPath.settings.themeLocation + "/images";
  
  var cb_span = $("#cb_span_" + uniqueID);
  // This is the hidden variable for this course, to
  // determine if it has been selected or not.
  var course = $("#advcr_" + uniqueID);

  if (course.val() == "true") {
    // Meaning, this course is currently selected.
    // So, unselect it.
    course.val("");
    //img.attr("src", imgPath + "/cb_" + display_status + ".gif");
    $(cb_span).removeClass("advise-checkbox-" + display_status + "-checked");
  } else {
    // Meaning, this is unselected, so lets select it.
    if (warningMsg != "") {
      var x = confirm(warningMsg);
      if (!x) {
        return;
      }
    }
    // Select it!
    
    course.val("true");
    //img.attr("src", imgPath + "/cb_" + display_status + "-check.gif");
    $(cb_span).addClass("advise-checkbox-" + display_status + "-checked");
    
  }
}


/* Similar to toggleSelection, except we always set it to checked. */
function setSelection(uniqueID, display_status) {
  var cb_span = $("#cb_span_" + uniqueID);
  // This is the hidden variable for this course, to
  // determine if it has been selected or not.
  var course = $("#advcr_" + uniqueID);

  // Reset to blank just in case
  course.val("");
  $(cb_span).removeClass("advise-checkbox-" + display_status + "-checked");
  
  // Now select it!
  
  course.val("true");
  $(cb_span).addClass("advise-checkbox-" + display_status + "-checked");
  
}





function z__toggleSelection(uniqueID, display_status, warningMsg) {
  // We expect this to be the graphic of the checkbox.
  
  var imgPath = FlightPath.settings.themeLocation + "/images";
  
  var img = $("#cb_" + uniqueID);
  // This is the hidden variable for this course, to
  // determine if it has been selected or not.
  var course = $("#advcr_" + uniqueID);

  if (course.val() == "true") {
    // Meaning, this course is currently selected.
    // So, unselect it.
    course.val("");
    img.attr("src", imgPath + "/cb_" + display_status + ".gif");
  } else {
    // Meaning, this is unselected, so lets select it.
    if (warningMsg != "") {
      var x = confirm(warningMsg);
      if (!x) {
        return;
      }
    }

    course.val("true");
    img.attr("src", imgPath + "/cb_" + display_status + "-check.gif");
    
  }
}

function dummyToggleSelection(x,y,z) {
  return;
}



/*
 * We are selecting a course to advise from a popup list of courses.  Normally, return
 * TRUE if there's no str.  If there is, it means it's a question we should ask.
 */
function adviseSelectCourseFromGroupPopup(str_confirm) {
  if (!str_confirm || str_confirm == "") {
    return true;
  }
  
  // else...
  return confirm(str_confirm); 
  
}



function describeCourse(dataString, blankDegreeID, dialogTitle) {
  //var url = FlightPath.settings.basePath + "/advise/popup-course-description";  
  
  
  // To make compatible with non-clean URL sites, we will use the "unclean" URL...
  var url = FlightPath.settings.basePath + "/index.php?q=advise/popup-course-description";    
  //popupWindowNew(url,"data_string=" + dataString + "&blank_degree_id=" + blankDegreeID);
  popupSmallIframeDialog(url, dialogTitle, "data_string=" + dataString + "&blank_degree_id=" + blankDegreeID);
  
  
}


function selectCourseFromGroup(group_id, semester_num, groupHoursRemaining, blankDegreeID, req_by_degree_id, dialogTitle) {
  //var url = FlightPath.settings.basePath + "/advise/popup-group-select";
  
  // To make compatible with non-clean URL sites, we will use the "unclean" URL...
  var url = FlightPath.settings.basePath + "/index.php?q=advise/popup-group-select";
    
  if (!dialogTitle) {
    dialogTitle = 'Select Course';
  }
    
  //popupWindowNew(url,"group_id=" + group_id + "&semester_num=" + semester_num + "&group_hours_remaining=" + groupHoursRemaining + "&blank_degree_id=" + blankDegreeID + "&req_by_degree_id=" + req_by_degree_id);
  popupSmallIframeDialog(url, dialogTitle, "group_id=" + group_id + "&semester_num=" + semester_num + "&group_hours_remaining=" + groupHoursRemaining + "&blank_degree_id=" + blankDegreeID + "&req_by_degree_id=" + req_by_degree_id);
}

/**
 * Specifically, this is for redirecting the poup to show a course description 
 * from a group.
 */
function popupDescribeSelected(group_id, semester_num, optionalCourseID, selectedSubject, extraVars)
{
  // This will go through the list of radio buttons
  // on a group select screen (of a popup window),
  // and look for the selected one, and then
  // switch over to a description.  Meant to be
  // called from clicking a tab. (The tabs onClick).

  var course_id = optionalCourseID;
  if (course_id < 1)
  { // CourseID wasnt specified, so try to figure it out...

    var cbs = document.getElementsByName("course");
    for (var t = 0; t < cbs.length; t++)
    {
      var cb = cbs[t];
      if (cb.checked == true)
      {
        // In other words, this course
        // was selected.
        course_id = cb.value;
        // Also attempt to figure out the selectedSubject, if
        // one has not been supplied.
        if (selectedSubject == "")
        {
          selectedSubject = document.getElementById("" + course_id + "_subject").value;
        }
        break;
      }
    }
  }

  // To make compatible with non-clean URLs, we need to always supply the "unclean" URL version.
  window.location = FlightPath.settings.basePath + "/index.php?q=advise/popup-group-select" + "&window_mode=popup&perform_action2=describe_course&course_id=" + encodeURI(course_id) + "&group_id=" + group_id + "&semester_num=" + semester_num + "&selected_subject=" + encodeURI(selectedSubject) + "&current_student_id=" + FlightPath.settings.currentStudentId + "&" + extraVars;

}


function popupBackToGroupSelect(course_id, group_id, semester_num, extraVars) {
  // This is meant to be called when switching back
  // from a course description tab, while in the
  // group select popup window.  So, this is like I
  // am reading a description of a course, then I click
  // the select tab to go back.  The course_id is the ID
  // of the course whose description I was just reading.
  
  // To make compatible with non-clean URLs, we need to always supply the "unclean" URL version.  
  window.location = FlightPath.settings.basePath + "/index.php?q=advise/popup-group-select" + "&window_mode=popup&course_id=" + course_id + "&group_id=" + group_id + "&semester_num=" + semester_num + "&current_student_id=" + FlightPath.settings.currentStudentId + "&" + extraVars;
}


function popupSubstituteSelected(course_id, group_id, semester_num, req_by_degree_id, extraVars)
{

  if (course_id < 1)
  { // CourseID wasnt specified, so try to figure it out...

    var cbs = document.getElementsByName("course");
    for (var t = 0; t < cbs.length; t++)
    {
      var cb = cbs[t];
      if (cb.checked == true)
      {
        // In other words, this course
        // was selected.
        course_id = cb.value;
        break;
      }
    }
  }

  // To make compatible with non-clean URLs, we need to always supply the "unclean" URL version.
  window.location = FlightPath.settings.basePath + "/index.php?q=advise/popup-substitute-selected" + "&window_mode=popup&course_id=" + encodeURI(course_id) + "&group_id=" + group_id + "&req_by_degree_id=" + req_by_degree_id + "&semester_num=" + semester_num + "&current_student_id=" + FlightPath.settings.currentStudentId + "&" + extraVars;
}


/**
 * Meant to be called from our group select popup.
 */
function popupAssignSelectedCourseToGroup(semester_num, group_id, advising_term_id, db_group_requirement_id) {

  //var var_hours = document.getElementById("varHours").value;
  var var_hours = $("#varHours").val();

  var c = document.getElementsByName("course");
  for (var t = 0; t < c.length; t++)
  {
    if (c[t].checked == true)
    { // Found users selection.
      var course_id = c[t].value;
      
      if (db_group_requirement_id == -1)
      {
        db_group_requirement_id = document.getElementById("" + course_id + "_db_group_requirement_id").value;
      }
      
           
      
      parent.assignSelectedCourseToGroup(course_id, semester_num, group_id, var_hours, advising_term_id, db_group_requirement_id);
      fpCloseSmallIframeDialog();
      
    }
  }

  return false;
}



function popupUpdateSubData(max_hours, term_id, transferFlag, groupHoursAvail, subCourseHours) {
  document.getElementById("subTermID").value = term_id;
  document.getElementById("subTransferFlag").value = transferFlag;
  // if the addition checkbox is checked, use the groupHoursAvail as
  // the max.
  
  // Force numbers to be floats (numeric) so they will be evaluated correctly.
  max_hours = parseFloat(max_hours);
  groupHoursAvail = parseFloat(groupHoursAvail);
  subCourseHours = parseFloat(subCourseHours);
    
  
  if (document.getElementById("cbAddition").checked == true)
  {
    max_hours = groupHoursAvail;
    if (max_hours > subCourseHours)
    {
      max_hours = subCourseHours;
    }
  }
        
  // Add some values to the global FlightPath object, for use later...
  FlightPath.globals = new Object();
  FlightPath.globals.maxHours = max_hours;
  FlightPath.globals.groupHoursAvail = groupHoursAvail;
  FlightPath.globals.subCourseHours = subCourseHours;
    
  
  var sel = document.getElementById("subHours");
  
  // Replace this pulldowns elements with a range of values from
  // max_hours to 1.
  
  // First, remove all existing options.
  sel.options.length = 0;

  sel.options[0] = new Option(" Max (default) ", max_hours, true, true);
  var c = 1;
  
  // Now, add in the others, but ONLY if we aren't dealing with any decimal values!
  // But, show the max_hours regardless...
  for (var t = max_hours; t > 0; t--)
  {
    
    sel.options[c] = new Option(" " + t + " ", t, false, false);
    c++;

    if (decimalPlaces(t) > 0) {
      break;
    }
    
  }

  
  // Add in the option for manual, decimal hours
  sel.options[c] = new Option(" Enter manual >", "manual");
  c++;
  
  
}

/* The user has changed the sub hours on the substitution popup */
function popupOnChangeSubHours() {
  // Get our selected hours selection, and look for "manual" to see if
  // we should prompt the user to select a new manual hours.
  var selectedVal = $("#subHours").val();
  if (selectedVal == "manual") {
    // Send the user to the manual hour entry...
    popupPromptSubManualHours()
  }
  else {
    // Hide the span showing the change link and value.
    $("#subManual").hide();
  }
  
}


/* Ask the user what hours they want, check for errors at the same time if possible */
function popupPromptSubManualHours() {
  // Existing manual sub hours?
  var manualHours = $("#subManualHours").val();
  if (manualHours == "" || manualHours == null) {
    manualHours = "1";
  }
  
  // We will look at the global values we set earlier.
  var maxHours = FlightPath.globals.maxHours;
  
  var subDecimalsAllowed = FlightPath.settings.subDecimalsAllowed;
  
  var newManualHours = $.trim(prompt("Please enter the hours to substitute manually, between 0 and " + maxHours + " hours.\n\nYou may enter decimal values. Ex: 2.5.\nNote: No more than " + subDecimalsAllowed + " decimal places are allowed.", manualHours));
  
  // Did they cancel?
  if (newManualHours == null || newManualHours == "") {
    $("#subHours").val(0); // set back to first index (max);
    $("#subManual").hide();
    return;
  }
  
  // Is the value non-numeric?
  if (!isNumeric(newManualHours)) {
    $("#subHours").val(0); // set back to first index (max);
    $("#subManual").hide();
    fp_alert("You entered a non-numeric value.  Please make sure you only enter numeric values.\nEx: 3, 2, 1.25");
    return;
  }
  
  newManualHours = newManualHours * 1;
  
  // Make sure the value falls within 0 to maxHours.
  // We will look at the global values we set earlier.
  var maxHours = FlightPath.globals.maxHours;
  if (newManualHours <= 0 || newManualHours > maxHours) {
    $("#subHours").val(0); // set back to first index (max);
    $("#subManual").hide();    
    fp_alert("Sorry, but the value you entered, " + newManualHours + ", isn't valid.\n\nMake sure you enter a number between 0 and " + maxHours + " for this substutution.");
    return;
  }
  
  // Make sure, if there are decimal places, there aren't too many entered.  If so, reject it.
  if (decimalPlaces(newManualHours) > subDecimalsAllowed) {
    fp_alert("Sorry, but you entered " + newManualHours + ", which has too many decimal places (only " + subDecimalsAllowed + " are allowed).\n\nPlease re-enter your substitution hours using the correct number of decimal places.");
    $("#subHours").val(0); // set back to first index (max);
    $("#subManual").hide();    
    return;
  }
  
  
  
  // It's good!  Let's store this number in our hidden variable, and display it in our span.
  $("#subManual").html("&nbsp;" + newManualHours + " - <a href='javascript:popupPromptSubManualHours()'>edit</a>");
  $("#subManual").show();
  $("#subManualHours").val(newManualHours);
  
  
}

// test if value is numeric or not.
function isNumeric(n) {
  return !isNaN(parseFloat(n)) && isFinite(n);
}

// From the Internet.  Get number of digits after decimal.
function decimalPlaces(num) {
  var match = (''+num).match(/(?:\.(\d+))?(?:[eE]([+-]?\d+))?$/);
  if (!match) { return 0; }
  return Math.max(
       0,
       // Number of digits right of decimal point.
       (match[1] ? match[1].length : 0)
       // Adjust for scientific notation.
       - (match[2] ? +match[2] : 0));
}


/* Saves a substitution from the popup */
function saveSubstitution(course_id, group_id, req_by_degree_id, semester_num, subCourseID, subTermID, subTransferFlag, subHours, subAddition, subRemarks) {
  

  var hiddenElements = document.getElementById("hidden_elements");
  var e = document.createElement("input");
  e.setAttribute("name","savesubstitution");
  e.setAttribute("type","hidden");
  e.setAttribute("value","" + course_id + "~" + group_id + "~" + req_by_degree_id + "~" + semester_num + "~" + subCourseID + "~" + subTermID + "~" + subTransferFlag + "~" + subHours + "~" + subAddition + "~" + subRemarks + "");
  hiddenElements.appendChild(e);      
  
  // rebuild the cache.
  document.getElementById("load_from_cache").value="no";
  
  submitForm(true);

}



/**
 * Meant to actually advise a course into a group.
 */
function assignSelectedCourseToGroup(course_id, semester_num, group_id, var_hours, advising_term_id, db_group_requirement_id)
{
  
  
  
    
  var_hours = var_hours * 1;
  var hiddenElements = document.getElementById("hidden_elements");
  var e = document.createElement("input");  
  // IMPORTANT!!!!!  //
  /////////////////////
  // We MUST use _'s here to separate values, due to the way this is being submitted (as a name of a variable)
  // However, we know that group_id probably contains a "_", separating the db_group_id from the degree_id.  So, let's 
  // replace _ with something else that is valid, just for this submission.
  group_id = str_replace("_", "U", group_id);  // replace _ with "U" for "underscore".  We will change it back in _FlightPath.php.
  
  // The "group" tells FlightPath to figure out the degree_id from the group_id var.
  e.setAttribute("name","advcr_" + course_id + "_" + semester_num + "_" + group_id + "_" + var_hours + "_r7" + "_" + advising_term_id + "_" + "group");
  
  // note, the "r7" above is taking the place of a "random number."  Since it's the only one being submitted, it doesn't
  // really need to be random.
  
  e.setAttribute("type","hidden");
  e.setAttribute("value","true");
  hiddenElements.appendChild(e);      
  submitForm(true);
  
}


function submitSaveActive() {
    document.getElementById("perform_action").value = "save_active";
    submitForm();
}

function submitForm(boolShowUpdating) {
  var scrollTop = fpGetScrollTop();
  
  document.getElementById("scroll_top").value = scrollTop;

  // Display an updating message...
  if (boolShowUpdating == true) {
    showUpdate(false); // function is in the template itself.          
  }
  
  var mainform = document.getElementById("mainform");
  mainform.submit();
}




/* For unselecting a course from a group.  We want to remove the selection, then submit the form. */
function toggleSelectionAndSave(uniqueID, display_status, warningMsg) {
  toggleSelection(uniqueID, display_status, warningMsg);
  submitForm(true);
}



function popupSaveSubstitution(course_id, group_id, semester_num, req_by_degree_id) {
  var subHours = document.getElementById("subHours").value;
  
  // If the subHours are "manual", then we will use the manual value instead.
  if (subHours == "manual") {
    subHours = $("#subManualHours").val();
  }
  
  var subCourseID = 0;
  var subAddition = "";
  if (document.getElementById("cbAddition").checked == true)
  {
    subAddition = "true";
  }
  
  var cbs = document.getElementsByName("subCourse");
  for (var t = 0; t < cbs.length; t++)
  {
    var cb = cbs[t];
    if (cb.checked == true)
    {
      // In other words, this course
      // was selected.
      subCourseID = cb.value;
    }
  }
  
  
        
  var subTermID = document.getElementById("subTermID").value;   
  var subTransferFlag = document.getElementById("subTransferFlag").value;   
  var subRemarks = document.getElementById("subRemarks").value;   
  
  // make sure the remarks do not have a ~ in them.
  subRemarks = str_replace("~", "_", subRemarks);

  

  if (subHours <= 0 || subCourseID == 0)
  {
    fp_alert("Please select a course to substitute.");
    return;
  }
  
  parent.saveSubstitution(course_id, group_id, req_by_degree_id, semester_num, subCourseID, subTermID, subTransferFlag, subHours, subAddition, subRemarks);
  fpCloseSmallIframeDialog();
  
}






function updateSelectedCourse(course_id, group_id, semester_num, varHours, random_id, advising_term_id, degree_id) {
  
      
  var hiddenElements = document.getElementById("hidden_elements");
  var e = document.createElement("input");
  e.setAttribute("name","updatecourse");
  e.setAttribute("type","hidden");
  e.setAttribute("value","" + course_id + "~" + group_id + "~" + semester_num + "~" + varHours + "~" + random_id + "~" + advising_term_id + "~" + degree_id);
  hiddenElements.appendChild(e);      
  
  submitForm(true);
  
}


function removeSubstitution(subID)
{
  var hiddenElements = document.getElementById("hidden_elements");
  var e = document.createElement("input");
  e.setAttribute("name","removesubstitution");
  e.setAttribute("type","hidden");
  e.setAttribute("value","" + subID + "~" + "");
  hiddenElements.appendChild(e);      
  
  // rebuild the cache.
  document.getElementById("load_from_cache").value="no";
  
  
  submitForm(true);
  
}


function restoreTransferEqv(db_unassign_transfer_id) {
  var hiddenElements = document.getElementById("hidden_elements");
  var e = document.createElement("input");
  e.setAttribute("name","restore_transfer_eqv");
  e.setAttribute("type","hidden");
  e.setAttribute("value","" + db_unassign_transfer_id + "~" + "");
  hiddenElements.appendChild(e);      
  
  // rebuild the cache.
  document.getElementById("load_from_cache").value="no";
  
  
  submitForm(true);
  
}

function restoreUnassignFromGroup(db_unassign_group_id)
{

  var hiddenElements = document.getElementById("hidden_elements");
  var e = document.createElement("input");
  e.setAttribute("name","restore_unassign_group");
  e.setAttribute("type","hidden");
  e.setAttribute("value","" + db_unassign_group_id + "~" + "");
  hiddenElements.appendChild(e);      
  
  // rebuild the cache.
  document.getElementById("load_from_cache").value="no";
  
  
  submitForm(true);
  
}


function popupDeleteAdvisingSession(advising_session_id,dt) {
  var x = confirm("Are you sure you wish to delete this advising session from date " + dt + "?\\n\\nThis action cannot be undone.");
  if (x)
  {    
    parent.removeSubstitution(subID);
    fpCloseSmallIframeDialog();
  }  
}


function popupRemoveSubstitution(subID) {

  var x = confirm("Are you sure you wish to remove this substitution?");
  if (x)
  {
    
    parent.removeSubstitution(subID);
    fpCloseSmallIframeDialog();
  } 
}

function popupRestoreTransferEqv(db_unassign_transfer_id) {

  
  parent.restoreTransferEqv(db_unassign_transfer_id);
  fpCloseSmallIframeDialog();
  
}

function popupRestoreUnassignFromGroup(db_unassign_group_id) {
  
  parent.restoreUnassignFromGroup(db_unassign_group_id);
  fpCloseSmallIframeDialog();

}

function popupSetVarHours() {
  var hid = document.getElementById("varHours");  
  var sel = document.getElementById("selHours");  
  hid.value = sel.value;
}


function popupUpdateSelectedCourse(course_id, group_id, semester_num, random_id, advising_term_id, degree_id) {
  var varHours = document.getElementById("varHours").value;

  parent.updateSelectedCourse(course_id, group_id, semester_num, varHours, random_id, advising_term_id, degree_id);
  fpCloseSmallIframeDialog();



}

function toggleDisabledChangeTerm(x,y,termDescription) {
  var t = "";
  t = t + "This course was advised for the " + termDescription + ". ";
  t = t + "It cannot be unselected from here.  Please first change the Currently Advising term to \"" + termDescription + "\"";
  t = t + " by clicking the <em>Advising Term</em> <i class='fa fa-pencil'></i> icon near the top of the page. ";

  fp_alert(t);
}

function toggleDisabledCompleted(x,y,type) {
  var t = "";
  if (type == "completed")
  {
    t = t + "The student has successfully completed this course. ";
    t = t + "To advise the student to retake this course, please select it from the \"Courses Added by Advisor\" box at the bottom of the screen.";
  } else if (type == "enrolled")
  {
    t = t + "The student is currently enrolled in this course. ";
    t = t + "To advise the student to retake this course, please select it from the \"Courses Added by Advisor\" box at the bottom of the screen.";
  }
  fp_alert(t);
}

function setVar(id, newValue) {
  document.getElementById(id).value = newValue
}


////////////////////////////////////////

function popupWindowNew(url, extraVars) {
  
  alert('deprecated.  Use dialog functions instead');
  return false;
  
  // Figure out the window's options from our settings, if they exist.
  var win_options = FlightPath.settings.popupAdviseWinOptions;
  if (!win_options) {
    win_options = "toolbar=no,status=2,scrollbars=yes,resizable=yes,width=460,height=375"; 
  }
  
  var myurl = url + "&window_mode=popup&current_student_id=" + FlightPath.settings.currentStudentId + "&" + extraVars
  
  
  //var my_windowx = window.open(myurl, "courseinfox" + FlightPath.settings.currentStudentId, win_options);
  //my_windowx.focus();  // make sure the popup window is on top.
  //openSmallDialog(myurl, 'hey');
  
  
}


function popupSmallIframeDialog(url, title, extraVars) {

  var theURL = url + "&window_mode=popup&current_student_id=" + FlightPath.settings.currentStudentId;
  if (extraVars) {
    theURL += "&" + extraVars;
  }
 
  fpOpenSmallIframeDialog(theURL, title);
  
} 


function popupLargeIframeDialog(url, title, extraVars) {

  var theURL = url + "&window_mode=popup&current_student_id=" + FlightPath.settings.currentStudentId;
  if (extraVars) {
    theURL += "&" + extraVars;
  }

  
  fpOpenLargeIframeDialog(theURL, title);
  
} 




function popupPrintWindow(url) {
  
  alert('deprecated.  Use dialog functions instead');
  return false;
  
  
  // Figure out the window's options from our settings, if they exist.
  var win_options = FlightPath.settings.popupPrintWinOptions;
  if (!win_options) {
    win_options = "toolbar=no,status=2,scrollbars=yes,resizable=yes,width=750,height=600"; 
  }
    
  var my_windowx2p = window.open(url + "&window_mode=popup&current_student_id=" + FlightPath.settings.currentStudentId,
  "courseinfoxprint" + FlightPath.settings.currentStudentId,win_options);

  my_windowx2p.focus();  // make sure the popup window is on top.

}







function popupHelpWindow(url) {
  
  alert('deprecated.  Use dialog functions instead');
  return false;
  
  
  // I don't think this function is used anymore.  Re-route to the popupPrintWindow instead.
  popupPrintWindow(url);
}



// function popupWindow(action, extraVars) {
//   
  // var my_windowx = window.open("' . $script_filename . '?windowMode=popup&performAction=" + action + "&current_student_id=" + csid + "&" + extraVars,
  // "courseinfox" + csid,"toolbar=no,status=2,scrollbars=yes,resizable=yes,width=460,height=375");
// 
  // my_windowx.focus();  // make sure the popup window is on top.
// 
// }




function popupWindow2(url, extraVars) {
  
  alert('deprecated.  Use dialog functions instead');
  return false;
  
  
  // I don't think this is used, re-route to popupWindowNew just in case.
  popupWindowNew(url, extraVars);

}




