<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for Lassie's background-job lifecycle monitoring.
 */
class LassieTest extends FlightPathTestCase {

  const JOB_PREFIX = 'lassie_module_test_';

  private bool $lastCheckExisted;
  private mixed $originalLastCheck;

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('lassie_start')) {
      require_once __DIR__ . '/../../modules/lassie/lassie.module';
    }

    $this->lastCheckExisted = variable_exists('lassie_last_check');
    $this->originalLastCheck = variable_get('lassie_last_check', 0);
    $this->clearTestJobs();
  }

  protected function tearDown(): void {
    $this->clearTestJobs();

    if ($this->lastCheckExisted) {
      variable_set('lassie_last_check', $this->originalLastCheck);
    }
    else {
      variable_delete('lassie_last_check');
    }

    parent::tearDown();
  }

  /**
   * Ensures job names are safe for variable storage and remain within the
   * maximum length accepted by the Lassie job registry.
   */
  public function testMachineNameNormalizesUnsafeCharactersAndLength(): void {
    $this->assertSame('import_degree_data_', lassie_get_machine_name('import degree/data!'));
    $this->assertSame(200, strlen(lassie_get_machine_name(str_repeat('a', 201))));
  }

  /**
   * Confirms starting a job records its owner, timeout, and optional alert
   * recipients so a later cron run can monitor the correct work item.
   */
  public function testStartStoresNormalizedJobDetails(): void {
    global $user;
    $user->id = 17;

    lassie_start(self::JOB_PREFIX . 'import data!', 3, 'ops@example.test');
    $job = variable_get('lassie_job__' . self::JOB_PREFIX . 'import_data_');

    $this->assertSame(3, $job['hours']);
    $this->assertSame('ops@example.test', $job['emails']);
    $this->assertSame(17, $job['user_uid']);
    $this->assertGreaterThanOrEqual(time() - 1, $job['start']);
    $this->assertGreaterThan($job['start'], $job['expires']);
  }

  /**
   * Ensures a completed job is removed from the monitoring registry so it
   * cannot later be reported as an expired background task.
   */
  public function testFinishRemovesStartedJob(): void {
    $jobName = self::JOB_PREFIX . 'finished';
    lassie_start($jobName, 2);

    lassie_finish($jobName);

    $this->assertFalse(variable_exists('lassie_job__' . $jobName));
  }

  /**
   * Confirms a job before its deadline remains registered when Lassie checks
   * it, preventing a valid long-running task from being discarded early.
   */
  public function testCheckKeepsUnexpiredJob(): void {
    $jobName = self::JOB_PREFIX . 'active';
    variable_set('lassie_job__' . $jobName, array(
      'start' => time() - 60,
      'hours' => 1,
      'expires' => time() + 600,
      'emails' => '',
      'user_uid' => 1,
    ));

    lassie_check($jobName);

    $this->assertTrue(variable_exists('lassie_job__' . $jobName));
  }

  /**
   * Ensures an expired job is removed after it is detected, preventing the
   * same timeout from producing repeat notifications on future cron runs.
   */
  public function testCheckRemovesExpiredJob(): void {
    $jobName = self::JOB_PREFIX . 'expired';
    variable_set('lassie_job__' . $jobName, array(
      'start' => time() - 7200,
      'hours' => 1,
      'expires' => time() - 1,
      'emails' => '',
      'user_uid' => 1,
    ));

    lassie_check($jobName);

    $this->assertFalse(variable_exists('lassie_job__' . $jobName));
  }

  /**
   * Confirms cron honors its 45-minute throttle before checking jobs, then
   * processes expired work once the next scheduled check is due.
   */
  public function testCronThrottlesThenProcessesExpiredJobs(): void {
    $jobName = self::JOB_PREFIX . 'cron';
    variable_set('lassie_job__' . $jobName, array(
      'start' => time() - 7200,
      'hours' => 1,
      'expires' => time() - 1,
      'emails' => '',
      'user_uid' => 1,
    ));
    variable_set('lassie_last_check', time());

    lassie_cron();
    $this->assertTrue(variable_exists('lassie_job__' . $jobName));

    variable_set('lassie_last_check', time() - 2701);
    lassie_cron();

    $this->assertFalse(variable_exists('lassie_job__' . $jobName));
    $this->assertGreaterThanOrEqual(time() - 1, variable_get('lassie_last_check'));
  }

  private function clearTestJobs(): void {
    $res = db_query("SELECT name FROM variables WHERE name LIKE ?", array('lassie_job__' . self::JOB_PREFIX . '%'));
    while ($row = db_fetch_array($res)) {
      variable_delete($row['name']);
    }
  }
}
