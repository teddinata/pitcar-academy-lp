<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EducationConsultant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'whatsapp_number', 'is_active',
        'programs', 'domiciles', 'max_active_leads', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'programs' => 'array',
            'domiciles' => 'array',
            'max_active_leads' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_consultant_id');
    }
}
