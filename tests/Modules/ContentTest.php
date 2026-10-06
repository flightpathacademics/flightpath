<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for Content routes, the Page type, and block declarations.
 */
class ContentTest extends FlightPathTestCase {

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('content_menu')) {
      require_once __DIR__ . '/../../modules/content/content.module';
    }
  }

  /**
   * Confirms content administration, public-file, and record routes use their
   * dedicated access checks to protect content and attached files.
   */
  public function testMenuDefinesContentManagementAndRecordAccessRoutes(): void {
    $items = content_menu();

    $this->assertSame('content_display_content_admin_list', $items['admin-tools/content']['page_callback']);
    $this->assertSame(array('admin_content'), $items['admin-tools/content']['access_arguments']);
    $this->assertSame(MENU_TYPE_DEFAULT_TAB, $items['admin-tools/content']['type']);
    $this->assertSame(array('admin_public_files'), $items['admin-tools/content/files']['access_arguments']);
    $this->assertSame('content_user_access', $items['content/add/%']['access_callback']);
    $this->assertSame(array('add', 2), $items['content/add/%']['access_arguments']);
    $this->assertSame(array('view', 1), $items['content/%']['access_arguments']);
    $this->assertSame('content_files_user_may_download_file', $items['content-files/handle-download/%']['access_callback']);
  }

  /**
   * Ensures the built-in Page type uses the filtered rich-text body field that
   * is required for administrators to create ordinary public pages safely.
   */
  public function testContentTypeDefinesRichTextPageBody(): void {
    $types = content_content_register_content_type();
    $page = $types['page'];

    $this->assertSame('Page', $page['title']);
    $this->assertSame('This is a basic, publicly visible web page.', $page['description']);
    $this->assertSame('textarea_editor', $page['fields']['body']['type']);
    $this->assertSame('basic', $page['fields']['body']['filter']);
  }

  /**
   * Confirms the Content module advertises its primary block, allowing the
   * theme/block system to discover content rendering without querying records.
   */
  public function testBlocksDeclaresPrimaryContentBlock(): void {
    $this->assertSame(array('primary' => 'Primary content block'), content_blocks());
  }

  /**
   * Ensures file-icon selection recognizes specific MIME families first, then
   * common extensions, and falls back to the generic file icon when unknown.
   */
  public function testFileIconSelectionSupportsMimeExtensionAndFallbackCases(): void {
    $this->assertSame('fa-file-pdf-o', content_get_fontawesome_icon_for_mimetype('application/pdf'));
    $this->assertSame('fa-file-image-o', content_get_fontawesome_icon_for_mimetype('image/webp'));
    $this->assertSame('fa-file-excel-o', content_get_fontawesome_icon_for_mimetype('', 'xlsx'));
    $this->assertSame('fa-file-archive-o', content_get_fontawesome_icon_for_mimetype('', 'zip'));
    $this->assertSame('fa-file-o', content_get_fontawesome_icon_for_mimetype('application/octet-stream', 'unknown'));
  }

  /**
   * Verifies content access honors publication, visibility, student ownership,
   * and own-versus-any edit rights without leaking protected records.
   */
  public function testContentUserAccessHonorsVisibilityOwnershipAndPermissions(): void {
    global $user;
    $contentId = random_int(800000000, 899999999);
    $originalUser = $user;
    $cacheExisted = array_key_exists('content_cache', $GLOBALS);
    $originalCache = $GLOBALS['content_cache'] ?? NULL;
    $content = (object) array(
      'cid' => $contentId,
      'type' => 'announcement',
      'published' => 1,
      'user_id' => 62,
      'field__visibility' => array('value' => 'faculty'),
      'field__student_id' => array('value' => 'STUDENT_A'),
    );
    try {
      $GLOBALS['content_cache'][$contentId] = $content;

      $user = (object) array('id' => 1, 'cwid' => 'ADMIN', 'is_student' => FALSE, 'permissions' => array());
      $this->assertTrue(content_user_access('view', $contentId));

      $user = (object) array('id' => 62, 'cwid' => 'FACULTY', 'is_student' => FALSE, 'permissions' => array('view_announcement_content', 'edit_own_announcement_content'));
      $this->assertTrue(content_user_access('view', $contentId));
      $this->assertTrue(content_user_access('edit', $contentId));

      $user = (object) array('id' => 63, 'cwid' => 'STUDENT_A', 'is_student' => TRUE, 'permissions' => array('view_announcement_content'));
      $this->assertFalse(content_user_access('view', $contentId));

      $content->field__visibility['value'] = 'public';
      $this->assertTrue(content_user_access('view', $contentId));
      $content->field__student_id['value'] = 'STUDENT_B';
      $this->assertFalse(content_user_access('view', $contentId));

      $user = (object) array('id' => 64, 'cwid' => 'FACULTY_OTHER', 'is_student' => FALSE, 'permissions' => array('edit_any_announcement_content', 'add_announcement_content'));
      $this->assertTrue(content_user_access('edit', $contentId));
      $this->assertTrue(content_user_access('add', 'announcement'));
    }
    finally {
      $user = $originalUser;
      if ($cacheExisted) $GLOBALS['content_cache'] = $originalCache; else unset($GLOBALS['content_cache']);
    }
  }
}
