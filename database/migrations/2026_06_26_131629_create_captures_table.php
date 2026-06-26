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
        Schema::create('captures', function (Blueprint $table) {
            $table->id();
            $table->string('capture_id')->unique();
            $table->string('type')->index();
            $table->string('title')->nullable();
            $table->longText('body')->nullable();
            $table->string('url')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('markdown_path');
            $table->string('media_path')->nullable();
            $table->string('media_mime')->nullable();
            $table->string('media_original_name')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('captured_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('captures');
    }
};
