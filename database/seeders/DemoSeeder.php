<?php

namespace Database\Seeders;

use App\Actions\Inventory\AdjustStock;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\HoldSale;
use App\Actions\Shifts\OpenShift;
use App\Models\Branch;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Setting;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Cabang (Multi-Cabang)
        $cab01 = Branch::create([
            'code' => 'CAB01',
            'name' => 'Pusat Jakarta',
            'address' => 'Jl. Sudirman No. 48, Jakarta Pusat',
            'phone' => '(021) 555-0192',
        ]);
        $cab02 = Branch::create([
            'code' => 'CAB02',
            'name' => 'Bandung Dago',
            'address' => 'Jl. Ir. H. Juanda No. 112, Bandung',
            'phone' => '(022) 420-8811',
        ]);
        $cab03 = Branch::create([
            'code' => 'CAB03',
            'name' => 'Surabaya Barat',
            'address' => 'Jl. HR Muhammad No. 25, Surabaya',
            'phone' => '(031) 731-4409',
        ]);

        // Pengaturan per cabang (PPN, format struk, printer QZ Tray 58mm)
        foreach ([$cab01, $cab02, $cab03] as $idx => $branch) {
            $printers = ['EPSON TM-T82 (58mm)', 'EPSON TM-T82X (58mm)', 'RONGTA RP58 (58mm)'];
            Setting::create([
                'branch_id' => $branch->id,
                'tax_rate' => 11,
                'tax_inclusive' => false,
                'receipt_prefix' => 'INV',
                'printer_name' => $printers[$idx],
                'paper_width' => 58,
                'chars_per_line' => 32,
                'auto_open_drawer' => true,
                'receipt_header' => "NUSAPOS RETAIL — {$branch->code}\n{$branch->address}\nTelp: {$branch->phone}",
                'receipt_footer' => "Terima kasih telah berbelanja!\nBarang yg dibeli tdk dpt dikembalikan",
            ]);
        }

        // 2. Pengguna & Role (Owner, Admin, Kasir, Gudang)
        $owner = User::create([
            'name' => 'Andi Pratama',
            'email' => 'owner@nusapos.id',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'pin' => Hash::make('123456'),
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'Dewi Lestari',
            'email' => 'admin@nusapos.id',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'pin' => Hash::make('123456'),
            'is_active' => true,
        ]);
        $admin->branches()->attach([$cab01->id, $cab02->id]);

        $kasir = User::create([
            'name' => 'Budi Santoso',
            'email' => 'kasir@nusapos.id',
            'password' => Hash::make('password'),
            'role' => 'kasir',
            'pin' => null,
            'is_active' => true,
        ]);
        $kasir->branches()->attach([$cab01->id]);

        $gudang = User::create([
            'name' => 'Rudi Hartono',
            'email' => 'gudang@nusapos.id',
            'password' => Hash::make('password'),
            'role' => 'gudang',
            'pin' => null,
            'is_active' => true,
        ]);
        $gudang->branches()->attach([$cab01->id]);

        // 3. Metode Pembayaran (Tunai aktif, QRIS & E-Wallet siap Paket C)
        PaymentMethod::create(['code' => 'cash', 'name' => 'Tunai', 'is_active' => true, 'opens_drawer' => true, 'sort_order' => 1]);
        PaymentMethod::create(['code' => 'qris', 'name' => 'QRIS', 'is_active' => true, 'opens_drawer' => false, 'sort_order' => 2]);
        PaymentMethod::create(['code' => 'ewallet', 'name' => 'E-Wallet', 'is_active' => true, 'opens_drawer' => false, 'sort_order' => 3]);

        // Diskon preset
        Discount::create(['branch_id' => null, 'code' => 'HEMAT8K', 'name' => 'Promo Belanja Rp 8.000', 'type' => 'nominal', 'value' => 8000, 'scope' => 'transaction', 'is_active' => true]);
        Discount::create(['branch_id' => null, 'code' => 'MEMBER5', 'name' => 'Diskon Member 5%', 'type' => 'percent', 'value' => 5, 'scope' => 'transaction', 'is_active' => true]);

        // 4. Kategori & Satuan
        $cats = [
            'sembako' => Category::create(['name' => 'Sembako', 'slug' => 'sembako']),
            'minuman' => Category::create(['name' => 'Minuman', 'slug' => 'minuman']),
            'makanan' => Category::create(['name' => 'Makanan', 'slug' => 'makanan']),
            'kebersihan' => Category::create(['name' => 'Kebersihan', 'slug' => 'kebersihan']),
            'bundling' => Category::create(['name' => 'Bundling / Paket', 'slug' => 'bundling']),
        ];

        $units = [
            'pcs' => Unit::create(['name' => 'Pieces', 'short_name' => 'pcs']),
            'sak' => Unit::create(['name' => 'Sak', 'short_name' => 'sak']),
            'pouch' => Unit::create(['name' => 'Pouch', 'short_name' => 'pouch']),
            'dus' => Unit::create(['name' => 'Dus', 'short_name' => 'dus']),
            'pack' => Unit::create(['name' => 'Pack', 'short_name' => 'pack']),
            'kotak' => Unit::create(['name' => 'Kotak', 'short_name' => 'kotak']),
            'kaleng' => Unit::create(['name' => 'Kaleng', 'short_name' => 'kaleng']),
            'paket' => Unit::create(['name' => 'Paket', 'short_name' => 'paket']),
            'lusin' => Unit::create(['name' => 'Lusin', 'short_name' => 'lusin']),
        ];

        // 5. Produk & Varian
        $catalog = [
            ['cat' => 'sembako', 'unit' => 'sak', 'name' => 'Beras Pandan Wangi Premium', 'variant' => '5 kg', 'sku' => 'BR-5KG', 'barcode' => '899100210011', 'cost' => 64000, 'sell' => 74000, 'disc' => 0, 'promo' => null, 'min' => 10, 'stock' => [32, 20, 15], 'code' => 'BR', 'color' => 'amber', 'bundle' => false],
            ['cat' => 'sembako', 'unit' => 'pouch', 'name' => 'Minyak Goreng Sawit Refill', 'variant' => '2 Liter', 'sku' => 'MG-2L', 'barcode' => '899276113402', 'cost' => 28500, 'sell' => 34000, 'disc' => 1000, 'promo' => 'Diskon -Rp 1.000', 'min' => 12, 'stock' => [45, 30, 25], 'code' => 'MG', 'color' => 'amber', 'bundle' => false],
            ['cat' => 'minuman', 'unit' => 'dus', 'name' => 'Air Mineral Pegunungan 600ml', 'variant' => 'Dus (24 pcs)', 'sku' => 'AM-DUS', 'barcode' => '899600130024', 'cost' => 39000, 'sell' => 48000, 'disc' => 0, 'promo' => null, 'min' => 6, 'stock' => [18, 12, 10], 'code' => 'AM', 'color' => 'blue', 'bundle' => false],
            ['cat' => 'sembako', 'unit' => 'pack', 'name' => 'Gula Pasir Kristal Putih', 'variant' => '1 kg', 'sku' => 'GP-1KG', 'barcode' => '899100210455', 'cost' => 14500, 'sell' => 17500, 'disc' => 0, 'promo' => null, 'min' => 10, 'stock' => [4, 18, 14], 'code' => 'GP', 'color' => 'amber', 'bundle' => false],
            ['cat' => 'minuman', 'unit' => 'pcs', 'name' => 'Teh Melati Botol Dingin', 'variant' => '350 ml', 'sku' => 'TM-350', 'barcode' => '899274500118', 'cost' => 3100, 'sell' => 4500, 'disc' => 0, 'promo' => null, 'min' => 24, 'stock' => [112, 60, 80], 'code' => 'TM', 'color' => 'teal', 'bundle' => false],
            ['cat' => 'minuman', 'unit' => 'kotak', 'name' => 'Susu UHT Full Cream', 'variant' => '1 Liter', 'sku' => 'SU-1L', 'barcode' => '899280210901', 'cost' => 15800, 'sell' => 19500, 'disc' => 0, 'promo' => null, 'min' => 12, 'stock' => [54, 24, 30], 'code' => 'SU', 'color' => 'blue', 'bundle' => false],
            ['cat' => 'makanan', 'unit' => 'pcs', 'name' => 'Mi Instan Goreng Original', 'variant' => '85 g', 'sku' => 'MI-GRG', 'barcode' => '899086855501', 'cost' => 2500, 'sell' => 3200, 'disc' => 0, 'promo' => null, 'min' => 40, 'stock' => [240, 160, 120], 'code' => 'MI', 'color' => 'rose', 'bundle' => false],
            ['cat' => 'makanan', 'unit' => 'kaleng', 'name' => 'Biskuit Wafer Cokelat Kaleng', 'variant' => '280 g', 'sku' => 'BK-CKL', 'barcode' => '899277521008', 'cost' => 22500, 'sell' => 28500, 'disc' => 0, 'promo' => null, 'min' => 8, 'stock' => [3, 14, 10], 'code' => 'BK', 'color' => 'rose', 'bundle' => false],
            ['cat' => 'kebersihan', 'unit' => 'pouch', 'name' => 'Detergen Cair Konsentrat', 'variant' => '800 ml · Fresh', 'sku' => 'DT-800', 'barcode' => '899999904412', 'cost' => 16500, 'sell' => 21000, 'disc' => 0, 'promo' => null, 'min' => 10, 'stock' => [29, 20, 18], 'code' => 'DT', 'color' => 'emerald', 'bundle' => false],
            ['cat' => 'kebersihan', 'unit' => 'pouch', 'name' => 'Sabun Mandi Cair Antibakteri', 'variant' => '450 ml · Refill', 'sku' => 'SB-450', 'barcode' => '899999907105', 'cost' => 19000, 'sell' => 24500, 'disc' => 0, 'promo' => null, 'min' => 8, 'stock' => [0, 12, 9], 'code' => 'SB', 'color' => 'emerald', 'bundle' => false],
            ['cat' => 'bundling', 'unit' => 'paket', 'name' => 'Paket Sembako Hemat Keluarga', 'variant' => 'Beras 5kg + Minyak 2L + Gula 1kg', 'sku' => 'BND-SMB-001', 'barcode' => 'BND-SMB-001', 'cost' => 107000, 'sell' => 119000, 'disc' => 0, 'promo' => 'Hemat Rp 6.500', 'min' => 5, 'stock' => [15, 10, 8], 'code' => 'PK', 'color' => 'violet', 'bundle' => true],
            ['cat' => 'bundling', 'unit' => 'paket', 'name' => 'Paket Ngopi & Wafer Sore', 'variant' => '2 Teh Botol + 1 Wafer Kaleng', 'sku' => 'BND-SNK-002', 'barcode' => 'BND-SNK-002', 'cost' => 28700, 'sell' => 35000, 'disc' => 0, 'promo' => null, 'min' => 4, 'stock' => [10, 8, 6], 'code' => 'PK', 'color' => 'violet', 'bundle' => true],
        ];

        $adjustStock = app(AdjustStock::class);
        $variantsBySku = [];
        $productsBySku = [];

        foreach ($catalog as $row) {
            $product = Product::create([
                'category_id' => $cats[$row['cat']]->id,
                'unit_id' => $units[$row['unit']]->id,
                'name' => $row['name'],
                'is_bundle' => $row['bundle'],
                'thumb_code' => $row['code'],
                'thumb_color' => $row['color'],
            ]);

            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'name' => $row['variant'],
                'sku' => $row['sku'],
                'barcode' => $row['barcode'],
                'cost_price' => $row['cost'],
                'sell_price' => $row['sell'],
                'promo_discount' => $row['disc'],
                'promo_label' => $row['promo'],
                'min_stock' => $row['min'],
            ]);

            $variantsBySku[$row['sku']] = $variant;
            $productsBySku[$row['sku']] = $product;

            foreach ([$cab01, $cab02, $cab03] as $bIdx => $branch) {
                if ($row['stock'][$bIdx] > 0) {
                    $adjustStock->execute(
                        branchId: $branch->id,
                        variantId: $variant->id,
                        quantityChange: $row['stock'][$bIdx],
                        type: 'adjustment',
                        userId: $owner->id,
                        notes: 'Saldo stok awal'
                    );
                } else {
                    \App\Models\Stock::create([
                        'branch_id' => $branch->id,
                        'product_variant_id' => $variant->id,
                        'quantity' => 0,
                    ]);
                }
            }
        }

        // Satuan bertingkat (product_units)
        ProductUnit::create([
            'product_variant_id' => $variantsBySku['TM-350']->id,
            'unit_id' => $units['lusin']->id,
            'conversion_qty' => 12,
            'sell_price' => 50000,
            'barcode' => '899274500118-LSN',
        ]);
        ProductUnit::create([
            'product_variant_id' => $variantsBySku['MI-GRG']->id,
            'unit_id' => $units['dus']->id,
            'conversion_qty' => 40,
            'sell_price' => 122000,
            'barcode' => '899086855501-DUS',
        ]);

        // Komponen Bundling
        BundleItem::create(['bundle_product_id' => $productsBySku['BND-SMB-001']->id, 'product_variant_id' => $variantsBySku['BR-5KG']->id, 'quantity' => 1]);
        BundleItem::create(['bundle_product_id' => $productsBySku['BND-SMB-001']->id, 'product_variant_id' => $variantsBySku['MG-2L']->id, 'quantity' => 1]);
        BundleItem::create(['bundle_product_id' => $productsBySku['BND-SMB-001']->id, 'product_variant_id' => $variantsBySku['GP-1KG']->id, 'quantity' => 1]);

        BundleItem::create(['bundle_product_id' => $productsBySku['BND-SNK-002']->id, 'product_variant_id' => $variantsBySku['TM-350']->id, 'quantity' => 2]);
        BundleItem::create(['bundle_product_id' => $productsBySku['BND-SNK-002']->id, 'product_variant_id' => $variantsBySku['BK-CKL']->id, 'quantity' => 1]);

        // 6. Supplier & Pelanggan
        $sup1 = Supplier::create(['code' => 'SUP-001', 'name' => 'PT Sumber Pangan Nusantara', 'phone' => '021-8890-1122', 'email' => 'order@sumberpangan.co.id', 'address' => 'Kawasan Industri Pulogadung, Jakarta']);
        $sup2 = Supplier::create(['code' => 'SUP-002', 'name' => 'CV Tirta Segar Distribusi', 'phone' => '021-7765-3344', 'email' => 'sales@tirtasegar.id', 'address' => 'Cikarang Barat, Bekasi']);
        $sup3 = Supplier::create(['code' => 'SUP-003', 'name' => 'PT Bersih Jaya Abadi', 'phone' => '022-601-9988', 'email' => 'supply@bersihjaya.co.id', 'address' => 'Jl. Soekarno Hatta, Bandung']);

        $cust1 = Customer::create(['code' => 'CUST-001', 'name' => 'Ibu Rina Wijaya', 'phone' => '0812-8800-1122', 'type' => 'Member']);
        $cust2 = Customer::create(['code' => 'CUST-002', 'name' => 'Pak Hendra Kusuma', 'phone' => '0813-9900-4455', 'type' => 'Member']);
        $cust3 = Customer::create(['code' => 'CUST-003', 'name' => 'Toko Berkah Jaya', 'phone' => '0811-2233-6677', 'type' => 'Grosir']);

        // 7. Shift Kasir Aktif (CAB01)
        $openShift = app(OpenShift::class);
        $shiftOwner = $openShift->execute($cab01->id, $owner->id, 500000, 'Shift pagi Owner');
        $shiftKasir = $openShift->execute($cab01->id, $kasir->id, 500000, 'Shift pagi Kasir');

        // 8. Transaksi Penjualan Historis (agar Laporan & Laba Rugi langsung berisi data realistis)
        $createSale = app(CreateSale::class);
        $createSale->execute([
            'branch_id' => $cab01->id,
            'user_id' => $kasir->id,
            'cash_shift_id' => $shiftKasir->id,
            'customer_id' => $cust1->id,
            'items' => [
                ['variant_id' => $variantsBySku['BR-5KG']->id, 'qty' => 2],
                ['variant_id' => $variantsBySku['MG-2L']->id, 'qty' => 2],
            ],
            'trx_discount_type' => 'nominal',
            'trx_discount_value' => 5000,
            'payment_method_code' => 'cash',
            'paid_amount' => 250000,
        ]);

        $createSale->execute([
            'branch_id' => $cab01->id,
            'user_id' => $owner->id,
            'cash_shift_id' => $shiftOwner->id,
            'customer_id' => $cust3->id,
            'items' => [
                ['variant_id' => $variantsBySku['AM-DUS']->id, 'qty' => 3],
                ['variant_id' => $variantsBySku['MI-GRG']->id, 'qty' => 10],
                ['variant_id' => $variantsBySku['SU-1L']->id, 'qty' => 4],
            ],
            'trx_discount_type' => 'nominal',
            'trx_discount_value' => 0,
            'payment_method_code' => 'cash',
            'paid_amount' => 300000,
        ]);

        $createSale->execute([
            'branch_id' => $cab02->id,
            'user_id' => $admin->id,
            'customer_id' => $cust2->id,
            'items' => [
                ['variant_id' => $variantsBySku['DT-800']->id, 'qty' => 3],
                ['variant_id' => $variantsBySku['TM-350']->id, 'qty' => 6],
            ],
            'payment_method_code' => 'cash',
            'paid_amount' => 110000,
        ]);

        // 9. Transaksi Hold (CAB01)
        $holdSale = app(HoldSale::class);
        $holdSale->execute($cab01->id, $owner->id, [
            ['variant_id' => $variantsBySku['BR-5KG']->id, 'qty' => 1],
            ['variant_id' => $variantsBySku['SU-1L']->id, 'qty' => 2],
        ], $cust1->id);

        // 10. Purchase Order Sample (Pembelian)
        $po1 = PurchaseOrder::create([
            'branch_id' => $cab01->id,
            'supplier_id' => $sup1->id,
            'user_id' => $admin->id,
            'po_number' => 'PO/CAB01/'.now()->format('Ymd').'/0001',
            'status' => 'ordered',
            'total_amount' => 1280000 + 290000,
            'paid_amount' => 500000,
            'payment_status' => 'partial',
            'notes' => 'Restok rutin mingguan sembako',
            'ordered_at' => now()->subDay(),
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po1->id,
            'product_variant_id' => $variantsBySku['BR-5KG']->id,
            'ordered_qty' => 20,
            'received_qty' => 0,
            'unit_cost' => 64000,
            'subtotal' => 1280000,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po1->id,
            'product_variant_id' => $variantsBySku['GP-1KG']->id,
            'ordered_qty' => 20,
            'received_qty' => 0,
            'unit_cost' => 14500,
            'subtotal' => 290000,
        ]);

        // 11. Stock Transfer In-Transit Sample (CAB01 -> CAB02)
        $trf = StockTransfer::create([
            'from_branch_id' => $cab01->id,
            'to_branch_id' => $cab02->id,
            'created_by' => $gudang->id,
            'transfer_number' => 'TRF/'.now()->format('Ymd').'/0001',
            'status' => 'in_transit',
            'notes' => 'Pengiriman stok tambahan Bandung',
            'sent_at' => now()->subHours(2),
        ]);
        StockTransferItem::create([
            'stock_transfer_id' => $trf->id,
            'product_variant_id' => $variantsBySku['TM-350']->id,
            'sent_qty' => 24,
            'received_qty' => 0,
            'difference' => 0,
        ]);
    }
}
