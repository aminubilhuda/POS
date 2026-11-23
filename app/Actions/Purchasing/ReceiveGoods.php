<?php

namespace App\Actions\Purchasing;

use App\Actions\Inventory\AdjustStock;
use App\Models\ActivityLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class ReceiveGoods
{
    public function __construct(private readonly AdjustStock $adjustStock) {}

    /**
     * @param  array<int, array{po_item_id: int, received_qty: int}>  $items
     */
    public function execute(PurchaseOrder $po, int $userId, array $items, ?string $notes = null): GoodsReceipt
    {
        return DB::transaction(function () use ($po, $userId, $items, $notes) {
            $po->load('items');
            $seq = GoodsReceipt::query()->where('branch_id', $po->branch_id)->count() + 1;
            $number = sprintf('GRN/%s/%04d', now()->format('Ymd'), $seq);

            $receipt = GoodsReceipt::create([
                'branch_id' => $po->branch_id,
                'purchase_order_id' => $po->id,
                'user_id' => $userId,
                'receipt_number' => $number,
                'notes' => $notes,
                'received_at' => now(),
            ]);

            foreach ($items as $row) {
                $qty = max(0, (int) ($row['received_qty'] ?? 0));
                if ($qty <= 0) {
                    continue;
                }

                $poItem = $po->items->firstWhere('id', (int) $row['po_item_id']);
                if (! $poItem) {
                    continue;
                }

                $remaining = max(0, $poItem->ordered_qty - $poItem->received_qty);
                $actualReceive = min($qty, $remaining);
                if ($actualReceive <= 0) {
                    continue;
                }

                GoodsReceiptItem::create([
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_variant_id' => $poItem->product_variant_id,
                    'received_qty' => $actualReceive,
                    'unit_cost' => $poItem->unit_cost,
                ]);

                $poItem->increment('received_qty', $actualReceive);

                $this->adjustStock->execute(
                    branchId: $po->branch_id,
                    variantId: $poItem->product_variant_id,
                    quantityChange: $actualReceive,
                    type: 'purchase',
                    userId: $userId,
                    reference: $receipt,
                    notes: "Penerimaan PO {$po->po_number} ({$number})"
                );
            }

            $po->refresh()->load('items');
            $allReceived = $po->items->every(fn ($i) => $i->received_qty >= $i->ordered_qty);
            $anyReceived = $po->items->some(fn ($i) => $i->received_qty > 0);

            $po->update([
                'status' => $allReceived ? 'received' : ($anyReceived ? 'partial' : $po->status),
            ]);

            ActivityLog::record(
                action: 'purchasing.goods_received',
                description: "Penerimaan barang {$number} untuk {$po->po_number} (status: {$po->status})",
                branchId: $po->branch_id,
                userId: $userId,
                subject: $receipt
            );

            return $receipt->load('items.variant.product');
        });
    }
}
