<?php

namespace Wazza\DbEncrypt\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Wazza\DbEncrypt\Helper\Encryptor;

class EncryptedAttributesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $randomText = $this->faker->text(50);
        $encryptedText = Encryptor::encrypt($randomText);
        $hashIndex = Encryptor::hash($randomText);
        return [
            'object_type' => 'users',
            'object_id' => $this->faker->randomNumber(5),
            'attribute' => $this->faker->randomElement(['social_security_number', 'private_note', 'custom_field']),
            'hash_index' => $hashIndex,
            'encrypted_value' => $encryptedText,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
