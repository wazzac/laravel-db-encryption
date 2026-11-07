<?php

namespace Wazza\DbEncrypt\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Wazza\DbEncrypt\Models\EncryptedAttributes;

class EncryptedAttributesFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<EncryptedAttributes>
     */
    protected $model = EncryptedAttributes::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'object_type' => 'users',
            'object_id' => $this->faker->unique()->randomNumber(5),
            'attribute' => $this->faker->randomElement(['social_security_number', 'private_note', 'custom_field']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Encrypt after any overrides are applied, because the payload is bound to the final row.
     *
     * @return static
     */
    public function configure(): static
    {
        return $this->afterMaking(function (EncryptedAttributes $record) {
            $record->fill(EncryptedAttributes::payloadFor(
                $record->object_type,
                $record->object_id,
                $record->attribute,
                $this->faker->text(50)
            ));
        });
    }
}
