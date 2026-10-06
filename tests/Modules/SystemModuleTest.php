<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for System module navigation, permissions, and block regions.
 */
class SystemModuleTest extends FlightPathTestCase {

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('system_menu')) {
      require_once __DIR__ . '/../../modules/system/system.module';
    }
  }

  /**
   * Confirms public entry pages and logged-in dashboard routes retain their
   * intended callbacks and access behavior.
   */
  public function testMenuDefinesDashboardAndAuthenticationRoutes(): void {
    $items = system_menu();

    $this->assertSame('system_display_dashboard_page', $items['main']['page_callback']);
    $this->assertTrue($items['main']['access_callback']);
    $this->assertSame(MENU_TYPE_NORMAL_ITEM, $items['main']['type']);
    $this->assertSame('system_display_login_page', $items['login']['page_callback']);
    $this->assertTrue($items['login']['access_callback']);
    $this->assertSame('fp_render_form', $items['mfa-login']['page_callback']);
    $this->assertSame(array('system_mfa_login_form'), $items['mfa-login']['page_arguments']);
    $this->assertSame(array('access_logged_in_content'), $items['render-advising-snapshot-for-iframe']['access_arguments']);
  }

  /**
   * Ensures cache, cron, module, status, and system-settings routes are each
   * guarded by their distinct administration permission.
   */
  public function testMenuDefinesProtectedSystemAdministrationRoutes(): void {
    $items = system_menu();

    $this->assertSame(array('clear_system_cache'), $items['admin-tools/clear-cache']['access_arguments']);
    $this->assertSame(array('administer_modules'), $items['admin/db-updates']['access_arguments']);
    $this->assertSame(array('run_cron'), $items['admin/config/run-cron']['access_arguments']);
    $this->assertSame(array('view_system_status'), $items['admin/config/status']['access_arguments']);
    $this->assertSame(array('de_can_administer_system_settings'), $items['admin/config/system-settings']['access_arguments']);
    $this->assertSame(array('de_can_administer_school_data'), $items['admin/config/school-data']['access_arguments']);
    $this->assertSame(array('administer_modules'), $items['admin/config/modules']['access_arguments']);
    $this->assertSame(array('execute_php'), $items['admin/config/execute-php']['access_arguments']);
  }

  /**
   * Confirms System registers the main and login block regions needed by themes
   * to place blocks consistently on authenticated and login pages.
   */
  public function testBlockRegionsDefineMainAndLoginLayouts(): void {
    $regions = system_block_regions();

    $this->assertSame('Main Tab', $regions['system_main']['title']);
    $this->assertSame(array('left_col', 'right_col'), array_keys($regions['system_main']['regions']));
    $this->assertSame('Login Page', $regions['system_login']['title']);
    $this->assertSame(array('top', 'left_col', 'right_col', 'bottom'), array_keys($regions['system_login']['regions']));
  }

  /**
   * Verifies sensitive module administration, PHP execution, and debug access
   * are administrator-restricted while normal signed-in access is separately defined.
   */
  public function testPermissionDefinesSystemAdministrationRestrictions(): void {
    $permissions = system_perm();

    $this->assertArrayHasKey('access_logged_in_content', $permissions);
    $this->assertArrayHasKey('administer_modules', $permissions);
    $this->assertArrayHasKey('execute_php', $permissions);
    $this->assertArrayHasKey('run_cron', $permissions);
    $this->assertTrue($permissions['administer_modules']['admin_restricted']);
    $this->assertTrue($permissions['execute_php']['admin_restricted']);
    $this->assertTrue($permissions['view_fpm_debug']['admin_restricted']);
  }
  /**
   * Verifies public login, logout, and contact routes retain their intended
   * callbacks and popup/access contracts.
   */
  public function testMenuDefinesPublicAndProtectedUtilityRoutes(): void {
    $items = system_menu();

    $this->assertSame('system_display_login_help_page', $items['login-help']['page_callback']);
    $this->assertTrue($items['login-help']['access_callback']);
    $this->assertSame('system_display_disable_login_page', $items['disable-student-login']['page_callback']);
    $this->assertSame(array('student'), $items['disable-student-login']['page_arguments']);
    $this->assertSame('system_handle_logout', $items['logout']['page_callback']);
    $this->assertSame(array('access_popup_report_contact'), $items['popup-report-contact']['access_arguments']);
    $this->assertTrue($items['popup-report-contact']['page_settings']['page_is_popup']);
  }

  /**
   * Ensures whitelist parsing ignores comments and blank lines while retaining
   * both submitted and lowercase email-style entries for lookup.
   */
  public function testUserWhitelistParsesEntriesCommentsAndCaseVariants(): void {
    $existed = variable_exists('user_whitelist');
    $original = variable_get('user_whitelist', NULL);
    try {
      variable_set('user_whitelist', "# administrators\nAdmin@Example.edu\n\nstudent@example.edu");
      $this->assertSame(
        array('Admin@Example.edu', 'admin@example.edu', 'student@example.edu', 'student@example.edu'),
        system_get_user_whitelist()
      );
      variable_set('user_whitelist', "\n# comment only");
      $this->assertFalse(system_get_user_whitelist());
    }
    finally {
      if ($existed) variable_set('user_whitelist', $original); else variable_delete('user_whitelist');
    }
  }

  /**
   * Confirms cache freshness compares session and persisted timestamps so an
   * outdated inventory is reloaded while matching values reuse the cache.
   */
  public function testCourseInventoryReloadCheckUsesPersistedTimestamp(): void {
    $variableExisted = variable_exists('cache_course_inventory_last_generated');
    $originalVariable = variable_get('cache_course_inventory_last_generated', NULL);
    $sessionExisted = array_key_exists('fp_cache_course_inventory_last_generated', $_SESSION);
    $originalSession = $_SESSION['fp_cache_course_inventory_last_generated'] ?? NULL;
    try {
      variable_set('cache_course_inventory_last_generated', 1700000000);
      $_SESSION['fp_cache_course_inventory_last_generated'] = 1700000000;
      $this->assertFalse(system_check_course_inventory_should_be_reloaded());
      $_SESSION['fp_cache_course_inventory_last_generated'] = 1699999999;
      $this->assertTrue(system_check_course_inventory_should_be_reloaded());
    }
    finally {
      if ($variableExisted) variable_set('cache_course_inventory_last_generated', $originalVariable); else variable_delete('cache_course_inventory_last_generated');
      if ($sessionExisted) $_SESSION['fp_cache_course_inventory_last_generated'] = $originalSession; else unset($_SESSION['fp_cache_course_inventory_last_generated']);
    }
  }

  /**
   * Verifies generated private-directory rules deny direct access and prevent
   * cached files from being interpreted as executable server scripts.
   */
  public function testPrivateHtaccessDeniesAccessAndScriptExecution(): void {
    $rules = system_get_private_htaccess();

    $this->assertStringContainsString('Require all denied', $rules);
    $this->assertStringContainsString('Deny from all', $rules);
    $this->assertStringContainsString('Options None', $rules);
    $this->assertStringContainsString('SetHandler FlightPath_Security_Do_Not_Remove_See_SA_2006_006', $rules);
  }

  /**
   * Confirms role resolution includes an explicit role and the authenticated or
   * anonymous baseline role used by permission evaluation.
   */
  public function testRolesForUserIncludeExplicitAndBaselineRoles(): void {
    db_query('INSERT INTO roles (name) VALUES (?)', array('System Test Role'));
    $roleId = intval(db_insert_id());
    db_query('INSERT INTO users (user_name, cwid) VALUES (?, ?)', array('system_role_' . $roleId, 'SYSTEM_ROLE_' . $roleId));
    $userId = intval(db_insert_id());
    db_query('INSERT INTO user_roles (user_id, rid) VALUES (?, ?)', array($userId, $roleId));
    try {
      $roles = system_get_roles_for_user($userId);
      $this->assertSame('System Test Role', $roles[$roleId]);
      $this->assertSame('authenticated user', $roles[2]);
      $this->assertSame('anonymous user', system_get_roles_for_user(2147483647)[1]);
    }
    finally {
      db_query('DELETE FROM user_roles WHERE user_id = ? AND rid = ?', array($userId, $roleId));
      db_query('DELETE FROM users WHERE user_id = ?', array($userId));
      db_query('DELETE FROM roles WHERE rid = ?', array($roleId));
    }
  }
  /**
   * Verifies student access requires login and then permits administrators,
   * broadly authorized staff, assigned advisors, or the student themselves.
   */
  public function testStudentAccessPermissionMatrix(): void {
    global $user, $current_student_id;
    if (!function_exists('advise_get_advisees')) require_once __DIR__ . '/../../modules/advise/advise.module';
    $hadCurrentStudent = array_key_exists('current_student_id', $GLOBALS);
    $originalCurrentStudent = $GLOBALS['current_student_id'] ?? NULL;
    $studentId = 'SYSTEM_ACCESS_STUDENT';
    $facultyId = 'SYSTEM_ACCESS_FACULTY';
    db_query('INSERT INTO advisor_student (faculty_id, student_id) VALUES (?, ?)', array($facultyId, $studentId));
    try {
      $user->id = 2; $user->cwid = 'SYSTEM_ACCESS_OTHER'; $user->permissions = array();
      $this->assertFalse(system_can_access_student($studentId));
      $user->permissions = array('access_logged_in_content');
      $this->assertFalse(system_can_access_student($studentId));
      $user->permissions[] = 'view_any_advising_session';
      $this->assertTrue(system_can_access_student($studentId));
      $user->cwid = $facultyId; $user->permissions = array('access_logged_in_content', 'view_advisee_advising_session');
      $this->assertTrue(system_can_access_student($studentId));
      $user->cwid = $studentId; $user->permissions = array('access_logged_in_content', 'view_own_advising_session');
      $this->assertTrue(system_can_access_student($studentId));
      $user->id = 1; $user->permissions = array('access_logged_in_content');
      $this->assertTrue(system_can_access_student($studentId));
      $current_student_id = '';
      $this->assertFalse(system_can_access_student());
    }
    finally {
      db_query('DELETE FROM advisor_student WHERE faculty_id = ? AND student_id = ?', array($facultyId, $studentId));
      if ($hadCurrentStudent) $GLOBALS['current_student_id'] = $originalCurrentStudent; else unset($GLOBALS['current_student_id']);
    }
  }
  /**
   * Ensures configured major exclusions expand to every matching degree ID and
   * retain the result in the per-school cache for later appearance checks.
   */
  public function testExcludeDegreeIdsExpandsConfiguredMajorCodes(): void {
    $code = 'SYSTEMEXCLUDE' . substr(sha1(uniqid('', TRUE)), 0, 10);
    $firstId = random_int(700000000, 749999999);
    $secondId = random_int(750000000, 799999999);
    $variableExisted = variable_exists('exclude_majors_from_appears_in_counts');
    $originalVariable = variable_get('exclude_majors_from_appears_in_counts', NULL);
    $cacheExisted = array_key_exists('exclude_degree_ids_from_appears_in_counts', $GLOBALS);
    $originalCache = $GLOBALS['exclude_degree_ids_from_appears_in_counts'] ?? NULL;
    db_query('INSERT INTO degrees (degree_id, major_code, catalog_year, school_id) VALUES (?, ?, ?, ?)', array($firstId, $code, 2023, 0));
    $firstRowId = intval(db_insert_id());
    db_query('INSERT INTO degrees (degree_id, major_code, catalog_year, school_id) VALUES (?, ?, ?, ?)', array($secondId, $code, 2024, 0));
    $secondRowId = intval(db_insert_id());
    try {
      variable_set('exclude_majors_from_appears_in_counts', $code);
      unset($GLOBALS['exclude_degree_ids_from_appears_in_counts'][0]);
      $this->assertEqualsCanonicalizing(array($firstId, $secondId), system_get_exclude_degree_ids_from_appears_in_counts(0));
      $this->assertEqualsCanonicalizing(array($firstId, $secondId), $GLOBALS['exclude_degree_ids_from_appears_in_counts'][0]);
    }
    finally {
      db_query('DELETE FROM degrees WHERE id = ?', array($firstRowId));
      db_query('DELETE FROM degrees WHERE id = ?', array($secondRowId));
      if ($variableExisted) variable_set('exclude_majors_from_appears_in_counts', $originalVariable); else variable_delete('exclude_majors_from_appears_in_counts');
      if ($cacheExisted) $GLOBALS['exclude_degree_ids_from_appears_in_counts'] = $originalCache; else unset($GLOBALS['exclude_degree_ids_from_appears_in_counts']);
    }
  }

  /**
   * Confirms the degree/track assignment guard blocks duplicate placement in a
   * major family, but honors administrators who disable that safeguard.
   */
  public function testCourseAssignmentGuardHonorsDegreeAndTrackSetting(): void {
    $code = 'SYSTEMASSIGN' . substr(sha1(uniqid('', TRUE)), 0, 10);
    $baseId = random_int(700000000, 749999999);
    $trackId = random_int(750000000, 799999999);
    $variableExisted = variable_exists('prevent_course_assignment_to_both_degree_and_track');
    $originalVariable = variable_get('prevent_course_assignment_to_both_degree_and_track', NULL);
    db_query('INSERT INTO degrees (degree_id, major_code, catalog_year, school_id) VALUES (?, ?, ?, ?)', array($baseId, $code, 2024, 0));
    $baseRowId = intval(db_insert_id());
    db_query('INSERT INTO degrees (degree_id, major_code, catalog_year, school_id) VALUES (?, ?, ?, ?)', array($trackId, $code . '|_DATA', 2024, 0));
    $trackRowId = intval(db_insert_id());
    try {
      $course = new Course();
      $course->school_id = 0;
      $course->assigned_to_degree_ids_array = array($baseId);
      variable_set('prevent_course_assignment_to_both_degree_and_track', 'yes');
      $this->assertFalse(system_flightpath_can_assign_course_to_degree_id($trackId, $course));
      variable_set('prevent_course_assignment_to_both_degree_and_track', 'no');
      $this->assertTrue(system_flightpath_can_assign_course_to_degree_id($trackId, $course));
    }
    finally {
      db_query('DELETE FROM degrees WHERE id = ?', array($baseRowId));
      db_query('DELETE FROM degrees WHERE id = ?', array($trackRowId));
      unset($GLOBALS['fp_temp_degree_major_codes'][$baseId], $GLOBALS['fp_temp_degree_major_codes'][$trackId]);
      if ($variableExisted) variable_set('prevent_course_assignment_to_both_degree_and_track', $originalVariable); else variable_delete('prevent_course_assignment_to_both_degree_and_track');
    }
  }

  /**
   * Verifies system status escalates stale cron execution while reporting a
   * normal state and last-run date after a recent successful cron run.
   */
  public function testSystemStatusReflectsCronFreshness(): void {
    $variableExisted = variable_exists('cron_last_run');
    $originalVariable = variable_get('cron_last_run', NULL);
    $settingsExisted = array_key_exists('fp_system_settings', $GLOBALS);
    $originalSettings = $GLOBALS['fp_system_settings'] ?? NULL;
    try {
      $GLOBALS['fp_system_settings'] = array('base_url' => 'https://system-test.example', 'cron_security_token' => 'test-token');
      variable_set('cron_last_run', time() - (3 * 86400));
      $stale = system_status();
      $this->assertSame('alert', $stale['severity']);
      $this->assertStringContainsString("Cron hasn't run in over 2 days", strip_tags($stale['status']));
      variable_set('cron_last_run', time());
      $recent = system_status();
      $this->assertSame('normal', $recent['severity']);
      $this->assertStringContainsString('Cron was last run on', strip_tags($recent['status']));
      $this->assertStringContainsString('https://system-test.example/cron.php?t=test-token', $recent['status']);
    }
    finally {
      if ($variableExisted) variable_set('cron_last_run', $originalVariable); else variable_delete('cron_last_run');
      if ($settingsExisted) $GLOBALS['fp_system_settings'] = $originalSettings; else unset($GLOBALS['fp_system_settings']);
    }
  }

  /**
   * Confirms maintenance pages communicate the correct all-user or student-only
   * login restriction while retaining a route back to the normal login screen.
   */
  public function testDisableLoginPagesDescribeTheCorrectRestriction(): void {
    $allUsers = system_display_disable_login_page();
    $students = system_display_disable_login_page('student');

    $this->assertStringContainsString('Logins Currently Disabled', strip_tags($allUsers));
    $this->assertStringContainsString('student logins are disabled', strip_tags($students));
    $this->assertStringContainsString('Return to login page', strip_tags($allUsers));
    $this->assertStringContainsString('Return to login page', strip_tags($students));
  }

  /**
   * Confirms theme discovery exposes the core theme with its human-readable
   * name, description, type, and filesystem location for settings forms.
   */
  public function testAvailableThemesIncludesCoreThemeMetadata(): void {
    $themes = system_get_available_themes();

    $this->assertArrayHasKey('themes/fp_clean', $themes);
    $themeDescription = strip_tags($themes['themes/fp_clean']);
    $this->assertStringContainsString('FlightPath Clean Theme', $themeDescription);
    $this->assertStringContainsString('A clean theme for FlightPath', $themeDescription);
    $this->assertStringContainsString('Type: Core', $themeDescription);
    $this->assertStringContainsString('Location: themes/fp_clean', $themeDescription);
  }

  /**
   * Verifies the fallback login-help page supplies actionable support guidance
   * whenever an administrator has not configured a content-page redirect.
   */
  public function testLoginHelpDisplaysGenericGuidanceWithoutConfiguredContent(): void {
    $variableExisted = variable_exists('login_help_cid');
    $originalVariable = variable_get('login_help_cid', NULL);
    try {
      variable_set('login_help_cid', 0);
      $output = system_display_login_help_page();

      $text = preg_replace('/\\s+/', ' ', strip_tags($output));
      $this->assertStringContainsString('If you need help logging in to FlightPath', $text);
      $this->assertStringContainsString('contact the system administrator or your IT department', $text);
    }
    finally {
      if ($variableExisted) variable_set('login_help_cid', $originalVariable); else variable_delete('login_help_cid');
    }
  }

  /**
   * Ensures the production-contact form retains useful reporting categories,
   * a multi-line comment field, and its confirmation-page contract.
   */
  public function testPopupContactFormAndThankYouPageDescribeReportingFlow(): void {
    $variableExisted = variable_exists('system_name');
    $originalVariable = variable_get('system_name', NULL);
    try {
      variable_set('system_name', 'System Test FlightPath');
      $form = system_popup_report_contact_form();
      $thankYou = system_popup_report_contact_thank_you();

      $this->assertStringContainsString('System Test FlightPath Production Team', strip_tags($form['mark0']['value']));
      $this->assertSame('select', $form['category']['type']);
      $this->assertArrayHasKey('Dashboard', $form['category']['options']);
      $this->assertArrayHasKey('Other', $form['category']['options']);
      $this->assertSame('textarea', $form['comment']['type']);
      $this->assertSame(7, $form['comment']['rows']);
      $this->assertTrue($form['submit']['spinner']);
      $this->assertSame(array('path' => 'popup-contact-form/thank-you'), $form['#redirect']);
      $this->assertStringContainsString('System Test FlightPath Production Team', strip_tags($thankYou));
      $this->assertStringContainsString('You may now close this window', strip_tags($thankYou));
      $this->assertStringContainsString("class='button'", $thankYou);
    }
    finally {
      if ($variableExisted) variable_set('system_name', $originalVariable); else variable_delete('system_name');
    }
  }

  /**
   * Confirms the MFA form protects the address in its prompt and requires a
   * code while offering an explicit opt-in for trusted-device recognition.
   */
  public function testMfaLoginFormMasksEmailAndDefinesRequiredControls(): void {
    $sessionExisted = array_key_exists('mfa__form_state_db_row', $_SESSION);
    $originalSession = $_SESSION['mfa__form_state_db_row'] ?? NULL;
    try {
      $_SESSION['mfa__form_state_db_row'] = array('user_id' => 998001, 'email' => 'student@example.edu');
      $form = system_mfa_login_form();

      $this->assertStringContainsString('stude' . str_repeat('*', strlen('student@example.edu') - 5), strip_tags($form['mark_top_msg']['value']));
      $this->assertSame('textfield', $form['mfa_code']['type']);
      $this->assertTrue($form['mfa_code']['required']);
      $this->assertSame('checkbox', $form['mfa_remember']['type']);
      $this->assertSame('Submit', $form['submit_btn']['value']);
      $this->assertTrue($form['submit_btn']['spinner']);
    }
    finally {
      if ($sessionExisted) $_SESSION['mfa__form_state_db_row'] = $originalSession; else unset($_SESSION['mfa__form_state_db_row']);
    }
  }

  /**
   * Verifies MFA validation accepts the stored numeric code and records a
   * field-specific error for a mismatch, preventing an unintended login.
   */
  public function testMfaLoginValidationAcceptsMatchingCodeAndRejectsMismatch(): void {
    if (!function_exists('user_set_attribute')) require_once __DIR__ . '/../../modules/user/user.module';
    $userId = random_int(800000000, 899999999);
    $sessionRowExisted = array_key_exists('mfa__form_state_db_row', $_SESSION);
    $originalSessionRow = $_SESSION['mfa__form_state_db_row'] ?? NULL;
    $errorsExisted = array_key_exists('fp_form_errors', $_SESSION);
    $originalErrors = $_SESSION['fp_form_errors'] ?? NULL;
    $messagesExisted = array_key_exists('fp_messages', $_SESSION);
    $originalMessages = $_SESSION['fp_messages'] ?? NULL;
    try {
      $_SESSION['mfa__form_state_db_row'] = array('user_id' => $userId, 'email' => 'student@example.edu');
      $_SESSION['fp_form_errors'] = array();
      $_SESSION['fp_messages'] = array();
      user_set_attribute($userId, 'mfa_validation_code', '123456');

      system_mfa_login_form_validate(array(), array('values' => array('mfa_code' => '123456')));
      $this->assertFalse(form_has_errors());

      system_mfa_login_form_validate(array(), array('values' => array('mfa_code' => '654321')));
      $this->assertTrue(form_has_errors());
      $this->assertSame('mfa_code', $_SESSION['fp_form_errors'][0]['name']);
      $this->assertStringContainsString('not the same that was sent', $_SESSION['fp_form_errors'][0]['msg']);
    }
    finally {
      db_query('DELETE FROM user_attributes WHERE user_id = ? AND name = ?', array($userId, 'mfa_validation_code'));
      if ($sessionRowExisted) $_SESSION['mfa__form_state_db_row'] = $originalSessionRow; else unset($_SESSION['mfa__form_state_db_row']);
      if ($errorsExisted) $_SESSION['fp_form_errors'] = $originalErrors; else unset($_SESSION['fp_form_errors']);
      if ($messagesExisted) $_SESSION['fp_messages'] = $originalMessages; else unset($_SESSION['fp_messages']);
    }
  }
}
