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
        Schema::create('specializations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('programme_id')->constrained('programmes')->onDelete('cascade');
            $table->string('available_from_level', 10); // L3, M1, etc.
            $table->unsignedInteger('capacity')->nullable(); // Places disponibles
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('min_average_required', 5, 2)->nullable(); // Moyenne minimum
            $table->date('application_start_date')->nullable();
            $table->date('application_end_date')->nullable();
            $table->enum('type', ['Obligatoire', 'Optionnelle'])->default('Obligatoire');
            $table->enum('selection_mode', ['Exclusive', 'Multiple'])->default('Exclusive');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['programme_id', 'available_from_level']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specializations');
    }
};
