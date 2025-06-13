<?php

namespace Wazza\DbEncrypt\Traits;

use Wazza\DbEncrypt\Http\Controllers\DbEncryptController;

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
            // Optionally log or handle decryption errors
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
            // Optionally log or handle encryption errors
        }

        $this->_encryptedAttributesBuffer = [];
    }
}
