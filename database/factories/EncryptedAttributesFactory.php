<?php

namespace Wazza\DbEncrypt\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class EncryptedAttributesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'object_type' => $this->faker->randomElement(['type1', 'type2', 'type3']),
            'object_id' => $this->faker->numberBetween(1, 1000),
            'attribute' => $this->faker->word(),
            'hash_index' => $this->faker->sha256(),
            'encrypted_value' => $this->faker->text(200),
        ];
    }
}
