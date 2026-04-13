<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the central database connection name
     * Uses SQLite in testing, MySQL in production
     */
    protected function centralConnection(): string
    {
        return config('database.default');
    }

    /**
     * Run the migrations.
     * CENTRAL DATABASE - Super Admin Users
     */
    public function up(): void
    {
        Schema::connection($this->centralConnection())->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->enum('application', ['superadmin'])->default('superadmin');
            $table->boolean('is_active')->default(true);
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->enum('sex', ['M', 'F', 'O'])->nullable();
            $table->timestamp('lastlogin')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('username');
            $table->index('email');
            $table->index(['application', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->centralConnection())->dropIfExists('users');
    }
};
