<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for System module navigation, permissions, and block regions.
 */
class SystemModuleTest extends FlightPathTestCase {

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('system_menu')) {
      require_once __DIR__ . '/../../modules/system/system.module';
    }
  }

  /**
   * Confirms public entry pages and logged-in dashboard routes retain their
   * intended callbacks and access behavior.
   */
  public function testMenuDefinesDashboardAndAuthenticationRoutes(): void {
    $items = system_menu();

    $this->assertSame('system_display_dashboard_page', $items['main']['page_callback']);
    $this->assertTrue($items['main']['access_callback']);
    $this->assertSame(MENU_TYPE_NORMAL_ITEM, $items['main']['type']);
    $this->assertSame('system_display_login_page', $items['login']['page_callback']);
    $this->assertTrue($items['login']['access_callback']);
    $this->assertSame('fp_render_form', $items['mfa-login']['page_callback']);
    $this->assertSame(array('system_mfa_login_form'), $items['mfa-login']['page_arguments']);
    $this->assertSame(array('access_logged_in_content'), $items['render-advising-snapshot-for-iframe']['access_arguments']);
  }

  /**
   * Ensures cache, cron, module, status, and system-settings routes are each
   * guarded by their distinct administration permission.
   */
  public function testMenuDefinesProtectedSystemAdministrationRoutes(): void {
    $items = system_menu();

    $this->assertSame(array('clear_system_cache'), $items['admin-tools/clear-cache']['access_arguments']);
    $this->assertSame(array('administer_modules'), $items['admin/db-updates']['access_arguments']);
    $this->assertSame(array('run_cron'), $items['admin/config/run-cron']['access_arguments']);
    $this->assertSame(array('view_system_status'), $items['admin/config/status']['access_arguments']);
    $this->assertSame(array('de_can_administer_system_settings'), $items['admin/config/system-settings']['access_arguments']);
    $this->assertSame(array('de_can_administer_school_data'), $items['admin/config/school-data']['access_arguments']);
    $this->assertSame(array('administer_modules'), $items['admin/config/modules']['access_arguments']);
    $this->assertSame(array('execute_php'), $items['admin/config/execute-php']['access_arguments']);
  }

  /**
   * Confirms System registers the main and login block regions needed by themes
   * to place blocks consistently on authenticated and login pages.
   */
  public function testBlockRegionsDefineMainAndLoginLayouts(): void {
    $regions = system_block_regions();

    $this->assertSame('Main Tab', $regions['system_main']['title']);
    $this->assertSame(array('left_col', 'right_col'), array_keys($regions['system_main']['regions']));
    $this->assertSame('Login Page', $regions['system_login']['title']);
    $this->assertSame(array('top', 'left_col', 'right_col', 'bottom'), array_keys($regions['system_login']['regions']));
  }

  /**
   * Verifies sensitive module administration, PHP execution, and debug access
   * are administrator-restricted while normal signed-in access is separately defined.
   */
  public function testPermissionDefinesSystemAdministrationRestrictions(): void {
    $permissions = system_perm();

    $this->assertArrayHasKey('access_logged_in_content', $permissions);
    $this->assertArrayHasKey('administer_modules', $permissions);
    $this->assertArrayHasKey('execute_php', $permissions);
    $this->assertArrayHasKey('run_cron', $permissions);
    $this->assertTrue($permissions['administer_modules']['admin_restricted']);
    $this->assertTrue($permissions['execute_php']['admin_restricted']);
    $this->assertTrue($permissions['view_fpm_debug']['admin_restricted']);
  }
}
