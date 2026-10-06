<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for User module administrative navigation and permissions.
 */
class UserModuleTest extends FlightPathTestCase {

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('user_menu')) {
      require_once __DIR__ . '/../../modules/user/user.module';
    }
  }

  /**
   * Confirms user-management, role, permission, and personal-settings routes
   * use their intended callbacks and permissions.
   */
  public function testMenuDefinesUserAdministrationAndSettingsRoutes(): void {
    $items = user_menu();

    $this->assertSame('user_subtab_switchboard', $items['admin/config/users']['page_callback']);
    $this->assertSame(array('manage_users'), $items['admin/config/users']['access_arguments']);
    $this->assertSame(MENU_TYPE_NORMAL_ITEM, $items['admin/config/users']['type']);
    $this->assertSame('fp_render_form', $items['admin/config/user-roles']['page_callback']);
    $this->assertSame(array('user_user_roles_form'), $items['admin/config/user-roles']['page_arguments']);
    $this->assertSame(array('can_edit_user_roles'), $items['admin/config/user-roles']['access_arguments']);
    $this->assertSame(array('can_edit_permissions'), $items['admin/config/permissions']['access_arguments']);
    $this->assertSame(array('user_user_settings_form'), $items['user-settings']['page_arguments']);
    $this->assertSame(MENU_TYPE_TAB, $items['user-settings']['type']);
  }

  /**
   * Ensures faculty and student listing routes remain separate subtabs while
   * sharing the manage-users permission that guards both directories.
   */
  public function testMenuSeparatesFacultyAndStudentUserListings(): void {
    $items = user_menu();

    $this->assertSame('user_display_users', $items['admin/users/faculty']['page_callback']);
    $this->assertSame('user_display_student_users', $items['admin/users/students']['page_callback']);
    $this->assertSame('users', $items['admin/users/faculty']['tab_family']);
    $this->assertSame('users', $items['admin/users/students']['tab_family']);
    $this->assertSame(array('manage_users'), $items['admin/users/faculty']['access_arguments']);
    $this->assertSame(array('manage_users'), $items['admin/users/students']['access_arguments']);
  }

  /**
   * Confirms sensitive permission and user-deletion capabilities are marked as
   * administrator-restricted while ordinary user-management remains available.
   */
  public function testPermissionDefinesAdministrativeRestrictions(): void {
    $permissions = user_perm();

    $this->assertArrayHasKey('manage_users', $permissions);
    $this->assertArrayHasKey('can_edit_permissions', $permissions);
    $this->assertArrayHasKey('delete_users', $permissions);
    $this->assertSame('Manage users', $permissions['manage_users']['title']);
    $this->assertTrue($permissions['can_edit_permissions']['admin_restricted']);
    $this->assertTrue($permissions['delete_users']['admin_restricted']);
  }

  /**
   * Confirms user-scoped settings and attributes persist independently and
   * return their caller-supplied fallback when no value has been stored.
   */
  public function testSettingsAndAttributesRoundTripWithIndependentDefaults(): void {
    $userId = random_int(800000000, 899999999);
    try {
      $this->assertSame('fallback-setting', user_get_setting($userId, 'dashboard_layout', 'fallback-setting'));
      $this->assertSame('fallback-attribute', user_get_attribute($userId, 'mobile_phone', 'fallback-attribute'));

      user_set_setting($userId, 'dashboard_layout', 'compact');
      user_set_attribute($userId, 'mobile_phone', '5551234567');

      $this->assertSame('compact', user_get_setting($userId, 'dashboard_layout'));
      $this->assertSame('5551234567', user_get_attribute($userId, 'mobile_phone'));
      $this->assertSame('fallback-attribute', user_get_attribute($userId, 'timezone', 'fallback-attribute'));
    }
    finally {
      db_query('DELETE FROM user_settings WHERE user_id = ?', array($userId));
      db_query('DELETE FROM user_attributes WHERE user_id = ?', array($userId));
    }
  }

  /**
   * Verifies role-select options omit baseline anonymous/authenticated roles by
   * default but include them when the caller explicitly requests both entries.
   */
  public function testRolesForFapiHonorsBaselineRoleExclusions(): void {
    db_query('INSERT INTO roles (name) VALUES (?)', array('User Module PHPUnit Role'));
    $roleId = intval(db_insert_id());
    try {
      $standard = user_get_roles_for_fapi();
      $allRoles = user_get_roles_for_fapi(FALSE, FALSE);

      $this->assertSame('User Module PHPUnit Role', $standard[$roleId]);
      $this->assertArrayNotHasKey(1, $standard);
      $this->assertArrayNotHasKey(2, $standard);
      $this->assertSame('anonymous user', $allRoles[1]);
      $this->assertSame('authenticated user', $allRoles[2]);
    }
    finally {
      db_query('DELETE FROM roles WHERE rid = ?', array($roleId));
    }
  }
}
