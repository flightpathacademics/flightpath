<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for Blank Degrees access, menu state, and prerequisite behavior.
 */
class BlankDegreesTest extends FlightPathTestCase {

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('blank_degrees_menu')) {
      require_once __DIR__ . '/../../modules/blank_degrees/blank_degrees.module';
    }
  }

  /**
   * Confirms the selection and display routes retain their access requirement
   * and rendering callbacks, which prevents public links from bypassing policy.
   */
  public function testMenuDefinesProtectedSelectionAndDisplayRoutes(): void {
    $items = blank_degrees_menu();

    $this->assertSame('Blank Degree Search', $items['tools/blank-degrees']['title']);
    $this->assertSame('fp_render_form', $items['tools/blank-degrees']['page_callback']);
    $this->assertSame(array('blank_degrees_select_degree_form'), $items['tools/blank-degrees']['page_arguments']);
    $this->assertSame(array('access_blank_degrees'), $items['tools/blank-degrees']['access_arguments']);
    $this->assertSame(MENU_TYPE_NORMAL_ITEM, $items['tools/blank-degrees']['type']);
    $this->assertSame('blank_degrees_display_blank_degree', $items['blank-degrees/display']['page_callback']);
    $this->assertSame(MENU_TYPE_TAB, $items['blank-degrees/display']['type']);
  }

  /**
   * Ensures degree-menu URL tokens use the request's catalog year and school,
   * preserving the context when users navigate between blank-degree screens.
   */
  public function testMenuReplacementUsesRequestedCatalogYearAndSchool(): void {
    $_REQUEST = array(
      'blank_catalog_year' => '2024',
      'school_id' => '12',
    );

    $result = blank_degrees_menu_handle_replacement_pattern(
      'tools/blank-degrees?year=%BLANK_CATALOG_YEAR%&school=%SCHOOL_ID%'
    );

    $this->assertSame('tools/blank-degrees?year=2024&school=12', $result);
  }

  /**
   * Verifies prerequisite warnings are suppressed only while rendering a blank
   * degree, where no real student schedule is being evaluated.
   */
  public function testPrerequisiteWarningsAreClearedOnlyForBlankDegree(): void {
    $_REQUEST = array('blank_degree_id' => '101');
    $warnings = array('Missing prerequisite');

    blank_degrees_prereqs_get_prereq_warnings_for_course($warnings, NULL, NULL);

    $this->assertSame(array(), $warnings);

    $_REQUEST = array();
    $warnings = array('Missing prerequisite');
    blank_degrees_prereqs_get_prereq_warnings_for_course($warnings, NULL, NULL);

    $this->assertSame(array('Missing prerequisite'), $warnings);
  }

  /**
   * Ensures the module advertises separate permissions for viewing a blank
   * degree and exposing the direct-link options beneath its display.
   */
  public function testPermissionDefinesViewingAndUrlOptionsAccess(): void {
    $permissions = blank_degrees_perm();

    $this->assertSame(
      array('access_blank_degrees', 'blank_degrees_view_url_options'),
      array_keys($permissions)
    );
    $this->assertSame('Access blank degrees', $permissions['access_blank_degrees']['title']);
    $this->assertSame('View URL options', $permissions['blank_degrees_view_url_options']['title']);
  }
}
