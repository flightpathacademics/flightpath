<?php

class ObjList
{

	public $array_list, $i, $is_empty, $count;

	function __construct()
	{
		$this->array_list = array();
		$this->i = 0;
		$this->count = 0;
		$this->is_empty = true;
	}


	function add($c, $bool_add_to_top = false)
	{
		// Adds courses to the list.  Remember to perform
		// reset_counter before using this list, or the count
		// variable will be off!
		if ($bool_add_to_top == false)
		{
			$this->array_list[] = $c;
			//adminDebug(".....adding course");
		} else {
			// We are going to add this to the top of the array, pushing
			// everything else down.
			$temp_array = array();
			$temp_array[0] = $c;
			
			$new_array = array_merge($temp_array, $this->array_list);
			$this->array_list = $new_array;
			// adminDebug("adding to top...");
		}
		
			$this->is_empty = false;
			$this->count = count($this->array_list);
		
		
	}

	function index_of($obj_c)
	{
		// Find in the array an object.equals(objC), and return the
		// index.

		for ($t = 0; $t < count($this->array_list); $t++)
		{
			if ($this->array_list[$t]->equals($obj_c))
			{
				return $t;
			}
		}

		return -1;

	}

	function check_is_empty()
	{
		if (count($this->array_list) > 0)
		{
			$this->is_empty = false;
		}
	}

	function object_index_of($obj_c)
	{
		// This will return the array index of the exact object being requested.
		// Not the ->equals(), but rather an == of the object (the reference is the same)

		for ($t = 0; $t < count($this->array_list); $t++)
		{
			if ($this->array_list[$t] == $obj_c)
			{
				return $t;
			}
		}

		return -1;


	}


	function reset_counter()
	{
		$this->i = 0;
		$this->count = count($this->array_list);
	}

	function get_first()
	{
		if ($this->get_size() > 0)
		{
			return $this->get_element(0);
		} else {
			return false;
		}
	}


	function get_element($c)
	{

		return $this->array_list[$c];
	}



	function find_match($obj_c)
	{ // This actually returns an object if it can find
		// it using index_of.

		$c = $this->index_of($obj_c);
		if ($c > -1)
		{
			return $this->get_element($c);
		} else {
			return false;
		}
	}


	function insert_after_index($new_i, $obj_c)
	{
		$rtn = new ObjList();
		for ($t = 0; $t < $new_i; $t++)
		{
			$rtn->add($this->array_list[$t]);
		}

		$rtn->add($obj_c);

		for ($t = $new_i; $t < count($this->array_list); $t++)
		{
			$rtn->add($this->array_list[$t]);
		}

		$this->array_list = $rtn->array_list;
		$this->count = count($this->array_list);

	}


	function find_all_matches(stdClass $obj_c)
	{
		// This will find all the matches of objC in the
		// array, and return an ObjList of matches.
		$rtn = new ObjList();
		$bool_no_matches = true;
		for ($t = 0; $t < $this->count; $t++)
		{
			if ($this->array_list[$t]->equals($obj_c))
			{
				$rtn->add($this->array_list[$t]);
				$bool_no_matches = false;
			}
		}

		if ($bool_no_matches == false)
		{
			return $rtn;
		} else {
			return false;
		}

	}


	function get_size()
	{
		return sizeof($this->array_list);
	}



	function to_string()
	{
		// Return a string of every obj in this list.
		$rtn = "";

		for ($t = 0; $t < $this->count; $t++)
		{
			$rtn .= $this->array_list[$t]->to_string();
		}

		return $rtn;
	}



  function refresh_indexes() {
    $new_array_list = array();
    foreach ($this->array_list as $obj) {
      $new_array_list[] = $obj;
    }
    $this->array_list = $new_array_list;
    $this->reset_counter();
  }






	function has_more()
	{
		//adminDebug("here " . count($this->array_list));
		if ($this->i < $this->count)
		{
			return true;
		} else {
			return false;
		}
	}

	function get_next()
	{
		$s = @$this->array_list[$this->i];
		$this->i++;
		return $s;
	}


}
