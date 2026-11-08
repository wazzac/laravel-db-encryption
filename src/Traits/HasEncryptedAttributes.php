<?php

namespace Wazza\DbEncrypt\Traits;

use Wazza\DbEncrypt\Http\Controllers\DbEncryptController;
use Wazza\DbEncrypt\Models\EncryptedAttributes;
use Wazza\DbEncrypt\Helper\Encryptor;
use Wazza\DbEncrypt\Exceptions\DecryptionException;
use Wazza\DbEncrypt\Exceptions\EncryptionException;
use Wazza\DbEncrypt\Exceptions\InvalidAttributeException;
use Illuminate\Support\Facades\Log;

/**
 * Include this trait in your model to enable database encryption functionality.
 *
 * Note: You do NOT need to override the save() method or manually call encryptAttributes().
 * Encryption and decryption are handled automatically via model events by this trait.
 * Be sure to add the `encryptedProperties` array to your model to specify which attributes should be encrypted.
 * If `encryptedProperties` is not defined, no attributes will be encrypted.
 *
 * Anything that skips model events skips this trait too: saveQuietly(), withoutEvents(),
 * and query builder writes such as insert(), upsert() and update().
 */
trait HasEncryptedAttributes
{
    /**
     * Buffer for temporarily holding encrypted attributes during save.
     *
     * @var array<string, array{value: mixed, dirty: bool}>
     */
    protected array $_encryptedAttributesBuffer = [];

    /**
     * When true, retrieved models are not decrypted.
     *
     * @var bool
     */
    protected static bool $encryptedAttributesLoadingDisabled = false;

    /**
     * Boot the HasEncryptedAttributes trait for a model.
     *
     * @return void
     * @throws InvalidAttributeException
     */
    public static function bootHasEncryptedAttributes(): void
    {
        static::retrieved(function ($model) {
            $model->loadEncryptedAttributes();
        });
        static::saving(function ($model) {
            $model->extractEncryptedAttributesForSave();
        });
        static::saved(function ($model) {
            $model->saveEncryptedAttributes();
        });
        static::deleted(function ($model) {
            // a soft delete can be restored, so its encrypted values have to stay
            if (!method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                $model->deleteEncryptedAttributes();
            }
        });
    }

    /**
     * Run a callback without decrypting the models it retrieves, avoiding the query per model
     * when the encrypted values aren't needed. Saving such a model leaves its encrypted rows alone.
     *
     * @param callable $callback
     * @return mixed The callback's return value
     */
    public static function withoutDecryption(callable $callback): mixed
    {
        $previous = static::$encryptedAttributesLoadingDisabled;
        static::$encryptedAttributesLoadingDisabled = true;

        try {
            return $callback();
        } finally {
            static::$encryptedAttributesLoadingDisabled = $previous;
        }
    }

    /**
     * Get a controller bound to this model.
     *
     * @return DbEncryptController
     * @throws InvalidAttributeException
     */
    protected function encryptedAttributesController(): DbEncryptController
    {
        return app(DbEncryptController::class)->setModel($this);
    }

    /**
     * Load and decrypt encrypted attributes from the encrypted_attributes table.
     *
     * @return void
     * @throws InvalidAttributeException
     * @throws DecryptionException
     */
    public function loadEncryptedAttributes(): void
    {
        if (empty($this->encryptedProperties ?? []) || static::$encryptedAttributesLoadingDisabled) {
            return;
        }

        // a query that didn't select the primary key can't be matched to any rows
        if ($this->getKey() === null) {
            return;
        }

        try {
            $this->encryptedAttributesController()->decrypt();
        } catch (InvalidAttributeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logEncryptedAttributesFailure('decrypt', $e);

            throw DecryptionException::attributeFailed(static::class, $e);
        }
    }

    /**
     * Remove encrypted attributes from the model's attributes before saving.
     * Also rejects a misconfigured model before anything is written.
     *
     * @return void
     * @throws InvalidAttributeException
     */
    public function extractEncryptedAttributesForSave(): void
    {
        if (empty($this->encryptedProperties ?? [])) {
            return;
        }

        $this->encryptedAttributesController();

        $this->_encryptedAttributesBuffer = [];

        // getAttributes() first, so values held by class casts have been written back
        $attributes = $this->getAttributes();

        foreach ($this->encryptedProperties as $prop) {
            if (array_key_exists($prop, $attributes)) {
                $this->_encryptedAttributesBuffer[$prop] = [
                    'value' => $attributes[$prop],
                    'dirty' => !$this->originalIsEquivalent($prop),
                ];

                // a cached cast would write the value straight back into the attributes
                unset($this->attributes[$prop], $this->classCastCache[$prop], $this->attributeCastCache[$prop]);
            }
        }
    }

    /**
     * Restore the attributes removed before saving, and store any that changed.
     * All the writes for one save happen in a single transaction.
     *
     * @return void
     * @throws InvalidAttributeException
     * @throws EncryptionException
     */
    public function saveEncryptedAttributes(): void
    {
        if (empty($this->encryptedProperties ?? []) || empty($this->_encryptedAttributesBuffer)) {
            return;
        }

        $buffer = $this->_encryptedAttributesBuffer;
        $this->_encryptedAttributesBuffer = [];

        $changed = [];
        foreach ($buffer as $prop => $entry) {
            $this->attributes[$prop] = $entry['value'];

            if ($entry['dirty']) {
                $changed[$prop] = $entry['value'];
            }
        }

        if ($changed === []) {
            return;
        }

        try {
            $controller = $this->encryptedAttributesController();

            (new EncryptedAttributes())->getConnection()->transaction(function () use ($controller, $changed) {
                foreach (array_keys($changed) as $prop) {
                    $controller->encryptProperty($prop);
                }
            });
        } catch (InvalidAttributeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logEncryptedAttributesFailure('encrypt', $e);

            throw EncryptionException::attributeFailed(static::class, $e);
        }

        // Eloquent synced its changes before this event, without these attributes
        $this->changes = array_merge($this->changes, $changed);
    }

    /**
     * Delete this model's rows from the encrypted_attributes table.
     *
     * @return void
     */
    public function deleteEncryptedAttributes(): void
    {
        if ($this->getKey() === null) {
            return;
        }

        app(DbEncryptController::class)->setModel($this)->deleteAll();
    }

    /**
     * Log a failure with its context. The exception messages raised here never hold plaintext.
     *
     * @param string $action
     * @param \Throwable $e
     * @return void
     */
    private function logEncryptedAttributesFailure(string $action, \Throwable $e): void
    {
        Log::error('DB Encrypt: Failed to ' . $action . ' attributes for model ' . static::class . ' (ID: ' . ($this->getKey() ?? 'n/a') . '): ' . $e->getMessage());
    }

    /**
     * Scope a query to filter by an encrypted property.
     * Example: $users = User::whereEncrypted('social_security_number', '123-45-6789')->get();
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $attribute
     * @param string $value
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws InvalidAttributeException
     */
    public function scopeWhereEncrypted($query, string $attribute, string $value)
    {
        return $this->applyEncryptedSearch($query, $attribute, [$value], 'whereHas');
    }

    /**
     * Scope a query to also include rows matching an encrypted property.
     * Example: $users = User::where('active', true)->orWhereEncrypted('ssn', '123-45-6789')->get();
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $attribute
     * @param string $value
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws InvalidAttributeException
     */
    public function scopeOrWhereEncrypted($query, string $attribute, string $value)
    {
        return $this->applyEncryptedSearch($query, $attribute, [$value], 'orWhereHas');
    }

    /**
     * Scope a query to filter by any of several values of an encrypted property.
     * Example: $users = User::whereEncryptedIn('ssn', ['123-45-6789', '987-65-4321'])->get();
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $attribute
     * @param array<int, string> $values
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws InvalidAttributeException
     */
    public function scopeWhereEncryptedIn($query, string $attribute, array $values)
    {
        return $this->applyEncryptedSearch($query, $attribute, $values, 'whereHas');
    }

    /**
     * Apply a search on the hash index.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $attribute
     * @param array<int, string> $values
     * @param string $method whereHas or orWhereHas
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws InvalidAttributeException
     */
    private function applyEncryptedSearch($query, string $attribute, array $values, string $method)
    {
        if (!in_array($attribute, $this->encryptedProperties ?? [], true)) {
            throw InvalidAttributeException::undefinedProperty($attribute);
        }

        $context = Encryptor::searchContext($this->getTable(), $attribute);

        $hashes = [];
        foreach ($values as $value) {
            array_push($hashes, ...Encryptor::hashCandidates((string) $value, $context));
        }

        return $query->{$method}('encryptedAttributesRelation', function ($q) use ($attribute, $hashes) {
            $q->where('attribute', $attribute)
                ->whereIn('hash_index', $hashes);
        });
    }

    /**
     * Define a relationship to the encrypted_attributes table.
     */
    public function encryptedAttributesRelation()
    {
        return $this->hasMany(
            EncryptedAttributes::class,
            'object_id',
            $this->getKeyName()
        )->where('object_type', $this->getTable());
    }
}
