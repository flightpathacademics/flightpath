<?php

/*
	This file simply includes all the class files.  It makes it easier
	for scripts to include just this one file (allClasses.php) rather
	than the individual class files.
*/


// Note: $levels_deep will have been set (or not) by another script.  This
// is so that you can send "../../" or whatever if you need to.  Currently
// used in routines/routines.php.
if (!isset($levels_deep)) $levels_deep = "";

// These are the various class files we need to load.  These 
// are all overridden by the files in custom/classes, even if nothing
// is changed.  This is how FlightPath lets you override core functionality
// without actually editing core files.
//
// If you need to make changes, make them in custom/classes.
require_once("AdvisingScreen.php");
require_once("AdvisingScreenTypeView.php");
require_once("DatabaseHandler.php");
require_once("Course.php");

require_once("StandardizedTest.php");
require_once("ObjList.php");
require_once("CourseList.php");
require_once("Group.php");
require_once("GroupList.php");
require_once("Semester.php");

require_once("DegreePlan.php");
require_once("Substitution.php");
require_once("SubstitutionList.php");
require_once("Student.php");
require_once("FlightPath.php");

