<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('voice_configurations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->unique();
            $table->string('tts_voice_id')->nullable();
            $table->string('tts_voice_name')->nullable();
            $table->decimal('tts_stability', 3, 2)->default(0.5);
            $table->decimal('tts_similarity_boost', 3, 2)->default(0.75);
            $table->decimal('tts_speed', 3, 2)->default(1.0);
            $table->text('announcement_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_configurations');
    }
};
