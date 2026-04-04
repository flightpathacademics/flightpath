<?php

/**
@file
This file describes hooks provided by the engagements module, which other modules
can take advantage of.
*/


/**
 * Called when an SMS is received by FlightPath and before any logic, queries, etc., are performed.
 * 
 * It is safe to assume the SMS has been validated.
 * 
 * Notice that $vars is passed by reference, so changes can be made if desired.
 * 
 * @param unknown $vars
 */
function hook_engagements_handle_incomming_sms_init(&$vars) {
 
  // Example code:
  $message_sid = $vars['MessageSid'];
  if ($message_sid == 'x12-ffg-3445') {
    // Perform special DB inserts into custom table.
  }
   
}



/**
 * Called at the very end of the engagements_handle_incomming_sms function, after the SMS message has been
 * received, and any alerts or other content have been created, and it has been added to the sms_history table.
 *
 * The $sms_history_mid value is the `mid` from the sms_history database table.
 * 
 * $content_created is an array of content (like the engagement, alerts, etc) which were created as a result
 *                    of this SMS).
 */
function hook_engagements_handle_incomming_sms_post_save(&$vars, $sms_history_mid = 0, $content_created = array()) {
  
  // Ex: Query sms_history where mid = $sms_history_mid, insert into other table
  
  
  
}