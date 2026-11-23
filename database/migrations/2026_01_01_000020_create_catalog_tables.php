<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 20);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->foreignId('unit_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_bundle')->default(false);
            $table->string('thumb_code', 4)->default('PR');
            $table->string('thumb_color', 20)->default('teal');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Standar');
            $table->string('sku', 60)->unique();
            $table->string('barcode', 80)->index();
            $table->unsignedBigInteger('cost_price')->default(0);
            $table->unsignedBigInteger('sell_price')->default(0);
            $table->unsignedBigInteger('promo_discount')->default(0);
            $table->string('promo_label')->nullable();
            $table->unsignedInteger('min_stock')->default(5);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained();
            $table->unsignedInteger('conversion_qty')->default(1);
            $table->unsignedBigInteger('sell_price')->default(0);
            $table->string('barcode', 80)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_items');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('categories');
    }
};
