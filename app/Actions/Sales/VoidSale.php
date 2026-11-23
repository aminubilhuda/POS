<?php

namespace App\Actions\Sales;

use App\Actions\Inventory\AdjustStock;
use App\Models\ActivityLog;
use App\Models\CashShift;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidSale
{
    public function __construct(private readonly AdjustStock $adjustStock) {}

    public function execute(Sale $sale, string $adminPin, string $reason, int $requestedByUserId): Sale
    {
        if ($sale->status !== 'completed') {
            throw ValidationException::withMessages([
                'invoice_number' => 'Transaksi ini sudah pernah di-void atau di-refund.',
            ]);
        }

        $authorizer = $this->authorizePin($adminPin, $sale->branch_id);

        return DB::transaction(function () use ($sale, $reason, $authorizer, $requestedByUserId) {
            $sale->load('items.variant.product.bundleItems', 'payments.paymentMethod');

            foreach ($sale->items as $item) {
                $variant = $item->variant;
                if ($variant && $variant->product->is_bundle && $variant->product->bundleItems->isNotEmpty()) {
                    foreach ($variant->product->bundleItems as $bItem) {
                        $this->adjustStock->execute(
                            branchId: $sale->branch_id,
                            variantId: $bItem->product_variant_id,
                            quantityChange: $bItem->quantity * $item->quantity,
                            type: 'sale_void',
                            userId: $requestedByUserId,
                            reference: $sale,
                            notes: "Void bundling {$sale->invoice_number}"
                        );
                    }
                } else {
                    $this->adjustStock->execute(
                        branchId: $sale->branch_id,
                        variantId: $item->product_variant_id,
                        quantityChange: $item->quantity,
                        type: 'sale_void',
                        userId: $requestedByUserId,
                        reference: $sale,
                        notes: "Void transaksi {$sale->invoice_number}"
                    );
                }
            }

            $cashPayment = $sale->payments->first(fn ($p) => $p->paymentMethod?->code === 'cash');
            if ($cashPayment && $sale->cash_shift_id) {
                CashShift::query()->whereKey($sale->cash_shift_id)->decrement('expected_cash', $cashPayment->amount);
            }

            $sale->update([
                'status' => 'voided',
                'voided_by' => $authorizer->id,
                'void_reason' => $reason,
                'voided_at' => now(),
            ]);

            ActivityLog::record(
                action: 'sale.voided',
                description: "Void transaksi {$sale->invoice_number} (otorisasi: {$authorizer->name}) · Alasan: {$reason}",
                branchId: $sale->branch_id,
                userId: $requestedByUserId,
                subject: $sale,
                properties: [
                    'authorized_by' => $authorizer->id,
                    'grand_total' => $sale->grand_total,
                    'reason' => $reason,
                ]
            );

            return $sale->fresh();
        });
    }

    private function authorizePin(string $pin, int $branchId): User
    {
        $candidates = User::query()
            ->whereIn('role', ['owner', 'admin'])
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->get();

        foreach ($candidates as $user) {
            if ($user->canAccessBranch($branchId) && $user->verifyPin($pin)) {
                return $user;
            }
        }

        throw ValidationException::withMessages([
            'pin' => 'PIN otorisasi Admin tidak valid untuk cabang ini.',
        ]);
    }
}
