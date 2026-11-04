<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for Admin navigation, permissions, and urgent-message configuration.
 */
class AdminTest extends FlightPathTestCase {

  private bool $urgentMessageExisted;
  private mixed $originalUrgentMessage;

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('admin_menu')) {
      require_once __DIR__ . '/../../modules/admin/admin.module';
    }
    require_once __DIR__ . '/../../modules/admin/admin.groups.inc';

    $this->urgentMessageExisted = variable_exists('urgent_msg');
    $this->originalUrgentMessage = variable_get('urgent_msg', '');
  }

  protected function tearDown(): void {
    if ($this->urgentMessageExisted) {
      variable_set('urgent_msg', $this->originalUrgentMessage);
    }
    else {
      variable_delete('urgent_msg');
    }

    parent::tearDown();
  }

  /**
   * Confirms the Admin Tools, console, watchdog, and urgent-message routes
   * retain the callbacks and permissions that separate sensitive functions.
   */
  public function testMenuDefinesProtectedAdministrationRoutes(): void {
    $items = admin_menu();

    $this->assertSame('admin_display_tools_screen', $items['admin-tools']['page_callback']);
    $this->assertSame(array('can_access_admin_tools'), $items['admin-tools']['access_arguments']);
    $this->assertSame('admin_display_main', $items['admin-tools/admin']['page_callback']);
    $this->assertSame(array('can_access_admin'), $items['admin-tools/admin']['access_arguments']);
    $this->assertSame('fp_render_form', $items['admin/config/urgent-message']['page_callback']);
    $this->assertSame(array('admin_urgent_message_form', 'system_settings'), $items['admin/config/urgent-message']['page_arguments']);
    $this->assertSame(array('display_watchdog'), $items['admin/config/watchdog']['access_arguments']);
  }

  /**
   * Ensures the urgent-message form returns the saved message administrators
   * intend to display globally, rather than replacing it with a blank value.
   */
  public function testUrgentMessageFormUsesStoredMessage(): void {
    variable_set('urgent_msg', 'Scheduled maintenance at 9 PM.');

    $form = admin_urgent_message_form();

    $this->assertSame('markup', $form['mark_top']['type']);
    $this->assertStringContainsString('all users', strip_tags($form['mark_top']['value']));
    $this->assertSame('textarea', $form['urgent_msg']['type']);
    $this->assertSame('Scheduled maintenance at 9 PM.', $form['urgent_msg']['value']);
  }

  /**
   * Confirms administration permissions distinguish console access, data entry,
   * publishing drafts, and sensitive watchdog-log visibility.
   */
  public function testPermissionDefinesAdministrationCapabilities(): void {
    $permissions = admin_perm();

    $this->assertArrayHasKey('can_access_admin_tools', $permissions);
    $this->assertArrayHasKey('can_edit_data_entry', $permissions);
    $this->assertArrayHasKey('can_apply_draft_changes', $permissions);
    $this->assertArrayHasKey('display_watchdog', $permissions);
    $this->assertSame('Access administrative console', $permissions['can_access_admin']['title']);
  }

  /**
   * Confirms the watchdog filter uses a GET Form API submission and reflects
   * its URL-based filter values.
   */
  public function testWatchdogFilterFormUsesGetValues(): void {
    $getWasArray = is_array($_GET);
    $originalGet = $getWasArray ? $_GET : NULL;
    if (!$getWasArray) $_GET = array();
    try {
      $_GET['type_filter'] = 'admin';
      $_GET['sev_filter'] = (string) WATCHDOG_ERROR;
      $_GET['start_datetime'] = '2026-11-01T08:30';
      $_GET['end_datetime'] = '2026-11-02T17:15';

      $form = fp_get_form('admin_watchdog_filter_form');

      $this->assertSame('GET', $form['#submit_method']);
      $this->assertSame('admin', $form['type_filter']['value']);
      $this->assertSame((string) WATCHDOG_ERROR, $form['sev_filter']['value']);
      $this->assertSame('datetime-local', $form['start_datetime']['type']);
      $this->assertSame('2026-11-01T08:30', $form['start_datetime']['value']);
      $this->assertSame('2026-11-02T17:15', $form['end_datetime']['value']);
      $this->assertSame('submit', $form['apply_btn']['type']);
      $this->assertSame('submit', $form['reset_btn']['type']);
    }
    finally {
      if ($getWasArray) $_GET = $originalGet; else unset($_GET);
    }
  }

  /**
   * Confirms watchdog filter submissions redirect to a canonical list URL.
   */
  public function testWatchdogFilterFormSubmitBuildsFilterRedirect(): void {
    $form = array();
    $formState = array(
      'values' => array(
        'apply_btn' => 'Apply',
        'reset_btn' => '',
        'type_filter' => 'admin',
        'sev_filter' => (string) WATCHDOG_ERROR,
        'start_datetime' => '2026-11-01T08:30',
        'end_datetime' => '2026-11-02T17:15',
      ),
    );

    admin_watchdog_filter_form_submit($form, $formState);

    $this->assertSame(
      array(
        'path' => 'admin/config/watchdog',
        'query' => 'type_filter=admin&sev_filter=' . WATCHDOG_ERROR . '&start_datetime=2026-11-01T08%3A30&end_datetime=2026-11-02T17%3A15',
      ),
      $form['#redirect']
    );

    $formState['values']['apply_btn'] = '';
    $formState['values']['reset_btn'] = 'Reset';
    admin_watchdog_filter_form_submit($form, $formState);

    $this->assertSame('', $form['#redirect']['query']);
  }

  /**
   * Confirms watchdog date filter values must be valid local datetimes.
   */
  public function testWatchdogDatetimeFilterValidation(): void {
    $start = admin_watchdog_datetime_to_timestamp('2026-11-01T08:30');
    $end = admin_watchdog_datetime_to_timestamp('2026-11-01T09:30');

    $this->assertIsInt($start);
    $this->assertIsInt($end);
    $this->assertGreaterThan($start, $end);
    $this->assertFalse(admin_watchdog_datetime_to_timestamp('2026-11-01 08:30'));
    $this->assertFalse(admin_watchdog_datetime_to_timestamp('not a datetime'));
  }

  /**
   * Confirms Admin's large-list difference helper retains duplicate source
   * values while excluding every value already present in the comparison list.
   */
  public function testArrayDiffHandlesDuplicateAcademicRecordIds(): void {
    $this->assertSame(
      array('degree-1', 'degree-1', 'degree-3'),
      admin_array_diff(array('degree-1', 'degree-2', 'degree-1', 'degree-3'), array('degree-2', 'degree-4'))
    );
    $this->assertSame(array(), admin_array_diff(array('degree-2'), array('degree-2')));
  }

  /**
   * Ensures the requested catalog year is preserved when valid, but an absent
   * or earlier value falls back to the configured earliest catalog year.
   */
  public function testCatalogYearUsesRequestOrConfiguredEarliestFallback(): void {
    global $current_student_id;
    $variableExisted = variable_exists('earliest_catalog_year');
    $originalVariable = variable_get('earliest_catalog_year', NULL);
    $requestWasArray = is_array($_REQUEST);
    $originalRequest = $requestWasArray ? $_REQUEST : NULL;
    if (!$requestWasArray) $_REQUEST = array();
    $currentStudentExisted = array_key_exists('current_student_id', $GLOBALS);
    $originalCurrentStudent = $GLOBALS['current_student_id'] ?? NULL;
    try {
      $current_student_id = '';
      variable_set('earliest_catalog_year', 2024);
      unset($_REQUEST['de_catalog_year']);
      $this->assertSame(2024, intval(admin_get_de_catalog_year()));
      $_REQUEST['de_catalog_year'] = 2023;
      $this->assertSame(2024, intval(admin_get_de_catalog_year()));
      $_REQUEST['de_catalog_year'] = 2026;
      $this->assertSame(2026, intval(admin_get_de_catalog_year()));
      $_REQUEST['de_catalog_year'] = 2023;
      $this->assertSame(2023, intval(admin_get_de_catalog_year(FALSE)));
    }
    finally {
      if ($variableExisted) variable_set('earliest_catalog_year', $originalVariable); else variable_delete('earliest_catalog_year');
      if ($requestWasArray) $_REQUEST = $originalRequest; else unset($_REQUEST);
      if ($currentStudentExisted) $GLOBALS['current_student_id'] = $originalCurrentStudent; else unset($GLOBALS['current_student_id']);
    }
  }

  /**
   * Verifies Admin menu token replacement retains catalog and watchdog filter
   * context, including rejecting array-shaped pagination input.
   */
  public function testMenuReplacementPatternsUseCatalogAndScalarFilters(): void {
    $requestWasArray = is_array($_REQUEST);
    $originalRequest = $requestWasArray ? $_REQUEST : NULL;
    if (!$requestWasArray) $_REQUEST = array();
    $getWasArray = is_array($_GET);
    $originalGet = $getWasArray ? $_GET : NULL;
    if (!$getWasArray) $_GET = array();
    try {
      $_REQUEST['de_catalog_year'] = 2031;
      $_GET['sev_filter'] = 'error';
      $_GET['type_filter'] = 'admin';
      $_GET['page'] = '4';
      $this->assertSame(
        'query?year=2031&severity=error&type=admin&page=4',
        admin_menu_handle_replacement_pattern('query?year=%DE_CATALOG_YEAR%&severity=%SEV_FILTER%&type=%TYPE_FILTER%&page=%PAGE%')
      );

      $_GET['page'] = array('unexpected');
      $this->assertSame(
        'query?page=',
        admin_menu_handle_replacement_pattern('query?page=%PAGE%')
      );
    }
    finally {
      if ($requestWasArray) $_REQUEST = $originalRequest; else unset($_REQUEST);
      if ($getWasArray) $_GET = $originalGet; else unset($_GET);
    }
  }
}
