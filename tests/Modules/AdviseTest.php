<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for Advise navigation, draft-mode state, and permissions.
 */
class AdviseTest extends FlightPathTestCase {

  private array $advisingSessionIds = array();
  private array $advisedCourseIds = array();
  private array $advisorAssignments = array();
  private array $courseRowIds = array();
  private array $degreeRowIds = array();
  private array $studentCourseIds = array();
  private array $degreeTrackIds = array();
  private array $studentIds = array();
  private array $userIds = array();
  private bool $maxSelectionsExisted;
  private mixed $originalMaxSelections;
  private bool $advisingGlobalExisted;
  private mixed $originalAdvisingGlobal;
  private bool $currentStudentGlobalExisted;
  private mixed $originalCurrentStudentGlobal;
  private bool $jsSettingsExisted;
  private mixed $originalJsSettings;
  private bool $screenGlobalExisted;
  private mixed $originalScreenGlobal;
  private bool $degreeCacheExisted;
  private mixed $originalDegreeCache;
  private array $originalAdviseVariables = array();

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('advise_menu')) {
      require_once __DIR__ . '/../../modules/advise/advise.module';
    }

    $_SESSION['fp_draft_mode'] = 'no';
    $this->maxSelectionsExisted = variable_exists('max_allowed_selections_in_what_if');
    $this->originalMaxSelections = variable_get('max_allowed_selections_in_what_if', 5);
    $this->advisingGlobalExisted = array_key_exists('fp_advising', $GLOBALS);
    $this->originalAdvisingGlobal = $GLOBALS['fp_advising'] ?? NULL;
    $this->currentStudentGlobalExisted = array_key_exists('current_student_id', $GLOBALS);
    $this->originalCurrentStudentGlobal = $GLOBALS['current_student_id'] ?? NULL;
    $this->jsSettingsExisted = array_key_exists('fp_extra_js_settings', $GLOBALS);
    $this->originalJsSettings = $GLOBALS['fp_extra_js_settings'] ?? NULL;
    $this->screenGlobalExisted = array_key_exists('screen', $GLOBALS);
    $this->originalScreenGlobal = $GLOBALS['screen'] ?? NULL;
    $this->degreeCacheExisted = array_key_exists('degreeplan_cache', $GLOBALS);
    $this->originalDegreeCache = $GLOBALS['degreeplan_cache'] ?? NULL;
    foreach (array(
      'current_catalog_year',
      'earliest_catalog_year',
      'what_if_catalog_year_mode',
      'advise_last_run_delete_flag_removal',
      'delete_flagged_data_from_db',
      'available_advising_term_ids',
      'advising_term_id',
      'show_level_3_on_what_if_selection',
    ) as $name) {
      $this->originalAdviseVariables[$name] = array(
        'exists' => variable_exists($name),
        'value' => variable_get($name, NULL),
      );
    }
    unset($_SESSION['fp_form_errors']);
  }

  protected function tearDown(): void {
    foreach ($this->advisedCourseIds as $advisedCourseId) {
      db_query('DELETE FROM advised_courses WHERE id = ?', array($advisedCourseId));
    }

    foreach ($this->advisingSessionIds as $advisingSessionId) {
      db_query(
        'DELETE FROM advising_sessions WHERE advising_session_id = ?',
        array($advisingSessionId)
      );
    }

    foreach ($this->studentCourseIds as $studentCourseId) {
      db_query('DELETE FROM student_courses WHERE id = ?', array($studentCourseId));
    }

    foreach ($this->courseRowIds as $courseRowId) {
      db_query('DELETE FROM courses WHERE id = ?', array($courseRowId));
    }

    foreach ($this->degreeTrackIds as $degreeTrackId) {
      db_query('DELETE FROM degree_tracks WHERE track_id = ?', array($degreeTrackId));
    }

    foreach ($this->degreeRowIds as $degreeRowId) {
      db_query('DELETE FROM degrees WHERE id = ?', array($degreeRowId));
    }

    foreach ($this->advisorAssignments as $assignment) {
      db_query(
        'DELETE FROM advisor_student WHERE faculty_id = ? AND student_id = ?',
        array($assignment['faculty_id'], $assignment['student_id'])
      );
    }

    foreach ($this->userIds as $userId) {
      db_query('DELETE FROM users WHERE user_id = ?', array($userId));
    }

    foreach ($this->studentIds as $studentId) {
      db_query('DELETE FROM students WHERE cwid = ?', array($studentId));
    }

    if ($this->maxSelectionsExisted) {
      variable_set('max_allowed_selections_in_what_if', $this->originalMaxSelections);
    }
    else {
      variable_delete('max_allowed_selections_in_what_if');
    }

    foreach ($this->originalAdviseVariables as $name => $original) {
      if ($original['exists']) {
        variable_set($name, $original['value']);
      }
      else {
        variable_delete($name);
      }
    }

    foreach (array_keys($_SESSION) as $key) {
      if (str_starts_with($key, 'advising_') || str_starts_with($key, 'what_if_')) {
        unset($_SESSION[$key]);
      }
    }

    unset($_SESSION['current_student_id']);
    unset($_SESSION['fp_draft_mode']);
    unset($_SESSION['fp_form_errors']);

    if ($this->advisingGlobalExisted) {
      $GLOBALS['fp_advising'] = $this->originalAdvisingGlobal;
    }
    else {
      unset($GLOBALS['fp_advising']);
    }

    if ($this->currentStudentGlobalExisted) {
      $GLOBALS['current_student_id'] = $this->originalCurrentStudentGlobal;
    }
    else {
      unset($GLOBALS['current_student_id']);
    }

    if ($this->jsSettingsExisted) {
      $GLOBALS['fp_extra_js_settings'] = $this->originalJsSettings;
    }
    else {
      unset($GLOBALS['fp_extra_js_settings']);
    }

    if ($this->screenGlobalExisted) {
      $GLOBALS['screen'] = $this->originalScreenGlobal;
    }
    else {
      unset($GLOBALS['screen']);
    }

    if ($this->degreeCacheExisted) {
      $GLOBALS['degreeplan_cache'] = $this->originalDegreeCache;
    }
    else {
      unset($GLOBALS['degreeplan_cache']);
    }

    foreach ($this->studentIds as $studentId) {
      unset($GLOBALS['db_get_student_catalog_year'][$studentId]);
    }

    parent::tearDown();
  }

  /**
   * Inserts an isolated advising session and remembers its generated ID so the
   * shared test database is restored during teardown.
   */
  private function insertAdvisingSession(array $overrides = array()): int {
    $values = array_merge(array(
      'student_id' => 'ADVISE_TEST_STUDENT',
      'faculty_id' => 'ADVISE_TEST_FACULTY',
      'term_id' => '202410',
      'degree_id' => 0,
      'major_code_csv' => 'TEST',
      'catalog_year' => 2024,
      'posted' => 1700000000,
      'is_whatif' => 0,
      'is_draft' => 0,
      'is_empty' => 0,
      'advising_session_token' => 'advise-test-token',
      'delete_flag' => 0,
      'most_recent_session' => 1,
    ), $overrides);

    db_query(
      'INSERT INTO advising_sessions
       (student_id, faculty_id, term_id, degree_id, major_code_csv,
        catalog_year, posted, is_whatif, is_draft, is_empty,
        advising_session_token, delete_flag, most_recent_session)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      array(
        $values['student_id'],
        $values['faculty_id'],
        $values['term_id'],
        $values['degree_id'],
        $values['major_code_csv'],
        $values['catalog_year'],
        $values['posted'],
        $values['is_whatif'],
        $values['is_draft'],
        $values['is_empty'],
        $values['advising_session_token'],
        $values['delete_flag'],
        $values['most_recent_session'],
      )
    );

    $advisingSessionId = intval(db_insert_id());
    $this->advisingSessionIds[] = $advisingSessionId;
    return $advisingSessionId;
  }

  /**
   * Creates the linked user and student rows required by advised-course counts.
   */
  private function insertStudentAccount(string $studentId, bool $isActive = TRUE, int $catalogYear = 2024): void {
    db_query(
      'INSERT INTO students (cwid, catalog_year, is_active) VALUES (?, ?, ?)',
      array($studentId, $catalogYear, $isActive ? 1 : 0)
    );
    $this->studentIds[] = $studentId;

    db_query(
      'INSERT INTO users (user_name, is_student, cwid, f_name, l_name)
       VALUES (?, ?, ?, ?, ?)',
      array('user_' . $studentId, 1, $studentId, 'Test', 'Student')
    );
    $this->userIds[] = intval(db_insert_id());
  }

  /**
   * Adds a course to an advising session and tracks the generated row ID.
   */
  private function insertAdvisedCourse(int $advisingSessionId, int $courseId): void {
    db_query(
      'INSERT INTO advised_courses (advising_session_id, course_id) VALUES (?, ?)',
      array($advisingSessionId, $courseId)
    );
    $this->advisedCourseIds[] = intval(db_insert_id());
  }

  /**
   * Creates a catalog course row used by enrollment-count tests.
   */
  private function insertCourseRow(int $courseId, bool $deleted = FALSE): void {
    db_query(
      'INSERT INTO courses
       (course_id, subject_id, course_num, catalog_year, title, delete_flag, school_id)
       VALUES (?, ?, ?, ?, ?, ?, ?)',
      array($courseId, 'TST', '100', 2024, 'Advise Test Course', $deleted ? 1 : 0, 0)
    );
    $this->courseRowIds[] = intval(db_insert_id());
  }

  /**
   * Creates a minimal degree row for What If combination validation.
   */
  private function insertDegreeRow(string $majorCode, bool $allowDynamic, string $degreeClass = 'MAJOR', string $degreeLevel = 'UG'): int {
    $degreeId = random_int(600000000, 699999999);
    db_query(
      'INSERT INTO degrees
       (degree_id, major_code, degree_type, degree_level, degree_class, title,
        catalog_year, allow_dynamic, school_id)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
      array(
        $degreeId,
        $majorCode,
        'BS',
        $degreeLevel,
        $degreeClass,
        $allowDynamic ? 'Dynamic Test Degree' : 'Static Test Degree',
        2024,
        $allowDynamic ? 1 : 0,
        0,
      )
    );
    $this->degreeRowIds[] = intval(db_insert_id());
    return $degreeId;
  }

  /**
   * Creates a level-three track lookup row for What If form tests.
   */
  private function insertDegreeTrack(string $majorCode, string $trackCode): void {
    db_query(
      'INSERT INTO degree_tracks (catalog_year, major_code, track_code, track_title, school_id)
       VALUES (?, ?, ?, ?, ?)',
      array(2024, $majorCode, $trackCode, 'Test Track', 0)
    );
    $this->degreeTrackIds[] = intval(db_insert_id());
  }

  /**
   * Creates a student-course row used by enrollment-count tests.
   */
  private function insertStudentCourse(string $studentId, int $courseId, string $termId): void {
    db_query(
      'INSERT INTO student_courses (student_id, term_id, course_id) VALUES (?, ?, ?)',
      array($studentId, $termId, $courseId)
    );
    $this->studentCourseIds[] = intval(db_insert_id());
  }

  /**
   * Confirms degree, What If, history, and draft-mode routes use their intended
   * callbacks and access controls for advising workflows.
   */
  public function testMenuDefinesAdvisingAndDraftModeRoutes(): void {
    $items = advise_menu();

    $this->assertSame('fp_render_form', $items['admin-tools/toggle-draft']['page_callback']);
    $this->assertSame(array('advise_toggle_draft_form'), $items['admin-tools/toggle-draft']['page_arguments']);
    $this->assertSame(array('toggle_draft'), $items['admin-tools/toggle-draft']['access_arguments']);
    $this->assertSame('advise_display_view', $items['view']['page_callback']);
    $this->assertSame(array('view'), $items['view']['page_arguments']);
    $this->assertSame('advise_can_access_view', $items['view']['access_callback']);
    $this->assertSame(array('what-if'), $items['what-if']['page_arguments']);
    $this->assertSame('advise_display_history', $items['history']['page_callback']);
    $this->assertSame(MENU_TYPE_TAB, $items['history']['type']);
  }

  /**
   * Confirms print callbacks bypass interactive advising access while forcing
   * the non-advising screen modes needed for printable degree views.
   */
  public function testMenuDefinesPrintableDegreeAndWhatIfCallbacks(): void {
    $items = advise_menu();

    $this->assertSame('advise_display_view', $items['view/print']['page_callback']);
    $this->assertSame(array('view'), $items['view/print']['page_arguments']);
    $this->assertTrue($items['view/print']['access_callback']);
    $this->assertSame('not_advising', $items['view/print']['page_settings']['screen_mode']);
    $this->assertSame(array('what-if'), $items['what-if/print']['page_arguments']);
    $this->assertTrue($items['what-if/print']['page_settings']['bool_print']);
    $this->assertSame('not_advising', $items['what-if/print']['page_settings']['screen_mode']);
  }

  /**
   * Verifies popup and toolbox routes retain their callback, permission, and
   * popup-display contracts so privileged advising actions stay protected.
   */
  public function testMenuDefinesPopupAndToolboxRouteContracts(): void {
    $items = advise_menu();

    $this->assertSame('advise_display_popup_change_term', $items['advise/popup-change-term']['page_callback']);
    $this->assertSame(array('can_advise_students'), $items['advise/popup-change-term']['access_arguments']);
    $this->assertTrue($items['advise/popup-change-term']['page_settings']['page_is_popup']);
    $this->assertSame('advise_display_popup_course_description', $items['advise/popup-course-description']['page_callback']);
    $this->assertSame(array('view_degree_plan_course_descriptions'), $items['advise/popup-course-description']['access_arguments']);
    $this->assertSame('advise_display_popup_toolbox_substitutions', $items['advise/popup-toolbox/substitutions']['page_callback']);
    $this->assertSame(array('can_substitute'), $items['advise/popup-toolbox/substitutions']['access_arguments']);
    $this->assertSame('advise-toolbox', $items['advise/popup-toolbox/substitutions']['tab_family']);
  }

  /**
   * Ensures the draft-mode form reflects the active session setting and supplies
   * the explicit redirect that returns staff to the dashboard after changing it.
   */
  public function testToggleDraftFormUsesCurrentSessionMode(): void {
    $_SESSION['fp_draft_mode'] = 'yes';

    $form = advise_toggle_draft_form();

    $this->assertStringContainsString('Draft Mode', strip_tags($form['mark_top']['value']));
    $this->assertSame('radios', $form['draft']['type']);
    $this->assertSame(array('yes' => 'Yes', 'no' => 'No'), $form['draft']['options']);
    $this->assertSame('yes', $form['draft']['value']);
    $this->assertSame('main', $form['#redirect']['path']);
  }

  /**
   * Verifies a submitted draft-mode choice is retained in the session, which
   * the advising and Blank Degrees screens use to select draft degree data.
   */
  public function testToggleDraftSubmitStoresSessionMode(): void {
    advise_toggle_draft_form_submit(array(), array('values' => array('draft' => 'yes')));

    $this->assertSame('yes', $_SESSION['fp_draft_mode']);
  }

  /**
   * Confirms permissions distinguish viewing sessions, advising students,
   * substitutions, course descriptions, and draft-mode operation.
   */
  public function testPermissionDefinesAdvisingCapabilities(): void {
    $permissions = advise_perm();

    $this->assertArrayHasKey('view_any_advising_session', $permissions);
    $this->assertArrayHasKey('view_advisee_advising_session', $permissions);
    $this->assertArrayHasKey('can_advise_students', $permissions);
    $this->assertArrayHasKey('can_substitute', $permissions);
    $this->assertArrayHasKey('view_degree_plan_course_descriptions', $permissions);
    $this->assertArrayHasKey('toggle_draft', $permissions);
    $this->assertSame('Can advise students', $permissions['can_advise_students']['title']);
  }

  /**
   * Ensures a multi-term advising action returns only its non-draft, nonempty
   * sessions and orders them with the newest term first.
   */
  public function testSessionsForTokenExcludeDraftAndEmptyRowsAndOrderTerms(): void {
    $token = 'advise-test-token-' . uniqid('', TRUE);
    $olderId = $this->insertAdvisingSession(array(
      'term_id' => '202410',
      'advising_session_token' => $token,
    ));
    $newerId = $this->insertAdvisingSession(array(
      'term_id' => '202430',
      'advising_session_token' => $token,
    ));
    $this->insertAdvisingSession(array(
      'term_id' => '202440',
      'is_draft' => 1,
      'advising_session_token' => $token,
    ));
    $this->insertAdvisingSession(array(
      'term_id' => '202450',
      'is_empty' => 1,
      'advising_session_token' => $token,
    ));

    $sessionIds = advise_get_advising_sessions_for_advising_token($token);

    $this->assertSame(array($newerId, $olderId), array_map('intval', $sessionIds));
  }

  /**
   * Confirms an unknown token returns false rather than an empty collection,
   * preserving the helper's documented missing-session contract.
   */
  public function testSessionsForUnknownTokenReturnFalse(): void {
    $this->assertFalse(
      advise_get_advising_sessions_for_advising_token('missing-advise-token-' . uniqid('', TRUE))
    );
  }

  /**
   * Verifies direct session lookup returns the academic and lifecycle fields
   * belonging to the requested record, including What If and draft state.
   */
  public function testSessionLookupReturnsRequestedLifecycleState(): void {
    $advisingSessionId = $this->insertAdvisingSession(array(
      'student_id' => 'ADVISE_LOOKUP_STUDENT',
      'term_id' => '202520',
      'degree_id' => 321,
      'catalog_year' => 2025,
      'is_whatif' => 1,
      'is_draft' => 1,
      'most_recent_session' => 0,
    ));

    $session = advise_get_advising_session_from_advising_session_id($advisingSessionId);

    $this->assertSame($advisingSessionId, intval($session['advising_session_id']));
    $this->assertSame('ADVISE_LOOKUP_STUDENT', $session['student_id']);
    $this->assertSame('202520', $session['term_id']);
    $this->assertSame(321, intval($session['degree_id']));
    $this->assertSame(2025, intval($session['catalog_year']));
    $this->assertSame(1, intval($session['is_whatif']));
    $this->assertSame(1, intval($session['is_draft']));
    $this->assertSame(0, intval($session['most_recent_session']));
  }

  /**
   * Confirms advisor assignments can be traversed in both directions, which is
   * required by advisee-limited access checks and advisor displays.
   */
  public function testAdvisorAssignmentsAreReturnedInBothDirections(): void {
    $facultyId = 'ATF_' . uniqid();
    $studentIds = array(
      'ATS_A_' . uniqid(),
      'ATS_B_' . uniqid(),
    );

    foreach ($studentIds as $studentId) {
      db_query(
        'INSERT INTO advisor_student (faculty_id, student_id) VALUES (?, ?)',
        array($facultyId, $studentId)
      );
      $this->advisorAssignments[] = array(
        'faculty_id' => $facultyId,
        'student_id' => $studentId,
      );
    }

    $this->assertEqualsCanonicalizing($studentIds, advise_get_advisees($facultyId));
    $this->assertSame(array($facultyId), advise_get_advisors_for_student($studentIds[0]));
  }

  /**
   * Verifies view access requires authentication and then permits an admin,
   * a broadly authorized viewer, an assigned advisor, or a student viewing
   * their own plan.
   */
  public function testViewAccessPermissionMatrix(): void {
    global $user, $current_student_id;
    $studentId = 'ADVISE_ACCESS_STUDENT';

    $user->id = 2;
    $user->cwid = 'OTHER_STUDENT';
    $user->permissions = array();
    $this->assertFalse(advise_can_access_view($studentId));

    $user->permissions = array('access_logged_in_content');
    $this->assertFalse(advise_can_access_view($studentId));

    $user->permissions[] = 'view_any_advising_session';
    $this->assertTrue(advise_can_access_view($studentId));

    $facultyId = 'AVA_' . uniqid();
    $user->cwid = $facultyId;
    $user->permissions = array('access_logged_in_content', 'view_advisee_advising_session');
    db_query(
      'INSERT INTO advisor_student (faculty_id, student_id) VALUES (?, ?)',
      array($facultyId, $studentId)
    );
    $this->advisorAssignments[] = array(
      'faculty_id' => $facultyId,
      'student_id' => $studentId,
    );
    $this->assertTrue(advise_can_access_view($studentId));

    $user->permissions = array('access_logged_in_content', 'view_own_advising_session');
    $user->cwid = $studentId;
    $this->assertTrue(advise_can_access_view($studentId));

    $user->id = 1;
    $user->permissions = array('access_logged_in_content');
    $this->assertTrue(advise_can_access_view($studentId));

    $current_student_id = '';
    $this->assertFalse(advise_can_access_view(''));
  }

  /**
   * Ensures advised-course reporting counts only active students whose session
   * is current, published, undeleted, and associated with the requested term.
   */
  public function testAdvisedCourseCountAppliesSessionAndStudentLifecycleFilters(): void {
    $courseId = random_int(900000000, 999999999);
    $termId = '202610';
    $scenarios = array(
      array('prefix' => 'ADC_OK_', 'active' => TRUE, 'overrides' => array()),
      array('prefix' => 'ADC_DRAFT_', 'active' => TRUE, 'overrides' => array('is_draft' => 1)),
      array('prefix' => 'ADC_DELETE_', 'active' => TRUE, 'overrides' => array('delete_flag' => 1)),
      array('prefix' => 'ADC_OLD_', 'active' => TRUE, 'overrides' => array('most_recent_session' => 0)),
      array('prefix' => 'ADC_INACTIVE_', 'active' => FALSE, 'overrides' => array()),
      array('prefix' => 'ADC_TERM_', 'active' => TRUE, 'overrides' => array('term_id' => '202620')),
    );

    foreach ($scenarios as $scenario) {
      $studentId = $scenario['prefix'] . uniqid();
      $this->insertStudentAccount($studentId, $scenario['active']);
      $sessionId = $this->insertAdvisingSession(array_merge(array(
        'student_id' => $studentId,
        'term_id' => $termId,
        'advising_session_token' => 'count-' . $studentId,
      ), $scenario['overrides']));
      $this->insertAdvisedCourse($sessionId, $courseId);
    }

    $this->assertSame(1, advise_get_count_of_advised_course_for_term($courseId, $termId));
  }

  /**
   * Ensures the advised-course metric keeps its documented student-level
   * meaning when duplicate course rows occur in the same advising session.
   */
  public function testAdvisedCourseCountIsUniquePerStudent(): void {
    $courseId = random_int(900000000, 999999999);
    $termId = '202610';
    $studentId = 'ADC_DUPLICATE_' . uniqid();
    $this->insertStudentAccount($studentId);
    $sessionId = $this->insertAdvisingSession(array(
      'student_id' => $studentId,
      'term_id' => $termId,
      'advising_session_token' => 'count-duplicate-' . $studentId,
    ));
    $this->insertAdvisedCourse($sessionId, $courseId);
    $this->insertAdvisedCourse($sessionId, $courseId);

    $this->assertSame(1, advise_get_count_of_advised_course_for_term($courseId, $termId));
  }

  /**
   * Verifies enrollment reporting counts distinct students for the requested
   * course and term while ignoring other terms and soft-deleted course rows.
   */
  public function testEnrolledCourseCountUsesDistinctStudentsTermAndActiveCourseRows(): void {
    $courseId = random_int(800000000, 899999999);
    $deletedCourseId = $courseId - 1;
    $termId = '202610';
    $this->insertCourseRow($courseId);
    $this->insertCourseRow($courseId);
    $this->insertCourseRow($deletedCourseId, TRUE);

    $this->insertStudentCourse('ENROLL_STUDENT_A', $courseId, $termId);
    $this->insertStudentCourse('ENROLL_STUDENT_A', $courseId, $termId);
    $this->insertStudentCourse('ENROLL_STUDENT_B', $courseId, $termId);
    $this->insertStudentCourse('ENROLL_OTHER_TERM', $courseId, '202620');
    $this->insertStudentCourse('ENROLL_DELETED', $deletedCourseId, $termId);

    $this->assertSame(2, advise_get_count_of_enrolled_course_for_term($courseId, $termId));
    $this->assertSame(0, advise_get_count_of_enrolled_course_for_term($deletedCourseId, $termId));
  }

  /**
   * Verifies cron respects its seven-day throttle, preserving flagged advising
   * records until the scheduled cleanup window is eligible to run.
   */
  public function testCronDoesNotDeleteFlaggedAdvisingDataBeforeThrottleExpires(): void {
    $sessionId = $this->insertAdvisingSession(array(
      'delete_flag' => 1,
      'advising_session_token' => 'cron-throttle-' . uniqid(),
    ));
    $this->insertAdvisedCourse($sessionId, random_int(900000000, 999999999));
    variable_set('delete_flagged_data_from_db', array('advising_sessions' => 1));
    variable_set('advise_last_run_delete_flag_removal', time());

    advise_cron();

    $result = db_query(
      'SELECT advising_session_id FROM advising_sessions WHERE advising_session_id = ?',
      array($sessionId)
    );
    $this->assertSame($sessionId, intval(db_result($result)));
    $this->assertCount(1, $this->advisedCourseIds);
    $result = db_query('SELECT id FROM advised_courses WHERE id = ?', array($this->advisedCourseIds[0]));
    $this->assertSame($this->advisedCourseIds[0], intval(db_result($result)));
  }

  /**
   * Confirms cron records that it ran but leaves flagged advising data intact
   * when the administrator has not enabled advising-session cleanup.
   */
  public function testCronLeavesFlaggedAdvisingDataWhenCleanupIsDisabled(): void {
    $sessionId = $this->insertAdvisingSession(array(
      'delete_flag' => 1,
      'advising_session_token' => 'cron-disabled-' . uniqid(),
    ));
    $this->insertAdvisedCourse($sessionId, random_int(900000000, 999999999));
    variable_set('delete_flagged_data_from_db', array());
    variable_set('advise_last_run_delete_flag_removal', 1);

    advise_cron();

    $result = db_query(
      'SELECT advising_session_id FROM advising_sessions WHERE advising_session_id = ?',
      array($sessionId)
    );
    $this->assertSame($sessionId, intval(db_result($result)));
    $result = db_query('SELECT id FROM advised_courses WHERE id = ?', array($this->advisedCourseIds[0]));
    $this->assertSame($this->advisedCourseIds[0], intval(db_result($result)));
    $this->assertGreaterThan(1, intval(variable_get('advise_last_run_delete_flag_removal', 0)));
  }

  /**
   * Confirms the term picker lists configured terms, marks the active one, and
   * retains the JavaScript action that applies the selected advising term.
   */
  public function testChangeTermPopupListsConfiguredTermsAndMarksCurrentTerm(): void {
    global $current_student_id;
    $currentStudentId = 'TERM_POPUP_' . substr(sha1(uniqid('', TRUE)), 0, 12);
    $current_student_id = $currentStudentId;
    $currentTermId = '990001';
    $nextTermId = '990002';
    variable_set('available_advising_term_ids', $currentTermId . ',' . $nextTermId);
    $_REQUEST['advising_term_id'] = $currentTermId;

    $popup = advise_display_popup_change_term();

    $visibleText = strip_tags($popup);
    $this->assertStringContainsString($currentTermId, $visibleText);
    $this->assertStringContainsString($nextTermId, $visibleText);
    $this->assertStringContainsString("class='current-term'", $popup);
    $expectedAction = 'parent.fpCloseSmallIframeDialog("");parent.changeTerm("' . $nextTermId . '");';
    $this->assertStringContainsString(base64_encode($expectedAction), $popup);
  }

  /**
   * Ensures extensions can append a machine-readable parent callback while
   * unsafe punctuation is excluded from the JavaScript confirmation action.
   */
  public function testChangeTermPopupSanitizesAppendedParentCallback(): void {
    global $current_student_id;
    $current_student_id = 'TERM_APPEND_' . substr(sha1(uniqid('', TRUE)), 0, 12);
    $termId = '990003';
    $appendValue = 'Custom-Callback() <bad>';
    variable_set('available_advising_term_ids', $termId);
    $_REQUEST['advising_term_id'] = $termId;
    $_REQUEST['append_parent_function'] = $appendValue;

    $popup = advise_display_popup_change_term();

    $expectedAction = 'parent.fpCloseSmallIframeDialog("");parent.changeTerm' . fp_get_machine_readable($appendValue) . '("' . $termId . '");';
    $this->assertStringContainsString(base64_encode($expectedAction), $popup);
    $this->assertStringNotContainsString($appendValue, $popup);
  }

  /**
   * Verifies record-level session access for unrestricted viewers, the owning
   * student, an assigned advisor, and unrelated authenticated users.
   */
  public function testAdvisingSessionAccessCallbackChecksRecordOwnership(): void {
    global $user;
    $studentId = 'ASA_STUDENT_' . uniqid();
    $sessionId = $this->insertAdvisingSession(array('student_id' => $studentId));
    $_REQUEST['advising_session_id'] = $sessionId;

    $user->id = 2;
    $user->cwid = 'ASA_OTHER';
    $user->permissions = array();
    $this->assertFalse(advise_user_can_view_advising_session_access_callback());

    $user->permissions = array('view_own_advising_session');
    $user->cwid = $studentId;
    $this->assertTrue(advise_user_can_view_advising_session_access_callback());

    $facultyId = 'ASA_FAC_' . uniqid();
    $user->cwid = $facultyId;
    $user->permissions = array('view_advisee_advising_session');
    db_query(
      'INSERT INTO advisor_student (faculty_id, student_id) VALUES (?, ?)',
      array($facultyId, $studentId)
    );
    $this->advisorAssignments[] = array(
      'faculty_id' => $facultyId,
      'student_id' => $studentId,
    );
    $this->assertTrue(advise_user_can_view_advising_session_access_callback());

    $user->cwid = 'ASA_UNRELATED';
    $this->assertFalse(advise_user_can_view_advising_session_access_callback());

    $user->permissions = array('view_any_advising_session');
    $this->assertTrue(advise_user_can_view_advising_session_access_callback());
  }

  /**
   * Ensures initialization replaces an unauthorized requested student with the
   * logged-in student's identity in every request and session location.
   */
  public function testInitFallsBackToLoggedInStudentWhenAccessIsDenied(): void {
    global $user, $current_student_id;
    $user->id = 2;
    $user->cwid = 'INIT_OWN_STUDENT';
    $user->is_student = 1;
    $user->permissions = array('access_logged_in_content', 'view_own_advising_session');
    $_REQUEST['current_student_id'] = 'INIT_OTHER_STUDENT';
    $_GET['current_student_id'] = 'INIT_OTHER_STUDENT';
    $_POST['current_student_id'] = 'INIT_OTHER_STUDENT';

    advise_init();

    $this->assertSame('INIT_OWN_STUDENT', $current_student_id);
    $this->assertSame('INIT_OWN_STUDENT', $_REQUEST['current_student_id']);
    $this->assertSame('INIT_OWN_STUDENT', $_GET['current_student_id']);
    $this->assertSame('INIT_OWN_STUDENT', $_POST['current_student_id']);
    $this->assertSame('INIT_OWN_STUDENT', $_SESSION['current_student_id']);
    $this->assertSame('INIT_OWN_STUDENT', $_REQUEST['advising_student_id']);
    $this->assertSame('yes', $_REQUEST['advising_load_active']);
  }

  /**
   * Ensures an unauthorized non-student has no student context left in the
   * request, session, or advising globals after access enforcement runs.
   */
  public function testInitClearsUnauthorizedStudentForNonStudentUser(): void {
    global $user, $current_student_id;
    $user->id = 2;
    $user->cwid = 'INIT_UNAUTHORIZED_STAFF';
    $user->is_student = 0;
    $user->permissions = array('access_logged_in_content');
    $_REQUEST['current_student_id'] = 'INIT_PROTECTED_STUDENT';
    $_GET['current_student_id'] = 'INIT_PROTECTED_STUDENT';
    $_POST['current_student_id'] = 'INIT_PROTECTED_STUDENT';

    advise_init();

    $this->assertSame('', $current_student_id);
    $this->assertSame('', $_REQUEST['current_student_id']);
    $this->assertSame('', $_GET['current_student_id']);
    $this->assertSame('', $_POST['current_student_id']);
    $this->assertSame('', $_SESSION['current_student_id']);
    $this->assertSame('', $_REQUEST['advising_student_id']);
    $this->assertSame('', $GLOBALS['fp_advising']['advising_student_id']);
    $this->assertSame('yes', $_REQUEST['advising_load_active']);
  }

  /**
   * Confirms initialization preserves an explicitly requested student when the
   * current user has permission to view any advising session.
   */
  public function testInitPreservesRequestedStudentWhenAccessIsAllowed(): void {
    global $user, $current_student_id;
    $user->id = 2;
    $user->cwid = 'INIT_ADVISOR';
    $user->is_student = 0;
    $user->permissions = array('access_logged_in_content', 'view_any_advising_session');
    $_REQUEST['current_student_id'] = 'INIT_ALLOWED_STUDENT';

    advise_init();

    $this->assertSame('INIT_ALLOWED_STUDENT', $current_student_id);
    $this->assertSame('INIT_ALLOWED_STUDENT', $_REQUEST['current_student_id']);
    $this->assertArrayNotHasKey('advising_load_active', $_REQUEST);
  }

  /**
   * Confirms explicit advising request values populate globals and namespaced
   * session keys while draft mode controls the degree-data source.
   */
  public function testAdvisingVariablesUseRequestValuesAndStudentScopedSessionKeys(): void {
    $studentId = 'VARS_REQUEST_STUDENT';
    $_SESSION['fp_draft_mode'] = 'yes';
    $_REQUEST = array(
      'current_student_id' => $studentId,
      'advising_student_id' => $studentId,
      'advising_load_active' => 'yes',
      'advising_what_if' => 'yes',
      'advising_major_code' => 'MATH',
      'advising_track_degree_ids' => '101,102',
      'advising_term_id' => '202610',
      'what_if_major_code' => 'PHYS',
      'what_if_catalog_year' => '2025',
      'what_if_track_degree_ids' => '201,202',
      'print_view' => 'yes',
      'load_from_cache' => 'no',
      'advising_view' => 'type',
    );

    advise_init_advising_variables();

    $this->assertSame($studentId, $GLOBALS['fp_advising']['current_student_id']);
    $this->assertSame('MATH', $GLOBALS['fp_advising']['advising_major_code']);
    $this->assertSame('202610', $GLOBALS['fp_advising']['advising_term_id']);
    $this->assertSame('yes', $GLOBALS['fp_advising']['advising_what_if']);
    $this->assertSame('PHYS', $GLOBALS['fp_advising']['what_if_major_code']);
    $this->assertSame('no', $GLOBALS['fp_advising']['load_from_cache']);
    $this->assertSame('type', $GLOBALS['fp_advising']['advising_view']);
    $this->assertTrue($GLOBALS['fp_advising']['bool_use_draft']);
    $this->assertSame('202610', $_SESSION['advising_term_id' . $studentId]);
    $this->assertSame('PHYS', $_SESSION['what_if_major_code' . $studentId]);
  }

  /**
   * Verifies missing request values fall back to the current student's scoped
   * session state without loading a different student's advising choices.
   */
  public function testAdvisingVariablesFallBackToStudentScopedSessionValues(): void {
    $studentId = 'VARS_SESSION_STUDENT';
    $_SESSION['advising_student_id' . $studentId] = $studentId;
    $_SESSION['advising_major_code' . $studentId] = 'BIOL';
    $_SESSION['advising_track_degree_ids' . $studentId] = '301';
    $_SESSION['advising_term_id' . $studentId] = '202620';
    $_SESSION['advising_what_if' . $studentId] = 'yes';
    $_SESSION['what_if_major_code' . $studentId] = 'CHEM';
    $_SESSION['what_if_catalog_year' . $studentId] = '2024';
    $_SESSION['what_if_track_degree_ids' . $studentId] = '401';
    $_SESSION['advising_view' . $studentId] = 'year';
    $_REQUEST = array('current_student_id' => $studentId);

    advise_init_advising_variables();

    $this->assertSame($studentId, $GLOBALS['fp_advising']['advising_student_id']);
    $this->assertSame('BIOL', $GLOBALS['fp_advising']['advising_major_code']);
    $this->assertSame('301', $GLOBALS['fp_advising']['advising_track_degree_ids']);
    $this->assertSame('202620', $GLOBALS['fp_advising']['advising_term_id']);
    $this->assertSame('yes', $GLOBALS['fp_advising']['advising_what_if']);
    $this->assertSame('CHEM', $GLOBALS['fp_advising']['what_if_major_code']);
    $this->assertSame('2024', $GLOBALS['fp_advising']['what_if_catalog_year']);
    $this->assertSame('401', $GLOBALS['fp_advising']['what_if_track_degree_ids']);
    $this->assertSame('year', $GLOBALS['fp_advising']['advising_view']);
    $this->assertSame('yes', $GLOBALS['fp_advising']['load_from_cache']);
    $this->assertFalse($GLOBALS['fp_advising']['bool_use_draft']);
  }

  /**
   * Ensures callers can suppress What If values in the active global state while
   * retaining the student's submitted values in their namespaced session keys.
   */
  public function testAdvisingVariablesCanIgnoreWhatIfState(): void {
    $studentId = 'VARS_IGNORE_STUDENT';
    $_REQUEST = array(
      'current_student_id' => $studentId,
      'advising_student_id' => $studentId,
      'advising_major_code' => 'MATH',
      'advising_track_degree_ids' => '101',
      'advising_term_id' => '202610',
      'advising_what_if' => 'yes',
      'what_if_major_code' => 'PHYS',
      'what_if_catalog_year' => '2025',
      'what_if_track_degree_ids' => '201',
    );

    advise_init_advising_variables(TRUE);

    $this->assertSame('', $GLOBALS['fp_advising']['advising_what_if']);
    $this->assertSame('', $GLOBALS['fp_advising']['what_if_major_code']);
    $this->assertSame('', $GLOBALS['fp_advising']['what_if_catalog_year']);
    $this->assertSame('', $GLOBALS['fp_advising']['what_if_track_degree_ids']);
    $this->assertSame('yes', $_SESSION['advising_what_if' . $studentId]);
    $this->assertSame('PHYS', $_SESSION['what_if_major_code' . $studentId]);
  }

  /**
   * Ensures a request without an advising term uses the configured school
   * default and persists it in the current student's scoped session state.
   */
  public function testAdvisingVariablesUseConfiguredDefaultTerm(): void {
    $studentId = 'VARS_DEFAULT_TERM_' . substr(sha1(uniqid('', TRUE)), 0, 10);
    variable_set('advising_term_id', '990401');
    $_REQUEST = array(
      'current_student_id' => $studentId,
      'advising_student_id' => $studentId,
      'advising_major_code' => 'HIST',
      'advising_track_degree_ids' => '501',
      'advising_what_if' => 'no',
      'what_if_major_code' => '',
      'what_if_catalog_year' => '',
      'what_if_track_degree_ids' => '',
    );

    advise_init_advising_variables();

    $this->assertSame('990401', $GLOBALS['fp_advising']['advising_term_id']);
    $this->assertSame('990401', $_SESSION['advising_term_id' . $studentId]);
    $this->assertSame('', $GLOBALS['fp_advising']['advising_load_active']);
    $this->assertSame('yes', $GLOBALS['fp_advising']['load_from_cache']);
  }

  /**
   * Confirms What If validation rejects submission when no top-level degree is
   * selected, preventing construction of an empty hypothetical degree plan.
   */
  public function testWhatIfValidationRequiresTopLevelDegree(): void {
    $formState = array('values' => array(
      'catalog_year' => 2024,
      'current_student_id' => 'WHATIF_STUDENT',
    ));

    advise_what_if_selection_form_validate(array(), $formState);

    $this->assertTrue(form_has_errors());
    $this->assertSame('select_level_1_degrees', $_SESSION['fp_form_errors'][0]['name']);
    $this->assertStringContainsString(
      'must select at least one top-level degree',
      strip_tags($_SESSION['fp_form_errors'][0]['msg'])
    );
  }

  /**
   * Verifies required track selections enforce their configured minimum before
   * the hypothetical degree combination is accepted.
   */
  public function testWhatIfValidationEnforcesRequiredTrackMinimum(): void {
    $fieldName = 'L3__sel__concentration__for__MATH__xx';
    $formState = array('values' => array(
      'catalog_year' => 2024,
      'current_student_id' => 'WHATIF_STUDENT',
      'select_level_1_degrees' => array('MATH' => 'MATH'),
      $fieldName => array(),
      'L3__ops__concentration__for__MATH__xx' => '1~2',
    ));

    advise_what_if_selection_form_validate(array(), $formState);

    $this->assertTrue(form_has_errors());
    $this->assertSame($fieldName, $_SESSION['fp_form_errors'][0]['name']);
    $this->assertStringContainsString(
      'did not select the correct number of options',
      strip_tags($_SESSION['fp_form_errors'][0]['msg'])
    );
  }

  /**
   * Ensures What If validation applies the configured overall selection limit
   * after counting both top-level degrees and their selected tracks.
   */
  public function testWhatIfValidationEnforcesOverallSelectionLimit(): void {
    variable_set('max_allowed_selections_in_what_if', 2);
    $formState = array('values' => array(
      'catalog_year' => 2024,
      'current_student_id' => 'WHATIF_STUDENT',
      'select_level_1_degrees' => array('MATH' => 'MATH'),
      'L3__sel__concentration__for__MATH__xx' => array(
        'MATH|_DATA' => 'MATH|_DATA',
        'MATH|_STAT' => 'MATH|_STAT',
      ),
      'L3__ops__concentration__for__MATH__xx' => '0~0',
    ));

    advise_what_if_selection_form_validate(array(), $formState);

    $this->assertTrue(form_has_errors());
    $this->assertSame('', $_SESSION['fp_form_errors'][0]['name']);
    $this->assertStringContainsString(
      'exceeded the maximum number of allowed selections',
      strip_tags($_SESSION['fp_form_errors'][0]['msg'])
    );
  }

  /**
   * Confirms a radio-style track selection is accepted when its selected value
   * belongs to a selected top-level degree and satisfies its exact requirement.
   */
  public function testWhatIfValidationAcceptsSelectedRadioTrack(): void {
    variable_set('max_allowed_selections_in_what_if', 3);
    $formState = array('values' => array(
      'catalog_year' => 2024,
      'current_student_id' => 'WHATIF_STUDENT',
      'select_level_1_degrees' => array('MATH' => 'MATH'),
      'L3__sel__concentration__for__MATH__xx' => 'MATH|_DATA',
      'L3__ops__concentration__for__MATH__xx' => '1~1',
    ));

    advise_what_if_selection_form_validate(array(), $formState);

    $this->assertFalse(form_has_errors());
  }

  /**
   * Ensures a zero track maximum means unlimited tracks rather than zero
   * permitted tracks, provided the overall What If selection cap is respected.
   */
  public function testWhatIfValidationAllowsUnlimitedTracksWhenMaximumIsZero(): void {
    variable_set('max_allowed_selections_in_what_if', 5);
    $formState = array('values' => array(
      'catalog_year' => 2024,
      'current_student_id' => 'WHATIF_STUDENT',
      'select_level_1_degrees' => array('MATH' => 'MATH'),
      'L3__sel__concentration__for__MATH__xx' => array(
        'MATH|_DATA' => 'MATH|_DATA',
        'MATH|_STAT' => 'MATH|_STAT',
        'MATH|_APPLIED' => 'MATH|_APPLIED',
        'MATH|_PURE' => 'MATH|_PURE',
      ),
      'L3__ops__concentration__for__MATH__xx' => '1~0',
    ));

    advise_what_if_selection_form_validate(array(), $formState);

    $this->assertFalse(form_has_errors());
  }

  /**
   * Confirms direct lookup of an unknown advising-session ID returns false,
   * allowing callers to distinguish a missing record from an empty session.
   */
  public function testSessionLookupReturnsFalseForUnknownId(): void {
    $this->assertFalse(
      advise_get_advising_session_from_advising_session_id(2147483647)
    );
  }

  /**
   * Ensures the legacy advising_student_id request can establish student context
   * when current_student_id is absent, preserving older advising links.
   */
  public function testAdvisingVariablesFallBackToRequestedAdvisingStudentId(): void {
    $studentId = 'VARS_LEGACY_STUDENT';
    $_REQUEST = array(
      'advising_student_id' => $studentId,
      'advising_major_code' => 'HIST',
      'advising_track_degree_ids' => '501',
      'advising_term_id' => '202630',
      'advising_what_if' => 'no',
      'what_if_major_code' => 'none',
      'what_if_catalog_year' => '2025',
      'what_if_track_degree_ids' => 'none',
    );

    advise_init_advising_variables();

    $this->assertSame($studentId, $GLOBALS['fp_advising']['current_student_id']);
    $this->assertSame($studentId, $GLOBALS['fp_advising']['advising_student_id']);
    $this->assertSame('HIST', $_SESSION['advising_major_code' . $studentId]);
    $this->assertSame('202630', $_SESSION['advising_term_id' . $studentId]);
  }

  /**
   * Verifies the legacy sentinel value "none" is normalized to empty selections
   * before advising state is consumed by degree-plan loaders.
   */
  public function testAdvisingVariablesNormalizeNoneSelections(): void {
    $studentId = 'VARS_NONE_STUDENT';
    $_REQUEST = array(
      'current_student_id' => $studentId,
      'advising_student_id' => $studentId,
      'advising_major_code' => 'HIST',
      'advising_track_degree_ids' => 'none',
      'advising_term_id' => '202630',
      'advising_what_if' => 'yes',
      'what_if_major_code' => 'none',
      'what_if_catalog_year' => '2025',
      'what_if_track_degree_ids' => 'none',
    );

    advise_init_advising_variables();

    $this->assertSame('', $GLOBALS['fp_advising']['advising_track_degree_ids']);
    $this->assertSame('', $GLOBALS['fp_advising']['what_if_major_code']);
    $this->assertSame('', $GLOBALS['fp_advising']['what_if_track_degree_ids']);
    $this->assertSame('', $_SESSION['advising_track_degree_ids' . $studentId]);
    $this->assertSame('', $_SESSION['what_if_major_code' . $studentId]);
    $this->assertSame('', $_SESSION['what_if_track_degree_ids' . $studentId]);
  }

  /**
   * Confirms What If controls are omitted from print screens, where interactive
   * degree-selection fields would be unusable and misleading.
   */
  public function testWhatIfSelectionFormIsEmptyInPrintMode(): void {
    global $screen;
    $screen = new stdClass();
    $screen->bool_print = TRUE;

    $this->assertSame(array(), advise_what_if_selection_form());
  }

  /**
   * Verifies track validation rejects selections above a degree's configured
   * maximum as well as below the minimum covered by the earlier test.
   */
  public function testWhatIfValidationEnforcesTrackMaximum(): void {
    $fieldName = 'L3__sel__concentration__for__MATH__xx';
    $formState = array('values' => array(
      'catalog_year' => 2024,
      'current_student_id' => 'WHATIF_STUDENT',
      'select_level_1_degrees' => array('MATH' => 'MATH'),
      $fieldName => array(
        'MATH|_ONE' => 'MATH|_ONE',
        'MATH|_TWO' => 'MATH|_TWO',
        'MATH|_THREE' => 'MATH|_THREE',
      ),
      'L3__ops__concentration__for__MATH__xx' => '1~2',
    ));

    advise_what_if_selection_form_validate(array(), $formState);

    $this->assertTrue(form_has_errors());
    $this->assertSame($fieldName, $_SESSION['fp_form_errors'][0]['name']);
    $this->assertStringContainsString(
      'did not select the correct number of options',
      strip_tags($_SESSION['fp_form_errors'][0]['msg'])
    );
  }

  /**
   * Ensures required tracks belonging to an unselected degree do not block a
   * valid top-level selection for a different degree.
   */
  public function testWhatIfValidationIgnoresTracksForUnselectedDegree(): void {
    variable_set('max_allowed_selections_in_what_if', 5);
    $formState = array('values' => array(
      'catalog_year' => 2024,
      'current_student_id' => 'WHATIF_STUDENT',
      'select_level_1_degrees' => array('MATH' => 'MATH'),
      'L3__sel__concentration__for__BIOL__xx' => array(),
      'L3__ops__concentration__for__BIOL__xx' => '2~2',
    ));

    advise_what_if_selection_form_validate(array(), $formState);

    $this->assertFalse(form_has_errors());
  }

  /**
   * Confirms a top-level degree with an allowed number of tracks passes What If
   * validation when it remains within the overall selection limit.
   */
  public function testWhatIfValidationAcceptsValidDegreeAndTracks(): void {
    variable_set('max_allowed_selections_in_what_if', 5);
    $formState = array('values' => array(
      'catalog_year' => 2024,
      'current_student_id' => 'WHATIF_STUDENT',
      'select_level_1_degrees' => array('MATH' => 'MATH'),
      'L3__sel__concentration__for__MATH__xx' => array(
        'MATH|_DATA' => 'MATH|_DATA',
        'MATH|_STAT' => 'MATH|_STAT',
      ),
      'L3__ops__concentration__for__MATH__xx' => '1~2',
    ));

    advise_what_if_selection_form_validate(array(), $formState);

    $this->assertFalse(form_has_errors());
  }

  /**
   * Confirms What If validation rejects combining any degree whose definition
   * disallows dynamic combinations with another selected degree.
   */
  public function testWhatIfValidationRejectsNonDynamicDegreeCombination(): void {
    $dynamicCode = 'WDI_' . uniqid();
    $staticCode = 'WST_' . uniqid();
    $this->insertDegreeRow($dynamicCode, TRUE);
    $this->insertDegreeRow($staticCode, FALSE);
    variable_set('earliest_catalog_year', 2000);
    $GLOBALS['fp_advising']['bool_use_draft'] = FALSE;
    $formState = array('values' => array(
      'catalog_year' => 2024,
      'current_student_id' => 'WHATIF_STUDENT',
      'select_level_1_degrees' => array(
        $dynamicCode => $dynamicCode,
        $staticCode => $staticCode,
      ),
    ));

    advise_what_if_selection_form_validate(array(), $formState);

    $this->assertTrue(form_has_errors());
    $this->assertSame('select_level_1_degrees', $_SESSION['fp_form_errors'][0]['name']);
    $this->assertStringContainsString(
      'does not allow you to combine it with any other degree',
      strip_tags($_SESSION['fp_form_errors'][0]['msg'])
    );
    $this->assertStringContainsString(
      'Static Test Degree',
      strip_tags($_SESSION['fp_form_errors'][0]['msg'])
    );
  }

  /**
   * Ensures history presents clear empty states when a student has neither
   * saved advising sessions nor visible advising comments.
   */
  public function testHistoryDisplaysEmptyStatesForStudentWithoutHistory(): void {
    global $current_student_id;
    if (!function_exists('comments_get_comments')) {
      require_once __DIR__ . '/../../modules/comments/comments.module';
    }
    if (!function_exists('advise_display_history')) {
      require_once __DIR__ . '/../../modules/advise/advise.history.inc';
    }
    $current_student_id = 'HISTORY_EMPTY_' . substr(sha1(uniqid('', TRUE)), 0, 12);

    $history = advise_display_history();

    $visibleText = strip_tags($history);
    $this->assertStringContainsString('No advising history available.', $visibleText);
    $this->assertStringContainsString('No comment history available.', $visibleText);
    $this->assertStringContainsString("class='no-comment-history-msg'", $history);
  }

  /**
   * Ensures history hides draft, empty, and soft-deleted sessions so students
   * see only completed advising records that remain part of their history.
   */
  public function testHistoryExcludesDraftEmptyAndDeletedSessions(): void {
    global $current_student_id;
    if (!function_exists('comments_get_comments')) {
      require_once __DIR__ . '/../../modules/comments/comments.module';
    }
    if (!function_exists('advise_display_history')) {
      require_once __DIR__ . '/../../modules/advise/advise.history.inc';
    }
    $current_student_id = 'HISTORY_FILTER_' . substr(sha1(uniqid('', TRUE)), 0, 12);
    $activeTerm = '990101';
    $draftTerm = '990102';
    $emptyTerm = '990103';
    $deletedTerm = '990104';
    $this->insertAdvisingSession(array(
      'student_id' => $current_student_id,
      'term_id' => $activeTerm,
      'advising_session_token' => 'history-active-' . uniqid(),
    ));
    $this->insertAdvisingSession(array(
      'student_id' => $current_student_id,
      'term_id' => $draftTerm,
      'is_draft' => 1,
      'advising_session_token' => 'history-draft-' . uniqid(),
    ));
    $this->insertAdvisingSession(array(
      'student_id' => $current_student_id,
      'term_id' => $emptyTerm,
      'is_empty' => 1,
      'advising_session_token' => 'history-empty-' . uniqid(),
    ));
    $this->insertAdvisingSession(array(
      'student_id' => $current_student_id,
      'term_id' => $deletedTerm,
      'delete_flag' => 1,
      'advising_session_token' => 'history-deleted-' . uniqid(),
    ));

    $history = advise_display_history();
    $visibleText = strip_tags($history);

    $this->assertStringContainsString($activeTerm, $visibleText);
    $this->assertStringNotContainsString($draftTerm, $visibleText);
    $this->assertStringNotContainsString($emptyTerm, $visibleText);
    $this->assertStringNotContainsString($deletedTerm, $visibleText);
  }

  /**
   * Verifies multi-term advising actions are grouped in history and expose a
   * combined-summary link while still listing each individual advised term.
   */
  public function testHistoryGroupsMultiTermAdvisingSessions(): void {
    global $current_student_id;
    if (!function_exists('comments_get_comments')) {
      require_once __DIR__ . '/../../modules/comments/comments.module';
    }
    if (!function_exists('advise_display_history')) {
      require_once __DIR__ . '/../../modules/advise/advise.history.inc';
    }
    $current_student_id = 'HISTORY_GROUP_' . substr(sha1(uniqid('', TRUE)), 0, 12);
    $token = 'history-group-' . uniqid();
    $earlierTerm = '990201';
    $laterTerm = '990202';
    $this->insertAdvisingSession(array(
      'student_id' => $current_student_id,
      'term_id' => $earlierTerm,
      'advising_session_token' => $token,
    ));
    $this->insertAdvisingSession(array(
      'student_id' => $current_student_id,
      'term_id' => $laterTerm,
      'advising_session_token' => $token,
    ));

    $history = advise_display_history();
    $visibleText = strip_tags($history);

    $this->assertStringContainsString($earlierTerm, $visibleText);
    $this->assertStringContainsString($laterTerm, $visibleText);
    $this->assertStringContainsString('View Combined', $visibleText);
    $this->assertStringContainsString("class='advising-summary-group-link'", $history);
  }

  /**
   * Confirms printable advising summaries include transaction details in both
   * HTML and plain-text formats, even when no courses were selected.
   */
  public function testPrintableSummarySupportsHtmlAndPlainText(): void {
    if (!function_exists('advise_popup_display_summary')) {
      require_once __DIR__ . '/../../modules/advise/advise.history.inc';
    }
    $studentId = 'SUMMARY_STUDENT_' . substr(sha1(uniqid('', TRUE)), 0, 10);
    $majorCode = 'SUMMARY' . substr(sha1(uniqid('', TRUE)), 0, 10);
    $degreeId = $this->insertDegreeRow($majorCode, TRUE);
    $this->insertStudentAccount($studentId);
    $sessionId = $this->insertAdvisingSession(array(
      'student_id' => $studentId,
      'degree_id' => $degreeId,
      'major_code_csv' => $majorCode,
      'term_id' => '990301',
      'advising_session_token' => 'summary-' . uniqid(),
    ));

    $html = advise_popup_display_summary($sessionId, TRUE, FALSE, FALSE);
    $plainText = advise_popup_display_summary($sessionId, FALSE, FALSE, FALSE);

    $this->assertStringContainsString('Alternate Courses', strip_tags($html));
    $this->assertStringContainsString("class='summary-advised-courses'", $html);
    $this->assertStringContainsString('Advising Summary for', $plainText);
    $this->assertStringContainsString($studentId, $plainText);
    $this->assertStringContainsString('Total advised hours: 0', $plainText);
  }

  /**
   * Confirms grouped summaries combine multiple advised terms under one set of
   * transaction details while retaining each term in the printable output.
   */
  public function testPrintableSummaryGroupsMultipleTerms(): void {
    if (!function_exists('advise_popup_display_summary')) {
      require_once __DIR__ . '/../../modules/advise/advise.history.inc';
    }
    $studentId = 'SUMMARY_GROUP_' . substr(sha1(uniqid('', TRUE)), 0, 10);
    $majorCode = 'SUMMARYGROUP' . substr(sha1(uniqid('', TRUE)), 0, 10);
    $degreeId = $this->insertDegreeRow($majorCode, TRUE);
    $this->insertStudentAccount($studentId);
    $firstTerm = '990302';
    $secondTerm = '990303';
    $firstSessionId = $this->insertAdvisingSession(array(
      'student_id' => $studentId,
      'degree_id' => $degreeId,
      'major_code_csv' => $majorCode,
      'term_id' => $firstTerm,
      'advising_session_token' => 'summary-group-' . uniqid(),
    ));
    $secondSessionId = $this->insertAdvisingSession(array(
      'student_id' => $studentId,
      'degree_id' => $degreeId,
      'major_code_csv' => $majorCode,
      'term_id' => $secondTerm,
      'advising_session_token' => 'summary-group-' . uniqid(),
    ));

    $summary = advise_popup_display_summary(
      $firstSessionId . ',' . $secondSessionId,
      TRUE,
      TRUE,
      FALSE
    );
    $visibleText = strip_tags($summary);

    $this->assertStringContainsString($firstTerm, $visibleText);
    $this->assertStringContainsString($secondTerm, $visibleText);
    $this->assertSame(1, substr_count($summary, 'summary-transaction-details'));
    $this->assertSame(2, substr_count($summary, 'Advised Courses for'));
  }

  /**
   * Verifies the What If form excludes graduate degrees by default but includes
   * them when callers explicitly request a mixed undergraduate/graduate list.
   */
  public function testWhatIfSelectionFormCanIncludeGraduateDegrees(): void {
    global $current_student_id, $screen;
    $undergradCode = 'WIFUG' . substr(sha1(uniqid('', TRUE)), 0, 10);
    $graduateCode = 'WIFGR' . substr(sha1(uniqid('', TRUE)), 0, 10);
    $levelOneClasses = fp_get_degree_classifications()['levels'][1];
    $degreeClass = array_key_first($levelOneClasses);
    $this->insertDegreeRow($undergradCode, TRUE, $degreeClass);
    $this->insertDegreeRow($graduateCode, TRUE, $degreeClass, 'GR');
    $current_student_id = 'WIF_GRAD_STUDENT';
    $screen = NULL;
    variable_set('current_catalog_year', 2024);
    variable_set('earliest_catalog_year', 2020);
    variable_set('what_if_catalog_year_mode', 'current');

    $undergraduateForm = advise_what_if_selection_form(TRUE);
    $allLevelsForm = advise_what_if_selection_form(FALSE);
    $undergraduateOptions = $undergraduateForm['cfieldset_level_1']['elements'][0]['select_level_1_degrees']['options'];
    $allLevelOptions = $allLevelsForm['cfieldset_level_1']['elements'][0]['select_level_1_degrees']['options'];

    $this->assertArrayHasKey($undergradCode, $undergraduateOptions);
    $this->assertArrayNotHasKey($graduateCode, $undergraduateOptions);
    $this->assertArrayHasKey($undergradCode, $allLevelOptions);
    $this->assertArrayHasKey($graduateCode, $allLevelOptions);
  }

  /**
   * Ensures the level-three selection setting controls whether available
   * tracks appear in What If forms without removing the parent degree choice.
   */
  public function testWhatIfSelectionFormHonorsLevelThreeVisibilitySetting(): void {
    global $current_student_id, $screen;
    $majorCode = 'WIFTRACK' . substr(sha1(uniqid('', TRUE)), 0, 10);
    $trackCode = 'DATA';
    $trackMajorCode = $majorCode . '|_' . $trackCode;
    $classifications = fp_get_degree_classifications();
    $levelOneClass = array_key_first($classifications['levels'][1]);
    $levelThreeClass = array_key_first($classifications['levels'][3]);
    $this->insertDegreeRow($majorCode, TRUE, $levelOneClass);
    $this->insertDegreeRow($trackMajorCode, TRUE, $levelThreeClass);
    $this->insertDegreeTrack($majorCode, $trackCode);
    $current_student_id = 'WIF_TRACK_STUDENT';
    $screen = NULL;
    variable_set('current_catalog_year', 2024);
    variable_set('earliest_catalog_year', 2020);
    variable_set('what_if_catalog_year_mode', 'current');
    variable_set('show_level_3_on_what_if_selection', 'no');

    $withoutTracks = advise_what_if_selection_form();

    variable_set('show_level_3_on_what_if_selection', 'yes');
    $withTracks = advise_what_if_selection_form();
    $trackField = 'L3__sel__' . $levelThreeClass . '__for__' . $majorCode . '__xx';

    $this->assertArrayNotHasKey($trackField, $withoutTracks);
    $this->assertArrayHasKey($trackField, $withTracks);
    $this->assertArrayHasKey($trackMajorCode, $withTracks[$trackField]['options']);
  }

  /**
   * Ensures initialization establishes a disabled draft-mode value when an
   * older or newly created session has not set one yet.
   */
  public function testInitDefaultsMissingDraftModeToDisabled(): void {
    global $user;
    unset($_SESSION['fp_draft_mode']);
    $user->id = 2;
    $user->cwid = 'INIT_DEFAULT_DRAFT';
    $user->is_student = 0;
    $user->permissions = array();
    $_REQUEST['current_student_id'] = 'INIT_DEFAULT_TARGET';

    advise_init();

    $this->assertSame('', $_SESSION['fp_draft_mode']);
  }

  /**
   * Verifies the What If form exposes active level-one degrees from the
   * selected catalog year, giving students a usable starting selection.
   */
  public function testWhatIfSelectionFormListsCatalogYearMajorOptions(): void {
    global $current_student_id, $screen;
    $majorCode = 'WIFFORM' . substr(sha1(uniqid('', TRUE)), 0, 10);
    $levelOneClasses = fp_get_degree_classifications()['levels'][1];
    $this->insertDegreeRow($majorCode, TRUE, array_key_first($levelOneClasses));
    $current_student_id = 'WIF_FORM_STUDENT';
    $screen = NULL;
    variable_set('current_catalog_year', 2024);
    variable_set('earliest_catalog_year', 2020);
    variable_set('what_if_catalog_year_mode', 'current');

    $form = advise_what_if_selection_form();
    $majorOptions = $form['cfieldset_level_1']['elements'][0]['select_level_1_degrees']['options'];

    $this->assertSame(2024, $form['catalog_year']['value']);
    $this->assertArrayHasKey($majorCode, $majorOptions);
    $this->assertSame('Dynamic Test Degree', $majorOptions[$majorCode]);
    $this->assertArrayHasKey('submit_btn', $form);
  }

  /**
   * Ensures the What If form stops before degree selection when a student's
   * catalog year is newer than the configured current catalog.
   */
  public function testWhatIfSelectionFormRejectsUnavailableStudentCatalogYear(): void {
    global $current_student_id, $screen;
    $current_student_id = 'WIF_YEAR_' . uniqid();
    $screen = NULL;
    $this->insertStudentAccount($current_student_id, TRUE, 2025);
    variable_set('current_catalog_year', 2024);
    variable_set('earliest_catalog_year', 2020);
    variable_set('what_if_catalog_year_mode', 'student');

    $form = advise_what_if_selection_form();
    $message = $form['mark_cat_year_past_current']['value'];

    $this->assertStringContainsString('catalog year 2025-2026 is not available', strip_tags($message));
    $this->assertStringContainsString("class='cat-year-past-current'", $message);
    $this->assertArrayNotHasKey('catalog_year', $form);
    $this->assertArrayNotHasKey('submit_btn', $form);
  }
}
