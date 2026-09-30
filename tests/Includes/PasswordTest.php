<?php

/**
 * Tests the password hashing, verification, and password-complexity helpers.
 */
class PasswordTest extends FlightPathTestCase
{
  public function testHashPasswordProducesValidFlightPathHash()
  {
    $hash = user_hash_password('TestPassword123!', 7);

    $this->assertIsString($hash);
    $this->assertSame(FP_HASH_LENGTH, strlen($hash));
    $this->assertStringStartsWith('$S$', $hash);
    $this->assertTrue(user_check_password('TestPassword123!', $hash));
    $this->assertFalse(user_check_password('WrongPassword123!', $hash));
  }

  public function testHashPasswordUsesDifferentSaltEachTime()
  {
    $hash1 = user_hash_password('TestPassword123!', 7);
    $hash2 = user_hash_password('TestPassword123!', 7);

    $this->assertNotSame($hash1, $hash2);
    $this->assertTrue(user_check_password('TestPassword123!', $hash1));
    $this->assertTrue(user_check_password('TestPassword123!', $hash2));
  }

  public function testCheckPasswordRejectsEmptyCredentialsAndUnknownHashTypes()
  {
    $hash = user_hash_password('TestPassword123!', 7);

    $this->assertFalse(user_check_password('', $hash));
    $this->assertFalse(user_check_password('TestPassword123!', ''));
    $this->assertFalse(user_check_password('TestPassword123!', '$X$invalid-hash'));
  }

  public function testCheckPasswordSupportsLegacyPhpassMd5Hashes()
  {
    $setting = _password_generate_salt(7);
    $setting = '$P$' . substr($setting, 3);
    $hash = _password_crypt('md5', 'LegacyPassword123!', $setting);

    $this->assertNotFalse($hash);
    $this->assertTrue(user_check_password('LegacyPassword123!', $hash));
    $this->assertFalse(user_check_password('WrongPassword123!', $hash));
  }

  public function testCheckPasswordSupportsPhpbbHashPrefix()
  {
    $setting = _password_generate_salt(7);
    $hash = _password_crypt('md5', 'LegacyPassword123!', $setting);
    $phpbb_hash = '$H$' . substr($hash, 3);

    $this->assertTrue(user_check_password('LegacyPassword123!', $phpbb_hash));
    $this->assertFalse(user_check_password('WrongPassword123!', $phpbb_hash));
  }

  public function testHashCountBoundariesAreEnforced()
  {
    $this->assertSame(FP_MIN_HASH_COUNT, _password_enforce_log2_boundaries(FP_MIN_HASH_COUNT - 1));
    $this->assertSame(FP_MIN_HASH_COUNT, _password_enforce_log2_boundaries(FP_MIN_HASH_COUNT));
    $this->assertSame(FP_HASH_COUNT, _password_enforce_log2_boundaries(FP_HASH_COUNT));
    $this->assertSame(FP_MAX_HASH_COUNT, _password_enforce_log2_boundaries(FP_MAX_HASH_COUNT));
    $this->assertSame(FP_MAX_HASH_COUNT, _password_enforce_log2_boundaries(FP_MAX_HASH_COUNT + 1));
  }

  public function testGenerateSaltContainsExpectedFormatAndIterationCount()
  {
    $salt = _password_generate_salt(7);

    $this->assertSame(12, strlen($salt));
    $this->assertSame('$S$', substr($salt, 0, 3));
    $this->assertSame(7, _password_get_count_log2($salt));
  }

  public function testGenerateSaltClampsIterationCount()
  {
    $low_salt = _password_generate_salt(FP_MIN_HASH_COUNT - 5);
    $high_salt = _password_generate_salt(FP_MAX_HASH_COUNT + 5);

    $this->assertSame(FP_MIN_HASH_COUNT, _password_get_count_log2($low_salt));
    $this->assertSame(FP_MAX_HASH_COUNT, _password_get_count_log2($high_salt));
  }

  public function testPasswordComplexityAcceptsValidPassword()
  {
    $this->assertTrue((bool) password_validate_complexity('CorrectHorse9!'));
  }

  public function testPasswordComplexityRequiresAtLeastTwelveCharacters()
  {
    $this->assertFalse((bool) password_validate_complexity('Short9!abc'));
  }

  public function testPasswordComplexityRequiresADigit()
  {
    $this->assertFalse((bool) password_validate_complexity('NoDigitsHere!'));
  }

  public function testPasswordComplexityRequiresANonDigitCharacter()
  {
    $this->assertFalse((bool) password_validate_complexity('123456789012'));
  }

  public function testPasswordComplexityRejectsThreeIdenticalCharactersInARow()
  {
    $this->assertFalse((bool) password_validate_complexity('GoodPassword111'));
    $this->assertFalse((bool) password_validate_complexity('aaaPassword123'));
    $this->assertFalse((bool) password_validate_complexity('GoodPasswor!!!12'));
  }

  public function testPasswordComplexityAllowsTwoIdenticalCharactersInARow()
  {
    $this->assertTrue((bool) password_validate_complexity('GoodPassword112'));
  }

  public function testPasswordComplexityRulesCanBeReturnedAsArray()
  {
    $rules = password_get_complexity_rules(TRUE);

    $this->assertIsArray($rules);
    $this->assertCount(4, $rules);
    $this->assertContains('Must be at least 12 characters long', $rules);
    $this->assertContains('Must contain at least one number', $rules);
    $this->assertContains('Must contain at least one letter or symbol', $rules);
    $this->assertContains('Cannot contain the same character more than twice in a row', $rules);
  }

  public function testPasswordComplexityRulesCanBeReturnedAsHtml()
  {
    $html = password_get_complexity_rules();

    $this->assertIsString($html);
    $this->assertStringStartsWith("<ul class='password-complexity-rules'>", $html);
    $this->assertStringContainsString('<li>Must be at least 12 characters long</li>', $html);
    $this->assertStringContainsString('<li>Must contain at least one number</li>', $html);
    $this->assertStringContainsString('</ul>', $html);
  }

  public function testNeedsNewHashRejectsLegacyAndMalformedHashes()
  {
    $account = new stdClass();

    $account->password = '';
    $this->assertTrue(user_needs_new_hash($account));

    $account->password = '$P$invalid';
    $this->assertTrue(user_needs_new_hash($account));

    $account->password = str_repeat('x', FP_HASH_LENGTH);
    $this->assertTrue(user_needs_new_hash($account));
  }

  public function testNeedsNewHashAcceptsCurrentHashConfiguration()
  {
    $original_count = variable_get('password_count_log2', FP_HASH_COUNT);

    try {
      variable_set('password_count_log2', 7);
      $account = new stdClass();
      $account->password = user_hash_password('TestPassword123!', 7);

      $this->assertFalse(user_needs_new_hash($account));
    }
    finally {
      variable_set('password_count_log2', $original_count);
    }
  }

  public function testNeedsNewHashDetectsDifferentIterationCount()
  {
    $original_count = variable_get('password_count_log2', FP_HASH_COUNT);

    try {
      variable_set('password_count_log2', 8);
      $account = new stdClass();
      $account->password = user_hash_password('TestPassword123!', 7);

      $this->assertTrue(user_needs_new_hash($account));
    }
    finally {
      variable_set('password_count_log2', $original_count);
    }
  }




} // class











//