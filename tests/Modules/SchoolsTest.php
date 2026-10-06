<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for the schools module's "Override Default school value?" save handler.
 */
class SchoolsTest extends FlightPathTestCase {

  protected function setUp(): void {
    parent::setUp();
    if (!function_exists('schools_override_elements_form_submit')) {
      require_once __DIR__ . '/../../modules/schools/schools.module';
    }
  }

  protected function tearDown(): void {
    variable_set('school_override__rhh_text~~school_2', '');
    variable_set('school_override__rhh_boxes~~school_2', '');
    parent::tearDown();
  }

  public function testOverrideSavesForTextfieldAndCheckboxesFields(): void {
    $form_state = array(
      'values' => array('school_id' => 2),
      'POST' => array(
        'fields_to_check_override' => 'rhh_text~~school_2,rhh_boxes~~school_2,',
        'school_override__rhh_text~~school_2' => 'yes',
        // Checkboxes fields post their override box under the inner-wrapper- name.
        'school_override__inner-wrapper-rhh_boxes~~school_2' => 'yes',
      ),
    );
    schools_override_elements_form_submit(array(), $form_state);

    $this->assertSame('yes', variable_get('school_override__rhh_text~~school_2'));
    $this->assertSame('yes', variable_get('school_override__rhh_boxes~~school_2'));
  }

  public function testUncheckedOverrideSavesNo(): void {
    $form_state = array(
      'values' => array('school_id' => 2),
      'POST' => array('fields_to_check_override' => 'rhh_boxes~~school_2,'),
    );
    schools_override_elements_form_submit(array(), $form_state);
    $this->assertSame('no', variable_get('school_override__rhh_boxes~~school_2'));
  }





  /**
   * Confirms the built-in default school is available consistently through
   * structured definitions, Form API options, and human-readable lookups.
   */
  public function testDefaultSchoolDefinitionsAndFapiOptionsAreAvailable(): void {
    $definitions = schools_get_school_definitions(TRUE);
    $options = schools_get_schools_for_fapi(TRUE, FALSE, 'degree', TRUE);

    $this->assertSame(0, intval($definitions[0]['school_id']));
    $this->assertSame('', $definitions[0]['school_code']);
    $this->assertSame('Default', $definitions[0]['name']);
    $this->assertStringContainsString('Default', $options[0]);
    $this->assertSame('Default', schools_get_school_name_for_id(0));
    $this->assertSame('-', schools_get_school_code_for_id(0));
  }

  /**
   * Confirms Schools converts default configuration routes into tab-family
   * defaults and adds per-entity access callbacks to editable data routes.
   */
  public function testMenuAlterAssignsSchoolTabsAndEntityAccessChecks(): void {
    $items = array(
      'admin/config/school-data' => array('type' => MENU_TYPE_NORMAL_ITEM, 'tab_parent' => 'admin-tools/admin', 'page_settings' => array()),
      'admin/config/course-search' => array('type' => MENU_TYPE_NORMAL_ITEM, 'tab_parent' => 'admin-tools/admin', 'page_settings' => array()),
      'admin/degrees/edit-degree/%/%' => array(),
      'admin/groups/edit-group' => array(),
      'admin/courses/edit-course' => array(),
    );

    schools_menu_alter($items);

    $this->assertSame(MENU_TYPE_DEFAULT_TAB, $items['admin/config/school-data']['type']);
    $this->assertSame('config_school_settings', $items['admin/config/school-data']['tab_family']);
    $this->assertSame('Default', $items['admin/config/school-data']['page_settings']['tab_title']);
    $this->assertArrayNotHasKey('tab_parent', $items['admin/config/school-data']);
    $this->assertSame('course_search_settings', $items['admin/config/course-search']['tab_family']);
    $this->assertSame('schools_check_access', $items['admin/degrees/edit-degree/%/%']['access_callback']);
    $this->assertSame(array(2, 3, 4), $items['admin/degrees/edit-degree/%/%']['access_arguments']);
    $this->assertSame(array(2, 'request_group_id', 'group_catalog_year'), $items['admin/groups/edit-group']['access_arguments']);
    $this->assertSame(array(2, 'request_course_id', 'course_catalog_year'), $items['admin/courses/edit-course']['access_arguments']);
  }

  /**
   * Ensures school-specific degree, group, course, user-management, and search
   * permissions are generated for the built-in default school.
   */
  public function testPermissionsIncludeDefaultSchoolCapabilities(): void {
    $permissions = schools_perm();

    $this->assertArrayHasKey('administer_schools', $permissions);
    $this->assertArrayHasKey('administer_0_degree_data', $permissions);
    $this->assertArrayHasKey('administer_0_group_data', $permissions);
    $this->assertArrayHasKey('administer_0_course_data', $permissions);
    $this->assertArrayHasKey('manage_0_user_data', $permissions);
    $this->assertArrayHasKey('search_students_0', $permissions);
  }

}