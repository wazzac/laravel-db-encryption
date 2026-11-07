# AGENTS.md

Guidance for coding agents working in this repository. This is the single shared source of truth: tool-specific files such as `CLAUDE.md` point here, so add anything that applies to every agent to this file.

## Project

`wazza/laravel-db-encryption`: a Laravel 12 and 13 package (PHP ^8.2, namespace `Wazza\DbEncrypt`) that transparently encrypts Eloquent model attributes. Values live in a separate `encrypted_attributes` table, never on the model's own table.

This package protects sensitive data, and mistakes can permanently destroy it. Treat changes to the payload format, key derivation and search hash with particular care (see [Encryptor format](#encryptor-format)).

## Commands

```bash
composer install

composer test                                  # all tests (vendor/bin/pest)
composer test-coverage                         # needs a coverage driver
vendor/bin/pest tests/Unit/EncryptorTest.php   # one file
vendor/bin/pest --filter="updates encrypted"   # one test by name
composer validate --strict                     # also runs in CI
composer audit                                 # also runs in CI
```

- Tests use in-memory SQLite through Testbench (`tests/TestCase.php`), so they leave nothing behind.
- Test env vars, including `DB_ENCRYPT_KEY`, are set in `phpunit.xml`. CI overrides the key, so a test relying on a specific key must set it with `config()`.
- There is no linter or static analyser. Follow the style of the surrounding code.
- `composer.lock` is generated on PHP 8.5 and needs PHP 8.4 or higher to install, because of Pest 5. To test an older stack, use `composer update` with narrower constraints in a copy of the repo. CI covers PHP 8.2 to 8.5 on Laravel 12 and 13, excluding PHP 8.2 on Laravel 13.

## Architecture

### Request flow

`Traits/HasEncryptedAttributes` hooks Eloquent events and delegates to `Http/Controllers/DbEncryptController`, which reads and writes `Models/EncryptedAttributes` using `Helper/Encryptor` for the crypto. Despite its namespace, the controller is not an HTTP controller and the package has no routes. It's the service layer, and it's bound with `bind()` so every resolve gets a new instance.

Models opt in with the trait plus `public array $encryptedProperties = [...]`. The event lifecycle:

| Event | What happens |
| --- | --- |
| `saving` | Throws `InvalidAttributeException` if an encrypted property is also a real column. Moves encrypted values out of `$attributes` into `$_encryptedAttributesBuffer`, recording whether each is dirty, so Eloquent doesn't write them to the model's table. |
| `saved` | Restores every value from the buffer, then upserts the dirty ones in one transaction. It runs after the insert because a new model needs its primary key. `null` or `''` deletes the row instead. |
| `retrieved` | Runs one query per model, sets decrypted values as raw attributes, and syncs them as original so the model isn't dirty. Missing rows become `null`. Skipped when the primary key wasn't selected, or inside `withoutDecryption()`. |
| `deleted` | Deletes the model's rows, unless it's a soft delete. A `forceDelete()` deletes them. |

### Storage and search

- Rows are keyed by `(object_type, object_id, attribute)`, and `object_type` is the model's **table name** (`getTable()`), not its class or morph alias. Values are cryptographically bound to these three columns, so changing one directly makes the value undecryptable. `db-encrypt:rename-table` handles table renames.
- The `object_id` column type is chosen at migration time from `db-encrypt.db.primary_key_format` (`int` or `uuid`), and applies to all models.
- `whereEncrypted()`, `orWhereEncrypted()` and `whereEncryptedIn()` match on `hash_index`, using `Encryptor::hashCandidates()`: the current key's hash, each previous key's and, with legacy support on, the v1.0 hash. Search is exact-match and case-sensitive, ignoring surrounding whitespace.
- The scopes use `whereHas`, so the model must share a connection with `encrypted_attributes`.

### Encryptor format

Two payload formats exist, told apart by the `v2:` prefix, since `:` isn't in the base64 alphabet:

| | Current (v2) | Legacy (v1.0.x, read-only) |
| --- | --- | --- |
| Layout | `"v2:" . base64(iv . tag . ciphertext)` | `base64(iv . ciphertext)` |
| Cipher | AES-256-GCM, with context as AAD | AES-256-CBC, unauthenticated |
| Key | HKDF-SHA256 of the base key, per purpose | SHA-512 hex digest, truncated by OpenSSL to 32 bytes |
| Search hash | HMAC-SHA256 of `context . "\0" . trim(value)` | SHA-256 of `trim(value)` |

Decryption tries `db-encrypt.key`, then each of `db-encrypt.previous_keys`. Encryption always uses `db-encrypt.key`. Legacy payloads and hashes are accepted only while `db-encrypt.legacy_support` is on.

**Changing a format breaks existing data unless a migration path is included.** To add a format, give it a new prefix, keep reading the old ones, and let `db-encrypt:re-encrypt` upgrade them. Never alter how legacy payloads are read: `tests/Unit/EncryptorTest.php` holds a frozen vector from the real v1.0.0 code, and `legacyEncrypt()` in `tests/Pest.php` is a deliberately frozen copy.

The context and hash scope come from `Encryptor::attributeContext()` and `searchContext()`. Build stored values with `EncryptedAttributes::payloadFor()` and read them with `decryptValue()`, so the two can't drift apart.

The `DbEncrypt` facade resolves to an `Encryptor` instance (container key `db-encrypt`), though `Encryptor`'s methods are static and the package's internal code calls them statically.

### Logging

`LogController` provides level-gated logging (`infoLow()`, `errorHigh()`, and so on) controlled by `db-encrypt.logging.level`, where `0` is off. The trait also logs failures directly with `Log::error()`, whatever the level.

## Rules

- **Never log, or put in an exception message, plaintext values or keys.** Name the attribute instead. `CONTRIBUTING.md` calls this out, and a test checks it at the most verbose log level.
- **Read and write raw attributes** (`getAttributes()`, `setRawAttributes()`), never `$model->{$prop}`. Going through the magic accessors runs mutators twice and encrypts accessor output.
- **Keep decrypted values synced as original** when loading, or every retrieved model becomes dirty.
- **Don't add state to `Encryptor`.** It's all static and reads config on each call, so tests and rotation can change keys at runtime.

## Behaviour to keep in mind

These come from the current code and are easy to get wrong when changing it:

- **Anything that skips model events skips encryption**: `saveQuietly()`, `withoutEvents()`, and query builder `insert()`, `upsert()` and `update()`. Writing an encrypted attribute that way hits a missing column and fails with a database error. Bulk deletes leave orphans for `db-encrypt:prune`.
- **Decrypted values are normal attributes after retrieval.** They appear in `toArray()` and JSON unless the model hides them.
- **Retrieving N models runs N queries** against `encrypted_attributes`, because `retrieved` fires per model before the collection exists. There's no batching. `withoutDecryption()` is the way out.
- **A single undecryptable row fails the whole query that loaded it**, by throwing `DecryptionException`.
- **Column conflict checks are cached per table per process** in `DbEncryptController::$validatedSchemas`. Call `flushSchemaCache()` if a schema changes in a running process.
- **`save()` isn't atomic across the two tables.** The model is written first, then the encrypted values in their own transaction. `saveOrFail()` covers both.
- **If another `saving` or `creating` listener cancels a save**, the encrypted values have already been moved to the buffer, and `saved` never restores them to the model's attributes. The stored data isn't affected.
- **`db-encrypt:prune` assumes the parent key column is `id`** unless given `--model` or `--key`. A table it can't read is skipped, and its rows are kept.
- **MySQL and MariaDB limit `encrypted_value` to 65,535 bytes**, enforced before writing because a non-strict server would truncate it.

## Conventions

- Include a test with code changes (`CONTRIBUTING.md`). Tests use Pest; `tests/Pest.php` binds the Testbench `TestCase` to both `Unit` and `Feature`. Test models are global classes at the bottom of each test file, so their names must be unique across files.
- Prefix PR titles with `Feat` or `Fix` (`CONTRIBUTING.md`).
- CI runs on `main` and `develop/**`. Release work uses `develop/vX.Y.Z` branches, with commit messages prefixed by the branch name.
- Record user-facing changes in `CHANGELOG.md` under the release version, and keep `README.md` and `SECURITY.md` accurate when behaviour changes.
