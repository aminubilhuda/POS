<?php

namespace App\Actions\Sales;

use App\Models\HeldSale;
use App\Models\ProductVariant;

class HoldSale
{
    /**
     * @param  array<int, array{variant_id: int, qty: int}>  $items
     */
    public function execute(
        int $branchId,
        int $userId,
        array $items,
        ?int $customerId = null,
        string $trxDiscountType = 'nominal',
        int $trxDiscountValue = 0
    ): HeldSale {
        $totalItems = 0;
        $estimatedTotal = 0;

        foreach ($items as $item) {
            $qty = (int) ($item['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $variant = ProductVariant::query()->find($item['variant_id']);
            if (! $variant) {
                continue;
            }
            $totalItems += $qty;
            $estimatedTotal += max(0, ($variant->sell_price - $variant->promo_discount) * $qty);
        }

        $seq = HeldSale::query()->where('branch_id', $branchId)->count() + 1;

        return HeldSale::create([
            'branch_id' => $branchId,
            'user_id' => $userId,
            'customer_id' => $customerId,
            'hold_code' => sprintf('HOLD-%02d', $seq),
            'payload' => [
                'items' => $items,
                'trx_discount_type' => $trxDiscountType,
                'trx_discount_value' => $trxDiscountValue,
            ],
            'total_items' => $totalItems,
            'estimated_total' => $estimatedTotal,
        ]);
    }
}
