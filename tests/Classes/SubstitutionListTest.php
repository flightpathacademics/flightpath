<?php


class SubstitutionListTest extends FlightPathTestCase
{
  /**
   * Verifies that a new SubstitutionList starts empty and behaves as an
   * ObjList-based collection.
   */
  public function testNewSubstitutionListHasExpectedDefaults()
  {
    $list = new SubstitutionList();

    $this->assertSame([], $list->array_list);
    $this->assertSame(0, $list->count);
    $this->assertTrue($list->is_empty);
  }

  /**
   * Verifies that find_requirement() matches a substitution by the required
   * course ID.
   */
  public function testFindRequirementMatchesCourseRequirement()
  {
    $requirement = new Course();
    $requirement->course_id = 988445;

    $substitution = new Substitution();
    $substitution->course_requirement = $requirement;

    $list = new SubstitutionList();
    $list->add($substitution);

    $search_requirement = new Course();
    $search_requirement->course_id = 988445;

    $this->assertSame($substitution, $list->find_requirement($search_requirement));
  }

  /**
   * Verifies that find_requirement() returns FALSE when no substitution
   * matches the required course.
   */
  public function testFindRequirementReturnsFalseWhenNoMatchExists()
  {
    $requirement = new Course();
    $requirement->course_id = 988445;

    $substitution = new Substitution();
    $substitution->course_requirement = $requirement;

    $list = new SubstitutionList();
    $list->add($substitution);

    $search_requirement = new Course();
    $search_requirement->course_id = 123456;

    $this->assertFalse($list->find_requirement($search_requirement));
  }

  /**
   * Verifies that find_requirement() can exclude substitutions that have
   * already been applied.
   */
  public function testFindRequirementCanExcludeAppliedSubstitutions()
  {
    $requirement = new Course();
    $requirement->course_id = 988445;

    $substitution = new Substitution();
    $substitution->course_requirement = $requirement;
    $substitution->bool_has_been_applied = TRUE;

    $list = new SubstitutionList();
    $list->add($substitution);

    $this->assertSame($substitution, $list->find_requirement($requirement));
    $this->assertFalse($list->find_requirement($requirement, TRUE));
  }

  /**
   * Verifies that find_requirement() filters substitutions by assigned
   * degree when a degree ID is supplied.
   */
  public function testFindRequirementFiltersByDegree()
  {
    $requirement = new Course();
    $requirement->course_id = 988445;

    $matching = new Substitution();
    $matching->course_requirement = $requirement;
    $matching->db_required_degree_id = 5450264;

    $other = new Substitution();
    $other->course_requirement = $requirement;
    $other->db_required_degree_id = 9999999;

    $list = new SubstitutionList();
    $list->add($other);
    $list->add($matching);

    $this->assertSame($matching, $list->find_requirement($requirement, FALSE, "", 5450264));
  }

  /**
   * Verifies that find_requirement() only returns a substitution when the
   * required course is assigned to the requested group.
   */
  public function testFindRequirementFiltersByGroup()
  {
    $matching_requirement = new Course();
    $matching_requirement->course_id = 988445;
    $matching_requirement->assigned_to_group_ids_array[12345] = 12345;

    $other_requirement = new Course();
    $other_requirement->course_id = 988445;
    $other_requirement->assigned_to_group_ids_array[67890] = 67890;

    $matching = new Substitution();
    $matching->course_requirement = $matching_requirement;

    $other = new Substitution();
    $other->course_requirement = $other_requirement;

    $list = new SubstitutionList();
    $list->add($other);
    $list->add($matching);

    $this->assertSame($matching, $list->find_requirement($matching_requirement, FALSE, 12345));
  }

  /**
   * Verifies that find_requirement() excludes substitutions whose database
   * IDs appear in the exclusion list.
   */
  public function testFindRequirementExcludesSpecifiedIDs()
  {
    $requirement = new Course();
    $requirement->course_id = 988445;

    $excluded = new Substitution();
    $excluded->course_requirement = $requirement;
    $excluded->db_substitution_id = 100;

    $allowed = new Substitution();
    $allowed->course_requirement = $requirement;
    $allowed->db_substitution_id = 200;

    $list = new SubstitutionList();
    $list->add($excluded);
    $list->add($allowed);

    $this->assertSame($allowed, $list->find_requirement($requirement, FALSE, "", 0, [100]));
  }

  /**
   * Verifies that find_group_additions() returns the substitution course when
   * the group-addition requirement is assigned to the requested group.
   */
  public function testFindGroupAdditionsReturnsMatchingCourses()
  {
    $group = new Group();
    $group->group_id = 12345;

    $matching_requirement = new Course();
    $matching_requirement->course_id = 988445;
    $matching_requirement->assigned_to_group_ids_array[12345] = 12345;

    $matching_course = new Course();
    $matching_course->course_id = 988445;

    $matching = new Substitution();
    $matching->bool_group_addition = TRUE;
    $matching->course_requirement = $matching_requirement;
    $matching->course_list_substitutions->add($matching_course);

    $other_requirement = new Course();
    $other_requirement->course_id = 123456;
    $other_requirement->assigned_to_group_ids_array[67890] = 67890;

    $other_course = new Course();
    $other_course->course_id = 123456;

    $other = new Substitution();
    $other->bool_group_addition = TRUE;
    $other->course_requirement = $other_requirement;
    $other->course_list_substitutions->add($other_course);

    $list = new SubstitutionList();
    $list->add($matching);
    $list->add($other);

    $courses = $list->find_group_additions($group);

    $this->assertInstanceOf(CourseList::class, $courses);
    $this->assertSame(1, $courses->get_size());
    $this->assertSame($matching_course, $courses->get_element(0));
  }

  /**
   * Verifies that find_group_additions() returns FALSE when the requested
   * group has no matching group-addition substitutions.
   */
  public function testFindGroupAdditionsReturnsFalseWhenNoMatchExists()
  {
    $group = new Group();
    $group->group_id = 12345;

    $requirement = new Course();
    $requirement->course_id = 988445;
    $requirement->assigned_to_group_ids_array[67890] = 67890;

    $course = new Course();
    $course->course_id = 988445;

    $substitution = new Substitution();
    $substitution->bool_group_addition = TRUE;
    $substitution->course_requirement = $requirement;
    $substitution->course_list_substitutions->add($course);

    $list = new SubstitutionList();
    $list->add($substitution);

    $this->assertFalse($list->find_group_additions($group));
  }
}