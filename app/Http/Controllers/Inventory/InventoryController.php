<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\AdjustStock;
use App\Actions\Inventory\CompleteOpname;
use App\Actions\Inventory\TransferStock;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $branch = $request->attributes->get('branch');

        $stocksByVariant = Stock::query()
            ->where('branch_id', $branch->id)
            ->pluck('quantity', 'product_variant_id');

        $inventory = ProductVariant::query()
            ->with(['product.category', 'product.unit'])
            ->whereHas('product', fn ($q) => $q->where('is_bundle', false))
            ->orderBy('sku')
            ->get()
            ->map(function (ProductVariant $v) use ($stocksByVariant) {
                $qty = (int) ($stocksByVariant[$v->id] ?? 0);

                return [
                    'variant_id' => $v->id,
                    'sku' => $v->sku,
                    'barcode' => $v->barcode,
                    'product_name' => $v->product->name,
                    'variant_name' => $v->name,
                    'category' => $v->product->category->name,
                    'unit' => $v->product->unit->short_name,
                    'quantity' => $qty,
                    'min_stock' => $v->min_stock,
                    'cost_price' => $v->cost_price,
                    'stock_value' => $qty * $v->cost_price,
                    'is_low' => $qty <= $v->min_stock,
                ];
            });

        $mSearch = $request->string('m_search')->trim()->toString();
        $mPerPage = in_array((int) $request->input('m_per_page', 10), [10, 25, 50], true)
            ? (int) $request->input('m_per_page', 10)
            : 10;

        $movements = StockMovement::query()
            ->with(['variant.product', 'user'])
            ->where('branch_id', $branch->id)
            ->when($mSearch !== '', function ($query) use ($mSearch) {
                $like = "%{$mSearch}%";
                $query->where(function ($q) use ($like) {
                    $q->where('type', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhereHas('variant', fn ($v) => $v->where('sku', 'like', $like))
                        ->orWhereHas('variant.product', fn ($p) => $p->where('name', 'like', $like));
                });
            })
            ->latest('id')
            ->paginate($mPerPage, ['*'], 'm_page')
            ->withQueryString()
            ->through(fn (StockMovement $m) => [
                'id' => $m->id,
                'date' => $m->created_at->format('d/m/Y H:i'),
                'sku' => $m->variant?->sku,
                'product_name' => $m->variant?->product?->name,
                'type' => $m->type,
                'quantity_change' => $m->quantity_change,
                'quantity_after' => $m->quantity_after,
                'user_name' => $m->user?->name ?? 'Sistem',
                'notes' => $m->notes,
            ]);

        $opnames = StockOpname::query()
            ->with(['user', 'items.variant.product'])
            ->where('branch_id', $branch->id)
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (StockOpname $o) => [
                'id' => $o->id,
                'opname_number' => $o->opname_number,
                'date' => $o->completed_at?->format('d/m/Y H:i'),
                'user_name' => $o->user?->name,
                'items_count' => $o->items->count(),
                'total_diff' => $o->items->sum('difference'),
                'notes' => $o->notes,
            ]);

        $transfers = StockTransfer::query()
            ->with(['fromBranch', 'toBranch', 'creator', 'receiver', 'items.variant.product'])
            ->where(function ($q) use ($branch) {
                $q->where('from_branch_id', $branch->id)->orWhere('to_branch_id', $branch->id);
            })
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (StockTransfer $t) => [
                'id' => $t->id,
                'transfer_number' => $t->transfer_number,
                'from_branch' => "{$t->fromBranch->code} — {$t->fromBranch->name}",
                'to_branch' => "{$t->toBranch->code} — {$t->toBranch->name}",
                'is_incoming' => $t->to_branch_id === $branch->id,
                'status' => $t->status,
                'sent_at' => $t->sent_at?->format('d/m/Y H:i'),
                'received_at' => $t->received_at?->format('d/m/Y H:i'),
                'notes' => $t->notes,
                'items' => $t->items->map(fn ($i) => [
                    'id' => $i->id,
                    'sku' => $i->variant?->sku,
                    'name' => $i->variant?->product?->name,
                    'sent_qty' => $i->sent_qty,
                    'received_qty' => $i->received_qty,
                    'difference' => $i->difference,
                ]),
            ]);

        return Inertia::render('inventory/Index', [
            'inventory' => $inventory,
            'movements' => $movements,
            'movementFilters' => [
                'm_search' => $mSearch,
                'm_per_page' => $mPerPage,
            ],
            'opnames' => $opnames,
            'transfers' => $transfers,
            'otherBranches' => Branch::query()
                ->where('id', '!=', $branch->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function adjust(Request $request, AdjustStock $adjustStock): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'notes' => ['required', 'string', 'max:255'],
        ]);

        $adjustStock->execute(
            branchId: $branch->id,
            variantId: (int) $validated['variant_id'],
            quantityChange: (int) $validated['quantity_change'],
            type: 'adjustment',
            userId: $request->user()->id,
            notes: $validated['notes'],
            logActivity: true
        );

        return back()->with('success', 'Penyesuaian stok berhasil dicatat ke kartu stok.');
    }

    public function opname(Request $request, CompleteOpname $completeOpname): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.physical_qty' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $opname = $completeOpname->execute($branch->id, $request->user()->id, $validated['items'], $validated['notes'] ?? null);

        return back()->with('success', "Stok opname {$opname->opname_number} selesai dan saldo diperbarui.");
    }

    public function sendTransfer(Request $request, TransferStock $transferStock): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $validated = $request->validate([
            'to_branch_id' => ['required', 'integer', 'exists:branches,id', 'different:from_branch_id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $transfer = $transferStock->send(
            fromBranchId: $branch->id,
            toBranchId: (int) $validated['to_branch_id'],
            userId: $request->user()->id,
            items: $validated['items'],
            notes: $validated['notes'] ?? null
        );

        return back()->with('success', "Transfer {$transfer->transfer_number} dikirim.");
    }

    public function receiveTransfer(Request $request, StockTransfer $transfer, TransferStock $transferStock): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        if ($transfer->to_branch_id !== $branch->id) {
            abort(403, 'Transfer ini bukan ditujukan ke cabang aktif Anda.');
        }

        $validated = $request->validate([
            'received' => ['nullable', 'array'],
        ]);

        $transferStock->receive($transfer, $request->user()->id, $validated['received'] ?? null);

        return back()->with('success', "Transfer {$transfer->transfer_number} berhasil diterima dan stok ditambahkan.");
    }
}
