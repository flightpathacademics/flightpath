<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for SMTP configuration and safe mail validation behavior.
 */
class SmtpTest extends FlightPathTestCase {

  private array $originalVariables = array();

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('smtp_menu')) {
      require_once __DIR__ . '/../../modules/smtp/smtp.module';
    }

    foreach (array(
      'smtp_host',
      'smtp_port',
      'smtp_secure',
      'smtp_username',
      'smtp_password',
      'smtp_from_email_address',
      'smtp_from_email_name',
    ) as $name) {
      $this->originalVariables[$name] = array(
        'exists' => variable_exists($name),
        'value' => variable_get($name, NULL),
      );
    }
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

    parent::tearDown();
  }

  /**
   * Confirms SMTP settings are exposed through the administrative form route
   * and retain their dedicated permission requirement.
   */
  public function testMenuDefinesSmtpSettingsRoute(): void {
    $items = smtp_menu();
    $item = $items['admin/config/smtp'];

    $this->assertSame('SMTP settings', $item['title']);
    $this->assertSame('fp_render_form', $item['page_callback']);
    $this->assertSame(array('smtp_settings_form', 'system_settings'), $item['page_arguments']);
    $this->assertSame(array('de_can_administer_smtp'), $item['access_arguments']);
    $this->assertSame(MENU_TYPE_NORMAL_ITEM, $item['type']);
  }

  /**
   * Ensures the form has practical defaults for a fresh installation so the
   * administrator is shown the expected sender identity and security choices.
   */
  public function testSettingsFormUsesDefaults(): void {
    foreach (array_keys($this->originalVariables) as $name) {
      variable_delete($name);
    }

    $form = smtp_settings_form();

    $this->assertSame('', $form['smtp_host']['value']);
    $this->assertSame('', $form['smtp_port']['value']);
    $this->assertSame(array('none' => 'none', 'tls' => 'TLS', 'ssl' => 'SSL'), $form['smtp_secure']['options']);
    $this->assertSame('noreply@yourdomain.com', $form['smtp_from_email_address']['value']);
    $this->assertSame('NoReply - FlightPath', $form['smtp_from_email_name']['value']);
  }

  /**
   * Confirms saved connection and sender settings reappear in their matching
   * inputs, which prevents an administrator from unknowingly overwriting them.
   */
  public function testSettingsFormUsesStoredConfiguration(): void {
    variable_set('smtp_host', 'mail.example.test');
    variable_set('smtp_port', '587');
    variable_set('smtp_secure', 'tls');
    variable_set('smtp_username', 'flightpath');
    variable_set('smtp_password', 'test-password');
    variable_set('smtp_from_email_address', 'advising@example.test');
    variable_set('smtp_from_email_name', 'FlightPath Advising');

    $form = smtp_settings_form();

    $this->assertSame('mail.example.test', $form['smtp_host']['value']);
    $this->assertSame('587', $form['smtp_port']['value']);
    $this->assertSame('tls', $form['smtp_secure']['value']);
    $this->assertSame('flightpath', $form['smtp_username']['value']);
    $this->assertSame('test-password', $form['smtp_password']['value']);
    $this->assertSame('advising@example.test', $form['smtp_from_email_address']['value']);
    $this->assertSame('FlightPath Advising', $form['smtp_from_email_name']['value']);
  }

  /**
   * Ensures malformed recipient addresses are rejected before PHPMailer is
   * loaded or an SMTP connection can be attempted.
   */
  public function testMailRejectsInvalidRecipientBeforeSending(): void {
    $this->assertFalse(smtp_mail('not-an-email-address', 'Test subject', 'Test message'));
  }
}
