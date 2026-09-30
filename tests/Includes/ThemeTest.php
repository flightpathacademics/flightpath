<?php

class ThemeTest extends FlightPathTestCase
{
  private $originalGet;
  private $originalRequest;
  private $originalSession;
  private array $originalGlobals;

  protected function setUp(): void
  {
    parent::setUp();

    $this->originalGet = $_GET;
    $this->originalRequest = $_REQUEST;
    $this->originalSession = $_SESSION;
    $this->originalGlobals = $GLOBALS;

    if (!is_array($_GET)) {
      $_GET = [];
    }

    if (!is_array($_REQUEST)) {
      $_REQUEST = [];
    }

    if (!is_array($_SESSION)) {
      $_SESSION = [];
    }
  }

  protected function tearDown(): void
  {
    $_GET = $this->originalGet;
    $_REQUEST = $this->originalRequest;
    $_SESSION = $this->originalSession;

    foreach ($GLOBALS as $key => $value) {
      if (!array_key_exists($key, $this->originalGlobals)) {
        unset($GLOBALS[$key]);
      }
    }

    foreach ($this->originalGlobals as $key => $value) {
      $GLOBALS[$key] = $value;
    }

    parent::tearDown();
  }

  public function testThemeTableHeaderSortable()
  {
    $_GET['q'] = 'test';

    $headers = [
      [
        'label' => 'Name',
        'field' => 'name',
      ],
      [
        'label' => 'Description',
      ],
    ];

    $result = theme_table_header_sortable($headers, 'test');

    $this->assertIsString($result);
    $this->assertStringContainsString('<tr>', $result);
    $this->assertStringContainsString('Name', $result);
    $this->assertStringContainsString('Description', $result);
    $this->assertStringContainsString('header-sortable-name', $result);
  }

  public function testThemeTableHeaderSortableSetInitialSort()
  {
    theme_table_header_sortable_set_initial_sort('name', 'ASC');

    $this->assertSame('name', $_GET['fsort']);
    $this->assertSame('ASC', $_GET['fsortdir']);
  }

  public function testThemeTableHeaderSortableSetInitialSortPreservesExistingSort()
  {
    $_GET['fsort'] = 'existing';
    $_GET['fsortdir'] = 'DESC';

    theme_table_header_sortable_set_initial_sort('name', 'ASC');

    $this->assertSame('existing', $_GET['fsort']);
    $this->assertSame('DESC', $_GET['fsortdir']);
  }

  public function testThemeTableHeaderSortableOrderBy()
  {
    $headers = [
      [
        'label' => 'Name',
        'field' => 'name',
      ],
      [
        'label' => 'Description',
        'field' => 'description',
      ],
    ];

    $_GET['fsort'] = 'name';
    $_GET['fsortdir'] = 'ASC';

    $result = theme_table_header_sortable_order_by($headers);

    $this->assertSame('ORDER BY name ASC', $result);
  }

  public function testThemeTableHeaderSortableOrderByRejectsUnknownField()
  {
    $headers = [
      [
        'label' => 'Name',
        'field' => 'name',
      ],
    ];

    $_GET['fsort'] = 'unknown';
    $_GET['fsortdir'] = 'ASC';

    $result = theme_table_header_sortable_order_by($headers);

    $this->assertSame('', $result);
  }

  public function testThemeTableHeaderSortableOrderByRejectsInvalidDirection()
  {
    $headers = [
      [
        'label' => 'Name',
        'field' => 'name',
      ],
    ];

    $_GET['fsort'] = 'name';
    $_GET['fsortdir'] = 'DROP TABLE';

    $result = theme_table_header_sortable_order_by($headers);

    $this->assertSame('ORDER BY name ', $result);
  }

  public function testPagerLoadArray()
  {
    $old_array = [
      0 => 2,
      1 => 4,
    ];

    $result = pager_load_array(7, 1, $old_array);

    $this->assertSame([
      0 => 2,
      1 => 7,
    ], $result);
  }

  public function testPagerLoadArrayFillsMissingElements()
  {
    $old_array = [];

    $result = pager_load_array(5, 2, $old_array);

    $this->assertSame([
      0 => 0,
      1 => 0,
      2 => 5,
    ], $result);
  }

  public function testThemePager()
  {
    $_GET['q'] = 'test';

    $GLOBALS['pager_page_array'] = [0];
    $GLOBALS['pager_total'] = [3];

    $result = theme_pager();

    $this->assertIsString($result);
    $this->assertStringContainsString("pager-wrapper-0", $result);
    $this->assertStringContainsString("pager", $result);
    $this->assertStringContainsString("pager-current", $result);
  }

  public function testThemePagerReturnsNullForSinglePage()
  {
    $GLOBALS['pager_page_array'] = [0];
    $GLOBALS['pager_total'] = [1];

    $result = theme_pager();

    $this->assertNull($result);
  }

  public function testFormatDateStandardFormat()
  {
    $timestamp = strtotime('2024-01-02 15:04:05');

    $this->assertSame('1/02/2024 03:04:05pm', format_date($timestamp));
  }

  public function testFormatDateShortFormat()
  {
    $timestamp = strtotime('2024-01-02 15:04:05');

    $this->assertSame('1/02/2024 - 3:04pm', format_date($timestamp, 'short'));
  }

  public function testFormatDatePrettyFormat()
  {
    $timestamp = strtotime('2024-01-02 15:04:05');

    $this->assertSame('January 2nd, 2024, 3:04pm', format_date($timestamp, 'pretty'));
  }

  public function testFormatDateJustDateFormat()
  {
    $timestamp = strtotime('2024-01-02 15:04:05');

    $this->assertSame('January 2nd, 2024', format_date($timestamp, 'just_date'));
  }

  public function testFormatDateJustTimeFormat()
  {
    $timestamp = strtotime('2024-01-02 15:04:05');

    $this->assertSame('3:04pm', format_date($timestamp, 'just_time'));
  }

  public function testFormatDateCustomFormat()
  {
    $timestamp = strtotime('2024-01-02 15:04:05');

    $this->assertSame('2024-01-02', format_date($timestamp, 'standard', 'Y-m-d'));
  }

  public function testFpPushAndBalanceProfileItems()
  {
    $profile_items = [
      'left_side' => [],
      'right_side' => [],
    ];

    fp_push_and_balance_profile_items($profile_items, ['first' => 'First']);

    $this->assertArrayHasKey('first', $profile_items['left_side']);
    $this->assertSame('First', $profile_items['left_side']['first']);
  }

  public function testFpPushAndBalanceProfileItemsBalancesItems()
  {
    $profile_items = [
      'left_side' => [
        'first' => 'First',
      ],
      'right_side' => [
        'second' => 'Second',
      ],
    ];

    fp_push_and_balance_profile_items($profile_items, ['third' => 'Third']);

    $this->assertArrayHasKey('third', $profile_items['right_side']);
    $this->assertArrayNotHasKey('third', $profile_items['left_side']);
  }

  public function testFpShowTitle()
  {
    fp_show_title(FALSE);

    $this->assertArrayHasKey('fp_set_show_title', $GLOBALS);
    $this->assertFalse($GLOBALS['fp_set_show_title']);

    fp_show_title(TRUE);

    $this->assertTrue($GLOBALS['fp_set_show_title']);
  }

  public function testFpRenderButton()
  {
    $result = fp_render_button('Test', 'doSomething()');

    $this->assertIsString($result);
    $this->assertStringContainsString('<button', $result);
    $this->assertStringContainsString('Test', $result);
    $this->assertStringContainsString("onClick='doSomething()'", $result);
    $this->assertStringContainsString('fp-render-button-test', $result);
  }

  public function testFpRenderButtonWithExtraClass()
  {
    $result = fp_render_button('Test Button', 'doSomething()', 'extra-class');

    $this->assertStringContainsString('extra-class', $result);
    $this->assertStringContainsString('fp-render-button-test_button', $result);
  }

  public function testFpRenderSectionTitle()
  {
    $result = fp_render_section_title('Test Title');

    $this->assertIsString($result);
    $this->assertStringContainsString('section-box-title', $result);
    $this->assertStringContainsString('Test Title', $result);
  }

  public function testFpRenderSectionTitleWithExtraClass()
  {
    $result = fp_render_section_title('Test Title', 'extra');

    $this->assertStringContainsString('section-box-title-extra', $result);
    $this->assertStringContainsString('section-box-text-test_title', $result);
  }

  public function testFpRenderCurvedLine()
  {
    $result = fp_render_curved_line('Test');

    $this->assertIsString($result);
    $this->assertStringContainsString('Test', $result);
  }

  public function testFpRenderSquareLine()
  {
    $result = fp_render_square_line('Test');

    $this->assertIsString($result);
    $this->assertStringContainsString('Test', $result);
  }

  public function testFpRenderCFieldset()
  {
    $result = fp_render_c_fieldset('Test', 'Content');

    $this->assertIsString($result);
    $this->assertStringContainsString('Test', $result);
    $this->assertStringContainsString('Content', $result);
  }

  public function testFpRenderSubTabArray()
  {
    $tabs = [
      [
        'title' => 'First',
        'href' => '/first',
      ],
      [
        'title' => 'Second',
        'href' => '/second',
      ],
    ];

    $result = fp_render_sub_tab_array($tabs);

    $this->assertIsString($result);
    $this->assertStringContainsString('First', $result);
    $this->assertStringContainsString('Second', $result);
  }

  public function testFpRenderMobileTabArray()
  {
    $tabs = [
      [
        'title' => 'First',
        'href' => '/first',
      ],
      [
        'title' => 'Second',
        'href' => '/second',
      ],
    ];

    $result = fp_render_mobile_tab_array($tabs);

    $this->assertIsString($result);
    $this->assertStringContainsString('First', $result);
    $this->assertStringContainsString('Second', $result);
  }

  public function testFpRenderTabArray()
  {
    $tabs = [
      [
        'title' => 'First',
        'href' => '/first',
      ],
      [
        'title' => 'Second',
        'href' => '/second',
      ],
    ];

    $result = fp_render_tab_array($tabs);

    $this->assertIsString($result);
    $this->assertStringContainsString('First', $result);
    $this->assertStringContainsString('Second', $result);
  }

  public function testFpThemeLocation()
  {
    $result = fp_theme_location();

    $this->assertIsString($result);
    $this->assertStringContainsString('themes/fp_clean', $result);
  }

  public function testFpThemeLocationWithoutBasePath()
  {
    $result = fp_theme_location(FALSE);

    $this->assertIsString($result);
    $this->assertStringContainsString('themes/fp_clean', $result);
  }

  public function testPrettyPrintReturn()
  {
    $result = pretty_print(['test' => 'value'], TRUE);

    $this->assertIsString($result);
    $this->assertStringContainsString('<pre>', $result);
    $this->assertStringContainsString('value', $result);
  }

  public function testPpmReturn()
  {
    $result = ppm(['test' => 'value'], TRUE);

    $this->assertIsString($result);
    $this->assertStringContainsString('<pre>', $result);
    $this->assertStringContainsString('value', $result);
  }







}