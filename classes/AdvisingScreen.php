<?php


class AdvisingScreen extends stdClass
{
  public $width_array, $popup_width_array, $script_filename, $is_on_left, $box_array;
  public $degree_plan, $student, $bool_popup, $footnote_array, $flightpath;
  public $screen_mode, $db, $bool_print, $view, $user_settings;
  public $bool_blank, $bool_hiding_grades, $bool_force_pie_charts;
  public $admin_message;

  // Variables for the template/theme output...
  public $theme_location, $page_content, $page_has_search, $page_tabs, $page_on_load;
  public $page_hide_report_error, $page_scroll_top, $page_is_popup;
  public $page_title, $page_extra_css_files, $page_body_classes, $page_display_currently_advising;



  /**
   * This is the constructor.  Must be named this for inheritence to work
   * correctly.
   *
   * @param string $script_filename
   *   - This is the script which forms with POST to.  Ex: "advise.php"
   *
   * @param FlightPath $flightpath
   *   - FlightPath object.
   *
   * @param string $screen_mode
   *   - A string describing what "mode" we are in.
   *     - If left blank, we assume it is full-screen and normal.
   *     - If set to "popup" then we are in a popup window, and we will
   *       not draw certain elements.
   *
   */
  function __construct($script_filename = "", FlightPath $flightpath = null, $screen_mode = "")
  {
    $this->width_array = Array("10%", "8%","8%", "17%", "26%", "10%", "10%", "9%");
    $this->popup_width_array = Array("17%", "1%", "1%", "15%", "26%", "15%", "15%", "10%");

    $this->script_filename = $script_filename;
    $this->is_on_left = true;
    $this->box_array = array();
    $this->footnote_array = array();

    $this->page_extra_css_files = array();

    $this->flightpath = $flightpath;
    if ($flightpath != null) {
      $this->degree_plan = $flightpath->degree_plan;
      $this->student = $flightpath->student;
    }


    $this->db = get_global_database_handler();

    if ($screen_mode == "popup")
    {
      $this->bool_popup = true;
    }

    $this->bool_blank = false;

    $this->screen_mode = $screen_mode;




  } // construct



  /**
   * This function will attempt to determine automatically
   * if we are on a mobile device.  If so, it will set
   * $this->page_is_mobile = TRUE
   *
   */
function determine_mobile_device(){
  depricated_message('determine_mobile_device is depricated');

  $user_agent = $_SERVER['HTTP_USER_AGENT'];

  $look_for = array(
    "ipod",
    "iphone",
    "android",
    "opera mini",
    "blackberry",
    "(pre\/|palm os|palm|hiptop|avantgo|plucker|xiino|blazer|elaine)",
    "(iris|3g_t|windows ce|opera mobi|windows ce; smartphone;|windows ce; iemobile)",
    "(smartphone|iemobile)",
    );

  foreach ($look_for as $test_agent) {
    if (preg_match('/' . $test_agent . '/i',$user_agent)) {
       $this->page_is_mobile = true;
       break;
    }
  }


  $GLOBALS["fp_page_is_mobile"] = $this->page_is_mobile;

} // ends function mobile_device_detect



/**
 * This function will return the HTML to contruct a collapsible fieldset,
 * complete with javascript and style tags.
 *
 * @param String $content
 * @param String $legend
 * @param bool $bool_start_closed
 * @return String
 */
function draw_c_fieldset($content, $legend = "Click to expand/collapse", $bool_start_closed = false)
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
      content.style.display = 'none';
      fs.className = 'c-fieldset-closed-$id';
    }
    else {
      // Was closed.  let's open it.
      fieldset_state_$id = 1;
      content.style.display = '';
      fs.className = 'c-fieldset-open-$id';
    }
  }
  </script>";

  $rtn = "
    <fieldset class='c-fieldset-$fsstate-$id' id='fs_$id'>
      <legend><a href='javascript: toggle_fieldset_$id();' class='nounderline'>$legend</a></legend>
      <div id='content_$id' style='$content_style'>
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
 * Simply builds a single menu item.
 *
 * @return string
 */
function draw_menu_item($url, $target, $icon_img, $title, $description = "") {

  $rtn = "";
  $extra_class = '';
  if (!$description) $extra_class = "fp-menu-item-tight";

  $rtn .= "<div class='fp-menu-item $extra_class'>
            <div class='fp-menu-item-link-line'>
              <a href='$url' target='$target'>$icon_img $title</a>
            </div>
            ";
  if ($description) {
    $rtn .= " <div class='fp-menu-item-description'>$description</div>";
  }
  $rtn .= "</div>";

  return $rtn;
}


/**
 * Uses the draw_menu_item method to draw the HTML for
 * all the supplied menu items, assuming the user has
 * permission to view them.
 *
 * Returns the HTML or "" if no menus could be drawn.
 *
 * @param array $menu_array
 */
function draw_menu_items($menu_array) {

  $rtn = "";

  if (count($menu_array) == 0) return "";


  foreach($menu_array as $item) {
    $url = $item["url"];
    $target = $item["target"];
    $icon = $item["icon"];
    if ($icon) {
      $icon_img = "<img src='$icon' border='0'>";
    }
    else {
      $icon_img = "<span class='fp-menu-item-no-icon'></span>";
    }

    $title = $item["title"];
    $description = $item["description"];

    // Make sure they have permission!
    if ($item["permission"] != "") {
      if (!user_has_permission($item["permission"])) {
        // User did NOT have permission to view this link.
        continue;
      }
    }

    $rtn .= $this->draw_menu_item($url, $target, $icon_img, $title, $description);

  }

  return $rtn;

}


  /**
   * This method outputs the screen to the browser by performing
   * an include(path-to-theme-file.php).  All necessary information
   * must be placed into certain variables before the include happens.
   *
   */
  function output_to_browser()
  {
    global $user, $current_student_id;
    // This method will output the screen to the browser.
    // outputs the $page_content variable.

    // Figure out our school id.

    $school_id = $user->school_id;
    if ($current_student_id) {
      $school_id = db_get_school_id_for_student_id($current_student_id);
    }
    else if (isset($this->student) && is_object($this->student)) {
      $school_id = $this->student->school_id;
    }
    else if (isset($this->degree_plan) && is_object($this->degree_plan)) {
      $school_id = $this->degree_plan->school_id;
    }



    $theme_location = fp_theme_location();  // file location of the theme folder


    $page_logo_url = variable_get("logo_image_url", "");
    if ($page_logo_url == "") {
      $page_logo_url = $theme_location . "/images/fp_banner_default.png";
    }

    $page_content = $this->page_content;
    $page_tabs = $this->page_tabs;

    $page_has_search = $this->page_has_search;
    $page_on_load = $this->page_on_load;
    $page_scroll_top = $this->page_scroll_top;
    $page_is_popup = $this->page_is_popup;
    $page_title = $this->page_title;
    $page_body_classes = $this->page_body_classes;

    // Are we explicitly setting that this is a popup in the URL?
    if (isset($_REQUEST['window_mode']) && $_REQUEST['window_mode'] == 'popup') {
      $page_is_popup = TRUE;
    }

    $page_extra_js_files = "";
    $page_extra_js_settings = "";
    $page_extra_css_files = "";
    $page_breadcrumbs = "";

    $system_name = variable_get("system_name", "FlightPath");

    if ($page_title == "") {
      // By default, page title is this...
      $page_title = variable_get_for_school("school_initials", "DEMO", $school_id) . " " . $system_name;
    }

    $page_title = menu_convert_replacement_pattern($page_title);

    $page_display_title = $page_title;
    if (isset($GLOBALS["fp_set_show_title"]) && $GLOBALS["fp_set_show_title"] === FALSE) {
      $page_display_title = "";
    }

    $page_title = strip_tags($page_title);

    $page_breadcrumbs = fp_render_breadcrumbs();


    if ($this->student && $this->page_display_currently_advising == TRUE && !$page_is_popup) {
      $page_student_profile_header = fp_render_student_profile_header();
    }


    $page_hide_report_error = $this->page_hide_report_error;


    // We don't have permission, so do not show the report error link regardless.
    if (!user_has_permission('access_popup_report_contact')) {
      $page_hide_report_error = TRUE;
    }


    $print_option = "";
    if ($this->bool_print == true) {
      $page_body_classes .= " bool-print";
    }


    if ($page_is_popup) {
      $page_body_classes .= " page-is-popup";
    }


    $page_body_classes .= " school-id-" . $school_id;
    if (module_enabled('schools')) {
      $page_body_classes .= " school-code-" . schools_get_school_code_for_id($school_id);
    }


    // A dummy query-string is added to filenames, to gain control over
    // browser-caching. The string changes on every update or full cache
    // flush, forcing browsers to load a new copy of the files, as the
    // URL changed.
    $page_css_js_query_string = variable_get('css_js_query_string', '0');


    // Add extra JS files.
    if (is_array($GLOBALS["fp_extra_js"]) && count($GLOBALS["fp_extra_js"]) > 0) {
     foreach ($GLOBALS["fp_extra_js"] as $js_file_name) {
       $page_extra_js_files .= "<script type='text/javascript' src='$js_file_name?$page_css_js_query_string'></script> \n";
     }
    }



    // Load any extra CSS files which addon modules might have added.
    if (isset($GLOBALS["fp_extra_css"]) && is_array($GLOBALS["fp_extra_css"]) && count($GLOBALS["fp_extra_css"]) > 0) {
     foreach ($GLOBALS["fp_extra_css"] as $css_file_name) {
       $page_extra_css_files .= "<link rel='stylesheet' type='text/css' href='$css_file_name?$page_css_js_query_string' /> \n";
     }
    }


    // Javascript settings...  (I know this would be better as a recursive function.  For now,
    // you can have up to 3 layers deep.  Sorry for it looking so ugly.
    $page_extra_js_settings .= "var FlightPath = new Object();   \n";
    $page_extra_js_settings .= " FlightPath.settings = new Object();   \n";
    foreach ($GLOBALS["fp_extra_js_settings"] as $key => $val) {
      if (is_array($val)) {
        $page_extra_js_settings .= "FlightPath.settings.$key = new Array();  \n";
        foreach ($val as $k => $v) {
          if (is_array($v)) {
            $page_extra_js_settings .= "FlightPath.settings.$key" . "['" . "$k'] = new Array();  \n";
            foreach ($v as $kk => $vv) {
              $page_extra_js_settings .= "FlightPath.settings.$key" . "['" . "$k']['$kk'] = '$vv';  \n";
            }
          }
          else {
            $page_extra_js_settings .= "FlightPath.settings.$key" . "['" . "$k'] = '$v';  \n";
          }
        }
      }
      else {
        $page_extra_js_settings .= "FlightPath.settings.$key = '$val';  \n";
      }
    }

    // Scrolling somewhere?  Add it to the page_on_load...
    if (trim($page_scroll_top != "")) {
      $page_on_load .= " scrollTo(0, $page_scroll_top);";
    }



    // Add our dialog HTML if the page isn't a popup.
    if (!$page_is_popup) {
      $page_content .= "
              <!-- iframe dialog, for use by javascript later on -->
                <div id='fp-iframe-dialog-small' style='display: none;' title=''>
                  <iframe id='fp-iframe-dialog-small-iframe' class='dialog-iframe' ></iframe>
                </div>
                <div id='fp-iframe-dialog-large' style='display: none;' title=''>
                  <iframe id='fp-iframe-dialog-large-iframe' class='dialog-iframe' ></iframe>
                </div>

      ";
    }
    else {
      // The page is in a dialog.  In order to cope with a strange bug in Chrome (as of 10-29-2020), we need
      // to "nudge" the dialog window 1 pixel, or sometimes the internal iframe will not show up.
      // We do this after it loads.

      $page_on_load .= "\n\n // From: https://stackoverflow.com/questions/9847580/how-to-detect-safari-chrome-ie-firefox-and-opera-browser  \n\n";
      $page_on_load .= ' var browser = (function() {
                            var test = function(regexp) {return regexp.test(window.navigator.userAgent)}
                            switch (true) {
                                case test(/edg/i): return "Microsoft Edge";
                                case test(/trident/i): return "Microsoft Internet Explorer";
                                case test(/firefox|fxios/i): return "Mozilla Firefox";
                                case test(/opr\//i): return "Opera";
                                case test(/ucbrowser/i): return "UC Browser";
                                case test(/samsungbrowser/i): return "Samsung Browser";
                                case test(/chrome|chromium|crios/i): return "Google Chrome";
                                case test(/safari/i): return "Apple Safari";
                                default: return "Other";
                            }
                        })();';

      $page_on_load .= " if (browser == 'Google Chrome') {
                            parent.fpNudgeDialog();
                          }";


    }



    // Grab the appropriate sidebar & top nav content (if any)
    $page_sidebar_left_content = $page_top_nav_content = "";

    if (!$page_is_popup) {
      $page_sidebar_left_content = fp_render_sidebar_left_content();
      $page_top_nav_content = fp_render_top_nav_content();
    }


    if ($page_sidebar_left_content) {
      $page_body_classes .= " has-sidebar-left";
    }

    if ($page_tabs) {
      $page_body_classes .= " has-page-tabs";
    }


    // We are going to try to include the theme.  If it can't be found, we will display a CORE theme, and display a message.
    $theme = variable_get("theme","themes/fp_clean");


    $head_template_filename = $theme . "/head.tpl.php";
    $page_template_filename = $theme . "/page.tpl.php";

    // If there is a special theme file we should be using based on the URL, set it here.
    $q = fp_trim(strtolower((string) @$_REQUEST['q']));
    if ($q) {
      $q = trim(str_replace("/", "-", $q));
      if ($q) {
        if (file_exists($theme . "/page--" . $q . ".tpl.php")) {
          $page_template_filename = $theme . "/page--" . $q . ".tpl.php";
        }
      }
    }


    if (!file_exists($page_template_filename)) {
      print "<p><b>Theme Error:</b> Tried to load template from: $page_template_filename, but this file could not be found.
                <br>This is possibly because either the filename or the directory specified does not exist.
                <br>Contact site administrator.</p>";
      $page_template_filename = "themes/fp_clean" . "/page.tpl.php";
    }



    // Are we adding any external CSS files?
    $external_css = variable_get("external_css", "");
    if ($external_css) {
      $temp = explode(",",$external_css);
      foreach ($temp as $line) {
        if (trim($line) == "") continue;

        $page_extra_css_files .= "<link rel='stylesheet' type='text/css' href='" . trim($line) . "?$page_css_js_query_string' /> \n";

      }
    }


    /////////////////////////
    // Output to browser:


    include($head_template_filename);
    include($page_template_filename);


  } // output_to_browser



  /**
   * This function simply adds a reference for additional CSS to be
   * link'd in to the theme.  It is used by add-on modules.
   *
   * The filename needs to be from the reference of the base
   * FlightPath install.
   *
   * Ex:  $screen->add_css("modules/course_search/css/style.css");
   *
   * @param String $filename
   */
  function add_css($filename) {

    $this->page_extra_css_files[] = $filename;

  }




/**
 * Clear the session varibles.
 *
 */
  function clear_variables()
  {
    // Clear the session variables.
    $csid = $_REQUEST["current_student_id"];

    $_SESSION["advising_student_id$csid"] = "";
    $_SESSION["advising_student_id"] = "";
    $_SESSION["advising_major_code$csid"] = "";
    $_SESSION["advising_track_code$csid"] = "";
    $_SESSION["advising_track_degree_ids$csid"] = "";
    $_SESSION["advising_term_id$csid"] = "";
    $_SESSION["advising_what_if$csid"] = "";
    $_SESSION["what_if_major_code$csid"] = "";

    $_SESSION["cache_f_p$csid"] = "";
    $_SESSION["cache_what_if$csid"] = "";

  }


  /**
   * Constructs the HTML which will be used to display
   * the student's transfer credits
   *
   */
  function build_transfer_credit()
  {
    $pC = "";
    $is_empty = true;
    $pC .= $this->draw_semester_box_top("Transfer Credit", FALSE);
    // Basically, go through all the courses the student has taken,
    // And only show the transfers.  This is similar to Excess credit.

    $student_id = $this->student->student_id;
    $school_id = db_get_school_id_for_student_id($student_id);


    // Now, sort alphabetical and display on screen.

    $this->student->list_courses_taken->sort_alphabetical_order(false, true);
    $this->student->list_courses_taken->reset_counter();
    while($this->student->list_courses_taken->has_more())
    {
      $course = $this->student->list_courses_taken->get_next();

      // Skip non transfer credits.
      if ($course->bool_transfer != true)
      {
        continue;
      }

      $bool_add_footnote = false;
      if ($course->get_has_been_displayed($course->req_by_degree_id) == true)
      { // Show the footnote if this has already been displayed
        // elsewhere on the page.
        $bool_add_footnote = true;
      }

      // Tell the course what group we are coming from. (in this case: none)
      $course->disp_for_group_id = "";

      $pC .= $this->draw_course_row($course,"","",false,false,$bool_add_footnote,true);
      $is_empty = false;

    }



    if (@$GLOBALS["advising_course_has_asterisk"] == true)
    {
      $pC .= "<tr>
        <td colspan='10'>
        <div class=' ' style='margin-top: 10px; padding: 3px;'>
        <b>*</b> Courses marked with an asterisk (*) have
          equivalencies at " . variable_get_for_school("school_initials", "DEMO", $school_id) . ".
          Click on the course for more
          details.
        </div>
        </td>
        </tr>
        ";
    }

    $pC .= $this->draw_semester_box_bottom();

    if (!$is_empty)
    {
      $this->add_to_screen($pC, "TRANSFER_CREDIT");
    }

  } // function build_transfer_credit



  /**
   * Constructs the HTML which will be used to display
   * the student's graduate credits (if any exist)
   *
   */
  function build_graduate_credit()
  {
    $pC = "";
    $is_empty = true;
    $pC .= $this->draw_semester_box_top(variable_get_for_school("graduate_credits_block_title", t("Graduate Credits"), $this->student->school_id), FALSE);
    // Basically, go through all the courses the student has taken,
    // And only show the graduate credits.  Similar to build_transfer_credits

    $graduate_level_codes_array = csv_to_array(variable_get_for_school("graduate_level_codes", "GR", $this->student->school_id));


    $this->student->list_courses_taken->sort_alphabetical_order(false, true);
    $this->student->list_courses_taken->reset_counter();
    while($this->student->list_courses_taken->has_more())
    {
      $course = $this->student->list_courses_taken->get_next();

      // Skip non graduate credits.
      if (!in_array($course->level_code, $graduate_level_codes_array))
      {
        continue;
      }

      // Tell the course_row what group we are coming from. (in this case: none)
      $course->disp_for_group_id = "";

      $pC .= $this->draw_course_row($course,"","",false,false,false,true);
      $is_empty = FALSE;

    }

    $notice = trim(variable_get_for_school("graduate_credits_block_notice", t("These courses may not be used for undergraduate credit."), $this->student->school_id));

    // Do we have a notice to display?
    if ($notice != "")
    {
      $pC .= "<tr><td colspan='8'>
          <div class='hypo  ' style='margin-top: 15px; padding: 5px;'>
            <b>" . t("Important Notice:") . "</b> $notice
          </div>
          </td></tr>";
    }


    $pC .= $this->draw_semester_box_bottom();

    if (!$is_empty)
    {
      $this->add_to_screen($pC, "GRADUATE_CREDIT");
    }

  }





  /**
   * Constructs the HTML to show which courses have been added
   * by an advisor.
   *
   */
  function build_added_courses()
  {

    $pC = "";


    $semester = new Semester(DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED);
    if ($new_semester = $this->degree_plan->list_semesters->find_match($semester))
    {
      $this->add_to_screen($this->display_semester($new_semester), "ADDED_COURSES");
    }
  }



  /**
   * Constructs the HTML to show the Excess Credits list.
   *
   */
  function build_excess_credit()
  {

    $pC = "";
    $pC .= $this->draw_semester_box_top(t("Excess Credits"));
    $is_empty = true;

    // Should we exclude graduate credits from this list?
    $bool_grad_credit_block = (variable_get_for_school("display_graduate_credits_block", "yes", $this->student->school_id) == "yes") ? TRUE : FALSE;
    $graduate_level_codes_array = csv_to_array(variable_get_for_school("graduate_level_codes", "GR", $this->student->school_id));

    // Basically, go through all the courses the student has taken,
    // selecting out the ones that are not fulfilling any
    // requirements.

    $this->student->list_courses_taken->sort_alphabetical_order();
    $this->student->list_courses_taken->reset_counter();
    while($this->student->list_courses_taken->has_more())
    {
      $course = $this->student->list_courses_taken->get_next();

      if ($course->get_has_been_displayed($course->req_by_degree_id) == TRUE)
      { // Skip ones which have been assigned to groups or semesters.
        continue;
      }

      // Skip transfer credits.
      if ($course->bool_transfer == true)
      {
        continue;
      }

      // Skip substitutions
      // Only skip if we have substituted for every degree the student is enrolled in.
      if ($course->get_bool_substitution(-1) == TRUE)
      {
        //fpm($course->get_bool_substitution(-1));
        //fpm($course);
        continue;
      }


      // Exclude graduate credits?
      if ($bool_grad_credit_block && $course->level_code != "" && in_array($course->level_code, $graduate_level_codes_array)) {
        continue;
      }


      // Tell the course_row what group we are coming from. (in this case: none)
      $course->disp_for_group_id = "";

      $pC .= $this->draw_course_row($course,"","",false,false);
      $is_empty = false;
    }


    $pC .= $this->draw_semester_box_bottom();

    if (!$is_empty)
    {
      $this->add_to_screen($pC, "EXCESS_CREDIT");
    }
  }


  /**
   * Constructs the HTML which will show footnotes for substitutions
   * and transfer credits.
   *
   */
  function build_footnotes($bool_include_box_top = TRUE)
  {
    // Display the footnotes & messages.

    $student_id = $this->student->student_id;


    $render = array();
    $render['#id'] = 'AdvisingScreen_build_footnotes';
    $render['#student_id'] = $student_id;
    $render['#degree_plan'] = $this->degree_plan;
    $render['#degree_id'] = $this->degree_plan->degree_id;
    $render['#footnote_array'] = $this->footnote_array;



    $school_id = db_get_school_id_for_student_id($student_id);

    //$pC = "";
    $is_empty = true;
    if ($bool_include_box_top) {
      //$pC .= $this->draw_semester_box_top(t("Footnotes & Messages"), true);

      $render['box_top'] = array(
        'value' => $this->draw_semester_box_top(t("Footnotes & Messages"), true),
        'weight' => 100,
      );

    }

    //$pC .= "<tr><td colspan='8' class=' '>";

    $render['tr_td_start'] = array(
      'value' => "<tr><td colspan='8' class=' '>",
      'weight' => 110,
    );


    $weight = 200;

    $fn_type_array = array("substitution","transfer");
    $fn_char = array("substitution" => "S", "transfer"=>"T");
    $fn_name = array("substitution" => t("Substitutions"),
                    "transfer" => t("Transfer Equivalency Footnotes"));
    $fn_between = array("substitution" => t("for"),
                       "transfer" => t("for") . " " . variable_get_for_school("school_initials", "DEMO", $school_id) . "'s");
    for ($xx = 0; $xx <= 1; $xx++)
    {
      $fn_type = $fn_type_array[$xx];
      if (isset($this->footnote_array[$fn_type]) && @count($this->footnote_array[$fn_type]) < 1)
      {
        continue;
      }

      //$pC .= "<div style='padding-bottom: 10px;'><b>{$fn_name[$fn_type]}</b>";


      $render['section_' . $fn_name[$fn_type] . '_start'] = array(
        'value' => "<div style='padding-bottom: 10px;'><b>{$fn_name[$fn_type]}</b>",
        'weight' => $weight,
      );

      $weight = $weight + 10;

      $is_empty = false;
      $ccount = 0;
      if (isset($this->footnote_array) && is_array($this->footnote_array) && isset($this->footnote_array[$fn_type])) {
        $ccount = count($this->footnote_array[$fn_type]);
      }

      for ($t = 1; $t <= $ccount; $t++)
      {
        $line = @$this->footnote_array[$fn_type][$t];

        if ($line == "")
        {
          continue;
        }

        $extra = ".";

        $temp = explode(" ~~ ", $line);
        $o_course = fp_trim(@$temp[0]);
        $new_course = fp_trim(@$temp[1]);
        $using_hours = fp_trim(@$temp[2]);
        if ($using_hours != "")
        {
          $using_hours = "($using_hours " . t("hrs") . ")";
        }
        $in_group = fp_trim(@$temp[3]);
        $sub_id = fp_trim(@$temp[4]);


        $fbetween = $fn_between[$fn_type];

        $sub_details = $this->db->get_substitution_details($sub_id);

        $remarks = fp_trim(@$sub_details["remarks"]);
        $sub_faculty_id = @$sub_details["faculty_id"];

        $sub_degree_plan = new DegreePlan();
        $sub_degree_plan->degree_id = @$sub_details["required_degree_id"];

        $sub_required_group_id = @$sub_details["required_group_id"];


        //if ($in_group > 0 && $fn_type=="substitution")
        if ($sub_required_group_id != "" && $fn_type=="substitution")
        {
          $new_group = new Group();
          $new_group->group_id = $sub_required_group_id;
          $new_group->load_descriptive_data();

          $extra = "<div style='padding-left: 45px;'><i>" . t("in") . " $new_group->title.</i></div>";
          if ($new_course == $o_course || $o_course == "")
          {
            $o_course = t("was added");
            $fbetween = "";
            $extra = str_replace("<i>" . t("in"), "<i>" . t("to"), $extra);
          }



        }

        // Clean this up, as far as the remarks and such.  Make it look similar (new function?) as popup text for a substitution.
        if ($remarks) $remarks = " ($remarks) ";

        // Build a "theme" array, so we can pass it to other modules in a hook.
        $theme = array();
        $theme["footnote"] = array(
          "fn_char" => $fn_char,
          "fn_type" => $fn_type,
          "fn_num" => $t,
          "css_class" => "",
          "new_course" => $new_course,
          "using_hours" => $using_hours,
          "fbetween" => $fbetween,
          "o_course" => $o_course,
          "extra" => $extra,
          "remarks" => $remarks,
          "for_degree" => $sub_degree_plan->get_title2(FALSE, TRUE),
          "overwrite_with_html" => "",
        );


        // Invoke a hook on our theme array, so other modules have a chance to change it up.
        invoke_hook("theme_advise_footnote", array(&$theme));

        $sup = $theme["footnote"]["fn_char"][$theme["footnote"]["fn_type"]] . $theme["footnote"]["fn_num"];

        // Actually gather the output for the footnote:
        $html = "";

        if ($theme["footnote"]["overwrite_with_html"] != "") {
          $html = $theme["footnote"]["overwrite_with_html"];
        }
        else {

          $html = "<div class='  advise-footnote {$theme["footnote"]["css_class"]}'>
            <sup>$sup</sup>
            <span class='advise-footnote-body'>
               {$theme["footnote"]["new_course"]}
               {$theme["footnote"]["using_hours"]}
               {$theme["footnote"]["fbetween"]}
               {$theme["footnote"]["o_course"]}{$theme["footnote"]["extra"]}{$theme["footnote"]["remarks"]}
               <span class='footnote-for-degree'>(Degree {$theme["footnote"]["for_degree"]})</span>
            </span>
            </div>";
        }


        $render['section_' . $fn_name[$fn_type] . '_item_' . $t] = array(
          'value' => $html,
          'weight' => $weight,
        );

        $weight = $weight + 10;

        //$pC .= $html;


      }
      //$pC .= "</div>";
      $render['section_' . $fn_name[$fn_type] . '_end'] = array(
        'value' => "</div>",
        'weight' => $weight,
      );
    }


    //////////////////////////////
    /// Unassigned transfer eqv's
    $this->student->list_transfer_eqvs_unassigned->load_descriptive_transfer_data();
    $this->student->list_transfer_eqvs_unassigned->sort_alphabetical_order();
    $this->student->list_transfer_eqvs_unassigned->reset_counter();
    $ut_is_empty = TRUE;
    $html = "<!--TRANS_UN_COURSES-->";
    while ($this->student->list_transfer_eqvs_unassigned->has_more()) {

      $c = $this->student->list_transfer_eqvs_unassigned->get_next();

      $l_si = $c->subject_id;
      $l_cn = $c->course_num;
      $l_term = $c->get_term_description(true);

      $html .= "<div class=' ' style='padding-left: 10px; padding-bottom: 5px;
                                       margin-left: 1.5em; text-indent: -1.5em;'>
              $l_si $l_cn (" . $c->get_hours() . " " . t("hrs") . ") from <em>$c->institution_name</em>.
                ";


      $html .= "</div>";

      $ut_is_empty = false;
      $is_empty = false;
    }


    if ($ut_is_empty == false)
    {
      $mtitle = "<div style='padding-bottom: 10px;'>
            <div style='padding-bottom: 5px;'>
            <b>" . t("Transfer Equivalency Removed Courses") . "</b><br>
        " . t("These courses have had their default transfer equivalencies removed.
                ") . "</div>";
      $html = str_replace("<!--TRANS_UN_COURSES-->",$mtitle,$html);
      $html .= "</div>";

      $render['section_trans_un_courses'] = array(
        'value' => $html,
        'weight' => 400,
      );

    }



    ////////////////////////////////////
    ////  Moved Courses...
    $m_is_empty = TRUE;
    $html = "<!--MOVEDCOURSES-->";
    $this->student->list_courses_taken->sort_alphabetical_order();
    $this->student->list_courses_taken->reset_counter();
    while($this->student->list_courses_taken->has_more())
    {
      $c = $this->student->list_courses_taken->get_next();
      // Skip courses which haven't had anything moved.
      if ($c->group_list_unassigned->is_empty == true) {
        continue;
      }


      if ($c->course_id > 0)
      { $c->load_descriptive_data();  }

      $l_s_i = $c->subject_id;
      $l_c_n = $c->course_num;
      $l_term = $c->get_term_description(true);


      $c->group_list_unassigned->reset_counter();
      while($c->group_list_unassigned->has_more()) {

        $html .= "<div class=' ' style='padding-left: 10px; padding-bottom: 5px;
                                       margin-left: 1.5em; text-indent: -1.5em;'>
                      $l_s_i $l_c_n (" . $c->get_hours_awarded() . " " . t("hrs") . ") - $c->grade - $l_term
                    ";

        $group = $c->group_list_unassigned->get_next();
        $group->load_descriptive_data();
        $group_title = "";
        $degree_title = "";
        if ($group->req_by_degree_id != 0) {
          $tdeg = new DegreePlan();
          $tdeg->degree_id = $group->req_by_degree_id;
          $degree_title = " (" . $tdeg->get_title2(FALSE, TRUE) . ")";
        }
        if ($group->group_id > 0)
        {
          $group_title = "<i>$group->title</i>";
        } else {
          $group_title = t("the degree plan");
        }
        $html .= t("was removed from") . " $group_title$degree_title.";

        $html .= "</div>";

      }

      $m_is_empty = false;
      $is_empty = false;
    }

    if ($m_is_empty == false)
    {
      $mtitle = "<div style='padding-bottom: 10px;'>
            <div style='padding-bottom: 5px;' class='moved-courses'>
            <b>" . t("Moved Courses") . "</b><br>
        " . t("These courses have been moved out of their
        original positions on your degree plan.") . "</div>";
      $html = str_replace("<!--MOVEDCOURSES-->",$mtitle,$html);
      $html .= "</div>";

      $render['section_moved_courses'] = array(
        'value' => $html,
        'weight' => 410,
      );
    }



    // For admins only....
    if (user_has_permission("can_substitute") && $bool_include_box_top) {
      if ($this->bool_print != true)
      {// Don't display in print view.
        $purl = fp_url("advise/popup-toolbox/transfers");
        $html = "<div class='admin-toolbox-link-wrapper'>
          <a href='javascript: popupSmallIframeDialog(\"" . $purl . "\",\"" . t("Administrator&#39;s Toolbox") . "\",\"\");'><i class='fa fa-gears'></i> " . t("Administrator's Toolbox") . "</a>
        </div>";
        $is_empty = false;

        $render['section_administrator_toolbox_link'] = array(
          'value' => $html,
          'weight' => 700,
        );

      }
    }


    //$pC .= "</td></tr>";

    $render['tr_td_end'] = array(
      'value' => "</td></tr>",
      'weight' => 800,
    );


    if ($bool_include_box_top) {
      //$pC .= $this->draw_semester_box_bottom();
      $render['box_bottom'] = array(
        'value' => $this->draw_semester_box_bottom(),
        'weight' => 810,
      );

    }

    $output = fp_render_content($render, FALSE);

    if (!$is_empty)
    {
      $this->add_to_screen($output, "FOOTNOTES");
    }

    // Return so other functions can use this output, if needed.
    return $output;
  }


  /**
   * Used in the Toolbox popup, this will display content of the tab which
   * shows a student's substututions
   *
   * @return string
   */
  function display_toolbox_substitutions()
  {
    $pC = "";
    // This will display the substitution management screen.

    $pC .= fp_render_section_title(t("Manage Substitutions"));

    $pC .= "<div class=' manage-substitutions-wrapper'>
        " . t("The following substitutions have been made for this student:") . "
        <br><br>
        ";
    $is_empty = true;


    $this->student->list_substitutions->reset_counter();

    while ($this->student->list_substitutions->has_more())
    {
      $substitution = $this->student->list_substitutions->get_next();

      $db_substitution_id = $substitution->db_substitution_id;

      $course_requirement = $substitution->course_requirement;
      $subbed_course = $substitution->course_list_substitutions->get_first();

      $assigned_to_degree_id = $substitution->assigned_to_degree_id;

      $sub_s_i = $subbed_course->subject_id;
      $sub_c_n = $subbed_course->course_num;

      $cr_s_i = $course_requirement->subject_id;
      $cr_c_n = $course_requirement->course_num;
      $cr_hrs = $course_requirement->get_hours();

      $in_group = ".";
      //if ($subbed_course->assigned_to_group_id > 0)
      //if ($subbed_course->get_first_assigned_to_group_id() != "")
      if ($substitution->db_required_group_id != "")
      {
        $new_group = new Group();
        //$new_group->group_id = $subbed_course->assigned_to_group_id;
        //$new_group->group_id = $subbed_course->get_first_assigned_to_group_id();
        $new_group->group_id = $substitution->db_required_group_id;
        $new_group->load_descriptive_data();

        $in_group = " in $new_group->title.";
      }

      $sub_action = t("was substituted for");
      $sub_trans_notice = "";
      if ($substitution->bool_group_addition == true)
      {
        $sub_action = t("was added to");
        $cr_s_i = $cr_c_n = "";
        $in_group = str_replace("in","",$in_group);
      }

      if ($subbed_course->bool_transfer == true && is_object($subbed_course->course_transfer))
      {
        $sub_s_i = $subbed_course->course_transfer->subject_id;
        $sub_c_n = $subbed_course->course_transfer->course_num;
        $sub_trans_notice = "[" . t("transfer") . "]";
      }

      $extra = $by = $remarks = "";
      $temp = $this->db->get_substitution_details($db_substitution_id);
      $by = $this->db->get_faculty_name($temp["faculty_id"], false);
      $remarks = $temp["remarks"];
      $ondate = format_date($temp["posted"]);

      if ($by != "")
      {
        $by = " <br>&nbsp; &nbsp; " . t("Substitutor:") . " $by.
            <br>&nbsp; &nbsp; <i>$ondate.</i>";
      }

      if ($remarks != "")
      {
        $remarks = " <br>&nbsp; &nbsp; " . t("Remarks:") . " <i>$remarks</i>.";
      }

      // If the sub'd course had ghost hours, make a note of that.
      if ($subbed_course->bool_ghost_hour) {
        $subbed_course->substitution_hours = "0 (1 ghost) ";
      }


      if ($substitution->bool_outdated)
      {
        $extra .= " <span style='color:red'>[OUTDATED: ";
        $extra .= $substitution->outdated_note;
        $extra .= "]</span>";
      }

      $substitution_hours = $subbed_course->get_substitution_hours($assigned_to_degree_id);

      $pC .= "<div class=' toolbox-remove-sub-wrapper' style='margin-bottom: 20px;'>
            $sub_s_i $sub_c_n $sub_trans_notice ($substitution_hours " . t("hrs") . ") $sub_action
            $cr_s_i $cr_c_n$in_group $by$remarks $extra
            <br>
              <a href='javascript: popupRemoveSubstitution(\"$db_substitution_id\");'>" . t("Remove substitution?") . "</a>
          </div>";

      $is_empty = false;
    }

    if ($is_empty == true)
    {
      $pC .= "<div align='center'>" . t("No substitutions have been made for this student.") . "</div>";
    }

    $pC .= "</div>";

    watchdog("toolbox", "substitutions", array(), WATCHDOG_DEBUG);

    return $pC;
  }

  /**
   * Used in the Toolbox popup, this will display content of the tab which
   * shows a student's transfers
   *
   * @return string
   */
  function display_toolbox_transfers()
  {
    $pC = "";
    // This will display the substitution management screen.

    $pC .= fp_render_section_title(t("Manage Transfer Equivalencies"));

    $pC .= "<div class=' '>
        " . t("This student has the following transfer credits and equivalencies.") . "
        <br><br>
        ";
    $is_empty = true;


    $student_id = $this->student->student_id;
    $school_id = db_get_school_id_for_student_id($student_id);


    $retake_grades = csv_to_array(variable_get_for_school("retake_grades", "F,W", $school_id));


    $this->student->list_courses_taken->sort_alphabetical_order(false, true);
    $this->student->list_courses_taken->reset_counter();
    while($this->student->list_courses_taken->has_more())
    {
      $c = $this->student->list_courses_taken->get_next();

      // Skip non transfer credits.
      if ($c->bool_transfer != true)
      {
        continue;
      }

      if ($c->course_id > 0)
      {
        $c->load_descriptive_data();
      }
      $course = $c->course_transfer;

      $course->load_descriptive_transfer_data();

      $l_s_i = $c->subject_id;
      $l_c_n = $c->course_num;
      $l_title = $this->fix_course_title($c->title);

      $t_s_i = $course->subject_id;
      $t_c_n = $course->course_num;
      $t_term = $c->get_term_description(true);
      $grade = $c->grade;
      if (in_array($grade, $retake_grades)) {
        $grade = "<span style='color: red;'>$grade</span>";
      }

      $t_inst = $this->fix_institution_name($course->institution_name);

      $pC .= "<div class=' ' style='padding-bottom: 15px;'>
              <b>$t_s_i $t_c_n</b> (" . $c->get_hours_awarded() . " " . t("hrs") . ") - $grade - $t_term - $t_inst
                ";
      if (isset($c->bool_substitution) && $c->bool_substitution_split == true)
      {
        $pC .= "<div class=' '><b> +/- </b> This course's hours were split in a substitution.</div>";
      }
      $initials = variable_get_for_school("school_initials", "DEMO", $school_id);
      // Does this course NOT have an equivalency?
      if ($c->course_id == 0)
      {
        // But, has the eqv been removed?  If so, display a link to restore it,
        // if not, show a link to remove it!
        if ($rC = $this->student->list_transfer_eqvs_unassigned->find_match($course))
        {
          // Yes, the eqv WAS removed (or unassigned)
          $pC .= "<div class=' '>" . t("This course's @initials equivalency was removed for this student.", array("@initials" => $initials)) . "<br>
              <a href='javascript: popupRestoreTransferEqv(\"$rC->db_unassign_transfer_id\")'>" . t("Restore?") . "</a></div>";
        } else {
          $pC .= "<div class=' '>" . t("@initials equivalency not yet entered (or is not applicable).", array("@initials" => $initials)) . "</div>";
        }
      } else {
        // This course *DOES* have an equivalency.
        $pC .= "<div class=' '>$initials eqv: $l_s_i $l_c_n - $l_title</div>";

        $pC .= "<div>
              <a href='javascript: popupUnassignTransferEqv(\"" . $course->course_id . "\");'>" . t("Remove this equivalency?") . "</a>
              </div>";

      }

      $pC .= "</div>";

      $is_empty = false;
    }

    if ($is_empty == true) {
      $pC .= "<div align='center'>" . t("There are no transfer equivalencies for this student.") . "</div>";
    }

    $pC .= "</div>";

    watchdog("toolbox", "transfers", array(), WATCHDOG_DEBUG);

    return $pC;
  }


  /**
   * Used in the Toolbox popup, this will display content of the tab which
   * shows a student's courses which they have taken.
   *
   * @return string
   */
  function display_toolbox_courses()
  {
    $pC = "";
    $ns = $os = $fan = $fat = "";
    $pC .= fp_render_section_title(t("All Student Courses"));

    $csid = @$_REQUEST["current_student_id"];
    $school_id = db_get_school_id_for_student_id($csid);
    $order = @$_REQUEST["order"];
    if ($order == "name")
    {
      $ns = "font-weight: bold; text-decoration: none;";
      $fan = " <i class='fa fa-angle-up' style='font-weight:bold;'></i>";
    } else {
      $os = "font-weight: bold; text-decoration: none;";
      $fat = " <i class='fa fa-angle-down' style='font-weight:bold;'></i>";
    }

    /*
    $pC .= "<div class=' '>
        " . t("This window displays all of the student's courses
        which FlightPath is able to load.") . "
        <br><br>
        " . t("Order by:") . " &nbsp; &nbsp;";
    $pC .= l(t("Name"), "advise/popup-toolbox/courses", "order=name&current_student_id=$csid", array("style" => $ns)) . "&nbsp; &nbsp;";
    $pC .= l(t("Term"), "advise/popup-toolbox/courses", "order=date&current_student_id=$csid", array("style" => $os));
*/
    $pC .= "
        <table border='0' cellpadding='2' cellspacing='3'>
          <tr>
            <th colspan='2'>" . l(t("Name") . $fan, "advise/popup-toolbox/courses", "order=name&current_student_id=$csid", array("style" => $ns)) . "</th>
            <th>Hr</th>
            <th>Gr</th>
            <th width=50>" . l(t("Term") . $fat, "advise/popup-toolbox/courses", "order=date&current_student_id=$csid", array("style" => $os)) . "</th>
            <th>Attributes</th>
          </tr>
          ";
    $is_empty = true;
    if ($order == "name")
    {
      $this->student->list_courses_taken->sort_alphabetical_order();
    } else {
      $this->student->list_courses_taken->sort_most_recent_first();
    }
    $this->student->list_courses_taken->reset_counter();
    while($this->student->list_courses_taken->has_more())
    {
      $c = $this->student->list_courses_taken->get_next();

      if ($c->course_id > 0)
      {
        $c->load_descriptive_data();
      }

      $l_s_i = $c->subject_id;
      $l_c_n = $c->course_num;
      $eqv_line = "";

      if (isset($c->course_transfer) && $c->course_transfer->course_id > 0)
      {
        if ($c->course_id > 0)
        {
          $eqv_line = "<tr>
              <td colspan='8' class=' '
                style='padding-left: 20px;'>
                <i>*eqv to " . variable_get_for_school("school_initials", "DEMO", $school_id) . " $l_s_i $l_c_n</i></td>
              </tr>";
        }
        $l_s_i = $c->course_transfer->subject_id;
        $l_c_n = $c->course_transfer->course_num;

      }


      $l_title = $this->fix_course_title($c->title);
      $l_term = $c->get_term_description(true);

      $h = $c->get_hours_awarded();
      if ($c->bool_ghost_hour) {
        $h .= "(" . t("ghost") . "<a href='javascript:alertSubGhost()'>?</a>)";
      }

      $pC .= "<tr>
            <td valign='top' class=' '>$l_s_i</td>
            <td valign='top' class=' '>$l_c_n</td>
            <td valign='top' class=' '>$h</td>
            <td valign='top' class=' '>$c->grade</td>
            <td valign='top' class=' '>$c->term_id</td>
            ";
      $pC .= "<td valign='top' class=' '>";

      if ($c->bool_transfer) {$pC .= "T ";}


      //if ($c->bool_substitution) {$pC .= "S ";}
      if ($c->get_bool_substitution()) {$pC .= "S ";}



      if ($c->get_has_been_assigned_to_any_degree())
      {
        $pC .= "A:";
        //////////////////////////////
        // List all the groups/degrees this course has been assigned to!
        if ($c->get_first_assigned_to_group_id() == "")
        {
          $pC .= "degree plan";
        }
        else {
          $temp_group = new Group();
          //$temp_group->group_id = $c->assigned_to_group_id;
          $temp_group->group_id = $c->get_first_assigned_to_group_id();
          $temp_group->load_descriptive_data();
          $pC .= $temp_group->title;
        }


      }
      $pC .= "</td>";



      $pC .= "</tr>$eqv_line";

      $is_empty = false;
    }

    if ($is_empty == true)
    {
      $pC .= "<div align='center'>" . t("No courses have been moved for this student.") . "</div>";
    }

    $pC .= "</table>";

    $pC .= "</div>";

    $pC .= "<hr><p>" . t("Attrib Legend: T = Transfer credit, A = Assigned to (group or degree plan)") . "</p>";


    watchdog("toolbox", "courses", array(), WATCHDOG_DEBUG);

    return $pC;
  }


  /**
   * Used in the Toolbox popup, this will display content of the tab which
   * shows a student's moved courses. That is, courses which have had
   * their group memberships changed.
   *
   * @return string
   */
  function display_toolbox_moved()
  {
    $pC = "";


    $pC .= fp_render_section_title(t("Manage Moved Courses"));

    $pC .= "<div class=' '>
        " . t("This student has the following course movements.") . "
        <br><br>
        ";
    $is_empty = true;

    $this->student->list_courses_taken->sort_alphabetical_order();
    $this->student->list_courses_taken->reset_counter();
    while($this->student->list_courses_taken->has_more())
    {
      $c = $this->student->list_courses_taken->get_next();

      // Skip courses which haven't had anything moved.
      if ($c->group_list_unassigned->is_empty == true)
      {
        continue;
      }


      if ($c->course_id > 0)
      {
        $c->load_descriptive_data();
      }

      $l_s_i = $c->subject_id;
      $l_c_n = $c->course_num;
      $l_title = $this->fix_course_title($c->title);
      $l_term = $c->get_term_description(true);

      $h = $c->get_hours_awarded();
      if ($c->bool_ghost_hour) {
        $h .= " [" . t("ghost") . "<a href='javascript:alertSubGhost();'>?</a>] ";
      }

      $pC .= "<div class=' ' style='padding-bottom: 15px;'>
              <b>$l_s_i $l_c_n</b> ($h " . t("hrs") . ") - $c->grade - $l_term
                ";

      $c->group_list_unassigned->reset_counter();
      while($c->group_list_unassigned->has_more())
      {
        $group = $c->group_list_unassigned->get_next();
        $group->load_descriptive_data();
        $group_title = "";
        if ($group->group_id > 0)
        {
          $group_title = "<i>$group->title</i>";
        } else {
          $group_title = t("the degree plan");
        }

        $degree_title = "";
        if ($group->req_by_degree_id != 0) {
          $tdeg = new DegreePlan();
          $tdeg->degree_id = $group->req_by_degree_id;
          $degree_title = " (" . $tdeg->get_title2(FALSE, TRUE) . ")";
        }


        $pC .= "<div class=' '>" . t("This course was removed from") . " $group_title$degree_title.<br>
              <a href='javascript: popupRestoreUnassignFromGroup(\"$group->db_unassign_group_id\")'>" . t("Restore?") . "</a>
              </div>
              ";
      }



      $pC .= "</div>";

      $is_empty = false;
    }

    if ($is_empty == true)
    {
      $pC .= "<div align='center'>" . t("No courses have been moved for this student.") . "</div>";
    }

    $pC .= "</div>";

    watchdog("toolbox", "moved", array(), WATCHDOG_DEBUG);

    return $pC;
  }


/**
 * Constructs the HTML to show the student's test scores.
 *
 */
  function build_test_scores()
  {
    // This function will build our Test Scores box.
    // Only do this if the student actually has any test scores.

    if ($this->student->list_standardized_tests->is_empty)
    {
      return;
    }

    $top_scores = array();

    $pC = "";
    $pC .= $this->draw_semester_box_top(t("Test Scores"), TRUE);

    $pC .= "<tr><td colspan='8' class=' '>
          ";

    $fsC = "";

    // Go through and find all the test scores for the student...
    $this->student->list_standardized_tests->reset_counter();
    while($this->student->list_standardized_tests->has_more()) {
      $st = $this->student->list_standardized_tests->get_next();

      $extra_date_css = "";

      if (!$st->bool_date_unavailable) {
        $dt = strtotime($st->date_taken);
        $ddate = format_date($dt, "just_date");
      }
      else {
        // The date was not available!
        $ddate = t("N/A");
        $extra_date_css = " test-date-unavailable";
      }

      $fsC .= "<div class='test-section'>
            <b class='test-description'>$st->description</b> - <span class='test-date $extra_date_css'>$ddate</span>
            <ul>";
      foreach($st->categories as $position => $cat_array)
      {
        $fsC .= "<li><span class='test-cat-desc'>{$cat_array["description"]}</span> - <span class='test-cat-score'>{$cat_array["score"]}</span></li>";

      }
      $fsC .= "</ul>
          </div>";

    }

    $pC .= fp_render_c_fieldset($fsC, t("Click to view/hide standardized test scores"), TRUE);

    $pC .= "</td></tr>";


    $pC .= $this->draw_semester_box_bottom();

    $this->add_to_screen($pC, "TEST_SCORES");
  }



/**
 * This function is used by the "build" functions most often.  It very
 * simply adds a block of HTML to an array called box_array.
 *
 * @param string $content_box
 */
  function add_to_screen($content_box, $index = "") {
    if ($index == "") {
      $this->box_array[] = $content_box;
    }
    else {
      $this->box_array[$index] = $content_box;
    }

  }


  /**
   * This function calls the other "build" functions to assemble
   * the View or What If tabs in FlightPath.
   *
   */
  function build_screen_elements()
  {
    // This function will build & assemble all of the onscreen
    // elements for the advising screen.  It should be
    // called before display_screen();

    $this->build_semester_list();

    $this->build_excess_credit();
    $this->build_test_scores();

    $this->build_transfer_credit();

    // Should we add the graduate credit block?

    if (variable_get("display_graduate_credits_block", "yes") == "yes") {
      $this->build_graduate_credit();
    }

    if (!$this->bool_blank)
    { // Don't show if this is a blank degree plan.
      $this->build_footnotes();
      $this->build_added_courses();
    }

    // invoke a hook, to give custom modules the chance to perform actions
    // (or add blocks) to the advise screen after we have run this function.
    invoke_hook("advise_build_screen_elements", array(&$this));

  }




  /**
   * This function is used to draw an individual pie chart box.
   * It accepts values of top/bottom in order to come up
   * with a percentage.
   *
   * @param string $title
   *
   * @param float $top_value
   *         - The top part of a ratio.  Ex: for 1/2, $top_value = 1.
   *
   * @param float $bottom_value
   *         - The bottom part of a ratio.  For 1/2, $bottom_value = 2.
   *         - Do not let this equal zero.  If it does, the calculation
   *           for the pie chart will never be evaluated.
   * @param string $pal
   *         - Which palette to use for the pie chart.  If set, fore_col will be ignored in the argument list.
   *         - Acceptable values:
   *           - core
   *           - major
   *           - cumulative
   *           - student
   * @param string $back_col
   *         - If $pal is left blank, the value here will be used for the "back" or "unfinished" color.
   * @param string $fore_col
   *         - If $pal is left blank, the value here will be used for the "foreground" or "progress" color.
   *
   *
   * @return string
   */
  function draw_pie_chart_box($title, $top_value, $bottom_value, $pal = "", $back_col = "", $fore_col = "", $extra = "")
  {
    $rtn = "";

    $val = 0;

    if ($bottom_value > 0) {
      $val = round(($top_value / $bottom_value)*100);
    }
    if ($val > 100) { $val = 100; }

    $leftval = 100 - $val;

    if ($back_col == "") $back_col = "660000";
    if ($fore_col == "") $fore_col = "FFCC33";

    if ($pal == "major") {
      $fore_col = "93D18B";
    }

    if ($pal == "cumulative") {
      $fore_col = "5B63A5";
    }

    // Remove # from colors, if needed.
    $fore_col = str_replace("#", "", $fore_col);
    $back_col = str_replace("#", "", $back_col);


    $vval = $val;
    if ($vval < 1) $vval = 1;

    // Create a graph using our built-in pchart api:
    // First, establish a token to we know the script is being called from US:
    if (!isset($_SESSION["fp_pie_chart_token"])) {
      $_SESSION["fp_pie_chart_token"] = md5(fp_token());
    }
    //old Google API url: $pie_chart_url = "https://chart.googleapis.com/chart?cht=p&chd=t:$vval,$leftval&chs=75x75&chco=$fore_col|$back_col&chp=91.1";
    $pie_chart_url = base_url() . "/libraries/pchart/fp_pie_chart.php?progress=$vval&unfinished=$leftval&unfinished_col=$back_col&progress_col=$fore_col&token=" . $_SESSION["fp_pie_chart_token"];

    $rtn .= "<table border='0' width='100%'  height='100' class='pie-chart-individual-table pie-chart-individual-table-" . fp_get_machine_readable(strtolower($title)) . "' cellpadding='0' cellspacing='0' >
            <tr class='pie-chart-title-tr'>
                <td class='pie-chart-box-title-td' align='center' height='20'>
            " . fp_render_section_title($title, 'pie-chart-box-section') . "
                </td>
            </tr>
            <tr class='pie-chart-inner-table-tr'>
              <td class='pie-chart-inner-table-td'>
                <table border='0' class='pie-chart-chart-table'>
                <td class='pie-visualization'>
                  <img src='$pie_chart_url'>
                </td>
                <td class='pie-values'>
                    <div class='pie-val-percent'>
                      <span class='pie-val-percent-complete'>$val% <span class='pie-v-cap'>" . t("Complete") . "</span></span>
                    </div>
                    <div class='pie-val-top-bottom'>
                        ( <span class='pie-val-top-val'>$top_value</span>
                      / <span class='pie-val-bottom-val'>$bottom_value <span class='pie-v-cap'>" . t("hours") . "</span></span> )
                    </div>
                   $extra";

    $rtn .= "
                </td>
                </table>
              </td>
            </tr>
          </table>
        ";

    return $rtn;
  }




  /**
   * This function calls drawPieChart to construct the student's 3
   * progress pie charts.
   *
   * @return string
   */
  function draw_progress_boxes()
  {
    global $user;
    // Draw the boxes for student progress (where
    // the pie charts go!)
    $rtn = "";

    if (!$this->db) {
      $this->db = get_global_database_handler();
    }


    $bool_charts_are_hidden = FALSE;



    // have we already calculated this degree's data?
    if (@$this->degree_plan->bool_calculated_progess_hours != TRUE)
    {

      // Only bother to get the types calculations needed for the piecharts
      // Get the requested piecharts from our config...
      $types = array();
      $temp = variable_get_for_school("pie_chart_config", "c ~ Core Requirements\nm ~ Major Requirements\ndegree ~ Degree Progress", $this->student->school_id);
      $lines = explode("\n", $temp);
      foreach ($lines as $line) {
        if (trim($line) == "") continue;
        $temp = explode("~", $line);
        $requirement_type = trim($temp[0]);
        $types[$requirement_type] = trim($temp[1]);
      }


      $this->degree_plan->calculate_progress_hours(FALSE, $types);

      $this->degree_plan->calculate_progress_quality_points(FALSE, $types);

    }

    // Create a "theme" array for later use.
    $pie_chart_theme_array = array();
    $pie_chart_theme_array["screen"] = $this;
    $pie_chart_theme_array["student"] = $this->student;
    $pie_chart_theme_array["degree_plan"] = $this->degree_plan;


    $pie_chart_html_array = array();  // init for later

    // Get the requested piecharts from our config...
    $temp = variable_get_for_school("pie_chart_config", "c ~ Core Requirements\nm ~ Major Requirements\ndegree ~ Degree Progress", $this->student->school_id);
    $config_lines = explode("\n", $temp);

    // Go through each of the degrees we have piecharts for
    foreach ($this->degree_plan->gpa_calculations as $degree_id => $val) {


      $dp = new DegreePlan();
      $dp->degree_id = $degree_id;
      if ($degree_id > 0) {
        $dp->load_descriptive_data();
        $d_title = $dp->get_title2(FALSE, TRUE);
        $d_code = fp_get_machine_readable($dp->major_code);
      }
      else {
        // Degree_id == 0, so this is the "overall" degree.
        $d_title = t("Overall Progress");
        $d_code = "PIE_OVERALL_PROGRESS";
      }

      // Add to our theme array.
      $pie_chart_theme_array["degree_rows"][$degree_id] = array(
        "degree_id" => $degree_id,
        "row_label" => $d_title,
        "row_classes" => "",
        "degree_plan" => $dp,
        "degree_major_code_machine" => $d_code,
        "bool_display" => TRUE,
      );


      foreach ($config_lines as $line) {
        if (trim($line) == "") continue;

        $temp = explode("~", $line);
        $requirement_type = trim($temp[0]);
        $label = trim($temp[1]);
        $unfinished_col = @trim($temp[2]);
        $progress_col = @trim($temp[3]);

        if ($unfinished_col == "") $unfinished_col = "660000";
        if ($progress_col == "") $progress_col = "FFCC33";


        // Okay, let's see if this degreeplan even has any data on this requirement type.
        $total_hours = $this->degree_plan->gpa_calculations[$degree_id][$requirement_type]["total_hours"]*1;
        $fulfilled_hours = $this->degree_plan->gpa_calculations[$degree_id][$requirement_type]["fulfilled_hours"]*1;
        $qpts = $this->degree_plan->gpa_calculations[$degree_id][$requirement_type]["qpts"]*1;

        if (floatval($total_hours) == 0) continue;  // no hours for this requirement type!

        // Setting to display GPA
        $gpa = $extra_gpa = "";
        if (variable_get_for_school("pie_chart_gpa", "no", $this->student->school_id) == "yes") {
          if ($this->degree_plan->gpa_calculations[$degree_id][$requirement_type]["qpts_hours"] > 0) {
            $gpa = fp_truncate_decimals($qpts / $this->degree_plan->gpa_calculations[$degree_id][$requirement_type]["qpts_hours"], 3);
          }
          if ($gpa) {
            $extra_gpa = "<div class='view-extra-gpa  ' style='text-align: right; color: gray;'>GPA: $gpa</div>";
          }
        }

        // If we are here, then there is indeed enough data to create a piechart!
        // Generate the pie chart and add to our array, for later display.
        $html = $this->draw_pie_chart_box($label,$fulfilled_hours, $total_hours, "", $unfinished_col, $progress_col, $extra_gpa);
        $hide_pie_html = "$label: $fulfilled_hours / $total_hours";

        // Will only display if we've set it above.
        if ($gpa) {
          $hide_pie_html .= " ($gpa)";
        }

        $pie_chart_html_array[] = array(
          "pie" => $html,
          "hide_pie" => $hide_pie_html,
         );

        // Add to our theme array
        $pie_chart_theme_array["degree_rows"][$degree_id]["data"][$requirement_type] = array(
          "full_html" => $html,
          "hide_pie_html" => $hide_pie_html,
          "requirement_type" => $requirement_type,
          "label" => $label,
          "unfinished_col" => $unfinished_col,
          "progress_col" => $progress_col,
          "total_hours" => $total_hours,
          "fulfilled_hours" => $fulfilled_hours,
          "qpts" => $qpts,
          "bool_display" => TRUE,
          "pie_classes" => '',
        );



      } // foreach $line  (for piechart by type)

    } //foreach $degree_id


    //////////////////
    // Send the pie_chart_theme_array to a hook for possible extra processing.
    invoke_hook("theme_pie_charts", array(&$pie_chart_theme_array));
    //////////////////

    $prcount = 0;
    $total_rows = count($pie_chart_theme_array["degree_rows"]);
    $degree_classes = fp_get_degree_classifications();
    // Now, cycle through all of the 'rows' of degrees we need to draw.
    foreach ($pie_chart_theme_array["degree_rows"] as $degree_id => $details) {

      if ($details["bool_display"] === FALSE) continue;   // hide the entire row

      // We also want to denote if this is part of a "combined" degree or not.
      $extra_pie_trtd_class = "";
      if ($this->degree_plan->is_combined_dynamic_degree_plan) {
        $extra_pie_trtd_class .= "pie-combined-dynamic-degree-plan";
      }
      else {
        $extra_pie_trtd_class .= "pie-single-degree-plan";
      }

      $degree_class = @$details['degree_plan']->degree_class;
      if ($degree_class == "") $degree_class = "NOT_SET";
      $degree_level_num = intval(@$degree_classes['machine_name_to_level_num'][$degree_class]);

      $rtn .= "<tr class='pie-degree-row pie-degree-row-$degree_id pie-degree-row-class-$degree_class pie-total-rows-$total_rows pie-degree-row-degree-level-num-$degree_level_num pie-row-count-$prcount {$details['row_classes']}'><td colspan='2' class='$extra_pie_trtd_class'>
                <div class='pie-row-label'>{$details["row_label"]}</div>";


      $td_width = "";
      if (is_array($pie_chart_html_array) && count($pie_chart_html_array) > 0) {
        $td_width = round(100 / count($pie_chart_html_array));
      }

      if (!isset($user->settings["hide_charts"])) $user->settings["hide_charts"] = "";

      if ($this->bool_force_pie_charts || ($user->settings["hide_charts"] != "hide" && $this->bool_blank == FALSE ))
      { // Display the pie charts

        $bool_charts_are_hidden = FALSE;

        $rtn .= "
                  <div style='margin-bottom: 10px;' class='pies-wrapper'>
                    <table class='pie-chart-table' width='100%' cellspacing='0' cellpadding='0' border='0'>
                      <tr>
                  ";

        $c = 0;
        if (@isset($pie_chart_theme_array['degree_rows'][$degree_id]["data"])) {
          foreach ($pie_chart_theme_array['degree_rows'][$degree_id]["data"] as $requirement_type => $val) {
            $html = $val["full_html"];
            if (@$val["bool_display"] === FALSE) continue; // this particular chart shouldn't be shown.

            $style = @($c == count($pie_chart_html_array) - 1) ? "" : "padding-right:5px;";
            $rtn .= "<td width='$td_width%' style='$style' class='td_full_pie td_full_pie_$requirement_type " . @$val["pie_classes"] . "'>
                       " . $html . "
                     </td>";
            $c++;
          }
        }


        $rtn .= "  </table>";

        $rtn .= "</div>"; // class pies-wrapper

      }
      else {
        // Hide the charts!
        $bool_charts_are_hidden = TRUE;

        $rtn .= "
        <table border='0' width='100%'  class='pie-chart-table-hide-charts' cellpadding='0' cellspacing='0' >
        <tr class='pie-hidden-charts-label-row'>
            <td colspan='10' class='' align='center' height='20'>
            " . fp_render_section_title(t("Progress"), 'hidden-pie-charts-section') . "
            </td>
        </tr>
        <tr class='pie-hidden-charts-row'>";

        $c = 0;
        if (isset($pie_chart_theme_array['degree_rows'][$degree_id]["data"])) {
          foreach ($pie_chart_theme_array['degree_rows'][$degree_id]["data"] as $requirement_type => $val) {
            $html = $val["hide_pie_html"];
            if ($val["bool_display"] === FALSE) continue; // this particular chart shouldn't be shown.

            $rtn .= "<td width='$td_width%' align='center' class='td_hidden_pie td_hidden_pie_$requirement_type {$val["pie_classes"]} '>
                       " . $html . "
                     </td>";
            $c++;
          }
        }

        $rtn .= "
          </tr>

         </table>";





      }



      $rtn .= "</td></tr>";
      $prcount++;

    } // foreach degree_rows



    return $rtn;
  }







  /**
   * Will display the "public note" at the top of a degree.  This
   * was entred in Data Entry.
   *
   * @return string
   */
  function draw_public_note()
  {
    // This will display a "public note" to the user about
    // this degree.  The public note was entered in Data Entry.

    if (count($this->degree_plan->public_notes_array) == 0)
    {
      return "";
    }

    $pC = "";

    foreach ($this->degree_plan->public_notes_array as $degree_id => $note) {

      if (trim($note) != "") {

        $pC .= "<tr><td colspan='8'>
            <div class=' '
              style='border: 5px double #C1A599;
                  padding: 5px;
                  margin: 10px;'>
            <b>" . t("Important Message:") . "</b> $note
            </div>
            </td></tr>";
      }

    }






    return $pC;


  }



  /**
   * This function generates the HTML to display the screen.  Should
   * be used in conjunction with output_to_browser()
   *
   * @return string
   */
  function display_screen()
  {
    // This will generate the html to display the screen.
    $pC = "";

    $school_id = db_get_school_id_for_student_id($this->student->student_id);


    if (!$this->db) {
      $this->db = get_global_database_handler();
    }

    if ($this->bool_hiding_grades && !$this->bool_print && variable_get_for_school("hiding_grades_message", '', $school_id) != "")
    {
      // Display the message about us hiding grades.
      $pC .= "
          <tr><td colspan='2'>
                <div class='  hypo' style='margin-top: 4px; margin-bottom: 4px;
                 padding: 2px; border: 1px solid maroon;'>
                <table border='0' cellspacing='0' cellpadding='0'>
                <td valign='top'>
                  <img src='" . fp_theme_location() . "/images/alert_lg.gif' >
                </td>
                <td valign='middle' class=' ' style='padding-left: 8px;'>
                  " . variable_get_for_school("hiding_grades_message", "", $school_id) . "
                </td>
                </table>
                </div>
          </td></tr>
      ";
    }


    //$pC .= $this->draw_currently_advising_box();
    $pC .= $this->draw_progress_boxes();

    $pC .= $this->draw_public_note();
    $t = 0;
    foreach ($this->box_array as $index => $box_array_contents) {

      $align = "right";
      if ($this->is_on_left)
      {
        $pC .= "<tr>";
        $align= "left";
      }
      $css_index = fp_get_machine_readable($index);
      $pC .= "<td valign='top' align='$align' class='fp-boxes fp-boxes-$css_index'>";
      $pC .= $box_array_contents;
      $pC .= "</td>";


      if (!$this->is_on_left) // on right of page
      {
        $pC .= "</tr>";
      }
      $this->is_on_left = !$this->is_on_left;
    }

    if (!$this->is_on_left) // on right of the page.
    { // close up any loose ends.
      $pC .= "</tr>";
    }

    if (user_has_permission("can_advise_students"))
    {
      if (!$this->bool_print && !$this->bool_blank)
      {

        $pC .= "<tr>";

        $pC .= "<td class='fp-boxes fp-boxes-blank'>&nbsp;</td>";


        $render = array();
        //$render['html'] = fp_render_button(t("Submit"),"submitSaveActive();");
        $render['submit_button_html'] = array(
          'value' => "<div class='buttons form-element element-type-submit'>
                            <input type='button' id='mainform_submit_btn' value='" . t("Submit") . "' onClick='submitSaveActive();'>
                           </div>",
          'weight' => 100,
        );

        invoke_hook("content_alter", array(&$render, 'advise_submit_button'));


        $pC .= "<td class='fp-boxes fp-boxes-submit-button' align='center'>
            <div class='  advise_submit_button_wrapper' style='margin-top:35px; margin-bottom:10px; padding: 10px;'>
            " . fp_render_content($render) . "
            </div>
            </td></tr>
            ";


        //$this->add_to_screen("<input type='button' value='Submit' onClick='submitSaveActive();'>");
      }
    }

    return $pC;

  }

  /**
   * Returns the HTML to draw a pretty button.
   *
   * @param string $title
   * @param string $on_click
   * @param bool $bool_padd
   * @param string $style
   * @return string
   */
  function draw_button($title, $on_click, $bool_padd = true, $style = "")
  {
    // Style is expected to look like:
    // style='some:thing;'
    // with SINGLE apostrophes!  not quotes.

    $on_mouse = "onmouseover='this.className=\"gradbutton gradbutton_hover hand\";'
          onmouseout='this.className=\"gradbutton hand\";'
          onmousedown='this.className=\"gradbutton gradbutton_down hand\";'
          onmouseup='this.className=\"gradbutton gradbutton_hover hand\";'
          ";


    $padd = '';
    if ($bool_padd) {
      $padd = "&nbsp; &nbsp;";
    }


    $rtn = "<span class='gradbutton hand' onClick='$on_click' $on_mouse $style >
        $padd $title $padd
        </span>

      ";
    return $rtn;
  }



  /**
   * Constructs the HTML to display the list of semesters for the student.
   *
   */
  function build_semester_list() {


    $list_semesters = $this->degree_plan->list_semesters;
    // Go through each semester and add it to the screen...
    $list_semesters->reset_counter();

    while($list_semesters->has_more())
    {
      $semester = $list_semesters->get_next();
      $semester->reset_list_counters();
      if ($semester->semester_num == DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED)
      { // These are the "added by advisor" courses.  Skip them.
        continue;
      }

      $semester->req_by_degree_id = $this->degree_plan->degree_id;

      $disp_sem = $this->display_semester($semester, TRUE);
      if ($disp_sem) {
        $this->add_to_screen($disp_sem, "SEMESTER_" . $semester->semester_num);
      }

    }


  }




  /**
   * Displays the contents of the Descripton tab for the course popup.
   *
   * @param int $course_id
   *        - The course_id of the course to show.  Leave blank if supplying
   *          the object instead.
   *
   * @param Course $course
   *        - The course object to display.  Leave as NULL if supplying
   *          the course_id instead.
   *
   * @param Group $group
   *        - The Group object that this course has been placed into.
   *
   * @param bool $show_advising_buttons
   *        - Should we show the advising buttons in this popup?  Would be
   *          set to false for student view, or for anyone who is not
   *          allowed to advise this course into a group for the student.
   *
   * @return string
   */
  function display_popup_course_description($course_id = 0, Course $course = NULL, $group = NULL, $show_advising_buttons = FALSE)
  {
    $pC = "";

    $db = $this->db;

    if ($course_id && $course_id != 0) {
      $course = new Course($course_id);
    }

    // Set up our "render array" for later rendering, using the render API.
    $render = array();
    $render["#id"] = "AdvisingScreen_display_popup_course_description";

    $render["#course"] = array(
      'type' => 'do_not_render',
      'value' => $course,
    );




    // Note: $render['#group'] is set lower in this function.



    $db_group_requirement_id = @$_REQUEST["db_group_requirement_id"];



    if ($course == null)
    {
      // No course available!

      $render["no_course_selected"] = array(
        "type" => "markup",
        "value" => t("No course was selected.  Please
          click the Select tab at the top of the screen."),
        "attributes" => array("style" => "margin-top: 13px;", "class" => " "),
      );

      $pC .= fp_render_content($render);
      return $pC;
    }

    $school_id = $course->school_id;

    // Not sure I need this line anymore.
    $datastring_max_hours = $course->max_hours;


    $datastring_bool_new_from_split = $course->get_bool_substitution_new_from_split();
    $req_by_degree_id = $course->req_by_degree_id;

    $advising_term_id = @$GLOBALS["fp_advising"]["advising_term_id"];

    $course->load_descriptive_data(TRUE, TRUE, TRUE, FALSE, FALSE, FALSE);

    $course_hours = $course->get_catalog_hours();
    if ($course->bool_transfer)
    {
      // Nothing at the moment.
    }

    // Does this course have more than one valid (non-excluded) name?
    $other_valid_names = "";
    if (count($course->array_valid_names) > 1)
    {
      for ($t = 0; $t < count($course->array_valid_names); $t++)
      {
        $name = $course->array_valid_names[$t];
        if ($name == "$course->subject_id~$course->course_num")
        {
          continue;
        }
        $other_valid_names .= ", " . str_replace("~"," ",$name);
      }
    }

    $course->fix_title();

    $initials = variable_get_for_school("school_initials", "DEMO", $school_id);

    $pC .= "<!--EQV1-->";
    $bool_transferEqv = true;
    if ($course->bool_transfer)
    {
      // This is a transfer course.  Begin by displaying the transfer credit's
      // information.

      $course->course_transfer->load_descriptive_transfer_data($this->student->student_id, $course->term_id);
      $hrs = $course->course_transfer->get_hours()*1;
      if ($hrs == 0)
      {
        $hrs = $course->get_hours();
      }

      // make transfer course titles all caps.
      $course->course_transfer->title = strtoupper($course->course_transfer->title);

      $html = "";
      $html .= "<div style='margin-top: 13px;' class=' '>
        <b>" . t("Transfer Credit Information:") . "</b><br>
        <div style='margin-left: 20px;' class=' '>
          " . t("Course:") . " " . $course->course_transfer->subject_id . " " . $course->course_transfer->course_num . "
          - " . $course->course_transfer->title . " ($hrs " . t("hrs") . ")<br>
          " . t("Institution:") . " " . $this->fix_institution_name($course->course_transfer->institution_name) . "<br>
          " . t("Term:") . " " . $course->get_term_description() . "<br>
          <!-- Grade: " . $course->grade . "<br> -->
          ";

      $transfer_eqv_text = $course->course_transfer->transfer_eqv_text;
      if ($transfer_eqv_text == "") {
        $transfer_eqv_text = t("Not entered or not applicable.");
        $bool_transferEqv = false;
      }

      $html .= "$initials Eqv: $transfer_eqv_text<br>
        </div>
          </div>";

      $render["transfer_credit_info"] = array(
        "type" => "markup",
        "value" => $html,
      );

    } // if course->bool_transfer





    if ($course->course_id != 0)
    {
      $html = "";
      $use_hours = $course_hours;

      if ($course->bool_transfer)
      {
        $html .= "<b>$initials " . t("Equivalent Course Information:") . "</b><br>
            <b>$course->subject_id $course->course_num</b> - ";
        $new_course = new Course();
        $new_course->course_id = $course->course_id;
        $new_course->load_descriptive_data();
        $use_hours = $new_course->get_catalog_hours();
      }

      // if this is a substitution, use the number of hours for the ORIGNAL course.
      if ($course->get_bool_substitution() == TRUE) {
        $sub_id = $course->db_substitution_id_array[$course->get_course_substitution()->req_by_degree_id];

        $temp = $db->get_substitution_details($sub_id);
        $sub_hours = @floatval($temp['sub_hours']) * 1;  // trim excess zeros with *1.
        if ($sub_hours < $use_hours) {
          $use_hours = $sub_hours;
        }
      }

      $html .= "
          <b>$course->subject_id $course->course_num - $course->title ($use_hours " . t("hrs") . ")</b>";


      $render["course_title_line"] = array(
        "type" => "markup",
        "value" => $html,
        "attributes" => array("style" => "margin-top: 13px; margin-bottom: 0;", "class" => " "),
        "weight" => 10,
      );


      // If the course can be repeated for credit, show that information next.
      if ($course->repeat_hours > $course->min_hours)
      {
        $html = t("May be repeated for up to @repeat hours of credit.", array("@repeat" => floatval($course->repeat_hours)));  // floatval trims excess zeroes from display.

        // if it is essentially infinite, then we just say it can be repeated for credit, period.
        if ($course->repeat_hours > 20) {
          $html = t("May be repeated for credit.");
        }

        $render["course_repeat_line"] = array(
          "type" => "markup",
          "value" => $html,
          "attributes" => array("class" => "  course-search-repeat"),
          "weight" => 15,
        );

      }




    } // if course->course_id != 0



    if ($course->get_bool_substitution_new_from_split($req_by_degree_id) || $course->get_bool_substitution_split($req_by_degree_id))
    {
      $html = "";
      $html .= "<div class=' ' style='margin-bottom:5px;'>
                    <i>" . t("This course's hours were split in a substitution.");

      if ($course->get_bool_substitution_new_from_split()) {

        $sub_remaining_hours = @$course->get_hours_awarded($req_by_degree_id);

        $html .= "<br>" . t("Remaining hours after split:") . "  $sub_remaining_hours " . t("hrs.") . "";
      }


      $html .= "</i>
                    <a href='javascript: alertSplitSub();'>?</a>
               </div>";

      $render["substitution_split"] = array(
         "type" => "markup",
         "value" => $html,
         "weight" => 20,
      );

    }


    if ($course->course_id != 0)
    {


      $render["course_description"] = array(
        "type" => "markup",
        "value" => $course->description,
        "attributes" => array("class" => " "),
        "weight" => 30,
      );

    }

    // The -1 for get_bool_substitution means, is it being used in ANY substitution?
    if ($course->bool_transfer == true && $course->course_id < 1 && $course->get_bool_substitution(-1) == FALSE)
    { // No local eqv!

      $html = "";
      $html .= "<div class=' ' style='margin-top: 10px;'><b>Note:</b> ";

      $pC = str_replace("<!--EQV1-->"," (" . t("Transfer Credit") . ")",$pC);  // place the words "transfer credit" in the curved title line at the top.

      if (!$bool_transferEqv)
      {
        $t_msg = t("This course does not have an assigned @initials equivalency, or the equivalency
              has been removed for this student.
            Ask your advisor if this course will count towards your degree.", array("@initials" => $initials)) . "
          </div>";
      }
      else {

        $t_msg = t("FlightPath cannot assign this course to a @initials equivalency on
              the student's degree plan,
              or the equivalency
              has been removed for this student.
            Ask your advisor if this course will count towards your degree.", array("@initials" => $initials)) . "
          </div>";
      }

      $html .= $t_msg;

      $render["course_transfer_no_eqv"] = array(
        "type" => "markup",
        "value" => $html,
        "weight" => 40,
      );


    }
    elseif ($course->bool_transfer == true && $course->course_id > 0 && $course->get_bool_substitution(-1) == FALSE)
    { // Has a local eqv!
      $html = "";

      $t_s_i = $course->course_transfer->subject_id;
      $t_c_n = $course->course_transfer->course_num;
      /*      $pC .= "<div class=' ' style='margin-top: 10px;'>
      <b>Note:</b> The course listed above is equivalent
      to <b>$t_s_i $t_c_n</b>,
      which the student completed at <i>";

      // Replace the temporary comment <!--EQV1--> in the header with
      // the new eqv information.
      */
      $pC = str_replace("<!--EQV1-->"," (" . t("Transfer Credit") . " $t_s_i $t_c_n)",$pC);

      // Admin function only.
      if (user_has_permission("can_substitute"))
      {
        $html .= "<div align='left' class=' '>
          <b>" . t("Special administrative function:") . "</b>
            <a href='javascript: popupUnassignTransferEqv(\"" . $course->course_transfer->course_id . "\");'>" . t("Remove this equivalency?") . "</a></div>";

        //$html .= "</div>";  // not sure what this went to.  Commenting out.
      }


      //$pC .= "</div>";   // not sure what this went to... commenting out.

      $render["course_transfer_local_eqv"] = array(
        "type" => "markup",
        "value" => $html,
        "weight" => 50,
      );


    }


    ////////////////////////////
    //  When was this student enrolled in this course?

    $html = "";
    if ($course->term_id != "" && $course->term_id != Course::COURSE_UNKNOWN_TERM_ID && $course->display_status != "eligible" && $course->display_status != "disabled")
    {
      $html .= "<div class=' ' style='margin-top: 10px;'>
            " . t("The student enrolled in this course in") . " " . $course->get_term_description() . ".
          </div>";

    } else if ($course->term_id == Course::COURSE_UNKNOWN_TERM_ID)
    {
      $html .= "<div class=' ' style='margin-top: 10px;'>
            " . t("The exact date that the student enrolled in this course
            cannot be retrieved at this time.  Please check the
            student's official transcript for more details.") . "
          </div>";

    }

    $render["when_enrolled"] = array(
      "type" => "markup",
      "value" => $html,
      "weight" => 50,
    );

    ///////////////////////////////////

    // Did the student earn a grade?
    $html = "";
    if ($course->grade != "") {

      $grd = $course->grade;

      $enrolled_grades = csv_to_array(variable_get_for_school("enrolled_grades",'E', $school_id));
      if (in_array($grd, $enrolled_grades)) {

        $html .= t("The student is currently enrolled in this course.", array("@grade" => $grd));
        $render['enrolled_notice'] = array(
          'type' => 'markup',
          'value' => $html,
          'weight' => 54,
        );


      }
      else {

        if (trim($grd) != trim($course->db_grade)) {
          $grd = "$course->db_grade ($grd)";
        }
        $disp_grade = $grd;
        if (strstr($grd, "MID")) {
          $disp_grade = str_replace("MID", "<span class='superscript'>mid</span>", $grd);
        }

        $html .= t("The student earned a grade of <strong>@grade</strong>.", array("@grade" => $disp_grade));
        $render['earned_grade'] = array(
          'type' => 'markup',
          'value' => $html,
          'weight' => 55,
        );
      }
    }




    ////////////////////////////////
    // Conditions on which this will even appear?  Like only if the student has more than one degree selected?
    // What degrees is this course fulfilling?
    if (count($course->assigned_to_degree_ids_array) > 0) {
      $html = "";

      $html .= "<div class='  course-description-assigned-to-degrees'>
                " . t("This course is fulfilling a requirement for: ");
      $c = "";
      $d = "";
      foreach ($course->assigned_to_degree_ids_array as $degree_id) {
        $d .= $degree_id . ",";
        $t_degree_plan = new DegreePlan();
        $t_degree_plan->degree_id = $degree_id;
        $c .= "<span>" . $t_degree_plan->get_title2(FALSE, TRUE) . "</span>, ";
      }
      $c = rtrim($c, ", ");
      $html .= "$c</div>";

      $render["fulfilling_reqs_for_degrees"] = array(
        "type" => "markup",
        "value" => $html,
        "weight" => 60,
      );
      // Also keep track of what degree ids we are fulfilling reqs for, in case we need it later.
      $render["#fulfilling_reqs_for_degree_ids"] = array(
        "type" => "do_not_render",
        "value" => $d,
      );


    }

    ////////////////
    // Is this course assigned to a group?
    if ($course->disp_for_group_id != "" && $course->grade != "" && $course->bool_transfer != true && $course->get_bool_substitution($course->req_by_degree_id) != TRUE)
    {

      $html = "";

      $g = new Group();

      $g->group_id = $course->disp_for_group_id;
      $g->load_descriptive_data();

      $html .= "<div class=' ' style='margin-top: 10px;'>
            <img src='" . fp_theme_location() . "/images/icons/$g->icon_filename' class='advising-course-row-icon advising-course-row-icon-in-group'>
            &nbsp;
            " . t("This course is a member of") . " $g->title.
          ";
      // If user is an admin...
      if (user_has_permission("can_substitute")) {
        $tflag = intval($course->bool_transfer);
        $html .= "<div align='left' class=' '>
          <b>" . t("Special administrative function:") . "</b>
            <a href='javascript: popupUnassignFromGroup(\"$course->course_id\",\"$course->term_id\",\"$tflag\",\"$g->group_id\",\"$req_by_degree_id\");'>" . t("Remove from this group?") . "</a></div>";
        $html .= "</div>";
      }

      $render["course_assigned_to_group"] = array(
        "type" => "markup",
        "value" => $html,
        "weight" => 70,
      );

      $render["#group"] = $g;


    }
    else if ($course->grade != "" && $course->bool_transfer != true && $course->get_bool_substitution($course->req_by_degree_id) != TRUE && $course->get_has_been_assigned_to_degree_id()) {
      // Course is not assigned to a group; it's on the bare degree plan.  group_id = 0.
      // If user is an admin...

      $html = "";
      if (user_has_permission("can_substitute"))
      {
        $tflag = intval($course->bool_transfer);
        $html .= "<div align='left' class=' '>
          <b>" . t("Special administrative function:") . "</b>
            <a href='javascript: popupUnassignFromGroup(\"$course->course_id\",\"$course->term_id\",\"$tflag\",\"0\",\"$req_by_degree_id\");'>" . t("Unassign fulfillment?") . "</a></div>";
        $html .= "</div>";
      }

      $render["course_not_assigned_to_group"] = array(
        "type" => "markup",
        "value" => $html,
        "weight" => 80,
      );

    }


    // Substitutors get extra information:
    if (user_has_permission("can_substitute") && $course->get_first_assigned_to_group_id()) {

      $html = "";

      $html .= "

        <span id='viewinfolink'
        onClick='document.getElementById(\"admin_info\").style.display=\"\"; this.style.display=\"none\"; '
        class='hand' style='color: blue;'
        > - " . t("Click to show") . " -</span>

          <div style='padding-left: 20px; display:none;' id='admin_info'>
          Groups this course has been assigned to:
          ";

      // Course is assigned to a group.
      // might be assigned to multiple groups, so show them in a loop
      if ($course->get_first_assigned_to_group_id()) {
        foreach ($course->assigned_to_group_ids_array as $group_id) {
          $group = new Group();
          $group->group_id = $group_id;
          $group->load_descriptive_data();

          $html .= "<div>
              " . t("Course is assigned to group:") . "<br>
              &nbsp; " . t("Group ID:") . " $group->group_id<br>
              &nbsp; " . t("Title:") . " $group->title<br>";
          $html .= "&nbsp; <i>" . t("Internal name:") . " $group->group_name</i><br>";

          $html .= "&nbsp; " . t("Catalog year:") . " $group->catalog_year
                   </div>";
        }
      }
      $html .= "


          </div>";

      $render["substitutor_extra"] = array(
        "type" => "markup",
        "label" => ("Special administrative information:"),
        "value" => $html,
        "weight" => 90,
        "attributes" => array("class" => " "),
      );

    }



    // Has the course been substituted into *this* degree plan?
    if ($course->get_bool_substitution() == TRUE)
    {

      $html = "";

      // Find out who did it and if they left any remarks.
      $db = $this->db;
      $sub_id = $course->db_substitution_id_array[$course->get_course_substitution()->req_by_degree_id];

      $temp = $db->get_substitution_details($sub_id);

      $required_degree_id = $temp["required_degree_id"];
      $req_degree_plan = new DegreePlan();
      $req_degree_plan->degree_id = $required_degree_id;

      $by = $db->get_faculty_name($temp["faculty_id"], false);
      $remarks = $temp["remarks"];
      $ondate = format_date($temp["posted"], "", "n/d/Y");


      if ($by != "") {
        $by = " by $by, on $ondate.";
      }

      if ($remarks != "")
      {
        $remarks = " " . t("Substitution remarks:") . " <i>$remarks</i>.";
      }

      $forthecourse = t("for the original course
          requirement of") . " <b>" . $course->get_course_substitution()->subject_id . "
          " . $course->get_course_substitution()->course_num . " (" . $course->get_course_substitution()->get_hours() . " " . t("hrs") . ")</b>";
      if ($temp["required_course_id"]*1 == 0)
      {
        $forthecourse = "";
      }

      $html .= "<div class=' ' style='margin-top: 10px;'>
          <b>" . t("Note:") . "</b> " . t("This course was substituted into the %title
          degree plan", array("%title" => $req_degree_plan->get_title2())) . " $forthecourse
          $by$remarks";


      if (user_has_permission("can_substitute")) {
        $html .= "<div align='left' class=' ' style='padding-left: 10px;'>
          <b>" . t("Special administrative function:") . "</b>
          <a href='javascript: popupRemoveSubstitution(\"$sub_id\");'>" . t("Remove substitution?") . "</a>
         </div>";
      }

      $render["course_sub_this_degree_plan"] = array(
        "type" => "markup",
        "value" => $html,
        "weight" => 100,
      );

    }



    // Variable hours? Only show if the course has not been taken...
    $var_hours_default = "";
    if ($course->has_variable_hours() && $course->grade == "")
    {
      $html = "";

      $html .= "<div class=' '>
          " . t("This course has variable hours. Please select
          how many hours this course will be worth:") . "<br>
          <div style='text-align: center;'>
          <select name='selHours' id='selHours' onChange='popupSetVarHours();'>
          ";

      // Correct for ghost hours, if they are there.
      $min_h = $course->min_hours*1;
      $max_h = $course->max_hours*1;
      if ($course->bool_ghost_min_hour) $min_h = 0;
      if ($course->bool_ghost_hour) $max_h = 0;

      for($t = $min_h; $t <= $max_h; $t++)
      {
        $sel = "";
        if ($t == $course->advised_hours){ $sel = "SELECTED"; }
        $html .= "<option value='$t' $sel>$t</option>";
      }
      $html .= "</select> " . t("hours.") . "<br>

          </div>
          </div>";

      if ($course->advised_hours > -1)
      {
        $var_hours_default = $course->advised_hours *1;
      } else {
        $var_hours_default = $min_h;
      }

      $render["course_var_hour_select"] = array(
        "value" => $html,
        "weight" => 110,
      );

    }


    // Some hidden vars and other details
    $html = "";
    if ($show_advising_buttons == true && !$this->bool_blank) {

      // Insert a hidden radio button so the javascript works okay...
      $html .= "<input type='radio' name='course' value='$course->course_id' checked='checked'
          style='display: none;'>
          <input type='hidden' name='varHours' id='varHours' value='$var_hours_default'>";

      $render['#group_assigned_to_semester_num'] = $group->assigned_to_semester_num;
      $render['#group_id'] = $group->group_id;
      $render['#advising_term_id'] = $advising_term_id;
      $render['#db_group_requirement_id'] = $db_group_requirement_id;
      $render['#req_by_degree_id'] = $req_by_degree_id;

      $group_js_select = "popupAssignSelectedCourseToGroup(\"$group->assigned_to_semester_num\", \"$group->group_id\",\"$advising_term_id\",\"$db_group_requirement_id\",\"$req_by_degree_id\");";

      $render['#group_js_select'] = $group_js_select;

      if (user_has_permission("can_advise_students"))
      {
        $html .= fp_render_button(t("Select Course"), $group_js_select);
      }
    }
    else if ($show_advising_buttons == false && $course->has_variable_hours() == true && $course->grade == "" && user_has_permission("can_advise_students") && !$this->bool_blank) {
      // Show an "update" button, and use the course's assigned_to_group_id and
      // assigned_to_semester_num.
      $html .= "<input type='hidden' name='varHours' id='varHours' value='$var_hours_default'> \n";

      // Same situation about the group_id.  I guess need to find out exactly which group it was assigned to?

      $html .= fp_render_button(t("Update"), "popupUpdateSelectedCourse(\"$course->course_id\",\"" . $course->get_first_assigned_to_group_id() . "\",\"$course->assigned_to_semester_num\",\"$course->random_id\",\"$advising_term_id\",\"$req_by_degree_id\");");

    }

    $render["hidden_vars_and_buttons"] = array(
      "value" => $html,
      "weight" => 1000,
    );


    watchdog("advise", "popup_course_description $course->course_id. <pre>" . print_r($course, TRUE) . "</pre>", array(), WATCHDOG_DEBUG);


    // Okay, render our render array and return.
    $pC .= fp_render_content($render);
    return $pC;

  } // end function




  /**
   * Simple function to make an institution name look more pretty, because
   * all institution names pass through ucwords(), sometimes the capitalization
   * gets messed up.  This function tries to correct it.
   *
   * Feel free to override it and add to it, if needed.
   *
   * @param string $str
   * @return string
   */
  function fix_institution_name($str)
  {
    $student_id = $this->student->student_id;
    $school_id = db_get_school_id_for_student_id($student_id);

    // Should we do this at all?  We will look at the "autocapitalize_institution_names" setting.
    $auto = variable_get_for_school("autocapitalize_institution_names", 'yes', $school_id);
    if ($auto == "no") {
      // Nope!  Just return.
      return $str;
    }

    $str = str_replace("-", " - ", $str);
    $str = ucwords(strtolower($str));
    $str = str_replace(" Of ", " of ", $str);
    $str = str_replace("clep", "CLEP", $str);
    $str = str_replace("_clep", "CLEP", $str);
    $str = str_replace("_act", "ACT", $str);
    $str = str_replace("_sat", "SAT", $str);
    $str = str_replace("Ap ", "AP ", $str);
    $str = str_replace("_dsst", "DSST", $str);

    // Fix school initials.
    // Turns "Ulm" into "ULM"
    $school_initials = variable_get_for_school("school_initials", "DEMO", $school_id);
    $str = str_replace(ucwords(strtolower($school_initials)), $school_initials, $str);


    if ($str == "")
    {
      $str = "<i>unknown institution</i>";
    }



    return $str;
  }

  /**
   * Left in for legacy reasons, this function uses a new Course object's
   * method of $course->fix_title to make a course's title more readable.
   *
   * @param string $str
   * @return string
   */
  function fix_course_title($str)
  {

    $new_course = new Course();
    $str = $new_course->fix_title($str);


    return $str;
  }



  /**
   * Given a Semester object, this will generate the HTML to draw it out
   * to the screen.  We will take advantage of the render engine, so we
   * can utilize hook_content_alter later on.
   *
   * @param Semester $semester
   * @param bool $bool_display_hour_count
   *       - If set to TRUE, it will display a small "hour count" message
   *         at the bottom of each semester, showing how many hours are in
   *         the semester.  Good for debugging purposes.
   *
   * @return string|false
   */
  function display_semester(Semester $semester, $bool_display_hour_count = FALSE)
  {
    // Display the contents of a semester object
    // on the screen (in HTML)

    $render = array();
    $render['#id'] = 'AdvisingScreen_display_semester';
    $render['#semester'] = $semester;
    $render['#degree_plan'] = $this->degree_plan;
    $render['#bool_display_hour_count'] = $bool_display_hour_count;
    $render_weight = 0;
    $render['semester_box_top'] = array(
      'value' => $this->draw_semester_box_top($semester->title),
      'weight' => $render_weight,
     );

    $count_hoursCompleted = 0;


    $html = array();


    // Create a temporary caching system for degree titles, so we don't have to keep looking them back up.
    if (!isset($GLOBALS["fp_temp_degree_titles"])) {
      $GLOBALS["fp_temp_degree_titles"] = array();
      $GLOBALS["fp_temp_degree_types"] = array();
      $GLOBALS["fp_temp_degree_classes"] = array();
      $GLOBALS["fp_temp_degree_levels"] = array();
    }


    $degree_sort_policy = variable_get_for_school("degree_requirement_sort_policy", "alpha", $this->student->school_id);

    // First, display the list of bare courses.
    if ($degree_sort_policy == 'database') {
      $semester->list_courses->sort_degree_requirement_id();
    }
    else {
      // By default, sort alphabetical
      $semester->list_courses->sort_alphabetical_order();  // sort, including the degree title we're sorting for.
    }
    $semester->list_courses->reset_counter();

    while($semester->list_courses->has_more())
    {
      $course = $semester->list_courses->get_next();

      if (!isset($html[$course->req_by_degree_id])) {
        $html[$course->req_by_degree_id] = "";
      }

      // Is this course being fulfilled by anything?

      if (!($course->course_list_fulfilled_by->is_empty))
      { // this requirement is being fulfilled by something the student took...

        $c = $course->course_list_fulfilled_by->get_first();

        $c->req_by_degree_id = $course->req_by_degree_id;  // make sure we assign it to the current degree_id.

        // Tell the course what group we are coming from. (in this case: none)
        $c->disp_for_group_id = "";

        $html[$course->req_by_degree_id]  .= $this->draw_course_row($c);
        $c->set_has_been_displayed($course->req_by_degree_id);


        if ($c->display_status == "completed")
        { // We only want to count completed hours, no midterm or enrolled courses.
          $h = $c->get_hours_awarded();
          if ($c->bool_ghost_hour == TRUE) {
            $h = 0;
          }
          $count_hoursCompleted += $h;
        }

      } else {
        // This requirement is not being fulfilled...

        // Tell the course what group we are coming from. (in this case: none)
        $course->disp_for_group_id = "";
        $x = $this->draw_course_row($course);
        //fpm(htmlentities($x));
        $html[$course->req_by_degree_id]  .= $x;

      }



    }


    /////////////////////////////////////
    // Now, draw all the groups.
    $semester->list_groups->sort_alphabetical_order();
    $semester->list_groups->reset_counter();
    while($semester->list_groups->has_more())
    {

      $group = $semester->list_groups->get_next();

      if (!isset($html[$group->req_by_degree_id])) {
        $html[$group->req_by_degree_id] = "";
      }

      //$html[$group->req_by_degree_id] .= "<tr class='semester-display-group-tr'><td colspan='8'>";
      $x = $this->display_group($group);
      //fpm(htmlentities($x, TRUE));
      $html[$group->req_by_degree_id] .= $x;
      $count_hoursCompleted += $group->hours_fulfilled_for_credit;
      //$html[$group->req_by_degree_id] .= "</td></tr>";
    } //while groups.




    // Sort by degree's advising weight
    $new_html = array();
    foreach($html as $req_by_degree_id => $content) {

      $dtitle = @$GLOBALS["fp_temp_degree_titles"][$req_by_degree_id];
      $dweight = intval(@$GLOBALS["fp_temp_degree_advising_weights"][$req_by_degree_id]);

      if ($dtitle == "") {
        $t_degree_plan = new DegreePlan();
        $t_degree_plan->degree_id = $req_by_degree_id;
        //$t_degree_plan->load_descriptive_data();
        $dtitle = $t_degree_plan->get_title2(TRUE, TRUE);
        $dweight = $t_degree_plan->db_advising_weight;
        $dtype = $t_degree_plan->degree_type;
        $dclass = $t_degree_plan->degree_class;
        $dlevel = $t_degree_plan->degree_level;
        $GLOBALS["fp_temp_degree_titles"][$req_by_degree_id] = $dtitle . " "; //save for next time.
        $GLOBALS["fp_temp_degree_types"][$req_by_degree_id] = $dtype; //save for next time.
        $GLOBALS["fp_temp_degree_classes"][$req_by_degree_id] = $dclass; //save for next time.
        $GLOBALS["fp_temp_degree_levels"][$req_by_degree_id] = $dlevel; //save for next time.
        $GLOBALS["fp_temp_degree_advising_weights"][$req_by_degree_id] = $dweight . " "; //save for next time.
      }

      $degree_title = fp_get_machine_readable($dtitle);  // make it machine readable.  No funny characters.
      $degree_advising_weight = str_pad($dweight, 4, "0", STR_PAD_LEFT);


      $new_html[$degree_advising_weight . "__" . $degree_title][$req_by_degree_id] = $content;

    }

    // Sort by the first index, the advising weight.

    ksort($new_html);

    $pC = "";

    //////////////////////////
    // Okay, now let's go through our HTML array and add to the screen....
    foreach ($new_html as $w => $html) {
      foreach($html as $req_by_degree_id => $content) {

        // Get the degree title...
        $dtitle = @$GLOBALS["fp_temp_degree_titles"][$req_by_degree_id];
        $dtype = @$GLOBALS["fp_temp_degree_types"][$req_by_degree_id];
        $dclass = @$GLOBALS["fp_temp_degree_classes"][$req_by_degree_id];
        $dlevel = @$GLOBALS["fp_temp_degree_levels"][$req_by_degree_id];
        if ($dtitle == "") {
          $t_degree_plan = new DegreePlan();
          $t_degree_plan->degree_id = $req_by_degree_id;
          //$t_degree_plan->load_descriptive_data();
          $dtitle = $t_degree_plan->get_title2(TRUE, TRUE);

          $dtype = $t_degree_plan->degree_type;
          $dclass = $t_degree_plan->degree_class;
          $dlevel = $t_degree_plan->degree_level;

          $GLOBALS["fp_temp_degree_titles"][$req_by_degree_id] = $dtitle; //save for next time.
          $GLOBALS["fp_temp_degree_types"][$req_by_degree_id] = $dtype; //save for next time.
          $GLOBALS["fp_temp_degree_classes"][$req_by_degree_id] = $dclass; //save for next time.
          $GLOBALS["fp_temp_degree_levels"][$req_by_degree_id] = $dlevel; //save for next time.

        }

        $css_dtitle = fp_get_machine_readable($dtitle);


        $theme = array(
          'classes' => array(' ', 'required-by-degree',
                              "required-by-degree-$css_dtitle",
                              "required-by-degree-type-" . fp_get_machine_readable($dtype),
                              "required-by-degree-class-" . fp_get_machine_readable($dclass),
                              "required-by-degree-level-" . fp_get_machine_readable($dlevel)),
          'css_dtitle' => $css_dtitle,
          'degree_id' => $req_by_degree_id,
          'required_by_html' => "<span class='req-by-label'>" . t("Required by") . "</span> <span class='req-by-degree-title'>$dtitle</span>",
          'view_by' => 'year',
        );


        // Don't display if we are in the Courses Added semester, or if we are NOT a "combined" degree.
        if ($semester->semester_num == DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED || (is_object($this->degree_plan)) && !$this->degree_plan->is_combined_dynamic_degree_plan) {
          $theme['required_by_html'] = '';
        }

        invoke_hook("theme_advise_degree_header_row", array(&$theme));

        if ($theme['required_by_html']) {
          $pC .= "<tr><td colspan='8' class='required-by-td'>
                    <div class='" . implode(' ',$theme['classes']) ."'>{$theme['required_by_html']}</div>
                  </td></tr>";
        }

        $pC .= $content;
      }
    }


    $render_weight = $render_weight + 10;

    $render['semester_content'] = array(
      'value' => $pC,
      'weight' => $render_weight,
    );



    // Add hour count to the bottom...
    if ($bool_display_hour_count == true && $count_hoursCompleted > 0)
    {
      $p = "<tr><td colspan='8'>
        <div class='advise-completed-hours' style='text-align:right; margin-top: 10px;'>
        <span class='completed-hours-label'>" . t("Completed hours:") . "</span> <span class='count-hours-completed'>$count_hoursCompleted</span>
        </div>
        </td></tr>";

      $render_weight = $render_weight + 10;
      $render['semester_disp_hour_count'] = array(
        'value' => $p,
        'weight' => $render_weight,
      );

    }


    // Does the semester have a notice?
    if ($semester->notice != "")
    {
      $p = "<tr><td colspan='8'>
          <div class='hypo   advise-semester-notice' style='margin-top: 15px; padding: 5px;'>
            <b>" . t("Important Notice:") . "</b> $semester->notice
          </div>
          </td></tr>";

      $render_weight = $render_weight + 10;
      $render['semester_notice'] = array(
        'value' => $p,
        'weight' => $render_weight,
      );
    }










    //$pC .= $this->draw_semester_box_bottom();
    $render_weight = $render_weight + 10;
    $render['semester_box_bottom'] = array(
      'value' => $this->draw_semester_box_bottom(),
      'weight' => $render_weight,
    );



    // If the semester has NO content, then just return FALSE.
    if (trim($render['semester_content']['value']) == "" && !isset($render['semester_notice']) && !isset($render['semester_disp_hour_count'])) {
      return FALSE;
    }

    // Otherwise, render out and return the HTML
    return fp_render_content($render);
  }





  /**
   * This function displays a Group object on the degree plan.  This is not
   * the selection popup display.  It will either show the group as multi
   * rows, filled in with courses, or as a "blank row" for the user to click
   * on.
   *
   * @param Group $place_group
   * @return string|false
   */
  function display_group(Group $place_group)
  {
    // Display a group, either filled in with courses,
    // and/or with a "blank row" for the user to
    // click on.
    $rtn = "";

    // Now, if you will recall, all of the groups and their courses, etc,
    // are in the degree_plan's list_groups.  The $place_group object here
    // is just a placeholder.  So, get the real group...

    if (!$group = $this->degree_plan->find_group($place_group->group_id))
    {
      fpm("Group not found.");
      return FALSE;
    }


    $title = $group->title;

    $display_course_list = new CourseList();

    // Okay, first look for courses in the first level
    // of the group.

    $display_semesterNum = $place_group->assigned_to_semester_num;
    $req_by_degree_id = $group->req_by_degree_id;

    // Make sure all courses and subgroups have the same req_by_degree_id set.
    $group->set_req_by_degree_id($group->req_by_degree_id);

    // What we are trying to do is end up with a list of courses we want to display on the screen (for example,
    // that the student took or were substituted in)
    $group->list_courses->remove_unfulfilled_and_unadvised_courses();
    $group->list_courses->reset_counter();
    while($group->list_courses->has_more())
    {
      $course = $group->list_courses->get_next();

      // Do we have enough hours to keep going?
      $fulfilled_hours = $display_course_list->count_hours("", FALSE, TRUE, FALSE, FALSE, $req_by_degree_id);
      $remaining = $place_group->hours_required - $fulfilled_hours;
      // If the course in question is part of a substitution that is not
      // for this group, then we should skip it.
      if (!($course->course_list_fulfilled_by->is_empty))
      {
        $try_c = $course->course_list_fulfilled_by->get_first();
        if ($try_c->get_bool_substitution($req_by_degree_id) == TRUE && $try_c->get_bool_assigned_to_group_id($group->group_id) != TRUE)
        {
          continue;
        }
      }


      if (!($course->course_list_fulfilled_by->is_empty) && $course->course_list_fulfilled_by->get_first()->get_has_been_displayed($req_by_degree_id) != TRUE && $course->get_has_been_displayed($req_by_degree_id) != TRUE)
      {
        $c = $course->course_list_fulfilled_by->get_first();
        $ch = $c->get_hours($req_by_degree_id);




        // Because PHP has dumb floating point arithmatic, we are going to round our values to 8 places,
        // otherwise I was getting weird results like 0.34 < 0.34 == true.  I chose 8 places to make sure it wouldn't
        // actually cause the values to round and mess up the math.
        $remaining = round($remaining, 8);
        $ch = round($ch, 8);



        // Is whats remaining actually LESS than the course hours?  If so, we need to skip it.
        if ($remaining < $ch)
        {
          continue;
        }


        $c->temp_flag = false;
        $c->icon_filename = $group->icon_filename;
        $c->title_text = "This course is a member of $group->title." . " ($place_group->requirement_type)";
        $c->requirement_type = $place_group->requirement_type;
        $c->req_by_degree_id = $req_by_degree_id;



        $display_course_list->add($c);


      }




      if ($course->bool_advised_to_take && $course->get_has_been_displayed($req_by_degree_id) != true && $course->assigned_to_semester_num == $display_semesterNum)
      {



        $c = $course;

        if ($remaining < $c->get_hours($req_by_degree_id))
        {
           continue;
        }

        $c->temp_flag = true;
        $c->icon_filename = $group->icon_filename;
        $c->req_by_degree_id = $req_by_degree_id;
        $c->title_text = t("The student has been advised to take this course to fulfill a @gt requirement.", array("@gt" => $group->title));



        $display_course_list->add($c);


      }
    }


    $group->list_groups->reset_counter();
    while($group->list_groups->has_more())
    {

      $branch = $group->list_groups->get_next();
      // look for courses at this level...
      if (!$branch->list_courses->is_empty)
      {

        $branch->list_courses->sort_alphabetical_order();
        $branch->list_courses->reset_counter();
        while($branch->list_courses->has_more())
        {
          $course = $branch->list_courses->get_next();

          // Do we have enough hours to keep going?
          $fulfilled_hours = $display_course_list->count_hours("", FALSE, TRUE, FALSE, FALSE, $req_by_degree_id);
          $remaining = $place_group->hours_required - $fulfilled_hours;


          if (!($course->course_list_fulfilled_by->is_empty) && $course->course_list_fulfilled_by->get_first()->get_has_been_displayed($req_by_degree_id) != true && $course->get_has_been_displayed($req_by_degree_id) != true)
          {
            $c = $course->course_list_fulfilled_by->get_first();
            if ($remaining < $c->get_hours() || $remaining == 0)
            {
              continue;
            }

            $c->temp_flag = false;
            $c->icon_filename = $group->icon_filename;
            $c->title_text = "This course is a member of $group->title." . "($place_group->requirement_type)";
            $c->requirement_type = $place_group->requirement_type;
            $c->req_by_degree_id = $req_by_degree_id;


            // TODO:  This right here is how we show specified-repeats in groups with branches.
            // TODO:  Originally we were making sure there wasn't a "match" already in the display_course_list, which was
            //        of course excluding any of our specified repeats.
            //if (!$display_course_list->find_match($c))
            $found_match = $display_course_list->find_match($c);
            if (!$found_match || ($found_match && $c->bool_specified_repeat == TRUE)) {
              $display_course_list->add($c);
            }
            else if (is_object($c->course_transfer)) {
              if (!$display_course_list->find_match($c->course_transfer))
              { // Make sure it isn't already in the display list.
                $display_course_list->add($c);
              }
            }


          }

          if ($course->bool_advised_to_take && $course->get_has_been_displayed($req_by_degree_id) != true && $course->assigned_to_semester_num == $display_semesterNum)
          {

            $c = $course;
            if ($remaining < $c->get_hours($req_by_degree_id) || $remaining == 0)
            {

              continue;
            }

            $c->temp_flag = true;
            $c->icon_filename = $group->icon_filename;
            $c->req_by_degree_id = $req_by_degree_id;
            $c->title_text = "The student has been advised to take this course to fulfill a $group->title requirement.";
            if (!$display_course_list->find_match($c))
            {
              $display_course_list->add($c);
            }

          }


        }

      }
    }




    $display_course_list->sort_advised_last_alphabetical();
    // Make sure we're all on the same page, for what degree_id we're being displayed under.
    $display_course_list->set_req_by_degree_id($req_by_degree_id);


    $rtn .= $this->display_group_course_list($display_course_list, $group, $display_semesterNum);


    // original: $fulfilled_hours = $display_course_list->count_hours("", false, false, TRUE, false, $req_by_degree_id);
    // Changing to new line, to match other argument list for previous occurances of 'fulfilled_hours'. This makes it so that
    // a zero-hour course does not "use up" a 1 hour spot in the course hour counts.
    // TODO:  This might cause a group of *only* zero hour courses to never count as being filled.
    // TODO:  Maybe this difference between the original and this line should be a setting?  Or per-group?
    $fulfilled_hours = $display_course_list->count_hours("", FALSE, TRUE, FALSE, FALSE, $req_by_degree_id);


    $fulfilled_credit_hours = $display_course_list->count_credit_hours("",false,true);



    $test_hours = $fulfilled_hours;

    // if the fulfilledCreditHours is > than the fulfilledHours,
    // then assign the fulfilledCreditHours to the testHours.
    if ($fulfilled_credit_hours > $fulfilled_hours)
    { // done to fix a bug involving splitting hours in a substitution.
      $test_hours = $fulfilled_credit_hours;
    }
    // If there are any remaining hours in this group,
    // draw a "blank" selection row.
    $remaining = $place_group->hours_required - $test_hours;
    $place_group->hours_remaining = $remaining;
    $place_group->hours_fulfilled = $fulfilled_hours;
    $place_group->hours_fulfilled_for_credit = $fulfilled_credit_hours;




    if ($remaining > 0)
    {
      $rowclass = "";
      // If we have met the min hours (if the group even HAS min hours) then add a class to $rowclass,
      // so we can hide it or whatever with CSS.
      if ($group->has_min_hours_allowed()) {
        if ($test_hours >= $group->min_hours_allowed) {
          $rowclass .= "group-select-min-hours-fulfilled";
        }
      }


      $rtn .= $this->draw_group_select_row($place_group, $remaining, $rowclass);

    }

    return $rtn;
  }


  /**
   * Find all instaces of a Course in a Group and mark as displayed.
   *
   * @param Group $group
   * @param Course $course
   */
  function mark_course_as_displayed(Group $group, Course $course)
  {
    // Find all instances of $course in $group,
    // and mark as displayed.

    if ($obj_list = $group->list_courses->find_all_matches($course))
    {
      $course_list = CourseList::cast($obj_list);
      $course_list->mark_as_displayed();
    }
    // Now, go through all the course lists within each branch...
    $group->list_groups->reset_counter();
    while($group->list_groups->has_more())
    {
      $g = $group->list_groups->get_next();
      if ($obj_list = $g->list_courses->find_all_matches($course))
      {

        $course_list = CourseList::cast($obj_list);
        //$course_list->mark_as_displayed($semester_num);
        $course_list->mark_as_displayed();
      }
    }


  }



  /**
   * Displays all the courses in a CourseList object, using
   * the draw_course_row function.
   *
   * It looks like the group and semester_num are not being used
   * anymore.
   *
   * @todo Check on unused variables.
   *
   * @param CourseList $course_list
   * @param Group $group
   * @param int $semester_num
   * @return string
   */
  function display_group_course_list($course_list, $group, $semester_num)
  {
    $pC = "";

    $course_list->reset_counter();
    while($course_list->has_more())
    {
      $course = $course_list->get_next();

      // Tell the course what group we are coming from, so it displays correctly
      $course->disp_for_group_id = $group->group_id;


      $pC .= $this->draw_course_row($course, $course->icon_filename, $course->title_text, $course->temp_flag, TRUE, TRUE, FALSE, $group);

      // Doesn't matter if its a specified repeat or not.  Just
      // mark it as having been displayed.
      $course->set_has_been_displayed($group->req_by_degree_id);

    }
    return $pC;

  }


  /**
   * This draws the "blank row" for a group on the degree plan, which instructs
   * the user to click on it to select a course from the popup.
   *
   * @param Group $group
   * @param int $remaining_hours
   * @return string
   */
  function draw_group_select_row(Group $group, $remaining_hours, $rowclass = "")
  {

    $img_path = fp_theme_location() . "/images";


    $on_mouse_over = "
            onmouseover='$(this).addClass(\"selection_highlight\");'
            onmouseout='$(this).removeClass(\"selection_highlight\");'   ";



    $render = array();
    $render['#id'] = 'AdvisingScreen_draw_group_select_row';
    $render['#group'] = $group;
    $render['#group_name'] = $group->group_name;
    $render['#remaining_hours'] = $remaining_hours;
    $render['#semester_num'] = $group->assigned_to_semester_num;

    $school_id = $group->school_id;

    $s = "s";
    if ($remaining_hours < 2)
    {
      $s = "";
    }

    $title_text = $extra_classes = "";


    // Add the name of the group to the extra-classes
    $extra_classes .= " gr-" . fp_get_machine_readable($group->group_name);


    $select_icon = "<img src='$img_path/select.gif' class='advising-course-row-select-icon'>";
    $icon_link = "<img src='$img_path/icons/$group->icon_filename' class='advising-course-row-icon advising-group-select-icon' alt='$title_text' title='$title_text'>";

    $blank_degree_id = "";
    if ($this->bool_blank)
    {
      $blank_degree_id = $this->degree_plan->degree_id;
    }

    $req_by_degree_id = $group->req_by_degree_id;
    $render['#degree_id'] = $req_by_degree_id;

    $disp_remaining_hours = $remaining_hours;
    // If the group has min_hours, then the disp_remaining_hours gets that too.
    if ($group->has_min_hours_allowed()) {
      $disp_remaining_hours = ($group->min_hours_allowed - $group->hours_fulfilled) . "-" . $remaining_hours;
    }

    $dialog_title = t("Select @disp_hrs hour$s from %group_title", array('@disp_hrs' => $disp_remaining_hours, '%group_title' => $group->title));
    if ($remaining_hours > 200) {
      // Don't bother showing the remaining hours number.
      $dialog_title = t("Select additional hour$s from %group_title", array('%group_title' => $group->title));
      if ($group->group_id == DegreePlan::GROUP_ID_FOR_COURSES_ADDED) {
        $dialog_title = t("Select additional courses");
      }
    }


    $js_code = "selectCourseFromGroup(\"$group->group_id\", \"$group->assigned_to_semester_num\", \"$remaining_hours\", \"$blank_degree_id\",\"$req_by_degree_id\",\"$dialog_title\");";

    $row_msg = t("Click") . " <span style='color:red;' class='group-select-arrows'>&gt;&gt;</span> " . t("to select @drh hour$s.", array("@drh" => $disp_remaining_hours));
    if ($remaining_hours > 200) {
      // Don't bother showing the remaining hours number.
      $row_msg = t("Click") . " <span style='color:red;' class='group-select-arrows'>&gt;&gt;</span> " . t("to select additional courses.");
    }

    $hand_class = "hand";

    if (variable_get_for_school("show_group_titles_on_view", "no", $school_id) == "yes")
    {

      $row_msg = t("Select") . " $disp_remaining_hours " . t("hour$s from") . " $group->title.";

      if ($remaining_hours > 200) {
        // Don't bother showing the remaining hours number.
        $row_msg = t("Select additional courses from") . " $group->title.";
      }

      if ($this->bool_print) {
        // In print view, disable all popups and mouseovers.
        $on_mouse_over = "";
        $js_code = "";
        $hand_class = "";
      }

    }


    if ($group->group_id == DegreePlan::GROUP_ID_FOR_COURSES_ADDED)
    { // This is the Add a Course group.
      $row_msg = t("Click to add an additional course.");
      $select_icon = "<span style='color:#0080ff;'><i class='fa fa-plus'></i></span>";
      $icon_link = "";
    }


    // Let's find out if this group contains courses which can be used in more than one degree.
    $res = intval($this->degree_plan->get_max_course_appears_in_degrees_count($group->group_id));

    if ($res > 1) {
      $extra_classes .= " contains-course-which-appears-in-mult-degrees contains-course-which-appears-in-$res-degrees";
    }


    // Just like the other times we check to theme a course row, let's give the option to theme this as well.
    $theme = array();
    $theme["screen"] = $this;
    $theme["degree_plan"] = $this->degree_plan;
    $theme["student"] = $this->student;
    $theme["group"]["group"] = $group;
    $theme["group"]["extra_classes"] = $extra_classes;
    $theme["group"]["icon_link"] = $icon_link;
    $theme["group"]["select_icon"] = $select_icon;
    $theme["group"]["js_code"] = $js_code;
    $theme["group"]["row_msg"] = $row_msg;
    $theme["group"]["title"] = $group->title;
    $theme["group"]["remaining_hours"] = $remaining_hours;


    // Invoke a hook on our theme array, so other modules have a chance to change it up.
    invoke_hook("theme_advise_group_select_row", array(&$theme));

    $render['#js_code'] = $js_code;



    $render['start_group_select_row'] = array(
      'value' => "<tr class='from-render-api $rowclass'><td colspan='8' class='group-select-row-tr'>",
      'weight' => 0,
    );



    $render['group_select_table_top'] = array(
      'value' => "<table border='0' cellpadding='0' class='table-group-select-row' cellspacing='0' >",
      'weight' => 100,
    );

    $render['group_select_table_tr'] = array(
      'value' => "<tr class='$hand_class {$theme["group"]["extra_classes"]} group-select-row'
          $on_mouse_over title='{$theme["group"]["title"]}'>",
      'weight' => 200,
    );

    $render['group_select_table_w1_1'] = array(
      'value' => "<td class='group-w1_1 w1_1' ></td>",
      'weight' => 300,
    );

    $render['group_select_table_icon_link'] = array(
      'value' => "<td class='group-w1_2 w1_2' onClick='{$theme["group"]["js_code"]}'>{$theme["group"]["icon_link"]}</td>",
      'weight' => 400,
    );

    $render['group_select_table_select_icon'] = array(
      'value' => "<td class='group-w1_3 w1_3' onClick='{$theme["group"]["js_code"]}'>{$theme["group"]["select_icon"]}</td>",
      'weight' => 500,
    );

    $render['group_select_table_row_msg'] = array(
      'value' => "<td class='underline group-row-msg' onClick='{$theme["group"]["js_code"]}'>
          {$theme["group"]["row_msg"]}
          </td>",
      'weight' => 600,
    );


    $render['group_select_table_bottom'] = array(
      'value' => "</tr>
                  </table>",
      'weight' => 5000,
    );



    $render['end_group_select_row'] = array(
      'value' => "</td></tr>",
      'weight' => 9999,
    );


    return fp_render_content($render, FALSE);
  }

  /**
   * Uses the draw_box_top function, specifically for semesters.
   *
   * @param string $title
   * @param bool $hideheaders
   * @return string
   */
  function draw_semester_box_top($title, $hideheaders = false)
  {

    $extra_classes = " fp-semester-box-top fp-semester-box-top-" . fp_get_machine_readable(strtolower($title));


    return $this->draw_box_top($title, $hideheaders, $extra_classes);
  }

  /**
   * Uses the draw_box_bottom function, specifically for semesters.
   * Actually, this function is a straight alias for $this->draw_box_bottom().
   *
   * @return string
   */
  function draw_semester_box_bottom()
  {
    return $this->draw_box_bottom();
  }

  /**
   * Very, very simple.  Just returns "</table>";
   *
   * @return string
   */
  function draw_box_bottom()
  {
    return "</table>";
  }

  /**
   * Used to draw the beginning of semester boxes and other boxes, for example
   * the footnotes.
   *
   * @param string $title
   * @param bool $hideheaders
   *       - If TRUE, then the course/hrs/grd headers will not be displayed.
   *
   *
   * @return string
   */
  function draw_box_top($title, $hideheaders = FALSE, $extra_classes = ""){
    // returns the beginnings of the semester block tables...

    $headers = array();
    if ($hideheaders != TRUE)
    {
      $headers[0] = t("Course");
      $headers[1] = t("Hrs");
      $headers[2] = t("Grd");
      $headers[3] = t("Pts");
    }


    $extra_classes .= " fp-box-top-" . fp_get_machine_readable(strtolower($title));


    $render = array();
    $render['#id'] = 'AdvisingScreen_draw_box_top';
    $render['#title'] = $title;
    $render['#hideheaders'] = $hideheaders;
    $render['#extra_classes'] = $extra_classes;


    $render['table_top'] = array(
      'value' => "<table border='0' cellpadding='0' cellspacing='0' class='fp-box-top $extra_classes'>",
    );

    $render['semester_title_box_top'] = array(
      'value' => "<tr>
                  <td colspan='8' class='semester-box-top'>
                  ",
    );


    $render['section_title'] = array(
      'value' => fp_render_section_title($title),
    );

    $render['semester_title_box_bottom'] = array(
      'value' => "</td>
                  </tr>",
    );

    if (!$hideheaders)
    {

      $render['headers'] = array(
        'value' => "<tr class='box-headers-row'>
            <td colspan='8'>
            <table class='header-table' cellpadding='0' cellspacing='0'>
                      <th class='w1_1'></th>
                      <th class='w1_2'></th>
                      <th class='w1_3'></th>
                      <th class='w2'>
                        $headers[0]
                      </th>
                      <th class='w3'></th>
                      <th class='w4'>
                        $headers[1]
                      </th>
                      <th class='w5'>
                        $headers[2]
                      </th>
                      <th class='w6'>
                        $headers[3]
                      </th>
                      <!--after_last_th-->
                  </tr>
             </table>
             </td>
             </tr>
                  ",
      );

    }

    return fp_render_content($render, FALSE);

  } // draw_year_box_top


  /**
   * This is used by lots of other functions to display a course on the screen.
   * It will show the course, the hours, the grade, and quality points, as well
   * as any necessary icons next to it.
   *
   * @param Course $course
   * @param string $icon_filename
   * @param string $title_text
   * @param bool $js_toggle_and_save
   *         - If set to TRUE, when the checkbox next to this course is clicked,
   *           the page will be submitted and a draft will be saved.
   *
   * @param bool $bool_display_check
   *         - If set to FALSE, no checkbox will be displayed for this course row.
   *
   * @param bool $bool_add_footnote
   * @param bool $bool_add_asterisk_to_transfers
   *
   * @return string
   */
  function draw_course_row(Course $course, $icon_filename = "", $title_text = "", $js_toggle_and_save = false, $bool_display_check = true, $bool_add_footnote = true, $bool_add_asterisk_to_transfers = false, $group = NULL)
  {


    $render = array();
    $render['#id'] = 'AdvisingScreen_draw_course_row';
    $render['#course'] = $course;
    $render['#group'] = $group;


    // Display a course itself

    $theme = array();
    $theme["screen"] = $this;
    $theme["student"] = $this->student;
    $theme["degree_plan"] = $this->degree_plan;



    $img_path = fp_theme_location() . "/images";

    // The current term we are advising for.
    $advising_term_id = @$GLOBALS["fp_advising"]["advising_term_id"];

    $pts = "";

    if (!$advising_term_id) {
      $advising_term_id = 0;
    }

    $extra_classes = "";

    $course->assign_display_status();
    // If the course has already been advised in a different semester,
    // we should set the advising_term_id to that and disable unchecking.
    if ($course->advised_term_id != NULL && $course->advised_term_id != 0 && $course->advised_term_id != '' && $course->bool_advised_to_take == true && $course->advised_term_id != $advising_term_id)
    {
      $course->display_status = "disabled";
      $advising_term_id = $course->advised_term_id;
    }

    // Add the name of the course to the extra-classes
    $extra_classes .= " cr-" . fp_get_machine_readable($course->subject_id . " " . $course->course_num);

    // Has the course been assigned to more than one degree?
    if (count($course->assigned_to_degree_ids_array) > 1) {
      $extra_classes .= " course-assigned-more-than-one-degree course-assigned-" . count($course->assigned_to_degree_ids_array) . "-degrees";
    }

    // If this is a course fragment, created as a remainder of a split substitution, add extra class.
    if (@$course->details_by_degree_array[$course->req_by_degree_id]["bool_substitution_new_from_split"]) {
      $extra_classes .= " course-sub-new-from-split";
    }
    if (@$course->details_by_degree_array[$course->req_by_degree_id]["bool_substitution_split"]) {
      $extra_classes .= " course-sub-split";
    }



    // If the course has NOT been assigned, but is appearing in more than one degree, give it an extra CSS class
    // Check to see if the course is in our required_courses_id_array for more than one degree.
    if ($course->display_status == "eligible") {
      if (isset($this->degree_plan->required_course_id_array[$course->course_id])) {


        if (count($this->degree_plan->required_course_id_array[$course->course_id]) > 1) {
          // Add a new classname for this course...
          $extra_classes .= " course-appears-in-mult-degrees course-appears-in-" . count($this->degree_plan->required_course_id_array[$course->course_id]) . "-degrees";
        }
      }
    }


    if ($course->subject_id == "")
    {
      $course->load_descriptive_data();
    }

    $bool_transfer = FALSE;

    $subject_id = $course->subject_id;
    $course_num = $course->course_num;

    $o_subject_id = $subject_id;
    $o_course_num = $course_num;

    $degree_id = $course->req_by_degree_id;

    $footnote = "";
    $ast = "";
    // Is this actually a transfer course?  If so, display its
    // original subject_id and course_num.
    if ($course->bool_transfer == true)
    {
      $bool_transfer = TRUE;
      if ($course->course_transfer->subject_id == '') {
        $course->course_transfer->load_descriptive_transfer_data($this->student->student_id);
      }
      $subject_id = $course->course_transfer->subject_id;
      $course_num = $course->course_transfer->course_num;
      $institution_name = $course->course_transfer->institution_name;

      if ($bool_add_asterisk_to_transfers == true)
      {
        $course->course_transfer->load_descriptive_transfer_data($this->student->student_id);
        if ($course->course_transfer->transfer_eqv_text != "")
        {
          $ast = "<i class='advising-course-transfer-ast fa fa-asterisk'></i>";
          $GLOBALS["advising_course_has_asterisk"] = true;
        }
      }

      // Apply a footnote if it has a local eqv.
      if ($bool_add_footnote == true && $course->course_id > 0)
      {
        $footnote = "";

        $footnote .= "<span class='superscript'>T";
        $fcount = 0;
        if (isset($this->footnote_array) && is_array($this->footnote_array) && isset($this->footnote_array["transfer"])) {
          $fcount = @count($this->footnote_array["transfer"]) + 1;
        }

        if ($course->get_has_been_displayed() == true)
        { // If we've already displayed this course once, and are
          // now showing it again (like in the Transfer Credit list)
          // we do not want to increment the footnote counter.
          $fcount = $course->transfer_footnote;
        }
        $course->transfer_footnote = $fcount;
        $footnote .= "$fcount</span>";
        $this->footnote_array["transfer"][$fcount] = "$o_subject_id $o_course_num ~~ $subject_id $course_num ~~  ~~ $institution_name";
      }
    } // bool_transfer == true


    $hours = $course->get_hours_awarded();

    if ($course->get_bool_substitution() == TRUE )
    {

      $hours = $course->get_substitution_hours();

      $temp_sub_course = $course->get_course_substitution();

      if (is_object($temp_sub_course))
      {

        if ($temp_sub_course->subject_id == "")
        { // Reload subject_id, course_num, etc, for the substitution course,
          // which is actually the original requirement.
          $temp_sub_course->load_descriptive_data();
        }

        $o_subject_id = $temp_sub_course->subject_id;
        $o_course_num = $temp_sub_course->course_num;
      }


      if ($bool_add_footnote == true)
      {
        if (!isset($this->footnote_array["substitution"])) $this->footnote_array["substitution"] = array();
        $footnote = "";
        $footnote .= "<span class='superscript'>S";
        $fcount = count($this->footnote_array["substitution"]) + 1;
        if ($course->get_has_been_displayed($course->req_by_degree_id) == true)
        { // If we've already displayed this course once, and are
          // now showing it again (like in the Transfer Credit list)
          // we do not want to increment the footnote counter.
          $fcount = $course->substitution_footnote;
        }
        $course->substitution_footnote = $fcount;
        $footnote .= "$fcount</span>";
        $r = $course->req_by_degree_id;

        @$sub_id = $course->db_substitution_id_array[$r];
        $this->footnote_array["substitution"][$fcount] = "$o_subject_id $o_course_num ~~ $subject_id $course_num ~~ " . $course->get_substitution_hours() . " ~~ " . $course->get_first_assigned_to_group_id() . " ~~ $sub_id";

      }
    } // if course->get_bool_substitution() == true



    if (is_numeric($hours) && $hours <= 0) {
      // Some kind of error-- default to catalog hours
      $hours = $course->get_catalog_hours();
    }

    if (is_numeric($hours)) {
      $hours = floatval($hours) * 1;  // force numeric, trim extra zeros.
    }

    $var_hour_icon = "&nbsp;";


    // Show the var_hour icon if this has variable hours and is NOT from a split substitution.
    if ($course->has_variable_hours() == TRUE && !$course->bool_taken && !$course->get_bool_substitution_split() && !$course->get_bool_substitution_new_from_split())
    {
      // The bool_taken part of this IF statement is because if the course
      // has been completed, we should only use the hours_awarded.

      $var_hour_icon = "<img src='" . fp_theme_location() . "/images/var_hour.gif'
                title='" . t("This course has variable hours.") . "'
                alt='" . t("This course has variable hours.") . "'>";
      $hours = $course->get_advised_hours();

    }

    if ($course->bool_ghost_hour == TRUE) {
      // This course was given a "ghost hour", meaning it is actually
      // worth 0 hours, not 1, even though it's hours_awarded is currently
      // set to 1.  So, let's just make the display be 0.
      $hours = "0";
    }

    $grade = (string) $course->grade;

    $dispgrade = $grade;
    // If there is a MID, then this is a midterm grade.
    $dispgrade = str_replace("MID","<span class='superscript'>" . t("mid") . "</span>", $dispgrade);

    if (strtoupper($grade) == "E")
    { // Currently enrolled.  Show no grade.
      $dispgrade = "";
    }

    if ($course->bool_hide_grade)
    {
      $dispgrade = "--";
      $this->bool_hiding_grades = true;
    }

    $display_status =  $course->display_status;

    if ($display_status == "completed")
    {
      $pts = $this->get_quality_points($grade, $hours);
    }

    $course_id = $course->course_id;

    $semester_num = $course->assigned_to_semester_num;

    if ($group != NULL && $course->bool_advised_to_take != TRUE) {
      $semester_num = $group->assigned_to_semester_num;
    }



    $render['#semester_num'] = $semester_num;
    $render['#course_id'] = $course_id;

    //$group_id = $course->assigned_to_group_id;
    $group_id = $course->get_first_assigned_to_group_id();
    $hid_group_id = str_replace("_", "U", $group_id); // replace _ with placeholder U so it doesn't mess up submission.
    $random_id = $course->random_id;
    $advised_hours = $course->advised_hours*1;

    $row_random_num = mt_rand(1,99999);
    $render['#row_random_num'] = $row_random_num;

    $unique_id = $course_id . "_" . $semester_num . "_" . $row_random_num;
    $hid_name = "advcr_$course_id" . "_$semester_num" . "_$hid_group_id" . "_$advised_hours" . "_$random_id" . "_$advising_term_id" . "_$degree_id" . "_r" . $row_random_num;

    $render['#row_unique_id'] = $unique_id;
    $render['#row_hid_name'] = $hid_name;
    $render['#row_hid_id'] = "advcr_$unique_id";

    // Add unique_id to the course object for later use.
    $course->unique_id = $unique_id;



    // Due to an interesting bug, the hid_name cannot contain periods.  So, if a course
    // has decimal hours, we need to replace the decimal with a placeholder.
    if (strstr($hid_name, ".")) {
      $hid_name = str_replace(".", "DoT", $hid_name);
    }


    $hid_value = "";
    $opchecked = "";
    if ($course->bool_advised_to_take == true)
    {
      $hid_value = "true";
      $opchecked = "-check";
    }

    $op_on_click_function = "toggleSelection";
    if ($js_toggle_and_save == true)
    {
      $op_on_click_function = "toggleSelectionAndSave";
    }

    $extra_js_vars = "";
    if ($course->display_status == "disabled")
    { // Checkbox needs to be disabled because this was advised in another
      // term.
      $op_on_click_function = "toggleDisabledChangeTerm";
      $course->term_id = $course->advised_term_id;
      $extra_js_vars = $course->get_term_description();

    }

    if ($course->display_status == "completed" || $course->display_status == "enrolled")
    {
      $op_on_click_function = "toggleDisabledCompleted";
      $opchecked = "";
      $extra_js_vars = $course->display_status;
    }

    if ($course->display_status == "retake")
    {
      // this course was probably subbed in while the student
      // was still enrolled, and they have since made an F or W.
      // So, disable it.
      $op_on_click_function = "dummyToggleSelection";
      $opchecked = "";
    }


    if ($this->bool_print || $this->bool_blank)
    {
      // If this is print view, disable clicking.
      $op_on_click_function = "dummyToggleSelection";
    }


    if (!user_has_permission("can_advise_students"))
    {
      // This user does not have the abilty to advise,
      // so take away the ability to toggle anything (like
      // we are in print view).
      $op_on_click_function = "dummyToggleSelection";
    }

    $extra_css = "";
    if ($opchecked == "-check") {
      $extra_css .= " advise-checkbox-$display_status-checked";
    }


    $theme["op"] = array(
      "display_status" => $display_status,
      "extra_css" => $extra_css,
      "unique_id" => $unique_id,
      "onclick" => array(
        "function" => $op_on_click_function,
        "arguments" => array($unique_id, $display_status, $extra_js_vars),
      ),
      "hidden_field" => "<input type='hidden' name='$hid_name' id='advcr_$unique_id' value='$hid_value'>",
    );


    // Okay, we can't actually serialize a course, as it takes too much space.
    // It was slowing down the page load significantly!  So, I am going
    // to use a function I wrote called to_data_string().

    $data_string = $course->to_data_string();
    $blank_degree_id = "";
    if ($this->bool_blank == true)
    {
      $blank_degree_id = $this->degree_plan->degree_id;
    }

    $js_code = "describeCourse(\"$data_string\",\"$blank_degree_id\",\"$subject_id $course_num\");";

    $theme["course"]["js_code"] = $js_code;

    // Assemble theme array elements for the course itself.
    $theme["course"] = array(
      "course" => $course,
      "js_code" => $js_code,
      "subject_id" => $subject_id,
      "course_num" => $course_num,
      "display_status" => $display_status,
      "extra_classes" => $extra_classes,
      "footnote" => $footnote,
      "hours" => $hours,
      "var_hour_icon" => $var_hour_icon,
      "dispgrade" => $dispgrade,
      "grade" => $grade,
      "pts" => $pts,
      "title" => $title_text,
      "group_id" => $group_id,
    );


    $theme['course']['row_unique_id'] = $unique_id;
    $theme['course']['row_hid_name'] = $hid_name;
    $theme['course']['row_hid_id'] = "advcr_$unique_id";



    // If the course has a 'u' in it, it is a 'University Capstone' course.
    if (strstr($course->requirement_type, "u")) {
      $icon_filename = "ucap.gif";
      $title_text = t("This course is a University Capstone.");
    }

    if ($icon_filename != "") {
      //$icon_link = "<img src='" . fp_theme_location() . "/images/icons/$icon_filename' width='19' height='19' border='0' alt='$title_text' title='$title_text'>";

      $theme["icon"] = array();
      $theme["icon"]["filename"] = $icon_filename;
      $theme["icon"]["location"] = fp_theme_location() . "/images/icons";
      $theme["icon"]["title"] = $title_text;

    }


    $on_mouse_over = "
            onmouseover='$(this).addClass(\"selection_highlight\");'
            onmouseout='$(this).removeClass(\"selection_highlight\");'
            ";


    $hand_class = "hand";

    if ($bool_display_check == false) {
      unset($theme["op"]);
    }


    if ($this->bool_print) {
      // In print view, disable all popups and mouseovers.
      $on_mouse_over = "";
      $theme["course"]["js_code"] = "";
      $hand_class = "";
    }



    // Invoke a hook on our theme array, so other modules have a chance to change it up.
    invoke_hook("theme_advise_course_row", array(&$theme));

    $render['#degree_id'] = $degree_id;


    /////////////////////////////////
    // Actually draw out our $theme array now....

    // The checkbox & hidden element....
    $op = $hid = "";
    if (isset($theme["op"]) && count($theme["op"]) > 0) {

      $onclick = "";
      $onclick = $theme["op"]["onclick"]["function"] . "(\"" . join("\",\"", $theme["op"]["onclick"]["arguments"]) . "\")";

      $op = "<span class='advise-checkbox advise-checkbox-{$theme["op"]["display_status"]} {$theme["op"]["extra_css"]}'
                 id='cb_span_{$theme["op"]["unique_id"]}'
                 onClick='$onclick;'></span>";
      $hid = $theme["op"]["hidden_field"];
    }

    // The icon....
    $icon_html = "";
    if (isset($theme["icon"]) && count($theme["icon"]) > 0) {
      $icon_html = "<img class='advising-course-row-icon'
                      src='{$theme["icon"]["location"]}/{$theme["icon"]["filename"]}' alt='{$theme["icon"]["title"]}' title='{$theme["icon"]["title"]}'>";
    }


    ////////////////////////////////////

    // Draw the actual course row...

    $render['start_course_row'] = array(
      'value' => "<tr class='from-render-api'><td colspan='8'>",
      'weight' => 0,
    );


    if ($course->get_bool_substitution_new_from_split() != TRUE || ($course->get_bool_substitution_new_from_split() == TRUE && $course->display_status != "eligible")){

      if ($course_num == ""){
        $course_num = "&nbsp;";
      }

      $js_code = $theme["course"]["js_code"];

      $render['#js_code'] = $js_code;

      $render['course_row_start_table'] = array(
        'value' => "<table border='0' cellpadding='0' cellspacing='0' class='draw-course-row'>",
        'weight' => 100,
      );

      $render['course_row_start_tr'] = array(
        'value' => "<tr class='$hand_class {$theme["course"]["display_status"]} {$theme["course"]["extra_classes"]}'
          $on_mouse_over title='{$theme["course"]["title"]}' >",
        'weight' => 200,
      );

      $render['course_row_td_op_and_hidden'] = array(
        'value' => "<td class='w1_1'>$op$hid</td>",
        'weight' => 300,
      );

      $render['course_row_td_icon_html'] = array(
        'value' => "<td class='w1_2' onClick='$js_code'>$icon_html</td>",
        'weight' => 400,
      );

      $render['course_row_td_ast'] = array(
        'value' => "<td class='w1_3' onClick='$js_code'>&nbsp;$ast</td>",
        'weight' => 500,
      );

      $render['course_row_td_subject_id'] = array(
        'value' => "<td class='underline  w2 '  onClick='$js_code'>
              {$theme["course"]["subject_id"]}</td>",
        'weight' => 600,
      );

      $render['course_row_td_course_num'] = array(
        'value' => "<td class='underline w3' align='left'
                     onClick='$js_code'>
                     {$theme["course"]["course_num"]}{$theme["course"]["footnote"]}</td>",
        'weight' => 700,
      );

      $render['course_row_td_hrs'] = array(
        'value' => "<td class='underline w4' onClick='$js_code'>{$theme["course"]["hours"]}{$theme["course"]["var_hour_icon"]}</td>",
        'weight' => 800,
      );

      $render['course_row_td_grd'] = array(
        'value' => "<td class='underline w5' onClick='$js_code'>{$theme["course"]["dispgrade"]}&nbsp;</td>",
        'weight' => 900,
      );

      $render['course_row_td_pts'] = array(
        'value' => "<td class='underline w6' onClick='$js_code'>{$theme["course"]["pts"]}&nbsp;</td>",
        'weight' => 1000,
      );


      /*
      $pC .= "
      <table border='0' cellpadding='0' width='100%' cellspacing='0' align='left' class='draw-course-row'>
      <tr height='20' class='$hand_class {$theme["course"]["display_status"]} {$theme["course"]["extra_classes"]}'
          $on_mouse_over title='{$theme["course"]["title"]}' >
          <td style='width:$w1_1; white-space:nowrap;' class='w1_1' align='left'>$op$hid</td>

          <td style='width:$w1_2; white-space:nowrap;' align='left'   class='w1_2' onClick='$js_code'>$icon_html</td>
          <td style='width:$w1_3; white-space:nowrap;' align='left'   class='w1_3' onClick='$js_code'>&nbsp;$ast</td>
          <td align='left' style='width:$w2; white-space:nowrap;' class='underline  w2 '  onClick='$js_code'>
              {$theme["course"]["subject_id"]}</td>
          <td class='underline w3' style='width:$w3; white-space:nowrap;' align='left'
                     onClick='$js_code'>
                     {$theme["course"]["course_num"]}{$theme["course"]["footnote"]}</td>
           <td class='underline w4' style='width:$w4; max-width:36px; white-space:nowrap;'  onClick='$js_code'>{$theme["course"]["hours"]}{$theme["course"]["var_hour_icon"]}</td>
           <td class='underline w5'  style='width:$w5; max-width:35px; white-space:nowrap;'  onClick='$js_code'>{$theme["course"]["dispgrade"]}&nbsp;</td>
           <td class='underline w6' style='width:$w6; max-width:31px; white-space:nowrap;' onClick='$js_code'>{$theme["course"]["pts"]}&nbsp;</td>

      </tr>
      </table>";
      */


      $render['course_row_end_row_and_table'] = array(
        'value' => "</tr></table>",
        'weight' => 5000,
      );



    }
    else {
      // These are the leftover hours from a partial substitution.

      $render['#leftover_hours_from_partial_sub'] = TRUE;
      $render['#js_code'] = $js_code;

      $render['course_row_start_table'] = array(
        'value' => "<table border='0' cellpadding='0' width='100%' cellspacing='0' align='left' class='draw-course-row-leftover-hours'>",
        'weight' => 100,
      );

      $render['course_row_start_tr'] = array(
        'value' => "<tr class='hand {$theme["course"]["display_status"]}'
          $on_mouse_over title='{$theme["course"]["title"]}'>",
        'weight' => 200,
      );

      $render['course_row_td_op_and_hidden'] = array(
        'value' => "<td class='w1_1'>$op$hid</td>",
        'weight' => 300,
      );

      $render['course_row_td_icon_html'] = array(
        'value' => "<td class='w1_2' onClick='$js_code'>$icon_html</td>",
        'weight' => 400,
      );

      $render['course_row_td_ast'] = array(
        'value' => "<td class='w1_3' onClick='$js_code'>&nbsp;</td>",
        'weight' => 500,
      );

      $render['course_row_td_leftover_course_details'] = array(
        'value' => "<td class='underline course-part-sub-hrs-left' onClick='$js_code'
            colspan='4'>
              &nbsp; &nbsp; {$theme["course"]["subject_id"]} &nbsp;
              {$theme["course"]["course_num"]}{$theme["course"]["footnote"]}
              &nbsp; ({$theme["course"]["hours"]} " . t("hrs left") . ")
            </td>",
        'weight' => 600,
      );



      $render['course_row_end_row_and_table'] = array(
        'value' => "</tr></table>",
        'weight' => 5000,
      );

    }

    $render['end_course_row'] = array(
      'value' => "</td></tr>",
      'weight' => 9999,
    );

    return fp_render_content($render, FALSE);

    //return $pC;
  }


  /**
   * Calculate the quality points for a grade and hours.
   *
   * This function is very similar to the one in the Course class.
   * It is only slightly different here.  Possibly, the two functions should be
   * merged.
   *
   * @param string $grade
   * @param int $hours
   * @return int
   */
  function get_quality_points($grade, $hours){

    $pts = 0;
    $qpts_grades = array();

    // Let's find out what our quality point grades & values are...
    if (isset($GLOBALS["qpts_grades"])) {
      // have we already cached this?
      $qpts_grades = $GLOBALS["qpts_grades"];
    }
    else {
      $tlines = explode("\n", variable_get_for_school("quality_points_grades", "A ~ 4\nB ~ 3\nC ~ 2\nD ~ 1\nF ~ 0\nI ~ 0", $this->student->school_id));
      foreach ($tlines as $tline) {
        $temp = explode("~", trim($tline));
        if (trim($temp[0]) != "") {
          $qpts_grades[trim($temp[0])] = trim($temp[1]);
        }
      }

      $GLOBALS["qpts_grades"] = $qpts_grades;  // save to cache
    }

    // Okay, find out what the points are by multiplying value * hours...

    if (isset($qpts_grades[$grade])) {
     $pts = $qpts_grades[$grade] * $hours;
    }


    return $pts;

  }


  /**
   * Used in the group selection popup, this will display a course with
   * a radio button next to it, so the user can select it.
   *
   * @param Course $course
   * @param int $group_hours_remaining
   *
   * @return string
   */
  function draw_popup_group_select_course_row(Course $course, $group_hours_remaining = 0)
  {
    // Display a course itself...
    $pC = "";
    $w1_1 = $this->popup_width_array[0];
    $w1_2 = $this->popup_width_array[1];
    $w1_3 = $this->popup_width_array[2];
    $w2 = $this->popup_width_array[3];
    $w3 = $this->popup_width_array[4];
    $w4 = $this->popup_width_array[5];
    $w5 = $this->popup_width_array[6];
    $w6 = $this->popup_width_array[7];

    $title_text = "";
    $icon_html = "";
    $pts = "";


    $theme["icon"] = array();

    $theme = array();
    $theme["screen"] = $this;
    $theme["student"] = $this->student;
    $theme["degree_plan"] = $this->degree_plan;
    $theme["from_group_select"] = TRUE;


    //Setting in Configure School Settings:
    $show_repeat_information = (variable_get_for_school("group_list_course_show_repeat_information", "yes", $course->school_id) == "yes");

    if ($course->subject_id == "")
    {
      // Lacking course's display data, so reload it from the DB.
      $course->load_course($course->course_id);
    }
    $subject_id = $course->subject_id;
    $course_num = $course->course_num;
    $hours = $course->get_catalog_hours();
    $display_status = $course->display_status;
    $db_group_requirement_id = $course->db_group_requirement_id;
    $grade = $course->grade;
    $repeats = $course->specified_repeats;
    if ($repeats > 0 && $show_repeat_information)
    {
      $w3 = "15%";
    }

    $attributes = $course->db_group_attributes;
    $attributes_class = "";
    if ($attributes == "*") {
      $attributes_class .= "group-attr-recommended";
    }
    if ($attributes == "-") {
      $attributes_class .= "group-attr-hidden";
    }


    $course_id = $course->course_id;
    //$group_id = $course->assigned_to_group_id;
    $group_id = $course->get_first_assigned_to_group_id();
    $semester_num = $course->assigned_to_semester_num;
    $req_by_degree_id = $course->req_by_degree_id;

    $min_var_hours = "";
    $var_hour_icon = "&nbsp;";
    if ($course->has_variable_hours() == true)
    {
      $var_hour_icon = "<img src='" . fp_theme_location() . "/images/var_hour.gif'
                title='" . t("This course has variable hours.") . "'
                alt='" . t("This course has variable hours.") . "'>";

      $min_var_hours = $course->min_hours;

      // Does the var hours actually start at zero?
      if ($course->bool_ghost_min_hour) {
        $min_var_hours = 0;
      }
    }


    $checked = "";
    if ($course->bool_selected == true)
    {
      $checked = " checked='checked' ";
    }

    $blank_degree_id = "";
    if ($this->bool_blank)
    {
      $blank_degree_id = $this->degree_plan->degree_id;
    }

    //$serializedCourse = urlencode(serialize($course));
    $js_code = "popupDescribeSelected(\"$group_id\",\"$semester_num\",\"$course_id\",\"$subject_id\",\"req_by_degree_id=$req_by_degree_id&group_hours_remaining=" . filter_untrusted_input($group_hours_remaining) . "&db_group_requirement_id=$db_group_requirement_id&blank_degree_id=$blank_degree_id\");";

    /*
    $on_mouse_over = " onmouseover=\"style.backgroundColor='#FFFF99'\"
              onmouseout=\"style.backgroundColor='white'\" ";
    */

    $on_mouse_over = "
            onmouseover='$(this).addClass(\"selection_highlight\");'
            onmouseout='$(this).removeClass(\"selection_highlight\");'
    ";



    $hand_class = "hand";
    $extra_style = $extra_classes = $extra_css = $extra_html = "";


    // Add the name of the course to the extra-classes
    $extra_classes .= " cr-" . fp_get_machine_readable($course->subject_id . " " . $course->course_num);

    // Check to see if the course is in our required_courses_id_array for more than one degree.
    if (isset($this->degree_plan->required_course_id_array[$course->course_id])) {
      if (count($this->degree_plan->required_course_id_array[$course->course_id]) > 1) {
        // Add a new classname for this course...
        $extra_classes .= " course-appears-in-mult-degrees course-appears-in-" . count($this->degree_plan->required_course_id_array[$course->course_id]) . "-degrees";

      }
    }




   // Assemble theme array elements for the course itself.
    $theme["course"] = array(
      "course" => $course,
      "course_id" => $course->course_id,
      "js_code" => $js_code,
      "subject_id" => $subject_id,
      "course_num" => $course_num,
      "display_status" => $display_status,
      "extra_classes" => $extra_classes,
      "hours" => $hours,
      "var_hour_icon" => $var_hour_icon,
      "grade" => $grade,
      "pts" => $pts,
      "title" => $title_text,
      "extra_html" => $extra_html,
    );



    $op_on_click_function = "adviseSelectCourseFromGroupPopup";

    $theme["op"] = array(
      "display_status" => $display_status,
      "extra_css" => $extra_css,
      "onclick" => array(
        "function" => $op_on_click_function,
        "arguments" => array(""),
      ),
      "checked" => $checked,
      "hidden_field" => "<input type='hidden' name='$course_id" . "_subject'
                            id='$course_id" . "_subject' value='$subject_id'>
                          <input type='hidden' name='$course_id" . "_db_group_requirement_id'
                              id='$course_id" . "_db_group_requirement_id' value='$db_group_requirement_id'>
                          <input type='hidden' name='$course_id" . "_req_by_degree_id' id='$course_id" . "_req_by_degree_id' value='$req_by_degree_id'>
                          <input type='hidden' name='$course_id" . "_min_var_hours' id='$course_id" . "_min_var_hours' value='$min_var_hours'>
                          ",
    );


    $theme["course"]["js_code"] = $js_code;



    // Invoke a hook on our theme array, so other modules have a chance to change it up.
    invoke_hook("theme_advise_course_row", array(&$theme));

    /////////////////////////////////
    // Actually draw out our $theme array now....

    // The checkbox & hidden element....
    $op = $hid = "";
    if (isset($theme["op"]) && count($theme["op"]) > 0) {
      $onclick = "";
      $onclick = $theme["op"]["onclick"]["function"] . "(\"" . join("\",\"", $theme["op"]["onclick"]["arguments"]) . "\")";

      $checked = $theme["op"]["checked"];
      $hid = $theme["op"]["hidden_field"];
      $op = "<input type='radio' name='course' class='cb-course' id='cb-course-$course_id' value='$course_id' $checked onClick='return $onclick;' $extra_css>";

    }

    // The icon....
    $icon_html = "";
    if (isset($theme["icon"]) && count($theme["icon"]) > 0) {

      $icon_html = "<img class='advising-popup-group-select-course-icon'
                      src='{$theme["icon"]["location"]}/{$theme["icon"]["filename"]}' width='14' height='14' border='0' alt='{$theme["icon"]["title"]}' title='{$theme["icon"]["title"]}'>";
    }




    if ($course->bool_unselectable == true)
    {
      // Cannot be selected, so remove that ability!
      $hand_class = "";
      $on_mouse_over = "";
      $js_code = "";
      $op = $op_on_click_function = "";
      $extra_style = "style='font-style: italic; color:gray;'";
    }






    //////////////////////////////////////
    //////////////////////////////////////

    // Actually draw the row's HTML

    //////////////////////////////////////

    $js_code = $theme["course"]["js_code"];

    $pC .= "
      <table border='0' cellpadding='0' width='100%' cellspacing='0' align='left' class='group-course-row $attributes_class'>
      <tr class='$hand_class {$theme["course"]["display_status"]} {$theme["course"]["extra_classes"]}'
          $on_mouse_over title='{$theme["course"]["title"]}'>
          <td width='$w1_1' class='group-w1_1' align='left'>$op$hid<span onClick='$js_code'>$icon_html</span></td>
          <td width='$w1_2' class='group-w1_2' align='left' onClick='$js_code'> </td>
          <td width='$w1_3' class='group-w1_3' align='left' onClick='$js_code'>&nbsp;</td>
          <td align='left' width='$w2' class='  underline group-w2'
              onClick='$js_code' $extra_style>
              {$theme["course"]["subject_id"]}</td>
          <td class='  underline group-w3' $extra_style width='$w3' align='left'
            onClick='$js_code'>
              {$theme["course"]["course_num"]}</td>
          ";
    if ($repeats > 0 && $repeats < 20 && $show_repeat_information)
    {
      $pC .= "
        <td class='  underline group-may-repeat' style='color: gray;'
          onClick='$js_code' colspan='3'>
        <i>" . t("May take up to") . " <span style='color: blue;'>" . ($repeats + 1) . "</span> " . t("times.") . "</i>
        </td>
      ";
    }
    else if ($repeats > 0 && $repeats >= 20 && $show_repeat_information) {
      $pC .= "
        <td class='  underline group-may-repeat' style='color: gray;'
          onClick='$js_code' colspan='3'>
        <i>" . t("May be repeated for credit.") . "</i>
        </td>
      ";
    }
    else if ($theme["course"]["extra_html"] != "") {
      $pC .= "
        <td class='  underline' class='group-w4' width='$w4' onClick='$js_code' $extra_style>{$theme["course"]["hours"]} {$theme["course"]["var_hour_icon"]}</td>
        <td class='  underline group-course-extra-html'
          onClick='$js_code' colspan='10'>
          {$theme["course"]["extra_html"]}
        </td>
      ";
    }
    else {

      $pC .= "
         <td class='  underline' class='group-w4' width='$w4' onClick='$js_code' $extra_style>{$theme["course"]["hours"]} {$theme["course"]["var_hour_icon"]}</td>
           <td class='  underline' class='group-w5' width='$w5' onClick='$js_code'>{$theme["course"]["grade"]}&nbsp;</td>
           <td class='  underline' class='group-w6' width='$w6' onClick='$js_code'>{$theme["course"]["pts"]}&nbsp;</td>
           ";
    }

    $pC .= "
      </tr>
      </table>";


    return $pC;
  }



  /**
   * This is used to display the substitution popup to a user, to let them
   * actually make a substitution.
   *
   * @param int $course_id
   * @param string $group_id
   * @param int $semester_num
   * @param string $hours_avail
   * @param int $req_by_degree_id
   *
   * @return string
   */
  function display_popup_substitute($course_id = 0, $group_id = '', $semester_num = 0, $hours_avail = "", $req_by_degree_id = 0)
  {
    global $current_student_id;
    // This lets the user make a substitution for a course.
    $pC = "";

    $school_id = db_get_school_id_for_student_id($current_student_id);

    // Bring in advise's css...
    fp_add_css(fp_get_module_path("advise") . "/css/advise.css");


    $course = new Course($course_id);
    $bool_sub_add = false;

    $req_degree_plan = new DegreePlan();
    $req_degree_plan->degree_id = $req_by_degree_id;
    if ($req_by_degree_id > 0) {
      $course->req_by_degree_id = $req_by_degree_id;
      $req_degree_plan->load_descriptive_data();
    }

    $c_title = t("Substitute for") . " $course->subject_id $course->course_num";
    if ($course_id == 0)
    {
      $c_title = t("Substitute an additional course");
      $bool_sub_add = true;
    }
    $pC .= fp_render_section_title($c_title);

    if ($req_by_degree_id > 0) {
      $pC .= "<div class='  sub-req-by-degree-title-line'>" . t("This substitution will only affect the <b>%title</b> degree requirements.", array("%title" => $req_degree_plan->get_title2())) . "
              </div>";
    }

    $extra = ".<input type='checkbox' id='cbAddition' value='true' style='display:none;'>";
    if ($group_id > 0)
    {
      $new_group = new Group($group_id);
      $checked = "";
      if ($bool_sub_add == true){$checked = "checked disabled";}
      $extra = " " . t("in the group %newg.", array("%newg" => $new_group->title)) . "
      " . t("Addition only:") . " <input type='checkbox' id='cbAddition' value='true' $checked>
         <a href='javascript: alertSubAddition();'>?</a>";
    }

    $c_hours = $course->max_hours*1;
    $c_ghost_hour = "";
    if ($course->bool_ghost_hour == TRUE) {
      $c_ghost_hour = t("ghost") . "<a href='javascript: alertSubGhost();'>?</a>";
    }

    if ((floatval($hours_avail) * 1 > 0 && $hours_avail < $c_hours) || ($c_hours <= 0))
    {

      // Use the remaining hours if we have fewer hours left in
      // the group than the course we are subbing for.
      $c_hours = $hours_avail;
    }

    if ($hours_avail == "" || floatval($hours_avail) * 1 <= 0)
    {
      $hours_avail = $c_hours;
    }

    $pC .= "<div class=' '>
          " . t("Please select a course to substitute
        for %course", array("%course" => "$course->subject_id $course->course_num ($c_hours $c_ghost_hour " . t("hrs") . ")")) . "$extra
        </div>
        ";


    // If this course has ghost hours, and if we've set that you can only sub ghost hours
    // for other ghost hours, then display a message here explaining that.
    $bool_ghost_for_ghost = (variable_get("restrict_ghost_subs_to_ghost_hours", "yes") == "yes" && $course->bool_ghost_hour);

    if ($bool_ghost_for_ghost) {
      $pC .= "<div class=' '>" . t("<b>Note:</b> As per a setting in FlightPath, the only courses which
                may be substituted must be worth zero hours (1 ghost hour).") . "</div>";
    }



    $pC .= "
        <div class=' '
          style='height: 175px; overflow: auto; border:1px inset black; padding: 5px;'>
          <table border='0' cellpadding='0' cellspacing='0' width='100%'>

          ";

    $this->student->list_courses_taken->sort_alphabetical_order(false, true, FALSE, $req_by_degree_id);

    $school_id = db_get_school_id_for_student_id($this->student->student_id);

    for ($t = 0; $t <= 1; $t++)
    {
      if ($t == 0) {$the_title = variable_get_for_school("school_initials", "DEMO", $school_id) . " " . t("Credits"); $bool_transferTest = true;}
      if ($t == 1) {$the_title = t("Transfer Credits"); $bool_transferTest = false;}

      $pC .= "<tr><td colspan='3' valign='top' class=' ' style='padding-bottom: 10px;'>
        $the_title
        </td>
        <td class=' ' valign='top' >" . t("Hrs") . "</td>
        <td class=' ' valign='top' >" . t("Grd") . "</td>
        <td class=' ' valign='top' >" . t("Term") . "</td>
        </tr>";

      $already_seen = array(); // keep track of the courses we've already seen.
      $used_hours_subs = array(); // extra help keeping up with how many hours we've used for particular courses in split up subs.

      $is_empty = true;
      $this->student->list_courses_taken->reset_counter();
      while($this->student->list_courses_taken->has_more())
      {
        $c = $this->student->list_courses_taken->get_next();

        if ($c->bool_transfer == $bool_transferTest)
        {
          continue;
        }


        if (!$c->meets_min_grade_requirement_of(null, variable_get_for_school("minimum_substitutable_grade", "D", $school_id)))
        {// Make sure the grade is OK.
          continue;
        }

        $bool_disable_selection = $disabled_msg = FALSE;

        // Should we skip this course, because of a ghost_for_ghost requirement?
        if ($bool_ghost_for_ghost && !$c->bool_ghost_hour) {
          continue;
        }
        // If we are supposed to restrict ghost for ghost, but the course does NOT
        // have a ghost hour, and this $c course does, then disable it
        if (variable_get_for_school("restrict_ghost_subs_to_ghost_hours", "yes", $school_id) == "yes"
            && $course->bool_ghost_hour != TRUE
            && $c->bool_ghost_hour == TRUE) {

          $bool_disable_selection = TRUE;
          $disabled_msg = t("Substitution of this course has been disabled.
                             As per a setting in FlightPath, courses worth zero hours (1 ghost hour)
                             may only be substituted for course requirements also worth zero hours.");

        }



        $t_flag = 0;
        if ($c->bool_transfer == true)
        {
          $t_flag = 1;
        }
        $is_empty = false;

        $subject_id = $c->subject_id;
        $course_num = $c->course_num;
        $tcourse_id = $c->course_id;

        if ($bool_transferTest == false)
        {
          // Meaning, we are looking at transfers now.
          // Does the transfer course have an eqv set up?  If so,
          // we want *that* course to appear.
          if (is_object($c->course_transfer))
          {
            $subject_id = $c->course_transfer->subject_id;
            $course_num = $c->course_transfer->course_num;
            $tcourse_id = $c->course_transfer->course_id;
            $t_flag = 1;
          }
        }


        $m_hours = $c->get_hours_awarded($req_by_degree_id);

        /*
         *
         * We don't want to do this. What it's saying is if the max_hours (from the course database)
         * is LESS than the awarded hours, use the lower hours.  Instead, we want to always use
         * what the student was AWARDED.
         *
        if ($c->max_hours*1 < $m_hours)
        {
          $m_hours = $c->max_hours*1;

        }
        */

        if (($hours_avail*1 > 0 && $hours_avail < $m_hours) || ($m_hours == 0))
        {
          $m_hours = $hours_avail;
        }

        // is max_hours more than the original course's hours?
        if ($m_hours > $c_hours)
        {
          $m_hours = $c_hours;
        }

        if ($m_hours > $c->get_hours_awarded($req_by_degree_id))
        {
          $m_hours = $c->get_hours_awarded($req_by_degree_id);
        }

        // If we have already displayed this EXACT course, then we shouldn't display it again.  This is to
        // fix a multi-degree bug, where we see the same course however many times it was split for a DIFFERENT degree.
        // If it's never been split for THIS degree, it should just show up as 1 course.
        $ukey = md5($c->course_id . $c->catalog_year . $c->term_id . $m_hours . $tcourse_id . intval(@$c->db_substitution_id_array[$req_by_degree_id]));
        if (isset($already_seen[$ukey])) {
          continue;
        }
        // Else, add it.
        $already_seen[$ukey] = TRUE;

        // We should also keep up with how many hours have been used by this sub...

        // Is this course NOT a substitution for this degree, and NOT an outdated sub?
        // In other words, are we safe to just display this course as an option for selection?
        if ($c->get_bool_substitution($req_by_degree_id) != TRUE && $c->get_bool_outdated_sub($req_by_degree_id) != TRUE)
        {


          $h = $c->get_hours_awarded($req_by_degree_id);
          if ($c->bool_ghost_hour == TRUE) {
            $h .= "(ghost<a href='javascript: alertSubGhost();'>?</a>)";
          }

          // If this course was split up, we need to use our
          // helper array to see what the OTHER, already-used pieces add up to.
          if ($c->get_bool_substitution_split($req_by_degree_id)) {

            $ukey = md5($c->course_id . $c->catalog_year . $c->term_id . $tcourse_id);
            if (isset($used_hours_subs[$ukey])) {
              $used_hours = $used_hours_subs[$ukey];
              // Get the remaining hours by subtracting the ORIGINAL hours for this course against
              // the used hours.

              // Okay, I believe this is a bug.  Somewhere, I have set the hours_awarded to the remaining hours, so
              // this math is no longer needed.
              /*
              $remaining_hours = $c->get_hours_awarded(0) - $used_hours;  // (0) gets the original hours awarded.
              if ($remaining_hours > 0) {
                $h = $remaining_hours;

              }*/

            }

          }




          $pC .= "<tr>
            <td valign='top' class=' ' width='15%'>
              <input type='radio' name='subCourse' id='subCourse' value='$tcourse_id'
               onClick='popupUpdateSubData(\"$m_hours\",\"$c->term_id\",\"$t_flag\",\"$hours_avail\",\"" . $c->get_hours_awarded($req_by_degree_id) . "\");'
               ";
          if ($bool_disable_selection) $pC .= "disabled=disabled";

          $pC .= "   >";

          if ($disabled_msg) {
            $pC .= fp_get_js_alert_link(fp_reduce_whitespace(str_replace("\n", " ", $disabled_msg)), "?");
          }

          $pC .= "
            </td>
            <td valign='top' class='  underline' width='13%'>
              $subject_id
            </td>
            <td valign='top' class='  underline' width='15%'>
              $course_num
            </td>


            <td valign='top' class='  underline' width='10%'>
              $h
            </td>
            <td valign='top' class='  underline' width='10%'>
              $c->grade
            </td>
            <td valign='top' class='  underline'>
              " . $c->get_term_description(true) . "
            </td>


          </tr>
          ";
        }
        else {

          // Does this course have a substitution for THIS degree?
          if (!is_object($c->get_course_substitution($req_by_degree_id))) {
            continue;
          }

          if (is_object($c->get_course_substitution($req_by_degree_id)) && $c->get_course_substitution($req_by_degree_id)->subject_id == "")
          { // Load subject_id and course_num of the original
            // requirement.
            $c->get_course_substitution($req_by_degree_id)->load_descriptive_data();
          }
          $extra = "";
          //if ($c->assigned_to_group_id > 0)
          if ($c->get_bool_assigned_to_group_id(-1))
          {
            // TODO:  based on degree (hint: probably so...?
            $new_group = new Group($c->get_first_assigned_to_group_id());
            $extra = " in $new_group->title";
          }
          if ($c->get_bool_outdated_sub($req_by_degree_id))
          {
            $help_link = fp_get_js_alert_link(t("This substitution is outdated. It was made for a course or group which does not currently appear on the student's degree plan.  You may remove this sub using the Administrator's Toolbox, at the bottom of the View tab."), "?");
            $extra .= " <span style='color:red;'>[" . t("Outdated") . "$help_link]</span>";
          }

          // It has already been substituted!
          $pC .= "<tr style='background-color: beige;'>
            <td valign='top' class=' ' width='15%'>
             " . t("Sub:") . "
            </td>
            <td valign='top' class=' ' colspan='5'>
              $subject_id

              $course_num (" . $c->get_substitution_hours($req_by_degree_id) . ")
               -> " . $c->get_course_substitution($req_by_degree_id)->subject_id . "
               " . $c->get_course_substitution($req_by_degree_id)->course_num . "$extra
            </td>


          </tr>
          ";

          // Keep track of how many hours THIS course has been subbed, if it was split.
          $ukey = md5($c->course_id . $c->catalog_year . $c->term_id . $tcourse_id);

          if (!isset($used_hours_subs[$ukey])) $used_hours_subs[$ukey] = 0;
          $used_hours_subs[$ukey] += $c->get_substitution_hours($req_by_degree_id);

        }

        // If this was a transfer course, have an extra line under the course, stating it's title.
        if ($bool_transferTest == FALSE) {  // Means this IS INDEED a transfer courses.

          $c->course_transfer->load_descriptive_transfer_data($this->student->student_id, $c->term_id);
          $pC .= "<tr class='advise-substitute-popup-transfer-course-title'>
                    <td colspan='8'>
                      {$c->course_transfer->title} ({$c->course_transfer->institution_name})
                    </td>
                  </tr>";

        }



      } // while list_courses_taken

      if ($is_empty == true)
      {
        // Meaning, there were no credits (may be the case with
        // transfer credits)
        $pC .= "<tr><td colspan='8' class=' '>
              - " . t("No substitutable credits available.") . "
            </td></tr>";
      }

      $pC .= "<tr><td colspan='4'>&nbsp;</td></tr>";
    }


    $pC .= "</table></div>
    <div class=' ' style='margin-top: 5px;'>
      " . t("Select number of hrs to use:") . "
      <select name='subHours' id='subHours' onChange='popupOnChangeSubHours()'>
        <option value=''>" . t("None Selected") . "</option>
      </select>
      ";

    // If we have entered manual hours (like for decimals), they go here:
    // The subManual span will *display* them, the hidden field keeps them so they can be transmitted.
    $pC .= "
      <span id='subManual' style='font-style:italic; display:none;'></span>
      <input type='hidden' id='subManualHours' value=''>


    </div>
    <input type='hidden' name='subTransferFlag' id='subTransferFlag' value=''>
    <input type='hidden' name='subTermID' id='subTermID' value=''>
    <input type='button' value='Save Substitution' onClick='popupSaveSubstitution(\"$course_id\",\"$group_id\",\"$semester_num\",\"$req_by_degree_id\");'>

    <div class=' ' style='padding-top: 5px;'><b>" . t("Optional") . "</b> - " . t("Enter remarks:") . "
    <input type='text' name='subRemarks' id='subRemarks' value='' size='30' maxlength='254'>

    </div>
    ";


    return $pC;
  }



  /**
   * This function displays the popup which lets a user select a course to be
   * advised into a group.
   *
   * @param Group $place_group
   * @param int $group_hours_remaining
   * @return string|false
   */
  function display_popup_group_select(Group $place_group, $group_hours_remaining = 0, $req_by_degree_id = 0)
  {
    $pC = "";

    $advising_term_id = $GLOBALS["fp_advising"]["advising_term_id"];
    if ($req_by_degree_id == 0) {
      $req_by_degree_id = $place_group->req_by_degree_id;
    }



    $bool_no_courses = FALSE;

    if ($place_group->group_id != DegreePlan::GROUP_ID_FOR_COURSES_ADDED)
    {
      // This is NOT the Add a Course group.

      if (!$group = $this->degree_plan->find_group($place_group->group_id))
      {
        fpm("Group not found.");
        return FALSE;
      }
      else {
        // Found the group... we don't need to do anything.
      }
    }
    else {
      // This is the Add a Course group.
      $group = $place_group;

    }

    $group_id = $group->group_id;

    // So now we have a group object, $group, which is most likely
    // missing courses.  This is because when we loaded & cached it
    // earlier, we did not load any course which wasn't a "significant course,"
    // meaning, the student didn't have credit for it or the like.
    // So what we need to do now is reload the group, being careful
    // to preserve the existing courses / sub groups in the group.

    $group->reload_missing_courses();

    if ($group_hours_remaining == 0)
    {
      // Attempt to figure out the remaining hours (NOT WORKING IN ALL CASES!)
      // This specifically messes up when trying to get fulfilled hours in groups
      // with branches.
      $group_fulfilled_hours = $group->get_fulfilled_hours(true, true, false, $place_group->assigned_to_semester_num);
      $group_hours_remaining = $place_group->hours_required - $group_fulfilled_hours;

    }


    $display_semesterNum = $place_group->assigned_to_semester_num;
    $pC .= "<!--MSG--><!--MSG2--><!--BOXTOP-->";
    $bool_display_submit = true;
    $bool_display_back_to_subject_select = false;
    $bool_subject_select = false;
    $bool_unselectableCourses = false;
    $final_course_list = new CourseList();

    $public_note = fp_trim($group->public_note);
    if ($public_note) {
      $pC .= "<tr><td colspan='8'><div class='group-public-note'>" . $public_note . "</div></td></tr>";
    }


    $group_sort_policy = variable_get_for_school("group_requirement_sort_policy", "alpha", $this->student->school_id);

    $group->list_courses->reset_counter();
    if (!($group->list_courses->is_empty))
    {

      $group->list_courses->assign_semester_num($display_semesterNum);

      $new_course_list = $group->list_courses;
      // Is this list so long that we first need to ask the user to
      // select a subject?
      if ($new_course_list->get_size() > 30)
      {

        // First, we are only going to do this if there are more
        // than 30 courses, AND more than 2 subjects in the list.
        $new_course_list->sort_alphabetical_order();
        $subject_array = $new_course_list->get_course_subjects();
        //print_pre($new_course_list->to_string());
        //var_dump($subject_array);
        if (count($subject_array) > 2)
        {
          // First, check to see if the user has already
          // selected a subject.
          $selected_subject = addslashes(fp_trim(@$_GET["selected_subject"]));
          if ($selected_subject == "")
          {
            // Prompt them to select a subject first.
            $pC .= $this->draw_popup_group_subject_select($subject_array, $group->group_id, $display_semesterNum, $group_hours_remaining, $req_by_degree_id);
            $new_course_list = new CourseList(); // empty it
            $bool_display_submit = false;
            $bool_subject_select = true;
          } else {
            // Reduce the newCourseList to only contain the
            // subjects selected.
            $new_course_list->exclude_all_subjects_except($selected_subject);
            $bool_display_back_to_subject_select = true;
          }
        }
      }


      if ($group_sort_policy == 'database') {
        $new_course_list->sort_group_requirement_id();
      }
      else {
        // By default, sort alphabetical
        $new_course_list->sort_alphabetical_order();
      }


      $new_course_list->reset_counter();


      $final_course_list->add_list($new_course_list);
    }


    if (!($group->list_groups->is_empty))
    {
      // Basically, this means that this group
      // has multiple subgroups.  We need to find out
      // which branches the student may select from
      // (based on what they have already taken, or been
      // advised to take), and display it (excluding duplicates).
      //print_pre($group->to_string());
      // The first thing we need to do, is find the subgroup
      // or subgroups with the most # of matches.
      $new_course_list = new CourseList();
      $all_zero= true;


      // Okay, this is a little squirely.  What I need to do
      // first is get a course list of all the courses which
      // are currently either fulfilling or advised for all branches
      // of this group.
      $fa_course_list = new CourseList();
      $group->list_groups->reset_counter();
      while($group->list_groups->has_more())
      {
        $branch = $group->list_groups->get_next();
        $fa_course_list->add_list($branch->list_courses->get_fulfilled_or_advised(true));
      }
      $fa_course_list->remove_duplicates();
      //print_pre($fa_course_list->to_string());
      // Alright, now we create a fake student and set their
      // list_courses_taken, so that we can use this student
      // to recalculate the count_of_matches in just a moment.
      $new_student = new Student();
      $new_student->load_student();
      $new_student->list_courses_taken = $fa_course_list;
      $new_student->load_significant_courses_from_list_courses_taken();

      // Okay, now we need to go through and re-calculate our
      // count_of_matches for each branch.  This is because we
      // have cached this value, and after some advisings, it may
      // not be true any longer.

      $highest_match_count = 0;
      $group->list_groups->reset_counter();
      while($group->list_groups->has_more())
      {
        $branch = $group->list_groups->get_next();
        // recalculate count_of_matches here.
        $clone_branch = new Group();
        $clone_branch->list_courses = $branch->list_courses->get_clone(true);
        $matches_count = $this->flightpath->get_count_of_matches($clone_branch, $new_student, $group);
        $branch->count_of_matches = $matches_count;
        if ($matches_count >= $highest_match_count)
        { // Has more than one match on this branch.

          $highest_match_count = $matches_count;
        }
      }

      // If highestMatchCount > 0, then get all the branches
      // which have that same match count.
      if ($highest_match_count > 0)
      {

        $group->list_groups->reset_counter();
        while($group->list_groups->has_more())
        {
          $branch = $group->list_groups->get_next();

          if ($branch->count_of_matches == $highest_match_count)
          { // This branch has the right number of matches.  Add it.

            $new_course_list->add_list($branch->list_courses);
            $all_zero = false;
          }

        }

      }

      if ($all_zero == true)
      {
        // Meaning, all of the branches had 0 matches,
        // so we should add all the branches to the
        // newCourseList.

        $group->list_groups->reset_counter();
        while($group->list_groups->has_more())
        {
          $branch = $group->list_groups->get_next();
          $new_course_list->add_list($branch->list_courses);
        }
      } else {
        // Meaning that at at least one branch is favored.
        // This also means that a user's course
        // selections have been restricted as a result.
        // Replace the MSG at the top saying so.
        $msg = "<div class=' '>" . t("Your selection of courses has been
              restricted based on previous course selections.") . "</div>";
        $pC = str_replace("<!--MSG-->", $msg, $pC);
      }

      // Okay, in the newCourseList object, we should
      // now have a list of all the courses the student is
      // allowed to take, but there are probably duplicates.
      //print_pre($new_course_list->to_string());


      $new_course_list->remove_duplicates();
      $new_course_list->assign_group_id($group->group_id);
      $new_course_list->assign_semester_num($display_semesterNum);

      $final_course_list->add_list($new_course_list);

    }


    // Remove courses which have been marked as "exclude" in the database.
    $final_course_list->remove_excluded();

    $final_course_list->assign_group_id($group->group_id);  // make sure everyone is in THIS group.
    //print_pre($final_course_list->to_string());



    // Here's a fun one:  We need to remove courses for which the student
    // already has credit that *don't* have repeating hours.
    // For example, if a student took MATH 113, and it fills in to
    // Core Math, then we should not see it as a choice for advising
    // in Free Electives (or any other group except Add a Course).
    // We also should not see it in other instances of Core Math.
    if ($group->group_id != DegreePlan::SEMESTER_NUM_FOR_COURSES_ADDED && $this->bool_blank != TRUE)
    {

      // Only do this if NOT in Add a Course group...
      // also, don't do it if we're looking at a "blank" degree.
      $final_course_list->remove_previously_fulfilled($this->student->list_courses_taken, $group->group_id, true, $this->student->list_substitutions, $req_by_degree_id);

    }


    if ($group_sort_policy == 'database') {
      $final_course_list->sort_group_requirement_id();
    }
    else {
      // By default, sort alphabetical
      $final_course_list->sort_alphabetical_order();
    }



    // flag any courses with more hours than are available for this group.
    if ($final_course_list->assign_unselectable_courses_with_hours_greater_than($group_hours_remaining))
    {

      $bool_unselectableCourses = true;
    }

    // Make sure all the courses in our final list have the same req_by_degree_id.
    $final_course_list->set_req_by_degree_id($req_by_degree_id);


    $pC .= $this->display_popup_group_select_course_list($final_course_list, $group_hours_remaining);

    // If there were no courses in the finalCourseList, display a message.
    if (count($final_course_list->array_list) < 1 && !$bool_subject_select)
    {
      $pC .= "<tr>
          <td colspan='8'>
            <div class=' '>
            <b>Please Note:</b>
            " . t("FlightPath could not find any eligible
            courses to display for this list.  Ask your advisor
            if you have completed courses, or may enroll in
            courses, which can be
            displayed here.");

      if (user_has_permission("can_advise_students")){
        // This is an advisor, so put in a little more
        // information.
        $pC .= "
                  <div class=' ' style='padding-top: 5px;'><b>" . t("Special note to advisors:") . "</b> " . t("You may still
                      advise a student to take a course, even if it is unselectable
                      in this list.  Use the \"add an additional course\" link at
                      the bottom of the page.") . "</div>";
      }
      $pC .= "            </div>
          </td>
          </tr>";
      $bool_no_courses = true;
    }

    $pC .= $this->draw_semester_box_bottom();

    $s = "s";
    //print_pre($place_group->to_string());

    $unselectable_notice = "";

    if ($group_hours_remaining == 1){$s = "";}
    if ($bool_unselectableCourses == true) {
      $unselectable_notice = " <div class=' '><i>(" . t("Courses worth more than %hrs hour$s
                may not be selected.", array("%hrs" => $group_hours_remaining)) . ")</i></div>";
      if (user_has_permission("can_advise_students")) {
        // This is an advisor, so put in a little more
        // information.
        $unselectable_notice .= "
                  <div class=' ' style='padding-top: 5px;'><b>" . t("Special note to advisors:") . "</b> " . t("You may still
                      advise a student to take a course, even if it is unselectable
                      in this list.  Use the \"add an additional course\" link at
                      the bottom of the page.") . "</div>";
      }
    }

    if ($group_hours_remaining < 200 && $bool_no_courses != true) {
      $disp_group_hours_remaining = $group_hours_remaining;
      // If we have min_hours, display that information.
      if ($place_group->has_min_hours_allowed()) {
        // Make sure the "real" group has the same min hours set.
        $group->min_hours_allowed = $place_group->min_hours_allowed;
      }

      if ($group->has_min_hours_allowed()) {

        $g_fulfilled_hours = $group->hours_required - $group_hours_remaining;  // How many have we actually used?

        $d_min_hours = $group->min_hours_allowed - $g_fulfilled_hours;  // min hours must be reduced by the number already assigned
        $disp_group_hours_remaining = $d_min_hours . "-" . $group_hours_remaining;
      }

      // Don't show for huge groups (like add-a-course)
      $pC .= "<div class=' ' style='margin-top:5px;'>
          " . t("You may select <b>@hrs</b>
            hour$s from this list.", array("@hrs" => $disp_group_hours_remaining)) . "$unselectable_notice</div>";
    }


   ////////////////////////////////
    // TODO:  Conditions on which this will even appear?  Like only if the student has more than one degree selected?
    // What degrees is this group req by?

    $t_degree_plan = new DegreePlan();
    $t_degree_plan->degree_id = $req_by_degree_id;
    $t = $t_degree_plan->get_title2(FALSE, TRUE);
    if (trim($t) != "") {

      $pC .= "<div class='  group-select-req-by-degree'>
                " . t("This group is required by ");
      $html = "";
      $html .= "<span class='group-req-by-degree-title'>" . $t . "</span>";

      $pC .= "$html</div>";

    }



    /////////////////////////////

    if ($bool_display_submit == true && !$this->bool_blank && $bool_no_courses != true)
    {
      if (user_has_permission("can_advise_students")) {
        $pC .= "<input type='hidden' name='varHours' id='varHours' value=''>
          <div style='margin-top: 20px;'>


        " . fp_render_button(t("Select Course"), "popupAssignSelectedCourseToGroup(\"$place_group->assigned_to_semester_num\", \"$group->group_id\",\"$advising_term_id\",\"-1\");") . "
          </div>
        ";
      }

    }

    // Substitutors get extra information:
    if (user_has_permission("can_substitute") && $group->group_id != DegreePlan::GROUP_ID_FOR_COURSES_ADDED)
    {
      $pC .= "<div class=' ' style='margin-top: 20px;'>
          <b>" . t("Special administrative information:") . "</b>

        <span id='viewinfolink'
        onClick='document.getElementById(\"admin_info\").style.display=\"\"; this.style.display=\"none\"; '
        class='hand' style='color: blue;'
        > - " . t("Click to show") . " -</span>

          <div style='padding-left: 20px; display:none;' id='admin_info'>
          " . t("Information about this group:") . "<br>
          &nbsp; " . t("Group ID:") . " $group->group_id<br>
          &nbsp; " . t("Title:") . " $group->title<br>";
      $pC .= "&nbsp; <i>" . t("Internal name:") . " $group->group_name</i><br>";

      $pC .= "&nbsp; " . t("Catalog year:") . " $group->catalog_year
          </div>

          </div>";
    }


    if ($bool_display_back_to_subject_select == true) {
      $csid = $GLOBALS["current_student_id"];
      $blank_degree_id = "";
      if ($this->bool_blank)
      {
        $blank_degree_id = $this->degree_plan->degree_id;
      }
      $back_link = "<span class=' '>
            <a href='" . fp_url("advise/popup-group-select", "window_mode=popup&group_id=$group->group_id&semester_num=$display_semesterNum&group_hours_remaining=" . filter_untrusted_input($group_hours_remaining) . "&current_student_id=$csid&blank_degree_id=$blank_degree_id") . "'
            class='nounderline'>&laquo; " . t("return to subject selection") . "</a></span>";
      $pC = str_replace("<!--MSG2-->",$back_link,$pC);
    }

    $box_top = $this->draw_semester_box_top("$group->title", !$bool_display_submit);
    $pC = str_replace("<!--BOXTOP-->",$box_top,$pC);



    watchdog("advise", "popup_group_select id:$group->group_id, name:$group->group_name, semester_num:$display_semesterNum", array(), WATCHDOG_DEBUG);



    return $pC;
  }


  /**
   * When the groupSelect has too many courses, they are broken down into
   * subjects, and the user first selects a subject.  This function will
   * draw out that select list.
   *
   * @param array $subject_array
   * @param int $group_id
   * @param int $semester_num
   * @param int $group_hours_remaining
   * @return string
   */
  function draw_popup_group_subject_select($subject_array, $group_id, $semester_num, $group_hours_remaining = 0, $req_by_degree_id = 0)
  {
    $csid = $GLOBALS["current_student_id"];
    $blank_degree_id = "";
    if ($this->bool_blank)
    {
      $blank_degree_id = $this->degree_plan->degree_id;
    }

    $db = get_global_database_handler();
    $school_id = db_get_school_id_for_student_id($this->student->student_id);

    $pC = "";

    $clean_urls = variable_get("clean_urls", FALSE);

    $pC .= "<tr><td colspan='8' class=' '>";
    $pC .= "<form action='" . fp_url("advise/popup-group-select") . "' method='GET' style='margin:0px; padding:0px;' id='theform'>
          <input type='hidden' name='window_mode' value='popup'>
          <input type='hidden' name='group_id' value='$group_id'>";
    if (!$clean_urls) {
      // Hack so that non-clean URLs sites still work
      $pC .= "<input type='hidden' name='q' value='advise/popup-group-select'>";
    }
    $pC .= "
          <input type='hidden' name='semester_num' value='$semester_num'>
          <input type='hidden' name='group_hours_remaining' value='$group_hours_remaining'>
          <input type='hidden' name='current_student_id' value='$csid'>
          <input type='hidden' name='blank_degree_id' value='$blank_degree_id'>
          <input type='hidden' name='req_by_degree_id' value='$req_by_degree_id'>

          " . t("Please begin by selecting a subject from the list below.") . "
          <br><br>
          <select name='selected_subject'>
          <option value=''>" . t("Please select a subject...") . "</option>
          <option value=''>----------------------------------------</option>
          ";
    $new_array = array();
    foreach($subject_array as $key => $subject_id)
    {

      if ($title = $this->flightpath->get_subject_title($subject_id, $school_id)) {
        $new_array[] = "$title ~~ $subject_id";
      } else {
        $new_array[] = "$subject_id ~~ $subject_id";
      }

    }

    sort($new_array);

    foreach ($new_array as $key => $value)
    {
      $temp = explode(" ~~ ",$value);
      $title = trim($temp[0]);
      $subject_id = trim($temp[1]);
      $pC .= "<option value='$subject_id'>$title ($subject_id)</option>";
    }

    $pC .= "</select>
        <div style='margin: 20px;' align='left'>
        " . fp_render_button(t("Next") . " ->","document.getElementById(\"theform\").submit();") . "
        </div>
          <!-- <input type='submit' value='submit'> -->

              </form>
        ";
    $pC .= "</td></tr>";

    return $pC;
  }


  /**
   * Accepts a CourseList object and draws it out to the screen. Meant to
   * be called by display_popup_group_select();
   *
   * @param CourseList $course_list
   * @param int $group_hours_remaining
   * @return string|false
   */
  function display_popup_group_select_course_list(CourseList $course_list = null, $group_hours_remaining = 0)
  {
    // Accepts a CourseList object and draws it out to the screen.  Meant to
    // be called by display_popup_group_select().
    $rtn = "";

    if ($course_list == null) {
      return FALSE;
    }

    $old_course = null;
    $bool_has_recommended = FALSE;
    $course_list->reset_counter();
    while($course_list->has_more())
    {
      $course = $course_list->get_next();
      if ($course->equals($old_course))
      { // don't display the same course twice in a row.
        continue;
      }

      if ($course->db_group_attributes == "*") {
        $bool_has_recommended = TRUE;
      }

      $rtn .= "<tr><td colspan='8'>";

      // Only display this course for advising IF it hasn't been fulfilled, or if it has infinite repeats, and only if it isn't already
      // advised to be taken.
      if (($course->course_list_fulfilled_by->is_empty || $course->specified_repeats == Group::GROUP_COURSE_INFINITE_REPEATS) && !$course->bool_advised_to_take ){
        // So, only display if it has not been fulfilled by anything.

        $rtn .= $this->draw_popup_group_select_course_row($course, $group_hours_remaining);
        $old_course = $course;
      }
      $rtn .= "</td></tr>";
    }


    if ($bool_has_recommended) {
      $rtn .= "<tr><td colspan='8'><span class='group-recommended-message'>" . t("<b>Note:</b> Courses in <strong>bold</strong> are recommended.") . "</span></td></tr>";
    }


    return $rtn;
  }




  /**
   * Returns a list of "hidden" HTML input tags which are used to keep
   * track of advising variables between page loads.
   *
   * @param string $perform_action
   *       - Used for when we submit the form, so that FlightPath will
   *         know what action we are trying to take.
   *
   * @return string
   */
  function get_hidden_advising_variables($perform_action = "")
  {

    $school_id = db_get_school_id_for_student_id($GLOBALS["fp_advising"]["current_student_id"]);


    $rtn = "";

    if (!isset($GLOBALS["print_view"])) $GLOBALS["print_view"] = "";

    $rtn .= "<span id='hidden_elements'>

      <input type='hidden' name='perform_action' id='perform_action' value='$perform_action'>
      <input type='hidden' name='perform_action2' id='perform_action2' value=''>
      <input type='hidden' name='scroll_top' id='scroll_top' value=''>
      <input type='hidden' name='load_from_cache' id='load_from_cache' value='yes'>
      <input type='hidden' name='print_view' id='print_view' value='{$GLOBALS["print_view"]}'>
      <input type='hidden' name='hide_charts' id='hide_charts' value=''>

      <input type='hidden' name='advising_load_active' id='advising_load_active' value='{$GLOBALS["fp_advising"]["advising_load_active"]}'>
      <input type='hidden' name='advising_student_id' id='advising_student_id' value='{$GLOBALS["fp_advising"]["advising_student_id"]}'>
      <input type='hidden' name='advising_term_id' id='advising_term_id' value='{$GLOBALS["fp_advising"]["advising_term_id"]}'>
      <input type='hidden' name='advising_major_code' id='advising_major_code' value='{$GLOBALS["fp_advising"]["advising_major_code"]}'>


      <input type='hidden' name='advising_track_degree_ids' id='advising_track_degree_ids' value='{$GLOBALS["fp_advising"]["advising_track_degree_ids"]}'>

      <input type='hidden' name='advising_update_student_settings_flag' id='advising_update_student_settings_flag' value=''>
      <input type='hidden' name='advising_what_if' id='advising_what_if' value='{$GLOBALS["fp_advising"]["advising_what_if"]}'>
      <input type='hidden' name='what_if_major_code' id='what_if_major_code' value='{$GLOBALS["fp_advising"]["what_if_major_code"]}'>
      <input type='hidden' name='what_if_catalog_year' id='what_if_catalog_year' value='{$GLOBALS["fp_advising"]["what_if_catalog_year"]}'>


      <input type='hidden' name='what_if_track_degree_ids' id='what_if_track_degree_ids' value='{$GLOBALS["fp_advising"]["what_if_track_degree_ids"]}'>

      <input type='hidden' name='advising_view' id='advising_view' value='{$GLOBALS["fp_advising"]["advising_view"]}'>

      <input type='hidden' name='current_student_id' id='current_student_id' value='{$GLOBALS["fp_advising"]["current_student_id"]}'>
      <input type='hidden' name='log_addition' id='log_addition' value=''>

      <input type='hidden' name='fp_update_user_settings_flag' id='fp_update_user_settings_flag' value=''>

      <input type='hidden' name='advising_update_student_degrees_flag' id='advising_update_student_degrees_flag' value=''>

      <input type='hidden' name='reinit_fp_after_draft_save' id='reinit_fp_after_draft_save' value='no'>

      </span>
      ";

    return $rtn;
  }


}

