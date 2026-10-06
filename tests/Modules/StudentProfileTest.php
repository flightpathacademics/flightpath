<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for the student_profile module's menu and grade summary helpers.
 */
class StudentProfileTest extends FlightPathTestCase {

  private bool $disabledTabsVariableExisted;
  private mixed $originalDisabledTabs;

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('student_profile_menu')) {
      require_once __DIR__ . '/../../modules/student_profile/student_profile.module';
    }

    $this->disabledTabsVariableExisted = (bool) db_result(db_query(
      "SELECT COUNT(*) FROM variables WHERE name = ?",
      array('system_disable_student_tabs')
    ));
    $this->originalDisabledTabs = variable_get('system_disable_student_tabs', array());
  }

  protected function tearDown(): void {
    if ($this->disabledTabsVariableExisted) {
      variable_set('system_disable_student_tabs', $this->originalDisabledTabs);
    }
    else {
      variable_delete('system_disable_student_tabs');
    }

    parent::tearDown();
  }

  /**
   * Confirms the Student Profile tab exposes the expected advising-screen
   * settings when an administrator has not disabled it.
   */
  public function testMenuIncludesStudentProfileTabByDefault(): void {
    variable_set('system_disable_student_tabs', array());

    $items = student_profile_menu();

    $this->assertArrayHasKey('student-profile', $items);
    $this->assertSame('Student Profile', $items['student-profile']['title']);
    $this->assertSame('student_profile_display_student_profile_page', $items['student-profile']['page_callback']);
    $this->assertSame('system_can_access_student', $items['student-profile']['access_callback']);
    $this->assertSame(MENU_TYPE_TAB, $items['student-profile']['type']);
    $this->assertSame('system', $items['student-profile']['tab_family']);
    $this->assertFalse($items['student-profile']['page_settings']['display_currently_advising']);
    $this->assertSame('not_advising', $items['student-profile']['page_settings']['screen_mode']);
  }

  /**
   * Ensures disabling an unrelated student tab does not accidentally hide
   * Student Profile from advisors.
   */
  public function testMenuIncludesStudentProfileTabWhenAnotherTabIsDisabled(): void {
    variable_set('system_disable_student_tabs', array('comments' => 'comments'));

    $items = student_profile_menu();

    $this->assertArrayHasKey('student-profile', $items);
  }

  /**
   * Confirms the system setting can intentionally remove Student Profile from
   * the student tab set.
   */
  public function testMenuOmitsStudentProfileTabWhenItIsDisabled(): void {
    variable_set('system_disable_student_tabs', array('profile' => 'profile'));

    $items = student_profile_menu();

    $this->assertArrayNotHasKey('student-profile', $items);
  }

  /**
   * Confirms an empty academic history produces predictable zero-grade
   * buckets rather than incomplete data for the profile renderer.
   */
  public function testGradePercentagesReturnDefaultCountsForStudentWithNoCourses(): void {
    $student = new Student('', NULL, 0);

    $result = student_profile_get_grade_percentages_for_student($student);

    $this->assertSame(array(
      'grade_counts' => array(
        'D' => array('count' => 0),
        'F' => array('count' => 0),
        'W' => array('count' => 0),
        'A' => array('count' => 0),
        'B' => array('count' => 0),
        'C' => array('count' => 0),
      ),
    ), $result);
  }

  /**
   * Confirms all recorded grade codes contribute to the denominator while the
   * standard and additional grade buckets retain their own course details.
   */
  public function testGradePercentagesCountStandardAndAdditionalGrades(): void {
    $student = new Student('', NULL, 0);
    $courses = array(
      $this->createCourse('BIOL', '1001', 'A', '202040'),
      $this->createCourse('CHEM', '1001', 'A', '202060'),
      $this->createCourse('MATH', '1010', 'D', '202140'),
      $this->createCourse('ENGL', '1001', 'F', '202160'),
      $this->createCourse('HIST', '1001', 'W', '202240'),
      $this->createCourse('UNIV', '1001', 'P', '202260'),
    );

    foreach ($courses as $course) {
      $student->list_courses_taken->add($course);
    }

    $result = student_profile_get_grade_percentages_for_student($student);
    $grades = $result['grade_counts'];

    $this->assertSame(2, $grades['A']['count']);
    $this->assertSame(33.3, $grades['A']['percent']);
    $this->assertSame(1, $grades['D']['count']);
    $this->assertSame(16.7, $grades['D']['percent']);
    $this->assertSame(1, $grades['F']['count']);
    $this->assertSame(16.7, $grades['F']['percent']);
    $this->assertSame(1, $grades['W']['count']);
    $this->assertSame(16.7, $grades['W']['percent']);
    $this->assertSame(0, $grades['B']['count']);
    $this->assertSame(0.0, $grades['B']['percent']);
    $this->assertSame('', $grades['B']['courses_html']);
    $this->assertSame(1, $grades['P']['count']);
    $this->assertSame(16.7, $grades['P']['percent']);
    $this->assertSame($courses[5], $grades['P']['courses'][0]);
  }

  /**
   * Confirms grade-detail output preserves the course information users need
   * while retaining the CSS hook used to display the detail rows.
   */
  public function testGradePercentagesBuildCourseDetailsHtml(): void {
    $student = new Student('', NULL, 0);
    $course = $this->createCourse('MATH', '1013', 'D', '202140');
    $student->list_courses_taken->add($course);

    $result = student_profile_get_grade_percentages_for_student($student);
    $html = $result['grade_counts']['D']['courses_html'];

    $this->assertSame('MATH 1013 D (202140)', strip_tags($html));
    $this->assertStringContainsString("class='grade-perc-course-row'", $html);
  }

  private function createCourse(string $subject, string $number, string $grade, string $termId): Course {
    $course = new Course(0, FALSE, NULL, TRUE);
    $course->subject_id = $subject;
    $course->course_num = $number;
    $course->grade = $grade;
    $course->term_id = $termId;
    return $course;
  }
}
