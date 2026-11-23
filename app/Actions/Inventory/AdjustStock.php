<?php

namespace App\Actions\Inventory;

use App\Models\ActivityLog;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdjustStock
{
    /**
     * Prinsip Desain 4.3 #2:
     * Stok tidak pernah diubah langsung. Semua perubahan lewat stock_movements agar bisa diaudit.
     */
    public function execute(
        int $branchId,
        int $variantId,
        int $quantityChange,
        string $type,
        ?int $userId = null,
        ?Model $reference = null,
        ?string $notes = null,
        bool $logActivity = false
    ): StockMovement {
        return DB::transaction(function () use (
            $branchId,
            $variantId,
            $quantityChange,
            $type,
            $userId,
            $reference,
            $notes,
            $logActivity
        ) {
            $stock = Stock::query()->firstOrCreate(
                [
                    'branch_id' => $branchId,
                    'product_variant_id' => $variantId,
                ],
                ['quantity' => 0]
            );

            $newQuantity = $stock->quantity + $quantityChange;
            $stock->update(['quantity' => $newQuantity]);

            $movement = StockMovement::create([
                'branch_id' => $branchId,
                'product_variant_id' => $variantId,
                'user_id' => $userId ?? auth()->id(),
                'type' => $type,
                'quantity_change' => $quantityChange,
                'quantity_after' => $newQuantity,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'created_at' => now(),
            ]);

            if ($logActivity) {
                ActivityLog::record(
                    action: 'stock.adjusted',
                    description: "Penyesuaian stok ({$type}): {$quantityChange} (saldo akhir: {$newQuantity})",
                    branchId: $branchId,
                    userId: $userId ?? auth()->id(),
                    subject: $movement,
                    properties: [
                        'variant_id' => $variantId,
                        'quantity_change' => $quantityChange,
                        'quantity_after' => $newQuantity,
                        'notes' => $notes,
                    ]
                );
            }

            return $movement;
        });
    }
}
