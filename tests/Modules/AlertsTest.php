<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for Alerts navigation, configuration, and session filtering.
 */
class AlertsTest extends FlightPathTestCase {

  private bool $tagsExisted;
  private mixed $originalTags;

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('alerts_menu')) {
      require_once __DIR__ . '/../../modules/alerts/alerts.module';
    }

    $this->tagsExisted = variable_exists('alerts_tags');
    $this->originalTags = variable_get('alerts_tags', '');
  }

  protected function tearDown(): void {
    if ($this->tagsExisted) {
      variable_set('alerts_tags', $this->originalTags);
    }
    else {
      variable_delete('alerts_tags');
    }

    unset($_SESSION['alerts_filter_status']);
    parent::tearDown();
  }

  /**
   * Confirms alert, activity, and administrative routes have the callbacks and
   * permissions that keep advisor workflows and system settings separated.
   */
  public function testMenuDefinesAlertAndAdministrationRoutes(): void {
    $items = alerts_menu();

    $this->assertSame('fp_render_form', $items['alerts']['page_callback']);
    $this->assertSame(array('alerts_advisees_alerts_form'), $items['alerts']['page_arguments']);
    $this->assertSame(array('view_advisee_alerts'), $items['alerts']['access_arguments']);
    $this->assertSame(MENU_TYPE_TAB, $items['alerts']['type']);
    $this->assertSame('alerts_display_advisee_activities_page', $items['advisee-activities']['page_callback']);
    $this->assertSame(array('can_view_advisee_activity_records'), $items['advisee-activities']['access_arguments']);
    $this->assertSame(array('administer_alerts'), $items['admin/config/alerts-settings']['access_arguments']);
  }

  /**
   * Ensures stored alert tags are presented in the setting field so staff can
   * safely review and maintain the classifications used when creating alerts.
   */
  public function testSettingsFormUsesStoredTags(): void {
    variable_set('alerts_tags', "Academics\nFinancial Aid");

    $form = alerts_settings_form();

    $this->assertSame('hidden', $form['school_id']['type']);
    $this->assertSame(0, $form['school_id']['value']);
    $this->assertSame('textarea', $form['alerts_tags']['type']);
    $this->assertSame("Academics\nFinancial Aid", $form['alerts_tags']['value']);
  }

  /**
   * Verifies the advisor-facing status filter is retained in the session after
   * submission, allowing the alerts listing to preserve the chosen view.
   */
  public function testAlertFilterSubmitStoresSelectedStatus(): void {
    $formState = array('values' => array('filter_status' => 'closed'));

    alerts_advisees_alerts_form_submit(array(), $formState);

    $this->assertSame('closed', $_SESSION['alerts_filter_status']);
  }

  /**
   * Confirms permission definitions include the distinct administration and
   * advisee-access capabilities used by alert routes and action controls.
   */
  public function testPermissionDefinesAdministrationAndAdviseeAccess(): void {
    $permissions = alerts_perm();

    $this->assertArrayHasKey('administer_alerts', $permissions);
    $this->assertArrayHasKey('view_advisee_alerts', $permissions);
    $this->assertArrayHasKey('can_edit_alerts', $permissions);
    $this->assertSame('Administer Alerts Settings', $permissions['administer_alerts']['title']);
  }
}
