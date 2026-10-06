<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for Student Files routes, upload forms, and access permissions.
 */
class StudentFilesTest extends FlightPathTestCase {

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('student_files_menu')) {
      require_once __DIR__ . '/../../modules/student_files/student_files.module';
    }
  }

  /**
   * Confirms settings, upload, download, and delete routes use the correct
   * callbacks and record-level access callbacks for protected file actions.
   */
  public function testMenuDefinesProtectedFileManagementRoutes(): void {
    $items = student_files_menu();

    $this->assertSame('fp_render_form', $items['admin/config/student-files']['page_callback']);
    $this->assertSame(array('student_files_settings_form', 'system_settings'), $items['admin/config/student-files']['page_arguments']);
    $this->assertSame(array('administer_student_files'), $items['admin/config/student-files']['access_arguments']);
    $this->assertSame('student_files_handle_upload', $items['student-files/handle-upload/%']['page_callback']);
    $this->assertSame('student_files_user_may_download_student_file', $items['student-files/handle-download/%/%']['access_callback']);
    $this->assertSame('student_files_user_may_delete_student_file', $items['student-files/handle-delete/%/%']['access_callback']);
  }

  /**
   * Ensures the bulk-upload form accepts multiple files and defaults to the
   * filename-based, faculty-visible workflow used by staff upload tools.
   */
  public function testBulkUploadFormDefinesExpectedDefaults(): void {
    $form = student_files_upload_any_student_files_form();

    $this->assertSame('multipart/form-data', $form['#attributes']['enctype']);
    $this->assertSame('radios', $form['student_method']['type']);
    $this->assertSame('filename', $form['student_method']['value']);
    $this->assertTrue($form['student_method']['required']);
    $this->assertSame('textfield', $form['manual_cwid']['type']);
    $this->assertSame('faculty', $form['access_type']['value']);
    $this->assertSame('file', $form['student_files']['type']);
    $this->assertTrue($form['student_files']['multiple']);
  }

  /**
   * Confirms the smaller History-tab upload form preserves the student ID and
   * defaults new files to faculty visibility before submission.
   */
  public function testLittleUploadFormKeepsStudentContext(): void {
    $form = student_files_little_upload_form('991234567');

    $this->assertSame('multipart/form-data', $form['#attributes']['enctype']);
    $this->assertSame('991234567', $form['student_id']['value']);
    $this->assertSame('991234567', $form['current_student_id']['value']);
    $this->assertSame('faculty', $form['access_type']['value']);
    $this->assertSame('Upload', $form['submit_btn']['value']);
  }

  /**
   * Verifies permissions distinguish administrative configuration, uploading,
   * deletion, and advising-file download access.
   */
  public function testPermissionDefinesFileAccessCapabilities(): void {
    $permissions = student_files_perm();

    $this->assertArrayHasKey('administer_student_files', $permissions);
    $this->assertArrayHasKey('upload_any_student_files', $permissions);
    $this->assertArrayHasKey('delete_own_student_files', $permissions);
    $this->assertArrayHasKey('delete_any_student_files', $permissions);
    $this->assertArrayHasKey('download_advising_student_files', $permissions);
    $this->assertSame('Upload student files', $permissions['upload_student_files']['title']);
  }
}
