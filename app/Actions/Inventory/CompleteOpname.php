<?php

namespace App\Actions\Inventory;

use App\Models\ActivityLog;
use App\Models\Stock;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Support\Facades\DB;

class CompleteOpname
{
    public function __construct(private readonly AdjustStock $adjustStock) {}

    /**
     * @param  array<int, array{variant_id: int, physical_qty: int, notes?: string|null}>  $items
     */
    public function execute(int $branchId, int $userId, array $items, ?string $notes = null): StockOpname
    {
        return DB::transaction(function () use ($branchId, $userId, $items, $notes) {
            $seq = StockOpname::query()->where('branch_id', $branchId)->count() + 1;
            $number = sprintf('OPN/%s/%04d', now()->format('Ymd'), $seq);

            $opname = StockOpname::create([
                'branch_id' => $branchId,
                'user_id' => $userId,
                'opname_number' => $number,
                'status' => 'completed',
                'notes' => $notes,
                'completed_at' => now(),
            ]);

            foreach ($items as $row) {
                $variantId = (int) $row['variant_id'];
                $physicalQty = (int) $row['physical_qty'];
                $systemQty = (int) (Stock::query()
                    ->where('branch_id', $branchId)
                    ->where('product_variant_id', $variantId)
                    ->value('quantity') ?? 0);

                $diff = $physicalQty - $systemQty;

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'product_variant_id' => $variantId,
                    'system_qty' => $systemQty,
                    'physical_qty' => $physicalQty,
                    'difference' => $diff,
                    'notes' => $row['notes'] ?? null,
                ]);

                if ($diff !== 0) {
                    $this->adjustStock->execute(
                        branchId: $branchId,
                        variantId: $variantId,
                        quantityChange: $diff,
                        type: 'opname',
                        userId: $userId,
                        reference: $opname,
                        notes: "Stok opname {$number}"
                    );
                }
            }

            ActivityLog::record(
                action: 'inventory.opname_completed',
                description: "Stok opname {$number} selesai (".count($items).' item)',
                branchId: $branchId,
                userId: $userId,
                subject: $opname
            );

            return $opname->load('items.variant.product');
        });
    }
}
