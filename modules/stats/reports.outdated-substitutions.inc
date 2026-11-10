<?php
/**
 * @file
 * Provides the core outdated substitutions report and CSV batch export.
 */

/**
 * Builds the form for the outdated substitutions report.
 *
 * @return array
 *   The report form.
 */
function stats_report_outdated_substitutions_form() {
  $form = array();
  $school_id = intval(@$_REQUEST['school_id']);
  $school_select_title = t('Outdated Substitutions - Select School');

  if (module_enabled('schools') && !isset($_REQUEST['school_id'])) {
    fp_goto('stats/select-school', 'title=' . urlencode($school_select_title) . '&redirect=stats/reports/outdated-substitutions');
    return $form;
  }

  if (module_enabled('schools')) {
    $form['selected_school_name'] = array(
      'value' => '<div class="current-school">' . t('Current school: ') . '<strong>' . schools_get_school_name_for_id($school_id) . '</strong> - ' . l(t('Change?'), 'stats/select-school', 'title=' . urlencode($school_select_title) . '&redirect=stats/reports/outdated-substitutions') . '</div>',
    );
  }

  $form['school_id'] = array(
    'type' => 'hidden',
    'value' => $school_id,
  );
  $form['description'] = array(
    'value' => '<p>' . t('This report checks active students with current substitutions and exports those that no longer apply to the student\'s current degree plan.') . '</p>',
  );
  $form['submit_btn'] = array(
    'type' => 'submit',
    'value' => t('Generate CSV'),
    'spinner' => TRUE,
    'suffix' => t('This report may take a long time to complete. Do not close the window or tab.'),
  );

  $last_timestamp = @$_SESSION['stats_report_outdated_substitutions_batch_results'][$school_id]['last_timestamp'];
  $last_batch_id = @$_SESSION['stats_report_outdated_substitutions_batch_results'][$school_id]['last_batch_id'];
  if ($last_batch_id && time() < $last_timestamp + (60 * 60)) {
    $filename = 'outdated_substitutions__' . format_date(convert_time($last_timestamp));
    $download_link = l(t('Download CSV'), 'stats/download-csv-from-batch-file/' . $last_batch_id, 'filename=' . urlencode($filename), array('class' => 'button'));
    $form['download_csv'] = array(
      'value' => '<hr><p>' . t('The last CSV report was generated at @time. It will remain available for one hour.', array('@time' => format_date(convert_time($last_timestamp)))) . '</p><p>' . $download_link . '</p>' . stats_report_outdated_substitutions_csv_preview($last_batch_id, $download_link),
    );
  }

  return $form;
}

/**
 * Builds an HTML preview of the first 100 records in a batch CSV file.
 *
 * @param int $batch_id
 *   The completed batch identifier.
 * @param string $download_link
 *   The rendered Download CSV link.
 *
 * @return string
 *   The report preview, or an empty string when the batch file is unavailable.
 */
function stats_report_outdated_substitutions_csv_preview($batch_id, $download_link) {
  $batch = batch_get($batch_id);
  if (!$batch) {
    return '';
  }

  $private_path = fp_get_files_path() . '/private';
  $filepath = $private_path . '/batch_' . intval($batch['batch_id']) . '__' . intval($batch['created']) . '.csv.tmp';
  if (!is_readable($filepath)) {
    return '';
  }

  $handle = fopen($filepath, 'r');
  if (!$handle) {
    return '';
  }

  $headers = fgetcsv($handle, 0, ',', '"', '\\');
  if ($headers === FALSE) {
    fclose($handle);
    return '';
  }

  $rows = array();
  $bool_more_records = FALSE;
  while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
    if (count($rows) >= 100) {
      $bool_more_records = TRUE;
      break;
    }
    $rows[] = $row;
  }
  fclose($handle);

  $html = '<div class="stats-report-preview"><h3>' . t('Report Preview') . '</h3>';
  $html .= '<div><table class="stats-report-preview-table" border="1" cellpadding="5">
            <thead>
            <tr>';
  foreach ($headers as $header) {
    $html .= '<th valign="top">' . htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8') . '</th>';
  }
  $html .= '</tr>
            </thead>
            <tbody>';
  foreach ($rows as $row) {
    $html .= '<tr>';
    foreach ($headers as $index => $unused) {
      $cell = isset($row[$index]) ? $row[$index] : '';
      $html .= '<td valign="top">' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
    }
    $html .= '</tr>';
  }
  $html .= '</tbody>
            </table>
            </div>';

  if ($bool_more_records) {
    $html .= '<p>' . t('Only the first 100 rows are displayed. Select the Download CSV button to view all records.') . '</p>';
    $html .= '<p>' . $download_link . '</p>';
  }

  $html .= '</div>';

  return $html;
}

/**
 * Starts the Batch API operation for the outdated substitutions report.
 *
 * @param array $form
 *   The submitted form.
 * @param array $form_state
 *   The submitted form state.
 */
function stats_report_outdated_substitutions_form_submit($form, $form_state) {
  $school_id = intval(@$form_state['values']['school_id']);
  $student_ids = array();
  $params = array(':school_id' => $school_id);
  $res = db_query("SELECT DISTINCT a.cwid
                   FROM students a
                   INNER JOIN users b ON a.cwid = b.cwid
                   INNER JOIN student_substitutions s ON a.cwid = s.student_id
                   WHERE b.school_id = :school_id
                   AND a.is_active = 1
                   AND s.delete_flag = 0
                   ORDER BY b.l_name, b.f_name, a.cwid", $params);
  while ($cur = db_fetch_array($res)) {
    $student_ids[] = $cur['cwid'];
  }

  $batch = array(
    'operation' => array('stats_report_outdated_substitutions_batch_operation', array($student_ids, $school_id)),
    'title' => t('Outdated Substitutions Report'),
    'progress_message' => t('Checking @current of @total students for outdated substitutions.'),
    'success_message' => t('The outdated substitutions report completed successfully.'),
    'display_percent' => TRUE,
    'file' => fp_get_module_path('stats', TRUE, FALSE) . '/reports.outdated-substitutions.inc',
  );
  batch_set($batch);
}

/**
 * Processes one student per Batch API request for the report.
 *
 * @param array $batch
 *   The Batch API state, passed by reference.
 * @param array $student_ids
 *   CWIDs ordered for processing.
 * @param int $school_id
 *   The school being reported on.
 */
function stats_report_outdated_substitutions_batch_operation(&$batch, $student_ids, $school_id) {
  if (!isset($batch['results']['total'])) {
    $batch['results']['current'] = 0;
    $batch['results']['total'] = count($student_ids);
    $batch['results']['student_index'] = 0;
    $batch['results']['finished'] = FALSE;
    batch_append_to_batch_file($batch, fp_array_to_csv_row(array(
      'CWID', 'Student Name', 'Date Added', 'Catalog Year', 'Current Major Codes', 'Sub in Degree', 'Sub in Group', 'Required Course', 'Substitute Course', 'Substitution Hours', 'Remarks', 'Outdated Reason',
    )));
  }

  $student_index = $batch['results']['student_index'];
  if ($student_index < count($student_ids)) {
    $student = new Student($student_ids[$student_index], NULL, $school_id);
    $student->load_student_substitutions();
    $degree_plan = stats_report_outdated_substitutions_get_degree_plan($student);
    $flightpath = new FlightPath($student, $degree_plan);
    $flightpath->flag_outdated_substitutions();

    $csv = '';
    $substitution_posted_dates = stats_report_outdated_substitutions_get_posted_dates($student->student_id);
    $student->list_substitutions->reset_counter();
    while ($student->list_substitutions->has_more()) {
      $substitution = $student->list_substitutions->get_next();
      if (!$substitution->bool_outdated) {
        continue;
      }

      $substitute_course = $substitution->course_list_substitutions->get_first();
      $required_course = stats_report_outdated_substitutions_get_course_label($substitution->course_requirement);
      $substitute_course_label = stats_report_outdated_substitutions_get_course_label($substitute_course);
      if ($substitution->bool_group_addition) {
        $required_course = '(added)';
      }
      $posted = 0;
      if (isset($substitution_posted_dates[$substitution->db_substitution_id])) {
        $posted = $substitution_posted_dates[$substitution->db_substitution_id];
      }
      $posted_date = '';
      if (intval($posted) > 0) {
        $posted_date = format_date(convert_time($posted), 'custom', 'm/d/Y g:ia');
      }
      $csv .= fp_array_to_csv_row(array(
        $student->student_id,
        $student->name,
        $posted_date,
        $student->catalog_year,
        $student->major_code_csv,
        stats_report_outdated_substitutions_get_degree_title($substitution->db_required_degree_id),
        stats_report_outdated_substitutions_get_group_label($substitution->db_required_group_id),
        $required_course,
        $substitute_course_label,
        $substitute_course->get_substitution_hours($substitution->assigned_to_degree_id),
        stats_report_outdated_substitutions_clean_csv_text($substitution->remarks),
        stats_report_outdated_substitutions_clean_csv_text($substitution->outdated_note),
      ));
    }
    if ($csv != '') {
      batch_append_to_batch_file($batch, $csv);
    }

    $batch['results']['student_index']++;
    $batch['results']['current']++;
  }

  if ($batch['results']['current'] >= $batch['results']['total']) {
    $batch['results']['finished'] = TRUE;
    $_SESSION['stats_report_outdated_substitutions_batch_results'][$school_id] = $batch['results'];
    $_SESSION['stats_report_outdated_substitutions_batch_results'][$school_id]['last_batch_id'] = $batch['batch_id'];
    $_SESSION['stats_report_outdated_substitutions_batch_results'][$school_id]['last_timestamp'] = time();
  }
}

/**
 * Reconstructs the current degree plan that normal advising uses for a student.
 *
 * @param Student $student
 *   The student whose degree plan should be loaded.
 *
 * @return DegreePlan
 *   The student's individual, combined, or blank degree plan.
 */
function stats_report_outdated_substitutions_get_degree_plan($student) {
  $db = get_global_database_handler();
  $catalog_year = intval($student->catalog_year);
  $current_catalog_year = intval(variable_get_for_school('current_catalog_year', '', $student->school_id));
  $earliest_catalog_year = intval(variable_get_for_school('earliest_catalog_year', 2006, $student->school_id));
  $degree_plans = array();

  if ($catalog_year > $current_catalog_year && $current_catalog_year > $earliest_catalog_year) {
    $catalog_year = 99999;
  }

  foreach ($student->major_code_array as $major_code => $unused) {
    $degree_id = $db->get_degree_id($major_code, $catalog_year, FALSE, $student->school_id);
    if (!$degree_id) {
      continue;
    }

    $degree_plan = fp_load_degree($degree_id, $db, FALSE, $student->array_significant_courses);
    $degree_plan->add_semester_developmental($student->student_id);
    $degree_plans[$degree_plan->degree_id] = $degree_plan;
  }

  if (count($degree_plans) == 1) {
    $degree_plan = reset($degree_plans);
  }
  else if (count($degree_plans) > 1) {
    $flightpath = new FlightPath();
    $degree_plan = $flightpath->combine_degree_plans($degree_plans, $student->student_id, $student->school_id);
    $degree_plan->db_allow_dynamic = 1;
  }
  else {
    $degree_plan = new DegreePlan();
  }

  invoke_hook('init_flightpath_degree', array($student->student_id, &$degree_plan));

  return $degree_plan;
}

/**
 * Formats a course object for the CSV export.
 *
 * @param Course $course
 *   The course to describe.
 *
 * @return string
 *   A course subject/number label, with a transfer designation when applicable,
 *   or a stable course ID fallback.
 */
function stats_report_outdated_substitutions_get_course_label($course) {
  if (!is_object($course)) {
    return '';
  }

  if ($course->bool_transfer == TRUE && is_object($course->course_transfer)) {
    if ($course->course_transfer->subject_id == '') {
      $course->course_transfer->load_descriptive_transfer_data();
    }
    $label = trim($course->course_transfer->subject_id . ' ' . $course->course_transfer->course_num);
    if ($label != '') {
      return $label . ' (transfer)';
    }
  }

  if ($course->subject_id == '' && $course->course_id > 0) {
    $course->load_descriptive_data();
  }

  $label = trim($course->subject_id . ' ' . $course->course_num);
  if ($label == '' && $course->course_id > 0) {
    $label = t('Course ID @course_id', array('@course_id' => $course->course_id));
  }

  return $label;
}

/**
 * Retrieves a plain-text degree title for the CSV export.
 *
 * @param int $degree_id
 *   The degree identifier.
 *
 * @return string
 *   The degree title or a stable degree ID fallback.
 */
function stats_report_outdated_substitutions_get_degree_title($degree_id) {
  $title = fp_get_degree_title($degree_id, TRUE, FALSE, FALSE, TRUE);
  if ($title == '') {
    $title = t('Degree ID @degree_id', array('@degree_id' => $degree_id));
  }

  return $title;
}

/**
 * Loads substitution posted timestamps for one student.
 *
 * @param string $student_id
 *   The student's CWID.
 *
 * @return array
 *   Posted timestamps keyed by substitution ID.
 */
function stats_report_outdated_substitutions_get_posted_dates($student_id) {
  $posted_dates = array();
  $res = db_query('SELECT id, posted
                   FROM student_substitutions
                   WHERE student_id = ?
                   AND delete_flag = 0', $student_id);
  while ($cur = db_fetch_array($res)) {
    $posted_dates[$cur['id']] = $cur['posted'];
  }

  return $posted_dates;
}


/**
 * Retrieves the group name for the CSV export.
 *
 * @param string $group_id
 *   The group identifier.
 *
 * @return string
 *   The group title, name, or a stable group ID fallback.
 */
function stats_report_outdated_substitutions_get_group_label($group_id) {
  if ($group_id == '') {
    return '';
  }

  $group = new Group();
  $group->group_id = $group_id;
  $group->load_descriptive_data();
  if ($group->title != '') {
    return $group->title;
  }
  if ($group->group_name != '') {
    return $group->group_name;
  }

  return t('Group ID @group_id', array('@group_id' => $group_id));
}

/**
 * Converts HTML-rich text into a single plain-text CSV cell.
 *
 * @param string $text
 *   The text to clean.
 *
 * @return string
 *   Plain text with decoded entities and collapsed whitespace.
 */
function stats_report_outdated_substitutions_clean_csv_text($text) {
  $text = html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');
  $text = preg_replace('/<[^>]+>/', ' ', $text);

  return trim(preg_replace('/\s+/u', ' ', $text));
}


