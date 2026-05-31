<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('expenses', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('expense_category_id');
            $table->string('label');
            $table->decimal('amount', 15, 2)->default(0);

            // Date de la charge (engagement) et date de décaissement réel.
            $table->date('expense_date');
            $table->date('paid_date')->nullable();

            $table->enum('status', ['pending', 'paid'])->default('paid');
            $table->enum('payment_method', ['cash', 'check', 'bank_transfer', 'card', 'mobile_money', 'other'])->default('cash');

            $table->string('supplier')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            // Charge récurrente (loyer mensuel, etc.) — informatif.
            $table->boolean('is_recurring')->default(false);

            $table->unsignedBigInteger('recorded_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('expense_category_id');
            $table->index('status');
            $table->index('expense_date');
            $table->index('paid_date');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('expenses');
    }
};
