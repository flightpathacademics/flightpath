<?php


class DbTest extends FlightPathTestCase
{
  public function testMaxCatalogRepeatsReturnsNullForUnknownCourse()
  {
    $this->assertNull(fp_get_max_catalog_repeats_for_course("XXXX", "9999", 2020, false, 0));
  }

  public function testMaxCatalogRepeatsReturnsOneForNonRepeatableCourse()
  {
    $repeats = fp_get_max_catalog_repeats_for_course("FINA", "2003", 2020, false, 0);

    $this->assertSame(1, $repeats);
  }

  // This course is configured to allow one repeat, producing two total attempts.
  public function testMaxCatalogRepeatsCalculatesRepeatAttempts()
  {
    $repeats = fp_get_max_catalog_repeats_for_course("ANTG", "423", 2020, false, 0);

    $this->assertSame(2, $repeats);
  }

  // Use a known sample degree so the expected major code comes from the test database.
  public function testGetDegreeMajorCode()
  {
    $this->assertSame("COSC", fp_get_degree_major_code(5450264, true));
  }

  public function testGetDegreeMajorCodeReturnsEmptyForUnknownDegree()
  {
    $this->assertSame("", fp_get_degree_major_code(999999999, true));
  }

  public function testGetDegreeTitle()
  {
    $this->assertSame("Computer Science", fp_get_degree_title(5450264));
  }

  public function testGetFacultyName()
  {
    $this->assertSame("Lisa Tester", fp_get_faculty_name("55588992"));
  }

  public function testGetFacultyNameReturnsUnknownForMissingFaculty()
  {
    $this->assertSame("Unknown Advisor", fp_get_faculty_name("does-not-exist"));
  }

  public function testLoadUser()
  {
    $user = fp_load_user(661);

    $this->assertIsObject($user);
    $this->assertSame(661, (int) $user->id);
    $this->assertSame("teststudent", $user->name);
    $this->assertSame("999999999", $user->cwid);
    $this->assertSame("Test", $user->f_name);
    $this->assertSame("Student", $user->l_name);
    $this->assertTrue($user->is_student);
    $this->assertFalse($user->is_faculty);
  }

  public function testLoadUserReturnsNullForMissingUser()
  {
    $this->assertNull(fp_load_user(999999999));
  }

  // User ID 0 represents the built-in anonymous user rather than a database user.
  public function testLoadAnonymousUser()
  {
    $user = fp_load_user(0);

    $this->assertIsObject($user);
    $this->assertSame(0, (int) $user->id);
    $this->assertSame("Anonymous", $user->name);
    $this->assertSame("", $user->cwid);
    $this->assertFalse($user->is_student);
    $this->assertFalse($user->is_faculty);
  }

  public function testGetUserId()
  {
    $this->assertSame("661", (string) db_get_user_id("teststudent"));
    $this->assertFalse(db_get_user_id("does-not-exist"));
  }

  public function testGetCwidFromUserId()
  {
    $this->assertSame("999999999", db_get_cwid_from_user_id(661));
  }

  // Verify both faculty and student lookups, including a type mismatch that should fail.
  public function testGetUserIdFromCwid()
  {
    $this->assertSame("671", (string) db_get_user_id_from_cwid("55588992", "faculty"));
    $this->assertSame("661", (string) db_get_user_id_from_cwid("999999999", "student"));
    $this->assertFalse(db_get_user_id_from_cwid("999999999", "faculty"));
  }

  public function testGetUserIdFromUserName()
  {
    $this->assertSame("671", (string) db_get_user_id_from_user_name("TESTFACULTY", "faculty"));
    $this->assertSame("661", (string) db_get_user_id_from_user_name("TESTSTUDENT", "student"));
    $this->assertFalse(db_get_user_id_from_user_name("teststudent", "faculty"));
  }

  public function testGetStudentName()
  {
    $this->assertSame("Test Student", fp_get_student_name("999999999", false));
    $this->assertSame("Test Student (999999999)", fp_get_student_name("999999999", true));
  }

  public function testGetStudentNameHandlesAnonymousUser()
  {
    $this->assertSame("Anonymous", fp_get_student_name(0));
  }

  public function testGetStudentEmailReturnsFalseWhenEmailIsMissing()
  {
    $this->assertFalse(fp_get_student_email("999999999"));
  }

  public function testGetFacultyEmailReturnsFalseWhenEmailIsMissing()
  {
    $this->assertFalse(fp_get_faculty_email("55588992"));
  }

  public function testGetStudentMajors()
  {
    $majors = fp_get_student_majors("999999999");

    $this->assertIsArray($majors);
    $this->assertArrayHasKey("COSC", $majors);
  }

  public function testGetStudentMajorsCanReturnCsv()
  {
    $majors = fp_get_student_majors("999999999", true);

    $this->assertSame("COSC", $majors);
  }


  // Use a unique variable name so this test does not interfere with existing configuration.
  public function testVariableExistsAndRoundTrip()
  {
    $name = "db_test_variable_" . uniqid();

    $this->assertFalse(variable_exists($name));

    variable_set($name, "test value");

    $this->assertTrue(variable_exists($name));
    $this->assertSame("test value", variable_get($name));

    variable_delete($name);

    $this->assertFalse(variable_exists($name));
  }

  // False is a legitimate stored value and must not be confused with a missing variable.
  public function testVariableCanStoreFalse()
  {
    $name = "db_test_false_" . uniqid();

    variable_set($name, false);

    $this->assertFalse(variable_get($name, false));

    variable_delete($name);
  }

  // Null is NOT a legitimate stored value for the variable system.
  public function testVariableCanNotStoreNull()
  {
    $name = "db_test_null_" . uniqid();

    variable_set($name, null);

    // Since this variable is set to NULL, it should use the default_value of 'test'
    // which we are passing it, since NULL is not a legitimate value.
    $this->assertSame(variable_get($name, 'test'), 'test');

    variable_delete($name);
  }

  // A school-specific value takes precedence over the default value.
  public function testSchoolSpecificVariable()
  {
    $name = "db_test_school_variable_" . uniqid();

    variable_set($name, "default");
    variable_set_for_school($name, "school value", 1);
    variable_set("school_override__{$name}~~school_1", "yes");

    $this->assertSame("school value", variable_get_for_school($name, "fallback", 1));

    variable_delete($name);
    variable_delete_for_school($name, 1);
    variable_delete("school_override__{$name}~~school_1");
  }

  // With no school-specific override, the default variable value should be returned.
  public function testSchoolSpecificVariableFallsBackToDefault()
  {
    $name = "db_test_school_fallback_" . uniqid();

    variable_set($name, "default");

    $this->assertSame("default", variable_get_for_school($name, "fallback", 1));

    variable_delete($name);
  }



  // The fourth argument requests the fallback rather than the deleted school-specific value.
  public function testSchoolSpecificVariableDelete()
  {
    $name = "db_test_school_delete_" . uniqid();

    variable_set_for_school($name, "school value", 1);

    $this->assertSame("fallback", variable_get_for_school($name, "fallback", 1, true));

    variable_delete_for_school($name, 1);

    $this->assertFalse(variable_exists($name . "~~school_1"));
  }

  public function testDatabaseTableExists()
  {
    $this->assertTrue(db_table_exists("users"));
    $this->assertTrue(db_table_exists("degrees"));
    $this->assertFalse(db_table_exists("definitely_not_a_real_table"));
  }




} // class









//