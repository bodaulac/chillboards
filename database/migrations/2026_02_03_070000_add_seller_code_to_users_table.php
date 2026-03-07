<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $row) {
            $row->string('seller_code')->nullable()->after('team_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $row) {
            $row->dropColumn('seller_code');
        });
    }
};
