<?php

function stats_report_activity_audit_form() {
  $form = array();

  // If the Batch module isn't enabled, stop right here!!!
  if (!function_exists("batch_set")) {
    $form = array();
    $form["markup"] = array(
      "value" => "<p><b>" . t("To use this report, the Batch module must be enabled.  Please ask your administrator
                            to enable the Batch module.") . "</b></p>",
    );
    return $form;
  }


  $form['mark_top'] = array(
    'type' => 'markup',
    'value' => '<p>This report lets you export user activities over a date range to a CSV file.</p>',
  );



  $form += stats_get_date_range_form_elements(TRUE, '', date('Y-m-d'));


  $form['submit_btn'] = array(
    'type' => 'submit',
    'value' => 'Submit',
    'spinner' => TRUE,
    'inline' => TRUE,
  );

  $form['mark_under_filter'] = array(
    'type' => 'markup',
    'value' => "<div><strong>Note:</strong> Depending on the date range selected, this report may take several minutes to run.</div>
                <hr>",
  );



  // TODO: if we have submitted, then display submission information
  // check: $_SESSION["stats_report_activity_audit_batch_results"]

  //$html .= "<p>" . l(t("Download CSV"), "stats/download-csv-from-batch-file/$last_batch_id", "filename=" . urlencode("lee_cocur_progress_report__" . format_date(convert_time($last_timestamp))), array('class' => 'button')) . "</p>";


  return $form;
} // form


function stats_report_activity_audit_form_validate($form, $form_state) {

  $start = $form_state['values']['start_date'];
  $end = $form_state['values']['end_date'];

  if ($end == '') $end = date('Y-m-d');

  if ($start == '') $start = date('Y-m-d');

  if ($start > $end) {
    form_error('start_date', t("You have selected a Start date which occurs after the End date. Please review and try again."));
    return;
  }



} // ... validate


function stats_report_activity_audit_form_submit($form, $form_state) {

  unset($_SESSION["stats_report_activity_audit_batch_results"]);
  $_SESSION["stats_report_activity_audit_batch_results"] = array();

  $start = $form_state['values']['start_date'];
  $end = $form_state['values']['end_date'];

  if ($end == '') $end = date('Y-m-d');
  if ($start == '') $start = date('Y-m-d');

  $start .= " 00:00:00";
  $end .= " 23:23:59";


  // Okay, set up the batch....
  $batch = array(
    "operation" => array("stats_report_activity_audit_perform_batch_operation", array($start, $end)),
    "title" => t("Activity Audit - CSV Export - Time Range: ") . $start . ' - ' . $end,
    "file" => menu_get_module_path("stats") . "/reports.activity-audit.inc",
    "progress_message" => "Processing record @current of @total",
    "display_percent" => TRUE,
  );

  watchdog('stats', "report_activity_audit range:$start to $end", array());

  // Set the batch...
  batch_set($batch);


} // ... submit




function stats_report_activity_audit_perform_batch_operation(&$batch, $start, $end) {

  $csv = '';
  ////////////////////////////  FIRST TIME ///////////////////////////////
  // If this is our first time through, let's init our values.
  if (!isset($batch["results"]["total"])) {
    // Our first time through.  Let's start up.
    $batch["results"]["current"] = 0;
    $batch["results"]["finished"] = FALSE;


    // TODO: set to real header
    //$csv = "CWID, LAST, FIRST, CATALOG, MAJOR, RANK, ";

    $types_exclude = array(
      'access_denied', 'page_not_found', 'ban_ip', 'cron', 'debug', 'http_request', 'lassie', 'nightly_import_routine',
      'night_import_routine', 'php_error',
      'php_warning', 'saml', 'update_status', 'save_adv_draft', 'save_adv_draft_whatif',
    );

    $types_not_in = "AND `type` NOT IN ('" . join("','", $types_exclude) . "')";

    // We need the "total" number of students in the selected major.
    $res = db_query("SELECT COUNT(wid) as count FROM watchdog
                     WHERE `timestamp` >= :start
                     AND `timestamp` <= :end
                     $types_not_in
                     ORDER BY wid
                     ", array(':start' => strtotime($start), ':end' => strtotime($end)));
    $cur = db_fetch_array($res);

    $batch["results"]["total"] = intval($cur["count"]);

  } // if first time through


  // Okay, we can now begin the actual batch process.
  $current = $batch["results"]["current"];
  $total = $batch["results"]["total"];

  $limit = 100;  // how many records to examine for THIS time through the function.
  $c = 0;  // count of records.

  $db = get_global_database_handler();

  $types_exclude = array(
    'access_denied', 'page_not_found', 'ban_ip', 'cron', 'debug', 'http_request', 'lassie', 'nightly_import_routine',
    'night_import_routine', 'php_error',
    'php_warning', 'saml', 'update_status', 'save_adv_draft', 'save_adv_draft_whatif',
  );

  $types_not_in = "AND `type` NOT IN ('" . join("','", $types_exclude) . "')";

  // We need the "total" number of students in the selected major.
  $res = db_query("SELECT * FROM watchdog
                     WHERE `timestamp` >= :start
                     AND `timestamp` <= :end
                     $types_not_in
                     ORDER BY wid
                     LIMIT $current, $limit
                     ", array(':start' => strtotime($start), ':end' => strtotime($end)));
   while ($cur = db_fetch_object($res)) {

     if ($c >= $limit) {
       break;
     }


     // TODO: Get data and add to $csv



     $c++;

   } // while $cur

   // Write our work to a temporary file in the /files/private dir for this batch.
   batch_append_to_batch_file($batch, $csv);

   // So that we don't use up all server resources, let's pause every so often.
   if (mt_rand(1, 6) == 3) {
     sleep(1);
   }


   // Update our $batch results variables
   $batch["results"]["current"] = $current + $c;


   if ($batch["results"]["current"] >= $total) {
     // We are finished!
     $batch["results"]["finished"] = TRUE;

     $_SESSION["stats_report_activity_audit_batch_results"]["results"] = $batch['results'];
     $_SESSION["stats_report_activity_audit_batch_results"]["last_batch_id"] = $batch["batch_id"];
     $_SESSION["stats_report_activity_audit_batch_results"]["last_timestamp"] = time();

   }







} // ... batch_operation










//

