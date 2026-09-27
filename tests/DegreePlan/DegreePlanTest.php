<?php

/**
 * Tests the structure, loading, and progress calculations of the DegreePlan class.
 */
require_once __DIR__ . '/../bootstrap.php';

class DegreePlanTest extends FlightPathTestCase
{
  /**
   * Verifies that a new DegreePlan starts with the expected empty collections and defaults.
   */
  public function testNewDegreePlanHasExpectedDefaults(): void
  {
    $degree_plan = new DegreePlan();

    $this->assertInstanceOf(ObjList::class, $degree_plan->list_semesters);
    $this->assertInstanceOf(GroupList::class, $degree_plan->list_groups);
    $this->assertTrue($degree_plan->list_semesters->is_empty);
    $this->assertTrue($degree_plan->list_groups->is_empty);
    $this->assertSame([], $degree_plan->required_course_id_array);
    $this->assertSame([], $degree_plan->public_notes_array);
    $this->assertSame([], $degree_plan->extra_data_array);
    $this->assertSame(0, $degree_plan->school_id);
    $this->assertSame("UG", $degree_plan->degree_level);
    $this->assertFalse($degree_plan->bool_use_draft);
  }

  /**
   * Verifies that loading a real degree plan assembles its basic descriptive data,
   * semesters, courses, and groups from the test database.
   */
  public function testDegreePlanLoadsFromDatabase(): void
  {
    $degree_plan = new DegreePlan(5450264);

    $this->assertSame(5450264, (int) $degree_plan->degree_id);
    $this->assertSame("COSC", $degree_plan->major_code);
    $this->assertSame("Computer Science", $degree_plan->title);
    $this->assertSame("BS", $degree_plan->degree_type);
    $this->assertSame("UG", $degree_plan->degree_level);
    $this->assertSame(2020, (int) $degree_plan->catalog_year);
    $this->assertFalse($degree_plan->list_semesters->is_empty);
    $this->assertFalse($degree_plan->list_groups->is_empty);
  }

  /**
   * Verifies that loading a complete degree plan creates the special "Courses Added
   * by Advisor" semester and its corresponding special group.
   */
  public function testDegreePlanAddsCoursesAddedSemester(): void
  {
    $degree_plan = new DegreePlan(5450264);

    $semester = new Semester(DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED);
    $semester_courses_added = $degree_plan->list_semesters->find_match($semester);

    $this->assertNotFalse($semester_courses_added);
    $this->assertSame("Courses Added by Advisor", $semester_courses_added->title);
    $this->assertCount(1, $semester_courses_added->list_groups->array_list);

    $group = $semester_courses_added->list_groups->array_list[0];
    $this->assertSame(DegreePlan::GROUP_ID_FOR_COURSES_ADDED, $group->group_id);
    $this->assertSame(DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED, $group->assigned_to_semester_num);
    $this->assertSame(99999.0, (float) $group->hours_required);
  }

  /**
   * Verifies that find_group() locates both top-level groups and groups nested
   * one level down in a branch.
   */
  public function testFindGroupFindsTopLevelAndNestedGroups(): void
  {
    $degree_plan = new DegreePlan();

    $top_level = new Group();
    $top_level->group_id = "TOP";

    $nested = new Group();
    $nested->group_id = "NESTED";

    $parent = new Group();
    $parent->group_id = "PARENT";
    $parent->list_groups->add($nested);

    $degree_plan->list_groups->add($top_level);
    $degree_plan->list_groups->add($parent);

    $this->assertSame($top_level, $degree_plan->find_group("TOP"));
    $this->assertSame($nested, $degree_plan->find_group("NESTED"));
    $this->assertFalse($degree_plan->find_group("MISSING"));
  }

  /**
   * Verifies that add_to_required_course_id_array() merges course requirement
   * information without losing the degree and group relationships already stored.
   */
  public function testAddToRequiredCourseIdArrayMergesRequirements(): void
  {
    $degree_plan = new DegreePlan();

    $degree_plan->required_course_id_array = [
      1001 => [
        101 => [
          0 => true,
        ],
      ],
    ];

    $degree_plan->add_to_required_course_id_array([
      1001 => [
        202 => [
          5001 => true,
        ],
      ],
      1002 => [
        101 => [
          0 => true,
        ],
      ],
    ]);

    $this->assertSame([
      1001 => [
        101 => [
          0 => true,
        ],
        202 => [
          5001 => true,
        ],
      ],
      1002 => [
        101 => [
          0 => true,
        ],
      ],
    ], $degree_plan->required_course_id_array);
  }

  /**
   * Verifies that required progress hours include bare courses and groups,
   * while excluding special negative-ID groups such as "Courses Added by Advisor".
   */
  public function testGetProgressHoursCountsCoursesAndGroupsByRequirementType(): void
  {
    $degree_plan = new DegreePlan();

    $semester = new Semester(0);

    $major_course = new Course();
    $major_course->course_id = 1001;
    $major_course->min_hours = 3;
    $major_course->max_hours = 3;
    $major_course->requirement_type = "m";
    $semester->list_courses->add($major_course);

    $general_course = new Course();
    $general_course->course_id = 1002;
    $general_course->min_hours = 3;
    $general_course->max_hours = 3;
    $general_course->requirement_type = "g";
    $semester->list_courses->add($general_course);

    $degree_plan->list_semesters->add($semester);

    $major_group = new Group();
    $major_group->group_id = 5001;
    $major_group->hours_required = 6;
    $major_group->requirement_type = "m";
    $major_group->min_hours_allowed = Group::GROUP_MIN_HOURS_NOT_SET;
    $degree_plan->list_groups->add($major_group);

    $general_group = new Group();
    $general_group->group_id = 5002;
    $general_group->hours_required = 4;
    $general_group->requirement_type = "g";
    $general_group->min_hours_allowed = Group::GROUP_MIN_HOURS_NOT_SET;
    $degree_plan->list_groups->add($general_group);

    $this->assertSame(9.0, $degree_plan->get_progress_hours("m"));
    $this->assertSame(7.0, $degree_plan->get_progress_hours("g"));
  }

  /**
   * Verifies that a requirement type of "degree" is treated as the overall
   * degree total rather than as a literal requirement type.
   */
  public function testGetProgressHoursTreatsDegreeAsOverallTotal(): void
  {
    $degree_plan = new DegreePlan();

    $semester = new Semester(0);

    $course = new Course();
    $course->course_id = 1001;
    $course->min_hours = 3;
    $course->max_hours = 3;
    $course->requirement_type = "m";
    $semester->list_courses->add($course);

    $degree_plan->list_semesters->add($semester);

    $this->assertSame(
        $degree_plan->get_progress_hours(""),
        $degree_plan->get_progress_hours("degree")
        );
  }

  /**
   * Verifies that calculate_progress_hours() stores the requested totals and
   * fulfilled hours in gpa_calculations and marks the calculation as complete.
   */
  public function testCalculateProgressHoursStoresResults(): void
  {
    $degree_plan = new DegreePlan();
    $degree_plan->degree_id = 5450264;

    $semester = new Semester(0);

    $course = new Course();
    $course->course_id = 1001;
    $course->min_hours = 3;
    $course->max_hours = 3;
    $course->requirement_type = "m";
    $semester->list_courses->add($course);

    $degree_plan->list_semesters->add($semester);

    $degree_plan->calculate_progress_hours(false, ["m" => "Major"]);

    $this->assertTrue($degree_plan->bool_calculated_progess_hours);
    $this->assertSame(3.0, $degree_plan->gpa_calculations[0]["m"]["total_hours"]);
    $this->assertArrayHasKey("fulfilled_hours", $degree_plan->gpa_calculations[0]["m"]);
    $this->assertArrayHasKey("qpts_hours", $degree_plan->gpa_calculations[0]["m"]);
    $this->assertArrayHasKey("degree", $degree_plan->gpa_calculations[0]);
  }


  /**
   * Parses track-selection configuration text into the structured configuration array used by the degree plan.
   */
  public function testParseTrackSelectionConfig(): void
  {
    $degree_plan = new DegreePlan();

    $degree_plan->db_track_selection_config = "
                  # This is a comment.

                  CONCENTRATION ~ 0 ~ 1 ~
                  EMPHASIS ~ 1 ~ 1 ~ ART|_SCULT, ART|_PAINT

                  ";

    $degree_plan->parse_track_selection_config();

    $this->assertSame([
      "CONCENTRATION" => [
        "machine_name" => "CONCENTRATION",
        "min_tracks" => 0,
        "max_tracks" => 1,
        "default_tracks" => "",
      ],
      "EMPHASIS" => [
        "machine_name" => "EMPHASIS",
        "min_tracks" => 1,
        "max_tracks" => 1,
        "default_tracks" => "ART|_SCULT, ART|_PAINT",
      ],
    ], $degree_plan->track_selection_config_array);
  }



  /**
   * Returns the degree title alone by default, and appends the track title when requested.
   */
  public function testGetTitleIncludesTrackTitleWhenRequested(): void
  {
    $degree_plan = new DegreePlan();

    $degree_plan->title = "Computer Science";
    $degree_plan->track_title = "Cybersecurity";

    $this->assertSame("Computer Science", $degree_plan->get_title());
    $this->assertSame("Computer Science with Cybersecurity", $degree_plan->get_title(true));
  }


  /**
   * Returns the major code directly for a normal, non-combined degree plan.
   */
  public function testGetMajorCodeCsvForRegularDegree(): void
  {
    $degree_plan = new DegreePlan();

    $degree_plan->major_code = "COSC";
    $degree_plan->is_combined_dynamic_degree_plan = false;

    $this->assertSame("COSC", $degree_plan->get_major_code_csv());
  }


  /**
   * Builds a comma-separated major-code list from the component degree plans of a combined degree.
   */
  public function testGetMajorCodeCsvForCombinedDegree(): void
  {
    $degree_plan = new DegreePlan();

    $degree_plan->is_combined_dynamic_degree_plan = true;
    $degree_plan->combined_degree_ids_array = [5450264, 742224];

    $this->assertSame("COSC,ENGL", $degree_plan->get_major_code_csv());
  }

  /**
   * Returns FALSE when the degree plan has no track title.
   */
  public function testGetTrackTitleReturnsFalseWhenNoTrackExists(): void
  {
    $degree_plan = new DegreePlan();

    $degree_plan->bool_loaded_descriptive_data = true;
    $degree_plan->track_title = "";

    $this->assertFalse($degree_plan->get_track_title());
  }

  /**
   * Returns the degree title wrapped in the default HTML markup.
   */
  public function testGetTitle2ReturnsHtmlTitleByDefault(): void
  {
    $degree_plan = new DegreePlan();

    $degree_plan->bool_loaded_descriptive_data = true;
    $degree_plan->title = "Computer Science";
    $degree_plan->track_title = "";
    $degree_plan->degree_class = "";

    $this->assertSame(
        "<span class='deg-title'>Computer Science</span>",
        $degree_plan->get_title2()
        );
  }

  /**
   * Returns the degree title as plain text when HTML output is disabled.
   */
  public function testGetTitle2CanReturnPlainTitle(): void
  {
    $degree_plan = new DegreePlan();

    $degree_plan->bool_loaded_descriptive_data = true;
    $degree_plan->title = "Computer Science";
    $degree_plan->track_title = "";
    $degree_plan->degree_class = "";

    $this->assertSame(
        "Computer Science",
        $degree_plan->get_title2(false, false, false)
        );
  }


  /**
   * Includes the track title in plain-text output when requested.
   */
  public function testGetTitle2IncludesTrackTitleAsPlainText(): void
  {
    $degree_plan = new DegreePlan();

    $degree_plan->bool_loaded_descriptive_data = true;
    $degree_plan->title = "Computer Science";
    $degree_plan->track_title = "Math Focus";
    $degree_plan->degree_class = "";

    $this->assertSame(
        "Computer ScienceMath Focus",
        $degree_plan->get_title2(false, true, false)
        );
  }

  /**
   * Includes the track title with the expected separator and HTML markup when requested.
   */
  public function testGetTitle2IncludesTrackTitleAsHtml(): void
  {
    $degree_plan = new DegreePlan();

    $degree_plan->bool_loaded_descriptive_data = true;
    $degree_plan->title = "Computer Science";
    $degree_plan->track_title = "Math Focus";
    $degree_plan->degree_class = "";

    $this->assertSame(
        "<span class='deg-title'>Computer Science</span><span class='level-3-raquo'>&raquo;</span><span class='deg-track-title'>Math Focus</span>",
        $degree_plan->get_title2(false, true, true)
        );
  }

  /**
   * Finds a course in the specified semester and returns the matching CourseList.
   */
  public function testFindCoursesFindsCourseInSpecifiedSemester(): void
  {
    $degree_plan = new DegreePlan();

    $semester = new Semester(2);

    $course = new Course();
    $course->course_id = 12345;
    $semester->list_courses->add($course);

    $degree_plan->list_semesters->add($semester);

    $result = $degree_plan->find_courses(12345, 0, 2);

    $this->assertInstanceOf(CourseList::class, $result);
    $this->assertSame(1, $result->get_size());
    $this->assertSame(12345, $result->get_first()->course_id);
  }

  /**
   * Returns FALSE when the requested course is not present in the specified semester.
   */
  public function testFindCoursesReturnsFalseWhenCourseIsNotFound(): void
  {
    $degree_plan = new DegreePlan();

    $semester = new Semester(2);

    $course = new Course();
    $course->course_id = 12345;
    $semester->list_courses->add($course);

    $degree_plan->list_semesters->add($semester);

    $this->assertFalse($degree_plan->find_courses(99999, 0, 2));
  }


  /**
   * Keeps published and draft degree data separate when loading the same degree ID.
   */
  public function testDegreePlanPublishedAndDraftUseSeparateDegreeData(): void
  {
    $result = db_query("SELECT title FROM degrees WHERE degree_id = ?", [5450264]);
    $published_title = db_result($result);

    $result = db_query("SELECT title FROM draft_degrees WHERE degree_id = ?", [5450264]);
    $draft_title = db_result($result);

    $this->assertSame("Computer Science", $published_title);
    $this->assertSame("Computer Science", $draft_title);

    db_query("UPDATE draft_degrees SET title = ? WHERE degree_id = ?", [
      "Test Draft Computer Science",
      5450264,
    ]);

    try {
      $published = new DegreePlan(5450264);
      $draft = new DegreePlan(5450264, NULL, FALSE, FALSE, TRUE);

      $this->assertSame("Computer Science", $published->title);
      $this->assertSame("Test Draft Computer Science", $draft->title);
      $this->assertFalse($published->bool_use_draft);
      $this->assertTrue($draft->bool_use_draft);
    }
    finally {
      db_query("UPDATE draft_degrees SET title = ? WHERE degree_id = ?", [
        $draft_title,
        5450264,
      ]);
    }
  }

  /**
   * Uses published degree data by default rather than draft degree data.
   */
  public function testDegreePlanDraftFlagDefaultsToFalse(): void
  {
    $degree_plan = new DegreePlan();

    $this->assertFalse($degree_plan->bool_use_draft);
  }


  /**
   * Loads descriptive degree data from the draft tables when the draft flag is explicitly enabled.
   */
  public function testDegreePlanExplicitDraftFlagIsHonored(): void
  {
    $degree_plan = new DegreePlan();
    $degree_plan->bool_use_draft = TRUE;
    $degree_plan->degree_id = 5450264;
    $degree_plan->load_descriptive_data();

    $this->assertTrue($degree_plan->bool_use_draft);
    $this->assertSame("Computer Science", $degree_plan->title);
    $this->assertSame("COSC", $degree_plan->major_code);
  }


  /**
   * Finds and returns an existing semester by its semester number.
   */
  public function testGetSemesterFindsExistingSemester(): void
  {
    $degree_plan = new DegreePlan();

    $semester = new Semester(2);
    $degree_plan->list_semesters->add($semester);

    $result = $degree_plan->get_semester(2);

    $this->assertSame($semester, $result);
  }

  /**
   * Returns FALSE when no semester with the requested number exists.
   */
  public function testGetSemesterReturnsFalseWhenSemesterDoesNotExist(): void
  {
    $degree_plan = new DegreePlan();

    $semester = new Semester(2);
    $degree_plan->list_semesters->add($semester);

    $this->assertFalse($degree_plan->get_semester(3));
  }

  /**
   * Finds a placeholder group in the specified semester.
   */
  public function testFindPlaceholderGroupFindsGroupInSpecifiedSemester(): void
  {
    $degree_plan = new DegreePlan();

    $semester = new Semester(2);
    $group = new Group();
    $group->group_id = 12345;
    $semester->list_groups->add($group);
    $degree_plan->list_semesters->add($semester);

    $result = $degree_plan->find_placeholder_group(12345, 2);

    $this->assertInstanceOf(Group::class, $result);
    $this->assertSame(12345, $result->group_id);
  }

  /**
   * Falls back to the degree table to obtain a title when the DegreePlan has no title of its own.
   */
  public function testGetTitle2FallsBackToDegreeData(): void
  {
    $degree_plan = new DegreePlan();
    $degree_plan->bool_loaded_descriptive_data = TRUE;
    $degree_plan->title = "";
    $degree_plan->major_code = "COSC";
    $degree_plan->catalog_year = 2020;
    $degree_plan->school_id = 0;
    $degree_plan->bool_use_draft = FALSE;

    $this->assertSame("Computer Science", $degree_plan->get_title2(FALSE, FALSE, FALSE));
  }

  /**
   * Loads a real track degree and returns the track title associated with it.
   */
  public function testGetTrackTitleReturnsTrackTitle(): void
  {
    $db = get_global_database_handler();
    $degree_id = $db->get_degree_id("COSC|_MATH", 2020, FALSE, 0);

    $this->assertNotEmpty($degree_id);

    $degree_plan = new DegreePlan($degree_id);

    $this->assertSame("Math Focus", $degree_plan->get_track_title());
  }


} // class
























//