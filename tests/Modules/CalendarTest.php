<?php


/**
 * Tests for the calendar module's availability and ICS functions.
 *
 * The event type and unavailable-time content objects are placed straight into
 * the content_load() cache.  Unavailable times also need a minimal row in
 * content / content__schedule_unavailable_time so that
 * content_get_content_for_faculty_id() finds them.  Those rows use high cids
 * and are removed again in tearDown().
 */
class CalendarTest extends FlightPathTestCase {

  const EVENT_CID = 990000;
  const UNAVAIL_CID = 990001;

  protected function tearDown(): void {
    db_query("DELETE FROM content WHERE cid IN (?, ?)", array(self::EVENT_CID, self::UNAVAIL_CID));
    db_query("DELETE FROM content__schedule_unavailable_time WHERE cid = ?", array(self::UNAVAIL_CID));
    unset($GLOBALS['content_cache']);
    parent::tearDown();
  }

  /**
   * Put a schedule_event_type object into the content cache.
   */
  private function setEventType(string $faculty_id, int $duration, int $buffer, int $lead_hours): void {
    $c = new stdClass();
    $c->cid = self::EVENT_CID;
    $c->type = 'schedule_event_type';
    $c->field__faculty_id = array('value' => $faculty_id);
    $c->field__event_duration_minutes = array('value' => $duration);
    $c->field__event_buffer_minutes = array('value' => $buffer);
    $c->field__prevent_less_than_hours = array('value' => $lead_hours);
    $GLOBALS['content_cache'][self::EVENT_CID] = $c;
  }

  /**
   * Add a "default hours" unavailable time for the faculty member.
   */
  private function setDefaultHours(string $faculty_id, array $days, int $start_hour, int $stop_hour): void {
    db_query("INSERT INTO content (cid, vid, type, title, published, delete_flag) VALUES (?, ?, 'schedule_unavailable_time', 'calendar test', 1, 0)",
             array(self::UNAVAIL_CID, self::UNAVAIL_CID));
    db_query("INSERT INTO content__schedule_unavailable_time (cid, vid, field__faculty_id) VALUES (?, ?, ?)",
             array(self::UNAVAIL_CID, self::UNAVAIL_CID, $faculty_id));

    $c = new stdClass();
    $c->cid = self::UNAVAIL_CID;
    $c->type = 'schedule_unavailable_time';
    $c->field__faculty_id = array('value' => $faculty_id);
    $c->field__ics_url = array('value' => '');
    $c->field__time_selector = array('value' => 'default');
    $c->field__days = array('value' => $days);
    $c->field__day_start_hour = array('value' => $start_hour);
    $c->field__day_stop_hour = array('value' => $stop_hour);
    $GLOBALS['content_cache'][self::UNAVAIL_CID] = $c;
  }

  /**
   * Returns the offered slots for one day as "9:00am-9:30am" strings.
   */
  private function slotsForDay(string $faculty_id, int $year, int $month, int $day): array {
    $rtn = calendar_get_available_faculty_schedule($faculty_id, self::EVENT_CID, $month, $year);
    $slots = $rtn[$year][$month][$day] ?? array();
    return array_values(array_map(fn($s) => $s['begin_hm'] . '-' . $s['end_hm'], $slots));
  }


  /**
   * with a stop hour of 5pm, the last slot offered must end at 5pm.
   */
  public function testDefaultHoursStopHourIsNotOffered(): void {
    $fid = 'CALTEST_M8';
    $this->setEventType($fid, 15, 15, 1);
    $this->setDefaultHours($fid, array(1), 9, 17);  // Mondays, 9am-5pm

    // Monday, Oct 14 2030 (far enough in the future that lead time doesn't matter).
    $slots = $this->slotsForDay($fid, 2030, 10, 14);

    $this->assertSame('9:00am-9:30am', $slots[0]);
    $this->assertSame('4:30pm-5:00pm', end($slots), 'Last slot should end at the 5pm stop hour');
    $this->assertNotContains('5:00pm-5:30pm', $slots);
    $this->assertCount(16, $slots);  // 9:00am .. 4:30pm, every 30 minutes
  }

  /**
   * a stop hour of 11pm still offers the 10:30pm-11:00pm slot.
   */
  public function testDefaultHoursStopHourElevenPm(): void {
    $fid = 'CALTEST_M8';
    $this->setEventType($fid, 15, 15, 1);
    $this->setDefaultHours($fid, array(1), 9, 23);

    $slots = $this->slotsForDay($fid, 2030, 10, 14);

    $this->assertSame('10:30pm-11:00pm', end($slots));
    $this->assertNotContains('11:00pm-11:30pm', $slots);
  }

  /**
   * a stop hour of 12am (midnight) must not block the whole day.
   */
  public function testDefaultHoursMidnightStop(): void {
    $fid = 'CALTEST_M8';
    $this->setEventType($fid, 15, 15, 1);
    $this->setDefaultHours($fid, array(1), 9, 0);

    $slots = $this->slotsForDay($fid, 2030, 10, 14);

    $this->assertNotEmpty($slots);
    $this->assertSame('9:00am-9:30am', $slots[0]);
    $this->assertContains('10:30pm-11:00pm', $slots);
  }

  /**
   * the lead time ("prevent less than X hours") must compare real instants.
   * Slot times are wall-clock times in the faculty member's timezone, while "now"
   * is real time.  With a 1-hour lead, the first slot offered should be about
   * 1 hour from now, not 1 hour + the UTC offset.
   *
   * The faculty id has no user record, so the timezone is the system default
   * (America/Chicago in the unit test db).
   */
  public function testLeadTimeUsesFacultyTimezone(): void {
    $fid = 'CALTEST_M10';
    $this->setEventType($fid, 15, 15, 1);  // 30-minute grid, 1 hour lead
    $tz = new DateTimeZone(fp_get_user_timezone(db_get_user_id_from_cwid($fid)));
    $this->assertNotSame(0, $tz->getOffset(new DateTime('now')), 'Test needs a non-UTC faculty timezone');

    $earliest = time() + 3600;
    $local = new DateTime('@' . $earliest);
    $local->setTimezone($tz);
    $year = intval($local->format('Y'));
    $month = intval($local->format('n'));

    // Collect all slots (as real UTC instants) for this month and next.
    $real_starts = array();
    foreach (array(array($year, $month), $month == 12 ? array($year + 1, 1) : array($year, $month + 1)) as $ym) {
      $rtn = calendar_get_available_faculty_schedule($fid, self::EVENT_CID, $ym[1], $ym[0]);
      foreach ($rtn as $y => $months) foreach ($months as $m => $days) foreach ($days as $d => $slots) {
        foreach ($slots as $slot) {
          // begin_slot_ts is the faculty's wall-clock time stored as if it were UTC.
          $dt = new DateTime(gmdate('Y-m-d H:i:s', $slot['begin_slot_ts']), $tz);
          $real_starts[] = $dt->getTimestamp();
        }
      }
    }
    $this->assertNotEmpty($real_starts);
    $first = min($real_starts);

    $this->assertGreaterThanOrEqual($earliest, $first, 'A slot inside the lead time was offered');
    // Allow up to two 30-minute grid steps (the 11:30pm slot is never offered).
    $this->assertLessThan($earliest + 3600 + 120, $first, 'First slot is much later than the lead time');
  }


  /**
   * text in SUMMARY / LOCATION / DESCRIPTION must be escaped per RFC 5545
   * (backslash, semicolon, comma, newlines) so it can't inject new ICS properties.
   */
  public function testIcsInvitationEscapesText(): void {
    $title = "Meeting between Eve\nATTENDEE;CN=x:mailto:x@evil.test\r\nURL:https://evil.test/login\rX and Dr. Smith";
    $location = "Room 3; Bldg A, C:\\temp";
    $description = "Line one\r\nC:\\temp, a; b";
    $ics = calendar_get_ics_invitation_string($title, $location, $description, "20301014T160000Z", "20301014T161500Z");

    // Unfold (RFC 5545 3.1) and split into content lines.
    $unfolded = preg_replace("/\r?\n[ \t]/", "", $ics);
    $lines = preg_split("/\r?\n/", $unfolded);

    foreach ($lines as $line) {
      $this->assertStringNotContainsString("\r", $line);
      $this->assertDoesNotMatchRegularExpression('/^(ATTENDEE|URL)/', $line, 'Injected property line found');
    }

    $props = array();
    foreach ($lines as $line) {
      list($name, $value) = explode(':', $line, 2);
      $props[$name] = $value;
    }

    $this->assertSame('Meeting between Eve\\nATTENDEE\\;CN=x:mailto:x@evil.test\\nURL:https://evil.test/login\\nX and Dr. Smith', $props['SUMMARY']);
    $this->assertSame('Room 3\\; Bldg A\\, C:\\\\temp', $props['LOCATION']);
    $this->assertSame('Line one\\nC:\\\\temp\\, a\\; b', $props['DESCRIPTION']);
  }

  public function testIcsEscape(): void {
    $this->assertTrue(function_exists('calendar_ics_escape'));
    $this->assertSame('a\\\\b\\;c\\,d\\ne\\nf\\ng', calendar_ics_escape("a\\b;c,d\r\ne\nf\rg"));
    $this->assertSame('plain text', calendar_ics_escape('plain text'));
  }



}