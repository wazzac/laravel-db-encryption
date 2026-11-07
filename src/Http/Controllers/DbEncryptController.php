<?php

namespace Wazza\DbEncrypt\Http\Controllers;

use Wazza\DbEncrypt\Http\Controllers\BaseController;
use Wazza\DbEncrypt\Models\EncryptedAttributes;
use Wazza\DbEncrypt\Helper\Encryptor;
use Wazza\DbEncrypt\Exceptions\InvalidAttributeException;
use Wazza\DbEncrypt\Exceptions\ModelNotSetException;
use Illuminate\Database\Eloquent\Model;

/**
 * DB Encrypt / Decrypt Controller
 *
 * @package Wazza\DbEncrypt\Http\Controllers
 * @version 1.1.0
 */

class DbEncryptController extends BaseController
{
    /**
     * MySQL and MariaDB TEXT columns hold 65,535 bytes, and can silently truncate beyond it.
     */
    private const MYSQL_TEXT_MAX_BYTES = 65535;

    /**
     * Table and property combinations already checked for column conflicts in this process.
     *
     * @var array<string, true>
     */
    private static array $validatedSchemas = [];

    /**
     * The model to sync
     *
     * @var \Illuminate\Database\Eloquent\Model|null
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
     * The log identifier without any model suffix.
     *
     * @var string
     */
    private string $baseLogIdentifier;

    /**
     * Create a new DbEncryptController instance and define the log identifier (blank will create a new one)
     *
     * @param string|null $logIdentifier
     * @return void
     */
    public function __construct(?string $logIdentifier = null)
    {
        // parent constructor
        parent::__construct($logIdentifier);

        // clear the properties
        $this->model = null;
        $this->encryptedProperties = [];
        $this->baseLogIdentifier = $this->logger->getLogIdentifier();
    }

    /**
     * Set the model to process.
     * Any property that is defined in the model will be encrypted in the `encrypted_attributes` table
     *
     * @param \Illuminate\Database\Eloquent\Model|null $model Null clears the current model.
     * @return $this
     * @throws InvalidAttributeException
     */
    public function setModel(?Model $model = null)
    {
        // -- set the model
        $this->model = $model;

        if ($model === null) {
            $this->encryptedProperties = [];
            $this->logger->setLogIdentifier($this->baseLogIdentifier);
            return $this;
        }

        // -- log the model set (and set the append to model type)
        $this->logger->setLogIdentifier($this->baseLogIdentifier . '[' . get_class($model) . ']');
        $this->logger->infoLow('DB Encrypt Model set. Class: `' . get_class($model) . '`, Table: `' . $model->getTable() . '`.');

        // -- set the properties in the model that will be encrypted
        $this->setEncryptedProperties($model->encryptedProperties ?? []);

        // done
        return $this;
    }

    /**
     * Set the properties to encrypt, after checking none of them is a real column on the model's table.
     * The schema lookup runs once per table per process, not once per model.
     *
     * @param array $propertyMapping
     * @return $this
     * @throws InvalidAttributeException
     */
    public function setEncryptedProperties(array $propertyMapping = []): self
    {
        if ($this->model && $propertyMapping !== []) {
            $this->assertNoColumnConflicts($propertyMapping);
        }

        // set the property mappings
        $this->encryptedProperties = $propertyMapping;
        $this->logger->infoLow('DB Encrypt Mapping: `' . json_encode($this->encryptedProperties) . '`');
        return $this;
    }

    /**
     * Throw if an encrypted property also exists as a column.
     *
     * @param array $properties
     * @return void
     * @throws InvalidAttributeException
     */
    private function assertNoColumnConflicts(array $properties): void
    {
        $table = $this->model->getTable();
        $connection = $this->model->getConnection();
        $cacheKey = $connection->getName() . '|' . $table . '|' . implode(',', $properties);

        if (isset(self::$validatedSchemas[$cacheKey])) {
            return;
        }

        $columns = array_map('strtolower', $connection->getSchemaBuilder()->getColumnListing($table));

        foreach ($properties as $property) {
            if (in_array(strtolower($property), $columns, true)) {
                throw InvalidAttributeException::columnConflict($property, $table);
            }
        }

        // an empty listing means the table isn't there yet, so the result proves nothing
        if ($columns !== []) {
            self::$validatedSchemas[$cacheKey] = true;
        }
    }

    /**
     * Forget which tables have been checked, for use after changing a schema in a running process.
     *
     * @return void
     */
    public static function flushSchemaCache(): void
    {
        self::$validatedSchemas = [];
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
     * @throws ModelNotSetException
     */
    public function encryptAll()
    {
        // check if the model is set
        if (!$this->isModelDefined()) {
            throw ModelNotSetException::required();
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
     * @throws ModelNotSetException
     */
    public function encryptProperty(string $property)
    {
        // check if the model is set
        if (!$this->isModelDefined()) {
            throw ModelNotSetException::required();
        }

        // encrypt the property
        $this->encrypt($property);
    }

    /**
     * Encrypt all of the model defined ($this->encryptedProperties) properties.
     * If $property is provided, only that property will be encrypted.
     *
     * Values are read from the model's raw attributes, so a mutator or cast has already been
     * applied once and an accessor's output is never what gets stored.
     *
     * @param string|null $property The property to encrypt. If null, all properties will be encrypted.
     * @return void
     * @throws ModelNotSetException
     * @throws InvalidAttributeException
     */
    public function encrypt(
        ?string $property = null
    ) {
        // check if the model is set
        if (!$this->isModelDefined()) {
            throw ModelNotSetException::required();
        }

        // if property is defined, make sure it is in the encrypted properties
        if ($property !== null && !in_array($property, $this->encryptedProperties, true)) {
            throw InvalidAttributeException::undefinedProperty($property);
        }

        // the rows are keyed on the primary key, so it must exist by now
        if ($this->model->getKey() === null) {
            throw ModelNotSetException::notPersisted();
        }
        $this->logger->infoLow('Encrypting properties for model: ' . $this->model->getTable() . ', property: ' . ($property ?? 'all'));

        $attributes = $this->model->getAttributes();

        // loop through the defined encrypted properties
        foreach ($this->encryptedProperties as $prop) {
            // check if the property is defined in the model's attributes
            if ($property === null || $prop === $property) {
                if (!array_key_exists($prop, $attributes)) {
                    continue;
                }

                $value = $this->stringifyValue($prop, $attributes[$prop]);
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
                $payload = EncryptedAttributes::payloadFor(
                    $this->model->getTable(),
                    $this->model->getKey(),
                    $prop,
                    $value
                );
                $this->assertFitsColumn($prop, $payload['encrypted_value']);

                EncryptedAttributes::updateOrCreate(
                    [
                        'object_type' => $this->model->getTable(),
                        'object_id' => $this->model->getKey(),
                        'attribute' => $prop,
                    ],
                    $payload
                );

                // Do NOT log the actual value
                $this->logger->infoLow('Encrypted property: ' . $prop . ' [value hidden for security]');
            }
        }

        // done.
        $this->logger->infoLow('Encryption completed for model: ' . $this->model->getTable() . ', properties: ' . json_encode($this->encryptedProperties));
    }

    /**
     * Convert a raw attribute to the string that gets encrypted.
     * Booleans become "1" and "0", because casting false to a string would erase the value.
     *
     * @param string $property
     * @param mixed $value
     * @return string|null
     * @throws InvalidAttributeException
     */
    private function stringifyValue(string $property, mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => throw InvalidAttributeException::unsupportedValue($property),
        };
    }

    /**
     * Refuse a value the column can't hold, rather than let MySQL truncate it into an unrecoverable payload.
     *
     * @param string $property
     * @param string $encryptedValue
     * @return void
     * @throws InvalidAttributeException
     */
    private function assertFitsColumn(string $property, string $encryptedValue): void
    {
        $driver = (new EncryptedAttributes())->getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true) && strlen($encryptedValue) > self::MYSQL_TEXT_MAX_BYTES) {
            throw InvalidAttributeException::valueTooLarge($property, self::MYSQL_TEXT_MAX_BYTES);
        }
    }

    /**
     * Decrypt all of the model defined ($this->encryptedProperties) properties.
     * If $property is provided, only that property will be decrypted.
     *
     * Values are set as raw attributes and synced as original, so loading doesn't run mutators
     * and doesn't mark the model dirty.
     *
     * @param mixed $property The property to decrypt. If null, all properties will be decrypted.
     * @return void
     * @throws ModelNotSetException
     * @throws InvalidAttributeException
     */
    public function decrypt($property = null)
    {
        // check if the model is set
        if (!$this->isModelDefined()) {
            throw ModelNotSetException::required();
        }

        // if property is defined, make sure it is in the encrypted properties
        if ($property !== null && !in_array($property, $this->encryptedProperties, true)) {
            throw InvalidAttributeException::undefinedProperty($property);
        }
        $this->logger->infoLow('Decrypting properties for model: ' . $this->model->getTable() . ', property: ' . ($property ?? 'all'));

        $properties = $property === null ? $this->encryptedProperties : [$property];

        // one query per model, however many properties it has
        $records = EncryptedAttributes::where('object_type', $this->model->getTable())
            ->where('object_id', $this->model->getKey())
            ->whereIn('attribute', $properties)
            ->get()
            ->keyBy('attribute');

        // properties without a row are set to null
        $values = [];
        foreach ($properties as $prop) {
            $record = $records->get($prop);
            $values[$prop] = $record && $record->encrypted_value ? $record->decryptValue() : null;
        }

        $this->model->setRawAttributes(array_merge($this->model->getAttributes(), $values));
        $this->model->syncOriginalAttributes($properties);

        // done.
        $this->logger->infoLow('Decryption completed for model: ' . $this->model->getTable() . ', properties: ' . json_encode($properties));
    }

    /**
     * Delete every encrypted row stored for the model, whether or not the property is still listed.
     *
     * @return void
     * @throws ModelNotSetException
     */
    public function deleteAll(): void
    {
        // check if the model is set
        if (!$this->isModelDefined()) {
            throw ModelNotSetException::required();
        }

        EncryptedAttributes::where('object_type', $this->model->getTable())
            ->where('object_id', $this->model->getKey())
            ->delete();

        $this->logger->infoLow('Deleted encrypted attributes for model: ' . $this->model->getTable());
    }
}
