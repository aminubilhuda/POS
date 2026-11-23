<?php

namespace App\Actions\Purchasing;

use App\Actions\Inventory\AdjustStock;
use App\Models\ActivityLog;
use App\Models\ProductVariant;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Illuminate\Support\Facades\DB;

class ReturnToSupplier
{
    public function __construct(private readonly AdjustStock $adjustStock) {}

    /**
     * @param  array<int, array{variant_id: int, qty: int, unit_cost?: int}>  $items
     */
    public function execute(
        int $branchId,
        int $supplierId,
        int $userId,
        array $items,
        ?int $purchaseOrderId = null,
        ?string $reason = null
    ): PurchaseReturn {
        return DB::transaction(function () use ($branchId, $supplierId, $userId, $items, $purchaseOrderId, $reason) {
            $seq = PurchaseReturn::query()->where('branch_id', $branchId)->count() + 1;
            $number = sprintf('PRTN/%s/%04d', now()->format('Ymd'), $seq);

            $return = PurchaseReturn::create([
                'branch_id' => $branchId,
                'supplier_id' => $supplierId,
                'purchase_order_id' => $purchaseOrderId,
                'user_id' => $userId,
                'return_number' => $number,
                'total_amount' => 0,
                'reason' => $reason,
                'returned_at' => now(),
            ]);

            $total = 0;

            foreach ($items as $row) {
                $qty = max(0, (int) ($row['qty'] ?? 0));
                if ($qty <= 0) {
                    continue;
                }

                $variant = ProductVariant::query()->findOrFail($row['variant_id']);
                $cost = (int) ($row['unit_cost'] ?? $variant->cost_price);
                $subtotal = $qty * $cost;
                $total += $subtotal;

                PurchaseReturnItem::create([
                    'purchase_return_id' => $return->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'subtotal' => $subtotal,
                ]);

                $this->adjustStock->execute(
                    branchId: $branchId,
                    variantId: $variant->id,
                    quantityChange: -$qty,
                    type: 'purchase_return',
                    userId: $userId,
                    reference: $return,
                    notes: "Retur ke supplier {$number}"
                );
            }

            $return->update(['total_amount' => $total]);

            ActivityLog::record(
                action: 'purchasing.returned_to_supplier',
                description: "Retur pembelian {$number} senilai Rp ".number_format($total, 0, ',', '.'),
                branchId: $branchId,
                userId: $userId,
                subject: $return
            );

            return $return->load('items.variant.product', 'supplier');
        });
    }
}
