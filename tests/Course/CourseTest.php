<?php

/**
 * Tests the basic behavior and state management of the Course class.
 */
require_once __DIR__ . '/../bootstrap.php';

class CourseTest extends FlightPathTestCase
{
  /**
   * A new Course should have its expected default state.
   */
  public function testNewCourseHasExpectedDefaults(): void
  {
    $course = new Course();

    $this->assertSame(0, $course->course_id); // new Course starts with course_id 0
    $this->assertSame(-1, $course->assigned_to_semester_num); // not assigned to a semester
    $this->assertSame("eligible", $course->display_status); // new courses start as eligible
    $this->assertSame(-1, $course->advised_hours); // no advising hours have been assigned
    $this->assertNull($course->bool_transfer); // transfer state is unset until explicitly assigned
    $this->assertFalse($course->bool_added_course); // course was not manually added
    $this->assertNull($course->bool_ghost_hour); // should be unset (null) until explicitly assigned

    $this->assertIsArray($course->assigned_to_group_ids_array); // group assignments are stored in an array
    $this->assertIsArray($course->assigned_to_degree_ids_array); // degree assignments are stored in an array
    $this->assertIsArray($course->details_by_degree_array); // degree-specific details are stored in an array
  }

  /**
   * A blank Course should stop initialization before normal course setup.
   */
  public function testBlankCourseStopsInitialization(): void
  {
    $course = new Course("", false, null, true);

    $this->assertSame(0, $course->course_id); // blank Course retains the default course_id
    $this->assertSame(0, $course->random_id); // blank Course does not receive a random ID
    $this->assertSame(-1, $course->advised_hours); // blank Course retains the default advising hours
  }

  /**
   * A real course should load its catalog information from the database.
   */
  public function testCourseLoadsFromDatabase(): void
  {
    $course = new Course(181405, false, null, false, 2020);

    $this->assertSame(181405, (int) $course->course_id); // requested course ID should be loaded
    $this->assertSame("FINA", $course->subject_id); // subject should come from the database
    $this->assertSame("2003", $course->course_num); // course number should come from the database
    $this->assertSame(2020, (int) $course->catalog_year); // catalog year should be preserved
    $this->assertSame("Principles of Real Estate", $course->title); // title should come from the database
    $this->assertSame(3.0, (float) $course->min_hours); // minimum hours should come from the database
    $this->assertSame(3.0, (float) $course->max_hours); // maximum hours should come from the database
    $this->assertSame(3.0, (float) $course->repeat_hours); // repeat hours should come from the database
  }

  /**
   * Degree-specific values should remain separate for different degrees.
   */
  public function testDegreeSpecificDetailsAreIndependent(): void
  {
    $course = new Course();

    $course->set_hours_awarded(101, 3); // assign 3 hours to degree 101
    $course->set_hours_awarded(202, 4); // assign 4 hours to degree 202

    $this->assertSame(3.0, $course->get_hours_awarded(101)); // degree 101 should have its own value
    $this->assertSame(4.0, $course->get_hours_awarded(202)); // degree 202 should have its own value
    $this->assertSame(3.0, $course->get_hours_awarded(303)); // non-existent degree ID falls back to the first stored value
  }

  /**
   * Hours awarded should be stored as a numeric value.
   */
  public function testHoursAwardedAreStoredAsNumbers(): void
  {
    $course = new Course();

    $course->set_hours_awarded(101, "3.50"); // provide hours as a string, as database values often are

    $this->assertSame(3.5, $course->get_hours_awarded(101)); // hours should be returned as a float
  }

  /**
   * Substitution state should be tracked independently by degree.
   */
  public function testSubstitutionStateIsDegreeSpecific(): void
  {
    $course = new Course();

    $course->set_bool_substitution(101, true); // mark the course as a substitution for degree 101
    $course->set_bool_substitution(202, false); // explicitly disable substitution for degree 202

    $this->assertTrue($course->get_bool_substitution(101)); // degree 101 should return true
    $this->assertFalse($course->get_bool_substitution(202)); // degree 202 should return false
    $this->assertTrue($course->get_bool_substitution(-1)); // no specific degree should find the existing true value
  }

  /**
   * Exclude-repeat state should be tracked independently by degree.
   */
  public function testExcludeRepeatStateIsDegreeSpecific(): void
  {
    $course = new Course();

    $course->set_bool_exclude_repeat(101, true); // mark the course as excluded from repeats for degree 101

    $this->assertTrue($course->get_bool_exclude_repeat(101)); // degree 101 should return true
    $this->assertNull($course->get_bool_exclude_repeat(202)); // non-existent degree ID has no stored value
    $this->assertTrue($course->get_bool_exclude_repeat(-1)); // no specific degree should find the existing true value
  }

  /**
   * Course data should survive a serialization round trip.
   */
  public function testDataStringRoundTripPreservesCourseState(): void
  {
    // Load a real course from the test database.
    $course = new Course(181405, false, null, false, 2020);

    $this->assertSame("FINA", $course->subject_id); // confirm the expected test course was loaded
    $this->assertSame("2003", $course->course_num); // confirm the expected course number was loaded
    $this->assertSame("Principles of Real Estate", $course->title); // confirm the expected course was loaded

    // Add state that is stored by Course's serialization format.
    $course->assigned_to_semester_num = 2;
    $course->bool_advised_to_take = true;
    $course->specified_repeats = 2;
    $course->bool_specified_repeat = true;
    $course->grade = "A+";
    $course->set_hours_awarded(101, 3);
    $course->term_id = 202610;
    $course->advised_hours = 3;
    $course->bool_added_course = true;
    $course->db_advised_courses_id = 555;
    $course->min_hours = 3;
    $course->max_hours = 4;
    $course->set_bool_substitution(101, true);
    $course->set_bool_substitution_new_from_split(101, true);
    $course->set_bool_substitution_split(101, false);
    $course->display_status = "fulfilled";
    $course->bool_ghost_hour = true;
    $course->req_by_degree_id = 101;
    $course->disp_for_group_id = "GROUP1";
    $course->school_id = 7;
    $course->db_grade = "A+";
    $course->extra_attribs = "test";
    $course->unique_id = "course-test-123";

    $data = $course->to_data_string(); // serialize the Course into FlightPath's data string format

    $restored = new Course();
    $restored->load_course_from_data_string($data); // restore the Course from the serialized data

    $this->assertSame(181405, (int) $restored->course_id); // course ID should survive serialization
    $this->assertSame("FINA", $restored->subject_id); // subject should survive serialization
    $this->assertSame("2003", $restored->course_num); // course number should survive serialization
    $this->assertSame("Principles of Real Estate", $restored->title); // title should survive serialization

    $this->assertSame(2, (int) $restored->assigned_to_semester_num); // semester assignment should survive serialization
    $this->assertTrue($restored->bool_advised_to_take); // advising flag should survive serialization
    $this->assertSame(2, (int) $restored->specified_repeats); // repeat count should survive serialization
    $this->assertTrue($restored->bool_specified_repeat); // specified-repeat flag should survive serialization
    $this->assertSame("A+", $restored->grade); // grade should survive serialization
    $this->assertSame(3.0, $restored->get_hours_awarded(101)); // degree-specific hours should survive serialization
    $this->assertSame(202610, (int) $restored->term_id); // term ID should survive serialization
    $this->assertSame(3.0, $restored->advised_hours); // advised hours should survive serialization
    $this->assertTrue($restored->bool_added_course); // added-course flag should survive serialization
    $this->assertSame(555, (int) $restored->db_advised_courses_id); // database advised-course ID should survive serialization
    $this->assertSame(3.0, $restored->min_hours); // minimum hours should survive serialization
    $this->assertSame(4.0, $restored->max_hours); // maximum hours should survive serialization
    $this->assertTrue((bool) $restored->get_bool_substitution(101)); // substitution state should survive serialization
    $this->assertTrue((bool) $restored->get_bool_substitution_new_from_split(101)); // split-created substitution state should survive serialization
    $this->assertFalse((bool) $restored->get_bool_substitution_split(101)); // split substitution state should survive serialization
    $this->assertSame("fulfilled", $restored->display_status); // display status should survive serialization
    $this->assertTrue($restored->bool_ghost_hour); // ghost-hour flag should survive serialization
    $this->assertSame(101, (int) $restored->req_by_degree_id); // required-by degree ID should survive serialization
    $this->assertSame("GROUP1", $restored->disp_for_group_id); // group ID should survive serialization
    $this->assertSame("7", $restored->school_id); // school ID should survive serialization
    $this->assertSame("A+", $restored->db_grade); // database grade should survive serialization
    $this->assertSame("test", $restored->extra_attribs); // extra attributes should survive serialization
    $this->assertSame("course-test-123", $restored->unique_id); // unique ID should survive serialization
  }
}