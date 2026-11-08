<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Wazza\DbEncrypt\Models\EncryptedAttributes;
use Wazza\DbEncrypt\Traits\HasEncryptedAttributes;

beforeEach(function () {
    Schema::create('cmd_people', function ($table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('cmd_things', function ($table) {
        $table->string('code')->primary();
    });
});

function makePerson(string $secret): CmdPerson
{
    return CmdPerson::create(['name' => 'Person', 'secret' => $secret]);
}

function rotateKey(): void
{
    config(['db-encrypt.previous_keys' => [config('db-encrypt.key')], 'db-encrypt.key' => 'a-brand-new-key']);
}

// --- db-encrypt:re-encrypt --------------------------------------------------

it('rotates data so the old key can be retired', function () {
    $id = makePerson('a-secret')->id;
    $before = EncryptedAttributes::first();

    rotateKey();

    $this->artisan('db-encrypt:re-encrypt', ['--force' => true])->assertSuccessful();

    $after = EncryptedAttributes::first();
    expect($after->encrypted_value)->not->toBe($before->encrypted_value);
    expect($after->hash_index)->not->toBe($before->hash_index);

    // The old key is gone, and both reading and searching still work
    config(['db-encrypt.previous_keys' => []]);
    expect(CmdPerson::find($id)->secret)->toBe('a-secret');
    expect(CmdPerson::whereEncrypted('secret', 'a-secret')->first()?->id)->toBe($id);
});

it('upgrades v1.0 rows so legacy support can be turned off', function () {
    $id = DB::table('cmd_people')->insertGetId(['name' => 'Old']);
    EncryptedAttributes::create([
        'object_type' => 'cmd_people',
        'object_id' => $id,
        'attribute' => 'secret',
        'hash_index' => legacyHash('old-secret'),
        'encrypted_value' => legacyEncrypt('old-secret'),
    ]);

    $this->artisan('db-encrypt:re-encrypt', ['--force' => true])->assertSuccessful();

    config(['db-encrypt.legacy_support' => false]);
    expect(CmdPerson::find($id)->secret)->toBe('old-secret');
    expect(CmdPerson::whereEncrypted('secret', 'old-secret')->first()?->id)->toBe($id);
});

it('processes every row across several batches', function () {
    foreach (range(1, 5) as $i) {
        makePerson("secret-{$i}");
    }
    rotateKey();

    $this->artisan('db-encrypt:re-encrypt', ['--force' => true, '--batch' => 2])->assertSuccessful();

    config(['db-encrypt.previous_keys' => []]);
    expect(CmdPerson::all()->pluck('secret')->sort()->values()->all())
        ->toBe(['secret-1', 'secret-2', 'secret-3', 'secret-4', 'secret-5']);
});

it('changes nothing on a dry run, and still reports rows it could not decrypt', function () {
    makePerson('a-secret');
    $before = EncryptedAttributes::first()->encrypted_value;

    $this->artisan('db-encrypt:re-encrypt', ['--dry-run' => true])->assertSuccessful();
    expect(EncryptedAttributes::first()->encrypted_value)->toBe($before);

    config(['db-encrypt.key' => 'the-wrong-key']);
    $this->artisan('db-encrypt:re-encrypt', ['--dry-run' => true])->assertFailed();
});

it('fails, and leaves the row intact, when a row cannot be decrypted', function () {
    makePerson('a-secret');
    $before = EncryptedAttributes::first()->encrypted_value;

    config(['db-encrypt.key' => 'the-wrong-key']);

    $this->artisan('db-encrypt:re-encrypt', ['--force' => true])->assertFailed();
    expect(EncryptedAttributes::first()->encrypted_value)->toBe($before);
});

it('limits re-encryption with --table and --model', function (array $option) {
    makePerson('a-secret');
    EncryptedAttributes::factory()->create(['object_type' => 'other']);
    $other = EncryptedAttributes::where('object_type', 'other')->first()->encrypted_value;
    $person = EncryptedAttributes::where('object_type', 'cmd_people')->first()->encrypted_value;

    $this->artisan('db-encrypt:re-encrypt', ['--force' => true, ...$option])->assertSuccessful();

    expect(EncryptedAttributes::where('object_type', 'other')->first()->encrypted_value)->toBe($other);
    expect(EncryptedAttributes::where('object_type', 'cmd_people')->first()->encrypted_value)->not->toBe($person);
})->with([
    'table' => [['--table' => 'cmd_people']],
    'model' => [['--model' => 'CmdPerson']],
]);

it('rejects bad re-encrypt options', function (array $option) {
    $this->artisan('db-encrypt:re-encrypt', ['--force' => true, ...$option])->assertFailed();
})->with([
    'zero batch' => [['--batch' => 0]],
    'negative batch' => [['--batch' => -5]],
    'not a model' => [['--model' => 'stdClass']],
    'unknown model' => [['--model' => 'Nope\\Missing']],
]);

it('fails re-encryption without a key', function () {
    config(['db-encrypt.key' => null]);

    $this->artisan('db-encrypt:re-encrypt', ['--force' => true])->assertFailed();
});

// --- db-encrypt:prune -------------------------------------------------------

it('prunes only rows whose parent is gone', function () {
    $kept = makePerson('kept');
    $gone = makePerson('gone');
    DB::table('cmd_people')->where('id', $gone->id)->delete();

    $this->artisan('db-encrypt:prune')->assertSuccessful();

    expect(EncryptedAttributes::pluck('object_id')->all())->toBe([$kept->id]);
});

it('changes nothing on a prune dry run', function () {
    $gone = makePerson('gone');
    DB::table('cmd_people')->where('id', $gone->id)->delete();

    $this->artisan('db-encrypt:prune', ['--dry-run' => true])
        ->expectsOutputToContain('Would have deleted 1')
        ->assertSuccessful();

    expect(EncryptedAttributes::count())->toBe(1);
});

it('leaves rows alone when the parent table cannot be read', function () {
    EncryptedAttributes::factory()->create(['object_type' => 'no_such_table']);

    $this->artisan('db-encrypt:prune')->assertSuccessful();

    expect(EncryptedAttributes::count())->toBe(1);
});

it('limits pruning with --table', function () {
    EncryptedAttributes::factory()->create(['object_type' => 'cmd_people', 'object_id' => 999]);
    EncryptedAttributes::factory()->create(['object_type' => 'cmd_things', 'object_id' => 999]);

    $this->artisan('db-encrypt:prune', ['--table' => 'cmd_people', '--key' => 'id'])->assertSuccessful();

    expect(EncryptedAttributes::pluck('object_type')->all())->toBe(['cmd_things']);
});

it('prunes tables with a custom primary key through --model and --key', function (array $option) {
    DB::table('cmd_things')->insert(['code' => '123']);

    EncryptedAttributes::factory()->create(['object_type' => 'cmd_things', 'object_id' => 123]);
    EncryptedAttributes::factory()->create(['object_type' => 'cmd_things', 'object_id' => 456]);

    $this->artisan('db-encrypt:prune', $option)->assertSuccessful();

    expect(EncryptedAttributes::pluck('object_id')->all())->toEqual([123]);
})->with([
    'model' => [['--model' => 'CmdThing']],
    'key' => [['--table' => 'cmd_things', '--key' => 'code']],
]);

it('prunes correctly across several batches', function () {
    foreach (range(1, 5) as $i) {
        makePerson("secret-{$i}");
    }
    DB::table('cmd_people')->whereIn('id', [1, 3, 5])->delete();

    $this->artisan('db-encrypt:prune', ['--batch' => 2])->assertSuccessful();

    expect(EncryptedAttributes::orderBy('object_id')->pluck('object_id')->all())->toEqual([2, 4]);
});

// --- db-encrypt:rename-table ------------------------------------------------

it('moves rows to a new table name so they stay readable and searchable', function () {
    $id = makePerson('a-secret')->id;

    $this->artisan('db-encrypt:rename-table', ['from' => 'cmd_people', 'to' => 'cmd_humans'])
        ->expectsOutputToContain('Moved 1')
        ->assertSuccessful();

    Schema::rename('cmd_people', 'cmd_humans');

    expect(CmdHuman::find($id)->secret)->toBe('a-secret');
    expect(CmdHuman::whereEncrypted('secret', 'a-secret')->first()?->id)->toBe($id);
});

it('explains why a plain SQL rename is not enough', function () {
    $id = makePerson('a-secret')->id;
    EncryptedAttributes::query()->update(['object_type' => 'cmd_humans']);
    Schema::rename('cmd_people', 'cmd_humans');

    CmdHuman::find($id);
})->throws(\Wazza\DbEncrypt\Exceptions\DecryptionException::class);

it('rejects renaming a table to itself', function () {
    $this->artisan('db-encrypt:rename-table', ['from' => 'a', 'to' => 'a'])->assertFailed();
});

// --- db-encrypt:status ------------------------------------------------------

it('reports counts and legacy values, without keys or values', function () {
    makePerson('a-secret');
    EncryptedAttributes::factory()->create([
        'object_type' => 'cmd_people',
        'attribute' => 'old',
    ]);
    EncryptedAttributes::where('attribute', 'old')->update(['encrypted_value' => legacyEncrypt('x')]);

    $this->artisan('db-encrypt:status')
        ->expectsOutputToContain('1 value(s) use the legacy format')
        ->doesntExpectOutputToContain(config('db-encrypt.key'))
        ->doesntExpectOutputToContain('a-secret')
        ->assertSuccessful();
});

it('says when legacy support can be turned off', function () {
    makePerson('a-secret');

    $this->artisan('db-encrypt:status')
        ->expectsOutputToContain('can be set to false')
        ->assertSuccessful();
});

it('fails the status check without a key', function () {
    config(['db-encrypt.key' => null]);

    $this->artisan('db-encrypt:status')->assertFailed();
});

// --- db-encrypt:generate-key ------------------------------------------------

describe('generate-key', function () {
    beforeEach(function () {
        $this->envDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'db-encrypt-' . bin2hex(random_bytes(6));
        mkdir($this->envDir);
        $this->app->useEnvironmentPath($this->envDir);
        $this->envFile = $this->envDir . DIRECTORY_SEPARATOR . '.env';
    });

    afterEach(function () {
        @unlink($this->envFile);
        @rmdir($this->envDir);
    });

    it('sets a key when there is none, without prompting', function () {
        config(['db-encrypt.key' => null]);
        file_put_contents($this->envFile, "APP_NAME=Test\n");

        $this->artisan('db-encrypt:generate-key')->assertSuccessful();

        $content = file_get_contents($this->envFile);
        expect($content)->toContain('APP_NAME=Test');
        expect($content)->toMatch('/^DB_ENCRYPT_KEY=[A-Za-z0-9+\/]{43}=$/m');
    });

    it('shows a key without writing anything', function () {
        file_put_contents($this->envFile, "APP_NAME=Test\n");

        $this->artisan('db-encrypt:generate-key', ['--show' => true])->assertSuccessful();

        expect(file_get_contents($this->envFile))->toBe("APP_NAME=Test\n");
    });

    it('does not replace an existing key if the prompt is declined', function () {
        file_put_contents($this->envFile, "DB_ENCRYPT_KEY=existing\n");

        $this->artisan('db-encrypt:generate-key')
            ->expectsConfirmation('This will make all existing encrypted data unreadable (use --rotate to keep it). Do you wish to continue?', 'no')
            ->assertFailed();

        expect(file_get_contents($this->envFile))->toBe("DB_ENCRYPT_KEY=existing\n");
    });

    it('replaces an existing key with --force, keeping CRLF line endings', function () {
        file_put_contents($this->envFile, "DB_ENCRYPT_KEY=existing\r\nAPP_NAME=Test\r\n");

        $this->artisan('db-encrypt:generate-key', ['--force' => true])->assertSuccessful();

        $content = file_get_contents($this->envFile);
        expect($content)->not->toContain('existing');
        expect($content)->toMatch('/^DB_ENCRYPT_KEY=[^\r\n]+\r\nAPP_NAME=Test\r\n$/');
    });

    it('moves the current key to the previous keys with --rotate', function () {
        config(['db-encrypt.key' => 'current-key', 'db-encrypt.previous_keys' => ['older-key']]);
        file_put_contents($this->envFile, "DB_ENCRYPT_KEY=current-key\nDB_ENCRYPT_PREVIOUS_KEYS=older-key\n");

        $this->artisan('db-encrypt:generate-key', ['--rotate' => true])->assertSuccessful();

        $content = file_get_contents($this->envFile);
        expect($content)->toContain('DB_ENCRYPT_PREVIOUS_KEYS=current-key,older-key');
        expect($content)->not->toContain('DB_ENCRYPT_KEY=current-key');
    });

    it('fails cleanly when there is no .env file', function () {
        config(['db-encrypt.key' => null]);

        $this->artisan('db-encrypt:generate-key')->assertFailed();
    });
});

it('parses previous keys from the environment', function () {
    putenv('DB_ENCRYPT_PREVIOUS_KEYS= one , two ,,');
    $_ENV['DB_ENCRYPT_PREVIOUS_KEYS'] = ' one , two ,,';
    $_SERVER['DB_ENCRYPT_PREVIOUS_KEYS'] = ' one , two ,,';

    try {
        $config = require __DIR__ . '/../../config/db-encrypt.php';
    } finally {
        putenv('DB_ENCRYPT_PREVIOUS_KEYS');
        unset($_ENV['DB_ENCRYPT_PREVIOUS_KEYS'], $_SERVER['DB_ENCRYPT_PREVIOUS_KEYS']);
    }

    expect($config['previous_keys'])->toBe(['one', 'two']);
});

// Test model classes
class CmdPerson extends Model
{
    use HasEncryptedAttributes;

    public $timestamps = false;
    protected $table = 'cmd_people';
    protected $fillable = ['name', 'secret'];
    public array $encryptedProperties = ['secret'];
}

class CmdHuman extends Model
{
    use HasEncryptedAttributes;

    public $timestamps = false;
    protected $table = 'cmd_humans';
    protected $fillable = ['name', 'secret'];
    public array $encryptedProperties = ['secret'];
}

class CmdThing extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $table = 'cmd_things';
    protected $primaryKey = 'code';
    protected $keyType = 'string';
}
