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




}