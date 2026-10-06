<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for TinyMCE configuration and conditional editor activation.
 */
class TinyMceTest extends FlightPathTestCase {

  private bool $includePathsExisted;
  private mixed $originalIncludePaths;
  private bool $toolbarExisted;
  private mixed $originalToolbar;

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('tinymce_init')) {
      require_once __DIR__ . '/../../modules/tinymce/tinymce.module';
    }

    $this->includePathsExisted = variable_exists('tinymce_include_on_paths');
    $this->originalIncludePaths = variable_get('tinymce_include_on_paths', '');
    $this->toolbarExisted = variable_exists('tinymce_toolbar');
    $this->originalToolbar = variable_get('tinymce_toolbar', '');
    unset($GLOBALS['tinymce_active']);
  }

  protected function tearDown(): void {
    if ($this->includePathsExisted) {
      variable_set('tinymce_include_on_paths', $this->originalIncludePaths);
    }
    else {
      variable_delete('tinymce_include_on_paths');
    }

    if ($this->toolbarExisted) {
      variable_set('tinymce_toolbar', $this->originalToolbar);
    }
    else {
      variable_delete('tinymce_toolbar');
    }

    unset($GLOBALS['tinymce_active']);
    parent::tearDown();
  }

  /**
   * Confirms the TinyMCE settings route uses the administrative form and
   * permission that protect site-wide editor behavior.
   */
  public function testMenuDefinesTinyMceSettingsRoute(): void {
    $items = tinymce_menu();
    $item = $items['admin/config/tinymce'];

    $this->assertSame('fp_render_form', $item['page_callback']);
    $this->assertSame(array('tinymce_config_form', 'system_settings'), $item['page_arguments']);
    $this->assertSame(array('administer_tinymce'), $item['access_arguments']);
    $this->assertSame(MENU_TYPE_NORMAL_ITEM, $item['type']);
    $this->assertTrue($item['page_settings']['page_hide_report_error']);
  }

  /**
   * Ensures saved editor paths and toolbar settings are surfaced by the form
   * so administrators can review the configuration they are about to change.
   */
  public function testConfigFormUsesStoredSettings(): void {
    variable_set('tinymce_include_on_paths', "comments\ncontent/*");
    variable_set('tinymce_toolbar', 'bold italic | link');

    $form = tinymce_config_form();

    $this->assertSame("comments\ncontent/*", $form['tinymce_include_on_paths']['value']);
    $this->assertSame('textarea', $form['tinymce_include_on_paths']['type']);
    $this->assertSame('bold italic | link', $form['tinymce_toolbar']['value']);
    $this->assertSame('textfield', $form['tinymce_toolbar']['type']);
  }

  /**
   * Confirms exact and trailing-wildcard paths activate TinyMCE for an
   * authenticated user, which is required for textarea_editor fields to work.
   */
  public function testInitActivatesEditorForConfiguredPaths(): void {
    global $user;
    $user->id = 17;
    variable_set('tinymce_include_on_paths', "comments\ncontent/*");
    $_REQUEST['q'] = 'content/edit/123';

    tinymce_init();

    $this->assertTrue($GLOBALS['tinymce_active']);
  }

  /**
   * Ensures TinyMCE never activates for anonymous users even when the current
   * path is configured, avoiding unnecessary editor assets on public pages.
   */
  public function testInitDoesNotActivateEditorForAnonymousUser(): void {
    global $user;
    $user->id = 0;
    variable_set('tinymce_include_on_paths', 'comments');
    $_REQUEST['q'] = 'comments';

    tinymce_init();

    $this->assertArrayNotHasKey('tinymce_active', $GLOBALS);
  }

  /**
   * Confirms active editor forms receive copy-and-paste guidance only on
   * textarea_editor elements, preserving ordinary field descriptions.
   */
  public function testFormAlterAddsInstructionsToEditorFieldsOnly(): void {
    $GLOBALS['tinymce_active'] = TRUE;
    $form = array(
      'editor' => array('type' => 'textarea_editor', 'description' => 'Existing help.'),
      'plain' => array('type' => 'textarea', 'description' => 'Plain help.'),
    );

    tinymce_form_alter($form, 'tinymce_test_form');

    $this->assertStringContainsString('Existing help.', strip_tags($form['editor']['description']));
    $this->assertStringContainsString('Trouble with Copy/Paste?', strip_tags($form['editor']['description']));
    $this->assertStringContainsString("class='tinymce-extra-instructions'", $form['editor']['description']);
    $this->assertSame('Plain help.', $form['plain']['description']);
  }

  /**
   * Ensures the module advertises its dedicated configuration permission for
   * permission-management screens and route access checks.
   */
  public function testPermissionDefinesTinyMceAdministration(): void {
    $permissions = tinymce_perm();

    $this->assertSame(array('administer_tinymce'), array_keys($permissions));
    $this->assertSame('Administer TinyMCE', $permissions['administer_tinymce']['title']);
  }
}
