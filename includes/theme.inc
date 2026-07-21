<?php

/**
 * These functions deal mostly with theme-related functions.
 * In other words, functions responsible for drawing, rendering, etc, HTML
 * to the screen.
 */





/**
 * Given an array of table headers (in the format listed below), returns back the HTML to draw it to the screen.
 * This makes them clickable, to make the table header sortable.  This is meant to be used with queries, by adding
 * in an "ORDER BY" clause.
 *
 * $headers should look like this:
 * 0 = array('label' => 'First Name', 'field' => 'field__first_name');
 *
 * If the label should NOT be clickable, then "field" should be blank or absent.
 *
 *
 */
function theme_table_header_sortable($headers = array(), $element_id = "default") {

  // Is there a "current student id" being specified in the URL? If so, let's keep it in our links.
  $csid = fp_trim(@$_REQUEST['current_student_id']);
  if ($csid) {
    $csid = "&current_student_id=$csid";
  }

  $rtn = "";
  $rtn .= "<tr>";

  $filter = trim((string) @$_GET['filter']); // if there is an existing filter we need to preserve.
  $filter_line = "";
  if ($filter) {
    $filter_line = "&filter=" . $filter;
  }

  foreach ($headers as $header) {
    $th_class = fp_get_machine_readable(strtolower($header['label']));
    $rtn .= "<th class='header-sortable-$th_class'>";

    $label = $header['label'];
    $field = @$header['field'];
    $init_dir = @$hader['init_dir'];

    if (!$field) {
      $rtn .= $label;
    }
    else {
      // Convert label to a link.  Also, show it as ASC or DESC based on if it is currently selected.

      // get the current header sort field and dir....
      $current_fsort = @$_GET['fsort'];
      $current_fsortdir = @$_GET['fsortdir'];

      $opposite_fsortdir = 'ASC';
      if ($current_fsortdir == 'ASC') {
        $opposite_fsortdir = 'DESC';
      }

      if ($field == $current_fsort) {
        if ($current_fsortdir == 'ASC') {
          $label .= " <i class='fa fa-chevron-up'></i>";
        }
        else {
          $label .= " <i class='fa fa-chevron-down'></i>";
        }
      }


      $new_fsortdir = 'ASC';

      //if ($default_sort) $new_fsortdir = $default_sort;



      // Scenario #1:
      // We have never clicked this item before.  The link should be for THIS field, and for its "new_fsortdir"
      if ($current_fsort != $field) {
        $link = l($label, $_GET['q'], "fsort=$field&fsortdir=$new_fsortdir" . $filter_line . $csid);
      }

      // Scenario #2:
      // We have just recently clcked this item.  We want to REVERSE the sort direction
      if ($current_fsort == $field) {
        $link = l($label, $_GET['q'], "fsort=$field&fsortdir=$opposite_fsortdir" . $filter_line . $csid);
      }


      $rtn .= $link;


    }  // else



    $rtn .= "</th>";
  } // foreach header





  $rtn .= "</tr>";
  return $rtn;

} // theme_table_header_sortable


/**
 * Sets our initial sort, if there isn't already one set.
 */
function theme_table_header_sortable_set_initial_sort($field, $dir) {

  if (@$_GET['fsort'] == '') {
    $_GET['fsort'] = $field;
    $_GET['fsortdir'] = $dir;
  }
}


/**
 * Used with the theme_table_header_sortable function (meant to be called AFTER headers have been created.)
 *
 * The main thing we want to do is confirm that what we are getting from GET is a valid fieldname in the headers array,
 * to prevent SQL injection.
 */
function theme_table_header_sortable_order_by($headers) {

  $rtn = "";

  $fsort = @$_GET['fsort'];
  $fsortdir = @$_GET['fsortdir'];

  if (!$fsort) return '';

  if ($fsort) {
    // Confirm that this field is in the headers array.
    $bool_found_it = FALSE;
    foreach ($headers as $header) {
      if (isset($header['field']) && $header['field'] == $fsort) {
        $bool_found_it = TRUE;
        break;
      }
    }
    if (!$bool_found_it) return ""; // couldn't find it!
  }

  if ($fsortdir != "" && $fsortdir != 'ASC' && $fsortdir != 'DESC') {
    $fsortdir = '';

  }

  return "ORDER BY $fsort $fsortdir";

} // ... sortable_order_by




/**
 * Adapted from https://api.drupal.org/api/drupal/includes%21pager.inc/function/theme_pager/6.x
 *
 * The purpose of this is to return the HTML to display a "pager", as created from the pager_query() function.
 *
 * $tags: An array of labels for the controls in the pager.
 * $limit: The number of query results to display per page.
 * $element: An optional integer to distinguish between multiple pagers on one page.
 * $parameters: An associative array of query string parameters to append to the pager links.
 * $quantity: The number of pages in the list.
 *
 */
function theme_pager($tags = array(), $limit = 10, $element = 0, $parameters = array(), $quantity = 9) {
  global $pager_page_array, $pager_total;

  // Calculate various markers within this pager piece:
  // Middle is used to "center" pages around the current page.
  $pager_middle = ceil($quantity / 2);

  if (!isset($pager_page_array)) $pager_page_array = array();
  if (!isset($pager_page_array[$element])) $pager_page_array[$element] = 0;

  if (!isset($pager_total)) $pager_total = array();
  if (!isset($pager_total[$element])) $pager_total[$element] = 0;

  // current is the page we are currently paged to
  $pager_current = $pager_page_array[$element] + 1;

  // first is the first page listed by this pager piece (re quantity)
  $pager_first = $pager_current - $pager_middle + 1;

  // last is the last page listed by this pager piece (re quantity)
  $pager_last = $pager_current + $quantity - $pager_middle;

  // max is the maximum page number
  $pager_max = $pager_total[$element];

  // End of marker calculations.
  // Prepare for generation loop.
  $i = $pager_first;
  if ($pager_last > $pager_max) {

    // Adjust "center" if at end of query.
    $i = $i + ($pager_max - $pager_last);
    $pager_last = $pager_max;
  }
  if ($i <= 0) {

    // Adjust "center" if at start of query.
    $pager_last = $pager_last + (1 - $i);
    $i = 1;
  }

  // End of generation loop preparation.
  $li_first = theme_pager_first(isset($tags[0]) ? $tags[0] : t('« first'), $limit, $element, $parameters);
  $li_previous = theme_pager_previous(isset($tags[1]) ? $tags[1] : t('‹ previous'), $limit, $element, 1, $parameters);
  $li_next = theme_pager_next(isset($tags[3]) ? $tags[3] : t('next ›'), $limit, $element, 1, $parameters);
  $li_last = theme_pager_last(isset($tags[4]) ? $tags[4] : t('last »'), $limit, $element, $parameters);
  if ($pager_total[$element] > 1) {
    if ($li_first) {
      $items[] = array(
        'class' => 'pager-first',
        'data' => $li_first,
      );
    }
    if ($li_previous) {
      $items[] = array(
        'class' => 'pager-previous',
        'data' => $li_previous,
      );
    }

    // When there is more than one page, create the pager list.
    if ($i != $pager_max) {
      if ($i > 1) {
        $items[] = array(
          'class' => 'pager-ellipsis',
          'data' => '…',
        );
      }

      // Now generate the actual pager piece.
      for (; $i <= $pager_last && $i <= $pager_max; $i++) {
        if ($i < $pager_current) {
          $items[] = array(
            'class' => 'pager-item',
            'data' => theme_pager_previous($i, $limit, $element, $pager_current - $i, $parameters),
          );
        }
        if ($i == $pager_current) {
          $items[] = array(
            'class' => 'pager-current',
            'data' => $i,
          );
        }
        if ($i > $pager_current) {
          $items[] = array(
            'class' => 'pager-item',
            'data' => theme_pager_next($i, $limit, $element, $i - $pager_current, $parameters),
          );
        }
      }
      if ($i < $pager_max) {
        $items[] = array(
          'class' => 'pager-ellipsis',
          'data' => '…',
        );
      }
    }

    // End generation.
    if ($li_next) {
      $items[] = array(
        'class' => 'pager-next',
        'data' => $li_next,
      );
    }
    if ($li_last) {
      $items[] = array(
        'class' => 'pager-last',
        'data' => $li_last,
      );
    }



    //return theme('item_list', $items, NULL, 'ul', array(
    //  'class' => 'pager',
    //));

    $rtn = "";
    $rtn = "<ul class='pager'>";

    foreach ($items as $details) {
      $class = @$details['class'];
      $data = @$details['data'];
      $rtn .= "<li class='$class'>$data</li>";

    }

    $rtn .= "</ul>";

    return "<div class='pager-wrapper pager-wrapper-$element'>$rtn</div>";

  }
}

/**
 * Adapted from https://api.drupal.org/api/drupal/includes%21pager.inc/function/pager_get_querystring/6.x
 */
function pager_get_querystring() {
  static $string = NULL;
  if (!isset($string)) {
    $string = fp_query_string_encode($_REQUEST, array_merge(array(
      'q',
      'page',
      'pass',
    ), array_keys($_COOKIE)));
  }
  return $string;
}


/**
 * Adapted from https://api.drupal.org/api/drupal/includes%21pager.inc/function/theme_pager_link/6.x
 *
 * $text: The link text. Also used to figure out the title attribute of the link, if it is not provided in $attributes['title'];
 *    in this case, $text must be one of the standard pager link text strings that would be generated by the pager theme functions,
 *    such as a number or t('« first').
 * $page_new: The first result to display on the linked page.
 * $element: An optional integer to distinguish between multiple pagers on one page.
 * $parameters: An associative array of query string parameters to append to the pager link.
 * $attributes: An associative array of HTML attributes to apply to the pager link.
 *
 */
function theme_pager_link($text, $page_new, $element, $parameters = array(), $attributes = array()) {
  $page = isset($_GET['page']) ? $_GET['page'] : '';
  if ($new_page = implode(',', pager_load_array($page_new[$element], $element, explode(',', $page)))) {
    $parameters['page'] = $new_page;
  }
  $query = array();
  if (count($parameters)) {
    $query[] = fp_query_string_encode($parameters, array());
  }
  $querystring = pager_get_querystring();
  if ($querystring != '') {
    $query[] = $querystring;
  }

  // Set each pager link title
  if (!isset($attributes['title'])) {
    static $titles = NULL;
    if (!isset($titles)) {
      $titles = array(
        t('« first') => t('Go to first page'),
        t('‹ previous') => t('Go to previous page'),
        t('next ›') => t('Go to next page'),
        t('last »') => t('Go to last page'),
      );
    }
    if (isset($titles[$text])) {
      $attributes['title'] = $titles[$text];
    }
    else {
      if (is_numeric($text)) {
        $attributes['title'] = t('Go to page @number', array(
          '@number' => $text,
        ));
      }
    }
  }


  return l($text, $_GET['q'], count($query) ? implode('&', $query) : '', $attributes);
  /*
  return l($text, $_GET['q'], array(
    'attributes' => $attributes,
    'query' => count($query) ? implode('&', $query) : NULL,
  ));
  */
}



/**
 * Adapted from: https://api.drupal.org/api/drupal/includes%21pager.inc/function/theme_pager_first/6.x
 */
function theme_pager_first($text, $limit, $element = 0, $parameters = array()) {
  global $pager_page_array;
  $output = '';

  // If we are anywhere but the first page
  if ($pager_page_array[$element] > 0) {
    $output = theme_pager_link($text, pager_load_array(0, $element, $pager_page_array), $element, $parameters);
  }
  return $output;
}

/**
 * Adapted from https://api.drupal.org/api/drupal/includes%21pager.inc/function/theme_pager_last/6.x
 */
function theme_pager_last($text, $limit, $element = 0, $parameters = array()) {
  global $pager_page_array, $pager_total;
  $output = '';

  // If we are anywhere but the last page
  if ($pager_page_array[$element] < $pager_total[$element] - 1) {
    $output = theme_pager_link($text, pager_load_array($pager_total[$element] - 1, $element, $pager_page_array), $element, $parameters);
  }
  return $output;
}

/**
 * Adapted from https://api.drupal.org/api/drupal/includes%21pager.inc/function/theme_pager_next/6.x
 */
function theme_pager_next($text, $limit, $element = 0, $interval = 1, $parameters = array()) {
  global $pager_page_array, $pager_total;
  $output = '';

  // If we are anywhere but the last page
  if ($pager_page_array[$element] < $pager_total[$element] - 1) {
    $page_new = pager_load_array($pager_page_array[$element] + $interval, $element, $pager_page_array);

    // If the next page is the last page, mark the link as such.
    if ($page_new[$element] == $pager_total[$element] - 1) {
      $output = theme_pager_last($text, $limit, $element, $parameters);
    }
    else {
      $output = theme_pager_link($text, $page_new, $element, $parameters);
    }
  }
  return $output;
}

/**
 * Adapted from https://api.drupal.org/api/drupal/includes%21pager.inc/function/theme_pager_previous/6.x
 */
function theme_pager_previous($text, $limit, $element = 0, $interval = 1, $parameters = array()) {
  global $pager_page_array;
  $output = '';

  // If we are anywhere but the first page
  if ($pager_page_array[$element] > 0) {
    $page_new = pager_load_array($pager_page_array[$element] - $interval, $element, $pager_page_array);

    // If the previous page is the first page, mark the link as such.
    if ($page_new[$element] == 0) {
      $output = theme_pager_first($text, $limit, $element, $parameters);
    }
    else {
      $output = theme_pager_link($text, $page_new, $element, $parameters);
    }
  }
  return $output;
}

/**
 * Adapted from https://api.drupal.org/api/drupal/includes%21pager.inc/function/pager_load_array/6.x
 *
 * Copies $old_array to $new_array and sets $new_array[$element] = $value Fills in $new_array[0 .. $element - 1] = 0
 *
 */
function pager_load_array($value, $element, $old_array) {
  $new_array = $old_array;

  // Look for empty elements.
  for ($i = 0; $i < $element; $i++) {
    if (!$new_array[$i]) {

      // Load found empty element with 0.
      $new_array[$i] = 0;
    }
  }

  // Update the changed element.
  $new_array[$element] = (int) $value;
  return $new_array;
}




/**
 * Format a timestamp using the date command.
 * TODO: Make the formats something which can be controlled through the settings.
 *
 * Available formats:
 *  - standard
 *  - short
 *  - pretty
 *  - just_date
 *  - just_time
 *
 */
function format_date($timestamp = 0, $format = "standard", $custom = "") {

  if (!is_numeric($timestamp)) {
    $timestamp = 0;  // make sure it's numeric.
  }

  if ($timestamp == 0) {
    $timestamp = strtotime("now");
  }


  $f = "";
  if ($format == "standard") {
    $f = "n/d/Y h:i:sa";
  }

  if ($format == "short") {
    $f = "n/d/Y - g:ia";
  }

  if ($format == "pretty") {
    $f = "F jS, Y, g:ia";
  }

  if ($format == 'long_no_year') {
    $f = 'l, F jS \a\t g:ia';
  }


  if ($format == "just_date") {
    $f = "F jS, Y";
  }

  if ($format == 'just_time') {
    $f = "g:ia";
  }

  if ($custom != "") $f = $custom;

  return date($f, $timestamp);


}





/**
 * Render a "menu" block of menu items which are all rooted at the menu_root.
 * So if the menu root is tools, it might return items whose paths look like:
 *   tools/fun
 *   tools/here/there
 * So long as the menu type is "MENU_TYPE_NORMAL_ITEM" or "MENU_TYPE_DEFAULT_TAB".  Other types will be ignored.
 *
 *
 * We want to
 */
function fp_render_menu_block($title = "Tools", $menu_root = "tools", $bool_reset = FALSE) {
  $rtn = "";

  $is_empty = true;
  if ($title != "") {
    $rtn .= fp_render_section_title($title);
  }

  $menu_items = menu_get_items_beginning_with($menu_root);

  foreach ($menu_items as $item) {
    if ($item['path'] === $menu_root) continue;   // don't need to duplicate the menu root link itself

    if ($item["type"] == MENU_TYPE_NORMAL_ITEM || $item['type'] == MENU_TYPE_DEFAULT_TAB) {
      $item = menu_get_item($item['path'], $bool_reset);

      $rtn .= fp_render_menu_item($item);
      $is_empty = false;
    }

  }

  if ($is_empty) {
    return "";
  }
  //pretty_print($menu_items);

  return $rtn;

}

/**
 * Output the contents of the $page variable to the screen.
 * $page is an array containing details about the page, as well as
 * its original menu item (router_item) definition.
 */
function fp_display_page($page) {

  global $current_student_id, $screen, $user;
  $page_title = $page["title"];
  if (@$GLOBALS["fp_set_title"] != "") {
    $page_title = $GLOBALS["fp_set_title"];
  }

  if (!is_object($screen)) {
    $screen = new AdvisingScreen("",null,"not_advising");
  }
  $screen->page_title = $page_title;
  $screen->page_has_search = @$page["router_item"]["page_settings"]["page_has_search"];

  // Add some body classes to the page for this student.
  $student = $screen->student;
  if (!is_object($student)) {
    $student = new Student();
    $student->student_id = $current_student_id;
    $student->load_student_data();
  }
  if (is_object($student)) {
    fp_add_body_class("student-rank-$student->db_rank  student-catalog-year-$student->catalog_year");
  }


  $screen->page_banner_is_link = @$page["router_item"]["page_settings"]["page_banner_is_link"];
  $screen->page_hide_report_error = @$page["router_item"]["page_settings"]["page_hide_report_error"];
  $screen->page_is_popup = @$page["router_item"]["page_settings"]["page_is_popup"];
  if (isset($page["router_item"]["page_settings"]["screen_mode"])) {
    $screen->screen_mode = $page["router_item"]["page_settings"]["screen_mode"];
  }
  if (isset($page["router_item"]["page_settings"]["bool_print"])) {
    $screen->bool_print = $page["router_item"]["page_settings"]["bool_print"];
  }


  if (@$_REQUEST["scroll_top"] != "") {
    $screen->page_scroll_top = $_REQUEST["scroll_top"];
  }

  // If there is a SESSION var for scroll_top, use that instead, then wipe it.
  if (@$_SESSION['scroll_top'] != "") {
    $screen->page_scroll_top = $_SESSION["scroll_top"];
    $_SESSION['scroll_top'] = "";
    unset($_SESSION['scroll_top']);
  }

  // Was this page a tab?  And if so, are there any other tabs we need to display? //
  if (@$page["router_item"]["type"] == MENU_TYPE_TAB || @$page["router_item"]["type"] == MENU_TYPE_DEFAULT_TAB || @$page["router_item"]["tab_parent"] != "") {
    // We know we should have at least 1 tab for this page.
    $tab_array = fp_build_tab_array($page);
    $screen->page_tabs = fp_render_tab_array($tab_array);
  }

  // Should we override the page_tabs with what is in "fp_set_page_tabs" ?
  if (isset($GLOBALS["fp_set_page_tabs"]) && is_array($GLOBALS["fp_set_page_tabs"])) {
    //fpm($GLOBALS["fp_set_page_tabs"]);
    $screen->page_tabs = fp_render_tab_array($GLOBALS["fp_set_page_tabs"]);
  }


  // Build up the content //

  $content_top = "";
  $page_show_title = @$page["router_item"]["page_settings"]["page_show_title"];
  if (isset($GLOBALS["fp_set_show_title"])) {
    $page_show_title = $GLOBALS["fp_set_show_title"];
  }

  if ($page_show_title == FALSE) {
    $page_title = "";
  }


  $c = 0;

  // Are there any "menu_link"s for this?  In order words, little links at the top of the page,
  // where breadcrumbs might appear.
  if (!$screen->bool_print && @is_array($page["router_item"]["page_settings"]["menu_links"])) {
    //$content_top .= "<ul class='top-menu-links'>";
    $crumbs = array();
    foreach ($page["router_item"]["page_settings"]["menu_links"] as $item) {
      $lclass = "";
      if ($c == 0) $lclass='first';

      $p = menu_convert_replacement_pattern(@$item["path"]);
      $q = menu_convert_replacement_pattern(@$item["query"]);
      $t = @$item["text"];
      $a = @$item["attributes"];
      if (!is_array($a)) $a = array();

      // Make sure the current user has access to view this link.  Otherwise, do not even
      // bother showing it.
      $test_item = menu_get_item($p) ;
      if (!menu_check_user_access($test_item)) {
        continue;
      }


      //$content_top .= "<li class='$lclass'>" . l($t, $p, $q, $a) . "</li>";
      $crumbs[] = array('text' => $t, 'path' => $p, 'query' => $q, 'attributes' => $a);

      $c++;
    }
    //$content_top .= "</ul>";

    // If not already set!
    if (!isset($GLOBALS['fp_breadcrumbs'])) {
      fp_set_breadcrumbs($crumbs);
    }
  }




  // Any sub-tabs we need to render?
  if (@$page["router_item"]["type"] == MENU_TYPE_SUB_TAB) {
    $sub_tab_array = fp_build_sub_tab_array($page);
    $content_top .= fp_render_sub_tab_array($sub_tab_array);
  }

  // Should we override the page sub-tabs with what is in "fp_set_page_sub_tabs" ?
  if (isset($GLOBALS["fp_set_page_sub_tabs"]) && is_array($GLOBALS["fp_set_page_sub_tabs"])) {
    $content_top .= fp_render_sub_tab_array($GLOBALS["fp_set_page_sub_tabs"]);
  }


  // If the REQUEST specifically asks NOT to show waiting fp_messages, then clear them from the session.
  if (isset($_REQUEST['fp_messages']) && $_REQUEST['fp_messages'] == 'none') {
    unset($_SESSION['fp_messages']);
  }


  // If there are "messages" waiting, then let's add them to the top of content.
  if (isset($_SESSION["fp_messages"]) && is_array($_SESSION["fp_messages"]) && count($_SESSION["fp_messages"]) > 0) {

    $content_top .= "<div class='fp-messages'>";
    foreach ($_SESSION["fp_messages"] as $key => $tmsg) {
      $type = $tmsg["type"];
      $message = $tmsg["msg"];
      $content_top .= "<div class='fp-message fp-message-$type'>$message</div>";
    }
    $content_top .= "</div>";

    unset($_SESSION["fp_messages"]);
  }


  // content_top gets the Currently Advising box.
  if (@$page["router_item"]["page_settings"]["display_currently_advising"] == TRUE) {
    // To do this, we need to make sure the $screen->student object is loaded.
    $screen->page_display_currently_advising = TRUE;
    if (!$screen->student) {
      $screen->student = new Student($current_student_id);
    }
  }

  if (isset($page["router_item"]["page_settings"]["display_currently_advising"])) {
    if ($page["router_item"]["page_settings"]["display_currently_advising"] === FALSE) {
      // Meaning, we are explicitly saying do NOT show the currently advising box!
      $screen->page_display_currently_advising = FALSE;
    }
  }



  $screen->page_content = $content_top .= $page["content"];

  if ($user->id > 0) {
    // meaning, they are logged in.
    $screen->page_content .= fp_render_mobile_hamburger_menu(); // add to the bottom
  }

  // Add in the body classes
  $screen->page_body_classes .= " " . @$GLOBALS["fp_add_body_classes"];

  // Add in our URL  (after we sanitize it appropriately)
  $class = @$_REQUEST['q'];
  if (!$class) $class = ''; // force it to be a normal string.
  $class = str_replace("'", '', $class);
  $class = str_replace('"', '', $class);
  $class = str_replace('(', '', $class);
  $class = str_replace(')', '', $class);
  $class = str_replace(';', '', $class);
  $class = str_replace('.', '', $class);
  $class = str_replace('<', '', $class);
  $class = str_replace('>', '', $class);
  $class = str_replace('/', '-', $class);
  $class = str_replace('\\', '', $class);
  $class = str_replace('#', '', $class);
  $class = str_replace('&', '', $class);

  $screen->page_body_classes .= " page--" . str_replace("/", "-", $class);


  $screen->output_to_browser();
} // fp_display_page



/**
 * This function accepts a "profile items" array by reference, which is presumed to have a "left_side" and a "right_side" already
 * defined.  We can "push" items onto it, and the item will automatically go to the side with the fewest "items" so as to keep
 * it "balanced."
 */
function fp_push_and_balance_profile_items(&$profile_items, array $item) {

  // We do the -1 to the right_side, because it is already unbalanced by 1 as a default.
  $dec = 1;

  if (count($profile_items['right_side']) == 0) $dec = 0;

  if (count($profile_items['left_side']) <= count($profile_items['right_side']) - $dec) {
    $profile_items['left_side'] += $item;
  }
  else {
    $profile_items['right_side'] += $item;
  }

  // No need to return, since passed by reference.

} // fp_push_and_balance_profile_items



/**
 * Returns the HTML for the "profile" header html for a student
 */
function fp_render_student_profile_header($bool_mini = TRUE, $extra_profile_items = array()) {
  global $current_student_id, $screen, $user, $student;

  $csid = $current_student_id;

  $school_id = db_get_school_id_for_student_id($current_student_id);
  $degree_plan = NULL;
  $rtn = "";

  if (!isset($extra_profile_items['left_side'])) $extra_profile_items['left_side'] = array();
  if (!isset($extra_profile_items['right_side'])) $extra_profile_items['right_side'] = array();


  // Call a hook to possibly add more items to our profile items.
  invoke_hook("alter_student_profile_items", array($bool_mini, &$extra_profile_items));


  if (is_object($screen) && is_object($screen->degree_plan)) {
    $degree_plan = $screen->degree_plan;
  }

  if (!isset($student) || $student == NULL || !is_object($student)) {
    $student = new Student($current_student_id);
  }

  if ($degree_plan == NULL) {
    // Degree plan is still null.  This probably means we are NOT on an advising screen, so, let's
    // use a FlightPath object to init our degree plan, which might actually be a combination of degrees.


    // First, we can cheat by seeing if we have anything cached for this student the last time their degree plan
    // was loaded.  Using this,
    // its much faster & memory-friendly than trying to re-init a whole new FlightPath object and DegreePlan object(s).
    $bool_got_from_simple_cache = FALSE;
    // TODO:  Check for _what_if if we are in what if mode?

    if (isset($_SESSION["fp_simple_degree_plan_cache_for_student"])) {
      if ($_SESSION["fp_simple_degree_plan_cache_for_student"]["cwid"] == $csid) {
        // Yes, it's for this student.  Load her up.
        $degree_plan = new DegreePlan();
        $degree_plan->degree_id = $_SESSION["fp_simple_degree_plan_cache_for_student"]["degree_id"];
        $degree_plan->combined_degree_ids_array = $_SESSION["fp_simple_degree_plan_cache_for_student"]["combined_degree_ids_array"];
        $degree_plan->is_combined_dynamic_degree_plan = $_SESSION["fp_simple_degree_plan_cache_for_student"]["is_combined_dynamic_degree_plan"];


        $bool_got_from_simple_cache = TRUE;
      }
    }

    if (!$bool_got_from_simple_cache) {
      // didn't get it from the simple cache, so load it all fresh.
      $fp = new FlightPath($student);
      $fp->init(TRUE);
      $degree_plan = $fp->degree_plan;
    }
  }


  $cat_year = $student->catalog_year . "-" . ($student->catalog_year + 1);
  $rank = $student->rank;


  // Should we display a catalog year warning?  This is
  // something that can be part of a settings table.
  if ($student->catalog_year < intval(variable_get_for_school("earliest_catalog_year", 2006, $school_id))) {

    $alert_msg = "";
    $alert_msg .= "<b>" . t("Important Notice:") . "</b> " . t("FlightPath cannot display degree plans from catalogs earlier than %earliest.
                      The student&#039;s catalog year is %current, which means that the degree plan displayed may not accurately
                      represent this student&#039;s degree requirements.", array("%earliest" => intval(variable_get_for_school("earliest_catalog_year", 2006, $school_id)) . "-" . (intval(variable_get_for_school("earliest_catalog_year", 2006, $school_id)) + 1), "%current" => $cat_year));




    $cat_year = "<span class='catalog-year-alert-early'>$cat_year<a href='javascript:fp_alert(\"$alert_msg\");' title='" . t("Important Notice") . "'><i class='fa fa-exclamation-triangle'></i></a></span>";
    $bool_catalog_warning = true;
  }

  if (variable_get_for_school("current_catalog_year",'', $school_id) > intval(variable_get_for_school("earliest_catalog_year", 2006, $school_id))) {
    // Is the student's catalog set beyond the range that
    // FP has data for?  If so, show a warning.
    if ($student->catalog_year > variable_get_for_school("current_catalog_year",'', $school_id))
    {

      $alert_msg = t("This student&#039;s catalog year is @cat_year,
                      and specific curriculum requirements are not yet
                      available for this year.
                      To advise this student according to @new_cat
                      requirements, select the student&#039;s major using What If.", array("@cat_year" => $cat_year, "@new_cat" => variable_get_for_school("current_catalog_year",'',$school_id) . "-" . (variable_get_for_school("current_catalog_year",'',$school_id) + 1)));

      $cat_year = "<span class='catalog-year-alert-too-far'>$cat_year<a href='javascript:fp_alert(\"$alert_msg\");' title='" . t("Important Notice") . "'><i class='fa fa-exclamation-triangle'></i></a></span>";
      $bool_future_catalog_warning = true;

    }
  }

    $db = get_global_database_handler();



    ///////////////////////////
    $degree_title_div = "";
    $s = "";  // plural degrees?
    $bool_show_track_selection_link = FALSE;
    $bool_legacy_concentrations = (bool) variable_get("enable_legacy_concentrations", FALSE);
    // We are trying to figure out what to display for the Degree field.  If there is only ONE degree plan
    // to worry about, then we will display it.
    if ($degree_plan == null || $degree_plan->is_combined_dynamic_degree_plan != TRUE || $bool_legacy_concentrations == TRUE) {
      // This is an ordinary degree plan.

      $use_degree_plan = $degree_plan;

      $degree_title = "";
      if ($degree_plan != NULL) {
        $degree_title = $degree_plan->get_title2(TRUE);
      }

      /**
       * This degree is in fact a track.
       */
      if ($degree_plan->major_code && strstr($degree_plan->major_code, "_")) {
        $degree_title = $degree_plan->get_title2(TRUE, TRUE);

        if ($degree_plan->db_allow_dynamic == 0 || $bool_legacy_concentrations == TRUE) {
          // This degree is NON-dynamic or we have legacy concentrations enabled.
          // Meaning, if we change tracks, we should do it from the perspective
          // of the original major code, and how FP 4.x did things.
          $temp = explode("_", $degree_plan->major_code);
          $m = trim($temp[0]);
          // Remove trailing | if its there.
          $m = rtrim($m, "|");

          $use_degree_plan = $db->get_degree_plan($m, $degree_plan->catalog_year, TRUE);  // the original degree plan!
        }
      }


      if (isset($screen) && $screen->screen_mode == "detailed") {
        $degree_title .= " ($degree_plan->major_code)";
      }

      if ($use_degree_plan != null && $use_degree_plan->get_available_tracks()) {
        $bool_show_track_selection_link = TRUE;

      }

      $degree_title_div = "<div class='degree-title'>$degree_title</div>";

      //array_push($display_array, t("Degree:") . " ~~ " . $degree_title);

    }
    else {
      // This degree plan is made from combining several other degrees.  let's trot them out and display them as well.
      $t_degree_plan_titles = "";
      $dc = 0;
      foreach ($degree_plan->combined_degree_ids_array as $t_degree_id) {
        $t_degree_plan = new DegreePlan();
        $t_degree_plan->degree_id = $t_degree_id;

        $t_degree_plan->load_descriptive_data();

        if ($t_degree_plan->get_available_tracks()) {
          $bool_show_track_selection_link = TRUE;
        }


        // Add it to our box...
        // If more than one major/minor, etc, combine them into one div
        $t_title = $t_degree_plan->get_title2(TRUE);
        $t_class = $t_degree_plan->degree_class;
        $t_class_details = fp_get_degree_classification_details($t_class);
        // If this is actually a track, let's get the track's title instead.
        if ($x = $t_degree_plan->get_track_title(TRUE)) {
          $t_title = $x;
        }

        if ($t_class_details["level_num"] == 3) {
          $t_title = "<span class='level-3-raquo'>&raquo;</span>" . $t_title;
        }

        $machine_major_code = fp_get_machine_readable($t_degree_plan->major_code);
        if ($t_degree_plan->track_code != "") {
          $machine_major_code .=  "-" . fp_get_machine_readable($t_degree_plan->track_code);
        }


        $t_degree_plan_titles .= "<span class='multi-degree-title multi-degree-class-" . $t_class . "
                                      multi-degree-class-level-" . $t_class_details["level_num"] . "
                                      multi-degree-code-$machine_major_code multi-degree-num-$dc'>
                                      " . $t_title . "</span><span class='multi-degree-comma'>,</span>";
        $dc++;
        $s = "s";
      }
      // Trim the last comma off the end of t_degree_plan_titles
      $t_degree_plan_titles = rtrim($t_degree_plan_titles, "<span class='multi-degree-comma'>,</span>");

      $degree_title_div = $t_degree_plan_titles;

    }

    // Are we in what_if mode?  (yes or no)
    $advising_what_if = @$GLOBALS["fp_advising"]["advising_what_if"];

    // Now, do we want to add an option to select a track to the degree_title_div?
    if ($bool_show_track_selection_link && ($bool_legacy_concentrations != TRUE || $advising_what_if === 'yes')) {
      // We want to add a link to the popup to let the user select other degree tracks.
      $op_link = "<a class='degree-op-link-change-degree-options' href='javascript: popupSmallIframeDialog(\"" . fp_url("advise/popup-change-track", "advising_what_if=$advising_what_if") . "\",\"" . t("Select a Degree Option") . "\");'
                    title='" . t("Change degree options") . "'><i class='fa fa-cog'></i></a>";



      if (!$screen || $screen->screen_mode == "not_advising" || $screen->screen_mode == "detailed") {
        $op_link = "";
      }

      if (!user_has_permission("can_advise_students")) {

        if (@$GLOBALS["fp_advising"]["advising_what_if"] != "yes")
        {
          // In other words, we do not have permission to advise,
          // and we are not in whatIf, so take out the link.
          $op_link = "";
        }
      }

      if ($op_link) {
        $degree_title_div .= "<span class='degree-op-link'>$op_link</span>";
      }
    }


  $right_side_top = "<h2>&nbsp; &nbsp; &nbsp; &nbsp;</h2>"; // need spacers on purpose
  $whatifclass = "";
  if (@$GLOBALS["fp_advising"]["advising_what_if"] == "yes") {
    $whatifclass = "student-mini-profile-what-if-mode";

    // Set the cat year to whatever our What If cat year was, current if we can't find it.
    $what_if_catalog_year = @$GLOBALS["fp_advising"]["what_if_catalog_year"];
    if ($what_if_catalog_year != 0 && $what_if_catalog_year != "") {
      $student->catalog_year = $what_if_catalog_year;
    }
    else {

      // Only change to current if that is how our settings are set...
      if (variable_get_for_school("what_if_catalog_year_mode", "current", $school_id) == "current") {
        $student->catalog_year = variable_get_for_school("current_catalog_year",'',$school_id);
      }
    }




  }


  // Figure out what the user's image is based on their account.
  $image_url = "";
  $student_account_user_id = db_get_user_id_from_cwid($student->student_id, 'student');
  if ($student_account_user_id) {
    $student_account = fp_load_user($student_account_user_id);
    if ($student_account) {
      $image_url = @$student_account->settings['image_url'];
    }
  }




  $rtn .= "<div class='student-mini-profile $whatifclass'>
             ";
  if ($image_url) {
    $rtn .= "<div class='header-profile-image'>
                <img src='$image_url' class='student-profile-image' />
              </div> <!-- profile-image -->
              ";
  }


  $school_html = $school_name = "";
  if (module_enabled("schools")) {
    // If we have schools module enabled, let's find out what school this student belongs to and display it
    $defs = schools_get_school_definitions(TRUE);
    $temp = @$defs[$student->school_id];
    if ($temp) {
      $school_html .= "
                          <div class='profile-detail-line profile-inline-line profile-detail-line-school profile-line-detail-school-$student->school_id'>
                            <label>" . t("School:") . "</label> <span class='profile-line-content'>" . $temp['name'] . "</span>

                          </div>
      ";
    }

  }


  $student_name = fp_trim($student->name);
  if ($student_name == '') {
    // This person may not be in the "students" table, and so their name may not show up.  Look in
    // the users table only to see if we can get a name.
    $student_name = fp_get_student_name($student->student_id);
    $student_name .= "<span class='catalog-year-alert-early'>" . fp_get_js_alert_link(t("This student's name was not found in the normal way.
                                                They may not have been imported correctly as a regular student."), "<i class='fa fa-exclamation-triangle'></i>", "", "Important Notice") . "</span>";
  }


  $rtn .= "        <div class='profile-top-details-wrapper profile-top-wrapper-left-side'>
                          <div class='profile-detail-line profile-detail-line-student-name-cwid'>
                            <h2>$student_name &nbsp; &nbsp; ($student->student_id)</h2>
                          </div>

                          $school_html

                          <div class='profile-detail-line profile-inline-line profile-detail-line-degree'>
                            <label>" . t("Degree$s:") . "</label> <span class='profile-line-content'>$degree_title_div</span>

                          </div>

                          <div class='profile-detail-line profile-inline-line profile-detail-line-cat-year'>
                            <label>" . t("Catalog Year:") . "</label> <span class='profile-line-content'>$cat_year</span>
                          </div>
                          ";

    if (isset($extra_profile_items['left_side'])) {
      foreach ($extra_profile_items['left_side'] as $details) {
        $label = @$details['label'];
        $content = @$details['content'];
        $extra_class = @$details['extra_class'];
        $mobile_content = @$details['mobile_content'];  // displayed in an initially-hidden span, if provided.
        if ($mobile_content) {
          $mobile_content = "<span style='display:none;' class='mobile-profile-line-content'>$mobile_content</span>";
          $extra_class .= " has-mobile-content";
        }

        $rtn .= "<div class='profile-detail-line profile-inline-line $extra_class'>
                      <label>$label</label> <span class='profile-line-content'>$content</span>$mobile_content
                 </div>";
      }
    }



    $rtn .= "</div>


              <div class='profile-top-details-wrapper profile-top-wrapper-right-side'>

                          <div class='profile-detail-line profile-detail-line-empty'>
                            $right_side_top  <!-- need something here so it lines up correctly -->
                          </div>

                          <div class='profile-detail-line profile-inline-line profile-detail-line-rank'>
                            <label>" . t("Classification:") . "</label> <span class='profile-line-content'>$rank</span>
                          </div>

                          <div class='profile-detail-line profile-inline-line profile-detail-line-cumu'>
                            <label>" . t("Cumulative:") . "</label> <span class='profile-line-content'>{$student->cumulative_hours} " . t("hrs") . " / " . fp_truncate_decimals($student->gpa, 3) . " " . t("GPA") . "</span>
                          </div>

                          ";

    if (isset($extra_profile_items['right_side'])) {
      foreach ($extra_profile_items['right_side'] as $details) {
        $label = @$details['label'];
        $content = @$details['content'];
        $extra_class = @$details['extra_class'];
        $mobile_content = @$details['mobile_content'];  // displayed in an initially-hidden span, if provided.

        if ($mobile_content) {
          $mobile_content = "<span style='display:none;' class='mobile-profile-line-content'>$mobile_content</span>";
          $extra_class .= " has-mobile-content";
        }

        $rtn .= "<div class='profile-detail-line profile-inline-line $extra_class'>
                      <label>$label</label> <span class='profile-line-content'>$content</span>$mobile_content
                 </div>";
      }
    }


    $rtn .= "
              </div>


              <div class='clear'></div>

            </div>

            ";


  return $rtn;
}






/**
 * Return the HTML for breadcrumbs for the current page we are on.
 * Will skip any breadcrumbs we do not have permission to access.
 */
function fp_render_breadcrumbs() {
  $rtn = "";

  $c = 0;

  if (isset($GLOBALS['fp_breadcrumbs']) && is_array($GLOBALS['fp_breadcrumbs'])) {

    $crumbs = $GLOBALS['fp_breadcrumbs'];

    $rtn .= "<div id='breadcrumb-inner-wrapper'>
               <ul class='breadcrumbs'>";

    foreach ($crumbs as $crumb) {
      $z_index = 20 - $c;

      $text = @$crumb['text'];
      $path = @$crumb['path'];
      $query = @$crumb['query'];
      $attributes = @$crumb['attributes'];


      // Do we have permission for this menu item?  If not, do not render this breadcrumb.
      if ($path != "<front>") {
        $item = menu_get_item($path);
        if (!menu_check_user_access($item)) {
          continue;
        }
      }

      if (!$attributes || !is_array($attributes)) {
        $attributes = array();
        $attributes['style'] = '';
      }

      $attributes['style'] .= " z-index: $z_index;";


      $link = l($text, $path, $query, $attributes);

      $extra_class = "";
      if ($c == 0) $extra_class .= "first";
      if ($c == count($crumbs) -1) $extra_class .= " last";

      $rtn .= "<li class='crumbs $extra_class' style='z-index: $z_index;' >
                  $link
               </li>";


      $c++;
    }


      $rtn .= "</ul>
                </div>";

  }

  return $rtn;
} // fp_render_breadcrumbs



/**
 * Returns the HTML for the left sidebar content.
 */
function fp_render_sidebar_left_content($bool_for_hamburger_menu = FALSE) {
  global $user;

  $html = "";

  // Our links....  (we will check permissions later based on the path)
  $links = array();

  if (!isset($_SESSION["fp_logged_in"]) || $_SESSION["fp_logged_in"] != TRUE) {
    // user is not logged in.  The top-most link should be to log in.
    $links[] = array(
      'path' => 'login',
      'icon' => 'fa-sign-in',
      'desc' => t('Login'),
      'class' => 'login',
      'weight' => 0,
    );
  }
  else {
    // the user is logged in normally, the top-most link should be the dashboard
    $links[] = array(
      'path' => '<front>',
      'icon' => 'fa-home',
      'desc' => t('Dashboard'),
      'class' => 'home',
      'weight' => 0,
    );
  }
  if ($user->is_student) {

    $path = 'student-profile';

    // If the profile path is disabled by system settings, then make Engagements default.
    $disabled_tabs = variable_get('system_disable_student_tabs', array());
    if (($disabled_tabs['profile'] ?? '') == 'profile') {
      $path = 'engagements';

      // If the engagements tab is also disabled, then set the path to 'degree'
      if (($disabled_tabs['engagements'] ?? '') == 'engagements') {
        $path = 'view';
      }

    }



    $links[] = array(
      'path' => $path,
      'icon' => 'fa-graduation-cap',
      'desc' => t('My Profile'),
      'class' => 'my-profile',
      'weight' => 100,
    );




  }
  $links[] = array(
    'path' => 'calendar',
    'icon' => 'fa-calendar',
    'desc' => t('Appointments'),
    'class' => 'appointments',
    'weight' => 200,
  );
  $links[] = array(
    'path' => 'student-search',
    'icon' => 'fa-users',
    'desc' => t('Students'),
    'class' => 'students',
    'weight' => 300,
  );
  $links[] = array(
    'path' => 'tools/course-search',
    'icon' => 'fa-book',
    'desc' => t('Courses'),
    'class' => 'courses',
    'weight' => 400,
  );
  $links[] = array(
    'path' => 'tools/blank-degrees',
    'icon' => 'fa-university',
    'desc' => t('Degrees'),
    'class' => 'degrees',
    'weight' => 500,
  );
  $links[] = array(
    'path' => 'stats',
    'icon' => 'fa-bar-chart',
    'desc' => t('Analytics'),
    'class' => 'analytics',
    'weight' => 600,
  );
  $links[] = array(
    'path' => 'admin-tools',
    'icon' => 'fa-bolt',
    'desc' => t('Admin Tools'),
    'class' => 'admin-tools',
    'weight' => 700,
  );

  if ($bool_for_hamburger_menu) {
    // Include a handy log-out on the mobile hamburger menu
    $links[] = array(
      'path' => 'logout',
      'icon' => 'fa-sign-out',
      'desc' => t('Log Out'),
      'class' => 'logout',
      'weight' => 9999,
    );
  }


  // Call a hook to possibly add more items or modify our list of left sidebar links.
  invoke_hook("sidebar_left_links_alter", array($bool_for_hamburger_menu, &$links));



  // Sort links by weight
  $temp = array();
  foreach ($links as $c => $details) {
    $weight = @floatval($details['weight']);
    $temp[] = "$weight ~ $c";
  }
  sort($temp);

  $new_links = array();
  foreach ($temp as $line) {
    $x = explode("~", $line);
    $i = intval($x[1]);
    $new_links[] = $links[$i];
  }
  $links = $new_links;

  // Display


  $html .= "<ul class='sidebar-left-nav'>";

  foreach ($links as $c => $link) {
    $path = $link['path'];
    $icon = $link['icon'];
    $desc = $link['desc'];
    $query = @$link['query'];
    $class = @$link['class'];

    if ($c == 0) $class .= " first";
    if ($c == count($links) - 1) $class .= " last";

    // Do we have permission for this menu item?
    if ($path != "<front>") {
      $item = menu_get_item($path);
      if (!menu_check_user_access($item)) {
        continue;
      }
    }

    // If we are here, we have access!
    $html .= "<a href='" . fp_url($path, $query) . "'>
                <li class='$class'>
                  <i class='fa $icon'></i>
                  <div class='desc'>$desc</div>
                </li>
              </a>";


  } // foreach

  $html .= "</ul>";








  return $html;

} // fp_render_sidebar_left_content


/**
 * This will create the HTML content for the "mobile hamburger" menu, which appears when we press
 * the 3 lines icon or "hamburger" icon on a mobile device.
 */
function fp_render_mobile_hamburger_menu() {
  $rtn = "";

  $rtn .= "<div id='mobile-hamburger-menu' style='display:none;'>
              <div class='mobile-top-nav'>";
  $rtn .= fp_render_top_nav_content(TRUE);
  $rtn .= "   </div>
              <div class='clear'></div>
              <div class='mobile-sidebar-content'>";
  $rtn .= fp_render_sidebar_left_content(TRUE);
  $rtn .= "   </div>
            </div>";


  return $rtn;
}





/**
 * Returns the HTML for the top navigation content of the screen itself.
 *
 * If the bool_for_hamburger_menu == true, then we are trying to render this for
 * our mobile hamburger menu, so we will alter a little bit.
 *
 */
function fp_render_top_nav_content($bool_for_hamburger_menu = FALSE) {
  global $user;
  $html = "";

  $name = "";

  if (intval($user->id) !== 2 && intval($user->id) !== 0) { // 0 = anon user, 2 == anonymous user in database
    if ($user->is_faculty) {
      $name = fp_get_faculty_name($user->cwid);
    }
    else {
      $name = fp_get_student_name($user->cwid);
    }
  }


  $badge = "";
  // If user has new alerts to view, we should show a "badge" on the icon over the alerts icon
  $alert_count_by_type = fp_get_alert_count_by_type();
  if ($alert_count_by_type && is_array($alert_count_by_type) && count($alert_count_by_type) > 0) {
    $unread_total = 0;
    foreach ($alert_count_by_type as $module => $alert_types) {
      if ($alert_types && is_array($alert_types)) {
        foreach ($alert_types as $k => $details) {
          $ur = intval( $details['unread']);
          if ($ur < 0) $ur = 0;  // bug that can happen after deletions, it can be in negative numbers.
          $unread_total += $ur;
        }
      }
    }
    if ($unread_total > 0) {
      $badge = "<span class='tub-alert-count'><i class='fa fa-circle'></i></span>";
    }
  }


  if (!$bool_for_hamburger_menu) {

    $html .= "<ul class='top-nav-ul'>";

    // If we have a student selected, then we will display a link to go back to that student.
    if ($user->is_faculty) {
      if (@trim($_SESSION["last_student_selected"]) != '') {
        $csid = trim($_SESSION["last_student_selected"]);
        $sname = trim(fp_get_student_name($csid));
        if ($sname) {
          $sname = htmlentities($sname);
          $cur_stu_link = l("<i class='fa fa-id-card-o'></i>", "student-select","current_student_id=$csid",array("title" => t("Return to @sname", array("@sname" => $sname))));
          $html .= "<li class='current-student-link'>$cur_stu_link</li>";
        }
      }
    }

    if (user_has_permission('view_any_advising_session')) {
      $html .= "<li class='student-search'>" . student_search_render_small_search() . "</li>";
    }
    if (user_has_permission('view_advisee_alerts')) {
      $html .= "<li class='alerts'><span class='tub-icon-element-alert'><a href='" . fp_url('alerts') . "'><i class='fa fa-bell'></i>$badge</a></span></li>";
    }
    $html .= "<li class='user-options last'>";




    $user_menu_links = array(
      0 => l(t('My Settings'), 'user-settings'),
      1 => l(t('Log Out'), 'logout'),
    );

    // Call a hook to allow others to modify this.
    invoke_hook('user_menu_links_alter', array(&$user_menu_links));

    $html .= "
            <div class='tub-element tub-float-right-a tub-last-element tub-user-menu tub-icon-element dropdown'>
                <div class='tub-element-wrapper'>
                  <a href='javascript:fpToggleUserMenu();' class='tub-link dropdown-trigger'>
                    <i class='fa fa-user'></i><span class='tub-caret-down'><i class='fa fa-caret-down'></i></span>
                  </a>
                </div>

                  <div id='tub-user-pulldown' style='display:none;' >
                      $name
                    <ul class='user-pulldown'>
                    ";

    foreach ($user_menu_links as $link) {
      $html .= "<li>" . $link . "</li>";
    }


    $html .= "
                    </ul>

                  </div>

            </div>



        </li>
      </ul>
    ";

    // We are also going to have a hidden "hamburger" icon, which gets displayed
    // when we shrink to mobile.  It will be invisible by default.
    $html .= "<div class='mobile-hamburger-icon' style='display: none;'>
                <a href='javascript:fpToggleHamburgerMenu();'><i class='fa fa-bars'></i></a>
              </div>";

  }
  else {
    // This IS for our *** hamburger menu **** !
    $cog_class = "cog-only";
    $html .= "<ul class='top-nav-ul'>";
    if (user_has_permission('view_advisee_alerts')) {
      $html .= "<li class='alerts first'><span class='tub-icon-element-alert'><a href='" . fp_url('alerts') . "'><i class='fa fa-bell'></i>$badge</a></span></li>";
      $cog_class = "last";
    }
    $html .= "
        <li class='user-options last $cog_class'>
          <a href='" . fp_url('user-settings') . "'><i class='fa fa-cog'></i></a>
        </li>
      </ul>
    ";

  }


  return $html;
} // fp_render_top_nav_content









/**
 * Sets whether the title should be shown on the page or not.
 */
function fp_show_title($bool_show = TRUE) {
  $GLOBALS["fp_set_show_title"] = $bool_show;
}


/**
 * This function will return the HTML to contruct a collapsible fieldset,
 * complete with javascript and style tags.
 *
 * @param String $content
 * @param String $legend
 * @param bool $bool_start_closed
 * @return String
 */
function fp_render_c_fieldset($content, $legend = "Click to expand/collapse", $bool_start_closed = false, $extra_class = "")
{

  // Create a random ID for this fieldset, js, and styles.
  $id = md5(rand(9,99999) . time());

  $start_js_val = 1;
  $fsstate = "open";
  $content_style = "";

  if ($bool_start_closed) {
    $start_js_val = 0;
    $fsstate = "closed";
    $content_style = "display: none;";
  }

  $js = "<script type='text/javascript'>

  var fieldset_state_$id = $start_js_val;

  function toggle_fieldset_$id() {

    var content = document.getElementById('content_$id');
    var fs = document.getElementById('fs_$id');

    if (fieldset_state_$id == 1) {
      // Already open.  Let's close it.
      fieldset_state_$id = 0;
      \$(content).slideUp('medium');
      fs.className = 'c-fieldset-closed-$id c-fieldset-closed c-fieldset';
    }
    else {
      // Was closed.  let's open it.
      fieldset_state_$id = 1;

      \$(content).slideDown('medium');
      fs.className = 'c-fieldset-open-$id c-fieldset-open c-fieldset';
    }
  }
  </script>";

  $rtn = "
    <fieldset class='c-fieldset-$fsstate-$id c-fieldset-$fsstate c-fieldset $extra_class' id='fs_$id'>
      <legend><a href='javascript: toggle_fieldset_$id();' class='nounderline'>$legend</a></legend>
      <div id='content_$id' class='c-fieldset-content' style='$content_style'>
        $content
      </div>
    </fieldset>
    $js

  <style>
  fieldset.c-fieldset-open-$id {
    border: 1px solid;
  }

  fieldset.c-fieldset-closed-$id {
    border: 1px solid;
    border-bottom-width: 0;
    border-left-width: 0;
    border-right-width: 0;
  }

  legend a {
    text-decoration: none;
  }

  </style>

  ";


  return $rtn;
}



/**
 * Create a "sub-tab" array, which looks like a standard tab_array,
 * but it contains only this page's sub-tab siblings.
 */
function fp_build_sub_tab_array($page) {
  global $current_student_id;

  $tab_array = array();

  $tab_family = $page["router_item"]["tab_family"];
  $active_tab_path = $page["path"];

  $items = menu_get_items_in_tab_family($tab_family);

  foreach ($items as $item) {

    // Does the user have access to this subtab?
    if (!menu_check_user_access($item)) {
      continue;
    }

    $title = $item["title"];
    $path = $item["path"];
    $active = FALSE;
    $on_click = @$page["page_settings"]["tab_on_click"];
    if ($on_click == "") {
      // Just a simple link to the path
      // Add the current_student_id to the query!
      $query = "current_student_id=$current_student_id";

      $turl = fp_url($path, $query, TRUE);

      $on_click = "window.location=\"" . $turl . "\"";
    }

    if ($path == $active_tab_path) {
      // This is the current page we are on.
      $active = TRUE;
      $title = $page["title"];
      $on_click = "";
    }

    $tab_array[] = array(
      "title" => $item["title"],
      "active" => $active,
      "on_click" => $on_click,
    );

  }

  return $tab_array;
}


/**
 * Looks at the current page array and returns a valid $tab_array
 * Meant for the top of the screen.
 */
function fp_build_tab_array($page) {
    global $current_student_id;
    $tab_array = array();

    $tab_family = $page["router_item"]["tab_family"];
    $active_tab_path = $page["path"];

    if ($page["router_item"]["tab_parent"] != "") {
      // We want to know the PARENT's tab_family, so we can display the correct tabs at the top of the page.
      $tab_parent = $page["router_item"]["tab_parent"];

      $item = menu_get_item($tab_parent);
      $tab_family = $item["tab_family"];
      $active_tab_path = $item["path"];

    }

    // look for other possible tabs, based on the "tab_family" value
    // Also check to make sure the user has access to the tab.
    $items = menu_get_items_in_tab_family($tab_family);
    foreach ($items as $item) {

      // Does the user have access to this tab?
      if (!menu_check_user_access($item)) {
        continue;
      }

      $title = $tab_title = $item["title"];
      $path = $item["path"];

      if (isset($item["page_settings"]["tab_title"])) {
        $tab_title = $item["page_settings"]["tab_title"];
      }


      if (strstr($path, "%")) {
        // it contains at least one wildcard.  So, let's replace the wildcard with
        // the coresponding value from arg().  Ex:  content/%/edit  (when we are on content/55/view) would == content/55/edit.
        $new_path = "";
        $pieces = explode("/", $path);
        foreach ($pieces as $c => $piece) {
          if ($piece != "%") {
            $new_path .= $piece . "/";
          }
          else {
            // This WAS a wildcard.  Replace with arg() value.
            $a = @arg($c);
            $new_path .= $a . "/";
          }

        }
        $new_path = rtrim($new_path, "/"); // get rid of trailing slash.
        $path = $new_path;
      }


      $active = FALSE;
      $on_click = @$page["page_settings"]["tab_on_click"];
      $href = "";
      if ($on_click == "") {
        // Just a simple link to the path
        // Include the current_student_id!
        $query = "current_student_id=$current_student_id";

        $turl = fp_url($path, $query, TRUE);

        //$on_click = "window.location=\"" . $turl . "\"";
        $href = $turl;
      }

      if ($path == $active_tab_path) {
        // This is the current page we are on.
        $active = TRUE;
        $title = $tab_title;
        $on_click = "";
        $href = "";
      }

      $tab_array[] = array(
        "title" => $tab_title,
        "active" => $active,
        "on_click" => $on_click,
        "href" => $href,
      );

    }

    return $tab_array;
}









/**
 * Similar to render_tab_array.
 */
function fp_render_sub_tab_array($tab_array) {
  global $current_student_id;

  $rtn = "";

  $rtn .= "<div class='sub-tabs'>";

  foreach ($tab_array as $tab) {
    $title = @$tab["title"];
    $on_click = @$tab["on_click"];
    $active = @$tab["active"];
    $type = @$tab["type"];

    if (!$title) $title = "";

    $extra_class = "";
    if ($active) $extra_class = "gradbutton-active";

    $extra_class .= " sub-tab-title-" . fp_get_machine_readable(strtolower(trim($title)));


    if ($type == "link" ) {
      // Render as a standard link
      $label = @$tab["label"];
      $text = @$tab["text"];
      $link_title = @$tab['link_title'];
      $link_class = @$tab['link_class'];

      $rtn .= "<span class='subtab-link-wrapper $link_class'>";

      if ($label) {
        $rtn .= "<label>$label</label>";
      }
      $rtn .= "<a href='javascript: $on_click' class='fp-sub-tab-link $extra_class' title='$link_title'>$text</a>";
      $rtn .= "</span>";

    }
    else {
      // Render as a button
      $rtn .= fp_render_button($title, $on_click, $extra_class);
    }

  }


  $rtn .= "</div>";

  return $rtn;
}



/**
 * Returns the HTML to draw a pretty button.
 *
 */
function fp_render_button($title, $on_click, $extra_class = "") {

  // we want to add on a variable-name-friendly version of the title to extra_class.
  $extra_class .= " fp-render-button-" . fp_get_machine_readable(strtolower($title));

  $rtn = "<button class='fp-render-button $extra_class' onClick='$on_click'>$title</button>";

  return $rtn;
}


function fp_render_mobile_tab_array($tab_array) {

    $rtn = "";

    $js_vars = "var mobileTabSelections = new Array(); ";

    if (count($tab_array) <= 1) return "";


    $rtn .= "<table border='0' width='200' cellpadding='0' cellspacing='0' class='fp-mobile-tabs'>
             <td>
             <b>" . t("Display") . ": </b>";


    $rtn .= "<select onChange='executeSelection()' id='mobileTabsSelect'>";

    for ($t = 0; $t < count($tab_array); $t++)
    {
      $title = $tab_array[$t]["title"];
      $active = $tab_array[$t]["active"];
      $on_click = $tab_array[$t]["on_click"];

      if ($title == "")
      {
        continue;
      }
      $sel = ($active == true) ? $sel = "selected":"";

      $rtn .= "<option $sel value='$t'>$title</option>";

      $js_vars .= "mobileTabSelections[$t] = '$on_click'; \n";

    }

    $rtn .= "</select>
              </td></table>";


    $rtn .= '
      <script type="text/javascript">
      ' . $js_vars . '

      function executeSelection() {
        var sel = document.getElementById("mobileTabsSelect").value;

        var statement = mobileTabSelections[sel];
        // Lets execute the statement...
        eval(statement);

      }


      </script>
    ';

    return $rtn;
}



/**
 * Given a propperly formatted tab_array, this will return the HTML
 * to draw it on a page.
 *
 * @param array $tab_array
 *       - Array should have this structure:
 *         - $tab_array[i]["title"] = The title or caption of the tab. "main", or "Edit", etc.
 *         - $tab_array[i]["active"] = boolean.  True if this is the tab we are currently looking at.
 *         - $tab_array[i]["href"] = This is a complete href for the link we will create.
 *         - $tab_array[i]["on_click"] = This is an onClick command for javascript.  If this is set, href will be ignored.
 *
 * @return string
 */
function fp_render_tab_array($tab_array) {

  $rtn = "";

  if (count($tab_array) == 0) return "";

  $img_path = fp_theme_location() . "/images";

  $rtn .= "<ul class='tabs'>";

  for ($t = 0; $t < count($tab_array); $t++)
  {
    $title = @$tab_array[$t]["title"];

    // If the title has a replacement pattern in it, then we need to call a hook to see what it should be set to.
    if (strpos(($title ?? ''), "%") !== 0) {
      $title = menu_convert_replacement_pattern($title);
    }

    $active = @$tab_array[$t]["active"];
    $on_click = @$tab_array[$t]["on_click"];
    $href = @$tab_array[$t]["href"];

    $extra_a = "";
    if ($on_click != "") {
      $href = "javascript:void(0);";
      $extra_a = "onclick='$on_click'";
    }

    if ($title == "")
    {
      continue;
    }

    $extra_class = "inactive";
    if ($active) {
       $extra_class = "active";
       // the active tab is not clickable.
       $href = 'javascript:void(0);';
       $extra_a = '';
    }

    if ($t == 0) $extra_class .= " first";
    if ($t == count($tab_array) - 1) $extra_class .= " last";

    // Also add in a machine-friendly class based on the title and position

    $extra_class .= " tab-count-$t tab-title-" . fp_get_machine_readable(strtolower(trim($title)));

    $rtn .= "<li class='tab $extra_class'><a href='$href' $extra_a>$title</a></li>";

  }


  $rtn .= "</ul>";


  return $rtn;

}




function display_not_found() {

  header("HTTP/1.0 404 Not Found", TRUE, 404);

  $page = array(
    "title" => t("Not Found"),
    "content" => "<h2>" . t("Page not found") . "</h2>" . t("Sorry, the requested page could not be found."),
  );

  $location = @$_SERVER["REQUEST_URI"];

  watchdog("page_not_found", "404 - Page not found (" . strip_tags($location) . ")", array(), WATCHDOG_DEBUG);


  // Should we ban this IP?
  system_check_should_ban_ip();


  fp_display_page($page);
  die;
}


function display_access_denied() {

  header('HTTP/1.1 403 Forbidden', TRUE, 403);

  $page = array(
    "title" => t("Access Denied"),
    "content" => "<h2>" . t("Access Denied") . "</h2>" . t("Sorry, but you do not have access to the requested page or function."),
  );

  $location = @$_SERVER["REQUEST_URI"];

  watchdog("access_denied", "403 - Access denied (" . strip_tags($location) . ")", array(), WATCHDOG_DEBUG);

  // Should we ban this IP?
  system_check_should_ban_ip();

  fp_display_page($page);
  die;
}



/**
 * Return the theme location
 */
function fp_theme_location($bool_include_base_path = TRUE) {

  $p = variable_get("theme",'themes/fp_clean');

  // The theme hasn't been set for some reason
  if ($p == "") {
    $p = "themes/fp_clean";
  }

  if ($bool_include_base_path) {
    $bp = base_path();

    // The following code is to fix a bug where we might end up with // as our location, if our base_path is simply "/", meaning,
    // the site is located on a bare domain.
    if ($bp != "/") {
      $p = base_path() . "/" . $p;
    }
    else {
      $p = "/" . $p;
    }
  }

  return $p;
}


/**
 * Will draw a string in a pretty curved box.  Used for displaying semester
 * titles.
 *
 * @param string $title
 * @return string
 */
function fp_render_curved_line($text) {
  depricated_message();
  // Will simply draw a curved title bar containing the $title
  // as the text.

  $rtn = "
    <div class='blueTitle' style='text-align: center; border-radius: 5px; padding: 1px;'>
      <span class='tenpt'><b>$text</b></span>
    </div>
  ";


  return $rtn;

}


function fp_render_section_title($text, $extra_class = '') {

  $extra_class .= " section-box-text-" . fp_get_machine_readable(strtolower(trim($text)));

  $rtn = "<div class='section-box-title section-box-title-$extra_class'>$text</div>";


  return $rtn;

}




/**
 * Will draw a string in a pretty square box.  Used for displaying semester
 * titles.
 *
 * @param string $title
 * @return string
 */
function fp_render_square_line($text) {
  depricated_message("render_square_line");
  $rtn = "";

  $rtn .= "
        <table border='0' width='100%' cellpadding='0' cellspacing='0'>
       <tr>
        <td width='10%' align='left' valign='top'></td>
        <td width='80%' align='center' rowspan='2'>
         <span class='tenpt' style='color: white' ><b>$text</b></span>
        </td>
        <td width='10%' align='right' valign='top'></td>
       </tr>
       <tr>
        <td align='left' valign='bottom'></td>
        <td align='right' valign='bottom'></td>
       </tr>
      </table>

      ";

  return $rtn;

}


function fp_render_menu_item($item, $bool_check_user_access = TRUE) {
  $rtn = "";

  if ($bool_check_user_access) {
    if (!menu_check_user_access($item)) {
      return "";
    }
  }

  $description = $item["description"];
  $title = $item["title"];
  $safe_title = htmlentities(trim($title), ENT_QUOTES);
  //$url = $GLOBALS["fp_system_settings"]["base_path"] . "/" . $item["path"];
  $url = fp_url($item["path"], "", TRUE);
  $target = @$item["page_settings"]["target"];
  $menu_icon = @$item["page_settings"]["menu_icon"];
  $extra_class = "";
  if ($menu_icon != "") {

    if (!strstr($menu_icon, 'http')) {
      $base_url = $GLOBALS['fp_system_settings']['base_url'];
      if ($base_url == "/") $base_url = "";  // so that we don't have // as a path, because of the next line.

      if (substr($menu_icon, 0, 1) == '/') {
        // Already starts with a slash, so probably should just use as is.
        // take no action.
      }
      else {
        $menu_icon = $base_url . "/" . $menu_icon;
      }
    }
    $icon_img = "<img src='$menu_icon' border='0' class='fpmn-icon' alt='$safe_title'>";
  }
  else {
    $icon_img = "<span class='fp-menu-item-no-icon'></span>";
  }

  if (!$description) $extra_class = "fp-menu-item-tight";


  $machine_title = strtolower(fp_get_machine_readable($title));
  $machine_path = strtolower(fp_get_machine_readable($item['path']));

  $extra_class .= " fp-menu-item-title-$machine_title fp-menu-item-path-$machine_path ";

  $rtn .= "<div class='fp-menu-item $extra_class'>
            <div class='fp-menu-item-link-line'>
              <a href='$url' target='$target'>$icon_img$title</a>
            </div>
            ";
  if ($description) {
    $rtn .= " <div class='fp-menu-item-description'>$description</div>";
  }
  $rtn .= "</div>";

  return $rtn;
}


/**
 * Alias of pretty_print($var)
 */
function ppm($var, $b = FALSE) {
  return pretty_print($var, $b);
}

function pretty_print($var, $bool_return = FALSE) {
  $x = "<pre>" . print_r($var, true) . "</pre>";

  if ($bool_return) return $x;

 print $x;

}


