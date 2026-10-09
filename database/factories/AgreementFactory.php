<?php

namespace Database\Factories;

use App\Models\Agreement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agreement>
 */
class AgreementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'signer_name' => fake()->name(),
            'signer_email' => fake()->safeEmail(),
            'envelope_id' => (string) Str::uuid(),
            'status' => 'sent',
        ];
    }
}
