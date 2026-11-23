<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('type', 30)->default('Member');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('opens_drawer')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 40)->nullable();
            $table->string('name');
            $table->string('type', 20)->default('nominal'); // nominal, percent
            $table->unsignedBigInteger('value');
            $table->string('scope', 20)->default('transaction'); // item, transaction
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('shift_code', 30);
            $table->string('status', 20)->default('open'); // open, closed
            $table->unsignedBigInteger('opening_cash')->default(0);
            $table->unsignedBigInteger('expected_cash')->default(0);
            $table->unsignedBigInteger('closing_cash')->nullable();
            $table->bigInteger('difference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('cash_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 60)->unique();
            $table->string('status', 20)->default('completed'); // completed, voided, refunded
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('item_discount_total')->default(0);
            $table->string('trx_discount_type', 20)->default('nominal');
            $table->unsignedBigInteger('trx_discount_value')->default(0);
            $table->unsignedBigInteger('trx_discount_amount')->default(0);
            $table->unsignedInteger('tax_rate')->default(11);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('grand_total');
            $table->unsignedBigInteger('paid_total');
            $table->unsignedBigInteger('change_amount')->default(0);
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained();
            $table->string('product_name_snapshot');
            $table->string('variant_name_snapshot');
            $table->string('unit_name_snapshot', 30);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('cost_price_snapshot');
            $table->unsignedBigInteger('unit_price_snapshot');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained();
            $table->unsignedBigInteger('amount');
            $table->string('reference_no', 80)->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('return_number', 50)->unique();
            $table->unsignedBigInteger('total_refund');
            $table->text('reason')->nullable();
            $table->timestamp('returned_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained();
            $table->foreignId('product_variant_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('refund_amount');
            $table->timestamps();
        });

        Schema::create('held_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hold_code', 30);
            $table->json('payload');
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedBigInteger('estimated_total')->default(0);
            $table->timestamps();

            $table->index(['branch_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('held_sales');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('cash_shifts');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('customers');
    }
};
