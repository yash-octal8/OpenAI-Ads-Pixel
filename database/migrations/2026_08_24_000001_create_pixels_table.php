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
        if (!Schema::hasTable('pixels')) {
            Schema::create('pixels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('name');
                $table->string('pixel_id');
                $table->text('capi_key')->nullable();
                $table->string('status')->default('active'); // active, testing, paused
                $table->boolean('test_mode')->default(false);
                $table->string('coverage_type')->default('entire_store'); // entire_store, specific
                $table->json('target_selection')->nullable(); // product / collection IDs if specific
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pixels');
    }
};
