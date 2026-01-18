<?php


/**
 * The name "Semester" might be a little misleading, as it usually refers
 * to years and the like.  But, it might also refer to Summer semesters.
 * Basically, its a collection of courses and groups that are required of
 * a student.  For example, the "Freshman" semester will contain courses
 * and groups to be taken Freshman year.
 *
 */
class Semester extends stdClass
{
	public $title, $semester_num, $notice, $bool_using_default_title;
	public $list_courses, $list_groups, $req_by_degree_id;
	/*
	* $title		Freshman, Sophomore, Summer II, etc.
	* $rankNum		Numeric "rank" or order of the semester object. 1,2,3, etc.
	*
	* *** MIGHT SHOULD BE A GROUP INSTEAD? A group can be a list
	*				of courses, and a list of groups.  That sounds like a semester
	*				to me.  But, if not...
	* $list_courses	This is a list of courses which are required
	* $list_groups	This is a list of the groups which are required.
	*/
	
	function __construct($semester_num = "")
	{
		$this->semester_num = $semester_num;
		    
    $this->bool_using_default_title = FALSE;
    
		//$this->list_courses = new ObjList();
		$this->list_courses = new CourseList();
		$this->list_groups = new GroupList();
		
		$this->assign_title();	
	}
	
	function equals(Semester $semester)
	{
	  
		if ($this->semester_num == $semester->semester_num)
		{
			  return true;
		}
		
		return false;			
	}
	
	function assign_title()
	{
		if ($this->semester_num == 0)
		{$this->title = t("Freshman Year");}
		if ($this->semester_num == 1)
		{$this->title = t("Sophomore Year");}
		if ($this->semester_num == 2)
		{$this->title = t("Junior Year");}
		if ($this->semester_num == 3)
		{$this->title = t("Senior Year");}
		if ($this->semester_num == 4)
		{$this->title = t("Year 5");}
		
		// Still didn't find anything?
		if ($this->title == "") {
		  $this->title = t("Year") . " " . ($this->semester_num + 1);
		}
		
    $this->bool_using_default_title = TRUE;
    
	}
	
	
	function to_string()
	{
		$rtn = "";
		
		$rtn .= " Semester: $this->semester_num \n";
		if (!$this->list_courses->is_empty)
		{
			$rtn .= $this->list_courses->to_string();
		}
		if (!$this->list_groups->is_empty)
		{
			$rtn .= $this->list_groups->to_string();
		}
		
		return $rtn;
	}
	
	function reset_list_counters()
	{
		// Goes through all lists in the semester and
		// calls function "reset_counter" on them.
		// Important to do before we start trying to use and
		// work with the semesters.
		$this->list_courses->reset_counter();
		$this->list_groups->reset_list_counters();
	}
	
} // end class Semester