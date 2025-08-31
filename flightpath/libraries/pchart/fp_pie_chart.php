<?php
/**
 * @file
 * This script uses the pchart library (pchart.net) to display a pie chart in FlightPath.
 * 
 * Use this script as if it were an image file, ex:
 *   <img src='fp_pie_chart.php?args=vals' />
 * 
 * NOTE!!  You are REQUIRED to first set an arbitrary token into $_SESSION["fp_pie_chart_token"],
 * and pass it an argument of token= in the query.  This is to prevent outside sites from accessing
 * this script.
 * 
 * Expected in the args list:
 * 
 *    token = string (ex: token=44539884)
 *      - This is REQUIRED.  This will be matched against $_SESSION["fp_pie_chart_token"] to see if
 *        they match.  This is to ensure that outside sites cannot access this script. * 
 *    progress = num  (ex:  progress=20)
 *      - How much progress, out of 100, should be displayed.
 *    unfinished = num (ex: unfinished=80)
 *      - How much is remaining?
 * 
 *    It is assumed you've already done the calculations to make sure that
 *    progress and unfinished add up to 100. 
 * 
 *    unfinished_col = html hex color  (ex:  unfinished_col=660000)
 *      - Notice we need to leave off the #.
 *    progress_col = html hex color (ex: progress_col=CCCCCC)
 * 
 */


// check session variable for valid token
session_start();

$token = $_GET["token"];
$sess_token = $_SESSION["fp_pie_chart_token"];

if ($token == "" || $sess_token == "" || ($token != $sess_token && $sess_token != "")) {
  
  die("Token mismatch");
}


// Include the pChart classes...
include_once("pchart/class/pData.class.php");
include_once("pchart/class/pDraw.class.php");
include_once("pchart/class/pPie.class.php");
include_once("pchart/class/pImage.class.php");


$progress = floatval($_GET["progress"]);
$unfinished = floatval($_GET["unfinished"]);
$unfinished_col = hex2rgb($_GET["unfinished_col"]);
$progress_col = hex2rgb($_GET["progress_col"]);


// Begin constructing the chart.
$data = new pData();
$data->addPoints(array($progress, $unfinished), "Value");




// Required before chart will show up...
$data->addPoints(array("point1", "point2"), "Legend");
$data->setAbscissa("Legend");


$size = 75;   // the default
if (@$_GET['size'] != "") {
  $size = intval($_GET['size']);
}

$picture = new pImage($size, $size, $data);

$chart = new pPie($picture, $data);




// Set colors
$chart->setSliceColor(0, array("R" => @$progress_col["r"], "G" => @$progress_col["g"], "B" => @$progress_col["b"]));  // first val (progress)
$chart->setSliceColor(1, array("R" => @$unfinished_col["r"], "G" => @$unfinished_col["g"], "B" => @$unfinished_col["b"]));  // remainder, unfinished col

// Render it out, with a certain size, and a little gap between the value and the remainder

$radius = 25;
if (@$_GET['radius'] != "") {
  $radius = intval($_GET['radius']);
}


$chart->draw2DPie(38, 38, array("Radius" => $radius, "Border" => TRUE));


// Render the graphic to the browser
$picture->Stroke();

exit();


// Needed a function to convert HTML hex colors to an rgb array.
function hex2rgb($hex) {
   $hex = str_replace("#", "", $hex);

   if(strlen($hex) == 3) {
      $r = hexdec(substr($hex,0,1).substr($hex,0,1));
      $g = hexdec(substr($hex,1,1).substr($hex,1,1));
      $b = hexdec(substr($hex,2,1).substr($hex,2,1));
   } else {
      $r = hexdec(substr($hex,0,2));
      $g = hexdec(substr($hex,2,2));
      $b = hexdec(substr($hex,4,2));
   }
   
   $rgb = array("r" => $r, "g" => $g, "b" => $b);
   return $rgb; // returns an array with the rgb values
}



