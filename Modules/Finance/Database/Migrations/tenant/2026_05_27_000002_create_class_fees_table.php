<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mandatory fee schedule per class (tuition + registration combined, as Niger
 * secondary schools bill it). When a student is enrolled in a class, an invoice
 * is generated automatically from these lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('class_fees')) {
            return;
        }

        Schema::create('class_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('fee_type_id')->nullable()->constrained('fee_types')->nullOnDelete();
            $table->string('label')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('class_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_fees');
    }
};
