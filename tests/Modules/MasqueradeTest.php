<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for masquerade menu and user-lookup behavior.
 */
class MasqueradeTest extends FlightPathTestCase {

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('masquerade_form_submit')) {
      require_once __DIR__ . '/../../modules/masquerade/masquerade.module';
    }

    unset($_SESSION['masquerade_lookup_users']);
  }

  protected function tearDown(): void {
    unset($_SESSION['masquerade_lookup_users']);
    parent::tearDown();
  }

  /**
   * Confirms privileged masquerade routes retain their form and callback
   * contracts so impersonation remains gated by its dedicated permission.
   */
  public function testMenuDefinesProtectedMasqueradeRoutes(): void {
    $items = masquerade_menu();

    $this->assertSame(array('admin-tools/masquerade', 'masquerade'), array_keys($items));
    $this->assertSame('fp_render_form', $items['admin-tools/masquerade']['page_callback']);
    $this->assertSame(array('masquerade_form'), $items['admin-tools/masquerade']['page_arguments']);
    $this->assertSame(array('access_masquerade'), $items['admin-tools/masquerade']['access_arguments']);
    $this->assertSame(MENU_TYPE_CALLBACK, $items['masquerade']['type']);
    $this->assertSame('masquerade_perform_masquerade', $items['masquerade']['page_callback']);
  }

  /**
   * Ensures a lookup stages matching users in the session for the follow-up
   * selection screen, while excluding the administrator account from targets.
   */
  public function testLookupSubmitStoresMatchingNonAdminUsers(): void {
    $formState = array('values' => array('username_or_cwid' => 'Student'));

    masquerade_form_submit(array(), $formState);

    $this->assertArrayHasKey(661, $_SESSION['masquerade_lookup_users']);
    $this->assertSame('661', (string) $_SESSION['masquerade_lookup_users'][661]);
    $this->assertArrayNotHasKey(1, $_SESSION['masquerade_lookup_users']);
  }

  /**
   * Confirms a staged lookup is rendered as an actionable user choice and is
   * consumed afterward so an old lookup cannot leak into a later form view.
   */
  public function testFormRendersAndConsumesLookupResults(): void {
    $_SESSION['masquerade_lookup_users'] = array(661);

    $form = masquerade_form();
    $markup = implode('', array_column($form, 'value'));
    $text = preg_replace('/\s+/', ' ', trim(strip_tags($markup)));

    $this->assertStringContainsString('Test Student (999999999) - student', $text);
    $this->assertStringContainsString('masquerade?user_id=661', $markup);
    $this->assertArrayNotHasKey('masquerade_lookup_users', $_SESSION);
  }

  /**
   * Ensures an empty search does not replace a prior result set or create a
   * misleading no-results state for an administrator.
   */
  public function testBlankLookupDoesNotStageResults(): void {
    $formState = array('values' => array('username_or_cwid' => '   '));

    masquerade_form_submit(array(), $formState);

    $this->assertArrayNotHasKey('masquerade_lookup_users', $_SESSION);
  }

  /**
   * Confirms the module advertises the explicitly restricted permission that
   * controls access to this high-impact impersonation feature.
   */
  public function testPermissionDescribesMasqueradeAccess(): void {
    $permissions = masquerade_perm();

    $this->assertSame(array('access_masquerade'), array_keys($permissions));
    $this->assertTrue($permissions['access_masquerade']['admin_restricted']);
    $this->assertStringContainsString('become any other user', $permissions['access_masquerade']['description']);
  }
}
