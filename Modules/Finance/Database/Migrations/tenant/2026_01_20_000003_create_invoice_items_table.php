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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            // Facture
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            // Type de frais
            $table->foreignId('fee_type_id')->nullable()->constrained('fee_types')->nullOnDelete();

            // Description
            $table->string('description');

            // Quantité et prix
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);

            // Remise
            $table->decimal('discount_amount', 15, 2)->default(0);

            $table->timestamps();

            // Indexes
            $table->index('invoice_id');
            $table->index('fee_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
