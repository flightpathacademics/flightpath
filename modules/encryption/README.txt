Encryption module README.txt


The encryption module is meant to facilitate simple AES encryption of values
or files, which can then be stored on the server or database.

------------------
SETUP
------------------

This module assumes you have OpenSSL support for PHP already installed.  See: https://www.php.net/manual/en/openssl.setup.php
as well as other resources online if your version of PHP does not already have this common package enabled.  Changes
are, it already does.

To configure, visit the settings page, admin/config/encryption.

You may set up your key one of two ways:
  #1) Find the $GLOBALS['encryption_key_string'] value in your settings.php file and set it to at least 32 random characers.
  #2) Enter the location of a key file on your server but outside of the web root.
  
Method #2 means some extra overhead since every time you want to encrypt or decrypt a file or value, the
key file must be read into memory.

Keep your key file a relatively small size.  Ex: 100 - 300 random characters would be fine.

** See below about keeping you key safe and unchanging. **


------------------
HASHING & CIPHER
------------------

NOTE: only make changes here if you really know what you are doing.

By default, the system will use SHA256 as the hashing algorithm and a 256-bit AES cipher selected from a list.

If you wish to enforce a particular hash protocol (ex: "md5"), or cipher then add these lines
to your settings.php file:

  $GLOBALS["encryption_hash"] = "md5";
  $GLOBALS["encryption_cipher"] = "aes-128-cbc";

This will force that particular hash and/or cipher to be used.

BE AWARE that not all ciphers will work correctly with encryption hashes.  It is strongly recommended to leave the default
settings alone.

Make note of which hash & cipher protocol you are using (see below).

------------------
KEY SECURITY
------------------

Keep your key or key file SAFE.  Anyone who gains knowledge of your key could decrypt
your files or values.  To protect against loss, it is recommended you print out
your key on a sheet of paper, and then store it in a secure location.  This way you can
re-type it by hand if need be.

Be sure to also record which hash protocol/cipher was being used along with the key.





