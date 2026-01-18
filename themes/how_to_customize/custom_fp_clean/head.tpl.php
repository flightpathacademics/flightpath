<!DOCTYPE HTML>
<html lang="en">
<?php
/**
 * @file
 * This file is meant to contain all of the common items which should appear in
 * the <head></head> section of the page.
 * 
 * The same variables are available as those found in the page.tpl.php file.
 * 
*/

// So that we can "inherit" the base CSS from fp_clean, let's set an $original_theme_location
$fp_clean_original = base_path() . "/themes/fp_clean";


?>
<head>  
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />  
  <meta name="viewport" content="width=device-width" />
  <meta http-equiv="content-language" content="en" />

  <link rel="shortcut icon" type="image/x-icon" href="<?php print base_path() ?>/themes/fp_clean/images/favicon.ico"/>

   
  <?php // Bring in jQuery and jQuery UI, as well as jQuery UI's css. ?>
  
  <script src="<?php print base_path() ?>/libraries/<?php print JQUERY_FILE; ?>" type="text/javascript"></script>
  <script src="<?php print base_path() ?>/libraries/<?php print JQUERY_UI_DIRECTORY; ?>/jquery-ui.min.js" type="text/javascript"></script>
  <script src="<?php print base_path() ?>/libraries/modal-alert/js/daypilot-modal-2.9.js" type="text/javascript"></script>
  
  <script type='text/javascript'>
  <?php print $page_extra_js_settings; ?>     
    // perform any requested actions on page load...
    $(document).ready(function() { <?php print $page_on_load; ?>; });  
  </script>
  
  <link rel='stylesheet' type='text/css' href='<?php print base_path() ?>/libraries/<?php print JQUERY_UI_DIRECTORY; ?>/jquery-ui.min.css' />
    
  
  <?php
   // Add extra JS files.     
   print $page_extra_js_files;
  
   // Load the origninal fp_clean theme's CSS file(s)
   print "<link rel='stylesheet' type='text/css' href='$fp_clean_original/style.css?$page_css_js_query_string' /> \n";
   print "<link rel='stylesheet' type='text/css' href='$fp_clean_original/media.css?$page_css_js_query_string' /> \n";
  
   // Load any extra CSS files which addon modules might have added.
   print $page_extra_css_files;
    
   // Load the custom.css file for THIS theme...
   print "<link rel='stylesheet' type='text/css' href='$theme_location/custom.css?$page_css_js_query_string' /> \n";
   
  ?>
  
  <link rel="stylesheet" href="<?php print $theme_location ?>/font-awesome-4.7.0/css/font-awesome.min.css">
    
  <title><?php print $page_title; ?> | <?php print $system_name; ?></title>
</head>
  