<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $branch = $request->attributes->get('branch');
        $search = $request->string('search')->trim()->toString();
        $perPage = in_array((int) $request->input('per_page', 10), [5, 10, 25, 50], true)
            ? (int) $request->input('per_page', 10)
            : 10;

        $stocksByVariant = Stock::query()
            ->where('branch_id', $branch->id)
            ->pluck('quantity', 'product_variant_id');

        $variants = ProductVariant::query()
            ->with(['product.category', 'product.unit', 'product.bundleItems.variant.product', 'productUnits.unit'])
            ->when($search !== '', function ($query) use ($search) {
                $like = "%{$search}%";
                $query->where(function ($q) use ($like) {
                    $q->where('sku', 'like', $like)
                        ->orWhere('barcode', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $like));
                });
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ProductVariant $v) => [
                'id' => $v->id,
                'product_id' => $v->product_id,
                'name' => $v->product->name,
                'category_id' => $v->product->category_id,
                'category_name' => $v->product->category->name,
                'unit_name' => $v->product->unit->short_name,
                'variant_name' => $v->name,
                'sku' => $v->sku,
                'barcode' => $v->barcode,
                'cost_price' => $v->cost_price,
                'sell_price' => $v->sell_price,
                'promo_discount' => $v->promo_discount,
                'promo_label' => $v->promo_label,
                'min_stock' => $v->min_stock,
                'is_bundle' => (bool) $v->product->is_bundle,
                'stock' => (int) ($stocksByVariant[$v->id] ?? 0),
                'product_units' => $v->productUnits->map(fn ($pu) => [
                    'unit' => $pu->unit->short_name,
                    'conversion_qty' => $pu->conversion_qty,
                    'sell_price' => $pu->sell_price,
                ]),
                'bundle_components' => $v->product->bundleItems->map(fn ($bi) => [
                    'name' => $bi->variant?->product?->name,
                    'qty' => $bi->quantity,
                ]),
            ]);

        return Inertia::render('products/Index', [
            'variants' => $variants,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'units' => Unit::query()->orderBy('name')->get(['id', 'name', 'short_name']),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'customers' => Customer::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'variant_name' => ['required', 'string', 'max:100'],
            'sku' => ['required', 'string', 'max:60', 'unique:product_variants,sku'],
            'barcode' => ['required', 'string', 'max:80'],
            'cost_price' => ['required', 'integer', 'min:0'],
            'sell_price' => ['required', 'integer', 'min:0'],
            'promo_discount' => ['nullable', 'integer', 'min:0'],
            'promo_label' => ['nullable', 'string', 'max:80'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'is_bundle' => ['boolean'],
            'extra_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'extra_conversion_qty' => ['nullable', 'integer', 'min:2'],
            'extra_sell_price' => ['nullable', 'integer', 'min:0'],
            'bundle_items' => ['nullable', 'array'],
            'bundle_items.*.variant_id' => ['integer', 'exists:product_variants,id'],
            'bundle_items.*.qty' => ['integer', 'min:1'],
        ]);

        DB::transaction(function () use ($validated, $branch, $request) {
            $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $validated['name']) ?: 'PR', 0, 2));
            $product = Product::create([
                'category_id' => $validated['category_id'],
                'unit_id' => $validated['unit_id'],
                'name' => $validated['name'],
                'is_bundle' => (bool) ($validated['is_bundle'] ?? false),
                'thumb_code' => $initials,
                'thumb_color' => ($validated['is_bundle'] ?? false) ? 'violet' : 'teal',
            ]);

            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'name' => $validated['variant_name'],
                'sku' => strtoupper($validated['sku']),
                'barcode' => $validated['barcode'],
                'cost_price' => $validated['cost_price'],
                'sell_price' => $validated['sell_price'],
                'promo_discount' => (int) ($validated['promo_discount'] ?? 0),
                'promo_label' => $validated['promo_label'] ?: null,
                'min_stock' => $validated['min_stock'],
            ]);

            Stock::firstOrCreate(
                ['branch_id' => $branch->id, 'product_variant_id' => $variant->id],
                ['quantity' => 0]
            );

            if (! empty($validated['extra_unit_id']) && ! empty($validated['extra_conversion_qty'])) {
                ProductUnit::create([
                    'product_variant_id' => $variant->id,
                    'unit_id' => $validated['extra_unit_id'],
                    'conversion_qty' => $validated['extra_conversion_qty'],
                    'sell_price' => (int) ($validated['extra_sell_price'] ?? ($validated['sell_price'] * $validated['extra_conversion_qty'])),
                    'barcode' => $validated['barcode'].'-U',
                ]);
            }

            if ($product->is_bundle && ! empty($validated['bundle_items'])) {
                foreach ($validated['bundle_items'] as $bi) {
                    BundleItem::create([
                        'bundle_product_id' => $product->id,
                        'product_variant_id' => $bi['variant_id'],
                        'quantity' => $bi['qty'],
                    ]);
                }
            }

            ActivityLog::record(
                action: 'product.created',
                description: "Produk baru dibuat: {$product->name} ({$variant->sku})",
                branchId: $branch->id,
                userId: $request->user()->id,
                subject: $variant
            );
        });

        return back()->with('success', "Produk {$validated['name']} berhasil ditambahkan.");
    }

    public function updateVariant(Request $request, ProductVariant $variant): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $validated = $request->validate([
            'cost_price' => ['required', 'integer', 'min:0'],
            'sell_price' => ['required', 'integer', 'min:0'],
            'promo_discount' => ['nullable', 'integer', 'min:0'],
            'promo_label' => ['nullable', 'string', 'max:80'],
            'min_stock' => ['required', 'integer', 'min:0'],
        ]);

        $oldSell = $variant->sell_price;
        $variant->update([
            'cost_price' => $validated['cost_price'],
            'sell_price' => $validated['sell_price'],
            'promo_discount' => (int) ($validated['promo_discount'] ?? 0),
            'promo_label' => $validated['promo_label'] ?: null,
            'min_stock' => $validated['min_stock'],
        ]);

        if ($oldSell !== (int) $validated['sell_price']) {
            ActivityLog::record(
                action: 'product.price_changed',
                description: "Harga jual {$variant->sku} diubah dari Rp ".number_format($oldSell, 0, ',', '.').' menjadi Rp '.number_format($variant->sell_price, 0, ',', '.'),
                branchId: $branch->id,
                userId: $request->user()->id,
                subject: $variant,
                properties: ['old_price' => $oldSell, 'new_price' => $variant->sell_price]
            );
        }

        return back()->with('success', "Varian {$variant->sku} diperbarui.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;
        $product->variants()->delete();
        $product->delete();

        return back()->with('success', "Produk {$name} dihapus (soft delete).");
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:80']]);
        Category::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(3)),
        ]);

        return back()->with('success', "Kategori {$validated['name']} ditambahkan.");
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'short_name' => ['required', 'string', 'max:20'],
        ]);
        Unit::create($validated);

        return back()->with('success', "Satuan {$validated['name']} ditambahkan.");
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'type' => ['required', 'string', 'max:30'],
        ]);
        $seq = Customer::withTrashed()->count() + 1;
        Customer::create([
            ...$validated,
            'code' => sprintf('CUST-%03d', $seq),
        ]);

        return back()->with('success', "Pelanggan {$validated['name']} ditambahkan.");
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        $seq = Supplier::withTrashed()->count() + 1;
        Supplier::create([
            ...$validated,
            'code' => sprintf('SUP-%03d', $seq),
        ]);

        return back()->with('success', "Supplier {$validated['name']} ditambahkan.");
    }

    public function downloadTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['nama', 'kategori', 'satuan', 'varian', 'sku', 'barcode', 'harga_modal', 'harga_jual', 'stok_min']);
            fputcsv($out, ['Kopi Bubuk Robusta 200g', 'Minuman', 'pack', '200 g', 'KP-200', '899123400200', '21000', '26000', '10']);
            fputcsv($out, ['Teh Celup Melati Box 25', 'Minuman', 'kotak', 'Isi 25', 'TC-025', '899123400025', '7500', '9500', '12']);
            fclose($out);
        }, 'template-import-produk.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        if (! $handle) {
            return back()->with('error', 'File CSV tidak dapat dibaca.');
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return back()->with('error', 'File CSV kosong.');
        }

        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($handle, $branch, $request, &$imported, &$skipped) {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 8) {
                    continue;
                }
                [$name, $catName, $unitShort, $variantName, $sku, $barcode, $cost, $sell] = array_map('trim', $row);
                $minStock = isset($row[8]) ? max(0, (int) trim($row[8])) : 5;
                $sku = strtoupper($sku);

                if ($name === '' || $sku === '' || ProductVariant::withTrashed()->where('sku', $sku)->exists()) {
                    $skipped++;
                    continue;
                }

                $category = Category::query()->firstOrCreate(
                    ['name' => $catName ?: 'Umum'],
                    ['slug' => Str::slug($catName ?: 'umum').'-'.Str::lower(Str::random(3))]
                );

                $unit = Unit::query()->firstOrCreate(
                    ['short_name' => $unitShort ?: 'pcs'],
                    ['name' => ucfirst($unitShort ?: 'Pieces')]
                );

                $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name) ?: 'PR', 0, 2));
                $product = Product::create([
                    'category_id' => $category->id,
                    'unit_id' => $unit->id,
                    'name' => $name,
                    'is_bundle' => false,
                    'thumb_code' => $initials,
                    'thumb_color' => 'teal',
                ]);

                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'name' => $variantName ?: 'Standar',
                    'sku' => $sku,
                    'barcode' => $barcode ?: $sku,
                    'cost_price' => max(0, (int) $cost),
                    'sell_price' => max(0, (int) $sell),
                    'min_stock' => $minStock,
                ]);

                Stock::firstOrCreate(
                    ['branch_id' => $branch->id, 'product_variant_id' => $variant->id],
                    ['quantity' => 0]
                );

                $imported++;
            }

            if ($imported > 0) {
                ActivityLog::record(
                    action: 'product.imported',
                    description: "Import CSV produk selesai: {$imported} berhasil, {$skipped} dilewati",
                    branchId: $branch->id,
                    userId: $request->user()->id
                );
            }
        });

        fclose($handle);

        return back()->with('success', "Import selesai: {$imported} produk berhasil ditambahkan, {$skipped} dilewati (duplikat/tidak valid).");
    }

    public function printBarcodeLabels(Request $request): View
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->values();

        $qty = max(1, min(30, (int) $request->query('qty', 3)));
        $size = in_array($request->query('size'), ['33x15', '50x30'], true) ? $request->query('size') : '33x15';

        $variants = ProductVariant::query()
            ->with('product')
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereIn('id', $ids))
            ->when($ids->isEmpty(), fn ($q) => $q->limit(4))
            ->get();

        $labels = [];
        foreach ($variants as $v) {
            for ($i = 0; $i < $qty; $i++) {
                $labels[] = [
                    'name' => $v->product->name,
                    'sku' => $v->sku,
                    'barcode' => $v->barcode,
                    'price' => $v->sell_price,
                ];
            }
        }

        return view('products.barcode-labels', [
            'labels' => $labels,
            'size' => $size,
        ]);
    }
}
