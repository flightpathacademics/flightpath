Retrieved via composer on 12-31-2023
Using community SDK for PHP 8.x: https://signalwire-community.github.io/docs/php/
REQUIRES PHP 8.x!


**********
Important note:  Modifications to signalwire-community/signalwire/src/Rest/Client.php were made to remove
                 references to Fax object, which for whatever reason is not included.  As FlightPath
                 does not use this functionality anyway, the original code was commented out and modified.
**********




For use, see: https://docs.signalwire.com/topics/laml-api/?php#api-reference-messages-the-message-object



For php, to use, gotta do this:

<?php
  require 'path-to/vendor/autoload.php';
?>



also...


<?php
  use SignalWire\Rest\Client;

  $client = new Client('your-project', 'your-token', array("signalwireSpaceUrl" => "example.signalwire.com"));

  // You can then use $client to make calls, send messages, and more.
?>


---------------------------------------

See: https://docs.signalwire.com/topics/laml-api/?php#api-reference-messages

Sending an SMS message:

<?php
  use SignalWire\Rest\Client;

  $client = new Client('YourProjectID', 'YourAuthToken', array("signalwireSpaceUrl" => "example.signalwire.com"));

  $message = $client->messages
                    ->create("+15557654321", // to
                             array("from" => "+15551234567", "body" => "Hello World!")
                    );

  print($message->sid);
?>