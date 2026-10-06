<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for the audit module's per-school settings lookups.
 */
class AuditTest extends FlightPathTestCase {

  const SCHOOL_ID = 987;
  const TEST_STUDENT_ID = 'TEST-AUDIT-918273';

  protected function tearDown(): void {
    variable_set("audit_approval_types~~school_" . self::SCHOOL_ID, "");
    variable_set("school_override__audit_approval_types~~school_" . self::SCHOOL_ID, "");
    db_query('DELETE FROM audit_approvals WHERE student_id = ?', array(self::TEST_STUDENT_ID));
    parent::tearDown();
  }

  public function testApprovalTypesUseOverriddenSchoolValue(): void {
    variable_set("audit_approval_types~~school_" . self::SCHOOL_ID, "custom_x ~ Custom School Approval");
    variable_set("school_override__audit_approval_types~~school_" . self::SCHOOL_ID, "yes");

    $types = audit_get_approval_types(self::SCHOOL_ID);
    $this->assertSame(array('custom_x'), array_keys($types));
    $this->assertSame('Custom School Approval', $types['custom_x']['title']);
  }

  public function testApprovalTypesFallBackToDefaultWithoutOverride(): void {
    variable_set("audit_approval_types~~school_" . self::SCHOOL_ID, "custom_x ~ Custom School Approval");
    variable_set("school_override__audit_approval_types~~school_" . self::SCHOOL_ID, "no");

    $types = audit_get_approval_types(self::SCHOOL_ID);
    $this->assertArrayNotHasKey('custom_x', $types);
  }

  /**
   * Ensures a school-specific approval definition retains its optional
   * explanation, which is shown alongside the approval title in the workflow.
   */
  public function testApprovalTypesRetainOptionalDescriptions(): void {
    variable_set("audit_approval_types~~school_" . self::SCHOOL_ID, "custom_x ~ Custom School Approval ~ Complete the local review\nblank_row ~ BLANK");
    variable_set("school_override__audit_approval_types~~school_" . self::SCHOOL_ID, "yes");

    $types = audit_get_approval_types(self::SCHOOL_ID);

    $this->assertSame('Custom School Approval', $types['custom_x']['title']);
    $this->assertSame('Complete the local review', $types['custom_x']['description']);
    $this->assertSame('BLANK', $types['blank_row']['title']);
  }

  /**
   * Verifies approval lookup isolates each student's record and approval type,
   * preventing an unrelated completion status from appearing in the popup.
   */
  public function testApprovalRecordReturnsOnlyMatchingStudentAndType(): void {
    db_query(
      'INSERT INTO audit_approvals (student_id, uid, faculty_id, approval_type, approval_value, posted) VALUES (?, ?, ?, ?, ?, ?)',
      array(self::TEST_STUDENT_ID, 1, 'FAC-1', 'coursework', 'complete', time())
    );
    db_query(
      'INSERT INTO audit_approvals (student_id, uid, faculty_id, approval_type, approval_value, posted) VALUES (?, ?, ?, ?, ?, ?)',
      array(self::TEST_STUDENT_ID, 1, 'FAC-1', 'major_gpa', 'in_progress', time())
    );

    $record = audit_get_approval_record(self::TEST_STUDENT_ID, 'coursework');

    $this->assertSame(self::TEST_STUDENT_ID, $record['student_id']);
    $this->assertSame('coursework', $record['approval_type']);
    $this->assertSame('complete', $record['approval_value']);
    $this->assertFalse(audit_get_approval_record(self::TEST_STUDENT_ID, 'graduation_gpa'));
  }

  /**
   * Confirms Audit navigation and the approval popup retain dedicated callbacks
   * and permissions for viewing, configuring, and editing approval records.
   */
  public function testMenuAndPermissionsDefineAuditCapabilities(): void {
    $items = audit_menu();
    $permissions = audit_perm();

    $this->assertSame('audit_display_audit', $items['audit']['page_callback']);
    $this->assertSame('audit_can_access_audit', $items['audit']['access_callback']);
    $this->assertSame(MENU_TYPE_TAB, $items['audit']['type']);
    $this->assertSame(array('administer_audit'), $items['admin/config/audit-settings']['access_arguments']);
    $this->assertSame(array('audit_popup_edit_approval_form', 'normal', 2, 3), $items['audit/popup-edit-approval/%/%']['page_arguments']);
    $this->assertTrue($items['audit/popup-edit-approval/%/%']['page_settings']['page_is_popup']);
    $this->assertArrayHasKey('view_student_audits', $permissions);
    $this->assertArrayHasKey('edit_audit_approvals', $permissions);
  }

  /**
   * Ensures the fixed approval-state list retains each workflow option so an
   * approval can be marked complete, in progress, inapplicable, or unfinished.
   */
  public function testApprovalOptionsDefineCompleteWorkflowStates(): void {
    $this->assertSame(
      array(
        'not_complete' => 'Not Complete',
        'in_progress' => 'In Progress',
        'complete' => 'Complete',
        'not_applicable' => 'Not Applicable',
      ),
      audit_get_approval_options()
    );
  }

  /**
   * Verifies approval-type validation records an error for non-machine-readable
   * keys, preventing malformed settings from becoming persistent identifiers.
   */
  public function testSettingsValidationRejectsInvalidApprovalTypeKey(): void {
    $errorsExisted = array_key_exists('fp_form_errors', $_SESSION);
    $originalErrors = $_SESSION['fp_form_errors'] ?? NULL;
    $messagesExisted = array_key_exists('fp_messages', $_SESSION);
    $originalMessages = $_SESSION['fp_messages'] ?? NULL;
    try {
      $_SESSION['fp_form_errors'] = array();
      $_SESSION['fp_messages'] = array();
      audit_settings_form_validate(array(), array('values' => array(
        'audit_approval_types' => 'bad key ~ Bad Key',
      )));

      $this->assertTrue(form_has_errors());
      $this->assertSame('audit_approval_types', $_SESSION['fp_form_errors'][0]['name']);
      $this->assertStringContainsString('letters, numbers, and underscores', $_SESSION['fp_form_errors'][0]['msg']);
    }
    finally {
      if ($errorsExisted) $_SESSION['fp_form_errors'] = $originalErrors; else unset($_SESSION['fp_form_errors']);
      if ($messagesExisted) $_SESSION['fp_messages'] = $originalMessages; else unset($_SESSION['fp_messages']);
    }
  }

  /**
   * Confirms valid approval definitions do not create form errors, allowing
   * optional descriptions and blank separator lines in the configuration.
   */
  public function testSettingsValidationAcceptsValidApprovalTypeDefinitions(): void {
    $errorsExisted = array_key_exists('fp_form_errors', $_SESSION);
    $originalErrors = $_SESSION['fp_form_errors'] ?? NULL;
    try {
      $_SESSION['fp_form_errors'] = array();
      audit_settings_form_validate(array(), array('values' => array(
        'audit_approval_types' => "coursework ~ Coursework ~ Complete the required courses\n\nmajor_gpa ~ Major GPA",
      )));

      $this->assertFalse(form_has_errors());
    }
    finally {
      if ($errorsExisted) $_SESSION['fp_form_errors'] = $originalErrors; else unset($_SESSION['fp_form_errors']);
    }
  }
}