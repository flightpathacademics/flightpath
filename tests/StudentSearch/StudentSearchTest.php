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



} // class