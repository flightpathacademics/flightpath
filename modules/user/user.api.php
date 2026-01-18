<?php
/**
 * This file is meant to demonstrate how to use hooks in the user module, as a fellow module developer.
*/


/**
 * Sample hook other modules implement to register a user attribute.
 * 
 * Simply return an array as illustrated below to register your
 * module's content types.
 * 
 * IMPORTNAT:  The attribute's machine name must be a valid variable name.
 * No spaces or symbols.
 *
 * Values are saved to the user_attributes table automatically as 
 *   name__fieldname
 * In the example below, the value would be saved under visa_status__visa
 * 
 * 
*/
function hook_user_register_user_attributes() {
  
  $arr['visa_status'] = array(
    'title' => 'Visa Status',
    'description' => 'Determines the visa status of the user.',
    'settings' => array(
      'large_profile' => TRUE,
      'large_profile_editable' => TRUE,
      'mini_profile' => TRUE,
      'mini_profile_editable' => FALSE,
      'user_type' => 'student',  // Can be "student", "faculty", or "all"
    ),
  );

  
  $arr['fun_field'] = array(
    'title' => 'Enter any text you like:',
    'settings' => array(
      'user_type' => 'student',
      'large_profile_editable' => TRUE,
    ),
  );


  // If we are in a popup (dialog)...
  if (@$_GET['window_mode'] == 'popup') {
    // We want to make sure we redirect to our handler URL, which will close the dialog.
    $arr['visa_status']['settings']['#redirect'] = array(
      'path' => 'content-dialog-handle-after-save',
      'query' => '',        
    );

    $arr['fun_field']['settings']['#redirect'] = array(
      'path' => 'content-dialog-handle-after-save',
      'query' => '',        
    );


    /**
     * // To do all attributes at once...
    foreach ($arr as $attr => $details) {
      $arr[$attr]['settings']['#redirect'] = array(
        'path' => 'content-dialog-handle-after-save',
        'query' => '',        
      );      
    }
     */
    
  }


  $fields = array();  
  
  $fields['visa'] = array(
    'type' => 'select',
    'label' => 'Status:',
    'options' => array(
      'open' => t('Open'),
      'closed' => t('Closed'),
    ),
    'required' => TRUE,
    'hide_please_select' => TRUE,
    'weight' => 20,
  );  
  
  
  $arr['visa_status']['fields'] = $fields;  
  
  // ** required! **
  // Display settings
  $arr['visa_status']['display']['visa'] = array(
      'label' => 'Visa Status:',
      'value' => 'Selected: <i>@value</i>',  // We can make use of HTML and replacement patterns.  
                                             // @value means whatever was selected / typed in.  @key means they key from an options field.
                                             // If 'value' is ommitted, we display @value by default.
  );
  
  
  //////////////////////////////////////////////////
  
  $fields = array();
  
  $fields['fun_field_val'] = array(
    'type' => 'textfield',
    'label' => 'Fun Field:',    
  ); 
  
  $arr['fun_field']['display']['fun_field_val'] = array(
    'label' => 'Fun Field:',    
  );
  
  $arr['fun_field']['fields'] = $fields;  
    
  return $arr;
    
} 
 
 
/**
 * Allows other modules to alter the "profile items".  
 * 
 * @see user_alter_student_profile_items for a real-life demo of this hook.
 */ 
function hook_alter_student_profile_items($bool_mini, &$extra_profile_items, $bool_balance = TRUE, $alt_section = "") {
  
  // Code goes here.  See user_alter_student_profile_items for a real-life demo of this hook.
  
} 
 
 
 
/**
 * This function lets us alter the value of a user attribute, before it is displayed to the user.
 * 
 * Notice that value is passed by reference.
 */ 
function hook_alter_user_attribute_display($attribute_id, &$value) {
  if ($attribute_id == "my_module__name_value") {
    $value = strtoupper($value);
  }
} 
 
 
 
 
 
 
 
 
 
 
 
 
 

