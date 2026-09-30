<?php

/**
 * Tests the FlightPath menu helpers.
 */
class MenuTest extends FlightPathTestCase
{
  public function testMenuGetMenuRouterItemFromDbReturnsRealMenuItem()
  {
    $item = menu_get_menu_router_item_from_db('admin-tools/admin');

    $this->assertIsArray($item);
    $this->assertSame('admin-tools/admin', $item['path']);
    $this->assertSame('admin_display_main', $item['page_callback']);
    $this->assertSame([], $item['page_arguments']);

    // This menu item does not specify an access_callback. menu_check_user_access()
    // will therefore use user_has_permission() when checking access, but the
    // inferred callback is not added back to the router item.
    $this->assertTrue(empty($item['access_callback']));
    $this->assertSame(['can_access_admin'], $item['access_arguments']);

    $this->assertSame('FlightPath Admin Console', $item['title']);
    $this->assertSame('This area contains the bulk of settings, degree entry, and other configurations for FlightPath.', $item['description']);
  }

  public function testMenuGetMenuRouterItemFromDbDeserializesPageSettings()
  {
    $item = menu_get_menu_router_item_from_db('admin-tools/admin');

    $this->assertIsArray($item['page_settings']);
    $this->assertArrayHasKey('menu_links', $item['page_settings']);
    $this->assertArrayHasKey('menu_icon', $item['page_settings']);

    $this->assertSame('Admin Tools', $item['page_settings']['menu_links'][0]['text']);
    $this->assertSame('admin-tools', $item['page_settings']['menu_links'][0]['path']);
  }

  public function testMenuGetMenuRouterItemFromDbReturnsNullForUnknownPath()
  {
    $this->assertNull(menu_get_menu_router_item_from_db('__this_path_does_not_exist__'));
  }

  public function testMenuGetItemFindsExactRealPath()
  {
    $item = menu_get_item('admin-tools/admin');

    $this->assertIsArray($item);
    $this->assertSame('admin-tools/admin', $item['path']);
    $this->assertSame('admin_display_main', $item['page_callback']);
    $this->assertSame([], $item['page_arguments']);
    $this->assertTrue(empty($item['access_callback']));
    $this->assertSame(['can_access_admin'], $item['access_arguments']);
    $this->assertSame('FlightPath Admin Console', $item['title']);
  }

  public function testMenuGetItemFindsRealWildcardPathAndResolvesPageArgument()
  {
    $original_q = $_REQUEST['q'] ?? NULL;

    try {
      // The database stores page_arguments as [3]. Since URL pieces are
      // zero-based, position 3 in admin/config/watchdog/123 is "123".
      $_REQUEST['q'] = 'admin/config/watchdog/123';

      $item = menu_get_item('admin/config/watchdog/123');

      $this->assertIsArray($item);
      $this->assertSame('admin/config/watchdog/%', $item['path']);
      $this->assertSame('admin_display_watchdog_entry', $item['page_callback']);
      $this->assertSame(['123'], $item['page_arguments']);
    }
    finally {
      if ($original_q === NULL) {
        unset($_REQUEST['q']);
      }
      else {
        $_REQUEST['q'] = $original_q;
      }
    }
  }

  public function testMenuGetItemResolvesMultiplePageArgumentPositions()
  {
    $original_q = $_REQUEST['q'] ?? NULL;

    try {
      // The route has two wildcards. Its page_arguments are
      // [form name, normal, 3, 4], so positions 3 and 4 resolve to
      // the two wildcard values.
      $_REQUEST['q'] = 'admin/degrees/edit-degree/6104/2020';

      $item = menu_get_item('admin/degrees/edit-degree/6104/2020');

      $this->assertIsArray($item);
      $this->assertSame('admin/degrees/edit-degree/%/%', $item['path']);
      $this->assertSame('fp_render_form', $item['page_callback']);
      $this->assertSame(['admin_edit_degree_form', 'normal', '6104', '2020'], $item['page_arguments']);
    }
    finally {
      if ($original_q === NULL) {
        unset($_REQUEST['q']);
      }
      else {
        $_REQUEST['q'] = $original_q;
      }
    }
  }

  public function testMenuGetItemResolvesNumericAccessArgument()
  {
    $original_q = $_REQUEST['q'] ?? NULL;

    try {
      // This route stores access_arguments as ["add", 2].
      // Position 2 is the first wildcard value in the URL.
      $_REQUEST['q'] = 'content/add/foo/bar/baz/qux/quux';

      $item = menu_get_item('content/add/foo/bar/baz/qux/quux');

      $this->assertIsArray($item);
      $this->assertSame('content/add/%/%/%/%/%', $item['path']);
      $this->assertSame('content_user_access', $item['access_callback']);
      $this->assertSame(['add', 'foo'], $item['access_arguments']);
    }
    finally {
      if ($original_q === NULL) {
        unset($_REQUEST['q']);
      }
      else {
        $_REQUEST['q'] = $original_q;
      }
    }
  }

  public function testMenuGetItemResolvesMultipleNumericPageArguments()
  {
    $original_q = $_REQUEST['q'] ?? NULL;

    try {
      // user/%/edit-attribute/% stores page_arguments as
      // [form name, empty string, 1, 3]. Positions 1 and 3 therefore
      // resolve to the two wildcard values in the requested URL.
      $_REQUEST['q'] = 'user/12345/edit-attribute/email';

      $item = menu_get_item('user/12345/edit-attribute/email');

      $this->assertIsArray($item);
      $this->assertSame('user/%/edit-attribute/%', $item['path']);
      $this->assertSame('fp_render_form', $item['page_callback']);
      $this->assertSame(['user_edit_attribute_form', '', '12345', 'email'], $item['page_arguments']);
    }
    finally {
      if ($original_q === NULL) {
        unset($_REQUEST['q']);
      }
      else {
        $_REQUEST['q'] = $original_q;
      }
    }
  }

  public function testMenuGetItemReturnsNullForUnknownPath()
  {
    $this->assertNull(menu_get_item('__this_path_does_not_exist__'));
  }

  public function testMenuGetItemsBeginningWithReturnsRealMenuItems()
  {
    $items = menu_get_items_beginning_with('admin/config/watchdog');

    $paths = array_map(static function ($item) {
      return $item['path'];
    }, $items);

      $this->assertContains('admin/config/watchdog', $paths);
      $this->assertContains('admin/config/watchdog/%', $paths);
  }

  public function testMenuGetItemsBeginningWithIncludesChildPaths()
  {
    $items = menu_get_items_beginning_with('admin/degrees');

    $paths = array_map(static function ($item) {
      return $item['path'];
    }, $items);

      $this->assertContains('admin/degrees', $paths);
      $this->assertContains('admin/degrees/add-degree', $paths);
      $this->assertContains('admin/degrees/copy-degree', $paths);
      $this->assertContains('admin/degrees/edit-degree/%/%', $paths);
  }

  public function testMenuGetItemsInTabFamilyReturnsRealTabFamilyItems()
  {
    $items = menu_get_items_in_tab_family('users');

    $paths = array_map(static function ($item) {
      return $item['path'];
    }, $items);

      $this->assertSame(['admin/users/faculty', 'admin/users/students'], $paths);
  }

  public function testMenuGetItemsInTabFamilyReturnsItemsInWeightOrder()
  {
    $items = menu_get_items_in_tab_family('advise-toolbox');

    $paths = array_map(static function ($item) {
      return $item['path'];
    }, $items);

      $this->assertSame([
        'advise/popup-toolbox/transfers',
        'advise/popup-toolbox/substitutions',
        'advise/popup-toolbox/moved',
        'advise/popup-toolbox/courses',
      ], $paths);
  }

  public function testMenuCheckUserAccessAcceptsLiteralOne()
  {
    $router_item = [
      'access_callback' => '1',
      'access_arguments' => [],
    ];

    $this->assertTrue(menu_check_user_access($router_item));
  }

  public function testMenuCheckUserAccessAcceptsIntegerOne()
  {
    $router_item = [
      'access_callback' => 1,
      'access_arguments' => [],
    ];

    $this->assertTrue(menu_check_user_access($router_item));
  }

  public function testMenuCheckUserAccessAcceptsBooleanTrue()
  {
    $router_item = [
      'access_callback' => TRUE,
      'access_arguments' => [],
    ];

    $this->assertTrue(menu_check_user_access($router_item));
  }

  public function testMenuCheckUserAccessRejectsMissingRouterItem()
  {
    $this->assertFalse(menu_check_user_access(NULL));
    $this->assertFalse(menu_check_user_access(FALSE));
    $this->assertFalse(menu_check_user_access(''));
  }

  public function testMenuCheckUserAccessUsesUserHasPermissionWhenCallbackMissing()
  {
    $router_item = [
      'access_callback' => NULL,
      'access_arguments' => ['can_access_admin'],
    ];

    // user_has_permission() is the default callback when access_callback
    // is missing but access_arguments are present.
    $this->assertTrue(menu_check_user_access($router_item));
  }

  public function testMenuCheckUserAccessCallsNamedCallbackWithArguments()
  {
    $router_item = [
      'access_callback' => 'menu_test_access_callback',
      'access_arguments' => ['allowed', 42],
    ];

    $this->assertTrue(menu_check_user_access($router_item));
  }

  public function testMenuCheckUserAccessReturnsFalseWhenNamedCallbackDeniesAccess()
  {
    $router_item = [
      'access_callback' => 'menu_test_deny_access_callback',
      'access_arguments' => [],
    ];

    $this->assertFalse(menu_check_user_access($router_item));
  }

  public function testMenuGetModulePathReturnsFalseForUnknownModule()
  {
    $this->assertFalse(menu_get_module_path('__definitely_not_a_real_module__'));
  }
}

/**
 * Test callback used by menu_check_user_access().
 *
 * These functions exist only to verify that menu_check_user_access() resolves
 * the callback name from the router item and passes access_arguments to it.
 */
function menu_test_access_callback($first, $second)
{
  return $first === 'allowed' && $second === 42;
}

function menu_test_deny_access_callback()
{
  return FALSE;
}