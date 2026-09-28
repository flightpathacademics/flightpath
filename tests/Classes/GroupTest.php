<?php

/**
 * Tests the basic behavior and recursive state management of the Group class.
 */
require_once __DIR__ . '/../bootstrap.php';

class GroupTest extends FlightPathTestCase
{
  /**
   * Verifies that a new Group starts with the expected default property values and empty child lists.
   */
  public function testNewGroupHasExpectedDefaults(): void
  {
    $group = new Group();

    $this->assertSame("", $group->group_id);
    $this->assertSame(-1, $group->assigned_to_semester_num);
    $this->assertSame(0, $group->hours_assigned);
    $this->assertSame(Group::GROUP_MIN_HOURS_NOT_SET, $group->min_hours_allowed);
    $this->assertSame(0, $group->school_id);
    $this->assertSame("", $group->requirement_type);
    $this->assertInstanceOf(CourseList::class, $group->list_courses);
    $this->assertInstanceOf(GroupList::class, $group->list_groups);
    $this->assertTrue($group->list_courses->is_empty);
    $this->assertTrue($group->list_groups->is_empty);
  }

  /**
   * Verifies that constructing a Group with an existing group ID loads its basic identifying information from the database.
   */
  public function testGroupLoadsFromDatabase(): void
  {
    $group = new Group("1001_101");

    $this->assertSame("1001_101", $group->group_id);
    $this->assertSame("1001", $group->get_db_group_id());
    $this->assertNotSame("", $group->title);
    $this->assertNotSame("", $group->group_name);
  }

  /**
   * Verifies that get_db_group_id() extracts the underlying database group ID from a degree-specific group ID.
   */
  public function testGetDbGroupIdRemovesDegreeId(): void
  {
    $group = new Group();

    $group->group_id = "12345_678";

    $this->assertSame("12345", $group->get_db_group_id());
  }

  /**
   * Verifies that two groups with the same group ID are considered equal.
   */
  public function testGroupsWithSameIdAreEqual(): void
  {
    $first = new Group();
    $first->group_id = "12345_101";

    $second = new Group();
    $second->group_id = "12345_101";

    $this->assertTrue($first->equals($second));
  }

  /**
   * Verifies that groups with different group IDs are not considered equal.
   */
  public function testGroupsWithDifferentIdsAreNotEqual(): void
  {
    $first = new Group();
    $first->group_id = "12345_101";

    $second = new Group();
    $second->group_id = "12346_101";

    $this->assertFalse($first->equals($second));
  }

  /**
   * Verifies that groups with the same underlying database group ID but different degree IDs can be considered equal when the degree ID is ignored.
   */
  public function testGroupsCanBeEqualIgnoringDegreeId(): void
  {
    $first = new Group();
    $first->group_id = "12345_101";

    $second = new Group();
    $second->group_id = "12345_202";

    $this->assertFalse($first->equals($second));
    $this->assertTrue($first->equals($second, true));
  }

  /**
   * Verifies the distinction between an unset minimum-hours value, a positive minimum that has not been met, and the resulting fulfillment status.
   */
  public function testMinHoursAllowedBehavior(): void
  {
    $group = new Group();

    $group->hours_required = 6;

    $this->assertFalse($group->has_min_hours_allowed());
    $this->assertTrue($group->get_is_min_hours_allowed_fulfilled());

    $group->min_hours_allowed = 3;

    $this->assertTrue($group->has_min_hours_allowed());
    $this->assertFalse($group->get_is_min_hours_allowed_fulfilled());

    $group->min_hours_allowed = Group::GROUP_MIN_HOURS_NOT_SET;
    $this->assertFalse($group->has_min_hours_allowed());

  }

  /**
   * Verifies that zero is a valid minimum-hours requirement rather than the special value used to mean that no minimum was set.
   */
  public function testZeroMinHoursAllowedIsAValidMinimum(): void
  {
    $group = new Group();

    $group->hours_required = 6;
    $group->min_hours_allowed = 0;

    $this->assertTrue($group->has_min_hours_allowed());
    $this->assertTrue($group->get_is_min_hours_allowed_fulfilled());
  }

  /**
   * Verifies that assigning a semester to a group updates the group and its direct courses but intentionally leaves subgroups and their courses at their existing semester assignment.
   */
  public function testAssignToSemesterDoesNotPropagateToSubgroups(): void
  {
    $group = new Group();

    $course = new Course();
    $subgroup = new Group();
    $subgroup_course = new Course();

    $group->list_courses->add($course);
    $subgroup->list_courses->add($subgroup_course);
    $group->list_groups->add($subgroup);

    $group->assign_to_semester(3);

    $this->assertSame(3, $group->assigned_to_semester_num);
    $this->assertSame(3, $course->assigned_to_semester_num);
    $this->assertSame(-1, $subgroup->assigned_to_semester_num);  // Subgroups are intentionally in semester_num -1
    $this->assertSame(-1, $subgroup_course->assigned_to_semester_num); // Subgroups are intentionally in semester_num -1
  }

  /**
   * Verifies that assigning a minimum grade to a group recursively applies it to the group's courses, subgroups, and courses within those subgroups.
   */
  public function testAssignMinGradePropagatesToCoursesAndSubgroups(): void
  {
    $group = new Group();

    $course = new Course();
    $subgroup = new Group();
    $subgroup_course = new Course();

    $group->list_courses->add($course);
    $subgroup->list_courses->add($subgroup_course);
    $group->list_groups->add($subgroup);

    $group->assign_min_grade("b");

    $this->assertSame("B", $group->min_grade);
    $this->assertSame("B", $course->min_grade);
    $this->assertSame("B", $subgroup->min_grade);
    $this->assertSame("B", $subgroup_course->min_grade);
  }

  /**
   * Verifies that the requirement-by-degree ID is propagated from a group to all nested courses and subgroups.
   */
  public function testSetReqByDegreeIdPropagatesRecursively(): void
  {
    $group = new Group();

    $course = new Course();
    $subgroup = new Group();
    $subgroup_course = new Course();

    $group->list_courses->add($course);
    $subgroup->list_courses->add($subgroup_course);
    $group->list_groups->add($subgroup);

    $group->set_req_by_degree_id(123);

    $this->assertSame(123, $group->req_by_degree_id);
    $this->assertSame(123, $course->req_by_degree_id);
    $this->assertSame(123, $subgroup->req_by_degree_id);
    $this->assertSame(123, $subgroup_course->req_by_degree_id);
  }

  /**
   * Verifies that the requirement type is propagated from a group to all nested courses and subgroups.
   */
  public function testSetRequirementTypePropagatesRecursively(): void
  {
    $group = new Group();

    $course = new Course();
    $subgroup = new Group();
    $subgroup_course = new Course();

    $group->list_courses->add($course);
    $subgroup->list_courses->add($subgroup_course);
    $group->list_groups->add($subgroup);

    $group->set_requirement_type("Major");

    $this->assertSame("Major", $group->requirement_type);
    $this->assertSame("Major", $course->requirement_type);
    $this->assertSame("Major", $subgroup->requirement_type);
    $this->assertSame("Major", $subgroup_course->requirement_type);
  }

  /**
   * Verifies that get_course_id_array() recursively collects course IDs from the group and all nested subgroups.
   */
  public function testGetCourseIdArrayIncludesCoursesFromSubgroups(): void
  {
    $group = new Group();

    $course = new Course();
    $course->course_id = 1001;

    $subgroup = new Group();

    $subgroup_course = new Course();
    $subgroup_course->course_id = 1002;

    $nested_group = new Group();

    $nested_course = new Course();
    $nested_course->course_id = 1003;

    $subgroup->list_courses->add($subgroup_course);
    $nested_group->list_courses->add($nested_course);
    $subgroup->list_groups->add($nested_group);
    $group->list_courses->add($course);
    $group->list_groups->add($subgroup);

    $result = $group->get_course_id_array();

    $this->assertSame(
        [
          1001 => true,
          1002 => true,
          1003 => true,
        ],
        $result
        );
  }

  /**
   * Verifies that get_course_id_array() returns each course ID only once when the same course appears multiple times.
   */
  public function testGetCourseIdArrayDoesNotDuplicateCourseIds(): void
  {
    $group = new Group();

    $first = new Course();
    $first->course_id = 1001;

    $second = new Course();
    $second->course_id = 1001;

    $group->list_courses->add($first);
    $group->list_courses->add($second);

    $result = $group->get_course_id_array();

    $this->assertSame([1001 => true], $result);
  }

  /**
   * Verifies that get_hours_remaining() calculates the remaining hours by subtracting fulfilled hours from the group's required hours.
   */
  public function testGetHoursRemainingUsesRequiredHoursAndFulfilledHours(): void
  {
    $group = new Group();

    $group->hours_required = 9;

    $course = new Course();
    $course->bool_advised_to_take = true;
    $course->min_hours = 3;
    $course->max_hours = 3;

    $group->list_courses->add($course);

    $this->assertSame(6.0, $group->get_hours_remaining());
  }



} // class





//