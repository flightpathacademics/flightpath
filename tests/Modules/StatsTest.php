<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests that stats report forms escape values that come from the request.
 */
class StatsTest extends FlightPathTestCase {

  protected function setUp(): void {
    parent::setUp();
    if (!function_exists('stats_draw_date_range_form')) {
      require_once __DIR__ . '/../../modules/stats/stats.module';
    }
  }

  public function testDateRangeFormEscapesDates(): void {
    $html = stats_draw_date_range_form('stats/reports/flightpath-use-summary', "2026-01-01'><script>alert(1)</script>", "2026-02-01");
    $this->assertStringNotContainsString("<script>alert(1)</script>", $html);
    $this->assertStringContainsString("&#039;&gt;&lt;script&gt;", $html);
    $this->assertStringContainsString("value='2026-02-01'", $html);
  }

  /**
   * Confirms analytics routes keep their report callbacks and shared access
   * permission, preventing reports or CSV downloads from becoming public.
   */
  public function testMenuDefinesProtectedAnalyticsAndDownloadRoutes(): void {
    $items = stats_menu();

    $this->assertSame('stats_display_main', $items['stats']['page_callback']);
    $this->assertSame(array('can_access_stats'), $items['stats']['access_arguments']);
    $this->assertSame('stats_report_major_counts', $items['stats/reports/major-counts']['page_callback']);
    $this->assertSame('stats_report_flightpath_use_summary', $items['stats/reports/flightpath-use-summary']['page_callback']);
    $this->assertSame('stats_download_csv_from_batch', $items['stats/download-csv-from-batch/%']['page_callback']);
    $this->assertSame(array(2), $items['stats/download-csv-from-batch/%']['page_arguments']);
    $this->assertSame(MENU_TYPE_CALLBACK, $items['stats/download-csv-from-batch-file/%']['type']);
  }

  /**
   * Verifies reusable date fields preserve caller values and layout preference,
   * while Stats advertises the permission used to protect all reports.
   */
  public function testDateRangeElementsAndPermissionDefinition(): void {
    $elements = stats_get_date_range_form_elements(FALSE, '2030-01-01', '2030-01-31');
    $permissions = stats_perm();

    $this->assertSame('date', $elements['start_date']['type']);
    $this->assertSame('2030-01-01', $elements['start_date']['value']);
    $this->assertFalse($elements['start_date']['inline']);
    $this->assertSame('2030-01-31', $elements['end_date']['value']);
    $this->assertFalse($elements['end_date']['inline']);
    $this->assertSame('Access/view stats & analytics', $permissions['can_access_stats']['title']);
  }

  /**
   * Ensures course-use search reflects a requested course as plain text rather
   * than executable markup, protecting reports rendered from query parameters.
   */
  public function testCourseUseSummaryEscapesRequestedCourseValues(): void {
    $getWasArray = is_array($_GET);
    $originalGet = $getWasArray ? $_GET : NULL;
    try {
      $_GET = array(
        'subject_id' => "MATH'><script>alert(1)</script>",
        'course_num' => "1010'><img src=x onerror=alert(1)>",
      );

      $html = stats_report_course_use_summary();

      $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
      $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
      $this->assertStringContainsString('&lt;script&gt;', $html);
      $this->assertStringContainsString('&lt;img', $html);
    }
    finally {
      if ($getWasArray) {
        $_GET = $originalGet;
      }
      else {
        unset($_GET);
      }
    }
  }
  /**
   * Ensures analytics counts honor action, user-type, mobile, and distinct-user
   * filters so the use-summary reports do not conflate different audiences.
   */
  public function testLogCountAppliesActionAudienceMobileAndDistinctFilters(): void {
    $typeOne = 'stats_test_one_' . substr(sha1(uniqid('', TRUE)), 0, 12);
    $typeTwo = 'stats_test_two_' . substr(sha1(uniqid('', TRUE)), 0, 12);
    $timestamp = strtotime('2030-06-15 12:00:00');
    $logIds = array();
    try {
      foreach (array(
        array(901, $typeOne, 1, 0, 0),
        array(901, $typeOne, 1, 0, 1),
        array(902, $typeTwo, 1, 0, 1),
        array(903, $typeOne, 0, 1, 0),
      ) as $record) {
        db_query(
          'INSERT INTO watchdog (user_id, type, is_student, is_faculty, is_mobile, timestamp) VALUES (?, ?, ?, ?, ?, ?)',
          array($record[0], $record[1], $record[2], $record[3], $record[4], $timestamp)
        );
        $logIds[] = intval(db_insert_id());
      }

      $this->assertSame(2, stats_get_log_count($typeOne, NULL, FALSE, '2030-06-01', '2030-07-01', TRUE));
      $this->assertSame(1, stats_get_log_count($typeOne, NULL, TRUE, '2030-06-01', '2030-07-01', TRUE));
      $this->assertSame(2, stats_get_log_count('', array($typeOne, $typeTwo), FALSE, '2030-06-01', '2030-07-01', TRUE, TRUE));
      $this->assertSame(1, stats_get_log_count($typeOne, NULL, FALSE, '2030-06-01', '2030-07-01', FALSE));
    }
    finally {
      foreach ($logIds as $logId) {
        db_query('DELETE FROM watchdog WHERE wid = ?', array($logId));
      }
    }
  }
}