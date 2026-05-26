<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The students table was missing the email/mobile/country columns that the
 * model, the create/edit form and the validation already reference — so
 * student creation through the UI always failed. Add them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('students', function (Blueprint $table) {
            if (! Schema::connection('tenant')->hasColumn('students', 'email')) {
                $table->string('email')->nullable()->after('sex');
            }
            if (! Schema::connection('tenant')->hasColumn('students', 'mobile')) {
                $table->string('mobile', 30)->nullable()->after('phone');
            }
            if (! Schema::connection('tenant')->hasColumn('students', 'country')) {
                $table->string('country', 100)->nullable()->after('city');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('students', function (Blueprint $table) {
            foreach (['email', 'mobile', 'country'] as $col) {
                if (Schema::connection('tenant')->hasColumn('students', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
