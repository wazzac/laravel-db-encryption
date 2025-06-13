<?php

namespace Wazza\DbEncrypt\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Wazza\DbEncrypt\Database\Factories\EncryptedAttributesFactory;

class EncryptedAttributes extends Model
{
    use HasFactory;

    /**
     * The factory for creating instances of this model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public static function newFactory()
    {
        return EncryptedAttributesFactory::new();
    }

    /**
     * The database table used by the model.
     * @var string
     */
    protected $table = 'encrypted_attributes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'object_id',
        'object_type',
        'attribute',
        'hash_index',
        'encrypted_value',
    ];

    /**
     * Get the validation rules that apply based on the migration schema.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        // load the local db primary key format
        $dbPkFormat = config('db-encrypt.db.primary_key_format', 'int');

        // define the validation rules
        return [
            'object_type'       => ['nullable', 'string', 'max:64'],
            'object_id'         => ['nullable', $dbPkFormat === 'uuid' ? 'string' : 'numeric'],
            'attribute'         => ['nullable', 'string', 'max:64'],
            'hash_index'        => ['nullable', 'string', 'max:64'],
            'encrypted_value'   => ['nullable', 'string', 'max:65535'], // 64KB max for text fields
        ];
    }

    // --------------------------------------------
    // Helpers
    // --------------------------------------------

    /**
     * Get the encryption record by the local object type, ID, and attribute.
     *
     * @param string $type The local object type. e.g. 'order', 'user', etc.
     * @param string $id The local object ID. e.g. '123', 'abc-uuid', etc.
     * @param string $attribute The attribute name. e.g. 'email', 'phone', etc.
     * @return self|null
     */
    public static function viaAttributeId(
        string $type,
        string $id,
        string $attribute
    ): ?self {
        return self::where('object_type', $type)
            ->where('object_id', $id)
            ->where('attribute', $attribute)
            ->first();
    }

    /**
     * Get the encryption record by the local object type, attribute, and hash index.
     *
     * @param string $type The local object type. e.g. 'order', 'user', etc.
     * @param string $attribute The attribute name. e.g. 'email', 'phone', etc.
     * @param string $hashIndex The SHA-256 hash of the attribute value (hex format).
     * @return self|null
     */
    public static function viaAttributeHash(
        string $type,
        string $attribute,
        string $hashIndex
    ): ?self {
        return self::where('object_type', $type)
            ->where('attribute', $attribute)
            ->where('hash_index', $hashIndex)
            ->first();
    }
}
