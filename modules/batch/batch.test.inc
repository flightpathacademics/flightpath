<?php

/**
 * @file
 * This file contains functionality for testing out Batch's processing with a sample form which performs
 * some arbitrary actions.
 */




/**
 * This is our test "finished" page which will be displayed upon completion of the batch (if selected in the test form).
 * 
 * Notice that $batch is being automatically supplied.
 *
 * @param unknown_type $batch
 */
function batch_test_finished_page($batch, $items_max) {
  $rtn = "";
  
  $rtn .= "<p>" . t("The batch has completed.  You processed %max items.", array("%max" => $items_max)) . "</p>";
  
  $rtn .= "<p>" . l(t("Return to form."), "batch-test-form") . "</p>";
  
  
  return $rtn;
}




/**
 * Basic sample form, accessed at site.com/batch-test
 *
 */
function batch_test_form() {
  $form = array();
  
  $form["mark" . $m++] = array(
    "value" => "<p>" . t("This is a test form, so you can see how the Batch API system works.  When you submit this form,
                           an arbitrary batch process will begin.") . "</p>",
  );
  
  
  $form["items"] = array(  
    "label" => t("How many 'items' to process?"),
    "type" => "select",
    "options" => array(100 => 100, 200 => 200, 300 => 300, 400 => 400, 500 => 500),
    "hide_please_select" => TRUE,
  );
  
  
  $form["destination"] = array(
    "label" => t("Destination after batch is complete:"),
    "type" => "select",
    "options" => array("default" => t("None set. By default return to this form page."), "function" => t("Function - go to the function 'batch_test_finished_page'.")),
    "hide_please_select" => TRUE,
  );
  
  $form["display_percent"] = array(
    "type" => "checkbox",
    "label" => "Display percentage in progress bar area?",
  );
  
  
  $form["submit_btn"] = array(
    "type" => "submit",
    "value" => t("Submit and begin batch..."),
  );
  
  
  return $form;
}


function batch_test_form_submit($form, $form_state) {
  
  $items_max = $form_state["values"]["items"];
  $destination = $form_state["values"]["destination"];
  
  // Okay, set up the batch....
  $batch = array(
    "operation" => array("batch_test_perform_batch_operation", array($items_max)),
    "title" => t("Test Batch Operation"),
    "success_message" => t("The batch operation completed successfully."),
    "display_percent" => $form_state["values"]["display_percent"],
    "file" => menu_get_module_path("batch") . "/batch.test.inc",
  );
  
  // If we should decide to go to a function (and override the default form behavior) then let's record that here.
  if ($destination == "function") {
    $batch["finished_callback"] = array("batch_test_finished_page", array($items_max));
  }
  
  
  
  // Set the batch...
  batch_set($batch);
   
  // Since this is being called from within a form_submit, there is nothing left for us to do.  The batch will be automatically called
  // once the submission process is executed. 
  
}



/**
 * This is our batch operation function, that gets called each time the batch executes.  Notice the first argument
 * is passed by reference, and it is our $batch variable.  The other arguments are whatever we defined in the batch_set operation.
 * 
 * Some variables in batch to be aware of-- it's up to us to populate:
 *   $batch["results"]["current"] and $batch["results"]["total"]  (current is the record number we are on, total is the current number of records.)
 *   !!!!! You MUST set $batch["results"]["finished"] = TRUE  when the batch has completed.
 * 
 *
 * @param unknown_type $batch
 * @param unknown_type $items_max
 */
function batch_test_perform_batch_operation(&$batch, $items_max) {

  // if this is our first time through, let's init our values.
  if (!isset($batch["results"]["total"])) {
    // Our first time through.  Let's start up.
    $batch["results"]["total"] = $items_max;
    $batch["results"]["current"] = -1;  // so that we start with zero below
    $batch["results"]["finished"] = FALSE;    
    
  }
  
  // Set an arbitrary limit of how many records we are going to examine each time the routine runs.
  // This should be whatever we believe we can safely accomplish before the routine times out.  Set low for safety.
  $limit = rand(5, 15);
  $c = $t = 0;

  for ($t = $batch["results"]["current"] + 1; $t < $batch["results"]["total"]; $t++) {
    

    // ....
    // ....
    // Here's where your code would go 
    // ....
    // ....
    
    $c++;
    // If we've gone past the limit, then break.
    if ($c >= $limit) break;
    
  } 
  
  
  // Update our values.
  $batch["results"]["current"] = $t;
   
  
  // Have we finished?
  if ($batch["results"]["current"] >= $batch["results"]["total"]) {
    $batch["results"]["finished"] = TRUE;    
  }
  
  
  ////////////////////////
  ////////////////////////
  //  !!!!!!
  // We are going to artificially slow down this script so we can see
  // the batch process operating.
  // Obviously, take this out in your own script.
  sleep(1);  //sleep for 1 second.
    
   
  
}









