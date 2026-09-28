<?php

/**
 * Tests basic CourseList behavior and course matching.
 */
require_once __DIR__ . '/../bootstrap.php';

class CourseListTest extends FlightPathTestCase
{
    /**
     * Adding courses should update the list and its count.
     */
    public function testAddingCoursesUpdatesList(): void
    {
        $list = new CourseList();

        $course1 = new Course();
        $course1->course_id = 1001;

        $course2 = new Course();
        $course2->course_id = 1002;

        $list->add($course1); // add the first course to the list
        $list->add($course2); // add the second course to the list

        $this->assertSame(2, $list->count); // list should contain two courses
        $this->assertFalse($list->is_empty); // list should no longer be empty
        $this->assertSame($course1, $list->get_element(0)); // first course should be at index 0
        $this->assertSame($course2, $list->get_element(1)); // second course should be at index 1
    }

    /**
     * find_all_matches should return every course with the same course ID.
     */
    public function testFindAllMatchesUsesCourseId(): void
    {
        $list = new CourseList();

        $course1 = new Course();
        $course1->course_id = 1001;

        $course2 = new Course();
        $course2->course_id = 1002;

        $course3 = new Course();
        $course3->course_id = 1001;

        $list->add($course1); // add a course with ID 1001
        $list->add($course2); // add a different course
        $list->add($course3); // add another course with ID 1001

        $matches = $list->find_all_matches($course1);

        $this->assertInstanceOf(CourseList::class, $matches); // matching results should be a CourseList
        $this->assertSame(2, $matches->count); // both courses with ID 1001 should match
        $this->assertSame($course1, $matches->get_element(0)); // first matching course should be returned
        $this->assertSame($course3, $matches->get_element(1)); // second matching course should be returned
    }

    /**
     * find_all_matches should return false when there are no matches.
     */
    public function testFindAllMatchesReturnsFalseWhenNoMatchExists(): void
    {
        $list = new CourseList();

        $course = new Course();
        $course->course_id = 1001;

        $search_course = new Course();
        $search_course->course_id = 9999;

        $list->add($course); // add a course that does not match the search

        $matches = $list->find_all_matches($search_course);

        $this->assertFalse($matches); // no matching course should return false
    }

    /**
     * find_first_unfulfilled_match should skip already-fulfilled requirements.
     */
    public function testFindFirstUnfulfilledMatchSkipsFulfilledCourse(): void
    {
        $list = new CourseList();

        $fulfilled = new Course();
        $fulfilled->course_id = 1001;
        $fulfilled->course_list_fulfilled_by->add(new Course()); // mark this requirement as fulfilled

        $unfulfilled = new Course();
        $unfulfilled->course_id = 1001;

        $list->add($fulfilled); // first matching course is already fulfilled
        $list->add($unfulfilled); // second matching course is still unfulfilled

        $match = $list->find_first_unfulfilled_match($fulfilled);

        $this->assertSame($unfulfilled, $match); // should return the first matching unfulfilled course
    }

    /**
     * find_first_unfulfilled_match should return false when every match is fulfilled.
     */
    public function testFindFirstUnfulfilledMatchReturnsFalseWhenAllAreFulfilled(): void
    {
        $list = new CourseList();

        $course1 = new Course();
        $course1->course_id = 1001;
        $course1->course_list_fulfilled_by->add(new Course()); // mark the requirement as fulfilled

        $course2 = new Course();
        $course2->course_id = 1001;
        $course2->course_list_fulfilled_by->add(new Course()); // mark the requirement as fulfilled

        $list->add($course1); // add first fulfilled match
        $list->add($course2); // add second fulfilled match

        $match = $list->find_first_unfulfilled_match($course1);

        $this->assertFalse($match); // no unfulfilled matching course should exist
    }

    /**
     * find_courses_with_grade should return only courses with the requested grade.
     */
    public function testFindCoursesWithGrade(): void
    {
        $list = new CourseList();

        $course1 = new Course();
        $course1->course_id = 1001;
        $course1->grade = "A";

        $course2 = new Course();
        $course2->course_id = 1002;
        $course2->grade = "B";

        $course3 = new Course();
        $course3->course_id = 1003;
        $course3->grade = "A";

        $list->add($course1); // add an A course
        $list->add($course2); // add a B course
        $list->add($course3); // add another A course

        $matches = $list->find_courses_with_grade("A");

        $this->assertInstanceOf(CourseList::class, $matches); // matching results should be a CourseList
        $this->assertSame(2, $matches->count); // two courses should have an A
        $this->assertSame($course1, $matches->get_element(0)); // first A course should be returned
        $this->assertSame($course3, $matches->get_element(1)); // second A course should be returned
    }

    /**
     * assign_min_grade should assign the requested minimum grade to every course.
     */
    public function testAssignMinGrade(): void
    {
        $list = new CourseList();

        $course1 = new Course();
        $course1->course_id = 1001;

        $course2 = new Course();
        $course2->course_id = 1002;

        $list->add($course1); // add the first course
        $list->add($course2); // add the second course

        $list->assign_min_grade("b-"); // assign the same minimum grade to every course

        $this->assertSame("B-", $course1->min_grade); // minimum grade should be uppercase on the first course
        $this->assertSame("B-", $course2->min_grade); // minimum grade should be uppercase on the second course
    }

    /**
     * mark_repeats_exclude should mark matching courses for exclusion.
     */
    public function testMarkRepeatsExclude(): void
    {
        $list = new CourseList();

        $course1 = new Course();
        $course1->course_id = 1001;

        $course2 = new Course();
        $course2->course_id = 1002;

        $course3 = new Course();
        $course3->course_id = 1001;

        $list->add($course1); // add the first matching course
        $list->add($course2); // add a non-matching course
        $list->add($course3); // add the second matching course

        $result = $list->mark_repeats_exclude($course1, 101); // exclude matching courses for degree 101

        $this->assertTrue($result); // at least one matching course should have been found
        $this->assertTrue($course1->get_bool_exclude_repeat(101)); // first matching course should be excluded
        $this->assertFalse($course2->get_bool_exclude_repeat(101) === true); // non-matching course should not be excluded
        $this->assertTrue($course3->get_bool_exclude_repeat(101)); // second matching course should be excluded
    }



    /**
     * find_most_recent_match should choose the most recently taken matching course.
     */
    public function testFindMostRecentMatchChoosesMostRecentCourse(): void
    {
      $list = new CourseList();

      $requirement = new Course();
      $requirement->course_id = 1001;

      $older = new Course();
      $older->course_id = 1001;
      $older->term_id = 202310;
      $older->grade = "A";

      $newer = new Course();
      $newer->course_id = 1001;
      $newer->term_id = 202410;
      $newer->grade = "B";

      $list->add($older); // add an older attempt
      $list->add($newer); // add a more recent attempt

      $match = $list->find_most_recent_match(
          $requirement,
          "",
          false,
          0,
          false
          ); // find the most recent matching attempt

          $this->assertSame($newer, $match); // the more recent attempt should be selected
    }

    /**
     * find_most_recent_match should skip a course that does not meet the minimum grade.
     */
    public function testFindMostRecentMatchSkipsCourseBelowMinimumGrade(): void
    {
      $list = new CourseList();

      $requirement = new Course();
      $requirement->course_id = 1001;

      $newer = new Course();
      $newer->course_id = 1001;
      $newer->term_id = 202410;
      $newer->grade = "C";

      $older = new Course();
      $older->course_id = 1001;
      $older->term_id = 202310;
      $older->grade = "B";

      $list->add($newer); // add the more recent course, which does not meet the minimum grade
      $list->add($older); // add an older course that does meet it

      $match = $list->find_most_recent_match(
          $requirement,
          "B",
          false,
          0,
          false
          ); // require at least a B

          $this->assertSame($older, $match); // the qualifying older attempt should be selected
    }

    /**
     * find_most_recent_match should skip a course marked as excluded from repeats.
     */
    public function testFindMostRecentMatchSkipsExcludedRepeat(): void
    {
      $list = new CourseList();

      $requirement = new Course();
      $requirement->course_id = 1001;

      $newer = new Course();
      $newer->course_id = 1001;
      $newer->term_id = 202410;
      $newer->grade = "A";
      $newer->set_bool_exclude_repeat(101, true); // exclude this attempt for degree 101

      $older = new Course();
      $older->course_id = 1001;
      $older->term_id = 202310;
      $older->grade = "B";

      $list->add($newer); // add the excluded recent attempt
      $list->add($older); // add the eligible older attempt

      $match = $list->find_most_recent_match(
          $requirement,
          "",
          false,
          101,
          false
          ); // find a match for degree 101

          $this->assertSame($older, $match); // excluded attempt should be skipped
    }

    /**
     * find_best_grade_match should choose the matching course with the best grade.
     */
    public function testFindBestGradeMatchChoosesBestGrade(): void
    {
      $list = new CourseList();

      $requirement = new Course();
      $requirement->course_id = 1001;

      $course_b = new Course();
      $course_b->course_id = 1001;
      $course_b->term_id = 202410;
      $course_b->grade = "B";

      $course_a = new Course();
      $course_a->course_id = 1001;
      $course_a->term_id = 202310;
      $course_a->grade = "A";

      $list->add($course_b); // add the B attempt
      $list->add($course_a); // add the A attempt

      $match = $list->find_best_grade_match(
          $requirement,
          "",
          false,
          0,
          false
          ); // choose the matching attempt with the best grade

          $this->assertSame($course_a, $match); // the A should be selected over the B
    }

    /**
     * find_best_grade_match should skip a course that does not meet the minimum grade.
     */
    public function testFindBestGradeMatchSkipsCourseBelowMinimumGrade(): void
    {
      $list = new CourseList();

      $requirement = new Course();
      $requirement->course_id = 1001;

      $course_a = new Course();
      $course_a->course_id = 1001;
      $course_a->grade = "A";

      $course_c = new Course();
      $course_c->course_id = 1001;
      $course_c->grade = "C";

      $list->add($course_c); // add a course below the required grade
      $list->add($course_a); // add a course that meets the requirement

      $match = $list->find_best_grade_match(
          $requirement,
          "B",
          false,
          0,
          false
          ); // require at least a B

          $this->assertSame($course_a, $match); // only the A should qualify
    }

    public function testFindBestMatchUsesMostRecentRepeatPolicy(): void
    {
      variable_set_for_school(
          "course_repeat_policy",
          "most_recent_exclude_previous",
          0
          );

      $list = new CourseList();

      $requirement = new Course();
      $requirement->course_id = 1001;
      $requirement->school_id = 0;

      $older = new Course();
      $older->course_id = 1001;
      $older->school_id = 0;
      $older->term_id = 202310;
      $older->grade = "A";

      $newer = new Course();
      $newer->course_id = 1001;
      $newer->school_id = 0;
      $newer->term_id = 202410;
      $newer->grade = "B";

      $list->add($older);
      $list->add($newer);

      $match = $list->find_best_match(
          $requirement,
          "",
          false,
          0,
          false
          );

      $this->assertSame($newer, $match);
    }


    public function testFindBestMatchUsesBestGradeRepeatPolicy(): void
    {
      variable_set_for_school("course_repeat_policy", "best_grade_exclude_others", 0);

      $list = new CourseList();

      $requirement = new Course();
      $requirement->course_id = 1001;
      $requirement->school_id = 0;

      $older = new Course();
      $older->course_id = 1001;
      $older->school_id = 0;
      $older->term_id = 202310;
      $older->grade = "A";

      $newer = new Course();
      $newer->course_id = 1001;
      $newer->school_id = 0;
      $newer->term_id = 202410;
      $newer->grade = "B";

      $list->add($older);
      $list->add($newer);

      variable_set_for_school("course_repeat_policy", "best_grade_exclude_others", 0);

      $this->assertSame("best_grade_exclude_others", variable_get_for_school("course_repeat_policy", "most_recent_exclude_previous", 0));

      $match = $list->find_best_match($requirement, "", false, 0, false);

      $this->assertNotFalse($match);
      $this->assertSame(202310, $match->term_id);
      $this->assertSame("A", $match->grade);
    }




} // class










//