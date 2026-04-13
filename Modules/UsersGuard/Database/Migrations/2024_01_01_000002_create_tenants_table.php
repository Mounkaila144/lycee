<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function centralConnection(): string
    {
        return config('database.default');
    }

    /**
     * Run the migrations.
     * CENTRAL DATABASE - Tenants Management
     */
    public function up(): void
    {
        Schema::connection($this->centralConnection())->create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->centralConnection())->dropIfExists('tenants');
    }
};
