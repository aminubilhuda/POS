<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosBlueprintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_role_access_control_enforced(): void
    {
        $kasir = User::query()->where('email', 'kasir@nusapos.id')->firstOrFail();
        $gudang = User::query()->where('email', 'gudang@nusapos.id')->firstOrFail();
        $owner = User::query()->where('email', 'owner@nusapos.id')->firstOrFail();

        $this->actingAs($kasir)->get('/pos')->assertOk();
        $this->actingAs($kasir)->get('/reports')->assertForbidden();
        $this->actingAs($kasir)->get('/settings')->assertForbidden();

        $this->actingAs($gudang)->get('/inventory')->assertOk();
        $this->actingAs($gudang)->get('/purchasing')->assertOk();
        $this->actingAs($gudang)->get('/pos')->assertForbidden();

        $this->actingAs($owner)->get('/pos')->assertOk();
        $this->actingAs($owner)->get('/products')->assertOk();
        $this->actingAs($owner)->get('/inventory')->assertOk();
        $this->actingAs($owner)->get('/purchasing')->assertOk();
        $this->actingAs($owner)->get('/reports')->assertOk();
        $this->actingAs($owner)->get('/settings')->assertOk();
    }

    public function test_pos_checkout_is_idempotent_deducts_stock_and_supports_void_with_admin_pin(): void
    {
        $kasir = User::query()->where('email', 'kasir@nusapos.id')->firstOrFail();
        $cab01 = Branch::query()->where('code', 'CAB01')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'BR-5KG')->firstOrFail();

        $stockBefore = Stock::query()->where('branch_id', $cab01->id)->where('product_variant_id', $variant->id)->value('quantity');
        $uuid = (string) Str::uuid();

        $payload = [
            'client_uuid' => $uuid,
            'customer_id' => null,
            'items' => [['variant_id' => $variant->id, 'qty' => 2]],
            'trx_discount_type' => 'nominal',
            'trx_discount_value' => 0,
            'payment_method_code' => 'cash',
            'paid_amount' => 200000,
        ];

        $this->actingAs($kasir)->post('/pos/checkout', $payload)->assertRedirect();
        // Repeat same UUID -> idempotent, must not create duplicate sale or deduct twice
        $this->actingAs($kasir)->post('/pos/checkout', $payload)->assertRedirect();

        $this->assertEquals(1, Sale::query()->where('client_uuid', $uuid)->count());
        $stockAfter = Stock::query()->where('branch_id', $cab01->id)->where('product_variant_id', $variant->id)->value('quantity');
        $this->assertEquals($stockBefore - 2, $stockAfter);

        $sale = Sale::query()->where('client_uuid', $uuid)->firstOrFail();

        // Wrong PIN rejected
        $this->actingAs($kasir)->post('/pos/void', [
            'invoice_number' => $sale->invoice_number,
            'pin' => '000000',
            'reason' => 'Salah input',
        ])->assertSessionHasErrors('pin');

        // Valid Admin PIN restores stock & logs activity
        $this->actingAs($kasir)->post('/pos/void', [
            'invoice_number' => $sale->invoice_number,
            'pin' => '123456',
            'reason' => 'Salah input',
        ])->assertRedirect();

        $this->assertEquals('voided', $sale->fresh()->status);
        $stockRestored = Stock::query()->where('branch_id', $cab01->id)->where('product_variant_id', $variant->id)->value('quantity');
        $this->assertEquals($stockBefore, $stockRestored);
        $this->assertTrue(ActivityLog::query()->where('action', 'sale.voided')->exists());
    }

    public function test_inventory_opname_and_interbranch_transfer(): void
    {
        $owner = User::query()->where('email', 'owner@nusapos.id')->firstOrFail();
        $cab01 = Branch::query()->where('code', 'CAB01')->firstOrFail();
        $cab02 = Branch::query()->where('code', 'CAB02')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'GP-1KG')->firstOrFail();

        // Opname sets physical_qty to 10
        $this->actingAs($owner)->post('/inventory/opname', [
            'notes' => 'Opname bulanan',
            'items' => [['variant_id' => $variant->id, 'physical_qty' => 10]],
        ])->assertRedirect();

        $this->assertEquals(
            10,
            Stock::query()->where('branch_id', $cab01->id)->where('product_variant_id', $variant->id)->value('quantity')
        );

        // Send transfer 3 units from CAB01 to CAB02
        $cab02Before = Stock::query()->where('branch_id', $cab02->id)->where('product_variant_id', $variant->id)->value('quantity');
        $this->actingAs($owner)->post('/inventory/transfers', [
            'to_branch_id' => $cab02->id,
            'notes' => 'Transfer test',
            'items' => [['variant_id' => $variant->id, 'qty' => 3]],
        ])->assertRedirect();

        $this->assertEquals(
            7,
            Stock::query()->where('branch_id', $cab01->id)->where('product_variant_id', $variant->id)->value('quantity')
        );

        $transfer = StockTransfer::query()->latest('id')->firstOrFail();

        // Switch Owner to CAB02 and receive transfer
        $this->actingAs($owner)->post('/branch/switch', ['branch_id' => $cab02->id])->assertRedirect();
        $this->actingAs($owner)->post("/inventory/transfers/{$transfer->id}/receive")->assertRedirect();

        $this->assertEquals('received', $transfer->fresh()->status);
        $this->assertEquals(
            $cab02Before + 3,
            Stock::query()->where('branch_id', $cab02->id)->where('product_variant_id', $variant->id)->value('quantity')
        );
    }

    public function test_purchasing_po_receive_pay_and_return(): void
    {
        $owner = User::query()->where('email', 'owner@nusapos.id')->firstOrFail();
        $cab01 = Branch::query()->where('code', 'CAB01')->firstOrFail();
        $supplier = Supplier::query()->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'SB-450')->firstOrFail();

        $this->actingAs($owner)->post('/purchasing/orders', [
            'supplier_id' => $supplier->id,
            'notes' => 'PO test',
            'items' => [['variant_id' => $variant->id, 'qty' => 10, 'unit_cost' => 19000]],
        ])->assertRedirect();

        $po = PurchaseOrder::query()->latest('id')->with('items')->firstOrFail();
        $poItem = $po->items->first();

        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/receive", [
            'items' => [['po_item_id' => $poItem->id, 'received_qty' => 10]],
        ])->assertRedirect();

        $this->assertEquals('received', $po->fresh()->status);
        $this->assertEquals(
            10,
            Stock::query()->where('branch_id', $cab01->id)->where('product_variant_id', $variant->id)->value('quantity')
        );

        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/pay", [
            'amount' => 190000,
            'payment_method' => 'transfer',
        ])->assertRedirect();

        $this->assertEquals('paid', $po->fresh()->payment_status);

        $this->actingAs($owner)->post('/purchasing/returns', [
            'supplier_id' => $supplier->id,
            'variant_id' => $variant->id,
            'qty' => 2,
            'reason' => 'Kemasan bocor',
        ])->assertRedirect();

        $this->assertEquals(
            8,
            Stock::query()->where('branch_id', $cab01->id)->where('product_variant_id', $variant->id)->value('quantity')
        );
        $this->assertTrue(StockMovement::query()->where('type', 'purchase_return')->exists());
    }

    public function test_reports_export_excel_and_pdf_work(): void
    {
        $owner = User::query()->where('email', 'owner@nusapos.id')->firstOrFail();

        $this->actingAs($owner)->get('/reports?scope=all')->assertOk();
        $this->actingAs($owner)->get('/reports/export/excel?scope=all')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($owner)->get('/reports/export/pdf?scope=all')
            ->assertOk()
            ->assertSee('Laporan Penjualan');
    }

    public function test_multi_unit_checkout_manual_discount_and_partial_refund(): void
    {
        $kasir = User::query()->where('email', 'kasir@nusapos.id')->firstOrFail();
        $cab01 = Branch::query()->where('code', 'CAB01')->firstOrFail();
        $variant = ProductVariant::query()->with('productUnits')->where('sku', 'TM-350')->firstOrFail();
        $lusin = $variant->productUnits->firstOrFail(); // conversion_qty = 12, sell_price = 50000

        $stockBefore = Stock::query()->where('branch_id', $cab01->id)->where('product_variant_id', $variant->id)->value('quantity');
        $uuid = (string) Str::uuid();

        $this->actingAs($kasir)->post('/pos/checkout', [
            'client_uuid' => $uuid,
            'items' => [[
                'variant_id' => $variant->id,
                'qty' => 2,
                'product_unit_id' => $lusin->id,
                'discount_type' => 'percent',
                'discount_value' => 10,
            ]],
            'trx_discount_type' => 'nominal',
            'trx_discount_value' => 0,
            'payment_method_code' => 'cash',
            'paid_amount' => 150000,
        ])->assertRedirect();

        // 2 lusin = 24 pcs deducted from base stock
        $stockAfter = Stock::query()->where('branch_id', $cab01->id)->where('product_variant_id', $variant->id)->value('quantity');
        $this->assertEquals($stockBefore - 24, $stockAfter);

        $sale = Sale::query()->with('items')->where('client_uuid', $uuid)->firstOrFail();
        $this->assertEquals(10000, $sale->item_discount_total); // 10% of 100.000

        // Partial refund of 1 out of 2 qty
        $saleItem = $sale->items->firstOrFail();
        $this->actingAs($kasir)->post('/pos/refund', [
            'invoice_number' => $sale->invoice_number,
            'pin' => '123456',
            'reason' => 'Retur sebagian',
            'items' => [['sale_item_id' => $saleItem->id, 'qty' => 1]],
        ])->assertRedirect();

        $this->assertEquals('partial_refund', $sale->fresh()->status);
    }

    public function test_csv_import_barcode_labels_and_user_management(): void
    {
        $owner = User::query()->where('email', 'owner@nusapos.id')->firstOrFail();
        $kasir = User::query()->where('email', 'kasir@nusapos.id')->firstOrFail();

        $csvContent = "nama,kategori,satuan,varian,sku,barcode,harga_modal,harga_jual,stok_min\n"
            ."Kopi Robusta 200g,Minuman,pack,200 g,KP-200,899123400200,20000,26000,10\n"
            ."Duplikat Beras,Sembako,sak,5 kg,BR-5KG,899100210011,64000,74000,10\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('produk.csv', $csvContent);

        $this->actingAs($owner)->post('/products/import', ['file' => $file])->assertRedirect();
        $this->assertTrue(ProductVariant::query()->where('sku', 'KP-200')->exists());

        $this->actingAs($owner)->get('/products/barcode-labels?qty=2&size=33x15')
            ->assertOk()
            ->assertSee('Cetak Label Barcode');

        // Edit & toggle user active
        $this->actingAs($owner)->put("/settings/users/{$kasir->id}", [
            'name' => 'Budi Santoso Updated',
            'role' => 'kasir',
            'branch_ids' => [Branch::query()->where('code', 'CAB01')->value('id')],
        ])->assertRedirect();
        $this->assertEquals('Budi Santoso Updated', $kasir->fresh()->name);

        $this->actingAs($owner)->patch("/settings/users/{$kasir->id}/toggle")->assertRedirect();
        $this->assertFalse($kasir->fresh()->is_active);
    }
}
