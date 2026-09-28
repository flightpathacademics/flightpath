<?php

require_once __DIR__ . '/../bootstrap.php';

class SemesterTest extends FlightPathTestCase
{
  /**
   * Verifies that a new Semester initializes its number, title, and course
   * and group collections with the expected defaults.
   */
  public function testNewSemesterHasExpectedDefaults(): void
  {
    $semester = new Semester(0);

    $this->assertSame(0, $semester->semester_num);
    $this->assertSame("Freshman Year", $semester->title);
    $this->assertTrue($semester->bool_using_default_title);
    $this->assertInstanceOf(CourseList::class, $semester->list_courses);
    $this->assertInstanceOf(GroupList::class, $semester->list_groups);
    $this->assertSame(0, $semester->list_courses->get_size());
    $this->assertSame(0, $semester->list_groups->get_size());
  }

  /**
   * Verifies that assign_title() produces the expected default title for
   * each standard semester/year number.
   */
  public function testAssignTitleUsesExpectedDefaultTitles(): void
  {
    $expected_titles = [
      0 => "Freshman Year",
      1 => "Sophomore Year",
      2 => "Junior Year",
      3 => "Senior Year",
      4 => "Year 5",
      5 => "Year 6",
    ];

    foreach ($expected_titles as $semester_num => $expected_title) {
      $semester = new Semester($semester_num);

      $this->assertSame($expected_title, $semester->title);
      $this->assertTrue($semester->bool_using_default_title);
    }
  }

  /**
   * Verifies that assign_title() continues the "Year N" pattern for
   * semester numbers beyond the explicitly named years.
   */
  public function testAssignTitleUsesYearPatternForLaterSemesters(): void
  {
    $semester = new Semester(10);

    $this->assertSame("Year 11", $semester->title);
    $this->assertTrue($semester->bool_using_default_title);
  }

  /**
   * Verifies that two Semester objects are considered equal when they have
   * the same semester number, regardless of their other properties.
   */
  public function testEqualsUsesSemesterNumber(): void
  {
    $first = new Semester(2);
    $second = new Semester(2);
    $different = new Semester(3);

    $this->assertTrue($first->equals($second));
    $this->assertFalse($first->equals($different));
  }

  /**
   * Verifies that reset_list_counters() resets the iteration state of both
   * the course and group collections.
   */
  public function testResetListCountersResetsCourseAndGroupLists(): void
  {
    $semester = new Semester(0);

    $course = new Course();
    $course->course_id = 988445;

    $group = new Group();
    $group->group_id = 12345;

    $semester->list_courses->add($course);
    $semester->list_groups->add($group);

    $semester->list_courses->reset_counter();
    $semester->list_groups->reset_counter();

    $semester->list_courses->get_next();
    $semester->list_groups->get_next();

    $this->assertFalse($semester->list_courses->has_more());
    $this->assertFalse($semester->list_groups->has_more());

    $semester->reset_list_counters();

    $this->assertTrue($semester->list_courses->has_more());
    $this->assertTrue($semester->list_groups->has_more());
    $this->assertSame($course, $semester->list_courses->get_next());
    $this->assertSame($group, $semester->list_groups->get_next());
  }
}