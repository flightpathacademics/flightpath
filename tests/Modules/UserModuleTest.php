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
}
