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
}