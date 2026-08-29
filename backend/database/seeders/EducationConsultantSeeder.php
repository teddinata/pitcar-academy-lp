<?php

namespace Database\Seeders;

use App\Models\EducationConsultant;
use Illuminate\Database\Seeder;

class EducationConsultantSeeder extends Seeder
{
    public function run(): void
    {
        // Replace with the real roster before staging. Numbers belong in the
        // database or env, never in the frontend bundle.
        EducationConsultant::query()->updateOrCreate(
            ['whatsapp_number' => (string) config('leads.fallback_consultant_whatsapp')],
            [
                'name' => 'Education Consultant (default)',
                'is_active' => (bool) config('leads.fallback_consultant_whatsapp'),
                'programs' => null,
                'domiciles' => null,
                'max_active_leads' => 100,
                'priority' => 10,
            ]
        );
    }
}
