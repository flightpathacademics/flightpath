<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for Engagements navigation, content registration, and phone helpers.
 */
class EngagementsTest extends FlightPathTestCase {

  private bool $disabledTabsExisted;
  private mixed $originalDisabledTabs;

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('engagements_menu')) {
      require_once __DIR__ . '/../../modules/engagements/engagements.module';
    }

    $this->disabledTabsExisted = variable_exists('system_disable_student_tabs');
    $this->originalDisabledTabs = variable_get('system_disable_student_tabs', array());
  }

  protected function tearDown(): void {
    if ($this->disabledTabsExisted) {
      variable_set('system_disable_student_tabs', $this->originalDisabledTabs);
    }
    else {
      variable_delete('system_disable_student_tabs');
    }

    unset($_GET['window_mode']);
    parent::tearDown();
  }

  /**
   * Confirms normal navigation exposes the Engagements tab and keeps its email,
   * SMS, and mass-message configuration routes under appropriate permissions.
   */
  public function testMenuDefinesEngagementAndConfigurationRoutes(): void {
    variable_set('system_disable_student_tabs', array());

    $items = engagements_menu();

    $this->assertSame('engagements_display_main', $items['engagements']['page_callback']);
    $this->assertSame(array('can_view_engagements'), $items['engagements']['access_arguments']);
    $this->assertSame(MENU_TYPE_TAB, $items['engagements']['type']);
    $this->assertSame(array('engagements_email_settings_form', 'system_settings'), $items['admin/config/email']['page_arguments']);
    $this->assertSame(array('engagements_sms_settings_form', 'system_settings'), $items['admin/config/sms']['page_arguments']);
    $this->assertSame(array('can_send_mass_sms'), $items['admin-tools/mass-sms']['access_arguments']);
  }

  /**
   * Ensures administrators can disable only the student-facing Engagements tab
   * while retaining the module's callback endpoints and configuration routes.
   */
  public function testMenuOmitsDisabledEngagementsTab(): void {
    variable_set('system_disable_student_tabs', array('engagements' => 'engagements'));

    $items = engagements_menu();

    $this->assertArrayNotHasKey('engagements', $items);
    $this->assertArrayHasKey('engagements-handle-incoming-sms', $items);
    $this->assertArrayHasKey('admin/config/email', $items);
  }

  /**
   * Confirms engagement content captures communication direction, type, time,
   * visibility, and attachments, including the popup-dialog completion route.
   */
  public function testContentTypeDefinesEngagementFieldsAndPopupRedirect(): void {
    $_GET['window_mode'] = 'popup';

    $types = engagements_content_register_content_type();
    $engagement = $types['engagement'];

    $this->assertSame('Engagement', $engagement['title']);
    $this->assertSame('content-dialog-handle-after-save', $engagement['settings']['#redirect']['path']);
    $this->assertSame('datetime-local', $engagement['fields']['activity_datetime']['type']);
    $this->assertTrue($engagement['fields']['engagement_type']['required']);
    $this->assertSame(array('sent' => 'Sent', 'received' => 'Received'), $engagement['fields']['direction']['options']);
    $this->assertSame('file', $engagement['fields']['attachment']['type']);
    $this->assertSame('radios', $engagement['fields']['visibility']['type']);
  }

  /**
   * Verifies phone-number normalization accepts common United States formats
   * and rejects invalid lengths before an SMS provider can be contacted.
   */
  public function testPhoneNormalizationAndFormatting(): void {
    $this->assertSame('5551234567', engagements_convert_to_valid_phone_number('(555) 123-4567'));
    $this->assertSame('5551234567', engagements_convert_to_valid_phone_number('1-555-123-4567'));
    $this->assertFalse(engagements_convert_to_valid_phone_number('555-123'));
    $this->assertSame('(555) 123-4567', engagements_convert_to_pretty_phone_number('5551234567'));
  }

  /**
   * Ensures permissions distinguish administration, viewing, outbound email,
   * outbound text messaging, logging, and mass-message capabilities.
   */
  public function testPermissionDefinesEngagementCapabilities(): void {
    $permissions = engagements_perm();

    $this->assertArrayHasKey('administer_engagements', $permissions);
    $this->assertArrayHasKey('can_view_engagements', $permissions);
    $this->assertArrayHasKey('can_send_email_engagements', $permissions);
    $this->assertArrayHasKey('can_send_txt_engagements', $permissions);
    $this->assertArrayHasKey('can_log_engagements', $permissions);
    $this->assertArrayHasKey('can_send_mass_sms', $permissions);
    $this->assertTrue($permissions['administer_engagements']['admin_restricted']);
  }
}
