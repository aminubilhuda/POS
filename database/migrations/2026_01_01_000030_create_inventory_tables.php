<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'product_variant_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30); // sale, sale_void, sale_return, purchase, purchase_return, transfer_out, transfer_in, opname, adjustment
            $table->integer('quantity_change');
            $table->integer('quantity_after');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['branch_id', 'product_variant_id']);
            $table->index(['branch_id', 'created_at']);
        });

        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('opname_number', 50)->unique();
            $table->string('status', 20)->default('completed');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained();
            $table->integer('system_qty');
            $table->integer('physical_qty');
            $table->integer('difference');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_branch_id')->constrained('branches');
            $table->foreignId('to_branch_id')->constrained('branches');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->string('transfer_number', 50)->unique();
            $table->string('status', 20)->default('in_transit'); // in_transit, received, cancelled
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained();
            $table->unsignedInteger('sent_qty');
            $table->unsignedInteger('received_qty')->default(0);
            $table->integer('difference')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stocks');
    }
};
