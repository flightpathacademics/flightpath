<?php

class SubstitutionList extends ObjList
{

  // If group_id == -1 or 0 or '' or null, no particular group is required.
	function find_requirement($course_requirement, $bool_exclude_has_been_applied = FALSE, $group_id = '', $degree_id = 0, $exclude_ids = array()) {
	  
		// Look through the array for a substitution which has this
		// course requirement.
		
		$flag = FALSE;
		for ($t = 0; $t < count($this->array_list); $t++)
		{
			$substitution = $this->array_list[$t];
      // Skip if this substitution id is in our list to exclude.

      if (in_array($substitution->db_substitution_id,$exclude_ids)) continue;


			if ($bool_exclude_has_been_applied == true && $substitution->bool_has_been_applied == true)
			{
				// Skip substitutions which we have already applied.
				continue;
			}

      
      if ($degree_id != 0 && $substitution->db_required_degree_id != $degree_id) {
        // Skip substitutions which are for a different degree_id than the one supplied.
        continue;
      }


			$cr = $substitution->course_requirement;
			//adminDebug($cr->course_id . " " . $course_requirement->course_id);

			if ($group_id === -1 || $group_id === 0 || $group_id == '' || $group_id == NULL)
			{ // No particular group_id is required...
				if ($cr->course_id == $course_requirement->course_id)
				{
					return $substitution;
				}
			} else {
				// ONLY check if it's in the supplied group_id...
				if ($cr->course_id == $course_requirement->course_id && $cr->get_bool_assigned_to_group_id($group_id))
				{
					return $substitution;
				}

			}
		}
		return false;
	}

	function find_group_additions(Group $group)
	{
		$group_id = $group->group_id;
		$rtn_list = new CourseList();
		// Find additions for this group_id and return them.
		for ($t = 0; $t < count($this->array_list); $t++)
		{
			$substitution = $this->array_list[$t];
			if ($substitution->bool_group_addition == true)
			{
				$c = $substitution->course_requirement;
				if ($c->get_bool_assigned_to_group_id($group_id))
				{
					$cc = $substitution->course_list_substitutions->get_first();
					//adminDebug("~~ $cc->course_id");
					$rtn_list->add($cc);
				}
			}

		}

		if (!$rtn_list->is_empty)
		{
			return $rtn_list;
		} else {
			return false;
		}

	}


} // class SubstitutionList
