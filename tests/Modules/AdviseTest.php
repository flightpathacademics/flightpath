<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for Advise navigation, draft-mode state, and permissions.
 */
class AdviseTest extends FlightPathTestCase {

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('advise_menu')) {
      require_once __DIR__ . '/../../modules/advise/advise.module';
    }

    $_SESSION['fp_draft_mode'] = 'no';
  }

  protected function tearDown(): void {
    unset($_SESSION['fp_draft_mode']);
    parent::tearDown();
  }

  /**
   * Confirms degree, What If, history, and draft-mode routes use their intended
   * callbacks and access controls for advising workflows.
   */
  public function testMenuDefinesAdvisingAndDraftModeRoutes(): void {
    $items = advise_menu();

    $this->assertSame('fp_render_form', $items['admin-tools/toggle-draft']['page_callback']);
    $this->assertSame(array('advise_toggle_draft_form'), $items['admin-tools/toggle-draft']['page_arguments']);
    $this->assertSame(array('toggle_draft'), $items['admin-tools/toggle-draft']['access_arguments']);
    $this->assertSame('advise_display_view', $items['view']['page_callback']);
    $this->assertSame(array('view'), $items['view']['page_arguments']);
    $this->assertSame('advise_can_access_view', $items['view']['access_callback']);
    $this->assertSame(array('what-if'), $items['what-if']['page_arguments']);
    $this->assertSame('advise_display_history', $items['history']['page_callback']);
    $this->assertSame(MENU_TYPE_TAB, $items['history']['type']);
  }

  /**
   * Ensures the draft-mode form reflects the active session setting and supplies
   * the explicit redirect that returns staff to the dashboard after changing it.
   */
  public function testToggleDraftFormUsesCurrentSessionMode(): void {
    $_SESSION['fp_draft_mode'] = 'yes';

    $form = advise_toggle_draft_form();

    $this->assertStringContainsString('Draft Mode', strip_tags($form['mark_top']['value']));
    $this->assertSame('radios', $form['draft']['type']);
    $this->assertSame(array('yes' => 'Yes', 'no' => 'No'), $form['draft']['options']);
    $this->assertSame('yes', $form['draft']['value']);
    $this->assertSame('main', $form['#redirect']['path']);
  }

  /**
   * Verifies a submitted draft-mode choice is retained in the session, which
   * the advising and Blank Degrees screens use to select draft degree data.
   */
  public function testToggleDraftSubmitStoresSessionMode(): void {
    advise_toggle_draft_form_submit(array(), array('values' => array('draft' => 'yes')));

    $this->assertSame('yes', $_SESSION['fp_draft_mode']);
  }

  /**
   * Confirms permissions distinguish viewing sessions, advising students,
   * substitutions, course descriptions, and draft-mode operation.
   */
  public function testPermissionDefinesAdvisingCapabilities(): void {
    $permissions = advise_perm();

    $this->assertArrayHasKey('view_any_advising_session', $permissions);
    $this->assertArrayHasKey('view_advisee_advising_session', $permissions);
    $this->assertArrayHasKey('can_advise_students', $permissions);
    $this->assertArrayHasKey('can_substitute', $permissions);
    $this->assertArrayHasKey('view_degree_plan_course_descriptions', $permissions);
    $this->assertArrayHasKey('toggle_draft', $permissions);
    $this->assertSame('Can advise students', $permissions['can_advise_students']['title']);
  }
}
