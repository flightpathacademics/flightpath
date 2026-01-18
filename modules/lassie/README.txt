Lassie
========================
Richard Peacock (flightpathacademics.com)


DESCRIPTION

The Lassie modue is meant to be used by module and routine developers,
to monitor long-running jobs and to make sure they finish within the expected
amount of time.

If a job doesn't finish, Lassie will email an admin user to warn them.

This module is very useful for making sure a nightly routine finished without errors.


REQUIREMENTS
In order to function correctly, you should set your site's cron to run at least once per hour.



USE
In your code, "start" a job for Lassie to monitor like so:

  lassie_start($job_name, $hours, $emails);

* $job_name must be a machine name (no spaces or unusual characters) unique to this job.

* $hours is an INTEGER (whole number-- no decimals) which tells Lassie how long we *expect* the job to take to run.  If we do not finish
  within this number of hours, the email addresses will be notified.

* $emails is an optional list of emails addresses (separated by comma) to notify if the job fails to end by $hours.


To tell Lassie that you have finished the job, always end your routine with:

  lassie_finish($job_name);
  
  
  
EXTRA
If you wish Lassie to automatically disable "maintenance mode" when a job fails, then add the following to
your custom/settings.php file (in the "Custom Settings" area):

    $GLOBALS['lassie_disable_maintenance_mode_on_fail'] = TRUE;
    
This is useful if a routine fails to complete, but you do not want the maintenance mode message to be continued to be displayed.
