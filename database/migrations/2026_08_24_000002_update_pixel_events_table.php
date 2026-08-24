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
        if (Schema::hasTable('pixel_events')) {
            Schema::table('pixel_events', function (Blueprint $table) {
                if (!Schema::hasColumn('pixel_events', 'event_id')) {
                    $table->string('event_id')->nullable()->after('pixel_id')->index();
                }
                if (!Schema::hasColumn('pixel_events', 'source')) {
                    $table->string('source')->default('Browser')->after('event_name'); // Browser, Server, Webhook
                }
                if (!Schema::hasColumn('pixel_events', 'oppref')) {
                    $table->string('oppref')->nullable()->after('source')->index();
                }
                if (!Schema::hasColumn('pixel_events', 'order_id')) {
                    $table->string('order_id')->nullable()->after('oppref')->index();
                }
                if (!Schema::hasColumn('pixel_events', 'response_code')) {
                    $table->integer('response_code')->nullable()->after('payload');
                }
                if (!Schema::hasColumn('pixel_events', 'response_body')) {
                    $table->text('response_body')->nullable()->after('response_code');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pixel_events')) {
            Schema::table('pixel_events', function (Blueprint $table) {
                $table->dropColumn(['event_id', 'source', 'oppref', 'order_id', 'response_code', 'response_body']);
            });
        }
    }
};
