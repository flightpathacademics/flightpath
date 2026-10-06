<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for Batch routing, ownership tokens, and persisted batch state.
 */
class BatchTest extends FlightPathTestCase {

  private array $batchIds = array();
  private bool $tempFileCheckExisted;
  private mixed $originalTempFileCheck;

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('batch_menu')) {
      require_once __DIR__ . '/../../modules/batch/batch.module';
    }

    $this->tempFileCheckExisted = variable_exists('batch_check_to_delete_temp_files');
    $this->originalTempFileCheck = variable_get('batch_check_to_delete_temp_files');
  }

  protected function tearDown(): void {
    foreach ($this->batchIds as $batchId) {
      db_query('DELETE FROM batch_queue WHERE batch_id = ?', array($batchId));
    }

    unset($_SESSION['fp_batch_id']);

    if ($this->tempFileCheckExisted) {
      variable_set('batch_check_to_delete_temp_files', $this->originalTempFileCheck);
    }
    else {
      variable_delete('batch_check_to_delete_temp_files');
    }

    parent::tearDown();
  }

  /**
   * Confirms the batch test, processing, completion, and AJAX routes use their
   * intended callbacks, including the protected developer test entry point.
   */
  public function testMenuDefinesBatchProcessingRoutes(): void {
    $items = batch_menu();

    $this->assertSame('fp_render_form', $items['batch-test-form']['page_callback']);
    $this->assertSame(array('batch_test_form'), $items['batch-test-form']['page_arguments']);
    $this->assertSame(array('batch_run_test'), $items['batch-test-form']['access_arguments']);
    $this->assertSame('batch_processing_page', $items['batch-processing/%']['page_callback']);
    $this->assertSame(array(1), $items['batch-processing/%']['page_arguments']);
    $this->assertSame('batch_finished_page', $items['batch-finished/%']['page_callback']);
    $this->assertSame('batch_ajax_callback', $items['batch-ajax-callback/%']['page_callback']);
  }

  /**
   * Ensures batch creation applies the documented defaults and persists the
   * operation for later AJAX processing under the submitting user's token.
   */
  public function testSetAndGetPersistBatchWithDefaults(): void {
    global $user;
    $user->user_id = 61;
    $definition = array(
      'operation' => array('batch_test_operation', array('example')),
    );

    $batchId = batch_set($definition);
    $this->batchIds[] = $batchId;
    $batch = batch_get($batchId);

    $this->assertGreaterThan(0, intval($batchId));
    $this->assertSame(intval($batchId), intval($_SESSION['fp_batch_id']));
    $this->assertSame('Processing Batch...', $batch['title']);
    $this->assertSame('Processed @current out of @total.', $batch['progress_message']);
    $this->assertFalse($batch['display_percent']);
    $this->assertSame($definition['operation'], $batch['operation']);
    $this->assertSame(batch_get_token(), $batch['token']);
    $this->assertSame(intval($batchId), intval($batch['batch_id']));
  }

  /**
   * Verifies requested batch fields override defaults, allowing callers to
   * customize the progress screen without changing batch infrastructure.
   */
  public function testSetPreservesCallerSuppliedDisplaySettings(): void {
    $batchId = batch_set(array(
      'operation' => array('batch_test_operation', array()),
      'title' => 'Importing students',
      'progress_message' => 'Completed @percent percent',
      'display_percent' => TRUE,
      'success_message' => 'Import complete.',
    ));
    $this->batchIds[] = $batchId;

    $batch = batch_get($batchId);

    $this->assertSame('Importing students', $batch['title']);
    $this->assertSame('Completed @percent percent', $batch['progress_message']);
    $this->assertTrue($batch['display_percent']);
    $this->assertSame('Import complete.', $batch['success_message']);
  }

  /**
   * Ensures a nonexistent queue identifier does not deserialize into a batch,
   * preventing callers from treating missing work as an executable job.
   */
  public function testGetReturnsFalseForMissingBatch(): void {
    $this->assertFalse(batch_get(999999999));
  }

  /**
   * Ensures processing-page access rejects a queue item whose session token no
   * longer matches, preventing another browser session from running the job.
   */
  public function testProcessingPageRejectsMismatchedToken(): void {
    $batchId = batch_set(array('operation' => array('batch_test_operation', array())));
    $this->batchIds[] = $batchId;
    db_query('UPDATE batch_queue SET token = ? WHERE batch_id = ?', array('different-session-token', $batchId));

    $output = batch_processing_page($batchId);

    $this->assertStringContainsString('token mismatch', strip_tags($output));
  }

  /**
   * Confirms completion callbacks receive the same session-token protection as
   * processing, so a stale or shared completion URL cannot finalize a batch.
   */
  public function testFinishedPageRejectsMismatchedToken(): void {
    $batchId = batch_set(array(
      'operation' => array('batch_test_operation', array()),
      'finished_callback' => array('batch_test_finished_page', array(1)),
    ));
    $this->batchIds[] = $batchId;
    db_query('UPDATE batch_queue SET token = ? WHERE batch_id = ?', array('different-session-token', $batchId));

    $output = batch_finished_page($batchId);

    $this->assertStringContainsString('token mismatch', strip_tags($output));
  }

  /**
   * Verifies cron removes only queue entries older than two hours while keeping
   * recent work available; file cleanup is deferred to avoid touching files.
   */
  public function testCronRemovesExpiredQueueEntriesAndKeepsRecentBatches(): void {
    $expiredId = batch_set(array('operation' => array('batch_test_operation', array())));
    $recentId = batch_set(array('operation' => array('batch_test_operation', array())));
    $this->batchIds[] = $expiredId;
    $this->batchIds[] = $recentId;
    db_query('UPDATE batch_queue SET created = ? WHERE batch_id = ?', array(time() - 10800, $expiredId));
    db_query('UPDATE batch_queue SET created = ? WHERE batch_id = ?', array(time(), $recentId));
    variable_set('batch_check_to_delete_temp_files', time() + 3600);

    batch_cron();

    $this->assertFalse(batch_get($expiredId));
    $this->assertSame(intval($recentId), intval(batch_get($recentId)['batch_id']));
  }

  /**
   * Confirms the module exposes its developer-only batch permission so test
   * processing cannot be invoked by ordinary application users.
   */
  public function testPermissionDefinesRestrictedTestBatchAccess(): void {
    $permissions = batch_perm();

    $this->assertSame(array('batch_run_test'), array_keys($permissions));
    $this->assertSame('Run test batch function', $permissions['batch_run_test']['title']);
    $this->assertTrue($permissions['batch_run_test']['admin_restricted']);
  }
}
