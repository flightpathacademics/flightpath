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

  /**
   * Ensures file download authorization permits administrators and students
   * viewing their own non-faculty file, but protects faculty-only material.
   */
  public function testDownloadAccessDistinguishesAdminStudentAndFacultyFiles(): void {
    global $user;
    $studentId = 'STUDENT_FILE_ACCESS_' . random_int(100000, 999999);
    $originalUser = $user;
    $fileIds = array();
    try {
      foreach (array('student', 'faculty') as $accessType) {
        db_query(
          'INSERT INTO student_files (student_id, original_filename, filename, access_type, uploaded_by_cwid, posted) VALUES (?, ?, ?, ?, ?, ?)',
          array($studentId, $accessType . '.pdf', $accessType . '.pdf', $accessType, 'UPLOADER', time())
        );
        $fileIds[$accessType] = intval(db_insert_id());
      }

      $user = (object) array('id' => 1, 'cwid' => 'ADMIN', 'is_student' => FALSE, 'permissions' => array());
      $this->assertTrue(student_files_user_may_download_student_file($studentId, $fileIds['faculty']));

      $user = (object) array('id' => 62, 'cwid' => $studentId, 'is_student' => TRUE, 'permissions' => array());
      $this->assertTrue(student_files_user_may_download_student_file($studentId, $fileIds['student']));
      $this->assertFalse(student_files_user_may_download_student_file($studentId, $fileIds['faculty']));

      $user = (object) array('id' => 63, 'cwid' => 'OTHER_STUDENT', 'is_student' => TRUE, 'permissions' => array());
      $this->assertFalse(student_files_user_may_download_student_file($studentId, $fileIds['student']));
    }
    finally {
      foreach ($fileIds as $fileId) {
        db_query('DELETE FROM student_files WHERE fid = ?', array($fileId));
      }
      $user = $originalUser;
    }
  }

  /**
   * Verifies deletion allows administrators, broad file managers, and the
   * original uploader with the own-file permission, while denying others.
   */
  public function testDeleteAccessHonorsAdministrativeAndUploaderPermissions(): void {
    global $user;
    $studentId = 'STUDENT_FILE_DELETE_' . random_int(100000, 999999);
    $originalUser = $user;
    db_query(
      'INSERT INTO student_files (student_id, original_filename, filename, access_type, uploaded_by_cwid, posted) VALUES (?, ?, ?, ?, ?, ?)',
      array($studentId, 'delete.pdf', 'delete.pdf', 'faculty', 'ORIGINAL_UPLOADER', time())
    );
    $fileId = intval(db_insert_id());
    try {
      $user = (object) array('id' => 1, 'cwid' => 'ADMIN', 'permissions' => array());
      $this->assertTrue(student_files_user_may_delete_student_file($studentId, $fileId));

      $user = (object) array('id' => 62, 'cwid' => 'MANAGER', 'permissions' => array('delete_any_student_files'));
      $this->assertTrue(student_files_user_may_delete_student_file($studentId, $fileId));

      $user = (object) array('id' => 63, 'cwid' => 'ORIGINAL_UPLOADER', 'permissions' => array('delete_own_student_files'));
      $this->assertTrue(student_files_user_may_delete_student_file($studentId, $fileId));

      $user = (object) array('id' => 64, 'cwid' => 'OTHER_UPLOADER', 'permissions' => array('delete_own_student_files'));
      $this->assertFalse(student_files_user_may_delete_student_file($studentId, $fileId));
    }
    finally {
      db_query('DELETE FROM student_files WHERE fid = ?', array($fileId));
      $user = $originalUser;
    }
  }

  /**
   * Confirms settings validation normalizes harmless path separators but rejects
   * a filename pattern that could escape its configured storage directory.
   */
  public function testSettingsValidationNormalizesPathsAndRejectsFilenameDirectories(): void {
    $errorsExisted = array_key_exists('fp_form_errors', $_SESSION);
    $originalErrors = $_SESSION['fp_form_errors'] ?? NULL;
    $messagesExisted = array_key_exists('fp_messages', $_SESSION);
    $originalMessages = $_SESSION['fp_messages'] ?? NULL;
    try {
      $_SESSION['fp_form_errors'] = array();
      $_SESSION['fp_messages'] = array();
      $validState = array('values' => array(
        'student_files_path' => rtrim(sys_get_temp_dir(), '/\\') . '/',
        'student_files_sub_dir_pattern' => '/%year/%student_cwid/',
        'student_files_filename_pattern' => '%student_cwid.%ext',
      ));
      student_files_settings_form_validate(array(), $validState);

      $this->assertFalse(form_has_errors());
      $this->assertSame(rtrim(sys_get_temp_dir(), '/\\'), $validState['values']['student_files_path']);
      $this->assertSame('%year/%student_cwid', $validState['values']['student_files_sub_dir_pattern']);

      $_SESSION['fp_form_errors'] = array();
      $invalidState = array('values' => array(
        'student_files_path' => sys_get_temp_dir(),
        'student_files_sub_dir_pattern' => '',
        'student_files_filename_pattern' => 'nested/%student_cwid.%ext',
      ));
      student_files_settings_form_validate(array(), $invalidState);

      $this->assertTrue(form_has_errors());
      $this->assertSame('student_files_filename_pattern', $_SESSION['fp_form_errors'][0]['name']);
    }
    finally {
      if ($errorsExisted) $_SESSION['fp_form_errors'] = $originalErrors; else unset($_SESSION['fp_form_errors']);
      if ($messagesExisted) $_SESSION['fp_messages'] = $originalMessages; else unset($_SESSION['fp_messages']);
    }
  }
}
