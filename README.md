# Laravel DB Encryptor

A Laravel package for secure, transparent encryption and decryption of sensitive model attributes, storing them in a dedicated table while keeping your main tables clean and fast.

## Features
- Seamless encryption/decryption of model attributes via a simple trait
- Encrypted data is stored in a separate `encrypted_attributes` table
- Only non-table attributes can be encrypted (enforced at runtime)
- Automatic loading and saving of encrypted attributes using Eloquent events
- No sensitive values are ever logged
- Easy integration: just add the trait and define `$encryptedProperties` in your model
- Compatible with Laravel 9+

## Requirements
- PHP 8.1+
- Laravel 9 or higher
- OpenSSL PHP extension

## How It Works
1. Add the `HasEncryptedAttributes` trait to your Eloquent model.
2. Define a public array property `$encryptedProperties` listing the attributes you want encrypted (these must NOT exist as columns in the model's table).
3. When you load a model, encrypted attributes are automatically decrypted and available as normal properties.
4. When you save a model, encrypted attributes are removed from the main table and securely stored in the `encrypted_attributes` table.

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
  ```
- Ensure your models and encrypted attributes behave as expected.

---
For more details, see the source code and comments. Contributions and issues welcome!
