<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\ReceiveGoods;
use App\Actions\Purchasing\ReturnToSupplier;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\GoodsReceipt;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchasingController extends Controller
{
    public function index(Request $request): Response
    {
        $branch = $request->attributes->get('branch');

        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier', 'user', 'items.variant.product'])
            ->where('branch_id', $branch->id)
            ->latest('id')
            ->get()
            ->map(fn (PurchaseOrder $po) => [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'supplier_id' => $po->supplier_id,
                'supplier_name' => $po->supplier->name,
                'status' => $po->status,
                'total_amount' => $po->total_amount,
                'paid_amount' => $po->paid_amount,
                'remaining_debt' => max(0, $po->total_amount - $po->paid_amount),
                'payment_status' => $po->payment_status,
                'ordered_at' => $po->ordered_at?->format('d/m/Y H:i'),
                'notes' => $po->notes,
                'items' => $po->items->map(fn ($i) => [
                    'id' => $i->id,
                    'variant_id' => $i->product_variant_id,
                    'sku' => $i->variant?->sku,
                    'name' => $i->variant?->product?->name,
                    'ordered_qty' => $i->ordered_qty,
                    'received_qty' => $i->received_qty,
                    'remaining_qty' => max(0, $i->ordered_qty - $i->received_qty),
                    'unit_cost' => $i->unit_cost,
                    'subtotal' => $i->subtotal,
                ]),
            ]);

        $receipts = GoodsReceipt::query()
            ->with(['purchaseOrder.supplier', 'user', 'items'])
            ->where('branch_id', $branch->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (GoodsReceipt $r) => [
                'id' => $r->id,
                'receipt_number' => $r->receipt_number,
                'po_number' => $r->purchaseOrder?->po_number,
                'supplier_name' => $r->purchaseOrder?->supplier?->name,
                'user_name' => $r->user?->name,
                'total_qty' => $r->items->sum('received_qty'),
                'received_at' => $r->received_at?->format('d/m/Y H:i'),
            ]);

        $returns = PurchaseReturn::query()
            ->with(['supplier', 'user', 'items.variant.product'])
            ->where('branch_id', $branch->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (PurchaseReturn $ret) => [
                'id' => $ret->id,
                'return_number' => $ret->return_number,
                'supplier_name' => $ret->supplier?->name,
                'total_amount' => $ret->total_amount,
                'reason' => $ret->reason,
                'returned_at' => $ret->returned_at?->format('d/m/Y H:i'),
                'items_count' => $ret->items->sum('quantity'),
            ]);

        return Inertia::render('purchasing/Index', [
            'purchaseOrders' => $purchaseOrders,
            'receipts' => $receipts,
            'returns' => $returns,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'code', 'name']),
            'variants' => ProductVariant::query()
                ->with('product')
                ->whereHas('product', fn ($q) => $q->where('is_bundle', false))
                ->orderBy('sku')
                ->get()
                ->map(fn ($v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'name' => $v->product->name,
                    'cost_price' => $v->cost_price,
                ]),
        ]);
    }

    public function storePo(Request $request): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
        ]);

        $po = DB::transaction(function () use ($validated, $branch, $request) {
            $seq = PurchaseOrder::query()->where('branch_id', $branch->id)->count() + 1;
            $poNumber = sprintf('PO/%s/%s/%04d', $branch->code, now()->format('Ymd'), $seq);

            $total = 0;
            $po = PurchaseOrder::create([
                'branch_id' => $branch->id,
                'supplier_id' => $validated['supplier_id'],
                'user_id' => $request->user()->id,
                'po_number' => $poNumber,
                'status' => 'ordered',
                'total_amount' => 0,
                'paid_amount' => 0,
                'payment_status' => 'unpaid',
                'notes' => $validated['notes'] ?? null,
                'ordered_at' => now(),
            ]);

            foreach ($validated['items'] as $row) {
                $subtotal = (int) $row['qty'] * (int) $row['unit_cost'];
                $total += $subtotal;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_variant_id' => $row['variant_id'],
                    'ordered_qty' => $row['qty'],
                    'received_qty' => 0,
                    'unit_cost' => $row['unit_cost'],
                    'subtotal' => $subtotal,
                ]);
            }

            $po->update(['total_amount' => $total]);

            return $po;
        });

        return back()->with('success', "Purchase Order {$po->po_number} berhasil dibuat.");
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, ReceiveGoods $receiveGoods): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        if ($purchaseOrder->branch_id !== $branch->id) {
            abort(403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.po_item_id' => ['required', 'integer'],
            'items.*.received_qty' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $receipt = $receiveGoods->execute($purchaseOrder, $request->user()->id, $validated['items'], $validated['notes'] ?? null);

        return back()->with('success', "Penerimaan {$receipt->receipt_number} disimpan & stok cabang diperbarui.");
    }

    public function paySupplier(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        if ($purchaseOrder->branch_id !== $branch->id) {
            abort(403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'max:30'],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $remaining = max(0, $purchaseOrder->total_amount - $purchaseOrder->paid_amount);
        $payAmount = min((int) $validated['amount'], $remaining);

        if ($payAmount <= 0) {
            return back()->with('error', 'PO ini sudah lunas.');
        }

        DB::transaction(function () use ($purchaseOrder, $payAmount, $validated, $branch, $request) {
            SupplierPayment::create([
                'branch_id' => $branch->id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'purchase_order_id' => $purchaseOrder->id,
                'user_id' => $request->user()->id,
                'amount' => $payAmount,
                'payment_method' => $validated['payment_method'],
                'reference_no' => $validated['reference_no'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'paid_at' => now(),
            ]);

            $newPaid = $purchaseOrder->paid_amount + $payAmount;
            $purchaseOrder->update([
                'paid_amount' => $newPaid,
                'payment_status' => $newPaid >= $purchaseOrder->total_amount ? 'paid' : 'partial',
            ]);

            ActivityLog::record(
                action: 'purchasing.supplier_paid',
                description: "Pembayaran hutang {$purchaseOrder->po_number} sebesar Rp ".number_format($payAmount, 0, ',', '.'),
                branchId: $branch->id,
                userId: $request->user()->id,
                subject: $purchaseOrder
            );
        });

        return back()->with('success', 'Pembayaran hutang supplier berhasil dicatat.');
    }

    public function storeReturn(Request $request, ReturnToSupplier $returnToSupplier): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $ret = $returnToSupplier->execute(
            branchId: $branch->id,
            supplierId: (int) $validated['supplier_id'],
            userId: $request->user()->id,
            items: [['variant_id' => (int) $validated['variant_id'], 'qty' => (int) $validated['qty']]],
            reason: $validated['reason']
        );

        return back()->with('success', "Retur pembelian {$ret->return_number} berhasil diproses.");
    }
}
