
$(document).ready(function() {
  
  
  // go through ALL and enable/disable
  $(".school-override-cb").each(function() {
    schoolsDisableEnableFieldFromOverride($(this).attr('id'));
  });
  
  // Set to check on change for these elements as well
  $(".school-override-cb").change(function() {
        
    schoolsDisableEnableFieldFromOverride($(this).attr('id'));
    
  });
});



function schoolsDisableEnableFieldFromOverride(id) {
  
  var ischecked = $("#" + id).is(':checked');
  var name = $("#" + id).attr('name');
  
  var temp = name.split("school_override__");
  var fieldname = temp[1];
    
  var css_fieldname = str_replace("~~", "_", fieldname);
  
  if (ischecked === true) {
    // Meaning, we are indeed going to override, so enable the field.
    $("#element-" + css_fieldname).removeAttr('readonly');
    $("#element-" + css_fieldname).removeClass('disable-element');
  }
  else {    
    // Meaning, disable this field!
    $("#element-" + css_fieldname).attr('readonly','readonly');
    $("#element-" + css_fieldname).addClass('disable-element');
  }
  
  
} // schoolsDisableEnableFieldFromOverride






function schoolsConfirmChangeSchool(old_school_id) {
  var x = confirm("Are you sure you wish to change the school?  This might cause problems with users, courses, groups, or degrees that reference the previously selected school value.  Only change if you are sure of what you are doing.  If unsure, click Cancel/No.");
  
  if (!x) {  
    // change back to whatever the orginal school_id was.
    $("#element-schools_school").val(old_school_id);   
  }
  
  
}
