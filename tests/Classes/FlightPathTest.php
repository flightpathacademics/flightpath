<?php

/**
 * Integration tests for the core FlightPath advising workflow.
 *
 * These tests intentionally exercise FlightPath with the real test database
 * and real FlightPath classes rather than mocking Student, DegreePlan,
 * Course, or the database layer.
 *
 * The goal here is not to test every individual method in FlightPath.
 * Those lower-level behaviors are covered by the class-specific tests.
 * Instead, these tests protect the important interactions that occur when
 * FlightPath builds an advising result:
 *
 *   1. Load the selected student and degree plan.
 *   2. Load substitutions and unassignments.
 *   3. Flag outdated substitutions.
 *   4. Assign taken courses to semesters.
 *   5. Assign taken courses to degree requirements/groups.
 *
 * The assertions below deliberately focus on observable advising state:
 * which degree was loaded, where representative courses were assigned,
 * how transfer equivalencies are represented, how substitutions affect
 * courses, and whether manual unassignments survive advising processing.
 *
 * AdvisingScreen is intentionally not exercised here. It adds another layer
 * of display and business logic, including display-state tracking. That
 * behavior will be characterized separately so that these tests remain
 * focused on the FlightPath advising/calculation layer.
 *
 * The test fixture uses synthetic data only. Student 999999999 and the
 * COSC degree data referenced below exist specifically for automated tests.
 */
class FlightPathTest extends FlightPathTestCase
{
  /**
   * Build a FlightPath object through the same major advising-processing
   * stages needed by these integration tests.
   *
   * FlightPath::init() loads the student and degree plan from the advising
   * context. We then explicitly load substitutions and unassignments because
   * those loaders are normally invoked by the higher-level advising screen
   * workflow. Finally, we run the core assignment stages that turn the
   * student's raw academic record into advising state.
   *
   * Keeping this setup in one helper is intentional: every test below should
   * exercise the same advising pipeline rather than quietly testing a
   * different subset of initialization steps.
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
   * Verify that the advising context selects the expected test student,
   * degree, and catalog year.
   *
   * This protects the basic inputs to the advising calculation. If the
   * wrong degree or catalog year is loaded, later course-assignment tests
   * could still pass while producing an advising result for the wrong
   * academic program.
   */
  public function testAdvisingLoadsCorrectDegree()
  {
    $fp = $this->buildAdvisingFlightPath();

    $this->assertSame("999999999", (string) $fp->student->student_id);
    $this->assertSame(5450264, (int) $fp->degree_plan->degree_id);
    $this->assertSame(2020, (int) $fp->degree_plan->catalog_year);
  }


  /**
   * Verify several representative forms of course-to-degree assignment.
   *
   * The three courses intentionally cover different assignment outcomes:
   *
   * - A course assigned directly to the first semester.
   * - A course assigned directly to a later semester.
   * - A course satisfying a degree requirement group rather than a specific
   *   semester.
   *
   * Together these assertions provide a small characterization of the
   * semester/group assignment logic without trying to assert the entire
   * advising result course-by-course.
   */
  public function testAdvisingAssignsRepresentativeCourses()
  {
    $fp = $this->buildAdvisingFlightPath();
    $courses = $fp->student->list_courses_taken;

    // Direct course assignment to the first semester.
    $course = $courses->find_specific_course(264383, 201040, false, true, null, 5450264);
    $this->assertNotFalse($course);
    $this->assertSame(0, (int) $course->assigned_to_semester_num);
    $this->assertSame(5450264, (int) $course->assigned_to_degree_ids_array[5450264]);

    // A course assigned to a later semester.
    $course = $courses->find_specific_course(509710, 201240, false, true, null, 5450264);
    $this->assertNotFalse($course);
    $this->assertSame(2, (int) $course->assigned_to_semester_num);
    $this->assertSame(5450264, (int) $course->assigned_to_degree_ids_array[5450264]);

    // A course assigned to a group rather than a specific semester.
    $course = $courses->find_specific_course(4926, 201040, false, true, null, 5450264);
    $this->assertNotFalse($course);
    $this->assertSame(5450264, (int) $course->assigned_to_degree_ids_array[5450264]);
    $this->assertContains("4992826_5450264", $course->assigned_to_group_ids_array);
  }


  /**
   * Verify that a transfer course is converted to its student-specific
   * local equivalency and then participates in normal degree assignment.
   *
   * The fixture represents transfer course 42946 as equivalent to local
   * course 988445 (CSCI 3010). The important integration path is:
   *
   *   transfer record -> local equivalency -> Course object ->
   *   degree/semester assignment
   *
   * The lookup intentionally identifies the course by its transfer-course
   * metadata rather than using find_specific_course() with the local course
   * ID, because that method's transfer mode compares against the underlying
   * transfer course ID.
   */
  public function testAdvisingProcessesTransferEquivalency()
  {
    $fp = $this->buildAdvisingFlightPath();
    $courses = $fp->student->list_courses_taken;

    $transfer_course = false;

    for ($i = 0; $i < $courses->get_size(); $i++) {
      $course = $courses->get_element($i);

      if (
          $course->bool_transfer == true
          && is_object($course->course_transfer)
          && (int) $course->course_transfer->course_id === 42946
          ) {
            $transfer_course = $course;
            break;
          }
    }

    $this->assertNotFalse($transfer_course);
    $this->assertSame(988445, (int) $transfer_course->course_id);
    $this->assertSame("CSCI", $transfer_course->subject_id);
    $this->assertSame("3010", $transfer_course->course_num);
    $this->assertSame("201660", (string) $transfer_course->term_id);
    $this->assertSame(1, (int) $transfer_course->assigned_to_semester_num);
    $this->assertContains(5450264, $transfer_course->assigned_to_degree_ids_array);
  }

  /**
   * Verify that substitutions are loaded and applied to the advising result.
   *
   * The fixture contains four substitutions, all belonging to the COSC
   * degree, and none should be marked outdated during this test.
   *
   * This test also protects two important substitution behaviors:
   *
   * - A full-course substitution marks the replacement course as a
   *   substitution for the degree.
   * - A split substitution preserves any hours that were not consumed by
   *   the substitution. In the fixture, the original two-credit CHEM 1007
   *   course is split into a 0.65-hour substituted course and a 0.35-hour
   *   remainder. A second two-hour substitution also exists, so the final
   *   advising state contains two substituted CHEM 1007 records plus the
   *   0.35-hour remainder.
   *
   * The remainder assertion is particularly important because losing those
   * hours would silently change the student's degree-audit result.
   */
  public function testAdvisingProcessesSubstitutions()
  {
    $fp = $this->buildAdvisingFlightPath();
    $student = $fp->student;

    $this->assertSame(4, $student->list_substitutions->get_size());

    for ($i = 0; $i < $student->list_substitutions->get_size(); $i++) {
      $substitution = $student->list_substitutions->get_element($i);

      $this->assertSame(5450264, (int) $substitution->db_required_degree_id);
      $this->assertFalse($substitution->bool_outdated);
    }

    // BIOL 1003 is the full-course substitution.
    $course = $student->list_courses_taken->find_specific_course(580574, 201060, false, false, null, 5450264);

    $this->assertNotFalse($course);
    $this->assertTrue($course->get_bool_substitution(5450264));


    // CHEM 1007 was split by two substitutions.  The original
    // two-credit course becomes .65 substituted + .35 remaining.
    $chem_courses = [];

    for ($i = 0; $i < $student->list_courses_taken->get_size(); $i++) {
      $course = $student->list_courses_taken->get_element($i);

      if ($course->course_id == 895323 && $course->term_id == 200940) {
        $chem_courses[] = $course;
      }
    }

    $this->assertCount(3, $chem_courses);

    $substitution_hours = [];
    $remaining_hours = [];

    foreach ($chem_courses as $course) {
      if ($course->get_bool_substitution(5450264)) {
        $substitution_hours[] = (float) $course->get_hours_awarded(5450264);
      }
      else {
        $remaining_hours[] = (float) $course->get_hours_awarded(5450264);
      }
    }

    sort($substitution_hours);
    sort($remaining_hours);

    $this->assertEquals([0.65, 2.0], $substitution_hours);
    $this->assertEquals([0.35], $remaining_hours);
  }


  /**
   * Verify that manually unassigned requirement groups remain attached to
   * their courses after the advising assignment process runs.
   *
   * ART 1001 has two unassigned groups in the fixture, while BIOL 1020 has
   * one. These represent student-specific advising state that must survive
   * the normal semester/group assignment workflow.
   *
   * This test checks both the group identifiers and their database record
   * IDs so that a regression that drops, merges, or replaces an unassignment
   * is visible.
   */
  public function testAdvisingPreservesUnassignments()
  {
    $fp = $this->buildAdvisingFlightPath();
    $courses = $fp->student->list_courses_taken;

    $course = $courses->find_specific_course(962172, 200860, false, true, null, 5450264);

    $this->assertNotFalse($course);
    $this->assertSame(2, $course->group_list_unassigned->get_size());

    $groups = [];

    for ($i = 0; $i < $course->group_list_unassigned->get_size(); $i++) {
      $group = $course->group_list_unassigned->get_element($i);
      $groups[$group->group_id] = (int) $group->db_unassign_group_id;
    }

    $this->assertSame(2, $groups["5187361_5450264"]);
    $this->assertSame(3, $groups["4992826_5450264"]);

    $course = $courses->find_specific_course(479538, 200940, false, true, null, 5450264);

    $this->assertNotFalse($course);
    $this->assertSame(1, $course->group_list_unassigned->get_size());

    $group = $course->group_list_unassigned->get_first();

    $this->assertSame("0", (string) $group->group_id);
    $this->assertSame(4, (int) $group->db_unassign_group_id);
  }



  public function testAdvisingCalculatesExpectedProgressHours()
  {
    $fp = $this->buildAdvisingFlightPath();
    $degree_plan = $fp->degree_plan;

    // Build the advising screen before calculating progress because group
    // fulfillment requires fulfilled courses to have been marked as displayed.
    $screen = new AdvisingScreen("", $fp);
    $screen->view = "year";
    $screen->build_screen_elements();

    $degree_plan->calculate_progress_hours(FALSE, ["c" => "Core Requirements", "m" => "Major Requirements", "degree" => "Degree Progress"]);

    // Major requirements.
    $this->assertSame(42.0, (float) $degree_plan->gpa_calculations[0]["m"]["total_hours"]);
    $this->assertSame(17.65, (float) $degree_plan->gpa_calculations[0]["m"]["fulfilled_hours"]);

    // Overall degree progress.
    $this->assertSame(118.0, (float) $degree_plan->gpa_calculations[0]["degree"]["total_hours"]);
    $this->assertSame(48.65, (float) $degree_plan->gpa_calculations[0]["degree"]["fulfilled_hours"]);
  }





}










//