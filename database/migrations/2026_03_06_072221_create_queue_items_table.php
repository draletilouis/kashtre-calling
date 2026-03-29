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
        Schema::create('queue_items', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('service_point_id')->index();
            $table->string('visit_id')->nullable();
            $table->string('client_name')->nullable();
            $table->string('status');
            $table->string('priority')->default('normal');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('updated_in_master_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_items');
    }
};
