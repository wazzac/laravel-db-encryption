<?php

namespace Wazza\DbEncrypt\Traits;

use Wazza\DbEncrypt\Http\Controllers\DbEncryptController;
use Wazza\DbEncrypt\Models\EncryptedAttributes;
use Wazza\DbEncrypt\Helper\Encryptor;
use Illuminate\Support\Facades\Log;

/**
 * Include this trait in your model to enable database encryption functionality.
 *
 * Note: You do NOT need to override the save() method or manually call encryptAttributes().
 * Encryption and decryption are handled automatically via model events by this trait.
 * Be sure to add the `encryptedProperties` array to your model to specify which attributes should be encrypted.
 * If `encryptedProperties` is not defined, no attributes will be encrypted.
 */
trait HasEncryptedAttributes
{
    /**
     * Buffer for temporarily holding encrypted attributes during save.
     *
     * @var array
     */
    protected array $_encryptedAttributesBuffer = [];

    /**
     * Boot the HasEncryptedAttributes trait for a model.
     *
     * @return void
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
    }

    /**
     * Load and decrypt encrypted attributes from the encrypted_attributes table.
     *
     * @return void
     */
    public function loadEncryptedAttributes(): void
    {
        if (empty($this->encryptedProperties ?? [])) {
            return;
        }

        try {
            $dnEncryptController = app(DbEncryptController::class);
            $dnEncryptController->setModel($this);
            $dnEncryptController->decrypt();
        } catch (\Throwable $e) {
            // Log the error with context, but never log sensitive values
            Log::error('DB Encrypt: Failed to decrypt attributes for model ' . get_class($this) . ' (ID: ' . ($this->getKey() ?? 'n/a') . '): ' . $e->getMessage());

            // Optionally, throw a custom exception for the application layer
            throw new \RuntimeException('Failed to decrypt encrypted attributes for this model. Please check the logs for details.', 0, $e);
        }
    }

    /**
     * Remove encrypted attributes from the model's attributes before saving.
     *
     * @return void
     */
    public function extractEncryptedAttributesForSave(): void
    {
        if (empty($this->encryptedProperties ?? [])) {
            return;
        }

        $this->_encryptedAttributesBuffer = [];

        foreach ($this->encryptedProperties as $prop) {
            if (array_key_exists($prop, $this->attributes)) {
                $this->_encryptedAttributesBuffer[$prop] = $this->attributes[$prop];
                unset($this->attributes[$prop]);
            }
        }
    }

    /**
     * Encrypt and save the encrypted attributes after saving the model.
     *
     * @return void
     */
    public function saveEncryptedAttributes(): void
    {
        if (empty($this->encryptedProperties ?? []) || empty($this->_encryptedAttributesBuffer)) {
            return;
        }

        try {
            $dnEncryptController = app(DbEncryptController::class);
            $dnEncryptController->setModel($this);

            foreach ($this->_encryptedAttributesBuffer as $prop => $value) {
                $this->{$prop} = $value;
                $dnEncryptController->encryptProperty($prop);
            }
        } catch (\Throwable $e) {
            // Log the error with context, but never log sensitive values
            Log::error('DB Encrypt: Failed to encrypt attributes for model ' . get_class($this) . ' (ID: ' . ($this->getKey() ?? 'n/a') . '): ' . $e->getMessage());

            // Optionally, throw a custom exception for the application layer
            throw new \RuntimeException('Failed to encrypt attributes for this model. Please check the logs for details.', 0, $e);
        }

        $this->_encryptedAttributesBuffer = [];
    }

    /**
     * Scope a query to filter by an encrypted property.
     * Example: $users = User::whereEncrypted('social_security_number', '123-45-6789')->get();
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $attribute
     * @param string $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereEncrypted($query, string $attribute, string $value)
    {
        $hash = Encryptor::hash($value);

        return $query->whereHas('encryptedAttributesRelation', function ($q) use ($attribute, $hash) {
            $q->where('attribute', $attribute)
                ->where('hash_index', $hash);
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
