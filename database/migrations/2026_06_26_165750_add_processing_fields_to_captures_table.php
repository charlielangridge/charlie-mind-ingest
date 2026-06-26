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
        Schema::table('captures', function (Blueprint $table) {
            if (! Schema::hasColumn('captures', 'processed_markdown_path')) {
                $table->string('processed_markdown_path')->nullable()->after('markdown_path');
            }

            if (! Schema::hasColumn('captures', 'processing_error')) {
                $table->text('processing_error')->nullable()->after('status');
            }

            if (! Schema::hasColumn('captures', 'processing_attempts')) {
                $table->unsignedInteger('processing_attempts')->default(0)->after('processing_error');
            }

            if (! Schema::hasColumn('captures', 'processing_started_at')) {
                $table->timestamp('processing_started_at')->nullable()->after('processing_attempts');
            }

            if (! Schema::hasColumn('captures', 'transcript')) {
                $table->text('transcript')->nullable()->after('processed_at');
            }

            if (! Schema::hasColumn('captures', 'summary')) {
                $table->text('summary')->nullable()->after('transcript');
            }

            if (! Schema::hasColumn('captures', 'suggested_title')) {
                $table->string('suggested_title')->nullable()->after('summary');
            }

            if (! Schema::hasColumn('captures', 'suggested_tags')) {
                $table->json('suggested_tags')->nullable()->after('suggested_title');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('captures', function (Blueprint $table) {
            foreach ([
                'processed_markdown_path',
                'processing_error',
                'processing_attempts',
                'processing_started_at',
                'transcript',
                'summary',
                'suggested_title',
                'suggested_tags',
            ] as $column) {
                if (Schema::hasColumn('captures', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
