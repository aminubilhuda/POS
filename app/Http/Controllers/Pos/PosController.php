<?php

namespace App\Http\Controllers\Pos;

use App\Actions\Sales\CreateSale;
use App\Actions\Sales\HoldSale;
use App\Actions\Sales\RefundSale;
use App\Actions\Sales\VoidSale;
use App\Actions\Shifts\CloseShift;
use App\Actions\Shifts\OpenShift;
use App\Http\Controllers\Controller;
use App\Models\CashShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\HeldSale;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $branch = $request->attributes->get('branch');

        $setting = Setting::query()->where('branch_id', $branch->id)->first();
        $openShift = CashShift::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        $stocksByVariant = Stock::query()
            ->where('branch_id', $branch->id)
            ->pluck('quantity', 'product_variant_id');

        $variants = ProductVariant::query()
            ->with(['product.category', 'product.unit', 'productUnits.unit'])
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->orderBy('id')
            ->get()
            ->map(function (ProductVariant $v) use ($stocksByVariant) {
                $unitNote = $v->productUnits->isNotEmpty()
                    ? $v->productUnits->map(fn ($pu) => "{$pu->unit->short_name} ({$pu->conversion_qty})")->implode(' / ')
                    : null;

                return [
                    'id' => $v->id,
                    'product_id' => $v->product_id,
                    'sku' => $v->sku,
                    'barcode' => $v->barcode,
                    'name' => $v->product->name,
                    'category' => $v->product->category->slug,
                    'variant' => $v->name,
                    'unit' => $v->product->unit->short_name,
                    'unit_note' => $unitNote,
                    'product_units' => $v->productUnits->map(fn ($pu) => [
                        'id' => $pu->id,
                        'unit' => $pu->unit->short_name,
                        'conversion_qty' => $pu->conversion_qty,
                        'sell_price' => $pu->sell_price,
                        'label' => "{$pu->unit->short_name} ({$pu->conversion_qty} {$v->product->unit->short_name})",
                    ])->values()->all(),
                    'price' => $v->sell_price,
                    'cost_price' => $v->cost_price,
                    'promo_discount' => $v->promo_discount,
                    'promo_label' => $v->promo_label,
                    'stock' => (int) ($stocksByVariant[$v->id] ?? 0),
                    'min_stock' => $v->min_stock,
                    'is_bundle' => (bool) $v->product->is_bundle,
                    'thumb_code' => $v->product->thumb_code,
                    'thumb_color' => $v->product->thumb_color,
                ];
            });

        $categories = collect([['id' => 'all', 'label' => 'Semua']])
            ->merge(
                Category::query()->orderBy('id')->get()->map(fn ($c) => [
                    'id' => $c->slug,
                    'label' => $c->name,
                ])
            )->values();

        $customers = collect([['id' => null, 'name' => 'Pelanggan Umum', 'label' => '(Opsional)']])
            ->merge(
                Customer::query()->orderBy('name')->get()->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'label' => $c->type,
                ])
            )->values();

        $heldSales = HeldSale::query()
            ->with('customer')
            ->where('branch_id', $branch->id)
            ->when($user->isKasir(), fn ($q) => $q->where('user_id', $user->id))
            ->latest()
            ->get()
            ->map(fn (HeldSale $h) => [
                'id' => $h->id,
                'code' => $h->hold_code,
                'customer_id' => $h->customer_id,
                'customer_name' => $h->customer?->name ?? 'Pelanggan Umum',
                'time' => $h->created_at->format('H:i').' WIB',
                'total_items' => $h->total_items,
                'estimated_total' => $h->estimated_total,
                'payload' => $h->payload,
            ]);

        // Kasir hanya melihat transaksi miliknya sendiri (Aturan Khusus Bagian 2)
        $recentSales = Sale::query()
            ->with(['items', 'payments.paymentMethod', 'customer', 'user'])
            ->where('branch_id', $branch->id)
            ->when($user->isKasir(), fn ($q) => $q->where('user_id', $user->id))
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (Sale $s) => $this->formatReceiptPayload($s));

        $todayCount = Sale::query()
            ->where('branch_id', $branch->id)
            ->whereDate('created_at', today())
            ->count();

        $shiftStats = null;
        if ($openShift) {
            $shiftSalesCount = Sale::query()
                ->where('cash_shift_id', $openShift->id)
                ->where('status', 'completed')
                ->count();
            $shiftStats = [
                'id' => $openShift->id,
                'code' => $openShift->shift_code,
                'status' => 'Aktif',
                'opened_at' => $openShift->opened_at->format('H:i').' WIB',
                'opening_cash' => $openShift->opening_cash,
                'expected_cash' => $openShift->expected_cash,
                'cash_sales' => max(0, $openShift->expected_cash - $openShift->opening_cash),
                'transactions_count' => $shiftSalesCount,
            ];
        }

        return Inertia::render('pos/Index', [
            'shift' => $shiftStats,
            'settings' => [
                'tax_rate' => $setting?->tax_rate ?? 11,
                'receipt_prefix' => $setting?->receipt_prefix ?? 'INV',
                'printer_name' => $setting?->printer_name ?? 'EPSON TM-T82 (58mm)',
                'paper_width' => $setting?->paper_width ?? 58,
                'chars_per_line' => $setting?->chars_per_line ?? 32,
                'auto_open_drawer' => (bool) ($setting?->auto_open_drawer ?? true),
                'receipt_header' => $setting?->receipt_header ?? "NUSAPOS RETAIL — {$branch->code}",
                'receipt_footer' => $setting?->receipt_footer ?? 'Terima kasih telah berbelanja!',
                'date_display' => now()->translatedFormat('d M Y'),
                'date_code' => now()->format('Ymd'),
                'next_sequence' => $todayCount + 1,
            ],
            'categories' => $categories,
            'customers' => $customers,
            'products' => $variants,
            'heldSales' => $heldSales,
            'recentSales' => $recentSales,
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function checkout(Request $request, CreateSale $createSale): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $shift = $request->attributes->get('cash_shift');

        $validated = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.product_unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'items.*.discount_type' => ['nullable', 'in:nominal,percent'],
            'items.*.discount_value' => ['nullable', 'integer', 'min:0'],
            'trx_discount_type' => ['required', 'in:nominal,percent'],
            'trx_discount_value' => ['required', 'integer', 'min:0'],
            'payment_method_code' => ['required', 'string', 'exists:payment_methods,code'],
            'paid_amount' => ['required', 'integer', 'min:0'],
        ]);

        $sale = $createSale->execute([
            ...$validated,
            'branch_id' => $branch->id,
            'user_id' => $request->user()->id,
            'cash_shift_id' => $shift->id,
        ]);

        return back()
            ->with('success', "Transaksi {$sale->invoice_number} berhasil disimpan.")
            ->with('receipt', $this->formatReceiptPayload($sale));
    }

    public function hold(Request $request, HoldSale $holdSale): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'trx_discount_type' => ['nullable', 'in:nominal,percent'],
            'trx_discount_value' => ['nullable', 'integer', 'min:0'],
        ]);

        $held = $holdSale->execute(
            branchId: $branch->id,
            userId: $request->user()->id,
            items: $validated['items'],
            customerId: $validated['customer_id'] ?? null,
            trxDiscountType: $validated['trx_discount_type'] ?? 'nominal',
            trxDiscountValue: (int) ($validated['trx_discount_value'] ?? 0)
        );

        return back()->with('success', "Transaksi ditahan sebagai {$held->hold_code}.");
    }

    public function deleteHold(Request $request, HeldSale $heldSale): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        if ($heldSale->branch_id !== $branch->id) {
            abort(403);
        }
        $code = $heldSale->hold_code;
        $heldSale->delete();

        return back()->with('success', "Hold {$code} dihapus.");
    }

    public function void(Request $request, VoidSale $voidSale): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $validated = $request->validate([
            'invoice_number' => ['required', 'string'],
            'pin' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $sale = Sale::query()
            ->where('branch_id', $branch->id)
            ->where('invoice_number', $validated['invoice_number'])
            ->firstOrFail();

        $voidSale->execute($sale, $validated['pin'], $validated['reason'], $request->user()->id);

        return back()->with('success', "Transaksi {$sale->invoice_number} berhasil di-void dan stok dikembalikan.");
    }

    public function refund(Request $request, RefundSale $refundSale): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $validated = $request->validate([
            'invoice_number' => ['required', 'string'],
            'pin' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:255'],
            'items' => ['nullable', 'array'],
            'items.*.sale_item_id' => ['required_with:items', 'integer'],
            'items.*.qty' => ['required_with:items', 'integer', 'min:0'],
        ]);

        $sale = Sale::query()
            ->where('branch_id', $branch->id)
            ->where('invoice_number', $validated['invoice_number'])
            ->firstOrFail();

        $ret = $refundSale->execute(
            sale: $sale,
            adminPin: $validated['pin'],
            reason: $validated['reason'],
            requestedByUserId: $request->user()->id,
            partialItems: $validated['items'] ?? null
        );

        return back()->with('success', "Refund {$ret->return_number} (Rp ".number_format($ret->total_refund, 0, ',', '.').") untuk {$sale->invoice_number} berhasil diproses.");
    }

    public function openShift(Request $request, OpenShift $openShift): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $validated = $request->validate([
            'opening_cash' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $shift = $openShift->execute(
            branchId: $branch->id,
            userId: $request->user()->id,
            openingCash: (int) $validated['opening_cash'],
            notes: $validated['notes'] ?? null
        );

        return back()->with('success', "{$shift->shift_code} berhasil dibuka.");
    }

    public function closeShift(Request $request, CloseShift $closeShift): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $validated = $request->validate([
            'closing_cash' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $shift = CashShift::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->firstOrFail();

        $closed = $closeShift->execute($shift, (int) $validated['closing_cash'], $validated['notes'] ?? null);

        return back()->with('success', "{$closed->shift_code} ditutup. Selisih kas: Rp ".number_format($closed->difference ?? 0, 0, ',', '.'));
    }

    /**
     * Mengembalikan public certificate QZ Tray agar browser tidak memunculkan popup untrusted.
     */
    public function qzCert(): \Illuminate\Http\Response
    {
        $certPath = config('pos.qz.certificate_path');
        if ($certPath && is_file($certPath)) {
            return response((string) file_get_contents($certPath), 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        return response('', 204);
    }

    /**
     * Endpoint penandatangan pesan QZ Tray (Bagian 6 Blueprint).
     * Menggunakan RSA private key (openssl_sign) di production; fallback HMAC di lokal.
     */
    public function qzSign(Request $request): JsonResponse
    {
        $payload = (string) $request->input('request', '');
        $privateKeyPath = config('pos.qz.private_key_path');

        if ($privateKeyPath && is_file($privateKeyPath)) {
            $privateKey = openssl_pkey_get_private((string) file_get_contents($privateKeyPath));
            if ($privateKey === false) {
                return response()->json(['error' => 'Private key QZ Tray tidak valid.'], 500);
            }

            $algo = strtolower((string) config('pos.qz.signature_algorithm', 'sha512')) === 'sha256'
                ? OPENSSL_ALGO_SHA256
                : OPENSSL_ALGO_SHA512;

            $signature = '';
            openssl_sign($payload, $signature, $privateKey, $algo);

            return response()->json([
                'signature' => base64_encode($signature),
                'mode' => 'rsa',
            ]);
        }

        if (app()->isProduction()) {
            return response()->json([
                'error' => 'QZ Tray private key belum dikonfigurasi di server production.',
            ], 503);
        }

        $signature = base64_encode(hash_hmac('sha256', $payload, config('app.key'), true));

        return response()->json([
            'signature' => $signature,
            'mode' => 'local-hmac',
        ]);
    }

    private function formatReceiptPayload(Sale $sale): array
    {
        $payment = $sale->payments->first();

        return [
            'id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'status' => $sale->status,
            'timestamp' => $sale->created_at->format('d/m/Y H:i'),
            'cashier_name' => $sale->user?->name ?? 'Kasir',
            'customer_name' => $sale->customer?->name,
            'subtotal' => $sale->subtotal - $sale->item_discount_total,
            'gross_subtotal' => $sale->subtotal,
            'item_discount_total' => $sale->item_discount_total,
            'trx_discount_amount' => $sale->trx_discount_amount,
            'tax_rate' => $sale->tax_rate,
            'tax_amount' => $sale->tax_amount,
            'grand_total' => $sale->grand_total,
            'paid_total' => $sale->paid_total,
            'change_amount' => $sale->change_amount,
            'payment_method' => $payment?->paymentMethod?->name ?? 'Tunai',
            'opens_drawer' => (bool) ($payment?->paymentMethod?->opens_drawer ?? true),
            'items' => $sale->items->map(fn ($i) => [
                'id' => $i->id,
                'product_name' => $i->product_name_snapshot,
                'variant_name' => $i->variant_name_snapshot,
                'unit_name' => $i->unit_name_snapshot,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price_snapshot,
                'discount_amount' => $i->discount_amount,
                'subtotal' => $i->subtotal,
            ])->values()->all(),
        ];
    }
}
