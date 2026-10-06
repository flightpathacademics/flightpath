<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for persisted notification-history records.
 */
class NotifyTest extends FlightPathTestCase {

  private array $notificationIds = array();

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('notify_save_notification')) {
      require_once __DIR__ . '/../../modules/notify/notify.module';
    }
  }

  protected function tearDown(): void {
    foreach ($this->notificationIds as $notificationId) {
      db_query('DELETE FROM notification_history WHERE hid = ?', array($notificationId));
    }

    parent::tearDown();
  }

  /**
   * Confirms notification history retains its delivery context and message,
   * providing an audit trail without performing actual email or SMS delivery.
   */
  public function testSaveNotificationPersistsDeliveryHistory(): void {
    $notificationId = notify_save_notification(
      61,
      314,
      'alert',
      'email',
      'advisor@example.test',
      'FlightPath - Notification',
      'A test notification.',
      'remind'
    );
    $this->notificationIds[] = $notificationId;

    $record = db_fetch_array(db_query(
      'SELECT * FROM notification_history WHERE hid = ?',
      array($notificationId)
    ));

    $this->assertSame(intval($notificationId), intval($record['hid']));
    $this->assertSame(314, intval($record['cid']));
    $this->assertSame('alert', $record['content_type']);
    $this->assertSame(61, intval($record['to_user_id']));
    $this->assertSame('email', $record['notification_method']);
    $this->assertSame('advisor@example.test', $record['to_address']);
    $this->assertSame('FlightPath - Notification', $record['subject']);
    $this->assertSame('A test notification.', $record['msg']);
    $this->assertSame('remind', $record['notification_type']);
    $this->assertGreaterThan(0, intval($record['submitted']));
  }
}
