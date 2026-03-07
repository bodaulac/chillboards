<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create Teams Table
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('leader_id')->nullable(); 
            $table->json('settings')->nullable(); // { "can_add_stores": true }
            $table->timestamps();
        });

        // 2. Modify Users Table
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'leader', 'seller'])->default('seller')->after('email');
            $table->foreignId('team_id')->nullable()->after('role')->constrained('teams')->nullOnDelete();
        });

        // 3. Store_Team Pivot (Stores assigned to a Team)
        Schema::create('store_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->timestamps();
        });

        // 4. Store_User Pivot (Store access delegated to specific User/Seller)
        Schema::create('store_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('permission_level')->default('view'); // view, edit
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_user');
        Schema::dropIfExists('store_team');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn(['role', 'team_id']);
        });
        Schema::dropIfExists('teams');
    }
};
