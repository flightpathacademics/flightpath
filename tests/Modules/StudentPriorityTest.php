<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for the student_priority module's core priority presentation and cache.
 */
class StudentPriorityTest extends FlightPathTestCase {

  const TEST_STUDENT_ID = 'STUDENT_PRIORITY_TEST';

  private bool $hadCurrentStudentId;
  private mixed $originalCurrentStudentId;
  private bool $hadStudent;
  private mixed $originalStudent;

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('student_priority_menu')) {
      require_once __DIR__ . '/../../modules/student_priority/student_priority.module';
    }

    $this->hadCurrentStudentId = array_key_exists('current_student_id', $GLOBALS);
    $this->originalCurrentStudentId = $GLOBALS['current_student_id'] ?? NULL;
    $this->hadStudent = array_key_exists('student', $GLOBALS);
    $this->originalStudent = $GLOBALS['student'] ?? NULL;
    $this->clearPriorityRecord();
  }

  protected function tearDown(): void {
    $this->clearPriorityRecord();

    if ($this->hadCurrentStudentId) {
      $GLOBALS['current_student_id'] = $this->originalCurrentStudentId;
    }
    else {
      unset($GLOBALS['current_student_id']);
    }

    if ($this->hadStudent) {
      $GLOBALS['student'] = $this->originalStudent;
    }
    else {
      unset($GLOBALS['student']);
    }

    parent::tearDown();
  }

  /**
   * Confirms the priority-calculation page remains a student-profile tab with
   * the access rule and screen settings required by its parent workflow.
   */
  public function testMenuDefinesPriorityCalculationsTab(): void {
    $items = student_priority_menu();

    $this->assertArrayHasKey('student-profile/priority-calculations', $items);
    $item = $items['student-profile/priority-calculations'];
    $this->assertSame('Priority Calculations', $item['title']);
    $this->assertSame('student_priority_display_priority_calculations_page', $item['page_callback']);
    $this->assertSame('system_can_access_student', $item['access_callback']);
    $this->assertSame(MENU_TYPE_TAB, $item['type']);
    $this->assertSame('priority-calculations', $item['tab_family']);
    $this->assertTrue($item['page_settings']['display_currently_advising']);
    $this->assertSame('not_advising', $item['page_settings']['screen_mode']);
  }

  /**
   * Protects the normal, medium, and high thresholds used throughout Student
   * Profile so advisors see a consistent interpretation of a priority score.
   */
  public function testPriorityLabelUsesDocumentedBoundaryValues(): void {
    $cases = array(
      array(0, 'normal', 'Normal'),
      array(30, 'normal', 'Normal'),
      array(31, 'medium', 'Medium'),
      array(70, 'medium', 'Medium'),
      array(70.01, 'high', 'High'),
    );

    foreach ($cases as $case) {
      list($value, $machine, $label) = $case;
      $result = student_priority_get_student_academic_priority_label($value);

      $this->assertSame($value, $result['val']);
      $this->assertSame($machine, $result['machine']);
      $this->assertSame($label, $result['label']);
    }
  }

  /**
   * Ensures a fresh saved priority is reused rather than recalculated, which
   * keeps repeated Student Profile views responsive and stable.
   */
  public function testFreshPriorityValueIsReadFromCache(): void {
    db_query(
      'INSERT INTO student_priority (student_id, priority_value, results, updated) VALUES (?, ?, ?, ?)',
      array(self::TEST_STUDENT_ID, 42.5, 'cached results', time())
    );

    $value = student_priority_get_academic_priority_value(self::TEST_STUDENT_ID, 86400);

    $this->assertSame(42.5, (float) $value);
  }

  /**
   * Confirms the page communicates the baseline priority result and scoring
   * scale when no site-specific calculation tests have been registered.
   */
  public function testPriorityCalculationsPageShowsBaselinePriority(): void {
    $GLOBALS['current_student_id'] = self::TEST_STUDENT_ID;
    $GLOBALS['student'] = new Student('', NULL, 0);

    $html = student_priority_display_priority_calculations_page();
    $text = preg_replace('/\s+/', ' ', trim(strip_tags($html)));

    $this->assertStringContainsString('Current Academic Priority:', $text);
    $this->assertStringContainsString('Normal', $text);
    $this->assertStringContainsString('Percent Scoring:', $text);
    $this->assertStringContainsString("class='profile-priority-bar priority-normal'", $html);
  }

  private function clearPriorityRecord(): void {
    db_query('DELETE FROM student_priority WHERE student_id = ?', array(self::TEST_STUDENT_ID));
  }
}
