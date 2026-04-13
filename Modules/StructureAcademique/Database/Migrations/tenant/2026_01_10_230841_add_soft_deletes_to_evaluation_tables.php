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
        // Add soft deletes to evaluation_templates if column doesn't exist
        if (Schema::hasTable('evaluation_templates')) {
            Schema::table('evaluation_templates', function (Blueprint $table) {
                if (! Schema::hasColumn('evaluation_templates', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // Add soft deletes to module_evaluation_configs if column doesn't exist
        if (Schema::hasTable('module_evaluation_configs')) {
            Schema::table('module_evaluation_configs', function (Blueprint $table) {
                if (! Schema::hasColumn('module_evaluation_configs', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('evaluation_templates')) {
            Schema::table('evaluation_templates', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('module_evaluation_configs')) {
            Schema::table('module_evaluation_configs', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
