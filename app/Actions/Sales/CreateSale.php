<?php

namespace App\Actions\Sales;

use App\Actions\Inventory\AdjustStock;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\PaymentMethod;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSale
{
    public function __construct(private readonly AdjustStock $adjustStock) {}

    /**
     * @param  array{
     *   client_uuid?: string|null,
     *   branch_id: int,
     *   user_id: int,
     *   cash_shift_id?: int|null,
     *   customer_id?: int|null,
     *   items: array<int, array{
     *     variant_id: int,
     *     qty: int,
     *     product_unit_id?: int|null,
     *     discount_type?: string|null,
     *     discount_value?: int|null,
     *     discount?: int|null
     *   }>,
     *   trx_discount_type?: string,
     *   trx_discount_value?: int,
     *   payment_method_code?: string,
     *   paid_amount: int,
     *   reference_no?: string|null
     * }  $data
     */
    public function execute(array $data): Sale
    {
        $clientUuid = $data['client_uuid'] ?? (string) Str::uuid();

        if ($existing = Sale::query()->where('client_uuid', $clientUuid)->with(['items', 'payments.paymentMethod', 'customer', 'user', 'branch'])->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($data, $clientUuid) {
            $branch = Branch::query()->findOrFail($data['branch_id']);
            $setting = Setting::query()->where('branch_id', $branch->id)->first();
            $taxRate = $setting?->tax_rate ?? 11;
            $prefix = $setting?->receipt_prefix ?: 'INV';

            $paymentMethod = PaymentMethod::query()
                ->where('code', $data['payment_method_code'] ?? 'cash')
                ->firstOrFail();

            $subtotal = 0;
            $itemDiscountTotal = 0;
            $preparedItems = [];

            foreach ($data['items'] as $row) {
                $qty = (int) ($row['qty'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $variant = ProductVariant::query()->with(['product.unit', 'product.bundleItems'])->findOrFail($row['variant_id']);

                // Satuan bertingkat (product_units)
                $productUnit = ! empty($row['product_unit_id'])
                    ? ProductUnit::query()->with('unit')->where('product_variant_id', $variant->id)->find($row['product_unit_id'])
                    : null;

                $conversionQty = $productUnit ? max(1, (int) $productUnit->conversion_qty) : 1;
                $unitPrice = $productUnit ? (int) $productUnit->sell_price : (int) $variant->sell_price;
                $unitCost = $variant->cost_price * $conversionQty;
                $unitName = $productUnit
                    ? "{$productUnit->unit->short_name} ({$conversionQty})"
                    : ($variant->product->unit->short_name ?? 'pcs');

                $gross = $unitPrice * $qty;

                // Diskon per item (manual nominal/persen atau promo bawaan varian)
                if (isset($row['discount_type']) || isset($row['discount_value'])) {
                    $dType = ($row['discount_type'] ?? 'nominal') === 'percent' ? 'percent' : 'nominal';
                    $dVal = max(0, (int) ($row['discount_value'] ?? 0));
                    $itemDisc = $dType === 'percent'
                        ? (int) round(($gross * min(100, $dVal)) / 100)
                        : $dVal;
                } elseif (array_key_exists('discount', $row)) {
                    $itemDisc = max(0, (int) $row['discount']);
                } else {
                    $itemDisc = $variant->promo_discount * $qty * $conversionQty;
                }

                $itemDisc = min($gross, $itemDisc);
                $net = $gross - $itemDisc;

                $subtotal += $gross;
                $itemDiscountTotal += $itemDisc;

                $preparedItems[] = [
                    'variant' => $variant,
                    'qty' => $qty,
                    'conversion_qty' => $conversionQty,
                    'unit_name' => $unitName,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'item_discount' => $itemDisc,
                    'net_subtotal' => $net,
                ];
            }

            if (empty($preparedItems)) {
                throw ValidationException::withMessages(['items' => 'Keranjang transaksi tidak boleh kosong.']);
            }

            $afterItemDisc = max(0, $subtotal - $itemDiscountTotal);
            $trxDiscountType = ($data['trx_discount_type'] ?? 'nominal') === 'percent' ? 'percent' : 'nominal';
            $trxDiscountValue = max(0, (int) ($data['trx_discount_value'] ?? 0));
            $trxDiscountAmount = $trxDiscountType === 'percent'
                ? (int) round(($afterItemDisc * min(100, $trxDiscountValue)) / 100)
                : min($afterItemDisc, $trxDiscountValue);

            $taxable = max(0, $afterItemDisc - $trxDiscountAmount);
            $taxAmount = (int) round(($taxable * $taxRate) / 100);
            $grandTotal = $taxable + $taxAmount;

            $paidTotal = (int) ($data['paid_amount'] ?? 0);
            if ($paymentMethod->code === 'cash' && $paidTotal < $grandTotal) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'Uang diterima kurang dari total tagihan.',
                ]);
            }
            if ($paymentMethod->code !== 'cash') {
                $paidTotal = $grandTotal;
            }
            $changeAmount = max(0, $paidTotal - $grandTotal);

            $todayCount = Sale::query()
                ->where('branch_id', $branch->id)
                ->whereDate('created_at', today())
                ->count() + 1;

            $invoiceNumber = sprintf('%s/%s/%s/%04d', $prefix, $branch->code, now()->format('Ymd'), $todayCount);

            $sale = Sale::create([
                'client_uuid' => $clientUuid,
                'branch_id' => $branch->id,
                'cash_shift_id' => $data['cash_shift_id'] ?? null,
                'user_id' => $data['user_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'invoice_number' => $invoiceNumber,
                'status' => 'completed',
                'subtotal' => $subtotal,
                'item_discount_total' => $itemDiscountTotal,
                'trx_discount_type' => $trxDiscountType,
                'trx_discount_value' => $trxDiscountValue,
                'trx_discount_amount' => $trxDiscountAmount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'paid_total' => $paidTotal,
                'change_amount' => $changeAmount,
            ]);

            foreach ($preparedItems as $item) {
                /** @var ProductVariant $variant */
                $variant = $item['variant'];
                $baseStockDeduct = $item['qty'] * $item['conversion_qty'];

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_variant_id' => $variant->id,
                    'product_name_snapshot' => $variant->product->name,
                    'variant_name_snapshot' => $variant->name,
                    'unit_name_snapshot' => $item['unit_name'],
                    'quantity' => $item['qty'],
                    'cost_price_snapshot' => $item['unit_cost'],
                    'unit_price_snapshot' => $item['unit_price'],
                    'discount_amount' => $item['item_discount'],
                    'subtotal' => $item['net_subtotal'],
                ]);

                if ($variant->product->is_bundle && $variant->product->bundleItems->isNotEmpty()) {
                    foreach ($variant->product->bundleItems as $bItem) {
                        $this->adjustStock->execute(
                            branchId: $branch->id,
                            variantId: $bItem->product_variant_id,
                            quantityChange: -($bItem->quantity * $baseStockDeduct),
                            type: 'sale',
                            userId: $data['user_id'],
                            reference: $sale,
                            notes: "Penjualan bundling {$invoiceNumber}"
                        );
                    }
                } else {
                    $this->adjustStock->execute(
                        branchId: $branch->id,
                        variantId: $variant->id,
                        quantityChange: -$baseStockDeduct,
                        type: 'sale',
                        userId: $data['user_id'],
                        reference: $sale,
                        notes: "Penjualan {$invoiceNumber} ({$item['qty']} {$item['unit_name']})"
                    );
                }
            }

            SalePayment::create([
                'sale_id' => $sale->id,
                'payment_method_id' => $paymentMethod->id,
                'amount' => $grandTotal,
                'reference_no' => $data['reference_no'] ?? null,
                'paid_at' => now(),
            ]);

            if ($paymentMethod->code === 'cash' && $sale->cash_shift_id) {
                CashShift::query()->whereKey($sale->cash_shift_id)->increment('expected_cash', $grandTotal);
            }

            return $sale->load(['items', 'payments.paymentMethod', 'customer', 'user', 'branch']);
        });
    }
}
