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
        if (!Schema::hasTable('attributions')) {
            Schema::create('attributions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('pixel_id')->nullable();
                $table->string('shopify_order_id')->index();
                $table->string('order_number')->nullable();
                $table->string('event_id')->nullable();
                $table->string('oppref')->nullable()->index();
                $table->string('campaign_id')->nullable();
                $table->string('ad_group_id')->nullable();
                $table->string('ad_id')->nullable();
                $table->decimal('revenue', 10, 2)->default(0.00);
                $table->string('currency', 10)->default('USD');
                $table->timestamp('event_time')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attributions');
    }
};
