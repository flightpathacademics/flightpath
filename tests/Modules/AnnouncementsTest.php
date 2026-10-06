<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for the announcements module's content-type and permission hooks.
 */
class AnnouncementsTest extends FlightPathTestCase {

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('announcements_content_register_content_type')) {
      require_once __DIR__ . '/../../modules/announcements/announcements.module';
    }
  }

  /**
   * Confirms announcements expose the content fields and visibility choices
   * needed to publish dated public or faculty-only notices.
   */
  public function testAnnouncementContentTypeDefinesExpectedFields(): void {
    $types = announcements_content_register_content_type();

    $this->assertArrayHasKey('announcement', $types);
    $announcement = $types['announcement'];
    $this->assertSame('Announcement', $announcement['title']);
    $this->assertSame(
      'This is a short announcement to the user, meant to be displayed like news items in a block.',
      $announcement['description']
    );
    $this->assertSame(array('activity_datetime', 'msg', 'visibility'), array_keys($announcement['fields']));
    $this->assertSame('datetime-local', $announcement['fields']['activity_datetime']['type']);
    $this->assertSame('now', $announcement['fields']['activity_datetime']['value']);
    $this->assertTrue($announcement['fields']['activity_datetime']['required']);
    $this->assertSame('textarea_editor', $announcement['fields']['msg']['type']);
    $this->assertSame('basic', $announcement['fields']['msg']['filter']);
    $this->assertSame(array(
      'public' => 'Anyone (incl. student)',
      'faculty' => 'Faculty/Staff only',
    ), $announcement['fields']['visibility']['options']);
  }

  /**
   * Ensures the module advertises the faculty-announcement permission used by
   * callers to keep staff-only notices out of student-facing views.
   */
  public function testPermissionDefinesFacultyAnnouncementAccess(): void {
    $permissions = announcements_perm();

    $this->assertSame(array('view_faculty_announcements'), array_keys($permissions));
    $this->assertSame('View faculty announcements', $permissions['view_faculty_announcements']['title']);
  }
}
