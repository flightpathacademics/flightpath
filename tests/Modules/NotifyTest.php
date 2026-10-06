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

  /**
   * Ensures notification-history rows preserve the documented default values
   * when a caller records a generic notification without optional metadata.
   */
  public function testSaveNotificationAppliesDefaultHistoryValues(): void {
    $notificationId = notify_save_notification(62);
    $this->notificationIds[] = $notificationId;

    $record = db_fetch_array(db_query(
      'SELECT * FROM notification_history WHERE hid = ?',
      array($notificationId)
    ));

    $this->assertSame(0, intval($record['cid']));
    $this->assertSame('', $record['content_type']);
    $this->assertSame(62, intval($record['to_user_id']));
    $this->assertSame('', $record['notification_method']);
    $this->assertSame('', $record['to_address']);
    $this->assertSame('', $record['subject']);
    $this->assertSame('', $record['msg']);
    $this->assertSame('default', $record['notification_type']);
  }

  /**
   * Confirms a user who opts out with the NONE notification setting receives
   * no persisted notification and cannot accidentally trigger a delivery path.
   */
  public function testSendNotificationHonorsNonePreferenceWithoutDelivery(): void {
    if (!function_exists('user_set_setting')) require_once __DIR__ . '/../../modules/user/user.module';
    $userName = 'notify_none_' . substr(sha1(uniqid('', TRUE)), 0, 12);
    db_query('INSERT INTO users (user_name, cwid, email) VALUES (?, ?, ?)', array($userName, strtoupper($userName), $userName . '@example.test'));
    $userId = intval(db_insert_id());
    try {
      user_set_setting($userId, 'default_notification_method', 'NONE');
      unset($GLOBALS['fp_load_user'][$userId]);
      notify_send_notification_to_user($userId, 'This notification must not be delivered.', 987, 'alert');

      $count = intval(db_result(db_query(
        'SELECT COUNT(*) FROM notification_history WHERE to_user_id = ? AND cid = ?',
        array($userId, 987)
      )));
      $this->assertSame(0, $count);
    }
    finally {
      db_query('DELETE FROM notification_history WHERE to_user_id = ?', array($userId));
      db_query('DELETE FROM user_settings WHERE user_id = ?', array($userId));
      db_query('DELETE FROM users WHERE user_id = ?', array($userId));
      unset($GLOBALS['fp_load_user'][$userId]);
    }
  }
}
