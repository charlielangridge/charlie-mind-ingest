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
            if (! Schema::hasColumn('captures', 'export_status')) {
                $table->string('export_status')->nullable()->after('reviewed_at');
            }

            if (! Schema::hasColumn('captures', 'exported_at')) {
                $table->timestamp('exported_at')->nullable()->after('export_status');
            }

            if (! Schema::hasColumn('captures', 'export_attempts')) {
                $table->unsignedInteger('export_attempts')->default(0)->after('exported_at');
            }

            if (! Schema::hasColumn('captures', 'export_error')) {
                $table->text('export_error')->nullable()->after('export_attempts');
            }

            if (! Schema::hasColumn('captures', 'last_export_attempt_at')) {
                $table->timestamp('last_export_attempt_at')->nullable()->after('export_error');
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
                'export_status',
                'exported_at',
                'export_attempts',
                'export_error',
                'last_export_attempt_at',
            ] as $column) {
                if (Schema::hasColumn('captures', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
