<?php

use PHPUnit\Framework\TestCase;

abstract class FlightPathTestCase extends TestCase
{

    /**
     * This is run before each test; useful for resetting variables back to defaults
     * before the next test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        global $user;
        $user = new stdClass();
        $user->id = 1;
        $user->cwid = 1;
        $user->name = 'admin';
        $user->school_id = 0;

        unset($_REQUEST);
        unset($_GET);
        unset($_POST);


        // Clear SESSION of unneeded vars...
        $keep_array = [
          'fp_db_fingerprint',
          'fp_db_host_ip',
          'fp_user_object',
          'fp_logged_in',
          'fp_alert_count_by_type_last_check',
          'fp_pie_chart_token',
          'fp_pie_chart_token',
        ];

        foreach ($_SESSION as $k => $v) {
          if (!in_array($k, $keep_array)) {
            unset($_SESSION[$k]);
          }
        }

        // Reset various variables to default...
        variable_delete_for_school("course_repeat_policy", 0);




    }
}