<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for Prerequisites configuration and administration hooks.
 */
class PrereqsTest extends FlightPathTestCase {

  private array $originalVariables = array();

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('prereqs_menu')) {
      require_once __DIR__ . '/../../modules/prereqs/prereqs.module';
    }

    foreach (array(
      'prereqs_show_availability_in_popup',
      'prereqs_lock_course_advise_based_on_avail',
      'prereqs_use_term_desc_abbr_in_popup',
      'prereqs_not_anticipated_text',
      'prereqs_lock_msg_avail',
      'prereqs_show_courses_in_popup',
      'prereqs_lock_course_advise_based_on_courses',
      'prereqs_lock_msg_courses',
      'prereqs_confirm_msg',
    ) as $name) {
      $this->originalVariables[$name] = array(
        'exists' => variable_exists($name),
        'value' => variable_get($name, NULL),
      );
    }
  }

  protected function tearDown(): void {
    foreach ($this->originalVariables as $name => $original) {
      if ($original['exists']) {
        variable_set($name, $original['value']);
      }
      else {
        variable_delete($name);
      }
    }

    parent::tearDown();
  }

  /**
   * Confirms the Prerequisites settings route uses the system-settings form
   * and its dedicated administration permission.
   */
  public function testMenuDefinesPrerequisitesSettingsRoute(): void {
    $items = prereqs_menu();
    $item = $items['admin/config/prereqs'];

    $this->assertSame('Prereqs settings', $item['title']);
    $this->assertSame('fp_render_form', $item['page_callback']);
    $this->assertSame(array('prereqs_settings_form', 'system_settings'), $item['page_arguments']);
    $this->assertSame(array('administer_prereqs'), $item['access_arguments']);
    $this->assertSame(MENU_TYPE_NORMAL_ITEM, $item['type']);
  }

  /**
   * Ensures the settings form exposes the availability and prerequisite-course
   * controls that determine how course locks and explanatory messages behave.
   */
  public function testSettingsFormDefinesAvailabilityAndCourseControls(): void {
    $form = prereqs_settings_form();

    $this->assertSame('cfieldset', $form['avail']['type']);
    $this->assertSame('cfieldset', $form['courses']['type']);
    $this->assertSame('select', $form['avail']['elements'][0]['prereqs_show_availability_in_popup']['type']);
    $this->assertSame(array('no' => 'No', 'yes' => 'Yes'), $form['avail']['elements'][0]['prereqs_lock_course_advise_based_on_avail']['options']);
    $this->assertSame('select', $form['courses']['elements'][0]['prereqs_show_courses_in_popup']['type']);
    $this->assertSame('textfield', $form['prereqs_confirm_msg']['type']);
  }

  /**
   * Confirms saved availability and lock-message settings are returned by the
   * matching fields so configuration changes do not silently discard values.
   */
  public function testSettingsFormUsesStoredConfiguration(): void {
    variable_set('prereqs_show_availability_in_popup', 'yes');
    variable_set('prereqs_lock_msg_avail', 'Available in @term only.');
    variable_set('prereqs_lock_course_advise_based_on_courses', 'yes');
    variable_set('prereqs_confirm_msg', 'Proceed despite the prerequisite lock?');

    $form = prereqs_settings_form();

    $this->assertSame('yes', $form['avail']['elements'][0]['prereqs_show_availability_in_popup']['value']);
    $this->assertSame('Available in @term only.', $form['avail']['elements'][0]['prereqs_lock_msg_avail']['value']);
    $this->assertSame('yes', $form['courses']['elements'][0]['prereqs_lock_course_advise_based_on_courses']['value']);
    $this->assertSame('Proceed despite the prerequisite lock?', $form['prereqs_confirm_msg']['value']);
  }

  /**
   * Ensures administrators can configure prereqs while designated advisors can
   * separately override course locks when institutional policy allows it.
   */
  public function testPermissionDefinesAdministrationAndCourseLockOverride(): void {
    $permissions = prereqs_perm();

    $this->assertSame(array('administer_prereqs', 'override_course_locks'), array_keys($permissions));
    $this->assertSame('Administer prereq settings', $permissions['administer_prereqs']['title']);
    $this->assertSame('Override course locks', $permissions['override_course_locks']['title']);
  }
}
