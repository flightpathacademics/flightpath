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
}
