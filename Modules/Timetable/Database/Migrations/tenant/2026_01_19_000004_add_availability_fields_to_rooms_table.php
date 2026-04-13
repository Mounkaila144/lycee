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
        Schema::table('rooms', function (Blueprint $table) {
            $table->text('unavailable_reason')->nullable()->after('is_active');
            $table->dateTime('unavailable_from')->nullable()->after('unavailable_reason');
            $table->dateTime('unavailable_to')->nullable()->after('unavailable_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['unavailable_reason', 'unavailable_from', 'unavailable_to']);
        });
    }
};
