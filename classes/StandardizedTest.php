<?php


class StandardizedTest extends stdClass
{
	public $categories, $description, $test_id, $date_taken, $bool_date_unavailable, $school_id;



	function __construct()
	{
		$this->categories = array();
    $this->bool_date_unavailable = FALSE;
    $this->school_id = 0;
	}


	function to_string()
	{
		$rtn = "";

		$rtn .= "test: $this->date_taken - $this->test_id - $this->description : \n";
		if (count($this->categories))
		{
			foreach($this->categories as $position => $value)
			{
				$rtn .= "  $position - {$value["category_id"]} - {$value["description"]} - {$value["score"]} \n";
			}
		}

		return $rtn;
	}


}


