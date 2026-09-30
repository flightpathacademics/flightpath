<?php

/**
 * Integration tests for DatabaseHandler.
 *
 * These tests use the real test MariaDB database rather than mocking the
 * database layer. The goal is to protect the database lookups that are used
 * throughout FlightPath's advising and degree-audit code.
 */
class DatabaseHandlerTest extends FlightPathTestCase
{
  /**
   * Return a fresh DatabaseHandler connected to the test database.
   */
  protected function getDatabaseHandler(): DatabaseHandler
  {
    return new DatabaseHandler();
  }


  /**
   * Verify that get_course_id() finds a known course.
   */
  public function testGetCourseIdFindsKnownCourse()
  {
    $db = $this->getDatabaseHandler();

    $course_id = $db->get_course_id("FINA", "2003", 2020, false, 0);

    $this->assertSame(181405, (int) $course_id);
  }


  /**
   * Verify that get_course_id() returns FALSE when the course does not exist.
   */
  public function testGetCourseIdReturnsFalseForUnknownCourse()
  {
    $db = $this->getDatabaseHandler();

    $course_id = $db->get_course_id("ZZZZ", "9999", 2020, false, 0);

    $this->assertFalse($course_id);
  }


  /**
   * Verify that get_course_id() ignores the portion of a course number after
   * a colon.
   *
   * FlightPath sometimes receives course numbers with additional information
   * after a colon. The database lookup should use only the portion before it.
   */
  public function testGetCourseIdIgnoresCourseNumberSuffixAfterColon()
  {
    $db = $this->getDatabaseHandler();

    $course_id = $db->get_course_id("FINA", "2003:Something", 2020, false, 0);

    $this->assertSame(181405, (int) $course_id);
  }


  /**
   * Verify that get_degree_id() finds the test COSC degree.
   */
  public function testGetDegreeIdFindsKnownDegree()
  {
    $db = $this->getDatabaseHandler();

    $degree_id = $db->get_degree_id("COSC", 2020, false, 0);

    $this->assertSame(5450264, (int) $degree_id);
  }


  /**
   * Verify that get_degree_id() handles a major/track code.
   *
   * The fixture contains COSC|_MATH specifically so track lookup is tested
   * independently of the base COSC degree.
   */
  public function testGetDegreeIdFindsDegreeTrack()
  {
    $db = $this->getDatabaseHandler();

    $degree_id = $db->get_degree_id("COSC|_MATH", 2020, false, 0);

    $this->assertSame(8030111, (int) $degree_id);
  }


  /**
   * Verify that get_degree_id() returns FALSE for an unknown degree.
   */
  public function testGetDegreeIdReturnsFalseForUnknownDegree()
  {
    $db = $this->getDatabaseHandler();

    $degree_id = $db->get_degree_id("ZZZZ", 2020, false, 0);

    $this->assertFalse($degree_id);
  }


  /**
   * Verify that get_group_id() finds a known group.
   */
  public function testGetGroupIdFindsKnownGroup()
  {
    $db = $this->getDatabaseHandler();

    $group_id = $db->get_group_id("core_english_comp", 2020, 0);

    $this->assertSame(7958642, (int) $group_id);
  }


  /**
   * Verify that get_group_name() accepts a composite group ID.
   *
   * Course/group identifiers in advising data may contain the degree ID after
   * an underscore, while the database lookup uses only the base group ID.
   */
  public function testGetGroupNameHandlesCompositeGroupId()
  {
    $db = $this->getDatabaseHandler();

    $group_name = $db->get_group_name("7958642_5450264");

    $this->assertSame("core_english_comp", $group_name);
  }


  /**
   * Verify that get_institution_name() finds a known transfer institution.
   */
  public function testGetInstitutionNameFindsKnownInstitution()
  {
    $db = $this->getDatabaseHandler();

    $institution_name = $db->get_institution_name(901582, 0);

    $this->assertNotSame("", $institution_name);
  }


  /**
   * Verify that set_variable() and get_variable() persist and retrieve a
   * value from the variables table.
   */
  public function testSetAndGetVariable()
  {
    $db = $this->getDatabaseHandler();

    $variable_name = "phpunit_database_handler_test";
    $variable_value = "test-value";

    // Ensure the test starts from a known state.
    db_query("DELETE FROM variables WHERE name = ?", $variable_name);

    $db->set_variable($variable_name, $variable_value);

    $this->assertSame($variable_value, $db->get_variable($variable_name));

    // Clean up so this test does not leave persistent test state behind.
    db_query("DELETE FROM variables WHERE name = ?", $variable_name);
  }


  /**
   * Verify that get_course_db_row() returns the current database row for a
   * known course.
   */
  public function testGetCourseDbRowFindsKnownCourse()
  {
    $db = $this->getDatabaseHandler();

    $row = $db->get_course_db_row(181405);

    $this->assertNotFalse($row);
    $this->assertSame(181405, (int) $row->course_id);
    $this->assertSame("FINA", $row->subject_id);
    $this->assertSame("2003", $row->course_num);
    $this->assertSame(2020, (int) $row->catalog_year);
  }


  /**
   * Verify that get_student_name() returns the student's formatted name.
   */
  public function testGetStudentName()
  {
    $db = $this->getDatabaseHandler();

    $name = $db->get_student_name("999999999");

    $this->assertSame("Test Student", $name);
  }


  /**
   * Verify that get_student_name() can include the student's CWID.
   */
  public function testGetStudentNameCanIncludeCwid()
  {
    $db = $this->getDatabaseHandler();

    $name = $db->get_student_name("999999999", true);

    $this->assertSame("Test Student (999999999)", $name);
  }


  /**
   * Verify that get_student_name() returns the expected fallback for an
   * unknown student.
   */
  public function testGetStudentNameReturnsUnknownForMissingStudent()
  {
    $db = $this->getDatabaseHandler();

    $name = $db->get_student_name("does-not-exist");

    $this->assertSame("Unknown Student", $name);
  }


  /**
   * Verify that get_faculty_name() returns the faculty member's formatted
   * name.
   */
  public function testGetFacultyName()
  {
    $db = $this->getDatabaseHandler();

    $name = $db->get_faculty_name("55588992");

    $this->assertSame("Lisa Tester", $name);
  }


  /**
   * Verify that get_faculty_name() can include the faculty member's CWID.
   */
  public function testGetFacultyNameCanIncludeCwid()
  {
    $db = $this->getDatabaseHandler();

    $name = $db->get_faculty_name("55588992", true);

    $this->assertSame("Lisa Tester (55588992)", $name);
  }


  /**
   * Verify that get_student_catalog_year() returns the student's catalog
   * year.
   */
  public function testGetStudentCatalogYear()
  {
    $db = $this->getDatabaseHandler();

    $catalog_year = $db->get_student_catalog_year("999999999");

    $this->assertSame(2020, (int) $catalog_year);
  }


  /**
   * Verify that get_student_cumulative_hours() returns the student's stored
   * cumulative hours.
   */
  public function testGetStudentCumulativeHours()
  {
    $db = $this->getDatabaseHandler();

    $hours = $db->get_student_cumulative_hours("9999999");

    $this->assertSame("48", (string) $hours);
  }


  /**
   * Verify that get_student_gpa() returns the student's stored GPA.
   */
  public function testGetStudentGpa()
  {
    $db = $this->getDatabaseHandler();

    $gpa = $db->get_student_gpa("9999999");

    $this->assertSame("3.9", (string) $gpa);
  }


  /**
   * Verify that get_student_rank() returns the student's rank code.
   */
  public function testGetStudentRank()
  {
    $db = $this->getDatabaseHandler();

    $rank = $db->get_student_rank("999999999");

    $this->assertSame("JR", $rank);
  }


  /**
   * Verify that get_student_majors_from_db() returns the student's major.
   */
  public function testGetStudentMajorsFromDb()
  {
    $db = $this->getDatabaseHandler();

    $majors = $db->get_student_majors_from_db("999999999");

    $this->assertSame(["COSC" => "COSC"], $majors);
  }


  /**
   * Verify that get_degrees_in_catalog_year() includes the test COSC degree.
   */
  public function testGetDegreesInCatalogYear()
  {
    $db = $this->getDatabaseHandler();

    $degrees = $db->get_degrees_in_catalog_year(2020, false, false, true, [1, 2], 0);

    $this->assertIsArray($degrees);
    $this->assertArrayHasKey("COSC", $degrees);
    $this->assertSame(5450264, (int) $degrees["COSC"]["degree_id"]);
    $this->assertSame("Computer Science", $degrees["COSC"]["title"]);
  }


  /**
   * Verify that get_degrees_in_catalog_year() can include degree tracks.
   */
  public function testGetDegreesInCatalogYearIncludesTracks()
  {
    $db = $this->getDatabaseHandler();

    $degrees = $db->get_degrees_in_catalog_year(2020, true, false, true, [1, 2, 3], 0);

    $this->assertIsArray($degrees);
    $this->assertArrayHasKey("COSC|_MATH", $degrees);
    $this->assertSame(8030111, (int) $degrees["COSC|_MATH"]["degree_id"]);
  }


  /**
   * Verify that the normal level-1/level-2 query excludes level-3 tracks.
   */
  public function testGetDegreesInCatalogYearExcludesLevelThreeTracksByDefault()
  {
    $db = $this->getDatabaseHandler();

    $degrees = $db->get_degrees_in_catalog_year(2020, true, false, true, [1, 2], 0);

    $this->assertIsArray($degrees);
    $this->assertArrayHasKey("COSC", $degrees);
    $this->assertArrayNotHasKey("COSC|_MATH", $degrees);
  }



  /**
   * Verify that get_degree_tracks() returns the tracks configured for a major.
   */
  public function testGetDegreeTracks()
  {
    $db = $this->getDatabaseHandler();

    $tracks = $db->get_degree_tracks("COSC", 2020, 0);

    $this->assertSame(["MATH", "BUSN"], $tracks);
  }


  /**
   * Verify that the school lookup methods return the school IDs associated
   * with the test data.
   */
  public function testSchoolIdLookups()
  {
    $db = $this->getDatabaseHandler();

    $this->assertSame(0, $db->get_school_id_for_student_id("999999999"));
    $this->assertSame(0, $db->get_school_id_for_course_id(181405));
    $this->assertSame(0, $db->get_school_id_for_degree_id(5450264));
    $this->assertSame(0, $db->get_school_id_for_group_id(7958642));
  }







} // class






//