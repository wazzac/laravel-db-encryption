<?php

namespace Wazza\DbEncrypt\Traits;

use Wazza\DbEncrypt\Http\Controllers\DnEncryptController;

/**
 * Include this trait in your model to enable database encryption functionality.
 *
 * public function save(array $options = [])
 * {
 *     parent::save($options);
 *     // Call the encryptAttributes method to encrypt the model's attributes.
 *     $this->encryptAttributes();
 * }
 */
trait HasEncryptedAttributes
{
    /**
     * Encrypt the model's attributes using the DnEncryptController.
     *
     * @return void
     */
    public function encryptAttributes(): void
    {
        // Get the current model instance the trait is called from
        $model = $this;

        if (!$model instanceof \Illuminate\Database\Eloquent\Model) {
            throw new \InvalidArgumentException('The encryptAttributes method can only be called from an Eloquent model instance.');
        }

        // Initiate a database encryption process
        $dnEncryptController = app(DnEncryptController::class);
        $dnEncryptController->setModel($model);
        $dnEncryptController->encrypt();
    }

    /**
     * Decrypt the model's attributes using the DnEncryptController.
     *
     * @return void
     */
    public function decryptAttributes(): void
    {
        // Get the current model instance the trait is called from
        $model = $this;

        if (!$model instanceof \Illuminate\Database\Eloquent\Model) {
            throw new \InvalidArgumentException('The decryptAttributes method can only be called from an Eloquent model instance.');
        }

        // Initiate a database decryption process
        $dnEncryptController = app(DnEncryptController::class);
        $dnEncryptController->setModel($model);
        $dnEncryptController->decrypt();
    }

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
        $dnEncryptController = app(DnEncryptController::class);
        return $dnEncryptController->isEncrypted($model);
    }

    /**
     * Decrypt the model's attributes before saving.
     *
     * @param array $options
     * @return void
     */
    public function save(array $options = []): void
    {
        // Decrypt the attributes before saving
        $this->decryptAttributes();

        // Call the parent save method
        parent::save($options);

        // Encrypt the attributes after saving
        $this->encryptAttributes();
    }

    /**
     * Delete the model and its encrypted attributes.
     *
     * @return void
     */
    public function delete(): void
    {
        // Get the current model instance the trait is called from
        $model = $this;

        if (!$model instanceof \Illuminate\Database\Eloquent\Model) {
            throw new \InvalidArgumentException('The delete method can only be called from an Eloquent model instance.');
        }

        // Delete the encrypted attributes using the DnEncryptController
        $dnEncryptController = app(DnEncryptController::class);
        $dnEncryptController->deleteEncryptedAttributes($model);

        // Call the parent delete method
        parent::delete();
    }

    /**
     * Restore the model and its encrypted attributes.
     *
     * @return void
     */
    public function restore(): void
    {
        // Get the current model instance the trait is called from
        $model = $this;

        if (!$model instanceof \Illuminate\Database\Eloquent\Model) {
            throw new \InvalidArgumentException('The restore method can only be called from an Eloquent model instance.');
        }

        // Restore the encrypted attributes using the DnEncryptController
        $dnEncryptController = app(DnEncryptController::class);
        $dnEncryptController->restoreEncryptedAttributes($model);

        // Call the parent restore method
        parent::restore();
    }

    /**
     * Force delete the model and its encrypted attributes.
     *
     * @return void
     */
    public function forceDelete(): void
    {
        // Get the current model instance the trait is called from
        $model = $this;

        if (!$model instanceof \Illuminate\Database\Eloquent\Model) {
            throw new \InvalidArgumentException('The forceDelete method can only be called from an Eloquent model instance.');
        }

        // Force delete the encrypted attributes using the DnEncryptController
        $dnEncryptController = app(DnEncryptController::class);
        $dnEncryptController->forceDeleteEncryptedAttributes($model);

        // Call the parent forceDelete method
        parent::forceDelete();
    }

}
