<?php

namespace Wazza\DbEncrypt\Traits;

use Wazza\DbEncrypt\Http\Controllers\DbEncryptController;

/**
 * Include this trait in your model to enable database encryption functionality.
 *
 * Note: You do NOT need to override the save() method or manually call encryptAttributes().
 * Encryption and decryption are handled automatically via model events by this trait.
 * Be sure to add the `encryptedProperties` array to your model to specify which attributes should be encrypted.
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
     * Get the encryption status of the model's attributes.
     *
     * @return bool
     */
    public function isEncrypted(): bool
    {
        // Get the current model instance the trait is called from
        $model = $this;

        if (!$model instanceof \Illuminate\Database\Eloquent\Model) {
            throw new \InvalidArgumentException('The isEncrypted method can only be called from an Eloquent model instance.');
        }

        // Check if the model's attributes are encrypted
        $dnEncryptController = app(DbEncryptController::class);
        return $dnEncryptController->isEncrypted($model);
    }

    /**
     * Boot the HasEncryptedAttributes trait for a model.
     * Automatically handles loading and saving encrypted attributes.
     */
    public static function bootHasEncryptedAttributes()
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
     */
    public function loadEncryptedAttributes(): void
    {
        if (!property_exists($this, 'encryptedProperties') || empty($this->encryptedProperties)) {
            return;
        }
        $dnEncryptController = app(\Wazza\DbEncrypt\Http\Controllers\DbEncryptController::class);
        $dnEncryptController->setModel($this);
        $dnEncryptController->decrypt();
    }

    /**
     * Remove encrypted attributes from the model's attributes before saving.
     * Store them temporarily for later encryption.
     */
    public function extractEncryptedAttributesForSave(): void
    {
        if (!property_exists($this, 'encryptedProperties') || empty($this->encryptedProperties)) {
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
     * Encrypt and save the encrypted attributes to the encrypted_attributes table after saving the model.
     */
    public function saveEncryptedAttributes(): void
    {
        if (
            !property_exists($this, 'encryptedProperties') ||
            empty($this->encryptedProperties) ||
            empty($this->_encryptedAttributesBuffer ?? [])
        ) {
            return;
        }
        $dnEncryptController = app(\Wazza\DbEncrypt\Http\Controllers\DbEncryptController::class);
        $dnEncryptController->setModel($this);
        foreach ($this->_encryptedAttributesBuffer as $prop => $value) {
            $this->{$prop} = $value; // restore for encryption
            $dnEncryptController->encryptProperty($prop);
        }
        unset($this->_encryptedAttributesBuffer);
    }
}
