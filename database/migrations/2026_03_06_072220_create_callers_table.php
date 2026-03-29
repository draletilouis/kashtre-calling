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
        Schema::create('callers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kashtre_id')->unique()->nullable();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('name');
            $table->string('display_token', 10)->nullable()->index();
            $table->string('status')->default('active');
            $table->text('announcement_message')->nullable();
            $table->decimal('speech_rate', 3, 2)->default(1.0);
            $table->decimal('speech_volume', 3, 2)->default(1.0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('callers');
    }
};
