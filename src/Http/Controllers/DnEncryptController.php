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
 * Sync Class CrmController
 * Example: (new CrmController())->setModel($user)->execute();
 *
 * @package Wazza\DbEncrypt\Http\Controllers
 * @version 1.0.0
 * @todo convert the log class to be injected into the controller instead of using the facade
 */

class DnEncryptController extends BaseController
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
     * Encrypt all of the model defined ($this->encryptedProperties) properties.
     * If $property is provided, only that property will be encrypted.
     *
     * @param mixed $property The property to encrypt. If null, all properties will be encrypted.
     * @return void
     * @throws Exception
     */
    public function encrypt($property = null)
    {
        // check if the model is set
        if (!$this->isModelDefined()) {
            throw new Exception('Model is not set. Please set the model using the `setModel` method.');
        }

        // check if the property is set
        if ($property !== null && !in_array($property, $this->encryptedProperties)) {
            throw new Exception('Property `' . $property . '` is not defined in the encrypted properties.');
        }

        // encrypt the properties
        $this->logger->infoLow('Encrypting properties for model: ' . get_class($this->model) . ', property: ' . ($property ?? 'all'));

        // loop through the encrypted properties
        foreach ($this->encryptedProperties as $prop) {
            // making sure the provided property to encrypt (if any) is defined in the model encrypted properties list
            if ($property === null || $prop === $property) {
                // check if the property exists in the model
                if (property_exists($this->model, $prop)) {
                    // encrypt the property
                    $value = $this->model->{$prop};
                    $encryptedValue = Encryptor::encrypt($value);

                    // save the encrypted value in the EncryptedAttributes model
                    EncryptedAttributes::updateOrCreate(
                        [
                            'object_type' => get_class($this->model),
                            'object_id' => $this->model->getKey(),
                            'attribute' => $prop,
                        ],
                        [
                            'hash_index' => Encryptor::hash($value),
                            'encrypted_value' => $encryptedValue,
                        ]
                    );

                    // log the encryption
                    $this->logger->infoLow('Encrypted property: ' . $prop . ', value: ' . $value);
                } else {
                    throw new Exception('Property `' . $prop . '` does not exist in the model.');
                }
            }
        }

        // log the encryption
        $this->logger->infoLow('Encryption completed for model: ' . get_class($this->model) . ', properties: ' . json_encode($this->encryptedProperties));
    }
}
