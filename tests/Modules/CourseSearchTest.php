<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for the course_search syllabus upload filename check.
 */
class CourseSearchTest extends FlightPathTestCase {


  public function testDocumentSyllabusIsAllowed(): void {
    $this->assertTrue(course_search_is_allowed_syllabus_filename('ENGL 1001 Syllabus.pdf'));
    $this->assertTrue(course_search_is_allowed_syllabus_filename('notes.DOCX'));
  }

  public function testScriptOrMissingExtensionIsRejected(): void {
    $this->assertFalse(course_search_is_allowed_syllabus_filename('shell.php'));
    $this->assertFalse(course_search_is_allowed_syllabus_filename('shell.pdf.php'));
    $this->assertFalse(course_search_is_allowed_syllabus_filename('shell.phtml'));
    $this->assertFalse(course_search_is_allowed_syllabus_filename('.htaccess'));
    $this->assertFalse(course_search_is_allowed_syllabus_filename('noextension'));
    $this->assertFalse(course_search_is_allowed_syllabus_filename(''));
  }





  /**
   * Confirms course-search pages separate general search, administrative
   * settings, editing, and syllabus download access by their intended rights.
   */
  public function testMenuAndPermissionsDefineCourseSearchCapabilities(): void {
    $items = course_search_menu();
    $permissions = course_search_perm();

    $this->assertSame('course_search_display_search', $items['tools/course-search']['page_callback']);
    $this->assertSame(array('access_course_search'), $items['tools/course-search']['access_arguments']);
    $this->assertSame('course_search_download_syllabus', $items['course-search/get-syllabus']['page_callback']);
    $this->assertSame(array('administer_course_search'), $items['admin/config/course-search']['access_arguments']);
    $this->assertSame(array('can_update_course_info_details'), $items['tools/course-search/edit-list']['access_arguments']);
    $this->assertArrayHasKey('access_course_search', $permissions);
    $this->assertArrayHasKey('administer_course_search', $permissions);
    $this->assertArrayHasKey('can_update_course_info_details', $permissions);
  }

  /**
   * Ensures the supported syllabus format allowlist remains explicit and uses
   * a case-insensitive extension check without permitting executable files.
   */
  public function testAllowedSyllabusExtensionsIncludeOfficeAndArchiveFormats(): void {
    $this->assertSame(
      array('pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', '7z'),
      course_search_allowed_syllabus_extensions()
    );
    $this->assertTrue(course_search_is_allowed_syllabus_filename('orientation.PPTX'));
    $this->assertTrue(course_search_is_allowed_syllabus_filename('materials.7Z'));
    $this->assertFalse(course_search_is_allowed_syllabus_filename('installer.exe'));
  }

  /**
   * Verifies menu replacement tokens use saved school context and current
   * filter parameters, then consume the one-time saved school identifier.
   */
  public function testMenuReplacementPatternsUseRequestAndSessionContext(): void {
    $sessionExisted = array_key_exists('last_saved_school_id', $_SESSION);
    $originalSession = $_SESSION['last_saved_school_id'] ?? NULL;
    $getWasArray = is_array($_GET);
    $originalGet = $getWasArray ? $_GET : NULL;
    if (!$getWasArray) $_GET = array();
    try {
      $_SESSION['last_saved_school_id'] = 27;
      $_GET['sev_filter'] = 'warning';
      $_GET['type_filter'] = 'rotation';
      $_GET['page'] = '3';

      $result = course_search_menu_handle_replacement_pattern('context-%SCHOOL_ID%/%SEV_FILTER%/%TYPE_FILTER%/%PAGE%');

      $this->assertSame('context-27/1/1/1', $result);
      $this->assertArrayNotHasKey('last_saved_school_id', $_SESSION);
    }
    finally {
      if ($sessionExisted) $_SESSION['last_saved_school_id'] = $originalSession; else unset($_SESSION['last_saved_school_id']);
      if ($getWasArray) $_GET = $originalGet; else unset($_GET);
    }
  }

  /**
   * Confirms saved availability-column settings return to the corresponding
   * form inputs so administrators can safely review their display order.
   */
  public function testSettingsFormUsesStoredAvailabilityConfiguration(): void {
    $names = array(
      'course_search_avail_term_id_suffix_order',
      'course_search_avail_term_headers',
      'course_search_avail_term_mobile_headers',
    );
    $original = array();
    foreach ($names as $name) {
      $original[$name] = array('exists' => variable_exists($name), 'value' => variable_get($name, NULL));
    }
    try {
      variable_set('course_search_avail_term_id_suffix_order', '60, 80');
      variable_set('course_search_avail_term_headers', 'Spring, Fall');
      variable_set('course_search_avail_term_mobile_headers', 'Spr, Fall');
      $form = course_search_settings_form();

      $this->assertSame('60, 80', $form['course_search_avail_term_id_suffix_order']['value']);
      $this->assertSame('Spring, Fall', $form['course_search_avail_term_headers']['value']);
      $this->assertSame('Spr, Fall', $form['course_search_avail_term_mobile_headers']['value']);
    }
    finally {
      foreach ($original as $name => $value) {
        if ($value['exists']) variable_set($name, $value['value']); else variable_delete($name);
      }
    }
  }
}