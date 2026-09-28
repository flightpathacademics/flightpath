<?php

/**
 * Tests Group-specific behavior provided by GroupList.
 */
require_once __DIR__ . '/../bootstrap.php';

class GroupListTest extends FlightPathTestCase
{
  public function testNewGroupListIsEmpty(): void
  {
    $list = new GroupList();

    $this->assertTrue($list->is_empty);
    $this->assertSame(0, $list->count);
    $this->assertSame(0, $list->get_size());
  }

  public function testAddListAddsAllGroups(): void
  {
    $source = new GroupList();

    $first = new Group();
    $first->group_id = "1001_101";

    $second = new Group();
    $second->group_id = "1002_101";

    $source->add($first);
    $source->add($second);

    $target = new GroupList();
    $target->add_list($source);

    $this->assertCount(2, $target->array_list);
    $this->assertSame("1001_101", $target->array_list[0]->group_id);
    $this->assertSame("1002_101", $target->array_list[1]->group_id);
  }

  public function testFindMatchWithDegreeIdRequiresBothGroupAndDegree(): void
  {
    $list = new GroupList();

    $first = new Group();
    $first->group_id = "1001_101";
    $first->req_by_degree_id = 101;

    $second = new Group();
    $second->group_id = "1001_101";
    $second->req_by_degree_id = 202;

    $list->add($first);
    $list->add($second);

    $search = new Group();
    $search->group_id = "1001_101";

    $match = $list->find_match_with_degree_id($search, 202);

    $this->assertSame($second, $match);
  }

  public function testFindMatchWithDegreeIdReturnsFalseWhenDegreeDoesNotMatch(): void
  {
    $list = new GroupList();

    $group = new Group();
    $group->group_id = "1001_101";
    $group->req_by_degree_id = 101;

    $list->add($group);

    $search = new Group();
    $search->group_id = "1001_101";

    $this->assertFalse($list->find_match_with_degree_id($search, 999));
  }

  public function testSetReqByDegreeIdPropagatesToEveryGroup(): void
  {
    $list = new GroupList();

    $first = new Group();
    $second = new Group();

    $first_course = new Course();
    $second_course = new Course();

    $first->list_courses->add($first_course);
    $second->list_courses->add($second_course);

    $list->add($first);
    $list->add($second);

    $list->set_req_by_degree_id(123);

    $this->assertSame(123, $first->req_by_degree_id);
    $this->assertSame(123, $second->req_by_degree_id);
    $this->assertSame(123, $first_course->req_by_degree_id);
    $this->assertSame(123, $second_course->req_by_degree_id);
  }

  public function testGetGroupCourseIdArrayReturnsCoursesGroupedByGroupId(): void
  {
    $list = new GroupList();

    $first = new Group();
    $first->group_id = "1001_101";

    $first_course = new Course();
    $first_course->course_id = 2001;

    $first->list_courses->add($first_course);

    $second = new Group();
    $second->group_id = "1002_101";

    $second_course = new Course();
    $second_course->course_id = 2002;

    $second->list_courses->add($second_course);

    $list->add($first);
    $list->add($second);

    $result = $list->get_group_course_id_array();

    $this->assertSame(
      [
        "1001_101" => [2001 => true],
        "1002_101" => [2002 => true],
      ],
      $result
    );
  }

  public function testGetAdvisedCoursesListFindsAdvisedCourses(): void
  {
    $list = new GroupList();

    $first = new Group();

    $advised_course = new Course();
    $advised_course->course_id = 2001;
    $advised_course->bool_advised_to_take = true;

    $other_course = new Course();
    $other_course->course_id = 2002;
    $other_course->bool_advised_to_take = false;

    $first->list_courses->add($advised_course);
    $first->list_courses->add($other_course);

    $list->add($first);

    $result = $list->get_advised_courses_list();

    $this->assertCount(1, $result->array_list);
    $this->assertSame(2001, $result->array_list[0]->course_id);
  }

  public function testGetAdvisedCoursesListRemovesDuplicates(): void
  {
    $list = new GroupList();

    $first = new Group();
    $second = new Group();

    $course_one = new Course();
    $course_one->course_id = 2001;
    $course_one->bool_advised_to_take = true;

    $course_two = new Course();
    $course_two->course_id = 2001;
    $course_two->bool_advised_to_take = true;

    $first->list_courses->add($course_one);
    $second->list_courses->add($course_two);

    $list->add($first);
    $list->add($second);

    $result = $list->get_advised_courses_list();

    $this->assertCount(1, $result->array_list);
    $this->assertSame(2001, $result->array_list[0]->course_id);
  }

  public function testResetListCountersRecursivelyResetsGroups(): void
  {
    $list = new GroupList();

    $group = new Group();
    $course = new Course();

    $group->list_courses->add($course);
    $list->add($group);

    $list->i = 1;
    $group->list_courses->i = 1;
    $group->list_groups->i = 1;

    $list->reset_list_counters();

    $this->assertSame(0, $list->i);
    $this->assertSame(0, $group->list_courses->i);
    $this->assertSame(0, $group->list_groups->i);
  }


} // class










//