<?php

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Wazza\DbEncrypt\Exceptions\DecryptionException;
use Wazza\DbEncrypt\Exceptions\InvalidAttributeException;
use Wazza\DbEncrypt\Exceptions\ModelNotSetException;
use Wazza\DbEncrypt\Helper\Encryptor;
use Wazza\DbEncrypt\Http\Controllers\DbEncryptController;
use Wazza\DbEncrypt\Models\EncryptedAttributes;
use Wazza\DbEncrypt\Traits\HasEncryptedAttributes;

beforeEach(function () {
    // Create a test users table
    Schema::create('test_users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->softDeletes();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('test_users');
});

function encryptedRow(string $attribute, int $id): ?EncryptedAttributes
{
    return EncryptedAttributes::where('object_type', 'test_users')
        ->where('object_id', $id)
        ->where('attribute', $attribute)
        ->first();
}

function makeTestUser(array $attributes = [], string $class = TestUser::class): Model
{
    return $class::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        ...$attributes,
    ]);
}

it('encrypts and decrypts attributes automatically on model save and retrieve', function () {
    $user = makeTestUser(['ssn' => '123-45-6789']);

    expect($user->ssn)->toBe('123-45-6789');

    // Check that the SSN is encrypted in the database
    $encryptedRecord = encryptedRow('ssn', $user->id);

    expect($encryptedRecord)->not->toBeNull();
    expect($encryptedRecord->encrypted_value)->not->toContain('123-45-6789');
    expect($encryptedRecord->hash_index)->not->toBeEmpty();

    // Reload the user and verify decryption works
    expect(TestUser::find($user->id)->ssn)->toBe('123-45-6789');
});

it('handles multiple encrypted attributes', function () {
    $user = makeTestUser(['ssn' => '987-65-4321', 'credit_card' => '4111-1111-1111-1111']);

    expect($user->ssn)->toBe('987-65-4321');
    expect($user->credit_card)->toBe('4111-1111-1111-1111');

    // Check database has both encrypted
    expect(EncryptedAttributes::where('object_type', 'test_users')->where('object_id', $user->id)->count())->toBe(2);

    // Reload and verify
    $reloadedUser = TestUser::find($user->id);
    expect($reloadedUser->ssn)->toBe('987-65-4321');
    expect($reloadedUser->credit_card)->toBe('4111-1111-1111-1111');
});

it('never writes encrypted attributes to the model table', function () {
    $user = makeTestUser(['ssn' => '123-45-6789']);

    expect((array) DB::table('test_users')->find($user->id))->not->toHaveKey('ssn');
});

it('updates encrypted attributes correctly', function () {
    $user = makeTestUser(['ssn' => '123-45-6789']);

    // Update the encrypted attribute
    $user->ssn = '999-99-9999';
    $user->save();

    expect(encryptedRow('ssn', $user->id))->not->toBeNull();
    expect($user->ssn)->toBe('999-99-9999');

    // Reload and verify decryption
    expect(TestUser::find($user->id)->ssn)->toBe('999-99-9999');
});

it('deletes encrypted attributes when set to null or empty', function (?string $empty) {
    $user = makeTestUser(['ssn' => '123-45-6789']);

    expect(encryptedRow('ssn', $user->id))->not->toBeNull();

    $user->ssn = $empty;
    $user->save();

    expect(encryptedRow('ssn', $user->id))->toBeNull();
})->with([null, '']);

it('handles empty encrypted properties array gracefully', function () {
    $user = makeTestUser([], TestUserWithoutEncryption::class);

    expect($user->name)->toBe('John Doe');
    expect(EncryptedAttributes::count())->toBe(0);
});

it('throws exception when trying to encrypt existing column, before anything is written', function () {
    expect(fn () => makeTestUser([], TestUserBadConfig::class))
        ->toThrow(InvalidAttributeException::class);

    expect(DB::table('test_users')->count())->toBe(0);
});

it('rejects a column conflict when retrieving a model', function () {
    $id = DB::table('test_users')->insertGetId(['name' => 'Bad', 'email' => 'bad@example.com']);

    expect(fn () => TestUserBadConfig::find($id))->toThrow(InvalidAttributeException::class);
});

// --- Dirty tracking ---------------------------------------------------------

it('does not mark a retrieved model as dirty', function () {
    $id = makeTestUser(['ssn' => '123-45-6789'])->id;

    $user = TestUser::find($id);

    expect($user->isDirty())->toBeFalse();
    expect($user->getOriginal('ssn'))->toBe('123-45-6789');
});

it('does not rewrite unchanged encrypted values on save', function () {
    $user = makeTestUser(['ssn' => '123-45-6789']);
    $stored = encryptedRow('ssn', $user->id)->encrypted_value;

    $reloaded = TestUser::find($user->id);
    $reloaded->name = 'Changed';
    $reloaded->save();

    expect(encryptedRow('ssn', $user->id)->encrypted_value)->toBe($stored);
    expect($reloaded->ssn)->toBe('123-45-6789');
    expect($reloaded->isDirty())->toBeFalse();
});

it('saves when only an encrypted attribute changed', function () {
    $id = makeTestUser(['ssn' => '123-45-6789'])->id;

    $user = TestUser::find($id);
    $user->ssn = '999-99-9999';

    expect($user->isDirty('ssn'))->toBeTrue();

    $user->save();

    expect($user->isDirty())->toBeFalse();
    expect($user->wasChanged('ssn'))->toBeTrue();
    expect(TestUser::find($id)->ssn)->toBe('999-99-9999');
});

it('does not delete values when saving a model with missing rows', function () {
    $id = makeTestUser(['ssn' => '123-45-6789'])->id;
    $user = TestUser::find($id);

    expect($user->credit_card)->toBeNull();
    expect(fn () => $user->save())->not->toThrow(Exception::class);
    expect(encryptedRow('ssn', $id))->not->toBeNull();
});

it('allows saveQuietly on a retrieved model with no changes', function () {
    $id = makeTestUser(['ssn' => '123-45-6789'])->id;

    expect(TestUser::find($id)->saveQuietly())->toBeTrue();
});

it('reloads encrypted values through fresh and refresh', function () {
    $user = makeTestUser(['ssn' => '123-45-6789']);
    $copy = TestUser::find($user->id);

    $user->ssn = '999-99-9999';
    $user->save();

    expect($copy->fresh()->ssn)->toBe('999-99-9999');
    expect($copy->refresh()->ssn)->toBe('999-99-9999');
});

// --- Mutators, accessors and casts ------------------------------------------

it('applies a mutator once, not again on save or load', function () {
    $user = makeTestUser(['nickname' => 'bob'], TestUserWithCasts::class);

    expect($user->getAttributes()['nickname'])->toBe('[bob]');

    $reloaded = TestUserWithCasts::find($user->id);
    expect($reloaded->getAttributes()['nickname'])->toBe('[bob]');
    expect($reloaded->isDirty())->toBeFalse();
});

it('stores the raw value rather than an accessor result', function () {
    $user = makeTestUser(['shouted' => 'quiet'], TestUserWithCasts::class);

    expect($user->shouted)->toBe('QUIET');
    expect(encryptedRow('shouted', $user->id)->decryptValue())->toBe('quiet');
    expect(TestUserWithCasts::find($user->id)->shouted)->toBe('QUIET');
});

it('round trips cast values', function () {
    $user = makeTestUser([
        'preferences' => ['theme' => 'dark'],
        'age' => 42,
        'active' => false,
    ], TestUserWithCasts::class);

    $reloaded = TestUserWithCasts::find($user->id);

    expect($reloaded->preferences)->toBe(['theme' => 'dark']);
    expect($reloaded->age)->toBe(42);
    expect($reloaded->active)->toBeFalse();
});

it('stores false and zero rather than treating them as empty', function () {
    $user = makeTestUser(['active' => false, 'age' => 0], TestUserWithCasts::class);

    expect(encryptedRow('active', $user->id)->decryptValue())->toBe('0');
    expect(encryptedRow('age', $user->id)->decryptValue())->toBe('0');
});

it('does not leak a class cast value into the model table', function () {
    $user = makeTestUser(['settings' => ['a' => 1]], TestUserWithCasts::class);

    expect($user->settings['a'])->toBe(1);

    $user->settings['a'] = 2;
    $user->name = 'Changed';
    $user->save();

    expect((array) DB::table('test_users')->find($user->id))->not->toHaveKey('settings');
    expect(TestUserWithCasts::find($user->id)->settings['a'])->toBe(2);
});

it('rejects values that cannot be stored as a string, without echoing them', function () {
    $user = makeTestUser();
    $user->ssn = ['not-a-string-secret'];

    expect(fn () => $user->save())->toThrow(
        fn (InvalidAttributeException $e) => expect($e->getMessage())->toContain('ssn')->not->toContain('not-a-string-secret')
    );
});

// --- Search -----------------------------------------------------------------

it('can search by encrypted attribute using whereEncrypted', function () {
    makeTestUser(['name' => 'User 1', 'ssn' => '111-11-1111']);
    makeTestUser(['name' => 'User 2', 'ssn' => '222-22-2222']);
    makeTestUser(['name' => 'User 3', 'ssn' => '333-33-3333']);

    // Search for specific SSN
    $results = TestUser::whereEncrypted('ssn', '222-22-2222')->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('User 2');
    expect($results->first()->ssn)->toBe('222-22-2222');
});

it('supports orWhereEncrypted and whereEncryptedIn', function () {
    makeTestUser(['name' => 'User 1', 'ssn' => '111-11-1111']);
    makeTestUser(['name' => 'User 2', 'ssn' => '222-22-2222']);
    makeTestUser(['name' => 'User 3', 'ssn' => '333-33-3333']);

    $or = TestUser::where('name', 'User 1')->orWhereEncrypted('ssn', '333-33-3333')->pluck('name');
    $in = TestUser::whereEncryptedIn('ssn', ['111-11-1111', '222-22-2222'])->pluck('name');

    expect($or->all())->toBe(['User 1', 'User 3']);
    expect($in->all())->toBe(['User 1', 'User 2']);
});

it('only matches within the searched attribute', function () {
    makeTestUser(['name' => 'User 1', 'ssn' => 'same-value']);
    makeTestUser(['name' => 'User 2', 'credit_card' => 'same-value']);

    expect(TestUser::whereEncrypted('ssn', 'same-value')->pluck('name')->all())->toBe(['User 1']);
});

it('does not reveal equal values across attributes through the hash', function () {
    $user = makeTestUser(['ssn' => 'same-value', 'credit_card' => 'same-value']);

    expect(encryptedRow('ssn', $user->id)->hash_index)
        ->not->toBe(encryptedRow('credit_card', $user->id)->hash_index);
});

it('rejects searching an attribute that is not encrypted', function () {
    TestUser::whereEncrypted('name', 'John Doe');
})->throws(InvalidAttributeException::class);

it('keeps finding rows after a key rotation, before re-encryption', function () {
    makeTestUser(['ssn' => '123-45-6789']);

    config(['db-encrypt.previous_keys' => [config('db-encrypt.key')], 'db-encrypt.key' => 'a-brand-new-key']);

    expect(TestUser::whereEncrypted('ssn', '123-45-6789')->first()->ssn)->toBe('123-45-6789');
});

// --- v1.0 data --------------------------------------------------------------

it('reads and searches rows written by v1.0, and upgrades them when they change', function () {
    $id = DB::table('test_users')->insertGetId(['name' => 'Old', 'email' => 'old@example.com']);
    EncryptedAttributes::create([
        'object_type' => 'test_users',
        'object_id' => $id,
        'attribute' => 'ssn',
        'hash_index' => legacyHash('123-45-6789'),
        'encrypted_value' => legacyEncrypt('123-45-6789'),
    ]);

    $user = TestUser::whereEncrypted('ssn', '123-45-6789')->first();
    expect($user->id)->toBe($id);
    expect($user->ssn)->toBe('123-45-6789');

    $user->ssn = '999-99-9999';
    $user->save();

    expect(Encryptor::isLegacyPayload(encryptedRow('ssn', $id)->encrypted_value))->toBeFalse();
    expect(TestUser::find($id)->ssn)->toBe('999-99-9999');
});

// --- Tampering --------------------------------------------------------------

it('refuses a ciphertext copied from another row', function () {
    $victim = makeTestUser(['name' => 'Victim', 'ssn' => 'victim-secret']);
    $attacker = makeTestUser(['name' => 'Attacker', 'ssn' => 'attacker-value']);

    encryptedRow('ssn', $attacker->id)->update([
        'encrypted_value' => encryptedRow('ssn', $victim->id)->encrypted_value,
    ]);

    expect(fn () => TestUser::find($attacker->id))->toThrow(DecryptionException::class);
    expect(TestUser::find($victim->id)->ssn)->toBe('victim-secret');
});

it('refuses a ciphertext moved to another attribute', function () {
    $user = makeTestUser(['ssn' => 'a-secret']);

    encryptedRow('ssn', $user->id)->update(['attribute' => 'credit_card']);

    TestUser::find($user->id);
})->throws(DecryptionException::class);

it('surfaces decryption failures with a package exception and does not expose the key', function () {
    $id = makeTestUser(['ssn' => 'a-secret'])->id;
    $key = config('db-encrypt.key');

    config(['db-encrypt.key' => 'the-wrong-key']);

    expect(fn () => TestUser::find($id))->toThrow(
        fn (DecryptionException $e) => expect($e->getMessage())->not->toContain($key)->not->toContain('a-secret')
    );
});

// --- Deleting ---------------------------------------------------------------

it('removes encrypted rows when a model is deleted', function () {
    $user = makeTestUser(['ssn' => '123-45-6789']);
    $other = makeTestUser(['ssn' => '999-99-9999']);

    $user->delete();

    expect(encryptedRow('ssn', $user->id))->toBeNull();
    expect(encryptedRow('ssn', $other->id))->not->toBeNull();
});

it('keeps encrypted rows through a soft delete and restore, and removes them on force delete', function () {
    $user = makeTestUser(['ssn' => '123-45-6789'], TestSoftDeletingUser::class);

    $user->delete();
    expect(encryptedRow('ssn', $user->id))->not->toBeNull();

    $trashed = TestSoftDeletingUser::withTrashed()->find($user->id);
    $trashed->restore();
    expect(TestSoftDeletingUser::find($user->id)->ssn)->toBe('123-45-6789');

    TestSoftDeletingUser::find($user->id)->forceDelete();
    expect(encryptedRow('ssn', $user->id))->toBeNull();
});

// --- Queries and process state ----------------------------------------------

it('runs one attribute query per retrieved model and one schema lookup per process', function () {
    $id = makeTestUser(['ssn' => '111', 'credit_card' => '222'])->id;
    makeTestUser(['ssn' => '333', 'credit_card' => '444']);
    DbEncryptController::flushSchemaCache();

    DB::enableQueryLog();
    TestUser::all();
    TestUser::find($id);
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    expect($queries->filter(fn ($q) => str_contains($q, 'encrypted_attributes')))->toHaveCount(3);
    expect($queries->filter(fn ($q) => str_contains(strtolower($q), 'pragma')))->toHaveCount(1);
});

it('skips decryption inside withoutDecryption, and a save leaves the rows alone', function () {
    $id = makeTestUser(['ssn' => '123-45-6789'])->id;

    DB::enableQueryLog();
    $user = TestUser::withoutDecryption(fn () => TestUser::find($id));
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    expect($queries->filter(fn ($q) => str_contains($q, 'encrypted_attributes')))->toHaveCount(0);
    expect($user->getAttributes())->not->toHaveKey('ssn');

    $user->name = 'Changed';
    $user->save();

    expect(TestUser::find($id)->ssn)->toBe('123-45-6789');
});

it('restores decryption when the withoutDecryption callback throws', function () {
    $id = makeTestUser(['ssn' => '123-45-6789'])->id;

    try {
        TestUser::withoutDecryption(fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
        // expected
    }

    expect(TestUser::find($id)->ssn)->toBe('123-45-6789');
});

it('does not touch encrypted attributes when the primary key was not selected', function () {
    makeTestUser(['ssn' => '123-45-6789']);

    $user = TestUser::query()->select('name')->first();

    expect($user->getAttributes())->toBe(['name' => 'John Doe']);
});

it('resolves a separate controller each time, so no state leaks between models', function () {
    expect(app(DbEncryptController::class))->not->toBe(app(DbEncryptController::class));
});

it('does not grow the log identifier as models are set', function () {
    $controller = app(DbEncryptController::class);
    $user = makeTestUser();

    $controller->setModel($user);
    $first = $controller->logger->getLogIdentifier();
    $controller->setModel($user)->setModel($user);

    expect($controller->logger->getLogIdentifier())->toBe($first);
});

it('allows clearing the model on the controller', function () {
    $controller = app(DbEncryptController::class)->setModel(makeTestUser())->setModel(null);

    expect($controller->isModelDefined())->toBeFalse();
    expect($controller->getEncryptedProperties())->toBe([]);

    $controller->encryptAll();
})->throws(ModelNotSetException::class);

it('refuses to encrypt for a model with no primary key', function () {
    $user = new TestUser(['ssn' => '123']);

    app(DbEncryptController::class)->setModel($user)->encrypt();
})->throws(ModelNotSetException::class);

it('never logs plaintext values at the most verbose level', function () {
    $messages = [];
    Log::listen(function ($event) use (&$messages) {
        $messages[] = $event->message . json_encode($event->context);
    });

    $user = makeTestUser(['ssn' => 'plaintext-secret']);
    TestUser::find($user->id);
    TestUser::whereEncrypted('ssn', 'plaintext-secret')->first();

    expect($messages)->not->toBeEmpty();
    expect(implode("\n", $messages))->not->toContain('plaintext-secret');
});

// --- The EncryptedAttributes model ------------------------------------------

it('hides ciphertext and hashes from serialization', function () {
    $row = EncryptedAttributes::factory()->create();

    expect($row->toArray())->not->toHaveKey('encrypted_value')->not->toHaveKey('hash_index');
});

it('builds factory rows that decrypt, including with overrides', function () {
    $row = EncryptedAttributes::factory()->create(['object_type' => 'orders', 'object_id' => 7, 'attribute' => 'note']);

    expect($row->fresh()->decryptValue())->toBeString()->not->toBeEmpty();
});

it('finds rows through the helper lookups', function () {
    $user = makeTestUser(['ssn' => '123-45-6789']);
    $hash = Encryptor::hash('123-45-6789', Encryptor::searchContext('test_users', 'ssn'));

    expect(EncryptedAttributes::viaAttributeId('test_users', (string) $user->id, 'ssn')?->decryptValue())->toBe('123-45-6789');
    expect(EncryptedAttributes::viaAttributeHash('test_users', 'ssn', $hash)?->object_id)->toEqual($user->id);
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

class TestUserWithCasts extends Model
{
    use HasEncryptedAttributes;

    protected $table = 'test_users';
    protected $guarded = [];

    public array $encryptedProperties = ['nickname', 'shouted', 'preferences', 'age', 'active', 'settings'];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'age' => 'integer',
            'active' => 'boolean',
            'settings' => AsArrayObject::class,
        ];
    }

    public function setNicknameAttribute(string $value): void
    {
        $this->attributes['nickname'] = '[' . $value . ']';
    }

    public function getShoutedAttribute(?string $value): ?string
    {
        return $value === null ? null : strtoupper($value);
    }
}

class TestSoftDeletingUser extends Model
{
    use HasEncryptedAttributes;
    use SoftDeletes;

    protected $table = 'test_users';
    protected $fillable = ['name', 'email', 'ssn'];

    public array $encryptedProperties = ['ssn'];
}
