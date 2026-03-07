<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trends', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->nullable()->index();
            $table->text('description')->nullable();
            $table->json('keywords')->nullable();
            $table->string('source')->comment("e.g., 'Google', 'Walmart'");
            $table->float('trending_score')->default(0);
            $table->json('potential_designs')->nullable();
            $table->enum('status', ['new', 'processing', 'completed', 'ignored'])->default('new');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // News table (Notification system)
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->text('description')->nullable();
            $table->string('event_type')->index(); // 'TRENDING', 'ORDER', 'SYSTEM'
            $table->string('source'); // 'walmart', 'google', 'system'
            $table->string('category')->default('general');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->string('related_sku')->nullable();
            $table->boolean('action_required')->default(false);
            $table->string('action_type')->default('none');
            $table->timestamp('event_timestamp')->useCurrent();
            $table->json('metadata')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
        Schema::dropIfExists('trends');
    }
};
