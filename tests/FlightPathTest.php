<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.inc';


class FlightPathTest extends TestCase {

  public function testEverythingIsWorking(): void {
    $this->assertTrue($GLOBALS['fp_bootstrap_loaded']);
  }

}