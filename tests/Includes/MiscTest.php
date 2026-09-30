<?php

class MiscTest extends FlightPathTestCase
{
  public function testFriendlyTimezone()
  {
    $this->assertSame("Central Time - US & Canada", friendly_timezone("America/Chicago"));
    $this->assertSame("Pacific Time - US & Canada", friendly_timezone("America/Los_Angeles"));
    $this->assertSame("Eastern Time - US & Canada", friendly_timezone("America/New_York"));
    $this->assertSame("America/Nowhere", friendly_timezone("America/Nowhere"));
  }

  public function testUtf8EncodeAndDecode()
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

  public function testStringCompatibilityFunctions()
  {
    $this->assertTrue(str_starts_with("FlightPath", "Flight"));
    $this->assertFalse(str_starts_with("FlightPath", "Path"));
    $this->assertTrue(str_ends_with("FlightPath", "Path"));
    $this->assertFalse(str_ends_with("FlightPath", "Flight"));
    $this->assertTrue(str_contains("FlightPath Academics", "Path"));
    $this->assertFalse(str_contains("FlightPath Academics", "CRM"));
  }


  public function testConvertTimeFromUtcToLocalTimezone()
  {
    $timestamp = time();

    $expected = (new DateTime("@{$timestamp}"))->setTimezone(new DateTimeZone("America/Chicago"))->format("Y-m-d H:i:s");

    $actual = convert_time($timestamp, "UTC", "America/Chicago", "Y-m-d H:i:s");

    $this->assertSame($expected, $actual);
  }


  public function testConvertTimeWithSameTimezoneDoesNotChangeTimestamp()
  {
    $date = new DateTime("2026-01-15 12:00:00", new DateTimeZone("UTC"));
    $timestamp = $date->getTimestamp();

    $actual = convert_time($timestamp, "UTC", "UTC", "Y-m-d H:i:s");

    $this->assertSame("2026-01-15 12:00:00", $actual);
  }


  public function testIsSerializedString()
  {
    $this->assertTrue(is_serialized_string("b:0;"));
    $this->assertTrue(is_serialized_string(serialize("hello")));
    $this->assertTrue(is_serialized_string(serialize(["one", "two"])));
    $this->assertFalse(is_serialized_string("hello"));
    $this->assertFalse(is_serialized_string(""));
  }

  public function testConvertTimeWithoutFormatting()
  {
    $timestamp = 1609459200;

    $this->assertSame($timestamp, convert_time($timestamp, "UTC", "UTC"));
  }

  public function testConvertTimeWithFormatting()
  {
    $timestamp = 1609459200;

    $this->assertSame("2021-01-01", convert_time($timestamp, "UTC", "UTC", "Y-m-d"));
  }

  public function testConvertTimeReturnsFalseForEmptyTime()
  {
    $this->assertFalse(convert_time(0, "UTC", "UTC"));
  }

  public function testGetRandomString()
  {
    $result = fp_get_random_string(20);

    $this->assertSame(20, strlen($result));
    $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $result);
  }

  public function testGetRandomStringCanGenerateOnlyNumericCharacters()
  {
    $result = fp_get_random_string(20, false, true, false);

    $this->assertSame(20, strlen($result));
    $this->assertMatchesRegularExpression('/^[0-9]+$/', $result);
  }

  public function testGetRandomStringCanGenerateOnlyAlphabeticCharacters()
  {
    $result = fp_get_random_string(20, true, false, false);

    $this->assertSame(20, strlen($result));
    $this->assertMatchesRegularExpression('/^[a-zA-Z]+$/', $result);
  }

  public function testNoHtmlXss()
  {
    $this->assertSame("&lt;script&gt;alert(&#039;x&#039;);&lt;/script&gt;", fp_no_html_xss("<script>alert('x');</script>"));
  }

  public function testFilterPlainRemovesHtml()
  {
    $this->assertSame("Hello world", filter_plain("<strong>Hello</strong> world"));
    $this->assertSame("Hello world", filter_plain("  <strong>Hello</strong> world  "));
  }

  public function testFilterMarkupPlainRemovesHtml()
  {
    $this->assertSame("Hello world", filter_markup("<strong>Hello</strong> world", "plain"));
  }

  public function testFilterMarkupBasicAllowsSafeTags()
  {
    $result = filter_markup("<strong>Hello</strong> <em>world</em>", "basic");

    $this->assertStringContainsString("<strong>Hello</strong>", $result);
    $this->assertStringContainsString("<em>world</em>", $result);
  }

  public function testFilterMarkupBasicRemovesDisallowedTags()
  {
    $result = filter_markup("<script>alert('x')</script><strong>Hello</strong>", "basic");

    $this->assertStringNotContainsString("<script>", $result);
    $this->assertStringNotContainsString("</script>", $result);
    $this->assertStringContainsString("<strong>Hello</strong>", $result);
  }

  public function testFilterXssRemovesDangerousProtocol()
  {
    $result = filter_xss('<a href="javascript:alert(1)">Click</a>', ["a"]);

    $this->assertStringNotContainsString("javascript:", strtolower($result));
    $this->assertStringContainsString("<a", $result);
  }

  public function testFilterXssRemovesEventHandlerAndStyleAttributes()
  {
    $result = filter_xss('<div onclick="alert(1)" style="color:red" class="safe">Hello</div>', ["div"]);

    $this->assertStringNotContainsString("onclick", strtolower($result));
    $this->assertStringNotContainsString("style=", strtolower($result));
    $this->assertStringContainsString('class="safe"', $result);
  }

  public function testStripDangerousProtocols()
  {
    $this->assertSame("http://example.com", fp_strip_dangerous_protocols("http://example.com"));
    $this->assertSame("example.com", fp_strip_dangerous_protocols("javascript:example.com"));
    $this->assertSame("example.com", fp_strip_dangerous_protocols("JAVASCRIPT:example.com"));
    $this->assertSame("/some/path", fp_strip_dangerous_protocols("/some/path"));
  }

  public function testValidateUtf8()
  {
    $this->assertTrue(fp_validate_utf8(""));
    $this->assertTrue(fp_validate_utf8("Hello"));
    $this->assertTrue(fp_validate_utf8("Café"));
    $this->assertFalse(fp_validate_utf8("\xFF\xFE"));
  }

  public function testGetMachineReadable()
  {
    $this->assertSame("Computer_Science", fp_get_machine_readable("Computer Science"));
    $this->assertSame("Computer_Science", fp_get_machine_readable("Computer & Science"));
    $this->assertSame("COSC_1010", fp_get_machine_readable("COSC 1010"));
    $this->assertSame("", fp_get_machine_readable(""));
  }

  public function testSpaceCsv()
  {
    $this->assertSame("one, two, three", fp_space_csv("one,two,three"));
    $this->assertSame("one, two, three", fp_space_csv(" one,  two,   three "));
  }

  public function testCsvToArray()
  {
    $this->assertSame(["one", "two", "three"], csv_to_array("one, two ,three"));
  }

  public function testCsvToFormApiArray()
  {
    $result = csv_to_form_api_array("Computer Science, Mathematics, English");

    $this->assertSame("Computer Science", $result["computer_science"]);
    $this->assertSame("Mathematics", $result["mathematics"]);
    $this->assertSame("English", $result["english"]);
  }

  public function testCsvMultilineToFormApiArray()
  {
    $result = csv_multiline_to_form_api_array("foo ~ Foo Value\nbar ~ Bar Value");

    $this->assertSame(["foo" => "Foo Value", "bar" => "Bar Value"], $result);
  }

  public function testCsvMultilineToArrayKeepsFirstRowByDefault()
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

  public function testCsvMultilineToArrayCanRemoveFirstRow()
  {
    $csv = "subject,course,hours\nCOSC,1010,3\nMATH,1010,4";

    $result = csv_multiline_to_array($csv, FALSE);

    $this->assertCount(2, $result);
    $this->assertSame("COSC", $result[0]["subject"]);
    $this->assertSame("MATH", $result[1]["subject"]);
  }


  public function testJoinAndExplodeAssocRoundTrip()
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

  public function testGetShorterCatalogYearRange()
  {
    $this->assertSame("08-09", get_shorter_catalog_year_range("2008-2009"));
    $this->assertSame("2008-09", get_shorter_catalog_year_range("2008-2009", false, true));
    $this->assertSame("08-2009", get_shorter_catalog_year_range("2008-2009", true, false));
    $this->assertSame("2008-2009", get_shorter_catalog_year_range("2008-2009", false, false));
  }

  public function testReduceWhitespace()
  {
    $this->assertSame("one two three", fp_reduce_whitespace("one  two   three"));
    $this->assertSame("one\ntwo", fp_reduce_whitespace("one\n two"));
  }

  public function testNumberPad()
  {
    $this->assertSame("001", fp_number_pad(1, 3));
    $this->assertSame("020", fp_number_pad(20, 3));
    $this->assertSame("1234", fp_number_pad(1234, 3));
  }

  public function testTruncateDecimals()
  {
    $this->assertSame("1.99", fp_truncate_decimals(1.99999, 2));
    $this->assertSame("1.20", fp_truncate_decimals(1.2, 2));
    $this->assertSame("10.000", fp_truncate_decimals(10, 3));
  }

  public function testQueryStringEncode()
  {
    $query = [
      "name" => "John Doe",
      "major" => "Computer Science",
    ];

    $this->assertSame("name=John%20Doe&major=Computer%20Science", fp_query_string_encode($query));
  }

  public function testQueryStringEncodeHandlesNestedArrays()
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

  public function testFpTrim()
  {
    $this->assertSame("hello", fp_trim("  hello  "));
    $this->assertSame("123", fp_trim(123));
    $this->assertSame("12.5", fp_trim(12.5));
    $this->assertSame("", fp_trim(null));
    $this->assertSame("", fp_trim(false));
  }

  public function testUserIsStudent()
  {
    $student = new stdClass();
    $student->is_student = 1;

    $faculty = new stdClass();
    $faculty->is_student = 0;

    $this->assertTrue(fp_user_is_student($student));
    $this->assertFalse(fp_user_is_student($faculty));
  }

  public function testUserHasRole()
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

  public function testUserHasPermission()
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

  public function testSetPageTabs()
  {
    $tabs = [
      ["title" => "Students", "path" => "student-search"],
      ["title" => "Reports", "path" => "reports"],
    ];

    fp_set_page_tabs($tabs);

    $this->assertSame($tabs, $GLOBALS["fp_set_page_tabs"]);
  }

  public function testSetPageSubTabs()
  {
    $tabs = [
      ["title" => "Overview", "path" => "overview"],
    ];

    fp_set_page_sub_tabs($tabs);

    $this->assertSame($tabs, $GLOBALS["fp_set_page_sub_tabs"]);
  }

  public function testSetBreadcrumbs()
  {
    $breadcrumbs = [
      ["text" => "Students", "path" => "student-search"],
      ["text" => "Profile", "path" => "student-profile"],
    ];

    fp_set_breadcrumbs($breadcrumbs);

    $this->assertSame($breadcrumbs, $GLOBALS["fp_breadcrumbs"]);
  }

  public function testAddBodyClassSanitizesDangerousCharacters()
  {
    unset($GLOBALS["fp_add_body_classes"]);

    fp_add_body_class("student-profile<script>bad</script>");

    $this->assertStringContainsString("student-profilescriptbadscript", $GLOBALS["fp_add_body_classes"]);
  }

  public function testAddCssDoesNotDuplicateFiles()
  {
    $GLOBALS["fp_extra_css"] = [];

    fp_add_css("/css/test.css");
    fp_add_css("/css/test.css");
    fp_add_css("/css/other.css");

    $this->assertSame(["/css/test.css", "/css/other.css"], $GLOBALS["fp_extra_css"]);
  }

  public function testAddJsDoesNotDuplicateFiles()
  {
    $GLOBALS["fp_extra_js"] = [];

    fp_add_js("/js/test.js");
    fp_add_js("/js/test.js");
    fp_add_js("/js/other.js");

    $this->assertSame(["/js/test.js", "/js/other.js"], $GLOBALS["fp_extra_js"]);
  }

  public function testAddJsSettings()
  {
    unset($GLOBALS["fp_extra_js_settings"]);

    fp_add_js(["my_color" => "red", "my_path" => "/test"], "setting");

    $this->assertSame(["my_color" => "red", "my_path" => "/test"], $GLOBALS["fp_extra_js_settings"]);
  }


  public function testBasePath()
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


  public function testModuleEnabled()
  {
    $GLOBALS["fp_system_settings"]["modules"]["test_module"] = ["enabled" => "1"];

    $this->assertTrue(module_enabled("test_module"));
    $this->assertFalse(module_enabled("module_that_does_not_exist"));

    unset($GLOBALS["fp_system_settings"]["modules"]["test_module"]);
  }

  public function testAlertMessagesDoNotRepeatWhenRequested()
  {
    $_SESSION["fp_messages"] = [];

    fp_add_message("Test message", "status", true);
    fp_add_message("Test message", "status", true);

    $this->assertCount(1, $_SESSION["fp_messages"]);
    $this->assertSame("Test message", $_SESSION["fp_messages"][0]["msg"]);
    $this->assertSame("status", $_SESSION["fp_messages"][0]["type"]);
  }

  public function testAlertMessagesCanRepeatByDefault()
  {
    $_SESSION["fp_messages"] = [];

    fp_add_message("Test message", "status");
    fp_add_message("Test message", "status");

    $this->assertCount(2, $_SESSION["fp_messages"]);
  }


  public function testUserIsStudentUsesGlobalUserWhenAccountIsOmitted()
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


  public function testGetTermsByYearRange()
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


  public function testGetTermsByYearRangeCanOmitTermIdFromDescription()
  {
    $with_ids = fp_get_terms_by_year_range(2020, 2020, 0, TRUE);
    $without_ids = fp_get_terms_by_year_range(2020, 2020, 0, FALSE);

    $this->assertNotEmpty($with_ids[2020]);
    $this->assertNotEmpty($without_ids[2020]);

    foreach ($without_ids[2020] as $term_id => $description) {
      $this->assertStringStartsNotWith("[", $description);
    }
  }



  public function testGetDepartments()
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

  public function testGetDepartmentsCachesResult()
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

  public function testTranslateNumericGrade()
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

  public function testTranslateNumericGradePreservesMidtermSuffix()
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

  public function testReArrayFiles()
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



  public function testHttpBuildQueryWithSimpleValues()
  {
    $result = fp_http_build_query([
      "name" => "John Doe",
      "age" => 42,
    ]);

    $this->assertSame("name=John%20Doe&age=42", $result);
  }

  public function testHttpBuildQueryWithNestedArray()
  {
    $result = fp_http_build_query([
      "student" => [
        "name" => "John Doe",
        "id" => 12345,
      ],
    ]);

    $this->assertSame("student%5Bname%5D=John%20Doe&student%5Bid%5D=12345", $result);
  }

  public function testHttpBuildQueryWithNullValue()
  {
    $result = fp_http_build_query([
      "foo" => null,
      "bar" => "value",
    ]);

    $this->assertSame("foo&bar=value", $result);
  }

  public function testHttpBuildQueryPreservesSlashes()
  {
    $result = fp_http_build_query([
      "path" => "/student/12345",
      "url" => "foo/bar",
    ]);

    $this->assertSame("path=/student/12345&url=foo/bar", $result);
  }

  public function testHttpBuildQueryWithMixedValues()
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


  public function testHttpBuildQueryWithParent()
  {
    $result = fp_http_build_query([
      "name" => "John Doe",
      "id" => 12345,
    ], "student");

    $this->assertSame("student%5Bname%5D=John%20Doe&student%5Bid%5D=12345", $result);
  }


  public function testArg()
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

  public function testArgTrimsWhitespace()
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

  public function testArgHandlesMissingQueryString()
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

  public function testGetTimezones()
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

  public function testGetTimezonesCanIncludeOffsets()
  {
    $timezones = get_timezones(TRUE);

    $this->assertIsArray($timezones);
    $this->assertArrayHasKey("America/Chicago", $timezones);

    $this->assertStringContainsString("America/Chicago", $timezones["America/Chicago"]);
    $this->assertStringContainsString("(Central)", $timezones["America/Chicago"]);
    $this->assertMatchesRegularExpression('/^\(UTC[+-]\d{2}:\d{2}\) /', $timezones["America/Chicago"]);
  }

  public function testHttpBuildQueryWithEmptyArray()
  {
    $this->assertSame("", fp_http_build_query([]));
  }

  public function testHttpBuildQueryWithEmptyString()
  {
    $result = fp_http_build_query([
      "foo" => "",
      "bar" => "value",
    ]);

    $this->assertSame("foo=&bar=value", $result);
  }


  public function testQueryStringEncodeWithEmptyArray()
  {
    $this->assertSame("", fp_query_string_encode([]));
  }

  public function testQueryStringEncodeWithSpecialCharacters()
  {
    $query = [
      "name" => "John & Jane",
      "path" => "/student/123",
    ];

    $result = fp_query_string_encode($query);

    $this->assertSame("name=John%20%26%20Jane&path=%2Fstudent%2F123", $result);
  }


  public function testGetTimezoneOffset()
  {
    $origin = new DateTime("now", new DateTimeZone("UTC"));
    $remote = new DateTime("now", new DateTimeZone("America/Chicago"));

    $expected = $origin->getOffset() - $remote->getOffset();

    $this->assertSame($expected, get_timezone_offset("America/Chicago", "UTC"));
  }

  public function testGetTimezoneOffsetUsesDefaultTimezoneWhenOriginIsOmitted()
  {
    $original_timezone = date_default_timezone_get();

    try {
      date_default_timezone_set("UTC");

      $origin = new DateTime("now", new DateTimeZone("UTC"));
      $remote = new DateTime("now", new DateTimeZone("America/Chicago"));

      $expected = $origin->getOffset() - $remote->getOffset();

      $this->assertSame($expected, get_timezone_offset("America/Chicago"));
    }
    finally {
      date_default_timezone_set($original_timezone);
    }
  }

  public function testTimerStartAndRead()
  {
    global $timers;

    $name = "test_timer_" . uniqid();
    unset($timers[$name]);

    timer_start($name);
    $elapsed = timer_read($name);

    $this->assertIsFloat($elapsed);
    $this->assertGreaterThanOrEqual(0, $elapsed);
    $this->assertSame(1, $timers[$name]["count"]);
  }

  public function testTimerStartIncrementsCount()
  {
    global $timers;

    $name = "test_timer_" . uniqid();
    unset($timers[$name]);

    timer_start($name);
    timer_start($name);

    $this->assertSame(2, $timers[$name]["count"]);
  }

  public function testTimerReadReturnsNullForUnknownTimer()
  {
    global $timers;

    $name = "does_not_exist_" . uniqid();
    unset($timers[$name]);

    $this->assertNull(timer_read($name));
  }


  public function testGetRandomStringCanReturnEmptyString()
  {
    $this->assertSame("", fp_get_random_string(0));
  }

  public function testGetRandomStringCanIncludeSymbols()
  {
    $result = fp_get_random_string(100, true, true, true);

    $this->assertSame(100, strlen($result));
    $this->assertMatchesRegularExpression('/^[a-zA-Z0-9!@#$%^&*()_+=\-]+$/', $result);
  }


  public function testGetDegreeClassifications()
  {
    $classifications = fp_get_degree_classifications();

    $this->assertIsArray($classifications);

    $this->assertSame("Major", $classifications["levels"][1]["MAJOR"]);
    $this->assertSame("Minor", $classifications["levels"][2]["MINOR"]);
    $this->assertSame("Concentration", $classifications["levels"][3]["CONC"]);

    $this->assertSame(1, $classifications["machine_name_to_level_num"]["MAJOR"]);
    $this->assertSame(2, $classifications["machine_name_to_level_num"]["MINOR"]);
    $this->assertSame(3, $classifications["machine_name_to_level_num"]["CONC"]);
  }

  public function testGetDegreeClassificationDetails()
  {
    $details = fp_get_degree_classification_details("MAJOR");

    $this->assertSame(1, $details["level_num"]);
    $this->assertSame("Major", $details["title"]);
    $this->assertSame("MAJOR", $details["degree_class"]);
  }

  public function testGetDegreeClassificationDetailsForUnknownClass()
  {
    $details = fp_get_degree_classification_details("UNKNOWN_CLASS");

    $this->assertSame(0, $details["level_num"]);
    $this->assertSame("UNKNOWN_CLASS", $details["title"]);
    $this->assertSame("UNKNOWN_CLASS", $details["degree_class"]);
  }

  public function testGetDegreeClassificationDetailsCanReturnEmptyForUnknownClass()
  {
    $details = fp_get_degree_classification_details("UNKNOWN_CLASS", FALSE);

    $this->assertSame([], $details);
  }

  public function testGetTermStructures()
  {
    $name = "term_id_structure";

    $original = variable_get($name, "");

    try {
      variable_set($name, "[Y4]40, Fall, Fall of [Y4-1], Fall '[Y2-1],");
      unset($GLOBALS["fp_cache_get_term_description"]);

      $structures = get_term_structures(0);

      $this->assertArrayHasKey("40", $structures);
      $this->assertSame("40", $structures["40"]["term_suffix"]);
      $this->assertSame("[Y4]40", $structures["40"]["term_def"]);
      $this->assertSame("Fall", $structures["40"]["short"]);
      $this->assertSame("Fall of [Y4-1]", $structures["40"]["full"]);
      $this->assertSame("Fall '[Y2-1]", $structures["40"]["abbr"]);
    }
    finally {
      variable_set($name, $original);
      unset($GLOBALS["fp_cache_get_term_description"]);
    }
  }

  public function testGetTermStructuresReturnsEmptyArrayWhenUnset()
  {
    $name = "term_id_structure";

    $original = variable_get($name, "");

    try {
      variable_delete($name);

      $this->assertSame([], get_term_structures(0));
    }
    finally {
      variable_set($name, $original);
    }
  }

  public function testGetRequirementTypes()
  {
    unset($GLOBALS["fp_temp_cache"]["fp_get_requirement_types"][0]);

    $name = "requirement_types";
    $original = variable_get($name, "");

    try {
      variable_set($name, "g ~ General\nc ~ Core\nm ~ Major\nx ~ Additional");

      $types = fp_get_requirement_types(0);

      $this->assertSame("General", $types["g"]);
      $this->assertSame("Core", $types["c"]);
      $this->assertSame("Major", $types["m"]);
      $this->assertSame("Additional", $types["x"]);
    }
    finally {
      variable_set($name, $original);
      unset($GLOBALS["fp_temp_cache"]["fp_get_requirement_types"][0]);
    }
  }

  public function testGetRequirementTypesAddsRequiredDefaults()
  {
    unset($GLOBALS["fp_temp_cache"]["fp_get_requirement_types"][0]);

    $name = "requirement_types";
    $original = variable_get($name, "");

    try {
      variable_set($name, "g ~ General");

      $types = fp_get_requirement_types(0);

      $this->assertSame("General", $types["g"]);
      $this->assertArrayHasKey("x", $types);
      $this->assertArrayHasKey("e", $types);
      $this->assertArrayHasKey("m", $types);
    }
    finally {
      variable_set($name, $original);
      unset($GLOBALS["fp_temp_cache"]["fp_get_requirement_types"][0]);
    }
  }

  public function testGetRequirementTypesCachesResult()
  {
    unset($GLOBALS["fp_temp_cache"]["fp_get_requirement_types"][0]);

    $name = "requirement_types";
    $original = variable_get($name, "");

    try {
      variable_set($name, "g ~ General");

      $first = fp_get_requirement_types(0);

      variable_set($name, "g ~ Changed");

      $second = fp_get_requirement_types(0);

      $this->assertSame("General", $first["g"]);
      $this->assertSame("General", $second["g"]);
    }
    finally {
      variable_set($name, $original);
      unset($GLOBALS["fp_temp_cache"]["fp_get_requirement_types"][0]);
    }
  }


  public function testFpTokenCreatesAndPersistsSiteToken()
  {
    $original = variable_get("site_token", "");

    try {
      variable_delete("site_token");

      $token = fp_token();

      $this->assertIsString($token);
      $this->assertSame(32, strlen($token));
      $this->assertSame($token, variable_get("site_token", ""));
      $this->assertSame($token, fp_token());
    }
    finally {
      if ($original === "") {
        variable_delete("site_token");
      }
      else {
        variable_set("site_token", $original);
      }
    }
  }

  public function testGetSessionStringCanBeValidated()
  {
    $original_ip = $_SERVER["REMOTE_ADDR"] ?? NULL;

    try {
      $_SERVER["REMOTE_ADDR"] = "127.0.0.1";

      $session_string = fp_get_session_str();

      $this->assertStringContainsString("~_", $session_string);
      $this->assertSame(session_id(), fp_get_session_id_from_str($session_string));
    }
    finally {
      if ($original_ip === NULL) {
        unset($_SERVER["REMOTE_ADDR"]);
      }
      else {
        $_SERVER["REMOTE_ADDR"] = $original_ip;
      }
    }
  }

  public function testGetSessionIdFromStringRejectsInvalidHash()
  {
    $this->assertFalse(fp_get_session_id_from_str(session_id() . "~_invalid"));
  }

  public function testGetSessionIdFromStringRejectsMalformedString()
  {
    $this->assertFalse(fp_get_session_id_from_str("not-a-valid-session-string"));
  }


  public function testTranslationFunctionReplacesVariables()
  {
    $this->assertSame("Hello Richard", t("Hello @name", ["@name" => "Richard"]));
  }

  public function testTranslationFunctionReplacesNullAndFalseWithEmptyString()
  {
    $this->assertSame("Hello ", t("Hello @name", ["@name" => NULL]));
    $this->assertSame("Hello ", t("Hello @name", ["@name" => FALSE]));
  }

  public function testTranslationFunctionItalicizesPercentVariables()
  {
    $this->assertSame("<em>Richard</em>", t("%name", ["%name" => "Richard"]));
  }

  public function testStaticTranslationFunctionMatchesTranslationBehavior()
  {
    $this->assertSame("Hello Richard", st("Hello @name", ["@name" => "Richard"]));
  }

  public function testStaticTranslationFunctionItalicizesPercentVariables()
  {
    $this->assertSame("<em>Richard</em>", st("%name", ["%name" => "Richard"]));
  }

  public function testBaseUrl()
  {
    $original = $GLOBALS["fp_system_settings"]["base_url"];

    try {
      $GLOBALS["fp_system_settings"]["base_url"] = "https://example.com/flightpath";

      $this->assertSame("https://example.com/flightpath", base_url());
    }
    finally {
      $GLOBALS["fp_system_settings"]["base_url"] = $original;
    }
  }

  public function testGetFilesPath()
  {
    $original = $GLOBALS["fp_system_settings"]["file_system_path"];

    try {
      $GLOBALS["fp_system_settings"]["file_system_path"] = "/var/www/flightpath";

      $this->assertSame("/var/www/flightpath/custom/files", fp_get_files_path());
    }
    finally {
      $GLOBALS["fp_system_settings"]["file_system_path"] = $original;
    }
  }

  public function testGetTmpPathUsesConfiguredPath()
  {
    $name = "tmp_path";
    $original = variable_get($name, "/tmp");

    try {
      variable_set($name, "/var/tmp/flightpath-tests");

      $this->assertSame("/var/tmp/flightpath-tests", fp_get_tmp_path());
    }
    finally {
      variable_set($name, $original);
    }
  }


  public function testFpUrlWithCleanUrlsDisabled()
  {
    $original_base_path = $GLOBALS["fp_system_settings"]["base_path"];
    $original_clean_urls = variable_get("clean_urls", FALSE);

    try {
      $GLOBALS["fp_system_settings"]["base_path"] = "/flightpath";
      variable_set("clean_urls", FALSE);

      $this->assertSame("/flightpath/index.php?q=student-search", fp_url("student-search"));
      $this->assertSame("/flightpath/index.php?q=student-search&foo=bar", fp_url("student-search", "foo=bar"));
    }
    finally {
      $GLOBALS["fp_system_settings"]["base_path"] = $original_base_path;
      variable_set("clean_urls", $original_clean_urls);
    }
  }

  public function testFpUrlWithCleanUrlsEnabled()
  {
    $original_base_path = $GLOBALS["fp_system_settings"]["base_path"];
    $original_clean_urls = variable_get("clean_urls", FALSE);

    try {
      $GLOBALS["fp_system_settings"]["base_path"] = "/flightpath";
      variable_set("clean_urls", TRUE);

      $this->assertSame("/flightpath/student-search", fp_url("student-search"));
      $this->assertSame("/flightpath/student-search?foo=bar", fp_url("student-search", "foo=bar"));
    }
    finally {
      $GLOBALS["fp_system_settings"]["base_path"] = $original_base_path;
      variable_set("clean_urls", $original_clean_urls);
    }
  }

  public function testFpUrlCanExcludeBasePath()
  {
    $original_clean_urls = variable_get("clean_urls", FALSE);

    try {
      variable_set("clean_urls", TRUE);

      $this->assertSame("student-search", fp_url("student-search", "", FALSE));
    }
    finally {
      variable_set("clean_urls", $original_clean_urls);
    }
  }

  public function testFpUrlAbsolute()
  {
    $original_base_url = $GLOBALS["fp_system_settings"]["base_url"];
    $original_base_path = $GLOBALS["fp_system_settings"]["base_path"];
    $original_clean_urls = variable_get("clean_urls", FALSE);

    try {
      $GLOBALS["fp_system_settings"]["base_url"] = "https://example.com/flightpath";
      $GLOBALS["fp_system_settings"]["base_path"] = "/flightpath";
      variable_set("clean_urls", TRUE);

      $this->assertSame("https://example.com/flightpath/student-search?foo=bar", fp_url_absolute("student-search", "foo=bar"));
    }
    finally {
      $GLOBALS["fp_system_settings"]["base_url"] = $original_base_url;
      $GLOBALS["fp_system_settings"]["base_path"] = $original_base_path;
      variable_set("clean_urls", $original_clean_urls);
    }
  }

  public function testLinkHelperCreatesLink()
  {
    $original_base_path = $GLOBALS["fp_system_settings"]["base_path"];
    $original_clean_urls = variable_get("clean_urls", FALSE);

    try {
      $GLOBALS["fp_system_settings"]["base_path"] = "/flightpath";
      variable_set("clean_urls", TRUE);

      $result = l("View Student", "student/12345", "foo=bar", ["class" => "student-link"]);

      $this->assertSame('<a href="/flightpath/student/12345?foo=bar" class="student-link" >View Student</a>', $result);
    }
    finally {
      $GLOBALS["fp_system_settings"]["base_path"] = $original_base_path;
      variable_set("clean_urls", $original_clean_urls);
    }
  }

  public function testScreenIsMobileDetectsAndroid()
  {
    $original_agent = $_SERVER["HTTP_USER_AGENT"] ?? NULL;
    $original_mobile = $GLOBALS["fp_page_is_mobile"] ?? NULL;

    try {
      unset($GLOBALS["fp_page_is_mobile"]);
      $_SERVER["HTTP_USER_AGENT"] = "Mozilla/5.0 Android 14";

      $this->assertTrue(fp_screen_is_mobile());
    }
    finally {
      if ($original_agent === NULL) {
        unset($_SERVER["HTTP_USER_AGENT"]);
      }
      else {
        $_SERVER["HTTP_USER_AGENT"] = $original_agent;
      }

      if ($original_mobile === NULL) {
        unset($GLOBALS["fp_page_is_mobile"]);
      }
      else {
        $GLOBALS["fp_page_is_mobile"] = $original_mobile;
      }
    }
  }

  public function testScreenIsMobileReturnsFalseForDesktopBrowser()
  {
    $original_agent = $_SERVER["HTTP_USER_AGENT"] ?? NULL;
    $original_mobile = $GLOBALS["fp_page_is_mobile"] ?? NULL;

    try {
      unset($GLOBALS["fp_page_is_mobile"]);
      $_SERVER["HTTP_USER_AGENT"] = "Mozilla/5.0 Windows NT 10.0 Win64 x64";

      $this->assertFalse(fp_screen_is_mobile());
    }
    finally {
      if ($original_agent === NULL) {
        unset($_SERVER["HTTP_USER_AGENT"]);
      }
      else {
        $_SERVER["HTTP_USER_AGENT"] = $original_agent;
      }

      if ($original_mobile === NULL) {
        unset($GLOBALS["fp_page_is_mobile"]);
      }
      else {
        $GLOBALS["fp_page_is_mobile"] = $original_mobile;
      }
    }
  }

  public function testScreenIsMobileUsesCachedResult()
  {
    $original_mobile = $GLOBALS["fp_page_is_mobile"] ?? NULL;

    try {
      $GLOBALS["fp_page_is_mobile"] = TRUE;

      $this->assertTrue(fp_screen_is_mobile());

      $GLOBALS["fp_page_is_mobile"] = FALSE;

      $this->assertFalse(fp_screen_is_mobile());
    }
    finally {
      if ($original_mobile === NULL) {
        unset($GLOBALS["fp_page_is_mobile"]);
      }
      else {
        $GLOBALS["fp_page_is_mobile"] = $original_mobile;
      }
    }
  }


  public function testFpStrEndsWith()
  {
    $this->assertTrue(fp_str_ends_with("FlightPath", "Path"));
    $this->assertFalse(fp_str_ends_with("FlightPath", "Flight"));
    $this->assertFalse(fp_str_ends_with("FlightPath", ""));
  }

  public function testJoinAssocWithCustomSeparators()
  {
    $result = fp_join_assoc(["first" => "one", "second" => "two"], ";", ":");

    $this->assertSame("first:one;second:two", $result);
  }

  public function testExplodeAssocConvertsNumericValuesBackToNumbers()
  {
    $result = fp_explode_assoc("hours_S-3,gpa_S-3.5,name_S-Richard");

    $this->assertSame(3, $result["hours"]);
    $this->assertSame(3.5, $result["gpa"]);
    $this->assertSame("Richard", $result["name"]);
  }

  public function testJoinAssocWithEmptyArray()
  {
    $this->assertSame("", fp_join_assoc([]));
  }

  public function testExplodeAssocIgnoresEmptyEntries()
  {
    $result = fp_explode_assoc("one_S-1,,two_S-2,");

    $this->assertSame(["one" => 1, "two" => 2], $result);
  }

  public function testGetModuleDetailsForFlightPathCore()
  {
    $result = fp_get_module_details("flightpath");

    $this->assertIsArray($result);
    $this->assertSame("FlightPath (Core)", $result["info"]["name"]);
    $this->assertSame(FLIGHTPATH_VERSION, $result["version"]);
  }

  public function testLoadDegreeCachesTheDegreePlan()
  {
    unset($GLOBALS["fp_temp_cache"]["fp_load_degree"]);

    $first = fp_load_degree(5450264);
    $second = fp_load_degree(5450264);

    $this->assertInstanceOf(DegreePlan::class, $first);
    $this->assertSame($first, $second);
  }

  public function testHtmlPrintRDisplaysSimpleValues()
  {
    $result = fp_html_print_r("Hello", "message");

    $this->assertStringContainsString("message", $result);
    $this->assertStringContainsString("Hello", $result);
    $this->assertStringContainsString("(string", $result);
  }

  public function testHtmlPrintRDisplaysArrays()
  {
    $result = fp_html_print_r(["name" => "Rex"], "pet");

    $this->assertStringContainsString("pet", $result);
    $this->assertStringContainsString("(array", $result);
    $this->assertStringContainsString("name", $result);
    $this->assertStringContainsString("Rex", $result);
  }

  public function testHtmlPrintRDisplaysBooleanValues()
  {
    $true_result = fp_html_print_r(TRUE, "enabled");
    $false_result = fp_html_print_r(FALSE, "enabled");

    $this->assertStringContainsString("TRUE", $true_result);
    $this->assertStringContainsString("FALSE", $false_result);
  }

  public function testHtmlPrintRStopsAtMaximumDepth()
  {
    $value = ["level" => ["nested" => "value"]];

    $result = fp_html_print_r($value, "test", 0, 0);

    $this->assertStringContainsString("Depth too great", $result);
  }

  public function testQueryStringEncodeCanExcludeKeys()
  {
    $query = [
      "name" => "John Doe",
      "major" => "Computer Science",
      "student_id" => 12345,
    ];

    $result = fp_query_string_encode($query, ["major"]);

    $this->assertSame("name=John%20Doe&student_id=12345", $result);
  }


  public function testQueryStringEncodeCanExcludeNestedKeys()
  {
    $query = [
      "student" => [
        "name" => "John Doe",
        "id" => 12345,
      ],
    ];

    $result = fp_query_string_encode($query, ["student[id]"]);

    $this->assertSame("student[name]=John%20Doe", $result);
  }


  public function testMapPhpErrorCode()
  {
    $this->assertSame("Fatal Error", _fp_map_php_error_code(E_ERROR));
    $this->assertSame("Fatal Error", _fp_map_php_error_code(E_PARSE));
    $this->assertSame("Fatal Error", _fp_map_php_error_code(E_CORE_ERROR));
    $this->assertSame("Fatal Error", _fp_map_php_error_code(E_COMPILE_ERROR));
    $this->assertSame("Fatal Error", _fp_map_php_error_code(E_USER_ERROR));

    $this->assertSame("Warning", _fp_map_php_error_code(E_WARNING));
    $this->assertSame("Warning", _fp_map_php_error_code(E_USER_WARNING));
    $this->assertSame("Warning", _fp_map_php_error_code(E_COMPILE_WARNING));
    $this->assertSame("Warning", _fp_map_php_error_code(E_RECOVERABLE_ERROR));

    $this->assertSame("Notice", _fp_map_php_error_code(E_NOTICE));
    $this->assertSame("Notice", _fp_map_php_error_code(E_USER_NOTICE));

    $this->assertSame("Strict", _fp_map_php_error_code(E_STRICT));
    $this->assertSame("Deprecated", _fp_map_php_error_code(E_DEPRECATED));
    $this->assertSame("Deprecated", _fp_map_php_error_code(E_USER_DEPRECATED));

    $this->assertSame("", _fp_map_php_error_code(123456789));
  }

  public function testFilterUntrustedInputForMajorCode()
  {
    $input = ' COSC (BS); #1010="test" ';

    $result = filter_untrusted_input($input, "major_code");

    $this->assertSame("COSCBS1010test", $result);
  }

  public function testFilterUntrustedInputRemovesHtml()
  {
    $result = filter_untrusted_input('<script>alert("x")</script>COSC', "major_code");

    $this->assertStringNotContainsString("<script>", $result);
    $this->assertStringNotContainsString("</script>", $result);
    $this->assertStringContainsString("alert", $result);
    $this->assertStringContainsString("COSC", $result);
  }

  public function testFilterUntrustedInputReturnsOtherTypesUnchanged()
  {
    $this->assertSame("Hello World", filter_untrusted_input("Hello World", "other"));
  }

  public function testFilterUntrustedInputHandlesEmptyInput()
  {
    $this->assertSame("", filter_untrusted_input("", "major_code"));
    $this->assertSame("", filter_untrusted_input(NULL, "major_code"));
  }

  public function testFilterMarkupReturnsEmptyInputUnchanged()
  {
    $this->assertSame("", filter_markup(""));
    $this->assertSame(NULL, filter_markup(NULL));
  }

  public function testFilterMarkupReturnsNonStringInputUnchanged()
  {
    $this->assertSame(123, filter_markup(123));
    $this->assertSame(["test"], filter_markup(["test"]));
  }

  public function testFilterMarkupFullAllowsHtml()
  {
    $html = "<strong>Hello</strong><script>alert('x')</script>";

    $this->assertSame($html, filter_markup($html, "full"));
  }

  public function testFilterMarkupBasicConvertsNewlinesToSafeMarkup()
  {
    $result = filter_markup("Hello\nWorld", "basic");

    $this->assertStringContainsString("Hello", $result);
    $this->assertStringContainsString("World", $result);
  }

  public function testRepairHtmlRepairsMismatchedTags()
  {
    $result = repair_html("<strong>Hello");

    $this->assertStringContainsString("<strong>Hello</strong>", $result);
  }

  public function testRepairHtmlPreservesValidMarkup()
  {
    $result = repair_html("<p>Hello <strong>world</strong></p>");

    $this->assertStringContainsString("<p>Hello <strong>world</strong></p>", $result);
  }

  public function testRepairHtmlHandlesPlainText()
  {
    $this->assertSame("Hello world", repair_html("Hello world"));
  }

  public function testFilterXssBadProtocolAllowsSafeHttpUrl()
  {
    $this->assertSame("http://example.com", filter_xss_bad_protocol("http://example.com"));
  }

  public function testFilterXssBadProtocolRemovesJavascriptUrl()
  {
    $result = filter_xss_bad_protocol("javascript:alert(1)");

    $this->assertStringNotContainsString("javascript:", strtolower($result));
    $this->assertStringContainsString("alert(1)", $result);
  }

  public function testFilterXssBadProtocolDecodesHtmlEntitiesBeforeFiltering()
  {
    $result = filter_xss_bad_protocol("javascript&#58;alert(1)");

    $this->assertStringNotContainsString("javascript:", strtolower($result));
  }

  public function testFilterXssAttributesKeepsSafeAttributes()
  {
    $result = filter_xss_attributes('class="student" id="student-123"');

    $this->assertContains('class="student"', $result);
    $this->assertContains('id="student-123"', $result);
  }

  public function testFilterXssAttributesRemovesStyleAttribute()
  {
    $result = filter_xss_attributes('style="display:none" class="student"');

    $this->assertNotContains('style="display:none"', $result);
    $this->assertContains('class="student"', $result);
  }

  public function testFilterXssAttributesRemovesEventHandlers()
  {
    $result = filter_xss_attributes('onclick="alert(1)" class="student"');

    $this->assertNotContains('onclick="alert(1)"', $result);
    $this->assertContains('class="student"', $result);
  }

  public function testFilterXssAttributesHandlesValuelessAttributes()
  {
    $result = filter_xss_attributes("disabled class=\"student\"");

    $this->assertContains("disabled", $result);
    $this->assertContains('class="student"', $result);
  }

  public function testStripDangerousProtocolsAllowsCommonSafeProtocols()
  {
    $this->assertSame("ftp://example.com", fp_strip_dangerous_protocols("ftp://example.com"));
    $this->assertSame("mailto:test@example.com", fp_strip_dangerous_protocols("mailto:test@example.com"));
    $this->assertSame("tel:5551234", fp_strip_dangerous_protocols("tel:5551234"));
    $this->assertSame("https://example.com", fp_strip_dangerous_protocols("https://example.com"));
  }

  public function testStripDangerousProtocolsHandlesRepeatedDangerousProtocols()
  {
    $result = fp_strip_dangerous_protocols("javascript:javascript:alert(1)");

    $this->assertSame("alert(1)", $result);
  }

  public function testStripDangerousProtocolsDoesNotTreatColonInRelativePathAsProtocol()
  {
    $result = fp_strip_dangerous_protocols("/path/to:file");

    $this->assertSame("/path/to:file", $result);
  }

  public function testGetMachineReadableReplacesRunsOfInvalidCharacters()
  {
    $this->assertSame("Hello_World", fp_get_machine_readable("Hello---World"));
    $this->assertSame("Hello_World", fp_get_machine_readable("Hello & World"));
  }

  public function testGetMachineReadablePreservesUnderscores()
  {
    $this->assertSame("TEST_ONE", fp_get_machine_readable("TEST_ONE"));
  }

  public function testGetMachineReadablePreservesNumbers()
  {
    $this->assertSame("Course_1010", fp_get_machine_readable("Course 1010"));
  }

  public function testGetTermDescriptionUsesConfiguredTermStructure()
  {
    $original = variable_get_for_school("term_id_structure", "", 0);

    try {
      variable_set_for_school("term_id_structure", "[Y4]40, Fall, Fall of [Y4], Fall '[Y2]", 0);
      unset($GLOBALS["fp_cache_get_term_description"]);

      $this->assertSame("Fall of 2020", get_term_description("202040", FALSE, 0));
    }
    finally {
      variable_set_for_school("term_id_structure", $original, 0);
      unset($GLOBALS["fp_cache_get_term_description"]);
    }
  }

  public function testGetTermDescriptionCanReturnAbbreviatedDescription()
  {
    $original = variable_get_for_school("term_id_structure", "", 0);

    try {
      variable_set_for_school("term_id_structure", "[Y4]40, Fall, Fall of [Y4], Fall '[Y2]", 0);
      unset($GLOBALS["fp_cache_get_term_description"]);

      $this->assertSame("Fall '20", get_term_description("202040", TRUE, 0));
    }
    finally {
      variable_set_for_school("term_id_structure", $original, 0);
      unset($GLOBALS["fp_cache_get_term_description"]);
    }
  }

  public function testGetTermDescriptionReturnsTermIdWhenNoStructureMatches()
  {
    $original = variable_get_for_school("term_id_structure", "", 0);

    try {
      variable_set_for_school("term_id_structure", "[Y4]40, Fall, Fall of [Y4], Fall '[Y2]", 0);
      unset($GLOBALS["fp_cache_get_term_description"]);

      $this->assertSame("202099", get_term_description("202099", FALSE, 0));
    }
    finally {
      variable_set_for_school("term_id_structure", $original, 0);
      unset($GLOBALS["fp_cache_get_term_description"]);
    }
  }

  public function testGetTermDescriptionReturnsUnavailableFor1111Terms()
  {
    $this->assertSame("(data unavailable at this time)", get_term_description("201111", FALSE, 0));
  }


  public function testGetTermStructuresIncludesDisplayAdjustment()
  {
    $original = variable_get_for_school("term_id_structure", "", 0);

    try {
      variable_set_for_school("term_id_structure", "[Y4]40, Fall, Fall of [Y4], Fall '[Y2], -1", 0);

      $structures = get_term_structures(0);

      $this->assertSame("-1", $structures["40"]["disp_adjust"]);
    }
    finally {
      variable_set_for_school("term_id_structure", $original, 0);
    }
  }

  public function testDebugCurrentTimeMillisStartsNewTimer()
  {
    $original = $GLOBALS["current_time_millis_test"] ?? NULL;

    try {
      unset($GLOBALS["current_time_millis_test"]);

      $result = fp_debug_current_time_millis("Starting test", TRUE, "_test");

      $this->assertStringContainsString("DEBUG:", $result);
      $this->assertStringContainsString("Starting test", $result);
      $this->assertStringContainsString("---", $result);
      $this->assertArrayHasKey("current_time_millis_test", $GLOBALS);
    }
    finally {
      if ($original === NULL) {
        unset($GLOBALS["current_time_millis_test"]);
      }
      else {
        $GLOBALS["current_time_millis_test"] = $original;
      }
    }
  }

  public function testDebugCurrentTimeMillisReportsElapsedTime()
  {
    $original = $GLOBALS["current_time_millis_test"] ?? NULL;

    try {
      $GLOBALS["current_time_millis_test"] = microtime(TRUE) * 1000 - 100;

      $result = fp_debug_current_time_millis("Finished test", TRUE, "_test");

      $this->assertStringContainsString("DEBUG:", $result);
      $this->assertStringContainsString("Finished test", $result);
      $this->assertStringContainsString("ms since last call", $result);
    }
    finally {
      if ($original === NULL) {
        unset($GLOBALS["current_time_millis_test"]);
      }
      else {
        $GLOBALS["current_time_millis_test"] = $original;
      }
    }
  }

  public function testDebugCurrentTimeMillisCanDisplayArrays()
  {
    $result = fp_debug_current_time_millis(["name" => "Rex"], TRUE, "_array_test");

    $this->assertStringContainsString("<pre>", $result);
    $this->assertStringContainsString("name", $result);
    $this->assertStringContainsString("Rex", $result);

    unset($GLOBALS["current_time_millis_array_test"]);
  }

  public function testGetJsConfirmLink()
  {
    $result = fp_get_js_confirm_link("Are you sure?", "deleteStudent(123)", "Delete", "danger", "Delete this student");

    $this->assertStringStartsWith("<a href='javascript: fp_confirm(", $result);
    $this->assertStringContainsString("class='danger'", $result);
    $this->assertStringContainsString("title='Delete this student'", $result);
    $this->assertStringContainsString(">Delete</a>", $result);
    $this->assertStringContainsString(base64_encode("Are you sure?"), $result);
    $this->assertStringContainsString(base64_encode("deleteStudent(123)"), $result);
  }

  public function testGetJsConfirmLinkConvertsNewlines()
  {
    $result = fp_get_js_confirm_link("Line one\nLine two", "doSomething()", "Go");

    $this->assertStringContainsString(base64_encode("Line one<br>Line two"), $result);
  }

  public function testGetJsPromptLink()
  {
    $result = fp_get_js_prompt_link("Enter name", "Richard", "saveName(response)", "Save", "prompt-link");

    $this->assertStringContainsString("prompt-link", $result);
    $this->assertStringContainsString("Enter name", $result);
    $this->assertStringContainsString("Richard", $result);
    $this->assertStringContainsString("saveName(response)", $result);
    $this->assertStringContainsString(">Save</a>", $result);
  }

  public function testGetJsAlertLink()
  {
    $result = fp_get_js_alert_link("This is a helpful message", "Help", "help-link", "Helpful information");

    $this->assertStringContainsString("fp-alert-link help-link", $result);
    $this->assertStringContainsString("title='Helpful information'", $result);
    $this->assertStringContainsString(">Help</a>", $result);
    $this->assertStringContainsString(base64_encode("This is a helpful message"), $result);
  }

  public function testGetJsAlertLinkUsesQuestionMarkWhenLinkTextIsOmitted()
  {
    $result = fp_get_js_alert_link("Help text");

    $this->assertStringContainsString("pop-q-mark", $result);
    $this->assertStringContainsString("fa-question-circle", $result);
  }

  public function testModulesImplementHookFindsImplementedHooks()
  {
    $GLOBALS["hook_cache"] = [];

    $original_modules = $GLOBALS["fp_system_settings"]["modules"];

    try {
      $GLOBALS["fp_system_settings"]["modules"] = [
        "testhookmodule" => ["enabled" => "1"],
      ];

      if (!function_exists("testhookmodule_test_characterization_hook")) {
        eval('function testhookmodule_test_characterization_hook() { return TRUE; }');
      }

      $result = modules_implement_hook("test_characterization_hook");

      $this->assertSame(["testhookmodule"], $result);
    }
    finally {
      $GLOBALS["fp_system_settings"]["modules"] = $original_modules;
      unset($GLOBALS["hook_cache"]["test_characterization_hook"]);
    }
  }













} // class











//