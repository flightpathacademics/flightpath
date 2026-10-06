<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for update-status presentation and request construction.
 */
class UpdateStatusTest extends FlightPathTestCase {

  private array $originalVariables = array();
  private stdClass $originalUser;

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('update_status_menu')) {
      require_once __DIR__ . '/../../modules/update_status/update_status.module';
    }

    foreach (array(
      'update_status_need_updates_modules',
      'update_status_need_updates_release_types',
      'update_status_last_run',
    ) as $name) {
      $this->originalVariables[$name] = array(
        'exists' => variable_exists($name),
        'value' => variable_get($name, NULL),
      );
    }

    global $user;
    $this->originalUser = clone $user;
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

    global $user;
    $user = $this->originalUser;
    parent::tearDown();
  }

  /**
   * Confirms the manual-check route is a callback guarded by cron permission,
   * preventing unprivileged visitors from initiating update requests.
   */
  public function testMenuDefinesProtectedManualCheckRoute(): void {
    $items = update_status_menu();
    $item = $items['update-status-check-now'];

    $this->assertSame('update_status_check_now', $item['page_callback']);
    $this->assertSame(array('run_cron'), $item['access_arguments']);
    $this->assertSame(MENU_TYPE_CALLBACK, $item['type']);
  }

  /**
   * Ensures a never-checked, up-to-date installation communicates its state
   * without advertising the manual-check action to users lacking permission.
   */
  public function testStatusDisplaysNoUpdatesWithoutManualCheckLink(): void {
    global $user;
    $user->id = 2;
    $user->permissions = array();
    variable_set('update_status_need_updates_modules', array());
    variable_set('update_status_need_updates_release_types', array());
    variable_set('update_status_last_run', 0);

    $status = update_status_status()['status'];
    $visibleText = strip_tags($status);

    $this->assertStringContainsString('All modules are up to date.', $visibleText);
    $this->assertStringContainsString('Module status has never been checked.', $visibleText);
    $this->assertStringNotContainsString('Check now?', $visibleText);
  }

  /**
   * Verifies available releases render a module-machine-name fallback and security
   * row class, making urgent updates distinguishable in the administrator status screen.
   */
  public function testStatusDisplaysAvailableSecurityUpdate(): void {
    global $user;
    $user->id = 2;
    $user->permissions = array();
    variable_set('update_status_need_updates_modules', array('lassie' => '9.9.9'));
    variable_set('update_status_need_updates_release_types', array('lassie' => 'security'));
    variable_set('update_status_last_run', 1700000000);

    $status = update_status_status()['status'];
    $visibleText = strip_tags($status);

    $this->assertStringContainsString('lassie', $visibleText);
    $this->assertStringContainsString('9.9.9', $visibleText);
    $this->assertStringContainsString('Security - High Priority!', $visibleText);
    $this->assertStringContainsString("class='update-status-status-table'", $status);
    $this->assertStringContainsString("class='release-row release-row-security'", $status);
  }

  /**
   * Confirms the update endpoint receives the site URL, enabled non-core
   * modules, and the FlightPath version while omitting versionless core items.
   */
  public function testInstallStatusUrlIncludesVersionedModulesOnly(): void {
    $url = update_status_get_install_status_url();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    $this->assertSame('cv', $query['pa']);
    $this->assertSame($GLOBALS['fp_system_settings']['base_url'], $query['u']);
    $this->assertSame(FLIGHTPATH_CORE, $query['c']);
    $this->assertSame(
      FLIGHTPATH_CORE . '-' . FLIGHTPATH_VERSION,
      $query['modules']['flightpath']
    );
    $this->assertSame(
      $GLOBALS['fp_system_settings']['modules']['lassie']['version'],
      $query['modules']['lassie']
    );
    $this->assertArrayNotHasKey('comments', $query['modules']);
  }
}
