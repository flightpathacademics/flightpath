
By performing the following steps, you can create your own custom
theme for FlightPath, using fp6_clean as the "base" theme.  This will
allow you to freely upload FlightPath's core files without worrying
about your custom changes being wiped out.

This method will continue to load the core CSS and JS files for the
theme, but it will also utilize a "custom.css" file in the custom
theme folder.

==================
STEPS:
==================


1) Copy the "custom_fp7_clean" directory and all of
   its contents to custom/themes/.

   For example, in a linux/unix environment, the following commands would work:
   
       cp -r /path/to/flightpath/themes/how_to_customize/custom_fp7_clean /path/to/flightpath/custom/themes/.
   
   
2) Log into FlightPath as an administrator, visit the System Settings, and set the new
   "Custom - FlightPath 7 Clean Theme" theme as the default theme.


3) You may now edit the theme's custom.css file to change or override any of the core
   CSS styles you wish to change.  See the custom.css file for an example on how to
   override the login page's wallpaper to an image of your choosing for your
   institution.