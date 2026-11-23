<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('po_number', 50)->unique();
            $table->string('status', 20)->default('ordered'); // draft, ordered, partial, received, cancelled
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('payment_status', 20)->default('unpaid'); // unpaid, partial, paid
            $table->text('notes')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained();
            $table->unsignedInteger('ordered_qty');
            $table->unsignedInteger('received_qty')->default(0);
            $table->unsignedBigInteger('unit_cost');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('receipt_number', 50)->unique();
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained();
            $table->unsignedInteger('received_qty');
            $table->unsignedBigInteger('unit_cost');
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('amount');
            $table->string('payment_method', 30)->default('transfer');
            $table->string('reference_no', 80)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('return_number', 50)->unique();
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->text('reason')->nullable();
            $table->timestamp('returned_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_cost');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
};
