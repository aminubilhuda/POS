<?php

namespace App\Actions\Sales;

use App\Actions\Inventory\AdjustStock;
use App\Models\ActivityLog;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundSale
{
    public function __construct(private readonly AdjustStock $adjustStock) {}

    /**
     * Mendukung refund penuh maupun refund parsial per item.
     *
     * @param  array<int, array{sale_item_id: int, qty: int}>|null  $partialItems
     */
    public function execute(
        Sale $sale,
        string $adminPin,
        string $reason,
        int $requestedByUserId,
        ?array $partialItems = null
    ): SaleReturn {
        if (! in_array($sale->status, ['completed', 'partial_refund'], true)) {
            throw ValidationException::withMessages([
                'invoice_number' => 'Transaksi ini tidak dapat di-refund.',
            ]);
        }

        $authorizer = User::query()
            ->whereIn('role', ['owner', 'admin'])
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->get()
            ->first(fn (User $u) => $u->canAccessBranch($sale->branch_id) && $u->verifyPin($adminPin));

        if (! $authorizer) {
            throw ValidationException::withMessages([
                'pin' => 'PIN otorisasi Admin tidak valid untuk cabang ini.',
            ]);
        }

        return DB::transaction(function () use ($sale, $reason, $authorizer, $requestedByUserId, $partialItems) {
            $sale->load(['items', 'returns.items']);

            $alreadyReturnedByItem = [];
            foreach ($sale->returns as $ret) {
                foreach ($ret->items as $ri) {
                    $alreadyReturnedByItem[$ri->sale_item_id] = ($alreadyReturnedByItem[$ri->sale_item_id] ?? 0) + $ri->quantity;
                }
            }

            $rowsToReturn = [];
            if ($partialItems !== null && count($partialItems) > 0) {
                foreach ($partialItems as $req) {
                    $qty = max(0, (int) ($req['qty'] ?? 0));
                    if ($qty <= 0) {
                        continue;
                    }
                    $saleItem = $sale->items->firstWhere('id', (int) $req['sale_item_id']);
                    if (! $saleItem) {
                        continue;
                    }
                    $remaining = max(0, $saleItem->quantity - ($alreadyReturnedByItem[$saleItem->id] ?? 0));
                    $actualQty = min($qty, $remaining);
                    if ($actualQty > 0) {
                        $rowsToReturn[] = ['item' => $saleItem, 'qty' => $actualQty];
                    }
                }
            } else {
                foreach ($sale->items as $saleItem) {
                    $remaining = max(0, $saleItem->quantity - ($alreadyReturnedByItem[$saleItem->id] ?? 0));
                    if ($remaining > 0) {
                        $rowsToReturn[] = ['item' => $saleItem, 'qty' => $remaining];
                    }
                }
            }

            if (empty($rowsToReturn)) {
                throw ValidationException::withMessages([
                    'items' => 'Pilih minimal 1 item dengan jumlah retur yang valid.',
                ]);
            }

            $seq = SaleReturn::query()->where('branch_id', $sale->branch_id)->count() + 1;
            $returnNumber = sprintf('RTR/%s/%04d', now()->format('Ymd'), $seq);

            $subtotalRefund = 0;
            $prepared = [];
            foreach ($rowsToReturn as $row) {
                $item = $row['item'];
                $qty = $row['qty'];
                $perUnitNet = $item->quantity > 0 ? (int) round($item->subtotal / $item->quantity) : $item->unit_price_snapshot;
                $lineRefund = $perUnitNet * $qty;
                $subtotalRefund += $lineRefund;
                $prepared[] = ['item' => $item, 'qty' => $qty, 'refund' => $lineRefund];
            }

            $taxRefund = (int) round(($subtotalRefund * $sale->tax_rate) / 100);
            $totalRefund = $subtotalRefund + $taxRefund;

            $saleReturn = SaleReturn::create([
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
                'user_id' => $requestedByUserId,
                'authorized_by' => $authorizer->id,
                'return_number' => $returnNumber,
                'total_refund' => $totalRefund,
                'reason' => $reason,
                'returned_at' => now(),
            ]);

            foreach ($prepared as $row) {
                $item = $row['item'];
                $qty = $row['qty'];

                SaleReturnItem::create([
                    'sale_return_id' => $saleReturn->id,
                    'sale_item_id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $qty,
                    'refund_amount' => $row['refund'],
                ]);

                $this->adjustStock->execute(
                    branchId: $sale->branch_id,
                    variantId: $item->product_variant_id,
                    quantityChange: $qty,
                    type: 'sale_return',
                    userId: $requestedByUserId,
                    reference: $saleReturn,
                    notes: "Refund {$returnNumber} ({$sale->invoice_number})"
                );

                $alreadyReturnedByItem[$item->id] = ($alreadyReturnedByItem[$item->id] ?? 0) + $qty;
            }

            $allReturned = $sale->items->every(fn ($i) => ($alreadyReturnedByItem[$i->id] ?? 0) >= $i->quantity);
            $sale->update(['status' => $allReturned ? 'refunded' : 'partial_refund']);

            ActivityLog::record(
                action: 'sale.refunded',
                description: "Refund {$returnNumber} untuk {$sale->invoice_number} (Rp ".number_format($totalRefund, 0, ',', '.').", otorisasi: {$authorizer->name})",
                branchId: $sale->branch_id,
                userId: $requestedByUserId,
                subject: $saleReturn,
                properties: [
                    'authorized_by' => $authorizer->id,
                    'total_refund' => $totalRefund,
                    'partial' => ! $allReturned,
                    'reason' => $reason,
                ]
            );

            return $saleReturn->load('items');
        });
    }
}
