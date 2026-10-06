<?php

use PHPUnit\Framework\Attributes\DataProvider;


require_once __DIR__ . '/../bootstrap.php';

class StudentSearchTest extends FlightPathTestCase {


  #[DataProvider('studentSearchTermsProvider')]
  public function testStudentCanBeFoundBySearchTerm(string $search): void {

    $_REQUEST['search_for'] = $search;

    $form = student_search_search_form();
    $results = $form['adv_array']['value'];

    $this->assertIsArray($results);
    $this->assertArrayHasKey('999999999', $results);
    $this->assertSame('Test', $results['999999999']['first_name']);
    $this->assertSame('Student', $results['999999999']['last_name']);

    $html = $form['mark_search_results']['value'];

    $this->assertStringContainsString('Test', $html);
    $this->assertStringContainsString('Student', $html);
    $this->assertStringContainsString('999999999', $html);
  }

  public static function studentSearchTermsProvider(): array {
    return [
      'first name' => ['Test'],
      'last name' => ['Student'],
      'full name' => ['Test Student'],
      'CWID' => ['999999999'],
    ];
  }



  public function testStudentsFoundByMajorSearch() {

    $_REQUEST['major_code'] = 'COSC~~school_0';
    $form = student_search_search_form();
    $results = $form['adv_array']['value'];
    $this->assertIsArray($results);
    $this->assertTrue(count($results) == 7, "COSC major has 7 students");


    parent::setUp();  // Clear values for new test

    $_REQUEST['major_code'] = 'TEST|_ONE~~school_0';
    $form = student_search_search_form();
    $results = $form['adv_array']['value'];
    $this->assertIsArray($results);
    $this->assertTrue(count($results) == 1, "TEST|_ONE major has 1 student");


  }







  /**
   * A level-1 major may contain an underscore in its major code.
   *
   * This is a regression test for a bug where major codes containing "_"
   * were incorrectly treated as degree options and excluded from the
   * student major search.
   */
  public function testLevelOneMajorWithUnderscoreIsIncluded(){
    $majors = student_search_get_majors_for_fapi();

    // We added a level-1 degree with major_code TEST|_ONE explicitly for this test.
    $this->assertArrayHasKey('TEST|_ONE~~school_0', $majors);
  }




  /**
   * Confirms Student Search declares the separate settings and navigation
   * permissions used to expose search and advisee-list subtabs safely.
   */
  public function testPermissionDefinesStudentSearchCapabilities(): void {
    $permissions = student_search_perm();

    $this->assertArrayHasKey('administer_student_search', $permissions);
    $this->assertArrayHasKey('display_search_subtab', $permissions);
    $this->assertArrayHasKey('display_my_advisees_subtab', $permissions);
    $this->assertSame('Administer Student Search', $permissions['administer_student_search']['title']);
  }

  /**
   * Ensures advanced-search help uses the configured system name and explains
   * both inactive-student and major-code search syntax to advisors.
   */
  public function testAdvancedSearchTipsUseSystemNameAndExplainSearchSyntax(): void {
    $variableExisted = variable_exists('system_name');
    $originalVariable = variable_get('system_name', NULL);
    try {
      variable_set('system_name', 'Search Test FlightPath');
      $tips = student_search_get_advanced_search_tips();
      $semanticText = preg_replace('/\\s+/', ' ', strip_tags($tips));

      $this->assertStringContainsString('Search Test FlightPath displays students', $semanticText);
      $this->assertStringContainsString('inactive students', $semanticText);
      $this->assertStringContainsString('major=CODE', $semanticText);
      $this->assertStringContainsString('student-search-advanced-tips-wrapper', $tips);
    }
    finally {
      if ($variableExisted) variable_set('system_name', $originalVariable); else variable_delete('system_name');
    }
  }

  /**
   * Verifies advisee-table headers retain sortable identity and academic fields,
   * while callers can omit the priority column for lighter-weight displays.
   */
  public function testAdviseeTableHeadersHonorPriorityFlag(): void {
    $withPriority = student_search_get_advisee_table_headers(TRUE);
    $withoutPriority = student_search_get_advisee_table_headers(FALSE);
    $withLabels = array_column($withPriority, 'label');
    $withoutLabels = array_column($withoutPriority, 'label');

    $this->assertContains('CWID', $withLabels);
    $this->assertContains('Student', $withLabels);
    $this->assertContains('Rank', $withLabels);
    $this->assertContains('Catalog<span class=' . "'mobile-hidden'" . '> Year</span>', $withLabels);
    $this->assertContains('<span class=' . "'mobile-hidden'" . '>Academic </span>Priority', $withLabels);
    $this->assertNotContains('<span class=' . "'mobile-hidden'" . '>Academic </span>Priority', $withoutLabels);
  }
} // class