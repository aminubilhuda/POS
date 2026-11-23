<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder aman untuk production: hanya memastikan metode pembayaran dasar tersedia.
     * Tidak membuat akun user, cabang, produk, atau transaksi demo.
     */
    public function run(): void
    {
        PaymentMethod::query()->firstOrCreate(
            ['code' => 'cash'],
            ['name' => 'Tunai', 'is_active' => true, 'opens_drawer' => true, 'sort_order' => 1]
        );
        PaymentMethod::query()->firstOrCreate(
            ['code' => 'qris'],
            ['name' => 'QRIS', 'is_active' => true, 'opens_drawer' => false, 'sort_order' => 2]
        );
        PaymentMethod::query()->firstOrCreate(
            ['code' => 'ewallet'],
            ['name' => 'E-Wallet', 'is_active' => true, 'opens_drawer' => false, 'sort_order' => 3]
        );
    }
}
