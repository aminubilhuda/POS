<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('kasir')->after('password'); // owner, admin, kasir, gudang
            $table->string('pin', 255)->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('pin');
            $table->softDeletes();
        });

        Schema::create('branch_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'user_id']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('tax_rate')->default(11);
            $table->boolean('tax_inclusive')->default(false);
            $table->string('receipt_prefix', 20)->default('INV');
            $table->string('printer_name')->default('EPSON TM-T82 (58mm)');
            $table->unsignedInteger('paper_width')->default(58);
            $table->unsignedInteger('chars_per_line')->default(32);
            $table->boolean('auto_open_drawer')->default(true);
            $table->text('receipt_header')->nullable();
            $table->text('receipt_footer')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['branch_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('branch_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'pin', 'is_active', 'deleted_at']);
        });
        Schema::dropIfExists('branches');
    }
};
