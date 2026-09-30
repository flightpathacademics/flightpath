<?php

/**
 * Integration tests for the AdvisingScreen display layer.
 *
 * These tests sit one level above the FlightPath integration tests.
 *
 * FlightPathTest verifies that FlightPath calculates the student's advising
 * state correctly: degree assignments, semester assignments, substitutions,
 * transfer equivalencies, and unassignments.
 *
 * AdvisingScreen takes that calculated state and turns it into the sections
 * and course rows that make up the browser's degree-plan page. AdvisingScreen
 * also contains business logic, particularly around deciding whether a
 * course has already been displayed.
 *
 * These tests therefore use the real FlightPath classes and the real test
 * database. They do not mock Student, DegreePlan, Course, or the database.
 *
 * The tests deliberately avoid comparing the entire generated HTML page.
 * HTML markup and CSS can change without changing the underlying advising
 * behavior. Instead, the tests protect important, observable pieces of the
 * AdvisingScreen workflow.
 */
class AdvisingScreenTest extends FlightPathTestCase
{
  /**
   * Build the fully calculated FlightPath state used by these integration
   * tests.
   *
   * This follows the important part of the normal advising workflow:
   *
   * - Load the COSC degree for the synthetic test student.
   * - Load student substitutions.
   * - Load student unassignments.
   * - Flag outdated substitutions.
   * - Assign courses to semesters.
   * - Assign courses to degree requirements/groups.
   *
   * AdvisingScreen expects this calculated state to already exist. Keeping
   * the setup here also means the AdvisingScreen tests exercise the same
   * advising state protected by FlightPathTest.
   */
  protected function buildAdvisingFlightPath(): FlightPath
  {
    $GLOBALS["fp_advising"] = [
      "advising_major_code" => "COSC",
      "advising_track_degree_ids" => "",
      "advising_student_id" => "999999999",
      "advising_term_id" => "",
      "available_advising_term_ids" => "",
      "advising_what_if" => "no",
      "what_if_major_code" => "",
      "what_if_track_degree_ids" => "",
      "what_if_catalog_year" => "",
      "advising_update_student_settings_flag" => "",
    ];

    $fp = new FlightPath();
    $fp->init();

    $student = $fp->student;
    $student->load_student();
    $student->load_student_substitutions();
    $student->load_unassignments();

    $fp->student = $student;

    $fp->flag_outdated_substitutions();
    $fp->assign_courses_to_semesters();
    $fp->assign_courses_to_groups();

    return $fp;
  }


  /**
   * Verify that AdvisingScreen receives the FlightPath's degree plan and
   * student when constructed with a FlightPath object.
   *
   * This is the basic connection between the calculation layer and the
   * display layer. AdvisingScreen's constructor is responsible for taking
   * these objects from FlightPath so that its display methods operate on
   * the same calculated advising state.
   */
  public function testAdvisingScreenUsesFlightPathState()
  {
    $fp = $this->buildAdvisingFlightPath();

    $screen = new AdvisingScreen("", $fp);

    $this->assertSame($fp, $screen->flightpath);
    $this->assertSame($fp->student, $screen->student);
    $this->assertSame($fp->degree_plan, $screen->degree_plan);
  }


  /**
   * Verify that build_screen_elements() creates the semester sections for
   * the calculated degree plan.
   *
   * build_semester_list() walks the DegreePlan's semesters and adds each
   * rendered semester to box_array using a key such as SEMESTER_0.
   *
   * The test degree has five semesters, so the first four normal semesters
   * should be represented by the corresponding screen sections. The fifth
   * semester in the test data is the special "courses added by advisor"
   * semester and is handled separately by build_added_courses().
   */
  public function testBuildScreenElementsCreatesSemesterSections()
  {
    $fp = $this->buildAdvisingFlightPath();

    $screen = new AdvisingScreen("", $fp);
    $screen->view = "year";
    $screen->build_screen_elements();

    $this->assertArrayHasKey("SEMESTER_0", $screen->box_array);
    $this->assertArrayHasKey("SEMESTER_1", $screen->box_array);
    $this->assertArrayHasKey("SEMESTER_2", $screen->box_array);
    $this->assertArrayHasKey("SEMESTER_3", $screen->box_array);
  }


  /**
   * Verify that a semester section contains the courses assigned to that
   * semester by FlightPath.
   *
   * CSCI 2000 is assigned by the FlightPath calculation to semester 0 for
   * the test COSC degree. AdvisingScreen should therefore include CSCI 2000
   * in the rendered Freshman Year section.
   *
   * This tests the important handoff from calculated DegreePlan state to
   * rendered semester content without asserting the complete HTML structure.
   */
  public function testSemesterDisplayContainsCalculatedCourse()
  {
    $fp = $this->buildAdvisingFlightPath();

    $screen = new AdvisingScreen("", $fp);
    $screen->view = "year";
    $screen->build_screen_elements();

    $freshman = $screen->box_array["SEMESTER_0"];

    $this->assertStringContainsString("Freshman Year", $freshman);
    $this->assertStringContainsString("CSCI", $freshman);
    $this->assertStringContainsString("2000", $freshman);
  }


  /**
   * Verify that rendering a semester marks a displayed course as having
   * been displayed for the degree.
   *
   * AdvisingScreen uses this state as part of its display logic. For
   * example, later sections such as Transfer Credit and other group
   * processing need to know whether a course has already appeared on the
   * degree plan so that it can be represented appropriately instead of
   * simply being displayed a second time.
   *
   * CSCI 2000 provides a simple representative course because FlightPath
   * assigns it directly to the first semester of the COSC degree.
   */
  public function testDisplayingSemesterMarksCourseAsDisplayed()
  {
    $fp = $this->buildAdvisingFlightPath();

    $course = $fp->student->list_courses_taken->find_specific_course(264383, 201040, false, true, null, 5450264);

    $this->assertNotFalse($course);
    $this->assertNull($course->get_has_been_displayed(5450264));

    $screen = new AdvisingScreen("", $fp);
    $screen->view = "year";
    $screen->build_screen_elements();

    $this->assertTrue($course->get_has_been_displayed(5450264));
  }


  /**
   * Verify that a transfer course which has already appeared in a semester
   * is also represented in the Transfer Credit section.
   *
   * AdvisingScreen deliberately checks the course's has-been-displayed state
   * when building the transfer section. A transfer course that was already
   * shown elsewhere receives the appropriate footnote treatment rather than
   * simply being treated as an unrelated second course.
   *
   * The test fixture contains transfer record 42946, which is equivalent to
   * local course 988445 (CSCI 3010).
   */
  public function testTransferCreditSectionContainsTransferCourse()
  {
    $fp = $this->buildAdvisingFlightPath();

    $screen = new AdvisingScreen("", $fp);
    $screen->view = "year";
    $screen->build_screen_elements();

    $this->assertArrayHasKey("TRANSFER_CREDIT", $screen->box_array);

    $transfer_html = $screen->box_array["TRANSFER_CREDIT"];

    $this->assertStringContainsString("Transfer Credit", $transfer_html);
    $this->assertStringContainsString("CIS", $transfer_html);
    $this->assertStringContainsString("371", $transfer_html);
  }


  /**
   * Verify that student-specific unassignments become "Moved Courses"
   * information on the degree page.
   *
   * AdvisingScreen's footnote/messaging logic examines each student's
   * group_list_unassigned collection. When courses have been manually
   * removed from their original requirement groups, the resulting degree
   * page contains a Moved Courses section explaining what was moved.
   *
   * The test fixture gives ART 1001 two unassigned groups and BIOL 1020 one,
   * so both courses should appear in this section.
   */
  public function testFootnotesContainMovedCourseInformation()
  {
    $fp = $this->buildAdvisingFlightPath();

    $screen = new AdvisingScreen("", $fp);
    $screen->view = "year";
    $screen->build_screen_elements();

    $this->assertArrayHasKey("FOOTNOTES", $screen->box_array);

    $footnotes = $screen->box_array["FOOTNOTES"];

    $this->assertStringContainsString("Moved Courses", $footnotes);
    $this->assertStringContainsString("ART 1001", $footnotes);
    $this->assertStringContainsString("BIOL 1020", $footnotes);
  }


  public function testDisplayScreenContainsRepresentativeDegreeContent()
  {
    $fp = $this->buildAdvisingFlightPath();

    $screen = new AdvisingScreen("", $fp);
    $screen->view = "year";
    $screen->build_screen_elements();

    $html = $screen->display_screen();

    $this->assertStringContainsString("Freshman Year", $html);
    $this->assertStringContainsString("Sophomore Year", $html);
    $this->assertStringContainsString("Junior Year", $html);
    $this->assertStringContainsString("Senior Year", $html);

    $this->assertStringContainsString("Transfer Credit", $html);
    $this->assertStringContainsString("Excess Credits", $html);
    $this->assertStringContainsString("Test Scores", $html);
    $this->assertStringContainsString("Moved Courses", $html);
  }


  /**
   * Verify that standardized test scores are rendered when the student has
   * test-score records.
   *
   * The test fixture contains five ACT scores for student 999999999:
   *
   *   English = 20
   *   Math = 34
   *   Reading = 24
   *   Science Reasoning = 27
   *   A05 = 27
   *
   * AdvisingScreen groups these records into the Test Scores section.
   */
  public function testTestScoresSectionContainsStudentScores()
  {
    $fp = $this->buildAdvisingFlightPath();

    $this->assertFalse($fp->student->list_standardized_tests->is_empty);

    $screen = new AdvisingScreen("", $fp);
    $screen->view = "year";
    $screen->build_screen_elements();

    $this->assertArrayHasKey("TEST_SCORES", $screen->box_array);

    $test_scores_html = $screen->box_array["TEST_SCORES"];

    $this->assertStringContainsString("Test Scores", $test_scores_html);
    $this->assertStringContainsString("ACT", $test_scores_html);
    $this->assertStringContainsString("20", $test_scores_html);
    $this->assertStringContainsString("34", $test_scores_html);
    $this->assertStringContainsString("24", $test_scores_html);
    $this->assertStringContainsString("27", $test_scores_html);
  }


  /**
   * Verify that courses which FlightPath did not use for a degree
   * requirement are placed into the Excess Credits section.
   *
   * This is important because AdvisingScreen does not simply display every
   * course that was loaded. It specifically filters out courses that have
   * already been displayed, transfer courses, substitutions, and certain
   * graduate courses before deciding what belongs in Excess Credits.
   *
   * ART 1001 and BIOL 1020 are useful representatives because the fixture
   * explicitly gives them student-level unassignments and they remain
   * outside the normal assigned degree-plan course display.
   */
  public function testExcessCreditSectionContainsUnassignedCourse()
  {
    $fp = $this->buildAdvisingFlightPath();

    $screen = new AdvisingScreen("", $fp);
    $screen->view = "year";
    $screen->build_screen_elements();

    $this->assertArrayHasKey("EXCESS_CREDIT", $screen->box_array);

    $excess_html = $screen->box_array["EXCESS_CREDIT"];

    $this->assertStringContainsString("Excess Credits", $excess_html);

    $this->assertStringContainsString("ART", $excess_html);
    $this->assertStringContainsString("1001", $excess_html);
  }



  /**
   * Verify that a course used as a substitution is actually rendered
   * in the degree-plan requirement it fulfills.
   *
   * The fixture has BIOL 1003 substituted for CSCI 2026. FlightPath
   * establishes the substitution relationship, while AdvisingScreen is
   * responsible for displaying the fulfilled course in the appropriate
   * degree-plan group.
   */
  public function testSemesterDisplayContainsSubstitutedCourse()
  {
    $fp = $this->buildAdvisingFlightPath();

    $screen = new AdvisingScreen("", $fp);
    $screen->view = "year";
    $screen->build_screen_elements();

    $freshman = $screen->box_array["SEMESTER_0"];

    $this->assertStringContainsString("BIOL", $freshman);
    $this->assertStringContainsString("1003", $freshman);
  }


  /**
   * Verify that a course rendered as degree-plan fulfillment is not
   * subsequently shown again in the Excess Credits section.
   *
   * This protects the display-state mechanism that prevents a course
   * from appearing both in its requirement and again as excess credit.
   */
  public function testDisplayedCourseIsNotShownAsExcessCredit()
  {
    $fp = $this->buildAdvisingFlightPath();

    $screen = new AdvisingScreen("", $fp);
    $screen->view = "year";
    $screen->build_screen_elements();

    $this->assertArrayHasKey("EXCESS_CREDIT", $screen->box_array);

    $excess = $screen->box_array["EXCESS_CREDIT"];

    $this->assertStringNotContainsString("CSCI", $excess);
    $this->assertStringNotContainsString("2000", $excess);
  }


  public function testProgressBoxesDisplayExpectedValues()
  {
    $fp = $this->buildAdvisingFlightPath();

    $screen = new AdvisingScreen("", $fp);
    $screen->view = "year";
    $screen->build_screen_elements();

    // Render the complete screen so courses fulfilled through groups are marked
    // as displayed before the progress calculation is performed.
    $output = $screen->display_screen();

    $this->assertStringContainsString("Major Requirements", $output);
    $this->assertStringContainsString("42%", $output);
    $this->assertStringContainsString("Complete", $output);
    $this->assertStringContainsString("17.65", $output);
    $this->assertStringContainsString("Degree Progress", $output);
    $this->assertStringContainsString("41%", $output);
    $this->assertStringContainsString("48.65", $output);
    $this->assertStringContainsString("118", $output);
  }


}





///