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
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caller_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('kashtre_log_id')->nullable();
            $table->string('visit_id')->nullable();
            $table->string('client_name')->nullable();
            $table->string('service_point_name')->nullable();
            $table->string('room_name')->nullable();
            $table->timestamp('called_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
