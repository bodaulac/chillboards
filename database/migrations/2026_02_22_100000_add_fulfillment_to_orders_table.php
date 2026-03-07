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
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'fulfillment')) {
                $table->json('fulfillment')->nullable()->after('product_details');
            }
            if (!Schema::hasColumn('orders', 'tracking_info')) {
                $table->json('tracking_info')->nullable()->after('fulfillment');
            }
            if (!Schema::hasColumn('orders', 'timeline')) {
                $table->json('timeline')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['fulfillment', 'tracking_info', 'timeline']);
        });
    }
};
