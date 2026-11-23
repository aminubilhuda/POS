<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PosLayout from '../../layouts/PosLayout.vue';
import RupiahInput from '../../components/RupiahInput.vue';
import { formatRupiah } from '../../lib/format';

const props = defineProps({
    variants: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '', per_page: 10 }) },
    categories: { type: Array, required: true },
    units: { type: Array, required: true },
    suppliers: { type: Array, required: true },
    customers: { type: Array, required: true },
});

const activeTab = ref('products'); // 'products' | 'categories' | 'suppliers' | 'customers'
const search = ref(props.filters.search || '');
const perPage = ref(props.filters.per_page || 10);
const showCreateModal = ref(false);
const editingVariant = ref(null);

let searchTimer = null;
watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            '/products',
            { search: value || undefined, per_page: perPage.value },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 300);
});

function changePerPage() {
    router.get(
        '/products',
        { search: search.value || undefined, per_page: perPage.value },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function formatPageLabel(label) {
    if (label.includes('Previous') || label.includes('&laquo;')) return 'Sebelumnya';
    if (label.includes('Next') || label.includes('&raquo;')) return 'Berikutnya';
    return label;
}

const createForm = useForm({
    name: '',
    category_id: props.categories[0]?.id || '',
    unit_id: props.units[0]?.id || '',
    variant_name: 'Standar',
    sku: '',
    barcode: '',
    cost_price: 0,
    sell_price: 0,
    promo_discount: 0,
    promo_label: '',
    min_stock: 5,
    is_bundle: false,
    extra_unit_id: '',
    extra_conversion_qty: '',
    extra_sell_price: '',
});

const editForm = useForm({
    cost_price: 0,
    sell_price: 0,
    promo_discount: 0,
    promo_label: '',
    min_stock: 5,
});

const categoryForm = useForm({ name: '' });
const unitForm = useForm({ name: '', short_name: '' });
const supplierForm = useForm({ name: '', phone: '', email: '', address: '' });
const customerForm = useForm({ name: '', phone: '', type: 'Member' });

// Import CSV & Barcode Label
const importForm = useForm({ file: null });
const selectedBarcodeIds = ref([]);
const barcodeQty = ref(3);
const barcodeSize = ref('33x15');

function onCsvSelected(event) {
    importForm.file = event.target.files?.[0] || null;
}

function submitImportCsv() {
    if (!importForm.file) return;
    importForm.post('/products/import', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => importForm.reset(),
    });
}

function openBarcodePrint() {
    const ids = selectedBarcodeIds.value.join(',');
    const url = `/products/barcode-labels?ids=${encodeURIComponent(ids)}&qty=${barcodeQty.value}&size=${barcodeSize.value}`;
    window.open(url, '_blank');
}

function submitCreateProduct() {
    createForm.post('/products', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
}

function openEdit(v) {
    editingVariant.value = v;
    editForm.cost_price = v.cost_price;
    editForm.sell_price = v.sell_price;
    editForm.promo_discount = v.promo_discount;
    editForm.promo_label = v.promo_label || '';
    editForm.min_stock = v.min_stock;
}

function submitEditVariant() {
    editForm.put(`/products/variants/${editingVariant.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingVariant.value = null;
        },
    });
}

function deleteProduct(productId) {
    if (!confirm('Hapus produk ini (soft delete)?')) return;
    router.delete(`/products/${productId}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Master Produk & Data" />

    <PosLayout active-menu="products" title="Master Data & Produk" subtitle="Produk, varian, satuan bertingkat, bundling, supplier & pelanggan">
        <div class="mx-auto flex max-w-7xl flex-col gap-4">
            <!-- Tabs & Actions -->
            <div class="flex flex-wrap items-center justify-between gap-2.5">
                <div class="flex max-w-full gap-1.5 overflow-x-auto pb-1">
                    <button
                        v-for="tab in [
                            { id: 'products', label: `Produk & Varian (${variants.total})` },
                            { id: 'import', label: 'Import & Label Barcode' },
                            { id: 'categories', label: `Kategori & Satuan` },
                            { id: 'suppliers', label: `Supplier (${suppliers.length})` },
                            { id: 'customers', label: `Pelanggan (${customers.length})` },
                        ]"
                        :key="tab.id"
                        type="button"
                        @click="activeTab = tab.id"
                        :class="[
                            'cursor-pointer whitespace-nowrap rounded-full border px-3.5 py-1.5 text-xs font-bold transition-colors',
                            activeTab === tab.id
                                ? 'border-teal-600 bg-teal-600 text-white'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300',
                        ]"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <button
                    v-if="activeTab === 'products'"
                    type="button"
                    @click="showCreateModal = true"
                    class="h-9 w-full cursor-pointer rounded-lg bg-teal-600 px-4 text-xs font-bold text-white hover:bg-teal-700 sm:h-10 sm:w-auto"
                >
                    + Tambah Produk / Bundling
                </button>
            </div>

            <!-- TAB 1: PRODUCTS -->
            <div v-if="activeTab === 'products'" class="rounded-[14px] border border-slate-200 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-2.5 border-b border-slate-200 px-3.5 py-3 sm:px-4">
                    <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Cari nama produk, SKU, atau barcode..."
                            class="h-9 w-full rounded-lg border border-slate-300 px-3 text-xs outline-none focus:border-teal-600 sm:w-72 md:w-80"
                        />
                        <select
                            v-model.number="perPage"
                            @change="changePerPage"
                            aria-label="Jumlah data per halaman"
                            class="h-9 cursor-pointer rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 outline-none focus:border-teal-600"
                        >
                            <option :value="5">5 / halaman</option>
                            <option :value="10">10 / halaman</option>
                            <option :value="25">25 / halaman</option>
                            <option :value="50">50 / halaman</option>
                        </select>
                    </div>
                    <span class="hidden text-xs text-slate-500 md:inline">Perubahan harga jual otomatis dicatat di Activity Log</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold tracking-wider text-slate-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">Produk &amp; Varian</th>
                                <th class="px-4 py-3">SKU / Barcode</th>
                                <th class="px-4 py-3">Kategori</th>
                                <th class="px-4 py-3 text-right">Harga Modal</th>
                                <th class="px-4 py-3 text-right">Harga Jual</th>
                                <th class="px-4 py-3 text-right">Stok Cabang</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 tabular-nums">
                            <tr v-if="variants.data.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                    Tidak ada produk yang cocok dengan pencarian.
                                </td>
                            </tr>
                            <tr v-for="v in variants.data" :key="v.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900">
                                        {{ v.name }}
                                        <span v-if="v.is_bundle" class="ml-1 rounded bg-violet-100 px-1.5 py-0.5 text-[10px] font-bold text-violet-700">Bundling</span>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        {{ v.variant_name }} · /{{ v.unit_name }}
                                        <span v-if="v.product_units.length" class="ml-1 text-teal-700">
                                            ({{ v.product_units.map((u) => `1 ${u.unit} = ${u.conversion_qty}`).join(', ') }})
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px] text-slate-600">
                                    <div>{{ v.sku }}</div>
                                    <div class="text-slate-400">{{ v.barcode }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ v.category_name }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ formatRupiah(v.cost_price) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="font-bold text-teal-700">{{ formatRupiah(v.sell_price) }}</div>
                                    <div v-if="v.promo_discount > 0" class="text-[10.5px] font-semibold text-red-600">
                                        Diskon −{{ formatRupiah(v.promo_discount) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span :class="['rounded-full px-2 py-0.5 text-[11px] font-bold', v.stock <= v.min_stock ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700']">
                                        {{ v.stock }} {{ v.unit_name }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-1.5">
                                        <button type="button" @click="openEdit(v)" class="cursor-pointer rounded border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:border-teal-600 hover:text-teal-700">
                                            Ubah Harga
                                        </button>
                                        <button type="button" @click="deleteProduct(v.product_id)" class="cursor-pointer rounded border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-bold text-red-700 hover:bg-red-100">
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Bar -->
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3 text-xs">
                    <div class="text-slate-600 tabular-nums">
                        Menampilkan
                        <strong class="text-slate-900">{{ variants.from ?? 0 }}</strong>–<strong class="text-slate-900">{{ variants.to ?? 0 }}</strong>
                        dari <strong class="text-slate-900">{{ variants.total }}</strong> produk
                    </div>
                    <nav v-if="variants.links && variants.links.length > 3" class="flex flex-wrap items-center gap-1" aria-label="Paginasi Produk">
                        <template v-for="(link, idx) in variants.links" :key="idx">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                :class="[
                                    'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2.5 text-xs font-bold transition-colors tabular-nums',
                                    link.active
                                        ? 'border-teal-600 bg-teal-600 text-white'
                                        : 'border-slate-300 bg-white text-slate-700 hover:border-teal-600 hover:text-teal-700',
                                ]"
                            >
                                {{ formatPageLabel(link.label) }}
                            </Link>
                            <span
                                v-else
                                class="inline-flex h-8 min-w-8 cursor-not-allowed items-center justify-center rounded-md border border-slate-200 bg-slate-100 px-2.5 text-xs font-semibold text-slate-400"
                            >
                                {{ formatPageLabel(link.label) }}
                            </span>
                        </template>
                    </nav>
                </div>
            </div>

            <!-- TAB 1.5: IMPORT CSV & CETAK LABEL BARCODE -->
            <div v-else-if="activeTab === 'import'" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <!-- Import CSV -->
                <form @submit.prevent="submitImportCsv" class="flex flex-col gap-3.5 rounded-[14px] border border-slate-200 bg-white p-5 text-xs">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-extrabold text-slate-900">Import Produk Massal (CSV / Excel)</h3>
                        <a href="/products/import/template" class="rounded-md border border-slate-300 bg-slate-50 px-3 py-1.5 font-bold text-slate-700 hover:border-teal-600 hover:text-teal-700">
                            Unduh Template .csv
                        </a>
                    </div>
                    <p class="text-slate-600">
                        Format kolom: <code class="font-mono">nama, kategori, satuan, varian, sku, barcode, harga_modal, harga_jual, stok_min</code>. SKU yang sudah ada akan otomatis dilewati.
                    </p>
                    <input
                        type="file"
                        accept=".csv,.txt"
                        @change="onCsvSelected"
                        required
                        class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-xs"
                    />
                    <div class="flex justify-end">
                        <button type="submit" :disabled="importForm.processing || !importForm.file" class="h-9 cursor-pointer rounded-md bg-teal-600 px-4 font-bold text-white hover:bg-teal-700 disabled:opacity-50">
                            {{ importForm.processing ? 'Mengimpor...' : 'Upload & Import Produk' }}
                        </button>
                    </div>
                </form>

                <!-- Cetak Label Barcode -->
                <div class="flex flex-col gap-3.5 rounded-[14px] border border-slate-200 bg-white p-5 text-xs">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-extrabold text-slate-900">Cetak Label Barcode</h3>
                        <div class="flex items-center gap-2">
                            <select v-model="barcodeSize" class="h-8 rounded border border-slate-300 px-2 font-semibold">
                                <option value="33x15">33x15mm (3 kol)</option>
                                <option value="50x30">50x30mm (2 kol)</option>
                            </select>
                            <input v-model.number="barcodeQty" type="number" min="1" max="30" class="h-8 w-16 rounded border border-slate-300 px-2 text-right font-bold tabular-nums" title="Jumlah stiker per produk" />
                            <button type="button" @click="openBarcodePrint" class="h-8 cursor-pointer rounded-md bg-teal-600 px-3 font-bold text-white hover:bg-teal-700">
                                Buka Lembar Cetak
                            </button>
                        </div>
                    </div>
                    <p class="text-slate-500">Centang produk yang ingin dicetak label barcodenya (kosongkan untuk cetak 4 produk pertama):</p>
                    <div class="flex max-h-60 flex-col gap-1.5 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50 p-2.5">
                        <label v-for="v in variants.data" :key="v.id" class="flex cursor-pointer items-center justify-between rounded bg-white px-2.5 py-1.5 border border-slate-200">
                            <span class="flex items-center gap-2">
                                <input type="checkbox" :value="v.id" v-model="selectedBarcodeIds" />
                                <strong class="text-slate-900">{{ v.name }}</strong>
                                <span class="font-mono text-[10.5px] text-slate-400">{{ v.barcode }}</span>
                            </span>
                            <span class="font-bold text-teal-700 tabular-nums">{{ formatRupiah(v.sell_price) }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- TAB 2: CATEGORIES & UNITS -->
            <div v-else-if="activeTab === 'categories'" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="rounded-[14px] border border-slate-200 bg-white p-4">
                    <h3 class="mb-3 text-sm font-extrabold text-slate-900">Kategori Produk</h3>
                    <form @submit.prevent="categoryForm.post('/products/categories', { onSuccess: () => categoryForm.reset() })" class="mb-3 flex gap-2">
                        <input v-model="categoryForm.name" required placeholder="Nama kategori baru..." class="h-9 flex-1 rounded-md border border-slate-300 px-3 text-xs outline-none focus:border-teal-600" />
                        <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 px-3.5 text-xs font-bold text-white hover:bg-teal-700">Tambah</button>
                    </form>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="c in categories" :key="c.id" class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                            {{ c.name }}
                        </span>
                    </div>
                </div>

                <div class="rounded-[14px] border border-slate-200 bg-white p-4">
                    <h3 class="mb-3 text-sm font-extrabold text-slate-900">Satuan Produk</h3>
                    <form @submit.prevent="unitForm.post('/products/units', { onSuccess: () => unitForm.reset() })" class="mb-3 flex gap-2">
                        <input v-model="unitForm.name" required placeholder="Nama (mis. Karton)" class="h-9 flex-1 rounded-md border border-slate-300 px-3 text-xs outline-none focus:border-teal-600" />
                        <input v-model="unitForm.short_name" required placeholder="Singkatan (krt)" class="h-9 w-28 rounded-md border border-slate-300 px-3 text-xs outline-none focus:border-teal-600" />
                        <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 px-3.5 text-xs font-bold text-white hover:bg-teal-700">Tambah</button>
                    </form>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="u in units" :key="u.id" class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                            {{ u.name }} ({{ u.short_name }})
                        </span>
                    </div>
                </div>
            </div>

            <!-- TAB 3: SUPPLIERS -->
            <div v-else-if="activeTab === 'suppliers'" class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_340px]">
                <div class="overflow-x-auto rounded-[14px] border border-slate-200 bg-white">
                    <table class="min-w-[540px] w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">Kode</th>
                                <th class="px-4 py-3">Nama Supplier</th>
                                <th class="px-4 py-3">Kontak</th>
                                <th class="px-4 py-3">Alamat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="s in suppliers" :key="s.id">
                                <td class="px-4 py-3 font-mono font-bold text-slate-700">{{ s.code }}</td>
                                <td class="px-4 py-3 font-bold text-slate-900">{{ s.name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ s.phone }} · {{ s.email }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ s.address }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form @submit.prevent="supplierForm.post('/products/suppliers', { onSuccess: () => supplierForm.reset() })" class="flex flex-col gap-3 rounded-[14px] border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-extrabold text-slate-900">Tambah Supplier</h3>
                    <input v-model="supplierForm.name" required placeholder="Nama perusahaan / supplier" class="h-9 rounded-md border border-slate-300 px-3 text-xs outline-none focus:border-teal-600" />
                    <input v-model="supplierForm.phone" placeholder="Nomor telepon" class="h-9 rounded-md border border-slate-300 px-3 text-xs outline-none focus:border-teal-600" />
                    <input v-model="supplierForm.email" type="email" placeholder="Email" class="h-9 rounded-md border border-slate-300 px-3 text-xs outline-none focus:border-teal-600" />
                    <input v-model="supplierForm.address" placeholder="Alamat" class="h-9 rounded-md border border-slate-300 px-3 text-xs outline-none focus:border-teal-600" />
                    <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 text-xs font-bold text-white hover:bg-teal-700">Simpan Supplier</button>
                </form>
            </div>

            <!-- TAB 4: CUSTOMERS -->
            <div v-else-if="activeTab === 'customers'" class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_340px]">
                <div class="overflow-x-auto rounded-[14px] border border-slate-200 bg-white">
                    <table class="min-w-[480px] w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">Kode</th>
                                <th class="px-4 py-3">Nama Pelanggan</th>
                                <th class="px-4 py-3">Telepon</th>
                                <th class="px-4 py-3">Tipe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="c in customers" :key="c.id">
                                <td class="px-4 py-3 font-mono font-bold text-slate-700">{{ c.code }}</td>
                                <td class="px-4 py-3 font-bold text-slate-900">{{ c.name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ c.phone || '-' }}</td>
                                <td class="px-4 py-3"><span class="rounded-full bg-teal-50 px-2 py-0.5 text-[11px] font-bold text-teal-700">{{ c.type }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form @submit.prevent="customerForm.post('/products/customers', { onSuccess: () => customerForm.reset() })" class="flex flex-col gap-3 rounded-[14px] border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-extrabold text-slate-900">Tambah Pelanggan</h3>
                    <input v-model="customerForm.name" required placeholder="Nama pelanggan" class="h-9 rounded-md border border-slate-300 px-3 text-xs outline-none focus:border-teal-600" />
                    <input v-model="customerForm.phone" placeholder="Nomor HP" class="h-9 rounded-md border border-slate-300 px-3 text-xs outline-none focus:border-teal-600" />
                    <select v-model="customerForm.type" class="h-9 rounded-md border border-slate-300 px-3 text-xs outline-none focus:border-teal-600">
                        <option value="Member">Member</option>
                        <option value="Grosir">Grosir</option>
                        <option value="VIP">VIP</option>
                    </select>
                    <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 text-xs font-bold text-white hover:bg-teal-700">Simpan Pelanggan</button>
                </form>
            </div>
        </div>

        <!-- MODAL: CREATE PRODUCT -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-3 sm:p-5" @click.self="showCreateModal = false">
            <div class="max-h-[90dvh] w-full max-w-[540px] overflow-y-auto rounded-[14px] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 sm:px-5 sm:py-3.5">
                    <h2 class="text-[15px] font-extrabold text-slate-900">Tambah Produk Baru</h2>
                    <button type="button" @click="showCreateModal = false" class="cursor-pointer text-lg font-bold text-slate-500">&times;</button>
                </div>
                <form @submit.prevent="submitCreateProduct" class="grid grid-cols-1 gap-3 p-4 text-xs sm:grid-cols-2 sm:p-5">
                    <div class="flex flex-col gap-1 sm:col-span-2">
                        <label class="font-bold">Nama Produk</label>
                        <input v-model="createForm.name" required class="h-9 rounded-md border border-slate-300 px-3 outline-none focus:border-teal-600" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Kategori</label>
                        <select v-model="createForm.category_id" required class="h-9 rounded-md border border-slate-300 px-2.5 outline-none">
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Satuan Dasar</label>
                        <select v-model="createForm.unit_id" required class="h-9 rounded-md border border-slate-300 px-2.5 outline-none">
                            <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }} ({{ u.short_name }})</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Varian (Ukuran/Warna)</label>
                        <input v-model="createForm.variant_name" required class="h-9 rounded-md border border-slate-300 px-3 outline-none" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">SKU (Unik)</label>
                        <input v-model="createForm.sku" required placeholder="CTH-001" class="h-9 rounded-md border border-slate-300 px-3 font-mono outline-none" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Barcode</label>
                        <input v-model="createForm.barcode" required placeholder="899..." class="h-9 rounded-md border border-slate-300 px-3 font-mono outline-none" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Stok Minimum</label>
                        <input v-model.number="createForm.min_stock" type="number" min="0" required class="h-9 rounded-md border border-slate-300 px-3 outline-none tabular-nums" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Harga Modal</label>
                        <RupiahInput v-model="createForm.cost_price" required />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Harga Jual</label>
                        <RupiahInput v-model="createForm.sell_price" required />
                    </div>
                    <label class="flex items-center gap-2 pt-1 font-semibold text-slate-700 sm:col-span-2">
                        <input v-model="createForm.is_bundle" type="checkbox" />
                        Tandai sebagai Produk Bundling / Paket
                    </label>
                    <div class="mt-2 flex justify-end gap-2 border-t border-slate-200 pt-3 sm:col-span-2">
                        <button type="button" @click="showCreateModal = false" class="h-9 cursor-pointer rounded-md border border-slate-300 bg-white px-4 font-bold">Batal</button>
                        <button type="submit" :disabled="createForm.processing" class="h-9 cursor-pointer rounded-md bg-teal-600 px-4 font-bold text-white hover:bg-teal-700">Simpan Produk</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: EDIT VARIANT PRICE -->
        <div v-if="editingVariant" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-5" @click.self="editingVariant = null">
            <div class="w-full max-w-[420px] overflow-hidden rounded-[14px] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                    <h2 class="text-[15px] font-extrabold text-slate-900">Ubah Harga — {{ editingVariant.sku }}</h2>
                    <button type="button" @click="editingVariant = null" class="cursor-pointer text-lg font-bold text-slate-500">&times;</button>
                </div>
                <form @submit.prevent="submitEditVariant" class="flex flex-col gap-3 p-5 text-xs">
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Harga Modal</label>
                        <RupiahInput v-model="editForm.cost_price" required />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Harga Jual</label>
                        <RupiahInput v-model="editForm.sell_price" required />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Diskon Promo per Item</label>
                        <RupiahInput v-model="editForm.promo_discount" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Stok Minimum</label>
                        <input v-model.number="editForm.min_stock" type="number" min="0" required class="h-9 rounded-md border border-slate-300 px-3 tabular-nums" />
                    </div>
                    <div class="mt-1 flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" @click="editingVariant = null" class="h-9 cursor-pointer rounded-md border border-slate-300 bg-white px-4 font-bold">Batal</button>
                        <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 px-4 font-bold text-white hover:bg-teal-700">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </PosLayout>
</template>
