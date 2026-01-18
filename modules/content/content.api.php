<?php
/**
 * This file is meant to demonstrate how to use hooks in the content module, as a fellow module developer.
*/



/**
 * Sample hook other modules implement to register a content type.
 * 
 * Simply return an array as illustrated below to register your
 * module's content types.
 * 
 * IMPORTNAT:  The type's machine name must be a valid variable name.
 * No spaces or symbols.
 *
 * Notice that the fields are set up much as you would set up a form using the
 * normal form api.
 * 
 * The content module assumes that a database table has already been created, where every
 * field name begins with field__
 * 
 * Also, the table itself should be named content__typename.
 * 
 * @see the Alert module, and its install file.
 * 
 * 
 */
function hook_content_register_content_type() {

  // Example is from the Alert module.

  $arr['alert'] = array(
    'title' => 'Alert',
    'description' => 'Signal an alert, notification, or issue to be resolved for a student.',
    'settings' => array(
      'title' => array(
        'label' => t('Title / Short Description'),    
        'weight' => 15,    
      ),
    ),
  );


  // If we are in a popup (dialog)...
  if (@$_GET['window_mode'] == 'popup') {
    // We want to make sure we redirect to our handler URL, which will close the dialog.
    $arr['alert']['settings']['#redirect'] = array(
      'path' => 'content-dialog-handle-after-save',
      'query' => '',        
    );
  }


  $fields = array();

  $fields['student_id'] = array(
    'type' => 'textfield',
    'label' => 'Student',
    'weight' => 10,
  );

      
  $fields['alert_status'] = array(
    'type' => 'select',
    'label' => 'Status',
    'options' => array(
      'open' => t('Open'),
      'closed' => t('Closed'),
    ),
    'required' => TRUE,
    'hide_please_select' => TRUE,
    'weight' => 40,
  );


  $fields['department'] = array(
    'type' => 'select',
    'label' => 'Department',    
    'hide_please_select' => TRUE,
    'options' => array(
      'default' => t("Default/None"),
      'finaid' => t('Financial Aid'),
      'reg' => t('Registrar'),
      'stu_aff' => t('Student Affairs'),      
    ),
    'weight' => 60,
  );  

    
  $fields['alert_msg'] = array(
    'type' => 'textarea_editor',  
    'label' => 'Message',
    'filter' => 'basic',
    'weight' => 70,    
  );

  
  
  $fields['visibility'] = array(
    'type' => 'radios',  
    'label' => 'Visible to:',
    'options' => array('public' => 'Anyone (incl. student)', 'faculty' => 'Faculty/Staff only'),   
    'weight' => 80,    
  );
    
  $arr['alert']['fields'] = $fields;  


  
  return $arr;
}



/**
 * This hook is called by the function content_load($cid), and allows other modules
 * to act on content which is being loaded.
 * 
 * Note: we will always call "content_content_load" FIRST, before calling other modules' hook_content_load functions.
 * 
 */
function hook_content_load(&$content) {
  $content->title = "Change the Title";
  
  // No need to return since we passes by reference.
}



/**
 * This hook is called just before saving the content to the database, and allows other modules to act on
 * content before it is writen to the database.
 * 
 * Note: we will always call "content_content_save" LAST, after calling the other modules' possible hooks.
 * 
 */
function hook_content_save(&$content) {
  $content->title = "Foo " . time();
  
  // No need to return since we passes by reference.
}








