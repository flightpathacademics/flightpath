<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Tests for encryption module configuration and authenticated encryption.
 */
class EncryptionTest extends FlightPathTestCase {

  private bool $keyPathExisted;
  private mixed $originalKeyPath;
  private bool $keyStringExisted;
  private mixed $originalKeyString;
  private bool $hashExisted;
  private mixed $originalHash;
  private bool $cipherExisted;
  private mixed $originalCipher;

  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('encryption_encrypt')) {
      require_once __DIR__ . '/../../modules/encryption/encryption.module';
    }

    $this->keyPathExisted = variable_exists('encryption_key_path');
    $this->originalKeyPath = variable_get('encryption_key_path', '');
    $this->keyStringExisted = array_key_exists('encryption_key_string', $GLOBALS);
    $this->originalKeyString = $GLOBALS['encryption_key_string'] ?? NULL;
    $this->hashExisted = array_key_exists('encryption_hash', $GLOBALS);
    $this->originalHash = $GLOBALS['encryption_hash'] ?? NULL;
    $this->cipherExisted = array_key_exists('encryption_cipher', $GLOBALS);
    $this->originalCipher = $GLOBALS['encryption_cipher'] ?? NULL;

    variable_delete('encryption_key_path');
    unset($GLOBALS['encryption_hash'], $GLOBALS['encryption_cipher']);
    $GLOBALS['encryption_key_string'] = 'encryption module PHPUnit test key';
  }

  protected function tearDown(): void {
    if ($this->keyPathExisted) {
      variable_set('encryption_key_path', $this->originalKeyPath);
    }
    else {
      variable_delete('encryption_key_path');
    }

    $this->restoreGlobal('encryption_key_string', $this->keyStringExisted, $this->originalKeyString);
    $this->restoreGlobal('encryption_hash', $this->hashExisted, $this->originalHash);
    $this->restoreGlobal('encryption_cipher', $this->cipherExisted, $this->originalCipher);

    parent::tearDown();
  }

  /**
   * Confirms the administration route keeps its form callback and restricted
   * permission, protecting changes that could make stored data unreadable.
   */
  public function testMenuDefinesEncryptionSettingsRoute(): void {
    $items = encryption_menu();

    $this->assertArrayHasKey('admin/config/encryption', $items);
    $item = $items['admin/config/encryption'];
    $this->assertSame('fp_render_form', $item['page_callback']);
    $this->assertSame(array('encryption_settings_form'), $item['page_arguments']);
    $this->assertSame(array('administer_encryption'), $item['access_arguments']);
    $this->assertSame(MENU_TYPE_NORMAL_ITEM, $item['type']);
  }

  /**
   * Ensures a configured key string is converted to the stable binary SHA-256
   * key material used by both encryption and decryption.
   */
  public function testKeyUsesSha256DigestOfConfiguredString(): void {
    $GLOBALS['encryption_key_string'] = 'a specific deterministic key string';

    $this->assertSame(
      openssl_digest('a specific deterministic key string', 'sha256', TRUE),
      encryption_get_key()
    );
  }

  /**
   * Confirms explicitly configured hash and cipher names take precedence over
   * automatic discovery when a deployment requires compatible algorithms.
   */
  public function testConfiguredHashAndCipherOverrideAutoDetection(): void {
    $GLOBALS['encryption_hash'] = 'sha512';
    $GLOBALS['encryption_cipher'] = 'aes-256-ctr';

    $this->assertSame('sha512', encryption_get_hash_protocol());
    $this->assertSame('aes-256-ctr', encryption_get_cipher_algorithm());
  }

  /**
   * Ensures textual and binary content survive an authenticated encryption
   * round trip, while ciphertext does not expose the original value.
   */
  public function testEncryptionRoundTripsTextAndBinaryContent(): void {
    $plainText = "Student notes: confidential\n" . "\x00" . 'binary tail';

    $ciphertext = encryption_encrypt($plainText);

    $this->assertNotSame($plainText, $ciphertext);
    $this->assertSame($plainText, encryption_decrypt($ciphertext));
  }

  /**
   * Confirms an altered ciphertext fails HMAC verification instead of being
   * returned as though it were trusted student data.
   */
  public function testDecryptionRejectsTamperedCiphertext(): void {
    $ciphertext = encryption_encrypt('sensitive advising note');
    $tampered = substr($ciphertext, 0, -2) . 'AA';

    $this->assertFalse(encryption_decrypt($tampered));
  }

  private function restoreGlobal(string $name, bool $existed, mixed $value): void {
    if ($existed) {
      $GLOBALS[$name] = $value;
    }
    else {
      unset($GLOBALS[$name]);
    }
  }
}
