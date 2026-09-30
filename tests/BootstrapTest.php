<?php

require_once __DIR__ . '/bootstrap.php';

class BootstrapTest extends FlightPathTestCase {

  public function testEverythingIsWorking()
  {
    $this->assertTrue($GLOBALS['fp_bootstrap_loaded']);
  }

}