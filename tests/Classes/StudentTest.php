<?php


class StudentTest extends FlightPathTestCase
{
  /**
   * Verifies that a new Student initializes its course, test, substitution,
   * settings, and significant-course collections with the expected defaults.
   */
  public function testNewStudentHasExpectedDefaults()
  {
    $student = new Student();

    $this->assertSame("", $student->student_id);
    $this->assertInstanceOf(CourseList::class, $student->list_courses_taken);
    $this->assertInstanceOf(CourseList::class, $student->list_courses_added);
    $this->assertInstanceOf(CourseList::class, $student->list_transfer_eqvs_unassigned);
    $this->assertInstanceOf(SubstitutionList::class, $student->list_substitutions);
    $this->assertInstanceOf(ObjList::class, $student->list_standardized_tests);
    $this->assertSame([], $student->array_settings);
    $this->assertSame([], $student->array_significant_courses);
  }

  /**
   * Verifies that loading a real student populates the student's descriptive
   * data, academic standing, major information, and school information.
   */
  public function testStudentLoadsFromDatabase()
  {
    $student = new Student("999999999");

    $this->assertSame("999999999", $student->student_id);
    $this->assertSame("Test Student", $student->name);
    $this->assertSame(1, (int) $student->is_active);
    $this->assertSame(2020, (int) $student->catalog_year);
    $this->assertSame(0, (int) $student->school_id);
    $this->assertNotEmpty($student->major_code_array);
    $this->assertNotSame("", $student->major_code_csv);
  }

  /**
   * Verifies that the student's completed courses are loaded as Course objects
   * with their historical term, grade, awarded hours, and completion status.
   */
  public function testStudentLoadsCoursesTaken()
  {
    $student = new Student("999999999");

    $this->assertFalse($student->list_courses_taken->is_empty);
  }

  /**
   * Verifies that the student's transfer course is loaded and retains its
   * transfer-course information and local equivalency.
   */
  public function testStudentLoadsTransferCourseAndEquivalency()
  {
    $student = new Student("999999999");

    $transfer_course = $student->list_courses_taken->find_specific_course(42946, 201660, true, true);

    $this->assertNotFalse($transfer_course);
    $this->assertTrue($transfer_course->bool_transfer);
    $this->assertInstanceOf(Course::class, $transfer_course->course_transfer);
    $this->assertSame(42946, (int) $transfer_course->course_transfer->course_id);
    $this->assertSame(988445, (int) $transfer_course->course_id);
    $this->assertSame("B", $transfer_course->grade);
    $this->assertSame(3.0, (float) $transfer_course->get_hours_awarded());
    $this->assertTrue($transfer_course->bool_taken);
  }

  /**
   * Verifies that courses loaded for the student are recorded in the
   * significant-course lookup used later by FlightPath's course assignment.
   */
  public function testStudentBuildsSignificantCourseList()
  {
    $student = new Student("999999999");

    $this->assertArrayHasKey(988445, $student->array_significant_courses);
    $this->assertTrue($student->array_significant_courses[988445]);
  }

  /**
   * Verifies that get_best_grade_for_course() finds the student's best grade
   * for a course already present in the student's course history.
   */
  public function testGetBestGradeForCourse()
  {
    $student = new Student("999999999");

    $course = new Course();
    $course->course_id = 988445;

    $this->assertSame("B", $student->get_best_grade_for_course($course));
  }

  /**
   * Verifies that get_transfer_course_eqv() resolves the student's transfer
   * course to its local equivalent.
   */
  public function testGetTransferCourseEqvReturnsLocalCourse()
  {
    $student = new Student("999999999");

    $this->assertSame(988445, (int) $student->get_transfer_course_eqv(42946));
  }

  /**
   * Verifies that the student's active database substitutions are loaded into
   * the SubstitutionList when load_student_substitutions() is called.
   */
  public function testStudentLoadsSubstitutions()
  {
    $student = new Student("999999999");

    $student->load_student_substitutions();

    $this->assertSame(4, $student->list_substitutions->get_size());

    $substitution_ids = [];

    for ($i = 0; $i < $student->list_substitutions->get_size(); $i++) {
      $substitution = $student->list_substitutions->get_element($i);
      $substitution_ids[] = (int) $substitution->db_substitution_id;
    }

    sort($substitution_ids);

    $this->assertSame([1, 2, 3, 4], $substitution_ids);
  }


  /**
   * Verifies that substitutions loaded from the database retain their
   * requirement, replacement course, degree, group, semester, and
   * administrative metadata.
   */
  public function testStudentLoadsSubstitutionDetails()
  {
    $student = new Student("999999999");

    $student->load_student_substitutions();

    $expected = [
      1 => [
        "required_course_id" => 980488,
        "sub_course_id" => 580574,
        "hours" => 3.0,
        "group_id" => "",
        "semester_num" => 0,
        "remarks" => "I have decided to do this - RP",
        "faculty_id" => "1",
      ],
      2 => [
        "required_course_id" => 630974,
        "sub_course_id" => 895323,
        "hours" => 2.0,
        "group_id" => "",
        "semester_num" => 1,
        "remarks" => "Only subbing in 2 hours.  RP",
        "faculty_id" => "1",
      ],
      3 => [
        "required_course_id" => 982230,
        "sub_course_id" => 649084,
        "hours" => 2.0,
        "group_id" => "",
        "semester_num" => 3,
        "remarks" => "I am intentionally splitting this sub. RP",
        "faculty_id" => "1",
      ],
      4 => [
        "required_course_id" => 871048,
        "sub_course_id" => 895323,
        "hours" => 0.65,
        "group_id" => "",
        "semester_num" => 3,
        "remarks" => "Entered a fractional split - RP",
        "faculty_id" => "1",
      ],
    ];

    foreach ($expected as $substitution_id => $values) {
      $substitution = NULL;

      for ($i = 0; $i < $student->list_substitutions->get_size(); $i++) {
        $candidate = $student->list_substitutions->get_element($i);

        if ((int) $candidate->db_substitution_id === $substitution_id) {
          $substitution = $candidate;
          break;
        }
      }

      $this->assertNotNull($substitution);

      $taken_course = $substitution->course_list_substitutions->get_first();

      $this->assertSame($values["required_course_id"], (int) $substitution->course_requirement->course_id);
      $this->assertSame($values["sub_course_id"], (int) $taken_course->course_id);
      $this->assertSame($values["hours"], (float) $taken_course->get_substitution_hours(5450264));
      $this->assertSame(5450264, (int) $substitution->db_required_degree_id);
      $this->assertSame(5450264, (int) $substitution->assigned_to_degree_id);
      $this->assertSame($values["group_id"], (string) $substitution->db_required_group_id);
      $this->assertSame($values["semester_num"], (int) $substitution->course_requirement->assigned_to_semester_num);
      $this->assertSame($values["semester_num"], (int) $taken_course->assigned_to_semester_num);
      $this->assertSame($values["remarks"], $substitution->remarks);
      $this->assertSame($values["faculty_id"], (string) $substitution->faculty_id);
      $this->assertFalse($substitution->bool_group_addition);
    }
  }


  /**
   * Verifies that courses used in substitutions are marked as substitutions
   * and retain the database substitution ID associated with the degree.
   */
  public function testStudentMarksSubstitutedCourses()
  {
    $student = new Student("999999999");

    $student->load_student_substitutions();

    $this->assertSame(4, $student->list_substitutions->get_size());

    for ($i = 0; $i < $student->list_substitutions->get_size(); $i++) {
      $substitution = $student->list_substitutions->get_element($i);
      $taken_course = $substitution->course_list_substitutions->get_first();

      $this->assertTrue($taken_course->get_bool_substitution(5450264));
      $this->assertSame("completed", $taken_course->display_status);
      $this->assertSame(
          (int) $substitution->db_substitution_id,
          (int) $taken_course->db_substitution_id_array[5450264]
          );
      $this->assertSame(
          (float) $substitution->course_list_substitutions->get_first()->get_substitution_hours(5450264),
          (float) $taken_course->get_substitution_hours(5450264)
          );
    }
  }


  /**
   * Verifies that calculate_cumulative_hours_and_gpa() returns totals and GPA
   * consistent with the student's loaded course history.
   */
  public function testCalculateCumulativeHoursAndGpa()
  {
    $student = new Student("999999999");

    $result = $student->calculate_cumulative_hours_and_gpa();

    $this->assertSame(
        (float) $student->list_courses_taken->count_credit_hours("", FALSE, TRUE, FALSE),
        (float) $result["cumulative_total_hours"]
        );

    $this->assertSame(
        (float) $student->list_courses_taken->count_credit_hours("", FALSE, TRUE, TRUE),
        (float) $result["cumulative_quality_hours"]
        );

    $this->assertSame(
        (float) $student->list_courses_taken->count_credit_quality_points("", FALSE, TRUE),
        (float) $result["cumulative_quality_points"]
        );

    if ($result["cumulative_quality_hours"] > 0) {
      $expected_gpa = fp_truncate_decimals(
          $result["cumulative_quality_points"] / $result["cumulative_quality_hours"],
          3
          );

      $this->assertSame($expected_gpa, $result["cumulative_gpa"]);
    }
    else {
      $this->assertFalse($result["cumulative_gpa"]);
    }
  }

  /**
   * Verifies that get_transfer_course_eqv() returns FALSE when no equivalency
   * exists and when the local equivalent cannot satisfy the requested hours.
   */
  public function testGetTransferCourseEqvRejectsInvalidMatches()
  {
    $student = new Student("999999999");

    $this->assertFalse($student->get_transfer_course_eqv(999999999));

    $this->assertFalse($student->get_transfer_course_eqv(42946, false, "", 4));
  }

  /**
   * Verifies that load_unassignments() loads each active unassignment onto
   * the corresponding student's Course object.
   */
  public function testStudentLoadsUnassignments()
  {
    $student = new Student("999999999");

    $student->load_unassignments();

    $course = $student->list_courses_taken->find_specific_course(962172, 200860, false, true, null, 5450264);

    $this->assertNotFalse($course);
    $this->assertSame(2, $course->group_list_unassigned->get_size());

    $unassigned_groups = [];

    for ($i = 0; $i < $course->group_list_unassigned->get_size(); $i++) {
      $group = $course->group_list_unassigned->get_element($i);

      $unassigned_groups[$group->group_id] = [
        "db_id" => (int) $group->db_unassign_group_id,
        "degree_id" => (int) $group->req_by_degree_id,
      ];
    }

    $this->assertArrayHasKey("5187361_5450264", $unassigned_groups);
    $this->assertSame(2, $unassigned_groups["5187361_5450264"]["db_id"]);
    $this->assertSame(5450264, $unassigned_groups["5187361_5450264"]["degree_id"]);

    $this->assertArrayHasKey("4992826_5450264", $unassigned_groups);
    $this->assertSame(3, $unassigned_groups["4992826_5450264"]["db_id"]);
    $this->assertSame(5450264, $unassigned_groups["4992826_5450264"]["degree_id"]);

    $course = $student->list_courses_taken->find_specific_course(479538, 200940, false, true, null, 5450264);

    $this->assertNotFalse($course);
    $this->assertSame(1, $course->group_list_unassigned->get_size());

    $group = $course->group_list_unassigned->get_first();

    $this->assertSame("0", (string) $group->group_id);
    $this->assertSame(4, (int) $group->db_unassign_group_id);
    $this->assertSame(5450264, (int) $group->req_by_degree_id);
  }







}