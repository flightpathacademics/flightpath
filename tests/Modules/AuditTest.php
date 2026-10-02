<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for the audit module's per-school settings lookups.
 */
class AuditTest extends FlightPathTestCase {

  const SCHOOL_ID = 987;

  protected function tearDown(): void {
    variable_set("audit_approval_types~~school_" . self::SCHOOL_ID, "");
    variable_set("school_override__audit_approval_types~~school_" . self::SCHOOL_ID, "");
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
}