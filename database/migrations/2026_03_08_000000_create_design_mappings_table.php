<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('base_sku')->unique();
            $table->text('design_url')->nullable();
            $table->text('mockup_url')->nullable();
            $table->text('design_url_2')->nullable();
            $table->text('mockup_url_2')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_mappings');
    }
};
