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
     * CENTRAL DATABASE - Tenant Domains (for multi-domain support)
     */
    public function up(): void
    {
        Schema::connection($this->centralConnection())->create('domains', function (Blueprint $table) {
            $table->increments('id');
            $table->string('domain')->unique();
            $table->string('tenant_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->centralConnection())->dropIfExists('domains');
    }
};
