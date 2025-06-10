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
            'entity_id' => null,
            'state_id' => null,
            'type' => $this->faker->randomElement(['physical', 'billing', 'postal']),
            'building_name' => $this->faker->word(),
            'floor_number' => $this->faker->word(),
            'address1' => $this->faker->streetAddress(),
            'address2' => $this->faker->secondaryAddress(),
            'city' => $this->faker->city(),
            'postcode' => $this->faker->postcode(),
            'comments' => $this->faker->text(2000),
        ];
    }
}
