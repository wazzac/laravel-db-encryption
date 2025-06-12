<?php

namespace Wazza\DbEncrypt\Http\Controllers;

use Wazza\DbEncrypt\Http\Controllers\BaseController;
use Wazza\DbEncrypt\Models\EncryptedAttributes;
use Wazza\DbEncrypt\Helper\Encryptor;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Container\BindingResolutionException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Exception;

/**
 * DB Encrypt / Decrypt Controller
 *
 * @package Wazza\DbEncrypt\Http\Controllers
 * @version 1.0.0
 */

class DbEncryptController extends BaseController
{
    /**
     * The model to sync
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    private $model;

    /**
     * Property to define the Model properties that will be encrypted.
     * Structure:
     *   $encryptedProperties = [
     *     'social_security_number',
     *     'business_address',
     *     'business_city',
     *   ];
     *
     * @var array
     */
    private $encryptedProperties = [];

    /**
     * Create a new CrmController instance and define the log identifier (blank will create a new one)
     *
     * @param string|null $logIdentifier
     * @return void
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function __construct(?string $logIdentifier = null)
    {
        // parent constructor
        parent::__construct($logIdentifier);

        // clear the properties
        $this->model = null;
        $this->encryptedProperties = [];
    }

    /**
     * Set the model to process.
     * Any property that is defined in the model will be encrypted in the `encrypted_attributes` table
     *
     * @param \Illuminate\Database\Eloquent\Model|null $model
     * @return $this
     */
    public function setModel(?Model $model = null)
    {
        // -- set the model
        $this->model = $model;

        // -- log the model set (and set the append to model type)
        $this->logger->setLogIdentifier('[' . get_class($this->model) . ']', true);
        $this->logger->infoLow('DB Encrypt Model set. Class: `' . get_class($this->model) . '`, Table: `' . $this->model->getTable() . '`.');

        // -- set the properties in the model that will be encrypted
        $this->setEncryptedProperties($model->encryptedProperties ?? []);

        // done
        return $this;
    }

    /**
     * Set the property mapping for the CRM provider
     *
     * @param array $propertyMapping
     * @return $this
     */
    public function setEncryptedProperties(array $propertyMapping = []): self
    {
        // Check for conflicts: encrypted property must not exist as a column in the model's table
        $table = $this->model ? $this->model->getTable() : null;
        $schema = $this->model ? $this->model->getConnection()->getSchemaBuilder() : null;
        if ($table && $schema) {
            foreach ($propertyMapping as $prop) {
                if ($schema->hasColumn($table, $prop)) {
                    throw new Exception("Cannot specify encrypted property '{$prop}' because it already exists as a column in the model's table '{$table}'.");
                }
            }
        }
        // set the property mappings
        $this->encryptedProperties = $propertyMapping;
        $this->logger->infoLow('DB Encrypt Mapping: `' . json_encode($this->encryptedProperties) . '`');
        return $this;
    }

    /**
     * Get the encrypted properties defined in the model.
     *
     * @return array
     */
    public function getEncryptedProperties(): array
    {
        // return the encrypted properties
        return $this->encryptedProperties;
    }

    /**
     * Get the model that is set.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getModel(): ?Model
    {
        // return the model
        return $this->model;
    }

    /**
     * Check if the model is defined.
     *
     * @return bool
     */
    public function isModelDefined(): bool
    {
        // check if the model is defined
        return $this->model instanceof Model;
    }

    /**
     * Encrypt all properties of the model.
     * This method will encrypt all properties defined in the model's encrypted properties.
     *
     * @return void
     * @throws Exception
     */
    public function encryptAll()
    {
        // check if the model is set
        if (!$this->isModelDefined()) {
            throw new Exception('Model is not set. Please set the model using the `setModel` method.');
        }

        // encrypt all properties
        $this->encrypt();
    }

    /**
     * Encrypt a specific property of the model.
     * This method will only encrypt the property if it is defined in the model's encrypted properties.
     *
     * @param string $property
     * @return void
     * @throws Exception
     */
    public function encryptProperty(string $property)
    {
        // check if the model is set
        if (!$this->isModelDefined()) {
            throw new Exception('Model is not set. Please set the model using the `setModel` method.');
        }

        // encrypt the property
        $this->encrypt($property);
    }

    /**
     * Encrypt all of the model defined ($this->encryptedProperties) properties.
     * If $property is provided, only that property will be encrypted.
     *
     * @param mixed $property The property to encrypt. If null, all properties will be encrypted.
     * @return void
     * @throws Exception
     */
    public function encrypt(
        $property = null
    ) {
        if (!$this->isModelDefined()) {
            throw new Exception('Model is not set. Please set the model using the `setModel` method.');
        }
        if ($property !== null && !in_array($property, $this->encryptedProperties, true)) {
            throw new Exception('Property `' . $property . '` is not defined in the encrypted properties.');
        }
        $this->logger->infoLow('Encrypting properties for model: ' . $this->model->getTable() . ', property: ' . ($property ?? 'all'));
        foreach ($this->encryptedProperties as $prop) {
            if ($property === null || $prop === $property) {
                if (array_key_exists($prop, $this->model->getAttributes())) {
                    // Use Eloquent attribute check
                    $value = $this->model->{$prop};
                    if ($value === null || $value === '') {
                        // if the value was encrypted, hard delete it form the encrypted_attributes table
                        EncryptedAttributes::where([
                            'object_type' => $this->model->getTable(),
                            'object_id' => $this->model->getKey(),
                            'attribute' => $prop,
                        ])->delete();

                        // done
                        continue;
                    }

                    // Encrypt the value and store it in the encrypted_attributes table
                    $encryptedValue = Encryptor::encrypt($value);
                    EncryptedAttributes::updateOrCreate(
                        [
                            'object_type' => $this->model->getTable(),
                            'object_id' => $this->model->getKey(),
                            'attribute' => $prop,
                        ],
                        [
                            'hash_index' => Encryptor::hash($value),
                            'encrypted_value' => $encryptedValue,
                        ]
                    );

                    // Do NOT log the actual value
                    $this->logger->infoLow('Encrypted property: ' . $prop . ' [value hidden for security]');
                } else {
                    continue;
                }
            }
        }
        $this->logger->infoLow('Encryption completed for model: ' . $this->model->getTable() . ', properties: ' . json_encode($this->encryptedProperties));
    }

    /**
     * Decrypt all of the model defined ($this->encryptedProperties) properties.
     * If $property is provided, only that property will be decrypted.
     *
     * @param mixed $property The property to decrypt. If null, all properties will be decrypted.
     * @return void
     * @throws Exception
     */
    public function decrypt($property = null)
    {
        if (!$this->isModelDefined()) {
            throw new Exception('Model is not set. Please set the model using the `setModel` method.');
        }
        if ($property !== null && !in_array($property, $this->encryptedProperties, true)) {
            throw new Exception('Property `' . $property . '` is not defined in the encrypted properties.');
        }
        $this->logger->infoLow('Decrypting properties for model: ' . $this->model->getTable() . ', property: ' . ($property ?? 'all'));
        foreach ($this->encryptedProperties as $prop) {
            if ($property === null || $prop === $property) {
                $encryptedAttribute = EncryptedAttributes::where([
                    'object_type' => $this->model->getTable(),
                    'object_id' => $this->model->getKey(),
                    'attribute' => $prop,
                ])->first();
                if ($encryptedAttribute && $encryptedAttribute->encrypted_value) {
                    $decryptedValue = Encryptor::decrypt($encryptedAttribute->encrypted_value);
                    $this->model->{$prop} = $decryptedValue;
                    $this->logger->infoLow('Decrypted property: ' . $prop . ' - success.');
                } else {
                    $this->model->{$prop} = null;
                    $this->logger->infoLow('Decrypted property: ' . $prop . ' - not found, set to null.');
                }
            }
        }
        $this->logger->infoLow('Decryption completed for model: ' . $this->model->getTable() . ', properties: ' . json_encode($this->encryptedProperties));
    }
}
