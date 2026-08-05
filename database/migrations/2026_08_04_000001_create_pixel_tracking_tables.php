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
        if (!Schema::hasTable('pixel_events')) {
            Schema::create('pixel_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
                $table->string('pixel_id');
                $table->string('event_name'); // checkout_started, page_viewed, product_viewed, add_to_cart, order_completed
                $table->string('event_type')->default('Standard'); // Standard or Custom
                $table->string('event_time'); // HH:mm:ss
                $table->json('payload')->nullable();
                $table->string('status')->default('Loaded');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn(['pixel_id', 'capi_key', 'advertiser_key', 'tracking_enabled', 'pixel_helper_enabled']);
        });
        Schema::dropIfExists('pixel_events');
    }
};
