<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for Engagements navigation, content registration, and phone helpers.
 */
class EngagementsTest extends FlightPathTestCase {

  private bool $disabledTabsExisted;
  private mixed $originalDisabledTabs;
  private array $smsVariables = array();
  private const TEST_USER_ID = 918273;

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('engagements_menu')) {
      require_once __DIR__ . '/../../modules/engagements/engagements.module';
    }

    $this->disabledTabsExisted = variable_exists('system_disable_student_tabs');
    $this->originalDisabledTabs = variable_get('system_disable_student_tabs', array());

    foreach (array('sms_from_phone', 'sms_mass_phone', 'sms_header') as $name) {
      $this->smsVariables[$name] = array(
        'exists' => variable_exists($name),
        'value' => variable_get($name),
      );
    }
  }

  protected function tearDown(): void {
    if ($this->disabledTabsExisted) {
      variable_set('system_disable_student_tabs', $this->originalDisabledTabs);
    }
    else {
      variable_delete('system_disable_student_tabs');
    }

    foreach ($this->smsVariables as $name => $setting) {
      if ($setting['exists']) {
        variable_set($name, $setting['value']);
      }
      else {
        variable_delete($name);
      }
    }

    db_query('DELETE FROM user_settings WHERE user_id = ?', array(self::TEST_USER_ID));

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
   * Ensures configured normal and mass-text phone lines retain their human
   * descriptions and honor an explicit default before messages are composed.
   */
  public function testFromPhoneParserUsesDescriptionsAndExplicitDefault(): void {
    variable_set('sms_from_phone', "555-111-1111 ~ Advising Office\n555-222-2222 ~ Evening Advising ~ default");
    variable_set('sms_mass_phone', '555-333-3333 ~ Outreach Campaign ~ default');

    $normalLines = engagements_get_from_phones();
    $massLines = engagements_get_from_phones(TRUE);

    $this->assertSame('5552222222', $normalLines['default']['num']);
    $this->assertSame('Evening Advising', $normalLines['default']['description']);
    $this->assertSame('Advising Office', $normalLines['lines']['5551111111']['description']);
    $this->assertSame('5553333333', $massLines['default']['num']);
    $this->assertSame('Outreach Campaign', $massLines['lines']['5553333333']['description']);
  }

  /**
   * Verifies the SMS settings form preserves configured values and documents
   * the default-line convention that determines routine sending behavior.
   */
  public function testSmsSettingsFormUsesConfiguredValuesAndGuidance(): void {
    variable_set('sms_from_phone', '555-111-1111 ~ Advising ~ default');
    variable_set('sms_mass_phone', '555-333-3333 ~ Outreach');
    variable_set('sms_header', '(@initials) @name:');

    $form = engagements_sms_settings_form();

    $this->assertSame('textarea', $form['sms_from_phone']['type']);
    $this->assertSame('555-111-1111 ~ Advising ~ default', $form['sms_from_phone']['value']);
    $this->assertSame('555-333-3333 ~ Outreach', $form['sms_mass_phone']['value']);
    $this->assertSame('(@initials) @name:', $form['sms_header']['value']);
    $this->assertTrue($form['sms_header']['required']);
    $this->assertStringContainsString("Notice that the 'default' designation", strip_tags($form['sms_from_phone']['description']));
  }

  /**
   * Confirms receipt-notification preferences are selected only for configured
   * normal or mass-text lines, so unrelated user settings cannot trigger mail.
   */
  public function testSmsReceiptPreferencesUseConfiguredPhoneLines(): void {
    variable_set('sms_from_phone', '555-111-1111 ~ Advising ~ default');
    variable_set('sms_mass_phone', '555-333-3333 ~ Outreach ~ default');
    user_set_setting(self::TEST_USER_ID, 'notify_sms_receipt__5551111111', '5551111111');
    user_set_setting(self::TEST_USER_ID, 'notify_sms_receipt__5553333333', '5553333333');
    user_set_setting(self::TEST_USER_ID, 'notify_sms_receipt__5559999999', '5559999999');

    $selected = engagements_get_user_notify_sms_receipt_values(self::TEST_USER_ID);

    $this->assertSame(array(
      '5551111111' => 5551111111,
      '5553333333' => 5553333333,
    ), $selected);
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
