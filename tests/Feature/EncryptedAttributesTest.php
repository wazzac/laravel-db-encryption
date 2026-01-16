<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Wazza\DbEncrypt\Traits\HasEncryptedAttributes;
use Wazza\DbEncrypt\Models\EncryptedAttributes;

beforeEach(function () {
    // Create a test users table
    Schema::create('test_users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('test_users');
});

it('encrypts and decrypts attributes automatically on model save and retrieve', function () {
    $user = TestUser::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'ssn' => '123-45-6789',
    ]);

    expect($user->ssn)->toBe('123-45-6789');

    // Check that the SSN is encrypted in the database
    $encryptedRecord = EncryptedAttributes::where('object_type', 'test_users')
        ->where('object_id', $user->id)
        ->where('attribute', 'ssn')
        ->first();

    expect($encryptedRecord)->not->toBeNull();
    expect($encryptedRecord->encrypted_value)->not->toBe('123-45-6789');
    expect($encryptedRecord->hash_index)->not->toBeEmpty();

    // Reload the user and verify decryption works
    $reloadedUser = TestUser::find($user->id);
    expect($reloadedUser->ssn)->toBe('123-45-6789');
});

it('handles multiple encrypted attributes', function () {
    $user = TestUser::create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'ssn' => '987-65-4321',
        'credit_card' => '4111-1111-1111-1111',
    ]);

    expect($user->ssn)->toBe('987-65-4321');
    expect($user->credit_card)->toBe('4111-1111-1111-1111');

    // Check database has both encrypted
    $encryptedCount = EncryptedAttributes::where('object_type', 'test_users')
        ->where('object_id', $user->id)
        ->count();

    expect($encryptedCount)->toBe(2);

    // Reload and verify
    $reloadedUser = TestUser::find($user->id);
    expect($reloadedUser->ssn)->toBe('987-65-4321');
    expect($reloadedUser->credit_card)->toBe('4111-1111-1111-1111');
});

it('can search by encrypted attribute using whereEncrypted', function () {
    TestUser::create([
        'name' => 'User 1',
        'email' => 'user1@example.com',
        'ssn' => '111-11-1111',
    ]);

    TestUser::create([
        'name' => 'User 2',
        'email' => 'user2@example.com',
        'ssn' => '222-22-2222',
    ]);

    TestUser::create([
        'name' => 'User 3',
        'email' => 'user3@example.com',
        'ssn' => '333-33-3333',
    ]);

    // Search for specific SSN
    $results = TestUser::whereEncrypted('ssn', '222-22-2222')->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('User 2');
    expect($results->first()->ssn)->toBe('222-22-2222');
});

it('updates encrypted attributes correctly', function () {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'ssn' => '123-45-6789',
    ]);

    $originalId = $user->id;

    // Update the encrypted attribute
    $user->ssn = '999-99-9999';
    $user->save();

    // Verify the new value is encrypted
    $encryptedRecord = EncryptedAttributes::where('object_type', 'test_users')
        ->where('object_id', $originalId)
        ->where('attribute', 'ssn')
        ->first();

    expect($encryptedRecord)->not->toBeNull();

    // Reload and verify decryption
    $reloadedUser = TestUser::find($originalId);
    expect($reloadedUser->ssn)->toBe('999-99-9999');
});

it('deletes encrypted attributes when set to null or empty', function () {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'ssn' => '123-45-6789',
    ]);

    expect(EncryptedAttributes::where('object_type', 'test_users')
        ->where('object_id', $user->id)
        ->where('attribute', 'ssn')
        ->exists())->toBeTrue();

    // Set to null and save
    $user->ssn = null;
    $user->save();

    // Verify it's deleted from encrypted_attributes
    expect(EncryptedAttributes::where('object_type', 'test_users')
        ->where('object_id', $user->id)
        ->where('attribute', 'ssn')
        ->exists())->toBeFalse();
});

it('handles empty encrypted properties array gracefully', function () {
    $user = TestUserWithoutEncryption::create([
        'name' => 'Plain User',
        'email' => 'plain@example.com',
    ]);

    expect($user->name)->toBe('Plain User');

    // No encrypted attributes should be created
    $count = EncryptedAttributes::where('object_type', 'test_users')
        ->where('object_id', $user->id)
        ->count();

    expect($count)->toBe(0);
});

it('throws exception when trying to encrypt existing column', function () {
    expect(fn() => TestUserBadConfig::create([
        'name' => 'Bad User',
        'email' => 'bad@example.com',
    ]))->toThrow(\Wazza\DbEncrypt\Exceptions\InvalidAttributeException::class);
});

// Test model classes
class TestUser extends Model
{
    use HasEncryptedAttributes;

    protected $table = 'test_users';
    protected $fillable = ['name', 'email', 'ssn', 'credit_card'];

    public array $encryptedProperties = [
        'ssn',
        'credit_card',
    ];

    public $timestamps = true;
}

class TestUserWithoutEncryption extends Model
{
    use HasEncryptedAttributes;

    protected $table = 'test_users';
    protected $fillable = ['name', 'email'];

    public array $encryptedProperties = [];

    public $timestamps = true;
}

class TestUserBadConfig extends Model
{
    use HasEncryptedAttributes;

    protected $table = 'test_users';
    protected $fillable = ['name', 'email'];

    // This should throw an exception because 'name' is a real column
    public array $encryptedProperties = [
        'name',
    ];

    public $timestamps = true;
}
