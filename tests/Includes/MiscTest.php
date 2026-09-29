<?php

class MiscTest extends FlightPathTestCase
{
  public function testFriendlyTimezone(): void
  {
    $this->assertSame("Central Time - US & Canada", friendly_timezone("America/Chicago"));
    $this->assertSame("Pacific Time - US & Canada", friendly_timezone("America/Los_Angeles"));
    $this->assertSame("Eastern Time - US & Canada", friendly_timezone("America/New_York"));
    $this->assertSame("America/Nowhere", friendly_timezone("America/Nowhere"));
  }

  public function testUtf8EncodeAndDecode(): void
  {
    // These functions intentionally replicate the old PHP utf8_encode()/decode()
    // behavior, which is ISO-8859-1 <-> UTF-8 rather than general UTF-8 conversion.
    $latin1 = "\xE9";
    $utf8 = "\xC3\xA9";

    $this->assertSame($utf8, fp_utf8_encode($latin1));
    $this->assertSame($latin1, fp_utf8_decode($utf8));
    $this->assertSame("Hello", fp_utf8_encode("Hello"));
    $this->assertSame("Hello", fp_utf8_decode("Hello"));
  }

  public function testStringCompatibilityFunctions(): void
  {
    $this->assertTrue(str_starts_with("FlightPath", "Flight"));
    $this->assertFalse(str_starts_with("FlightPath", "Path"));
    $this->assertTrue(str_ends_with("FlightPath", "Path"));
    $this->assertFalse(str_ends_with("FlightPath", "Flight"));
    $this->assertTrue(str_contains("FlightPath Academics", "Path"));
    $this->assertFalse(str_contains("FlightPath Academics", "CRM"));
  }


  public function testConvertTimeFromUtcToLocalTimezone(): void
  {
    $timestamp = time();

    $expected = (new DateTime("@{$timestamp}"))->setTimezone(new DateTimeZone("America/Chicago"))->format("Y-m-d H:i:s");

    $actual = convert_time($timestamp, "UTC", "America/Chicago", "Y-m-d H:i:s");

    $this->assertSame($expected, $actual);
  }


  public function testConvertTimeWithSameTimezoneDoesNotChangeTimestamp(): void
  {
    $date = new DateTime("2026-01-15 12:00:00", new DateTimeZone("UTC"));
    $timestamp = $date->getTimestamp();

    $actual = convert_time($timestamp, "UTC", "UTC", "Y-m-d H:i:s");

    $this->assertSame("2026-01-15 12:00:00", $actual);
  }


  public function testIsSerializedString(): void
  {
    $this->assertTrue(is_serialized_string("b:0;"));
    $this->assertTrue(is_serialized_string(serialize("hello")));
    $this->assertTrue(is_serialized_string(serialize(["one", "two"])));
    $this->assertFalse(is_serialized_string("hello"));
    $this->assertFalse(is_serialized_string(""));
  }

  public function testConvertTimeWithoutFormatting(): void
  {
    $timestamp = 1609459200;

    $this->assertSame($timestamp, convert_time($timestamp, "UTC", "UTC"));
  }

  public function testConvertTimeWithFormatting(): void
  {
    $timestamp = 1609459200;

    $this->assertSame("2021-01-01", convert_time($timestamp, "UTC", "UTC", "Y-m-d"));
  }

  public function testConvertTimeReturnsFalseForEmptyTime(): void
  {
    $this->assertFalse(convert_time(0, "UTC", "UTC"));
  }

  public function testGetRandomString(): void
  {
    $result = fp_get_random_string(20);

    $this->assertSame(20, strlen($result));
    $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $result);
  }

  public function testGetRandomStringCanGenerateOnlyNumericCharacters(): void
  {
    $result = fp_get_random_string(20, false, true, false);

    $this->assertSame(20, strlen($result));
    $this->assertMatchesRegularExpression('/^[0-9]+$/', $result);
  }

  public function testGetRandomStringCanGenerateOnlyAlphabeticCharacters(): void
  {
    $result = fp_get_random_string(20, true, false, false);

    $this->assertSame(20, strlen($result));
    $this->assertMatchesRegularExpression('/^[a-zA-Z]+$/', $result);
  }

  public function testNoHtmlXss(): void
  {
    $this->assertSame("&lt;script&gt;alert(&#039;x&#039;);&lt;/script&gt;", fp_no_html_xss("<script>alert('x');</script>"));
  }

  public function testFilterPlainRemovesHtml(): void
  {
    $this->assertSame("Hello world", filter_plain("<strong>Hello</strong> world"));
    $this->assertSame("Hello world", filter_plain("  <strong>Hello</strong> world  "));
  }

  public function testFilterMarkupPlainRemovesHtml(): void
  {
    $this->assertSame("Hello world", filter_markup("<strong>Hello</strong> world", "plain"));
  }

  public function testFilterMarkupBasicAllowsSafeTags(): void
  {
    $result = filter_markup("<strong>Hello</strong> <em>world</em>", "basic");

    $this->assertStringContainsString("<strong>Hello</strong>", $result);
    $this->assertStringContainsString("<em>world</em>", $result);
  }

  public function testFilterMarkupBasicRemovesDisallowedTags(): void
  {
    $result = filter_markup("<script>alert('x')</script><strong>Hello</strong>", "basic");

    $this->assertStringNotContainsString("<script>", $result);
    $this->assertStringNotContainsString("</script>", $result);
    $this->assertStringContainsString("<strong>Hello</strong>", $result);
  }

  public function testFilterXssRemovesDangerousProtocol(): void
  {
    $result = filter_xss('<a href="javascript:alert(1)">Click</a>', ["a"]);

    $this->assertStringNotContainsString("javascript:", strtolower($result));
    $this->assertStringContainsString("<a", $result);
  }

  public function testFilterXssRemovesEventHandlerAndStyleAttributes(): void
  {
    $result = filter_xss('<div onclick="alert(1)" style="color:red" class="safe">Hello</div>', ["div"]);

    $this->assertStringNotContainsString("onclick", strtolower($result));
    $this->assertStringNotContainsString("style=", strtolower($result));
    $this->assertStringContainsString('class="safe"', $result);
  }

  public function testStripDangerousProtocols(): void
  {
    $this->assertSame("http://example.com", fp_strip_dangerous_protocols("http://example.com"));
    $this->assertSame("example.com", fp_strip_dangerous_protocols("javascript:example.com"));
    $this->assertSame("example.com", fp_strip_dangerous_protocols("JAVASCRIPT:example.com"));
    $this->assertSame("/some/path", fp_strip_dangerous_protocols("/some/path"));
  }

  public function testValidateUtf8(): void
  {
    $this->assertTrue(fp_validate_utf8(""));
    $this->assertTrue(fp_validate_utf8("Hello"));
    $this->assertTrue(fp_validate_utf8("Café"));
    $this->assertFalse(fp_validate_utf8("\xFF\xFE"));
  }

  public function testGetMachineReadable(): void
  {
    $this->assertSame("Computer_Science", fp_get_machine_readable("Computer Science"));
    $this->assertSame("Computer_Science", fp_get_machine_readable("Computer & Science"));
    $this->assertSame("COSC_1010", fp_get_machine_readable("COSC 1010"));
    $this->assertSame("", fp_get_machine_readable(""));
  }

  public function testSpaceCsv(): void
  {
    $this->assertSame("one, two, three", fp_space_csv("one,two,three"));
    $this->assertSame("one, two, three", fp_space_csv(" one,  two,   three "));
  }

  public function testCsvToArray(): void
  {
    $this->assertSame(["one", "two", "three"], csv_to_array("one, two ,three"));
  }

  public function testCsvToFormApiArray(): void
  {
    $result = csv_to_form_api_array("Computer Science, Mathematics, English");

    $this->assertSame("Computer Science", $result["computer_science"]);
    $this->assertSame("Mathematics", $result["mathematics"]);
    $this->assertSame("English", $result["english"]);
  }

  public function testCsvMultilineToFormApiArray(): void
  {
    $result = csv_multiline_to_form_api_array("foo ~ Foo Value\nbar ~ Bar Value");

    $this->assertSame(["foo" => "Foo Value", "bar" => "Bar Value"], $result);
  }

  public function testCsvMultilineToArrayKeepsFirstRowByDefault(): void
  {
    $csv = "subject,course,hours\nCOSC,1010,3\nMATH,1010,4";

    $result = csv_multiline_to_array($csv);

    $this->assertSame("subject", $result[0]["subject"]);
    $this->assertSame("course", $result[0]["course"]);
    $this->assertSame("hours", $result[0]["hours"]);
    $this->assertSame("COSC", $result[1]["subject"]);
    $this->assertSame("1010", $result[1]["course"]);
    $this->assertSame("3", $result[1]["hours"]);
  }

  public function testCsvMultilineToArrayCanRemoveFirstRow(): void
  {
    $csv = "subject,course,hours\nCOSC,1010,3\nMATH,1010,4";

    $result = csv_multiline_to_array($csv, FALSE);

    $this->assertCount(2, $result);
    $this->assertSame("COSC", $result[0]["subject"]);
    $this->assertSame("MATH", $result[1]["subject"]);
  }


  public function testJoinAndExplodeAssocRoundTrip(): void
  {
    $original = [
      "pet" => "dog",
      "name" => "Rex",
      "age" => 7,
    ];

    $encoded = fp_join_assoc($original);
    $result = fp_explode_assoc($encoded);

    $this->assertSame($original, $result);
  }

  public function testGetShorterCatalogYearRange(): void
  {
    $this->assertSame("08-09", get_shorter_catalog_year_range("2008-2009"));
    $this->assertSame("2008-09", get_shorter_catalog_year_range("2008-2009", false, true));
    $this->assertSame("08-2009", get_shorter_catalog_year_range("2008-2009", true, false));
    $this->assertSame("2008-2009", get_shorter_catalog_year_range("2008-2009", false, false));
  }

  public function testReduceWhitespace(): void
  {
    $this->assertSame("one two three", fp_reduce_whitespace("one  two   three"));
    $this->assertSame("one\ntwo", fp_reduce_whitespace("one\n two"));
  }

  public function testNumberPad(): void
  {
    $this->assertSame("001", fp_number_pad(1, 3));
    $this->assertSame("020", fp_number_pad(20, 3));
    $this->assertSame("1234", fp_number_pad(1234, 3));
  }

  public function testTruncateDecimals(): void
  {
    $this->assertSame("1.99", fp_truncate_decimals(1.99999, 2));
    $this->assertSame("1.20", fp_truncate_decimals(1.2, 2));
    $this->assertSame("10.000", fp_truncate_decimals(10, 3));
  }

  public function testQueryStringEncode(): void
  {
    $query = [
      "name" => "John Doe",
      "major" => "Computer Science",
    ];

    $this->assertSame("name=John%20Doe&major=Computer%20Science", fp_query_string_encode($query));
  }

  public function testQueryStringEncodeHandlesNestedArrays(): void
  {
    $query = [
      "student" => [
        "name" => "John Doe",
        "id" => 123,
      ],
    ];

    $result = fp_query_string_encode($query);

    $this->assertSame("student[name]=John%20Doe&student[id]=123", $result);
  }

  public function testFpTrim(): void
  {
    $this->assertSame("hello", fp_trim("  hello  "));
    $this->assertSame("123", fp_trim(123));
    $this->assertSame("12.5", fp_trim(12.5));
    $this->assertSame("", fp_trim(null));
    $this->assertSame("", fp_trim(false));
  }

  public function testUserIsStudent(): void
  {
    $student = new stdClass();
    $student->is_student = 1;

    $faculty = new stdClass();
    $faculty->is_student = 0;

    $this->assertTrue(fp_user_is_student($student));
    $this->assertFalse(fp_user_is_student($faculty));
  }

  public function testUserHasRole(): void
  {
    $account = new stdClass();
    $account->id = 661;
    $account->roles = ["advisor", "faculty"];

    $this->assertTrue(user_has_role("advisor", $account));
    $this->assertFalse(user_has_role("student", $account));

    $admin = new stdClass();
    $admin->id = 1;
    $admin->roles = [];

    $this->assertTrue(user_has_role("anything", $admin));
  }

  public function testUserHasPermission(): void
  {
    $account = new stdClass();
    $account->id = 661;
    $account->permissions = ["view_students", "edit_advising"];

    $this->assertTrue(user_has_permission("view_students", $account));
    $this->assertFalse(user_has_permission("delete_everything", $account));

    $admin = new stdClass();
    $admin->id = 1;

    $this->assertTrue(user_has_permission("anything", $admin));
  }

  public function testSetPageTabs(): void
  {
    $tabs = [
      ["title" => "Students", "path" => "student-search"],
      ["title" => "Reports", "path" => "reports"],
    ];

    fp_set_page_tabs($tabs);

    $this->assertSame($tabs, $GLOBALS["fp_set_page_tabs"]);
  }

  public function testSetPageSubTabs(): void
  {
    $tabs = [
      ["title" => "Overview", "path" => "overview"],
    ];

    fp_set_page_sub_tabs($tabs);

    $this->assertSame($tabs, $GLOBALS["fp_set_page_sub_tabs"]);
  }

  public function testSetBreadcrumbs(): void
  {
    $breadcrumbs = [
      ["text" => "Students", "path" => "student-search"],
      ["text" => "Profile", "path" => "student-profile"],
    ];

    fp_set_breadcrumbs($breadcrumbs);

    $this->assertSame($breadcrumbs, $GLOBALS["fp_breadcrumbs"]);
  }

  public function testAddBodyClassSanitizesDangerousCharacters(): void
  {
    unset($GLOBALS["fp_add_body_classes"]);

    fp_add_body_class("student-profile<script>bad</script>");

    $this->assertStringContainsString("student-profilescriptbadscript", $GLOBALS["fp_add_body_classes"]);
  }

  public function testAddCssDoesNotDuplicateFiles(): void
  {
    $GLOBALS["fp_extra_css"] = [];

    fp_add_css("/css/test.css");
    fp_add_css("/css/test.css");
    fp_add_css("/css/other.css");

    $this->assertSame(["/css/test.css", "/css/other.css"], $GLOBALS["fp_extra_css"]);
  }

  public function testAddJsDoesNotDuplicateFiles(): void
  {
    $GLOBALS["fp_extra_js"] = [];

    fp_add_js("/js/test.js");
    fp_add_js("/js/test.js");
    fp_add_js("/js/other.js");

    $this->assertSame(["/js/test.js", "/js/other.js"], $GLOBALS["fp_extra_js"]);
  }

  public function testAddJsSettings(): void
  {
    unset($GLOBALS["fp_extra_js_settings"]);

    fp_add_js(["my_color" => "red", "my_path" => "/test"], "setting");

    $this->assertSame(["my_color" => "red", "my_path" => "/test"], $GLOBALS["fp_extra_js_settings"]);
  }


  public function testBasePath(): void
  {
    $original = $GLOBALS["fp_system_settings"]["base_path"];

    try {
      $GLOBALS["fp_system_settings"]["base_path"] = "/test/path/";
      $this->assertSame("/test/path/", base_path());

      $GLOBALS["fp_system_settings"]["base_path"] = "";
      $this->assertSame(".", base_path());

      $GLOBALS["fp_system_settings"]["base_path"] = "/";
      $this->assertSame("", base_path());
    }
    finally {
      $GLOBALS["fp_system_settings"]["base_path"] = $original;
    }
  }


  public function testModuleEnabled(): void
  {
    $GLOBALS["fp_system_settings"]["modules"]["test_module"] = ["enabled" => "1"];

    $this->assertTrue(module_enabled("test_module"));
    $this->assertFalse(module_enabled("module_that_does_not_exist"));

    unset($GLOBALS["fp_system_settings"]["modules"]["test_module"]);
  }

  public function testAlertMessagesDoNotRepeatWhenRequested(): void
  {
    $_SESSION["fp_messages"] = [];

    fp_add_message("Test message", "status", true);
    fp_add_message("Test message", "status", true);

    $this->assertCount(1, $_SESSION["fp_messages"]);
    $this->assertSame("Test message", $_SESSION["fp_messages"][0]["msg"]);
    $this->assertSame("status", $_SESSION["fp_messages"][0]["type"]);
  }

  public function testAlertMessagesCanRepeatByDefault(): void
  {
    $_SESSION["fp_messages"] = [];

    fp_add_message("Test message", "status");
    fp_add_message("Test message", "status");

    $this->assertCount(2, $_SESSION["fp_messages"]);
  }


  public function testUserIsStudentUsesGlobalUserWhenAccountIsOmitted(): void
  {
    global $user;

    $original_user = $user;

    try {
      $user = new stdClass();
      $user->is_student = 1;

      $this->assertTrue(fp_user_is_student());

      $user->is_student = 0;

      $this->assertFalse(fp_user_is_student());
    }
    finally {
      $user = $original_user;
    }
  }


  public function testGetTermsByYearRange(): void
  {
    $result = fp_get_terms_by_year_range(2020, 2020);

    $this->assertIsArray($result);
    $this->assertArrayHasKey(2020, $result);
    $this->assertNotEmpty($result[2020]);

    foreach ($result[2020] as $term_id => $description) {
      $this->assertIsString((string) $term_id);
      $this->assertStringStartsWith("[2020", $description);
    }
  }


  public function testGetTermsByYearRangeCanOmitTermIdFromDescription(): void
  {
    $with_ids = fp_get_terms_by_year_range(2020, 2020, 0, TRUE);
    $without_ids = fp_get_terms_by_year_range(2020, 2020, 0, FALSE);

    $this->assertNotEmpty($with_ids[2020]);
    $this->assertNotEmpty($without_ids[2020]);

    foreach ($without_ids[2020] as $term_id => $description) {
      $this->assertStringStartsNotWith("[", $description);
    }
  }



  public function testGetDepartments(): void
  {
    unset($GLOBALS["fp_cache_departments"]);

    variable_set_for_school("departments", "COSC~Computer Science\nMATH~Mathematics", 0);

    try {
      $result = fp_get_departments(0);

      $this->assertSame("Computer Science", $result["COSC"]);
      $this->assertSame("Mathematics", $result["MATH"]);
    }
    finally {
      variable_delete_for_school("departments", 0);
      unset($GLOBALS["fp_cache_departments"]);
    }
  }

  public function testGetDepartmentsCachesResult(): void
  {
    unset($GLOBALS["fp_cache_departments"]);

    variable_set_for_school("departments", "COSC~Computer Science", 0);

    try {
      $first = fp_get_departments(0);

      variable_set_for_school("departments", "COSC~Changed Name", 0);

      $second = fp_get_departments(0);

      $this->assertSame("Computer Science", $first["COSC"]);
      $this->assertSame("Computer Science", $second["COSC"]);
    }
    finally {
      variable_delete_for_school("departments", 0);
      unset($GLOBALS["fp_cache_departments"]);
    }
  }

  public function testTranslateNumericGrade(): void
  {
    unset($GLOBALS["fp_translate_numeric_grade"]);

    variable_set_for_school("numeric_to_letter_grades", "0~59.99~F\n60~69.99~D\n70~79.99~C\n80~89.99~B\n90~100~A", 0);

    try {
      $this->assertSame("A", fp_translate_numeric_grade("95", 0));
      $this->assertSame("B", fp_translate_numeric_grade("85", 0));
      $this->assertSame("C", fp_translate_numeric_grade("75", 0));
      $this->assertSame("D", fp_translate_numeric_grade("65", 0));
      $this->assertSame("F", fp_translate_numeric_grade("50", 0));
    }
    finally {
      variable_delete_for_school("numeric_to_letter_grades", 0);
      unset($GLOBALS["fp_translate_numeric_grade"]);
    }
  }

  public function testTranslateNumericGradePreservesMidtermSuffix(): void
  {
    unset($GLOBALS["fp_translate_numeric_grade"]);

    variable_set_for_school("numeric_to_letter_grades", "80~89.99~B\n90~100~A", 0);

    try {
      $this->assertSame("B", fp_translate_numeric_grade("85", 0));
      $this->assertSame("BMID", fp_translate_numeric_grade("85MID", 0));
      $this->assertSame("AMID", fp_translate_numeric_grade("95MID", 0));
    }
    finally {
      variable_delete_for_school("numeric_to_letter_grades", 0);
      unset($GLOBALS["fp_translate_numeric_grade"]);
    }
  }

  public function testReArrayFiles(): void
  {
    $file_post = [
      "name" => ["one.txt", "two.txt"],
      "type" => ["text/plain", "text/plain"],
      "tmp_name" => ["/tmp/php1", "/tmp/php2"],
      "error" => [0, 0],
      "size" => [100, 200],
    ];

    $result = fp_re_array_files($file_post);

    $this->assertSame("one.txt", $result[0]["name"]);
    $this->assertSame("two.txt", $result[1]["name"]);
    $this->assertSame("/tmp/php1", $result[0]["tmp_name"]);
    $this->assertSame("/tmp/php2", $result[1]["tmp_name"]);
    $this->assertSame(100, $result[0]["size"]);
    $this->assertSame(200, $result[1]["size"]);
  }



  public function testHttpBuildQueryWithSimpleValues(): void
  {
    $result = fp_http_build_query([
      "name" => "John Doe",
      "age" => 42,
    ]);

    $this->assertSame("name=John%20Doe&age=42", $result);
  }

  public function testHttpBuildQueryWithNestedArray(): void
  {
    $result = fp_http_build_query([
      "student" => [
        "name" => "John Doe",
        "id" => 12345,
      ],
    ]);

    $this->assertSame("student%5Bname%5D=John%20Doe&student%5Bid%5D=12345", $result);
  }

  public function testHttpBuildQueryWithNullValue(): void
  {
    $result = fp_http_build_query([
      "foo" => null,
      "bar" => "value",
    ]);

    $this->assertSame("foo&bar=value", $result);
  }

  public function testHttpBuildQueryPreservesSlashes(): void
  {
    $result = fp_http_build_query([
      "path" => "/student/12345",
      "url" => "foo/bar",
    ]);

    $this->assertSame("path=/student/12345&url=foo/bar", $result);
  }

  public function testHttpBuildQueryWithMixedValues(): void
  {
    $result = fp_http_build_query([
      "student_id" => 12345,
      "path" => "/student/12345",
      "options" => [
        "active" => 1,
        "type" => "student",
      ],
      "empty" => null,
    ]);

    $this->assertSame("student_id=12345&path=/student/12345&options%5Bactive%5D=1&options%5Btype%5D=student&empty", $result);
  }


  public function testHttpBuildQueryWithParent(): void
  {
    $result = fp_http_build_query([
      "name" => "John Doe",
      "id" => 12345,
    ], "student");

    $this->assertSame("student%5Bname%5D=John%20Doe&student%5Bid%5D=12345", $result);
  }


  public function testArg(): void
  {
    $original_request = $_REQUEST;

    try {
      $_REQUEST["q"] = "student-search/12345/edit";

      $this->assertSame("student-search", arg(0));
      $this->assertSame("12345", arg(1));
      $this->assertSame("edit", arg(2));
      $this->assertSame("", arg(3));
    }
    finally {
      $_REQUEST = $original_request;
    }
  }

  public function testArgTrimsWhitespace(): void
  {
    $original_request = $_REQUEST;

    try {
      $_REQUEST["q"] = "  student-search / 12345 / edit  ";

      $this->assertSame("student-search", arg(0));
      $this->assertSame("12345", arg(1));
      $this->assertSame("edit", arg(2));
    }
    finally {
      $_REQUEST = $original_request;
    }
  }

  public function testArgHandlesMissingQueryString(): void
  {
    $original_request = $_REQUEST;

    try {
      unset($_REQUEST["q"]);

      $this->assertSame("", arg(0));
    }
    finally {
      $_REQUEST = $original_request;
    }
  }

  public function testGetTimezones(): void
  {
    $timezones = get_timezones();

    $this->assertIsArray($timezones);
    $this->assertArrayHasKey("America/Chicago", $timezones);
    $this->assertArrayHasKey("America/New_York", $timezones);
    $this->assertArrayHasKey("America/Los_Angeles", $timezones);

    $this->assertSame("America/Chicago - (Central)", $timezones["America/Chicago"]);
    $this->assertSame("America/New York - (Eastern)", $timezones["America/New_York"]);
    $this->assertSame("America/Los Angeles - (Pacific)", $timezones["America/Los_Angeles"]);
  }

  public function testGetTimezonesCanIncludeOffsets(): void
  {
    $timezones = get_timezones(TRUE);

    $this->assertIsArray($timezones);
    $this->assertArrayHasKey("America/Chicago", $timezones);

    $this->assertStringContainsString("America/Chicago", $timezones["America/Chicago"]);
    $this->assertStringContainsString("(Central)", $timezones["America/Chicago"]);
    $this->assertMatchesRegularExpression('/^\(UTC[+-]\d{2}:\d{2}\) /', $timezones["America/Chicago"]);
  }

  public function testHttpBuildQueryWithEmptyArray(): void
  {
    $this->assertSame("", fp_http_build_query([]));
  }

  public function testHttpBuildQueryWithEmptyString(): void
  {
    $result = fp_http_build_query([
      "foo" => "",
      "bar" => "value",
    ]);

    $this->assertSame("foo=&bar=value", $result);
  }


  public function testQueryStringEncodeWithEmptyArray(): void
  {
    $this->assertSame("", fp_query_string_encode([]));
  }

  public function testQueryStringEncodeWithSpecialCharacters(): void
  {
    $query = [
      "name" => "John & Jane",
      "path" => "/student/123",
    ];

    $result = fp_query_string_encode($query);

    $this->assertSame("name=John%20%26%20Jane&path=%2Fstudent%2F123", $result);
  }






} // class











//