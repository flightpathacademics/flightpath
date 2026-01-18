<?php

/**
 * This file contains functions which related only to the Advise module's "Administrator's Toolbox",
 * meant to be displayed in a popup window.
 * 
 * This file is loaded from the advise module's hook_menu as it's needed.
 */

 
function advise_display_popup_toolbox_transfers() {
  global $screen, $current_student_id;  
  advise_init_screen();      
  fp_add_js(fp_get_module_path("advise") . "/js/advise.js");   
  $rtn = "";
  
  $rtn .= $screen->display_toolbox_transfers();   
  
  return $rtn;
}

function advise_display_popup_toolbox_substitutions() {
  global $screen, $current_student_id;  
  advise_init_screen();
  fp_add_js(fp_get_module_path("advise") . "/js/advise.js");
  
  $rtn = "";
  
  $rtn .= $screen->display_toolbox_substitutions();   
  
  return $rtn;
}

function advise_display_popup_toolbox_moved() {
  global $screen, $current_student_id;  
  advise_init_screen();
  fp_add_js(fp_get_module_path("advise") . "/js/advise.js");
  
  $rtn = "";
   
  $rtn .= $screen->display_toolbox_moved();   
  
  return $rtn;
} 

function advise_display_popup_toolbox_courses() {
  global $screen, $current_student_id;  
  advise_init_screen();
  fp_add_js(fp_get_module_path("advise") . "/js/advise.js");
  
  $rtn = "";
  
  $rtn .= $screen->display_toolbox_courses();   
  
  return $rtn;
}