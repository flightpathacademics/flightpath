<?php
/**
 * @file
 * This file is responsible for outputting our content to the web browser.
 * 
 * Available variables (supplied by AdvisingScreen::output_to_browser() method.
 * 
 * $page_content    The primary content of the page.    
 * $page_logo_url   Will contain the URL to the logo image
 * $page_on_load    If the page performs any javascript onLoad, it goes here.
 * $page_tabs     Contains the HTML to draw the correct tabs at the top of the page.
 * $page_is_popup   Set to either TRUE or FALSE.  If TRUE, do not display the header,
 *          and possibly have different layout parameters.  These are booleans
 *          and not strings.
 * $page_title      The title of the page, set in the <head>
 * $page_display_title  The title we should display for the page.  Blank if none.
 * $page_has_search   Either TRUE or FALSE.  Is a boolean, not a string.  If set to
 *          TRUE, the page will have a search bar at the top.
 * $page_scroll_top   If set, the page will automatically scroll to this position (and
 *          the one below it) on load.
 * $page_hide_report_error     if set to TRUE, the page will not display the link to report
 *              an error.
 * $page_banner_is_link   true or false.  If true, the banner at the top will load FP in a
 *            new window.
 * $page_extra_js_settings    These are variable definitions set by other modules, using the fp_add_js() command.
 * $page_extra_css_files      These are extra CSS files which other modules wish to include, using fp_add_css().
 * $page_extra_js_files       Similar to extra_css files, but for extra javascript files.
 * $page_body_classes         A string containing the CSS classes (space-separated) which should go on the body element.
 * 
 * $page_header   This is the "header content" for the page.  Basically, the logo?  TODO:  do I need this to be a variable?
 * $page_sidebar_left_content      This is the HTML contents that should appear in the left sidebar, if any.
 * $page_top_nav_content           This is HTML for the top navigation content.
 * 
 * $theme_location    This contains the URL to the selected theme's location.  Ex:  "/themes/fp_clean"
 * 
 * 
*/

?>
  
  
  
<body class='<?php print $page_body_classes; ?>'>

  <?php if (!$page_is_popup) : ?>
  <div id='header'>
    <div id='top-nav'>
      <?php print $page_top_nav_content; ?>
    </div>
    <div class='top-banner'>
      <a href='<?php print fp_url("<front>"); ?>'>
        <img src='<?php print $page_logo_url; ?>' border='0' alt='<?php print htmlentities(variable_get('system_name', 'FlightPath'), ENT_QUOTES); ?>'>
      </a>
    </div>          
  </div>

  <?php endif; ?>


  <div id='main-contant-wrapper'>

    <?php if ($page_sidebar_left_content) : ?>
    <div id='sidebar-left'>
      <?php print $page_sidebar_left_content; ?>
    </div>
    <?php endif; ?>

  
    <div id='page-content'>
      <?php if ($page_breadcrumbs): ?>
        <div id='breadcrumbs-wrapper'><?php print $page_breadcrumbs;?></div>
        <div class='clear'></div>
      <?php endif; ?>


      <?php if ($page_display_title): ?>
        <h2 class='title'><?php print $page_display_title; ?></h2>
      <?php endif; ?>

            
      <?php if ($page_tabs): ?>
        <div id='page-print-options'><a href='javascript:print();' title='Print'><i class='fa fa-print'></i></a></div>      
        <div class='page-tabs-wrapper'><?php print $page_tabs; ?></div>
      <?php endif; ?>

      <?php if (isset($page_student_profile_header) && $page_student_profile_header != ""): ?>
        <div id='page-student-mini-profile-wrapper'><?php print $page_student_profile_header; ?></div>
                <div class='clear'></div>
      <?php endif; ?>

      
      <div class='inner-page-content-wrapper'>
        <?php print $page_content; ?>
      </div>
      
      
    </div>


  </div>

  <?php if (!$page_is_popup) : ?>
    
    <div class='fp-bottom-message'>
      <span class='popup-contact'>
        <?php
          if ($page_hide_report_error != TRUE && trim(variable_get('contact_email_address', ''))) {
            $contact_title = t("Contact the @FlightPath Production Team", array("@FlightPath" => variable_get("system_name", "FlightPath")));
            $contact_title = str_replace("'", "", $contact_title);
            $contact_title = str_replace('"', "", $contact_title);
            print "<a class='nounderline' href='javascript:fpOpenLargeIframeDialog(\"" . fp_url("popup-report-contact") . "\",\"" . $contact_title . "\")'>" . $contact_title . "</a>";
          }
        ?>
      </span>
      <span class='powered-by-fpa'><?php print t("Powered by ") . "&nbsp; <a href='https://flightpathacademics.com' target='_blank'><i class='fa fa-send'></i> FlightPath Academics</a>"; ?></span>
    <!-- Optional copyright message could go here.
        <span>&copy; Date, Institution, etc.</span> -->   
    </div>

  <?php endif; ?>


  
</body>
</html>