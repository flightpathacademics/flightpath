/*
 * @file 
 * javascript file for prereqs module
*/


/**
 * The user is not allowed to toggle the selection.
 *  
 */
function prereqs_no_toggleSelection(uniqueID, display_status, warningMsg) {
  fp_alert(warningMsg);
  return false;
}


/* user cannot toggle selection from group */
function prereqs_no_group_toggleSelection(warningMsg) {
  fp_alert(warningMsg);
  return false;
}