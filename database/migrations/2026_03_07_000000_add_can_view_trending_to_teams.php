<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('can_view_trending')->default(false)->after('product_sheet_url');
            $table->string('description')->nullable()->after('name');
            $table->boolean('active')->default(true)->after('can_view_trending');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['can_view_trending', 'description', 'active']);
        });
    }
};
