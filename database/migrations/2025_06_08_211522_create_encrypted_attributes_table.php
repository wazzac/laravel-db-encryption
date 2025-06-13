<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEncryptedAttributesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // load the local db primary key format
        $dbPkFormat = config('db-encrypt.db.primary_key_format', 'int');

        // create the table
        Schema::create('encrypted_attributes', function (Blueprint $table) use ($dbPkFormat) {
            // define tables engine and charset
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // define columns
            $table->id();
            $table->string('object_type', 64)->nullable()->comment('The local object type - e.g. order, entity, user, etc.');
            if ($dbPkFormat === 'uuid') {
                $table->string('object_id', 36)->nullable()->comment('The local object unique ID (primary key - `uuid`)');
            } else {
                $table->unsignedBigInteger('object_id')->nullable()->comment('The local object unique ID (primary key - auto-incremented `int`)');
            }
            $table->string('attribute', 64)->nullable()->comment('The attribute name, e.g. email, phone, etc.');
            $table->string('hash_index', 64)->nullable()->comment('SHA-256 hash of the attribute value (hex format) for fast searching');
            $table->text('encrypted_value')->nullable()->comment('The encrypted value of the attribute (Base64 or binary-safe string)');
            $table->timestamps();

            // add some indexes (we need one on all columns for searching)
            $table->index('object_id');
            $table->index('hash_index');
            $table->index('attribute');

            // composite indexes
            $table->unique(['object_type', 'object_id', 'attribute'], 'object_type_attribute_unique');
            $table->index(['object_type', 'attribute', 'hash_index'], 'object_type_attribute_hash_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('encrypted_attributes');
    }
}
