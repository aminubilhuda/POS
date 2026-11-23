<?php

namespace App\Actions\Inventory;

use App\Models\ActivityLog;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Support\Facades\DB;

class TransferStock
{
    public function __construct(private readonly AdjustStock $adjustStock) {}

    /**
     * @param  array<int, array{variant_id: int, qty: int}>  $items
     */
    public function send(int $fromBranchId, int $toBranchId, int $userId, array $items, ?string $notes = null): StockTransfer
    {
        return DB::transaction(function () use ($fromBranchId, $toBranchId, $userId, $items, $notes) {
            $seq = StockTransfer::query()->count() + 1;
            $number = sprintf('TRF/%s/%04d', now()->format('Ymd'), $seq);

            $transfer = StockTransfer::create([
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'created_by' => $userId,
                'transfer_number' => $number,
                'status' => 'in_transit',
                'notes' => $notes,
                'sent_at' => now(),
            ]);

            foreach ($items as $row) {
                $qty = (int) $row['qty'];
                if ($qty <= 0) {
                    continue;
                }

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_variant_id' => (int) $row['variant_id'],
                    'sent_qty' => $qty,
                    'received_qty' => 0,
                    'difference' => 0,
                ]);

                $this->adjustStock->execute(
                    branchId: $fromBranchId,
                    variantId: (int) $row['variant_id'],
                    quantityChange: -$qty,
                    type: 'transfer_out',
                    userId: $userId,
                    reference: $transfer,
                    notes: "Transfer keluar {$number}"
                );
            }

            ActivityLog::record(
                action: 'inventory.transfer_sent',
                description: "Transfer stok {$number} dikirim ke cabang #{$toBranchId}",
                branchId: $fromBranchId,
                userId: $userId,
                subject: $transfer
            );

            return $transfer->load('items.variant.product', 'fromBranch', 'toBranch');
        });
    }

    /**
     * @param  array<int, int>|null  $receivedByItemId  map of stock_transfer_item_id => received_qty
     */
    public function receive(StockTransfer $transfer, int $userId, ?array $receivedByItemId = null): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $userId, $receivedByItemId) {
            if ($transfer->status !== 'in_transit') {
                return $transfer;
            }

            $transfer->load('items');

            foreach ($transfer->items as $item) {
                $receivedQty = $receivedByItemId !== null && array_key_exists($item->id, $receivedByItemId)
                    ? max(0, (int) $receivedByItemId[$item->id])
                    : $item->sent_qty;

                $item->update([
                    'received_qty' => $receivedQty,
                    'difference' => $receivedQty - $item->sent_qty,
                ]);

                if ($receivedQty > 0) {
                    $this->adjustStock->execute(
                        branchId: $transfer->to_branch_id,
                        variantId: $item->product_variant_id,
                        quantityChange: $receivedQty,
                        type: 'transfer_in',
                        userId: $userId,
                        reference: $transfer,
                        notes: "Transfer masuk {$transfer->transfer_number}"
                    );
                }
            }

            $transfer->update([
                'status' => 'received',
                'received_by' => $userId,
                'received_at' => now(),
            ]);

            ActivityLog::record(
                action: 'inventory.transfer_received',
                description: "Transfer stok {$transfer->transfer_number} diterima",
                branchId: $transfer->to_branch_id,
                userId: $userId,
                subject: $transfer
            );

            return $transfer->fresh(['items.variant.product', 'fromBranch', 'toBranch']);
        });
    }
}
