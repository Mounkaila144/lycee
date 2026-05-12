<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('firstname');
            $table->string('lastname');
            $table->enum('relationship', ['Père', 'Mère', 'Tuteur', 'Tutrice', 'Autre']);
            $table->string('phone', 30);
            $table->string('phone_secondary', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('profession')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parents');
    }
};
