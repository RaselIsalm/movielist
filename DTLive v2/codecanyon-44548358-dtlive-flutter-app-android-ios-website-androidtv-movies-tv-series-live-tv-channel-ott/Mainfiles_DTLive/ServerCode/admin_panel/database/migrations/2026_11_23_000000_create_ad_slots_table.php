<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_slots', function (Blueprint $table) {
            $table->id();
            $table->string('slot_key', 120);
            $table->string('image', 2048);
            $table->unsignedTinyInteger('storage_type')->default(1);
            $table->string('click_url', 2048)->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->enum('platform', ['all', 'web', 'app'])->default('all');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['slot_key', 'status', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_slots');
    }
};
