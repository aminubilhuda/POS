<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $this->buildReportData($request);

        return Inertia::render('reports/Index', $data);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $data = $this->buildReportData($request);
        $filename = 'laporan-pos-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM agar Excel membuka karakter Rupiah dengan benar
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['LAPORAN PENJUALAN & LABA RUGI NUSAPOS RETAIL']);
            fputcsv($out, ['Cakupan', $data['scopeLabel']]);
            fputcsv($out, ['Periode', "{$data['filters']['from']} s/d {$data['filters']['to']}"]);
            fputcsv($out, []);

            fputcsv($out, ['RINGKASAN LABA RUGI']);
            fputcsv($out, ['Total Transaksi', $data['summary']['transactions_count']]);
            fputcsv($out, ['Penjualan Bersih (Rp)', $data['summary']['net_sales']]);
            fputcsv($out, ['PPN Terkumpul (Rp)', $data['summary']['tax_total']]);
            fputcsv($out, ['HPP / Modal (Rp)', $data['summary']['cogs']]);
            fputcsv($out, ['Laba Kotor (Rp)', $data['summary']['gross_profit']]);
            fputcsv($out, ['Nilai Persediaan Stok (Rp)', $data['summary']['inventory_value']]);
            fputcsv($out, []);

            fputcsv($out, ['PRODUK TERLARIS']);
            fputcsv($out, ['Produk', 'Varian', 'Qty Terjual', 'Omzet (Rp)', 'Laba Kotor (Rp)']);
            foreach ($data['topProducts'] as $row) {
                fputcsv($out, [$row['product_name'], $row['variant_name'], $row['qty_sold'], $row['revenue'], $row['profit']]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request): View
    {
        $data = $this->buildReportData($request);

        return view('reports.print', $data);
    }

    private function buildReportData(Request $request): array
    {
        $user = $request->user();
        $branch = $request->attributes->get('branch');

        $scopeAll = $user->isOwner() && $request->string('scope')->toString() === 'all';
        $from = $request->string('from')->toString() ?: now()->subDays(30)->toDateString();
        $to = $request->string('to')->toString() ?: now()->toDateString();

        $salesQuery = Sale::query()
            ->where('status', 'completed')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->when(! $scopeAll, fn ($q) => $q->where('branch_id', $branch->id));

        $sales = (clone $salesQuery)->with(['branch', 'user'])->get();
        $saleIds = $sales->pluck('id');

        $netSales = $sales->sum(fn ($s) => $s->grand_total - $s->tax_amount);
        $grossTotal = $sales->sum('grand_total');
        $taxTotal = $sales->sum('tax_amount');

        $cogs = (int) SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->selectRaw('COALESCE(SUM(cost_price_snapshot * quantity), 0) as total_cogs')
            ->value('total_cogs');

        $grossProfit = $netSales - $cogs;
        $marginPct = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 1) : 0;

        $inventoryValue = (int) Stock::query()
            ->join('product_variants', 'product_variants.id', '=', 'stocks.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.is_bundle', false)
            ->when(! $scopeAll, fn ($q) => $q->where('stocks.branch_id', $branch->id))
            ->selectRaw('COALESCE(SUM(stocks.quantity * product_variants.cost_price), 0) as val')
            ->value('val');

        // Penjualan Harian
        $dailySales = $sales
            ->groupBy(fn ($s) => $s->created_at->format('Y-m-d'))
            ->map(fn ($group, $date) => [
                'date' => $date,
                'transactions' => $group->count(),
                'grand_total' => $group->sum('grand_total'),
                'tax_amount' => $group->sum('tax_amount'),
            ])
            ->sortByDesc('date')
            ->values();

        // Produk Terlaris
        $topProducts = SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->select([
                'product_name_snapshot as product_name',
                'variant_name_snapshot as variant_name',
                DB::raw('SUM(quantity) as qty_sold'),
                DB::raw('SUM(subtotal) as revenue'),
                DB::raw('SUM(subtotal - (cost_price_snapshot * quantity)) as profit'),
            ])
            ->groupBy('product_name_snapshot', 'variant_name_snapshot')
            ->orderByDesc('qty_sold')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'product_name' => $r->product_name,
                'variant_name' => $r->variant_name,
                'qty_sold' => (int) $r->qty_sold,
                'revenue' => (int) $r->revenue,
                'profit' => (int) $r->profit,
            ]);

        // Kinerja Kasir
        $cashierPerformance = $sales
            ->groupBy('user_id')
            ->map(fn ($group) => [
                'cashier_name' => $group->first()->user?->name ?? 'Kasir',
                'role' => $group->first()->user?->role ?? 'kasir',
                'transactions' => $group->count(),
                'total_sales' => $group->sum('grand_total'),
            ])
            ->sortByDesc('total_sales')
            ->values();

        // Grafik 7 hari terakhir (Penjualan Bersih vs Laba Kotor)
        $chartSeries = collect();
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $key = $d->format('Y-m-d');
            $daySales = $sales->filter(fn ($s) => $s->created_at->format('Y-m-d') === $key);
            $dayIds = $daySales->pluck('id');
            $dayNet = $daySales->sum(fn ($s) => $s->grand_total - $s->tax_amount);
            $dayCogs = $dayIds->isEmpty()
                ? 0
                : (int) SaleItem::query()
                    ->whereIn('sale_id', $dayIds)
                    ->selectRaw('COALESCE(SUM(cost_price_snapshot * quantity), 0) as c')
                    ->value('c');

            $chartSeries->push([
                'date' => $key,
                'label' => $d->translatedFormat('d M'),
                'net_sales' => $dayNet,
                'gross_profit' => max(0, $dayNet - $dayCogs),
            ]);
        }

        return [
            'canViewAllBranches' => $user->isOwner(),
            'scopeLabel' => $scopeAll ? 'Semua Cabang (Gabungan)' : "{$branch->code} — {$branch->name}",
            'filters' => [
                'scope' => $scopeAll ? 'all' : 'branch',
                'from' => $from,
                'to' => $to,
            ],
            'summary' => [
                'transactions_count' => $sales->count(),
                'gross_total' => $grossTotal,
                'net_sales' => $netSales,
                'tax_total' => $taxTotal,
                'cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'margin_pct' => $marginPct,
                'inventory_value' => $inventoryValue,
            ],
            'chartSeries' => $chartSeries,
            'dailySales' => $dailySales,
            'topProducts' => $topProducts,
            'cashierPerformance' => $cashierPerformance,
        ];
    }
}
