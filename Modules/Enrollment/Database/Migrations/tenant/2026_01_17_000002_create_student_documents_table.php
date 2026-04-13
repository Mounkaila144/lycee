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
        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');

            // Type de document
            $table->enum('type', [
                'certificat_naissance',
                'releve_baccalaureat',
                'photo_identite',
                'cni_passeport',
                'autre',
            ]);

            // Fichier
            $table->string('filename');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedInteger('file_size'); // en bytes

            // Métadonnées
            $table->string('description')->nullable();
            $table->boolean('is_validated')->default(false);
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('student_id');
            $table->index('type');
            $table->index('is_validated');
            $table->index('validated_by');

            // Foreign key constraint (only if users table exists)
            if (Schema::hasTable('users')) {
                $table->foreign('validated_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_documents');
    }
};
