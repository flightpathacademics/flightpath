/**
 * @file
 * This javascript file is only meant to run on the What If selection screen.  
 */


// run on page startup...
$(document).ready(function() {
  
  
  // We want to start by updating the visibility of certain sections based on
  // what Majors or Minors have been selected.
  
  // loop through them all elements, hide or show as needed.
  adviseWhatIf_UpdateBasedOnSelections();
 
  // We also want to call that function when we make a change to a level 1 or level 2 checkbox.
  $(".form-checkboxes-select_level_1_degrees input[type=checkbox], .form-checkboxes-select_level_2_degrees input[type=checkbox]").change(function() {
    adviseWhatIf_UpdateBasedOnSelectionOf(this);
  });
  
  
});  //document.ready



/**
 * Loop through all elements, hide or show as needed. 
 */
function adviseWhatIf_UpdateBasedOnSelections() {
  $(".form-checkboxes-select_level_1_degrees input[type=checkbox], .form-checkboxes-select_level_2_degrees input[type=checkbox]").each(function(i, element) {
    adviseWhatIf_UpdateBasedOnSelectionOf(element);
  });  
}


/**
 * Looks at a specific element to decide what to do. 
 * @param {Object} element
 */
function adviseWhatIf_UpdateBasedOnSelectionOf(element) {
  
  var is_checked = false;
  
  // If we have selected/unselected a degree which has tracks, then show/hide those options.
  
  if ($(element).is(":checked")) {
    var is_checked = true;
    
  }
   
  var major_code = $(element).val();
  
  var css_major_code = fp_get_machine_readable(major_code);
  
  
  // We want all the wrappers that END with our major_code.
  // The exact format will look like   
  // id = element-wrapper-L3_sel_CLASSTYPE_for_MAJORCODE_xx
  // jquery to see if element ENDS with string.... $("[id$=ENDING_STRING]")
  if (is_checked) {
    // Make visible...
    $("[id$=__for__" + css_major_code + "__xx]").show();
    
  }
  else {    
    $("[id$=__for__" + css_major_code + "__xx]").hide();
  }
  
  
  
} // update based on selections