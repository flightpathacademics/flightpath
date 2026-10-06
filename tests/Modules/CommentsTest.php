<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for comment storage, visibility, rendering, and advising summaries.
 */
class CommentsTest extends FlightPathTestCase {

  const COMMENT_ID_START = 991000;
  const COMMENT_ID_END = 991099;
  const SESSION_ID_START = 991100;
  const SESSION_ID_END = 991199;
  const ADVISED_COURSE_ID_START = 991200;
  const ADVISED_COURSE_ID_END = 991299;
  const TEST_STUDENT_ID = 'COMMENTS_TEST_STUDENT';
  const FIXTURE_STUDENT_ID = '999999999';
  const TEST_FACULTY_ID = 'COMMENTS_TEST_FACULTY';
  const DEGREE_ID = 5450264;

  private bool $hadCurrentStudentId;
  private mixed $originalCurrentStudentId;

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('comments_get_comments')) {
      require_once __DIR__ . '/../../modules/comments/comments.module';
    }

    $this->hadCurrentStudentId = array_key_exists('current_student_id', $GLOBALS);
    $this->originalCurrentStudentId = $GLOBALS['current_student_id'] ?? NULL;
    $this->clearTestRecords();
  }

  protected function tearDown(): void {
    $this->clearTestRecords();

    if ($this->hadCurrentStudentId) {
      $GLOBALS['current_student_id'] = $this->originalCurrentStudentId;
    }
    else {
      unset($GLOBALS['current_student_id']);
    }

    parent::tearDown();
  }

  /**
   * Verifies every Comments route retains its intended callback, access rule,
   * and display type so users can reach the correct comments workflow.
   */
  public function testMenuDefinesCommentRoutes(): void {
    $items = comments_menu();

    $this->assertSame(array(
      'comments',
      'comments/ajax-generate-advising-summary',
      'comments/delete-comment',
      'comments/popup-display-all-comments',
      'comments/popup-display-comment',
    ), array_keys($items));
    $this->assertSame('comments_display_main', $items['comments']['page_callback']);
    $this->assertSame('comments_can_access_comments', $items['comments']['access_callback']);
    $this->assertSame(MENU_TYPE_TAB, $items['comments']['type']);
    $this->assertTrue($items['comments']['page_settings']['display_currently_advising']);
    $this->assertSame(array('can_save_comments'), $items['comments/ajax-generate-advising-summary']['access_arguments']);
    $this->assertTrue($items['comments/popup-display-comment']['page_settings']['page_is_popup']);
  }

  /**
   * Ensures a new comment is tied to the student being advised and the
   * school's current advising term, preventing comments from being misfiled.
   */
  public function testCommentFormUsesCurrentStudentAndAdvisingTerm(): void {
    $GLOBALS['current_student_id'] = self::FIXTURE_STUDENT_ID;

    $form = comments_comment_form();

    $this->assertSame('faculty', $form['type']['value']);
    $this->assertSame(array(
      'public' => 'Anyone (incl student)',
      'faculty' => 'Faculty/Staff only',
    ), $form['type']['options']);
    $this->assertSame('202140', (string) $form['term_id']['value']);
    $this->assertSame(self::FIXTURE_STUDENT_ID, $form['current_student_id']['value']);
    $this->assertSame('textarea_editor', $form['comment']['type']);
    $this->assertSame('Save', $form['submit']['value']);
  }

  /**
   * Confirms comment history is limited to one student, newest first, and
   * omits soft-deleted records during ordinary viewing.
   */
  public function testGetCommentsScopesOrdersAndExcludesDeletedRecords(): void {
    $this->insertStandardCommentRecords();

    $comments = comments_get_comments(self::TEST_STUDENT_ID);

    $this->assertSame(array(991002, 991003, 991001), array_keys($comments));
    $this->assertSame('faculty', $comments[991002]['access_type']);
    $this->assertSame('audit private', $comments[991003]['access_type']);
    $this->assertArrayNotHasKey(991004, $comments);
    $this->assertArrayNotHasKey(991005, $comments);
  }

  /**
   * Confirms callers can restrict history to comment visibility types, which
   * keeps faculty-only and audit comments out of inappropriate views.
   */
  public function testGetCommentsFiltersAccessTypes(): void {
    $this->insertStandardCommentRecords();

    $public = comments_get_comments(self::TEST_STUDENT_ID, FALSE, array('public'));
    $facultyAndPublic = comments_get_comments(self::TEST_STUDENT_ID, FALSE, array('faculty', 'public'));

    $this->assertSame(array(991001), array_keys($public));
    $this->assertSame(array(991002, 991001), array_keys($facultyAndPublic));
  }

  /**
   * Confirms administrative or cleanup callers can deliberately include
   * soft-deleted comments without changing the normal viewing behavior.
   */
  public function testGetCommentsCanIncludeDeletedRecords(): void {
    $this->insertStandardCommentRecords();

    $comments = comments_get_comments(self::TEST_STUDENT_ID, TRUE, array('public'));

    $this->assertSame(array(991004, 991001), array_keys($comments));
    $this->assertSame('1', (string) $comments[991004]['delete_flag']);
  }

  /**
   * Confirms direct comment lookup preserves soft-deleted records for actions
   * such as authorization checks while returning false for an unknown ID.
   */
  public function testGetCommentReturnsRecordRegardlessOfDeleteFlag(): void {
    $this->insertStandardCommentRecords();

    $comment = comments_get_comment(991004);

    $this->assertSame(self::TEST_STUDENT_ID, $comment['student_id']);
    $this->assertSame('Deleted public comment', $comment['comment']);
    $this->assertSame('1', (string) $comment['delete_flag']);
    $this->assertFalse(comments_get_comment(991099));
  }

  /**
   * Verifies submission stores a trimmed comment with its student, faculty,
   * term, and access type so the advising record remains attributable.
   */
  public function testCommentSubmitTrimsAndSavesComment(): void {
    global $user;
    $user->cwid = self::TEST_FACULTY_ID;
    $GLOBALS['current_student_id'] = self::TEST_STUDENT_ID;
    $formState = array('values' => array(
      'type' => 'faculty',
      'term_id' => '202140',
      'comment' => '  Saved from the comments test.  ',
    ));

    comments_comment_form_submit(array(), $formState);
    $comments = comments_get_comments(self::TEST_STUDENT_ID);

    $this->assertCount(1, $comments);
    $comment = reset($comments);
    $this->assertSame(self::TEST_FACULTY_ID, $comment['faculty_id']);
    $this->assertSame('202140', $comment['term_id']);
    $this->assertSame('faculty', $comment['access_type']);
    $this->assertSame('Saved from the comments test.', $comment['comment']);
    $this->assertSame('0', (string) $comment['delete_flag']);
  }

  /**
   * Ensures whitespace-only submissions do not create empty advising-comment
   * records that would clutter a student's history.
   */
  public function testCommentSubmitDoesNotSaveBlankComment(): void {
    global $user;
    $user->cwid = self::TEST_FACULTY_ID;
    $GLOBALS['current_student_id'] = self::TEST_STUDENT_ID;
    $formState = array('values' => array(
      'type' => 'public',
      'term_id' => '202140',
      'comment' => " \n\t ",
    ));

    comments_comment_form_submit(array(), $formState);

    $this->assertSame(array(), comments_get_comments(self::TEST_STUDENT_ID));
  }

  /**
   * Confirms rendering denies users without the base comment-view permission,
   * providing a record-level defense beyond menu access.
   */
  public function testRenderCommentRequiresViewPermission(): void {
    global $user;
    $user->id = 2;
    $user->is_student = FALSE;
    $user->permissions = array();

    $html = comments_render_comment($this->getRenderableComment());

    $this->assertStringContainsString('do not have permission to view comments', strip_tags($html));
    $this->assertStringContainsString('<p>', $html);
  }

  /**
   * Confirms an authorized viewer sees the comment's human-readable details
   * and the CSS hooks needed to present comment content and deletion actions.
   */
  public function testRenderCommentShowsVisibleTextAndMeaningfulMarkup(): void {
    global $user;
    $user->id = 2;
    $user->is_student = FALSE;
    $user->permissions = array('view_comments');
    $deleteLink = "<a class='button'>Delete</a>";

    $html = comments_render_comment($this->getRenderableComment(), $deleteLink);
    $text = preg_replace('/\s+/', ' ', trim(strip_tags($html)));

    $this->assertStringContainsString('Anyone (incl. student) comment by Lisa Tester', $text);
    $this->assertStringContainsString('Plan updated', $text);
    $this->assertStringContainsString('Delete', $text);
    $this->assertStringContainsString("class='comment-comment comment-comment-public'", $html);
    $this->assertStringContainsString("class='comment-text'", $html);
    $this->assertStringContainsString("class='comment-delete'", $html);
  }

  /**
   * Ensures a user with general comment access still cannot render a
   * faculty-only comment without the additional faculty-comments permission.
   */
  public function testRenderCommentHidesFacultyCommentWithoutFacultyPermission(): void {
    global $user;
    $user->id = 2;
    $user->is_student = FALSE;
    $user->permissions = array('view_comments');
    $comment = $this->getRenderableComment();
    $comment['access_type'] = 'faculty';

    $html = comments_render_comment($comment);

    $this->assertStringContainsString('marked as faculty-only', strip_tags($html));
  }

  /**
   * Ensures a student cannot use a public-comment rendering path to view a
   * different student's record.
   */
  public function testRenderCommentPreventsStudentFromViewingAnotherStudentsComment(): void {
    global $user;
    $user->id = 2;
    $user->cwid = 'OTHER_STUDENT';
    $user->is_student = TRUE;
    $user->permissions = array('view_comments');

    $html = comments_render_comment($this->getRenderableComment());

    $this->assertStringContainsString('saved for a different student', strip_tags($html));
  }

  /**
   * Confirms summary generation reports no result when the advisor has no
   * saved, usable session for the requested student.
   */
  public function testAdvisingSummaryReturnsFalseWhenNoValidSessionExists(): void {
    $this->assertFalse(comments_get_most_recent_advising_summary(
      self::FIXTURE_STUDENT_ID,
      self::TEST_FACULTY_ID
    ));
  }

  /**
   * Preserves support for pre-token advising sessions so their recommended
   * courses can still be turned into an editable comment summary.
   */
  public function testAdvisingSummaryUsesLegacySessionWithoutToken(): void {
    $this->insertSession(991100, '202140', 1000);
    $this->insertAdvisedCourse(991200, 991100, 122022, 3);

    $summary = comments_get_most_recent_advising_summary(
      self::FIXTURE_STUDENT_ID,
      self::TEST_FACULTY_ID
    );

    $this->assertStringContainsString('I met with Test Student to review their academic plan', $summary);
    $this->assertStringContainsString('Fall of 2020', $summary);
    $this->assertStringContainsString('- ENGL 1001', $summary);
    $this->assertStringContainsString('(3 hours).', $summary);
    $this->assertStringContainsString('Additional notes:', $summary);
  }

  /**
   * Confirms one logical multi-term advising action is regrouped by token and
   * rendered in term order, including advisor-added-course context.
   */
  public function testAdvisingSummaryGroupsTokenSessionsInTermOrder(): void {
    $this->insertSession(991100, '202140', 2000, 'comments-test-token');
    $this->insertSession(991101, '202060', 1000, 'comments-test-token');
    $this->insertAdvisedCourse(991200, 991100, 122022, 3);
    $this->insertAdvisedCourse(
      991201,
      991101,
      649084,
      3,
      DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED
    );

    $summary = comments_get_most_recent_advising_summary(
      self::FIXTURE_STUDENT_ID,
      self::TEST_FACULTY_ID
    );

    $springPosition = strpos($summary, 'Spring of 2020');
    $fallPosition = strpos($summary, 'Fall of 2020');
    $this->assertNotFalse($springPosition);
    $this->assertNotFalse($fallPosition);
    $this->assertLessThan($fallPosition, $springPosition);
    $this->assertStringContainsString('- MATH 1013', $summary);
    $this->assertStringContainsString('I manually added MATH 1013 because:', $summary);
    $this->assertStringContainsString('- ENGL 1001', $summary);
  }

  /**
   * Ensures a newer non-final session cannot displace the student's latest
   * valid saved advising action when generating a summary.
   */
  public function testAdvisingSummaryIgnoresNewerDraftEmptyAndDeletedSessions(): void {
    $this->insertSession(991100, '202140', 1000);
    $this->insertSession(991101, '202140', 4000, NULL, 1, 1);
    $this->insertSession(991102, '202140', 3000, NULL, 1, 0, 1);
    $this->insertSession(991103, '202140', 2000, NULL, 1, 0, 0, 1);
    $this->insertAdvisedCourse(991200, 991100, 122022, 3);

    $summary = comments_get_most_recent_advising_summary(
      self::FIXTURE_STUDENT_ID,
      self::TEST_FACULTY_ID
    );

    $this->assertStringContainsString('review their academic plan', $summary);
    $this->assertStringNotContainsString('explore a What If academic plan', $summary);
  }

  /**
   * Confirms What If summaries clearly state their planning-only status so
   * they are not mistaken for changes to the student's declared program.
   */
  public function testAdvisingSummaryDescribesWhatIfSession(): void {
    $this->insertSession(991100, '202140', 1000, NULL, 1);
    $this->insertAdvisedCourse(991200, 991100, 122022, 3);

    $summary = comments_get_most_recent_advising_summary(
      self::FIXTURE_STUDENT_ID,
      self::TEST_FACULTY_ID
    );

    $this->assertStringContainsString('explore a What If academic plan', $summary);
    $this->assertStringContainsString("does not change the student's current declared program", $summary);
    $this->assertStringContainsString('Rationale for exploring this program or plan:', $summary);
  }

  private function insertStandardCommentRecords(): void {
    $this->insertComment(991001, self::TEST_STUDENT_ID, 'public', 100, 0, 'Older public comment');
    $this->insertComment(991002, self::TEST_STUDENT_ID, 'faculty', 300, 0, 'Faculty comment');
    $this->insertComment(991003, self::TEST_STUDENT_ID, 'audit private', 200, 0, 'Audit comment');
    $this->insertComment(991004, self::TEST_STUDENT_ID, 'public', 400, 1, 'Deleted public comment');
    $this->insertComment(991005, 'OTHER_COMMENTS_STUDENT', 'public', 500, 0, 'Other student comment');
  }

  private function insertComment(
    int $id,
    string $studentId,
    string $accessType,
    int $posted,
    int $deleteFlag,
    string $comment
  ): void {
    db_query(
      "INSERT INTO advising_comments
       (id, student_id, faculty_id, term_id, comment, posted, access_type, delete_flag)
       VALUES (?, ?, ?, '202140', ?, ?, ?, ?)",
      array($id, $studentId, self::TEST_FACULTY_ID, $comment, $posted, $accessType, $deleteFlag)
    );
  }

  private function getRenderableComment(): array {
    return array(
      'id' => 991001,
      'student_id' => self::TEST_STUDENT_ID,
      'faculty_id' => '55588992',
      'term_id' => '202140',
      'comment' => '<strong>Plan</strong> updated',
      'posted' => 1700000000,
      'access_type' => 'public',
      'delete_flag' => 0,
    );
  }

  private function insertSession(
    int $id,
    string $termId,
    int $posted,
    ?string $token = NULL,
    int $isWhatIf = 0,
    int $isDraft = 0,
    int $isEmpty = 0,
    int $deleteFlag = 0
  ): void {
    db_query(
      "INSERT INTO advising_sessions
       (advising_session_id, student_id, faculty_id, term_id, degree_id,
        major_code_csv, catalog_year, posted, is_whatif, is_draft, is_empty,
        advising_session_token, delete_flag, most_recent_session)
       VALUES (?, ?, ?, ?, ?, 'COSC', 2020, ?, ?, ?, ?, ?, ?, 0)",
      array(
        $id,
        self::FIXTURE_STUDENT_ID,
        self::TEST_FACULTY_ID,
        $termId,
        self::DEGREE_ID,
        $posted,
        $isWhatIf,
        $isDraft,
        $isEmpty,
        $token,
        $deleteFlag,
      )
    );
  }

  private function insertAdvisedCourse(
    int $id,
    int $sessionId,
    int $courseId,
    float $hours,
    int $semesterNum = 0
  ): void {
    db_query(
      "INSERT INTO advised_courses
       (id, advising_session_id, course_id, entry_value, semester_num,
        group_id, var_hours, term_id, degree_id)
       VALUES (?, ?, ?, '', ?, '', ?, '', ?)",
      array($id, $sessionId, $courseId, $semesterNum, $hours, self::DEGREE_ID)
    );
  }

  private function clearTestRecords(): void {
    db_query(
      "DELETE FROM advised_courses
       WHERE id BETWEEN ? AND ?
       OR advising_session_id BETWEEN ? AND ?",
      array(
        self::ADVISED_COURSE_ID_START,
        self::ADVISED_COURSE_ID_END,
        self::SESSION_ID_START,
        self::SESSION_ID_END,
      )
    );
    db_query(
      "DELETE FROM advising_sessions WHERE advising_session_id BETWEEN ? AND ?",
      array(self::SESSION_ID_START, self::SESSION_ID_END)
    );
    db_query(
      "DELETE FROM advising_comments
       WHERE id BETWEEN ? AND ? OR student_id = ?",
      array(self::COMMENT_ID_START, self::COMMENT_ID_END, self::TEST_STUDENT_ID)
    );
  }
}
