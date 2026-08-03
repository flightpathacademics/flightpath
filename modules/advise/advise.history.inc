<?php

/*
 * This file is responsible for the contents / functionality of the History tab.
*/


/**
 * Displays the history tab on screen.
 */
function advise_display_history() {
  global $current_student_id, $user;

  $student_id = $current_student_id;
  $db = get_global_database_handler();
  $school_id = db_get_school_id_for_student_id($student_id);
  fp_set_title('');

  $extra_style = $ast = "";
  $rtn = "";

  fp_add_css(fp_get_module_path("advise") . "/css/advise.css");
  fp_add_js(fp_get_module_path("advise") . "/js/advise.js");


  $rtn .= "<table class='fp-semester-table'>";


  // If coming here from a save, display info here.
  if (@$_REQUEST["adv_id_array_serialized"] != "") {
    $acount = 0;
    $click_links = "";
    $advising_session_id_array = unserialize($_REQUEST["adv_id_array_serialized"]);

    foreach($advising_session_id_array as $term_id=>$value) {
      $term_id = (string) $term_id;  // Make sure term_id is a standard string
      $aid = $advising_session_id_array[$term_id];
      if ($aid != "")
      {
        $term_name = get_term_description($term_id, FALSE, $school_id);

        $turl = fp_url("advise/popup-display-summary", "advising_session_id=$aid");

        $click_links .= "<li class='just-saved-summary'>
                            <a href='javascript: popupLargeIframeDialog(\"" . $turl . "\",\"" . t("Advising Session @term", array("@term" => $term_name)) . "\",\"\");'>
                                <i class='fa fa-window-restore'></i> $term_name
                            </a>
                         </li>";

        $acount++;

      }
    }
    $group_link = "";
    if ($acount > 1) {
      $turl = fp_url("advise/popup-display-summary", "show_as_group=yes&advising_session_id=" . join(",", $advising_session_id_array));
      $on_click = "popupLargeIframeDialog(\"" . $turl . "\",\"" . t("Advising Sessions") . "\",\"\");";
      $group_link = "<a href='javascript:$on_click' class='advising-summary-group-link'><i class='fa fa-list-ul'></i> " . t("View Combined") . "</a>";
    }

    $acount = 0;

    $rtn .= "
        <tr>
          <td colspan='2' width='100%'>

        <div class='hypo'
        align='left' style='border: 1px solid black;
              margin: 10px 0px 10px 0px; padding: 10px;
              font-size: 12pt; font-weight: bold;'>
        " . t("You have successfully advised") . " " . $db->get_student_name($student_id) . " ($student_id).
        <br>" . t("Click to view a pop-up printable summary for:") . "
         <ul style='margin-top: 5px; margin-bottom: 5px;'>
        $click_links
        </ul>$group_link</div>

        </td>
        </tr>
    ";

  }

  $group_link = "";





  $rtn .= "<tr><td width='50%' valign='top'  class='left-side' style='padding-right: 10px;'>
          ";

  ///////////////////////////////////////////////////
  //////////////////////////////////////////////////
  /////////  Advising History
  ///////////////////////////////////////////////////
  $rtn .= fp_render_section_title(t("Advising History"));
  $render_left = array();  // creating a new render array.
  $render_left["#id"] = "advise_history_left_column";
  $render_left["#student_id"] = $student_id;

  $school_id = db_get_school_id_for_student_id($student_id);

  $old_session_d_t = 0;
  $last_session_id = 0;
  $a_count = 0;
  $c = 0;
  $is_empty = TRUE;
  $first_style = "color: maroon; font-weight:bold;";
  $advising_session_id_array = array();

  $on_mouse_over = "
            onmouseover='$(this).addClass(\"selection_highlight\");'
            onmouseout='$(this).removeClass(\"selection_highlight\");'
    ";




  $advising_session_groups_array = array();
  $previous_sessions = array();
  $session_group_code = $old_session_group_code = "";



  // First, find all of the advising session "groups".  That is, advising sessions over multiple
  // terms which were submitted together.
  $res = db_query("SELECT * FROM advising_sessions
                    WHERE student_id = ?
                    AND is_draft = 0
                    AND is_empty = 0
                    AND delete_flag = 0
                    GROUP BY advising_session_token
                    ORDER BY `posted` DESC
                     ", $student_id);
  while($cur = db_fetch_array($res)) {
    $html = "";
    $is_empty = FALSE;
    $advtoken = $cur['advising_session_token'];
    $bool_is_empty = FALSE;
    $faculty_id = $cur['faculty_id'];
    $dt = date("m/d/Y g:ia",convert_time($cur['posted']));

    $group_link ="";

    // If this has more than one session for this token, then show a group_link
    $sessions_arr = advise_get_advising_sessions_for_advising_token($advtoken);

    if ($sessions_arr && count($sessions_arr) > 1) {
      $turl = fp_url("advise/popup-display-summary", "show_as_group=yes&advising_session_id=" . join(",",$sessions_arr));
      $on_click = "popupLargeIframeDialog(\"" . $turl . "\",\"" . t("Advising Sessions - @date", array("@date" => $dt)) . "\",\"\");";
      $group_link = "<a href='javascript:$on_click' class='advising-summary-group-link'><i class='fa fa-list-ul'></i> " . t("View Combined") . "</a>";
    }


    $advised_by = "<div class='history-advised-row history-advised-row-$a_count'>
            <strong>" . t("Advised by") . " " . fp_get_faculty_name($cur['faculty_id']) . "</strong>$group_link
          </div>";


    $html .= "<div class=''>$advised_by</div>";

    $a_count++;
    if ($a_count > 1) {
      $first_style = "";
    }





    $res2 = db_query("SELECT * FROM advising_sessions
                      WHERE advising_session_token = ?
                      AND is_empty = 0
                      AND delete_flag = 0
                      ORDER BY term_id DESC", array($advtoken));
    while ($cur2 = db_fetch_array($res2)) {



      // Are we supposed to "delete" an advising session?  We will double-check the user has all needed permissions if so.
      if (intval(@$_GET['deladv']) === intval($cur2['advising_session_id'])) {
        if (user_has_permission('can_advise_students') && user_has_permission('can_delete_own_advising_session_3_months') && $cur2['faculty_id'] == $user->cwid) {

          $del_range = strtotime("now -3 months");
          $then = intval($cur2["posted"]);
          if ($then > $del_range) {
            db_query("UPDATE advising_sessions
                      SET delete_flag = 1
                      WHERE advising_session_id = ?", array($cur2['advising_session_id']));
            fp_add_message(t("Advising session has been successfully deleted."));
            fp_goto("history", "current_student_id=$current_student_id");
            die; // redirect from fp_goto above anyway.
          }

        }
      } // if we are trying to delete





      $turl = fp_url("advise/popup-display-summary", "advising_session_id={$cur2['advising_session_id']}");
      $advising_session_id_array[] = $cur2['advising_session_id'];
      $term = get_term_description($cur2['term_id'], FALSE, $school_id);
      $on_click = "popupLargeIframeDialog(\"" . $turl . "\",\"" . t("Advising Session @term - @date", array("@term" => $term, "@date" => $dt)) . "\",\"\");";


      $delete_html = "";
      // Should we show the "delete" link?
      if (user_has_permission('can_advise_students') && user_has_permission('can_delete_own_advising_session_3_months') && $cur2['faculty_id'] == $user->cwid) {

        $del_range = strtotime("now -3 months");
        $then = intval($cur["posted"]);
        if ($then > $del_range) {

          $safe_term = $term;
          $safe_term = str_replace("'", "", $safe_term);
          $safe_term = str_replace('"', "", $safe_term);
          $safe_term = str_replace(';', "", $safe_term);
          $safe_term = str_replace('\\', "", $safe_term);

          $confirm_link = fp_get_js_confirm_link(t("Are you sure you wish to delete the advising session from @dt for @term?  \\n\\nThis action cannot be undone.", array("@dt" => $dt, "@term" => $safe_term)),
                              "window.location=\"" . fp_url("history", "current_student_id=$current_student_id&deladv=" . $cur2['advising_session_id']) . "\";", "<i class='fa fa-remove'></i>", "action-link-remove", t("Delete?"));
          $delete_html .= "<div class='delete-adv-wrapper'>
                      $confirm_link
                    </div>";


        }


      } // user had permission to delete


      $html .= "<div $on_mouse_over style='cursor: pointer; $first_style' class='advhist-row' onClick='$on_click'>
                  <div class='advhist-term' style='display: inline-block; padding-left:20px; width:165px;'>$term</div>
                  <div class='advhist-datetime' style='display: inline-block;'>$dt</div>
                </div>
                $delete_html";



      // If this is more than X number, let's hide the rest in a collapsible fieldset.
      if ($c < 5) {
        $render_left["advising_history_$c"] = array(
          "value" => $html,
          "weight" => $c,
        );
      }
      else {
        // Let's place in a collapsible fieldset!
        if (!isset($render_left["advising_history_fs"])) {
          $render_left["advising_history_fs"] = array(
            "type" => "cfieldset",
            "label" => t("Show complete advising history"),
            "start_closed" => TRUE,
            "elements" => array(),
            "weight" => $c,
          );
        }
        $render_left["advising_history_fs"]["elements"][] = array("advising_history_$c" => array(
          "value" => $html,
        ));


      }

      $html = "";

      $c++;






    } // while cur2


  } // while cur group by advising session token


   if ($is_empty == true) {
    //$rtn .= "<div class=' '>No advising history available.</div>";

    $render_left["markup_no_adv_history"] = array(
      "value" => t("No advising history available."),
    );
  }
  else {

    $print_all_url = fp_url("advise/popup-display-summary", "advising_session_id=" . join(",", $advising_session_id_array));

    $render_left["markup_print_all"] = array(
      "value" => "<a href='javascript: popupLargeIframeDialog(\"" . $print_all_url . "\",\"" . t("Complete Advising History") . "\",\"\");' class='view-print-all'>
                      <i class='fa fa-window-restore'></i> " . t("View Complete History") . "
                  </a>
          ",
      "weight" => -1,
    );
  }

  // Okay, we can render the left-hand side now.
  $rtn .= fp_render_content($render_left);




  //----------------------------------------------------------------------------------------
  //------------------------------ COMMENT HISTORY -----------------------------------------
  //----------------------------------------------------------------------------------------
  $rtn .= "</td><td width='50%' class='right-side' valign='top'>";
  $rtn .= fp_render_section_title(t("Comment History"));
  //$rtn .= "<table border='0' cellspacing='0'>";

  // Prepare a render array for the right-hand side.
  $render_right = array();
  $render_right["#id"] = "advise_history_right_column";
  $render_right["#student_id"] = $student_id;

  $html = "";

  $old_term_id = "";
  $first_style = "first";
  $is_empty = true;
  $has_admin_category = false;
  $access_line = "";

  //if (!user_has_permission("view_faculty_comments")) {
  //  $access_line = "and `access_type`='public' ";
  //}

  $access_types = (user_has_permission("view_faculty_comments")) ? array("faculty", "public") : array("public");


  $turl = fp_url("comments/popup-display-all-comments");


  $html .= "<div class='advising-comments-header'>
        <!--STARTCOM--><div style='padding-top: 10px;'>
          <strong>" . t("Advising Comments") . "</strong>
          &nbsp;
          <a href='javascript: popupLargeIframeDialog(\"" . $turl . "\",\"" . t("All Comments for Student") . "\",\"\");' class='view-print-all'>
            <i class='fa fa-window-restore'></i> " . t("View All Comments") . "
          </a>
        </div><!--ENDCOM-->
        </div>";


// Use the function comments_get_comments instead of a direct query.

  $res = comments_get_comments($student_id, FALSE, $access_types);
  foreach ($res as $cur)
  {
    $dt = format_date(convert_time($cur['posted']), 'custom', 'm/d/Y g:ia');

    if ($first_style == "first"){
      $first_style = "color: maroon; font-weight:bold;";
    }


    $turl = fp_url("comments/popup-display-comment", "id=" . $cur['id']);

    $on_click = "popupLargeIframeDialog(\"" . $turl . "\",\"" . t("Comment @date", array("@date" => $dt)) . "\",\"\");";


    $html .=  "<div $on_mouse_over class='history-comment-row' style='cursor:pointer; $first_style $extra_style'
          onClick='$on_click'>
          <div class=' ' style='padding-left: 20px; width:165px; display:inline-block;'>
            " . fp_get_faculty_name($cur['faculty_id']) . "
          </div>
          <div class=' ' style='display: inline-block;'>
              $dt$ast
          </div>
        </div>";

    $is_empty = false;
    $first_style = "";
  }

  if ($is_empty == true) {

    $render_right["markup_no_comment_history"] = array(
      "value" => "<span class='no-comment-history-msg'>" . t("No comment history available.") . "</span>",
    );

  }

  $render_right["comment_history"] = array(
    "value" => "<span class='advising-comment-history-block'>" . $html . "</span>",
    "weight" => 0,
  );

  //$rtn .= "</table>";

  // Render our render array now...
  $rtn .= fp_render_content($render_right);




  $rtn .= "</td></tr>";




  $rtn .= "</table>";


  // Let's set our breadcrumbs

  fp_set_standard_advising_breadcrumbs();



  watchdog("advise", "display_history $current_student_id", array(), WATCHDOG_DEBUG);


  return $rtn;
}


/**
 * Displays the printable advising summary.
 *
 * If the advising_session_id is null, it gets that value from the REQUEST.  Otherwise, we expect it to be a csv of the
 * sessions we are interested in.
 *
 * If bool_html is set to FALSE, then we will return results in plain text, suitable for email.
 * if bool_minimal_text is set to TRUE, then we will return a reduced amount of text.
 *
 */
function advise_popup_display_summary($advising_session_id = NULL, $bool_html = TRUE, $bool_show_as_group = FALSE, $bool_minimal_text = TRUE) {
  $rtn = "";
  $txt = "";
  $term = '';
  fp_add_css(fp_get_module_path("advise") . "/css/advise.css");
  $db = get_global_database_handler();

  if ($advising_session_id == NULL) {
    $advising_session_id = rtrim(fp_trim(@$_REQUEST["advising_session_id"]), ",");
  }

  if (@$_REQUEST['show_as_group'] === 'yes') {
    $bool_show_as_group = TRUE;
  }

  $bool_multiple = FALSE;
  $bool_show_transaction_details = TRUE;
  $terms = $student_id = "";
  // If we have commas in our advising_session_id, it means we had more than one that were passed to us!  We want to show
  // them all.

  if (!strstr($advising_session_id, ",")) {
    $res = db_query("SELECT * FROM advising_sessions
                     WHERE advising_session_id = ? ", $advising_session_id);
  }
  else {

    $bool_multiple = TRUE;
    $session_array = csv_to_array($advising_session_id);

    $where = "";
    foreach ($session_array as $sid) {
      $where .= " advising_session_id = ? OR";
    }
    $where = rtrim($where, "OR");
    $res = db_query("SELECT * FROM advising_sessions
                     WHERE $where
                     ORDER BY `posted` DESC ", $session_array);

  }
  while ($cur = db_fetch_array($res)) {

    $dt = format_date(convert_time($cur['posted']), "pretty");

    $degree_title_line = "";
    $ds = "";
    $degree_plan = new DegreePlan();
    if ($cur['degree_id'] != DegreePlan::DEGREE_ID_FOR_COMBINED_DEGREE) {
      $degree_plan->degree_id = $cur['degree_id'];
      $degree_plan->load_descriptive_data();
      $degree_title_line = $degree_plan->get_title(true);
    }
    else {
      // This is a set of combined degrees.  We need to load all of the
      // degrees in the major_code_csv to get their titles.
      $temp = explode(",", $cur['major_code_csv']);
      $ds = "s";
      foreach ($temp as $major_code) {
        if ($major_code == "") continue;
        $dp = $db->get_degree_plan($major_code, $cur['catalog_year']);
        if ($dp != null) {
          $degree_title_line .= $dp->get_title2(TRUE, TRUE) . ", ";
        }
      }
      $degree_title_line = rtrim($degree_title_line, ", ");
    }

    $student = new Student($cur['student_id'], $db);

    $student_id = $cur['student_id'];
    $school_id = db_get_school_id_for_student_id($student_id);

    $term = get_term_description($cur['term_id'], FALSE, $school_id);
    $terms .= "<div class='summary-term-desc-multi'>$term</div>";

    $what_if_message = "";
    if (intval($cur['is_whatif']) == 1) {

      $official_degree_title = "";
      $odsverb = " is";
      $c = 0;
      $majors = $db->get_student_majors_from_db($student->student_id);
      foreach ($majors as $major_code) {
        if ($major_code == "") continue;

        $dp = $db->get_degree_plan($major_code, $cur['catalog_year']);
        if ($dp != null) {
          $c++;
          $official_degree_title .= $dp->get_title2(TRUE, TRUE) . ", ";
        }
      }
      if ($c > 1) $odsverb = "s are";

      $official_degree_title = rtrim($official_degree_title, ", ");

      $what_if_message = "<b>" . t("Note:") . "</b>
                " . t("This advising was saved using <b>What If</b> mode
                for the degree$ds %degree_title.  According to @initials records,
                the student's official degree$odsverb %official.", array("%degree_title" => $degree_title_line,
                                                              "@initials" => variable_get_for_school("school_initials", "DEMO", $school_id),
                                                              "%official" => $official_degree_title));
    }



    if (!$bool_minimal_text) {
      $txt .= "\n-----  " . t("Advising Summary for") . " $term -----\n";
      $txt .= t("Student:") . " " . $db->get_student_name($cur['student_id'], false) . " ({$cur['student_id']}) \n";
      $txt .= t("Advisor:") . " " . fp_get_faculty_name($cur['faculty_id']) . "\n";
      $txt .= t("Term:") . " " . $term . "\n";
    }

    $rtn .= "<table class='adv-history-table' width='100%'><td valign='top'>";


    if ($bool_show_transaction_details) {


      $rtn .= "
        <table class='  summary-transaction-details' border='0' width='100%' cellpadding='3' cellspacing='0'>
          <tr height='7'>
          <td> </td>
          </tr>
          <tr>
          <td valign='top' width='15%'>" . t("Student:") . "</td>
          <td valign='top' width='40%'>" . $db->get_student_name($cur['student_id'], false) . " ({$cur['student_id']})</td>
          <td valign='top' rowspan='3'>
            <table width='100%' cellpadding='0' cellspacing='0'>
              <tr height='20'>
                <td width='100%' id='student-signature-line' valign='bottom'><hr noshade size='1' width='100%' color='black' style='margin: 0px;'></td>
              </tr>
              <tr height='20'>
                <td width='100%' id='student-signature-caption' valign='top' align='right'><span style='font-size: 8pt;'>" . t("Student signature") . "</span></td>
              </tr>

              <tr height='20'>
                <td width='100%'  id='advisor-signature-line' valign='bottom'><hr noshade size='1' width='100%' color='black' style='margin: 0px;'></td>
              </tr>
              <tr>
                <td width='100%'  id='advisor-signature-caption'valign='bottom' align='right'><span style='font-size: 8pt;'>" . t("Advisor signature") . "</span></td>
              </tr>
              <tr height='7'>
              <td> </td>
              </tr>


            </table>
          </td>
        </tr>
        <tr>
          <td valign='top' width='10%'>" . t("Advisor:") . "</td>
          <td valign='top'>" . fp_get_faculty_name($cur['faculty_id'], false) . "</td>

          </tr>
          <tr>
          <td valign='top'>" . t("Term(s):") . "</td>";

    $term_disp = $term;
    if ($bool_multiple && $bool_show_as_group) {
      $term_disp = "<!--group_terms-->";
    }

    $rtn .= "
          <td valign='top'>$term_disp</td>

          </tr>

          <tr>
          <td valign='top' colspan='2'  id='summary-alternate-term'>
          " . t("Alternate Term(s):") . " ____________________________
          </td>
          </tr>
          <tr>
            <td valign='top' colspan='4'>
            ";

      if ($degree_title_line != "")
      {
        $rtn .= t("Degree$ds:") . " $degree_title_line";

        if (!$bool_minimal_text) {
          $txt .= "Degree$ds: " . $degree_title_line . "\n";
        }

      }

      $rtn .= "
          </tr>
          </table>
          <div class=' '><i>" . t("Submitted on") . " $dt.</i></div>
          <div class=' '>$what_if_message</div>
          <br>
        ";
      if ($bool_show_as_group) {
        $bool_show_transaction_details = FALSE;
      }


    } // bool_first_time_through

    if (!$bool_minimal_text) {
      $txt .= t("Submitted on") . " $dt \n";
      if ($what_if_message) {
        $txt .= "  " . fp_reduce_whitespace(strip_tags($what_if_message)) . "\n";
      }
    }

    $rtn .= fp_render_section_title(t("Advised Courses for @term", array("@term" => $term)));

    if (!$bool_minimal_text) {
      $txt .= "\n" . t("Advised Courses:") . "\n";
    }
    else {
      $txt .= "" . t("Advised Courses for @term", array("@term" => $term)) .  "\n";
    }
    $fp = new FlightPath($student,$degree_plan, $db);

    $fp->load_advising_session_from_database("","",false,false,$cur['advising_session_id']);

    $rtn .= "<table border='0' cellpadding='3' class='summary-advised-courses'>
        <tr>
          <td class='  course-si-cn' valign='top' width='25%'>
            <b>" . t("Course") . "</b>
          </td>
          <td class='  course-title' valign='top' width='70%'>
            <b>" . t("Title") . "</b>
          </td>
          <td class='  course-hours' valign='top'>
            <b>" . t("Hours") . "</b>
          </td>
        </tr>";



    // Get a courseList of all the courses which are set as advised to take.
    $advised_courses_list = $fp->course_list_advised_courses;
    $advised_courses_list->load_course_descriptive_data();
    $advised_courses_list->sort_alphabetical_order();
    $advised_courses_list->reset_counter();
    while ($advised_courses_list->has_more()) {
      $course = $advised_courses_list->get_next();
      // set the catalogYear from the term_id.
      $course->term_id = $cur['term_id'];
      $course->set_catalog_year_from_term_id();
      $course->load_descriptive_data(false);

      $rtn .= "<tr>
            <td class='  course-si-cn' valign='top'>
            $course->subject_id $course->course_num
            </td>
            <td class='  course-title' valign='top'>
            $course->title
            </td>
            <td class='  course-hours' valign='top' align='center'>
            " . $course->get_hours() . "
            </td>

          </tr>
        ";

        $txt .= "    $course->subject_id $course->course_num - $course->title - " . $course->get_hours() . "hrs \n";
    }

    $rtn .= "</table>
        <div align='right' class='  total-advised-hours' style='padding-top: 10px; padding-right: 15px;'>
          <b>" . t("Total advised hours:") . " &nbsp; " . $advised_courses_list->count_hours() . "</b>
        </div>
        ";

    $txt .= "Total advised hours: " . $advised_courses_list->count_hours() . "\n\n\n";



    $rtn .= "</table>";

    if ($bool_show_transaction_details && $bool_multiple) {
      $rtn .= "<div class='history-summary-divider'>&nbsp;</div>";
    }

  } // while, for the query itself!


  $rtn .= "<br>";
  $rtn .= "<div class='summary-alternate-courses'>";
  $rtn .= fp_render_section_title(t("Alternate Courses"));
  $rtn .= "<div class='  alt-courses-desc-and-lines'>
      " . t("You may use this space to write in alternate courses
      the student should enroll in, in the event that an
      advised course is full or unavailable.") . "
      <br><br>
      ___________________________________________________________________ <br><br>
      ___________________________________________________________________
      </div>  ";
  $rtn .= "</div>";


  if ($bool_multiple && $bool_show_as_group) {
    $rtn = str_replace("<!--group_terms-->", $terms, $rtn);;
  }



  // Are we NOT returning html?
  if (!$bool_html) {
    return $txt;
  }

  fp_set_title($db->get_student_name($student_id, FALSE) . " ($student_id) $term " . t("Advising Summary"));

  watchdog("advise", "popup_display_summary advising_session_id:$advising_session_id", array(), WATCHDOG_DEBUG);

  return $rtn;
}









