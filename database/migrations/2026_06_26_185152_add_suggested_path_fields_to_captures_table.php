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
            if (! Schema::hasColumn('captures', 'suggested_folder')) {
                $table->string('suggested_folder')->nullable()->after('suggested_title');
            }

            if (! Schema::hasColumn('captures', 'suggested_path')) {
                $table->string('suggested_path')->nullable()->after('suggested_folder');
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
                'suggested_folder',
                'suggested_path',
            ] as $column) {
                if (Schema::hasColumn('captures', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
