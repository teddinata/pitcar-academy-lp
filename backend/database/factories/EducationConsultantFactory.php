<?php

namespace Database\Factories;

use App\Models\EducationConsultant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EducationConsultant>
 */
class EducationConsultantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'whatsapp_number' => '628'.$this->faker->numerify('##########'),
            'is_active' => true,
            'programs' => null,
            'domiciles' => null,
            'max_active_leads' => 50,
            'priority' => 10,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
