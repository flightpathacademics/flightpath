<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for the lightweight standardized-test value object.
 */
class StandardizedTestTest extends FlightPathTestCase {

  /**
   * Confirms a new standardized test starts with a usable category collection
   * and an available date, so student test data can be assembled incrementally.
   */
  public function testConstructorInitializesDefaults(): void {
    $test = new StandardizedTest();

    $this->assertSame(array(), $test->categories);
    $this->assertFalse($test->bool_date_unavailable);
    $this->assertSame(0, $test->school_id);
  }

  /**
   * Verifies printable test data retains each category's identifier,
   * description, and score for diagnostic or advising output.
   */
  public function testToStringIncludesTestAndCategoryDetails(): void {
    $test = new StandardizedTest();
    $test->date_taken = '2030-05-01';
    $test->test_id = 'ACT';
    $test->description = 'ACT Composite';
    $test->categories = array(array(
      'category_id' => 'MATH',
      'description' => 'Mathematics',
      'score' => '28',
    ));

    $output = $test->to_string();

    $this->assertStringContainsString('2030-05-01 - ACT - ACT Composite', $output);
    $this->assertStringContainsString('0 - MATH - Mathematics - 28', $output);
  }
}
