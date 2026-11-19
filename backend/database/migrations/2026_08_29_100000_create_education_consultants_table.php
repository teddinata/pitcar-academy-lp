<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_consultants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('whatsapp_number', 20);
            $table->boolean('is_active')->default(true);

            // Empty arrays mean "no restriction", so a single generalist
            // consultant works without any routing configuration.
            $table->json('programs')->nullable();
            $table->json('domiciles')->nullable();

            $table->unsignedSmallInteger('max_active_leads')->default(50);
            $table->unsignedTinyInteger('priority')->default(10);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_consultants');
    }
};
