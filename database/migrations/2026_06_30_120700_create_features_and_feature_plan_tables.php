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
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('text');
            $table->integer('display_order')->default(0);
            $table->boolean('hidden_feature')->default(false);
            $table->timestamps();
        });

        Schema::create('feature_plan', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('plan_id');
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->string('value')->nullable();
            $table->timestamps();
            
            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_plan');
        Schema::dropIfExists('features');
    }
};
