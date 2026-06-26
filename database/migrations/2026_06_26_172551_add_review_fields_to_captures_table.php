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
            if (! Schema::hasColumn('captures', 'needs_review')) {
                $table->boolean('needs_review')->default(false)->after('suggested_tags');
            }

            if (! Schema::hasColumn('captures', 'review_reason')) {
                $table->string('review_reason')->nullable()->after('needs_review');
            }

            if (! Schema::hasColumn('captures', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('review_reason');
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
                'needs_review',
                'review_reason',
                'reviewed_at',
            ] as $column) {
                if (Schema::hasColumn('captures', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
