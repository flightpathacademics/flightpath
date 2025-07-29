<?php
/*
* This include file contains functions pertaining to the
* creation of forms through FlightPath's form API 
*/





/**
 * This is very similar to fp_get_form / fp_render_form, except in this case we are being passed
 * the completed "render_array", which already contains all of our elements.  We will call
 * hooks on it, sort by weights, and then return the rendered HTML.
 */
function fp_render_content($render_array = array(), $bool_include_wrappers = TRUE) {

  $rtn = "";

  if (!isset($render_array["#id"])) {
    // An #id wasn't set, which is required, so we're going to 
    // create one based on the function that called this function.
    $x = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $render_array['#id'] = trim(@$x[1]['class'] . "_" . @$x[1]['function']);
    // If nothing was discovered, just ditch it...
    if ($render_array['#id'] == "_") unset($render_array["#id"]);
  }

  // First, check to see if this render array has an "#id" field defined.  This is required.
  $render_id = fp_get_machine_readable(fp_trim(@$render_array["#id"]));
  if ($render_id == "") {
    fp_add_message(t("The render array supplied does not have an #id field set.  This must be
                     machine-readable and unique.<br>Ex:  \$arr['#id'] = 'advise_course_description_popup';
                     <br>It may be easiest to simply name it after the function which is creating the render array."), "error");
    return "";
  }

  $class = trim((string) @$render_array['#class']);

  // Any hooks to alter this render array?  
  $modules = modules_implement_hook("content_alter");
  foreach ($modules as $module) {
    call_user_func_array($module . '_content_alter', array(&$render_array, $render_id));
  }

  // Okay, now the fun part.  Re-order the elements by weight.  Lighter weights
  // should float to the top.  Elements w/o a weight listed are assumed to have a weight of 0.
  // Unfortunately we cannot use uasort, as it re-orders our indexes when weights are identical.
  
  
  // The first the I want to do is find out, what are the defined weights in this form, if any.
  $defined_weights = array();
  foreach ($render_array as $key => $element) {
        
    // If we were just passed a string, treat it as plain HTML markup.
    if (!strstr($key, "#") && is_string($element)) {
      $element = array('value' => $element);
      $render_array[$key] = $element;  // make sure we pick up any changes we've made      
    }      
    
    if (!is_array($element)) {
      continue;
    }    
    
    if (!isset($element["weight"])) {
      $element["weight"] = 0;      
    }
    
    
    
    
    
    $weight = (float)$element["weight"];
    if (!in_array($weight, $defined_weights)) {
      $defined_weights[] = $weight;
    }
  } 
  
  // Okay, now sort our weights.
  sort($defined_weights);
  
  
  // Before we get to assigning weights, we need to make sure
  // that none of our form elements have a name which might cause us trouble.
  // Namely, no element can be named "submit" (like a button) because it will
  // interfere with our javascript functions.
  $form2 = array();
  foreach ($render_array as $key => $element) {
    $name = $key;
    if ($name == "submit") {
      $name = "btn_submit";
    }
    
    $form2[$name] = $element;    
  }
  
  $form = $form2;
  
   
  // Okay, now go through the weights and create a new form in THAT order.
  $new_form = array();
  foreach ($defined_weights as $dw) {
    foreach ($form as $key => $element) {
        
      if (!is_array($element)) {
        $new_form[$key] = $element;
        continue;
      }
      
      if (!isset($element["weight"])) $element["weight"] = 0;
      $weight = (float)$element["weight"];
      if ($weight == $dw) {
        $new_form[$key] = $element;
      }      
    }
  }  

  // Okay, we should now be good to go!  
  $render_array = $new_form;
  
  // We can now proceed with rendering this render_array.  It will be similar to fp_render_form.
  if ($bool_include_wrappers) {
    $rtn .= "<div class='renderapi-content $class' id='render-$render_id'>";
  }
  
  $rtn .= fp_render_array($render_array);
  
  if ($bool_include_wrappers) {
    $rtn .= "</div>";
  }
  
  return $rtn;
  
}



/**
 * This takes a render_array and generates the HTML for it.  This usually is not called directly, but
 * instead you should call fp_render_content() or fp_render_form()
 */
function fp_render_array($render_array, $use_callback = "") {
  $rtn = "";
  
  foreach ($render_array as $name => $element) {
    
    
    
    if (is_array($element) && (isset($element["type"]) || isset($element["value"]))) {
        
      // Is this a cfieldset (collapsible fieldset)?
      if (@$element["type"] == "cfieldset") {        
        $celements = $element["elements"];  // get our list of form elements within this fieldset.
        // Go through these new elements and prepare to display them inside a collapsible fieldset.        
        $html = "";
        foreach ($celements as $k => $v) {
          foreach ($celements[$k] as $name => $celement) {            
            $html .= fp_render_element($name, $celement, $use_callback);
          }
        }        
        // add to c_fieldset
        $rtn .= fp_render_c_fieldset($html, @$element["label"], @$element["start_closed"], @$element['attributes']['class']);
         
      }
      else {
        // No, this is a normal element.  Not in a fieldset.
        $rtn .= fp_render_element($name, $element, $use_callback);
      }
      
      
      
    }    
  }  
  
  return $rtn;
  
}





/**
 * This function gets the form array, where the callback is the same as form_id.
 * It will also look for modules which may want to alter the form, using hook_form_alter,
 * and go ahead and apply that.
 * 
 * It will also reorder the elements by weight.
 * 
 */ 
function fp_get_form($form_id, $params = array()) {

  $form = call_user_func_array($form_id, $params);
  
  // Add in the default submit_handlers and validate_handlers, if not all ready set.
  if (!isset($form["#submit_handlers"])) $form["#submit_handlers"] = array($form_id . "_submit");
  if (!isset($form["#validate_handlers"])) $form["#validate_handlers"] = array($form_id . "_validate");
  
  $modules = modules_implement_hook("form_alter");
  foreach ($modules as $module) {
    call_user_func_array($module . '_form_alter', array(&$form, $form_id));
  }

  // Okay, now the fun part.  Re-order the elements by weight.  Lighter weights
  // should float to the top.  Elements w/o a weight listed are assumed to have a weight of 0.
  // Unfortunately we cannot use uasort, as it re-orders our indexes when weights are identical.

  // The first the I want to do is find out, what are the defined weights in this form, if any.
  $defined_weights = array();
  foreach ($form as $element) {
    if (!isset($element["weight"])) $element["weight"] = 0;
    $weight = (int)$element["weight"];
    if (!in_array($weight, $defined_weights)) {
      $defined_weights[] = $weight;
    }
  } 
  
  // Okay, now sort our weights.
  sort($defined_weights);
  
  
  // Before we get to assigning weights, we need to make sure
  // that none of our form elements have a name which might cause us trouble.
  // Namely, no element can be named "submit" (like a button) because it will
  // interfere with our javascript functions.
  $form2 = array();
  foreach ($form as $key => $element) {
    $name = $key;
    if ($name == "submit") {
      $name = "btn_submit";
    }
    
    $form2[$name] = $element;    
  }
  
  $form = $form2;
  
   
  // Okay, now go through the weights and create a new form in THAT order.
  $new_form = array();
  foreach ($defined_weights as $dw) {
    foreach ($form as $key => $element) {
      if (!isset($element["weight"])) $element["weight"] = 0;
      $weight = (int)$element["weight"];
      if ($weight == $dw) {
        $new_form[$key] = $element;
      }      
    }
  }  

  // Okay, we should now be good to go!
      
  return $new_form;
  
}

/**
 * Render the form array from the callback to the screen, and
 * set the form to save itself in our default submit handler.
 * Valid form_types are:
 * "system_settings" => values automatically saved to variables table.
 * "normal" or BLANK => values are sent to {$callback}_validate() and {$callback}_submit() function, if it exists.
*/
function fp_render_form($callback, $form_type = "") {
  global $current_student_id, $user;
  $rtn = "";
  
  // Were there extra params after callback and form_type?  Wrap them up
  // and send them along to fp_get_form
  $params = array();
  if (func_num_args() > 2) {
    // Remove first 2 arguments, so all we have left is what the user added to it.
    $params = func_get_args();
    array_shift($params);
    array_shift($params);    
  }
  
  $form = fp_get_form($callback, $params);
  
  // Base64 enc the params, so we can easily handle if there are quotation marks, line breaks, etc.
  $form_params = base64_encode(serialize($params));
  
  // Figure out the current page's title and display it.
  $path = fp_no_html_xss($_GET["q"]);    // Sanitize _GET valuses.
  
  $default_path = $path;
  $default_query = "";
  // Figure out the "default_query" from $_GET
  $new_query = array();
  foreach ($_GET as $key => $val) {
        
    // Sanitize since it is coming from _GET.  
    $key = fp_no_html_xss($key);
    $val = fp_no_html_xss($val);
      
    
    if ($key != "q" && $key != "scroll_top") {
      $new_query[] = "$key=$val"; 
    }
  }
  if (count($new_query)) {
    $default_query = join("&", $new_query);
  }
  
  $page_title = $GLOBALS["fp_current_menu_router_item"]["title"];
  if (isset($GLOBALS["fp_set_title"])) {
     $page_title = $GLOBALS["fp_set_title"];
  }
  
  if ($page_title != "") {
    fp_show_title(TRUE);
  }
    
  $form_path = $GLOBALS["fp_current_menu_router_item"]["path"];
    
  // Are there any files required to get to the submit handler for this form?
  $form_include = "";  
  // Set the form_include to the current page's "file" requirement, if any.
  if (is_array($GLOBALS["fp_current_menu_router_item"])) {
    if (isset($GLOBALS["fp_current_menu_router_item"]["file"])) {
      $form_include = $GLOBALS["fp_current_menu_router_item"]["file"];
    }
  }  
  if (@$form["#form_include"]) {
    $form_include = $form["#form_include"];
  }
    
  $extra_form_class = "";
  if ($form_type == "system_settings") {
    $extra_form_class = "fp-system-form";
  }
    
  $form_token = md5($callback . fp_token());     
    
  // Set up our form's attributes.
  $attributes = @$form["#attributes"];
  if (!is_array($attributes)) $attributes = array();

  if (!isset($attributes["class"])) $attributes["class"] = "";
  $attributes["class"] .= " $extra_form_class fp-form fp-form-$callback ";
  // Convert the attributes array into a string.
  $new_attr = "";
  foreach ($attributes as $key => $val) {
    $new_attr .= " $key='$val' ";
  }
  $attributes = $new_attr;
    
  // Did the user specify a submit method (like GET or POST)?  POST is default.
  $submit_method = (@$form["#submit_method"] == "") ? "POST" : $form["#submit_method"];
  
  // If the window_mode has been set in the _GET, then include it as a query in the fp_url() function.  But force it to either be "popup" or another valid option.
  $url_query = "";
  if (isset($_GET['window_mode']) && $_GET['window_mode'] == 'popup') {    
    $url_query = "window_mode=popup";
  }
  
  $form_q_64 = base64_encode($_REQUEST['q']);  // what was q set to when this form was rendered?
      
  $rtn .= "<form action='" . fp_url("system-handle-form-submit", $url_query, TRUE) .  "' method='$submit_method' id='fp-form-$callback' name='fp_form_name_$callback' $attributes>";
  
  $rtn .= "<input type='hidden' name='callback' value='$callback'>";
  $rtn .= "<input type='hidden' name='form_token' value='$form_token'>";
  $rtn .= "<input type='hidden' name='form_type' value='$form_type'>";
  $rtn .= "<input type='hidden' name='form_path' value='$form_path'>";
  $rtn .= "<input type='hidden' name='form_q_64' value='$form_q_64'>";
  $rtn .= "<input type='hidden' name='form_params' value='$form_params'>";
  $rtn .= "<input type='hidden' name='form_include' value='$form_include'>";
  $rtn .= "<input type='hidden' name='default_redirect_path' value='$default_path'>";
  $rtn .= "<input type='hidden' name='default_redirect_query' value='$default_query'>";
  $rtn .= "<input type='hidden' name='current_student_id' value='$current_student_id'>";
  
  /* // Note: This bit doesn't make a difference right now, because it's only in the "render_form" function.
   * // All of these values need to be in the "get_form" function, in order for them to be detected if someone tries
   * // to change them.
  // Instead of using hidden input types (which can be modified by malicious actors),
  // we will use "values" added to the form itself.
  $form['callback'] = ['type' => 'value', 'value' => $callback];
  $form['form_token'] = ['type' => 'value', 'value' => $form_token];
  $form['form_type'] = ['type' => 'value', 'value' => $form_type];
  $form['form_path'] = ['type' => 'value', 'value' => $form_path];
  $form['form_q_64'] = ['type' => 'value', 'value' => $form_q_64];
  $form['form_params'] = ['type' => 'value', 'value' => $form_params];
  $form['form_include'] = ['type' => 'value', 'value' => $form_include];
  $form['default_redirect_path'] = ['type' => 'value', 'value' => $default_path];
  $form['default_redirect_query'] = ['type' => 'value', 'value' => $default_query];
  $form['current_student_id'] = ['type' => 'value', 'value' => $current_student_id];
  */
  
  
  
  $use_callback = "";
  if (form_has_errors()) {
    // We will only pull previous POST's values if there are errors on the form.
    $use_callback = $callback;
  }
  
  
  $rtn .= fp_render_array($form, $use_callback);
      

  // If this is a system settings form, go ahead and display the save button.
  if ($form_type == "system_settings") {      
    $rtn .= "<div class='buttons form-element element-type-submit'>";
    $rtn .= "<input type='submit' name='submit_button' value='" . t("Save settings") . "'>";
    $rtn .= "</div>";      
  }
  
  
  $rtn .= "</form>";
      

  // Clear any existing form errors and values
  unset($_SESSION["fp_form_errors"]);
  clear_session_form_values($callback);
      
  return $rtn;
  
}


/**
 * Clear the form submissions variable from the SESSION for this callback.
 */
function clear_session_form_values($callback) {
  unset($_SESSION["fp_form_submissions"][$callback]);  
}



/**
 * This is a very basic validator for form API submission.
 * All I really care about is making sure required fields have
 * a value in them.  If they do not, we will file a form_error.
 */
function form_basic_validate($form, $form_state) {
  
  foreach ($form as $name => $element) {
    if (is_array($element) && @$element["required"]) {
      // Okay, this is a required field.  So, check that it has a non-blank value
      // in form_submitted.
      if ($form_state["values"][$name] == "") {
        // It's blank!  ERROR!
        $label = $element["label"];
        if ($label == "") $label = $name;
        form_error($name, t("You must enter a value for <b>%element_label</b>.", array("%element_label" => $label)));
      }
    } // if is_array element and required
    
    if (is_array($element) && isset($element['type']) && $element['type'] == 'value') {
      
      $should_be_value = @trim($form[$name]['value']);
      $found_value = @trim($form_state['values'][$name]);
      
      if ($should_be_value !== $found_value) {
        form_error('', t("Invalid value found in form, possible hacking attempt or bug.  This incident has been logged.", array("%element_label" => $label)));
        watchdog('system', "Invalid value found in form, possible hacking attempt or bug.  Should be value: '$should_be_value', Found value: '$found_value' ", array(), WATCHDOG_ERROR);
      }      
      
    } // if element is of type "value"
    
    
    
  }
  
  
  
  
  
}


/**
 * Register a form_error in the SESSION.
 * 
 * If bool_stop_futher_validators is set to TRUE, then (in system_handle_form_submit) we will
 * not continue with any other form validators which may be present.
 * 
 */
function form_error($element_name, $message, $bool_stop_further_validators = FALSE) {
  
  $_SESSION["fp_form_errors"][] = array("name" => $element_name, "msg" => $message);
  fp_add_message($message, "error");
  
  if ($bool_stop_further_validators) {
    $GLOBALS['form_error_stop_further_validators'] = TRUE;
  }
  
  
}


/**
 * Returns TRUE or FALSE if there have been errors for this form submission
 * (We will just look in the SESSION to find out).
 */
function form_has_errors() {
  
  if (!isset($_SESSION["fp_form_errors"]) || !is_array($_SESSION["fp_form_errors"])) return FALSE;
  
  if (@count($_SESSION["fp_form_errors"]) > 0) {
    return TRUE;
  }
  
  return FALSE;
}






/**
 * Returns the HTML to render this form (or content) element to the screen.
 * $name is the HTML machine name.  $element is an array containing all we need to render it.
 * If you want default values to be taken from the SESSION (because we had form_errors, say, and we
 * want values to keep what we had between submissions) specify the callback to use in the
 * use_session_submission_values_for_callback variable.
 */
function fp_render_element($name, $element, $use_session_submission_values_for_callback = "") {
  $rtn = "";

  $type = @$element["type"]; 
  if ($type == "") $type = "markup_no_wrappers"; 
   
  // Make sure the "css name" is friendly.
  $cssname = fp_get_machine_readable($name);
  
  if ($type == "do_not_render") return;  // not supposed to render this element.
  
  // Does the name start with a # character?  If so, do not attempt to render.
  if (substr($name, 0, 1) == "#") return;  
    
  $value = @$element["value"];
  $label = @$element["label"];
  $options = @$element["options"];
  $description = @$element["description"];  
  $popup_description = @$element["popup_description"];
  $prefix = @$element["prefix"];
  $suffix = @$element["suffix"];
  $multiple = @$element["multiple"];
  $spinner = @$element["spinner"];
  if ($multiple == TRUE) {
    $multiple = "multiple=multiple";
  }
  else {
    $multiple = "";
  }
  
  $autocomplete_path = @$element["autocomplete_path"];
  $required = @$element["required"];
  $no_please_select = @$element["no_please_select"];
  if (isset($element["hide_please_select"])) {
    $no_please_select = @$element["hide_please_select"];
  }
  $confirm = @$element["confirm"];

  // Let's also add our cssname as a class to the element, so that even markup will get it...
  if ($type == "markup") {  
    if (!isset($element["attributes"]) || is_array($element["attributes"])) {        
      @$element["attributes"]["class"] .= " markup-element form-element markup-element-$cssname";
    }
  }
  


  $attributes = @$element["attributes"];
  if (!is_array($attributes)) {
    $attributes = array();
    $attributes['style'] = '';
    $attributes['class'] = '';    
  }
  
  if ($type == 'textarea_editor') {
    // Add the "html-editor" class to attributes.    
    $attributes['class'] .= ' html-editor';
  }
  

  if ($spinner) {
    $attributes['class'] .= " show-spinner ";
  }
  

  $popup_help_link = "";
  if ($popup_description) {        
    
    //$popup_help_link = " <a href='javascript: alert(\"" . $popup_description . "\");' class='form-popup-description'>[?]</a>";
    $popup_help_link = fp_get_js_alert_link($popup_description, NULL, "form-popup-description");
  }

  $element_error_css = "";
  if (isset($_SESSION["fp_form_errors"]) && is_array($_SESSION["fp_form_errors"])) {
    foreach ($_SESSION["fp_form_errors"] as $err) {
      if ($err["name"] == $name) {
        // There is an error on this element!  Add an extra CSS element.
        $element_error_css .= " form-element-error ";
      }
    }
  }

  if ($use_session_submission_values_for_callback && is_array(@$_SESSION["fp_form_submissions"][$use_session_submission_values_for_callback]["values"])) {
    // Check the SESSION for a previous value which we should use.
    $ignore_types = array("hidden", "markup", "markup_no_wrappers", "submit", "password");
    if (!in_array($type, $ignore_types)) {
      $value = $_SESSION["fp_form_submissions"][$use_session_submission_values_for_callback]["values"][$name];
    }    
  }


  if ($type == "markup" && $element_error_css) {
    if (!isset($attributes)) {
      $attributes = array();
    }
    if (is_array($attributes)) {
      $attributes['class'] .= $element_error_css;
    }
  }


  $extra_wrapper_class = "";  // We will give the wrapper a similar class as are defined in attributes, if any.
  if (is_array($attributes)) {
    // Convert the attributes array into a string.
    
    $new_attr = "";
    foreach ($attributes as $key => $val) {
      $new_attr .= " $key='$val' ";
      if ($key == 'class') {
        $extra_wrapper_class .= " element-wrapper--" . trim($val);
      }
    }
    $attributes = $new_attr;
  }  

  



  
  
  
  if ($type != "markup" && $type != "markup_no_wrappers" && $type != 'value') {
    $rtn .= "<div id='element-wrapper-$cssname' class='form-element element-type-$type $extra_wrapper_class'>";
  }

  if ($prefix) {
    $rtn .= $prefix;
  }


  if ($type != "markup" && $type != "markup_no_wrappers" && $type != 'value') {
    $rtn .= "<div id='element-inner-wrapper-$cssname' class='form-element element-type-$type $element_error_css'>";
  }


  if ($type == 'datetime-local') {
    // As of the time of this comment (8-16-2021) FireFox STILL does not support datetime-local as a field type for their desktop browser,
    // even though they support it for mobile.  Every other modern browser supports it as well.
    // Anyway, we will need to include a workaround in jquery as a result if this field is being used:
    fp_add_js(fp_get_module_path("system") . '/lib/jquery.datetimepicker/jquery.datetimepicker.min.js');
    fp_add_css(fp_get_module_path("system") . '/lib/jquery.datetimepicker/jquery.datetimepicker.min.css');
    
    fp_add_js(fp_get_module_path("system") . '/lib/fp_datetimepicker_shim/fp_datetimepicker_shim.js');
  }



  $ast = "";
  if ($required) {
    $ast = "<span class='form-required-ast'>*</span>";
  }

  // First of all, what is it's "type"?
  if ($type == "markup") {
    if (is_string($attributes) && $attributes != "") {
      $rtn .= "<div $attributes>";
    }
    
    // If a label is set, go ahead and display, even though its markup...
    if ($label != "") {
      $rtn .= "<label>$ast$label$popup_help_link</label>";
    }     
    
    $rtn .= $value;
    
    if (is_string($attributes) && $attributes != "") {
      $rtn .= "</div>";
    }
  }
  else if ($type == "markup_no_wrappers") {

    // If a label is set, go ahead and display, even though its markup...
    if ($label != "") {
      $rtn .= "<label>$ast$label$popup_help_link</label>";
    }     
    
    $rtn .= $value; // plain value, no wrapper divs at all.
  }
  else if ($type != "hidden" && $type != 'value' && $type != "checkbox") {
    $rtn .= "<label>$ast$label$popup_help_link</label>";
  }

  if ($type == "textarea" || $type == 'textarea_editor') {
    $rows = (isset($element["rows"])) ? $element["rows"] : "5";
    $maxlength = (isset($element["maxlength"])) ? $element["maxlength"] : "";
    $extra_span = "";
    // if maxlength is set, then we want to show the char count upon change.
    if ($maxlength != "") {
      fp_add_css(fp_get_module_path('system') . '/css/style.css');
      fp_add_js(fp_get_module_path('system') . '/js/textarea-maxlength.js');
      $extra_span = "<span class='textarea-maxlength-count' id='textarea-maxlength-count___$cssname'>
                          <span class='current-count' id='element-{$cssname}__current_count'>0</span>/<span class='maxlength-chars'>$maxlength</span>
                          <span class='maxlength-description'>" . t("Max Characters") . "</span>
                      </span>";

    }

    $rtn .= "<textarea name='$name' id='element-$cssname' rows='$rows' maxlength='$maxlength' $attributes>$value</textarea>$extra_span";
  }
 
  if ($type == "textfield" || $type == "text" || $type == "search" || $type == "password" || $type == 'datetime-local' || $type == 'time' || $type == 'date') {
    if ($type == "textfield") $type = "text";
    $size = (isset($element["size"])) ? $element["size"] : "60";
    $maxlength = (isset($element["maxlength"])) ? $element["maxlength"] : "255";
        
    // if there is an autocomplete_path, we need to include some javascript.
    if ($autocomplete_path != "") {
      fp_add_js(array("autocomplete_fields" => array(array("id" => "element-$cssname", "path" => $autocomplete_path))), 'setting');            
    }  
        
    $value = htmlentities((string) $value, ENT_QUOTES);        
    $rtn .= "<input type='$type' name='$name' id='element-$cssname' size='$size' maxlength='$maxlength' value='$value' $attributes>";
  }
  
  if ($type == "hidden" || $type == "value") {
    if (!$value) $value = '';  // Force it to be a string  
    $value = htmlentities($value, ENT_QUOTES);    
    $rtn .= "<input type='hidden' name='$name' id='element-$cssname' value='$value' data-type='$type'>";
  }
  

  if ($type == "file") {
    $tname = $name;
    // Always going to put [] for a file, no matter what.
    //if ($multiple != "") {
      $tname .= "[]";   // if we allow uploading multiple files, we MUST put a [] behind it, or HTML will not upload correctly.  Weird but true.
    //}
    $rtn .= "<input type='file' name='$tname' id='element-$cssname' $multiple $attributes>";
  }
  
  
  if ($type == "radios") {
    $rtn .= "<div class='form-radios form-radios-$cssname'>";
    foreach ($options as $key => $val) {
      $checked = "";

      // For radios, it's possible we've been sent an array for the value (though a string is more common and prefered).
      // We need to check both.  
      if (is_array($value) && isset($value[$key]) && $value[$key] == $key) {
        $checked = "checked=checked";
      }
      else if (!is_array($value) && $value == $key) {
        $checked = "checked=checked";
      }
      
      $csskey = fp_get_machine_readable($key);
      $rtn .= "<div class='radio-element radio-element-$csskey'>
                 <label class='label-for-radio'><input type='radio' name='$name' id='element-$cssname-$csskey' value='$key' $checked $attributes> $val</label>
               </div>";
    }
    $rtn .= "</div>";
  }

  if ($type == "select") {
    $rtn .= "<select name='$name' id='element-$cssname' $attributes>";
    if ($no_please_select != TRUE) {
      $rtn .= "<option value=''>- Please select -</option>";
    }
    
    foreach ($options as $key => $val) {
      
      if (is_array($val)) {
        // We need to establish an optgroup and then descend one level.
        $rtn .= "<optgroup label='" . htmlentities($key) . "'>";
        foreach ($val as $k => $v) {
          $selected = "";
          if ($value == $k) {
            $selected = "selected";
          }
          $rtn .= "<option value='$k' $selected>$v</option>";
          
        }
        $rtn .= "</optgroup>";
      }
      else {
        // This is just a normal string, so we can continue as-is  
        $selected = "";
        if ($value == $key) {
          $selected = "selected";
        }
        $rtn .= "<option value='$key' $selected>$val</option>";
      }
    }
    $rtn .= "</select>";
  }


  // Multiple checkboxes...
  if ($type == "checkboxes") {
    $rtn .= "<div class='form-checkboxes form-checkboxes-$cssname'>";
    foreach ($options as $key => $val) {
        
      if (is_array($val)) {
        // Similar to select lists above, we need to simulate having "optgroup"s for checkboxes.
        $rtn .= "<div class='checkbox-pseudo-optgroup-wrapper'>
                  <label>$key</label>";
        foreach ($val as $k => $v) {
          $checked = "";
          if (is_array($value) && isset($value[$k]) && $value[$k] == $k) {        
            $checked = "checked=checked";
          }
          
          $csskey = fp_get_machine_readable($key);
          $rtn .= "<div class='checkbox-element checkbox-element-$csskey'>
                     <label class='label-for-checkbox'><input type='checkbox' name='$name" . "[$k]' id='element-$cssname-$csskey' value='$k' $checked $attributes> $v</label>
                   </div>";          
        }
        $rtn .= "</div>"; // close the pseudo-optgroup-wrapper
      } 
      else {
        $checked = "";
        if (is_array($value) && isset($value[$key]) && $value[$key] == $key) {        
          $checked = "checked=checked";
        }
        $csskey = fp_get_machine_readable($key);
        $rtn .= "<div class='checkbox-element checkbox-element-$csskey'>
                   <label class='label-for-checkbox'><input type='checkbox' name='$name" . "[$key]' id='element-$cssname-$csskey' value='$key' $checked $attributes> $val</label>
                 </div>";
      }
    }
    $rtn .= "</div>";
  }

  // A single checkbox... The values will be with 0 (zero) or 1 (one), and boolean
  // values are accepted/saved
  if ($type == "checkbox") {
    $rtn .= "<div class='form-checkbox form-checkbox-$cssname'>";

    $checked = "";
    if ((bool)($value) == TRUE) {      
      $checked = "checked=checked";
    }
    $rtn .= "<div class='checkbox-element'>
               <label class='label-for-checkbox'><input type='checkbox' name='$name' id='element-$cssname' value='1' $checked $attributes> $label$popup_help_link</label>
             </div>";

    $rtn .= "</div>";    
  }
  


  if ($type == "submit") {

    if ($confirm != "") {
      $confirm = htmlentities($confirm, ENT_QUOTES);
      $confirm = str_replace("\n", "\\n", $confirm); 
      
      $attributes .= " onClick='return confirm(\"$confirm\");' ";
    }
    
    $rtn .= "<input type='$type' name='$name' value='$value' $attributes>";
  }
  
  if ($type == "button") {
    $rtn .= "<input type='button' name='$name' value='$value' $attributes>";    
  }

  if ($spinner) {
    fp_add_css(fp_get_module_path("system") . "/css/style.css");
    fp_add_js(fp_get_module_path("system") . "/js/spinner.js");
    $rtn .= "<span class='loading-spinner loading-spinner-$name' style='display:none;'></span>";
  }


  if ($description) {
    $rtn .= "<div class='form-element-description'>$description</div>";
  }

  if ($type != "markup" && $type != 'markup_no_wrappers' && $type != 'value') {
    $rtn .= "</div>";  // close the inner wrapper
  }



  if ($suffix) {
    $rtn .= $suffix;
  }

  if ($type != "markup" && $type != 'markup_no_wrappers' && $type != 'value') {
    $rtn .= "</div>";  // close the over-all wrapper
  }

  
    
  


  return $rtn;
}