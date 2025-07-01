<p align="center">
    <a href="https://github.com/wazzac/laravel-db-encryption/issues"><img alt="GitHub issues" src="https://img.shields.io/github/issues/wazzac/laravel-db-encryption"></a>
    <a href="https://github.com/wazzac/laravel-db-encryption/stargazers"><img alt="GitHub stars" src="https://img.shields.io/github/stars/wazzac/laravel-db-encryption"></a>
    <a href="https://github.com/wazzac/laravel-db-encryption/blob/main/LICENSE"><img alt="GitHub license" src="https://img.shields.io/github/license/wazzac/laravel-db-encryption"></a>
    <a href="https://github.com/wazzac/laravel-db-encryption"><img alt="GitHub version" src="https://img.shields.io/github/v/tag/wazzac/laravel-db-encryption?label=version&sort=semver"></a>
</p>

# Laravel DB Encryptor

A Laravel package for secure, transparent encryption and decryption of sensitive model attributes, storing them in a dedicated table while keeping your main tables clean and fast.

## Features
- Seamless encryption/decryption of model attributes via a simple trait
- Encrypted data is stored in a separate `encrypted_attributes` table
- Only non-table attributes can be encrypted (enforced at runtime)
- Automatic loading and saving of encrypted attributes using Eloquent events
- **Search/filter on encrypted properties using SHA-256 hash**
- No sensitive values are ever logged
- Easy integration: just add the trait and define `$encryptedProperties` in your model
- Compatible with Laravel 12

## Requirements
- PHP 8.2+
- Laravel 12
- OpenSSL PHP extension

## How It Works
1. Add the `HasEncryptedAttributes` trait to your Eloquent model.
2. Define a public array property `$encryptedProperties` listing the attributes you want encrypted (these must NOT exist as columns in the model's table).
3. When you load a model, encrypted attributes are automatically decrypted and available as normal properties.
4. When you save a model, encrypted attributes are removed from the main table and securely stored in the `encrypted_attributes` table.
5. **You can filter/search on encrypted properties using the provided query scope.**

**Example Model:**
```php
use Wazza\DbEncrypt\Traits\HasEncryptedAttributes;

class User extends Model
{
    use HasEncryptedAttributes;

    protected $fillable = ['name', 'email'];

    // Only non-table attributes can be encrypted!
    public array $encryptedProperties = [
        'social_security_number',
        'private_note',
    ];
}
```

## Usage
- Use your model as normal:
```php
$user = User::find(1);
$user->social_security_number = '123-45-6789';
$user->private_note = 'Sensitive info';
$user->save();

// When you retrieve the user again, encrypted attributes are automatically decrypted:
$user = User::find(1);
echo $user->social_security_number; // '123-45-6789'
```
- If you try to add an attribute to `$encryptedProperties` that already exists as a column, an exception will be thrown.

### Filtering/Search on Encrypted Properties
You can filter or search for models by encrypted property value using the built-in query scope:

```php
// Find users with a specific social security number
$users = User::whereEncrypted('social_security_number', '123-45-6789')->get();
```

This uses the SHA-256 hash of the value and joins the `encrypted_attributes` table for efficient searching, without ever exposing the decrypted value in the query or logs.

## Installation Steps
1. Require the package in your Laravel project:
   ```sh
   composer require wazza/laravel-db-encryption
   ```
2. Publish the config and migration files (if needed):
   ```sh
   php artisan vendor:publish --provider="Wazza\DbEncrypt\DbEncryptServiceProvider"
   ```
3. Run the migration to create the `encrypted_attributes` table:
   ```sh
   php artisan migrate
   ```
4. Add the trait and `$encryptedProperties` to your models as shown above.

## Monitoring & Logs
- All encryption/decryption operations are logged (without sensitive values).
- To monitor package logs:
  ```sh
  tail -f storage/logs/laravel.log | grep db-encrypt
  ```

## Testing
- Run the test suite using Pest:
  ```sh
  ./vendor/bin/pest

  PASS  Tests\Unit\EncryptorTest
  ✓ it correctly hashes the value                                         0.40s
  ✓ it correctly encrypts and decrypts data                               0.15s
  ✓ it returns different ciphertext for same plaintext (random IV)        0.12s
  ✓ it throws on null input for encrypt                                   0.13s
  ✓ it throws on null input for decrypt                                   0.13s
  ✓ it throws on invalid base64 for decrypt                               0.14s
  ✓ it throws on missing key in config                                    0.19s

   PASS  Tests\Unit\ExampleTest
  ✓ that true is true                                                     0.28s

   PASS  Tests\Feature\ExampleTest
  ✓ it contains a successful example feature test                         0.18s

  Tests:    9 passed (9 assertions)
  Duration: 1.98s
  ```
- Ensure your models and encrypted attributes behave as expected.

---
For more details, see the source code and comments. Contributions and issues welcome!
