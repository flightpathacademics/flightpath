<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for the course_search syllabus upload filename check.
 */
class CourseSearchTest extends FlightPathTestCase {


  public function testDocumentSyllabusIsAllowed(): void {
    $this->assertTrue(course_search_is_allowed_syllabus_filename('ENGL 1001 Syllabus.pdf'));
    $this->assertTrue(course_search_is_allowed_syllabus_filename('notes.DOCX'));
  }

  public function testScriptOrMissingExtensionIsRejected(): void {
    $this->assertFalse(course_search_is_allowed_syllabus_filename('shell.php'));
    $this->assertFalse(course_search_is_allowed_syllabus_filename('shell.pdf.php'));
    $this->assertFalse(course_search_is_allowed_syllabus_filename('shell.phtml'));
    $this->assertFalse(course_search_is_allowed_syllabus_filename('.htaccess'));
    $this->assertFalse(course_search_is_allowed_syllabus_filename('noextension'));
    $this->assertFalse(course_search_is_allowed_syllabus_filename(''));
  }




}