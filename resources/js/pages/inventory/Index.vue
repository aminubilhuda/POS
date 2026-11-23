<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PosLayout from '../../layouts/PosLayout.vue';
import { formatRupiah } from '../../lib/format';

const props = defineProps({
    inventory: { type: Array, required: true },
    movements: { type: Object, required: true },
    movementFilters: { type: Object, default: () => ({ m_search: '', m_per_page: 10 }) },
    opnames: { type: Array, required: true },
    transfers: { type: Array, required: true },
    otherBranches: { type: Array, required: true },
});

const activeTab = ref(props.movementFilters.m_search ? 'movements' : 'stock'); // 'stock' | 'movements' | 'opname' | 'transfer'
const onlyLowStock = ref(false);

const mSearch = ref(props.movementFilters.m_search || '');
const mPerPage = ref(props.movementFilters.m_per_page || 10);

let mTimer = null;
watch(mSearch, (val) => {
    if (mTimer) clearTimeout(mTimer);
    mTimer = setTimeout(() => {
        router.get(
            '/inventory',
            { m_search: val || undefined, m_per_page: mPerPage.value },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 300);
});

function changeMPerPage() {
    router.get(
        '/inventory',
        { m_search: mSearch.value || undefined, m_per_page: mPerPage.value },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function formatPageLabel(label) {
    if (label.includes('Previous') || label.includes('&laquo;')) return 'Sebelumnya';
    if (label.includes('Next') || label.includes('&raquo;')) return 'Berikutnya';
    return label;
}

const filteredInventory = computed(() => {
    return onlyLowStock.value ? props.inventory.filter((i) => i.is_low) : props.inventory;
});

const totalStockValue = computed(() => props.inventory.reduce((sum, i) => sum + i.stock_value, 0));
const lowStockItems = computed(() => props.inventory.filter((i) => i.is_low).length);

// Penyesuaian stok cepat
const adjustForm = useForm({
    variant_id: props.inventory[0]?.variant_id || '',
    quantity_change: 0,
    notes: '',
});

function submitAdjust() {
    adjustForm.post('/inventory/adjust', {
        preserveScroll: true,
        onSuccess: () => {
            adjustForm.quantity_change = 0;
            adjustForm.notes = '';
        },
    });
}

// Stok Opname
const opnameRows = ref(
    props.inventory.slice(0, 6).map((i) => ({
        variant_id: i.variant_id,
        sku: i.sku,
        name: i.product_name,
        system_qty: i.quantity,
        physical_qty: i.quantity,
    }))
);
const opnameNotes = ref('');

function submitOpname() {
    router.post(
        '/inventory/opname',
        {
            notes: opnameNotes.value,
            items: opnameRows.value.map((r) => ({
                variant_id: r.variant_id,
                physical_qty: Number(r.physical_qty),
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                opnameNotes.value = '';
            },
        }
    );
}

// Transfer Multi-Item
const transferToBranchId = ref(props.otherBranches[0]?.id || '');
const transferNotes = ref('');
const transferItems = ref([{ variant_id: props.inventory[0]?.variant_id || '', qty: 5 }]);

function addTransferRow() {
    const next = props.inventory[transferItems.value.length % props.inventory.length] || props.inventory[0];
    transferItems.value.push({ variant_id: next?.variant_id || '', qty: 5 });
}

function removeTransferRow(idx) {
    if (transferItems.value.length <= 1) return;
    transferItems.value.splice(idx, 1);
}

function submitTransfer() {
    router.post(
        '/inventory/transfers',
        {
            to_branch_id: transferToBranchId.value,
            notes: transferNotes.value,
            items: transferItems.value.map((r) => ({
                variant_id: Number(r.variant_id),
                qty: Number(r.qty),
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                transferNotes.value = '';
                transferItems.value = [{ variant_id: props.inventory[0]?.variant_id || '', qty: 5 }];
            },
        }
    );
}

// Terima Transfer dengan Selisih
const receivingTransfer = ref(null);
const transferReceiveRows = ref([]);

function openReceiveTransferModal(transfer) {
    receivingTransfer.value = transfer;
    transferReceiveRows.value = transfer.items.map((it) => ({
        id: it.id,
        name: it.name,
        sku: it.sku,
        sent_qty: it.sent_qty,
        received_qty: it.sent_qty,
    }));
}

function submitReceiveTransfer() {
    if (!receivingTransfer.value) return;
    const received = {};
    for (const r of transferReceiveRows.value) {
        received[r.id] = Math.max(0, Number(r.received_qty) || 0);
    }
    router.post(
        `/inventory/transfers/${receivingTransfer.value.id}/receive`,
        { received },
        {
            preserveScroll: true,
            onSuccess: () => {
                receivingTransfer.value = null;
            },
        }
    );
}
</script>

<template>
    <Head title="Inventori & Stok" />

    <PosLayout active-menu="inventory" title="Inventori & Mutasi Stok" subtitle="Saldo stok cabang, kartu stok berpaginasi, opname & transfer multi-item">
        <div class="mx-auto flex max-w-7xl flex-col gap-4">
            <!-- KPI Row -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 tabular-nums">
                <div class="rounded-[12px] border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">Total Nilai Persediaan (HPP)</div>
                    <div class="mt-1 text-xl font-extrabold text-teal-700">{{ formatRupiah(totalStockValue) }}</div>
                </div>
                <div class="rounded-[12px] border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">Total SKU Aktif</div>
                    <div class="mt-1 text-xl font-extrabold text-slate-900">{{ inventory.length }} Varian</div>
                </div>
                <div class="rounded-[12px] border border-amber-200 bg-amber-50 p-4">
                    <div class="text-xs font-semibold text-amber-800">Peringatan Stok Menipis / Habis</div>
                    <div class="mt-1 text-xl font-extrabold text-amber-900">{{ lowStockItems }} Produk</div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex max-w-full gap-1.5 overflow-x-auto pb-1">
                <button
                    v-for="tab in [
                        { id: 'stock', label: 'Saldo Stok & Penyesuaian' },
                        { id: 'movements', label: `Kartu Stok (${movements.total})` },
                        { id: 'opname', label: 'Stok Opname' },
                        { id: 'transfer', label: `Transfer Cabang (${transfers.length})` },
                    ]"
                    :key="tab.id"
                    type="button"
                    @click="activeTab = tab.id"
                    :class="[
                        'cursor-pointer whitespace-nowrap rounded-full border px-3.5 py-1.5 text-xs font-bold transition-colors',
                        activeTab === tab.id ? 'border-teal-600 bg-teal-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300',
                    ]"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- TAB 1: STOCK & QUICK ADJUSTMENT -->
            <div v-if="activeTab === 'stock'" class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_320px]">
                <div class="overflow-hidden rounded-[14px] border border-slate-200 bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3">
                        <strong class="text-xs text-slate-800">Saldo Stok Cabang Aktif</strong>
                        <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-amber-800">
                            <input v-model="onlyLowStock" type="checkbox" />
                            Tampilkan stok menipis saja
                        </label>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-[480px] w-full text-left text-xs tabular-nums">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                            <tr>
                                <th class="px-4 py-2.5">SKU / Produk</th>
                                <th class="px-4 py-2.5 text-right">Saldo</th>
                                <th class="px-4 py-2.5 text-right">Min</th>
                                <th class="px-4 py-2.5 text-right">Nilai Stok</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in filteredInventory" :key="item.variant_id">
                                <td class="px-4 py-2.5">
                                    <div class="font-bold text-slate-900">{{ item.product_name }}</div>
                                    <div class="font-mono text-[11px] text-slate-400">{{ item.sku }} · {{ item.variant_name }}</div>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <span :class="['rounded-full px-2 py-0.5 text-[11px] font-bold', item.quantity <= 0 ? 'bg-red-100 text-red-700' : item.is_low ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-700']">
                                        {{ item.quantity }} {{ item.unit }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-right text-slate-500">{{ item.min_stock }}</td>
                                <td class="px-4 py-2.5 text-right font-bold text-slate-800">{{ formatRupiah(item.stock_value) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>

                <form @submit.prevent="submitAdjust" class="flex h-fit flex-col gap-3 rounded-[14px] border border-slate-200 bg-white p-4 text-xs">
                    <h3 class="text-sm font-extrabold text-slate-900">Penyesuaian Stok Manual</h3>
                    <p class="text-[11px] text-slate-500">Semua perubahan wajib lewat buku besar <code>stock_movements</code>.</p>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Produk</label>
                        <select v-model="adjustForm.variant_id" class="h-9 rounded-md border border-slate-300 px-2.5 outline-none">
                            <option v-for="i in inventory" :key="i.variant_id" :value="i.variant_id">
                                {{ i.sku }} — {{ i.product_name }} ({{ i.quantity }})
                            </option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Perubahan Qty (+ tambah / − kurang)</label>
                        <input v-model.number="adjustForm.quantity_change" type="number" required class="h-9 rounded-md border border-slate-300 px-3 tabular-nums" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Catatan Audit</label>
                        <input v-model="adjustForm.notes" required placeholder="Contoh: Barang rusak / koreksi rak" class="h-9 rounded-md border border-slate-300 px-3" />
                    </div>
                    <button type="submit" :disabled="adjustForm.processing" class="h-9 cursor-pointer rounded-md bg-teal-600 font-bold text-white hover:bg-teal-700">
                        Catat Mutasi Stok
                    </button>
                </form>
            </div>

            <!-- TAB 2: KARTU STOK (PAGINATED STOCK MOVEMENTS) -->
            <div v-else-if="activeTab === 'movements'" class="overflow-hidden rounded-[14px] border border-slate-200 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3 text-xs">
                    <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                        <input
                            v-model="mSearch"
                            type="search"
                            placeholder="Cari SKU, produk, tipe, atau catatan..."
                            class="h-8 w-full rounded-md border border-slate-300 px-3 outline-none focus:border-teal-600 sm:w-72"
                        />
                        <select v-model.number="mPerPage" @change="changeMPerPage" class="h-8 rounded-md border border-slate-300 bg-white px-2 font-semibold text-slate-700">
                            <option :value="10">10 / halaman</option>
                            <option :value="25">25 / halaman</option>
                            <option :value="50">50 / halaman</option>
                        </select>
                    </div>
                    <span class="hidden text-slate-500 sm:inline">Buku besar mutasi stok cabang</span>
                </div>
                <div class="overflow-x-auto">
                <table class="min-w-[600px] w-full text-left text-xs tabular-nums">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-2.5">Waktu</th>
                            <th class="px-4 py-2.5">Produk</th>
                            <th class="px-4 py-2.5">Tipe</th>
                            <th class="px-4 py-2.5 text-right">Mutasi</th>
                            <th class="px-4 py-2.5 text-right">Saldo Akhir</th>
                            <th class="px-4 py-2.5">Oleh &amp; Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="m in movements.data" :key="m.id">
                            <td class="px-4 py-2.5 font-mono text-[11px] text-slate-500">{{ m.date }}</td>
                            <td class="px-4 py-2.5 font-bold text-slate-900">{{ m.product_name }} <span class="font-mono text-[10.5px] font-normal text-slate-400">({{ m.sku }})</span></td>
                            <td class="px-4 py-2.5"><span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-[10.5px] font-bold text-slate-700">{{ m.type }}</span></td>
                            <td :class="['px-4 py-2.5 text-right font-extrabold', m.quantity_change > 0 ? 'text-green-700' : 'text-red-600']">
                                {{ m.quantity_change > 0 ? `+${m.quantity_change}` : m.quantity_change }}
                            </td>
                            <td class="px-4 py-2.5 text-right font-bold text-slate-900">{{ m.quantity_after }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ m.user_name }} · {{ m.notes }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3 text-xs tabular-nums">
                    <span class="text-slate-600">
                        Menampilkan <strong>{{ movements.from ?? 0 }}</strong>–<strong>{{ movements.to ?? 0 }}</strong> dari <strong>{{ movements.total }}</strong> mutasi
                    </span>
                    <nav v-if="movements.links && movements.links.length > 3" class="flex gap-1">
                        <template v-for="(link, idx) in movements.links" :key="idx">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                :class="['inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2.5 font-bold', link.active ? 'border-teal-600 bg-teal-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-teal-600']"
                            >
                                {{ formatPageLabel(link.label) }}
                            </Link>
                            <span v-else class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-slate-200 bg-slate-100 px-2.5 text-slate-400">
                                {{ formatPageLabel(link.label) }}
                            </span>
                        </template>
                    </nav>
                </div>
            </div>

            <!-- TAB 3: STOK OPNAME -->
            <div v-else-if="activeTab === 'opname'" class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_340px]">
                <form @submit.prevent="submitOpname" class="rounded-[14px] border border-slate-200 bg-white p-4 text-xs">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-extrabold text-slate-900">Hitung Fisik Stok Opname</h3>
                        <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 px-4 font-bold text-white hover:bg-teal-700">
                            Selesaikan Opname &amp; Sesuaikan
                        </button>
                    </div>
                    <input v-model="opnameNotes" placeholder="Catatan opname (opsional)" class="mb-3 h-9 w-full rounded-md border border-slate-300 px-3" />
                    <table class="w-full text-left tabular-nums">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                            <tr>
                                <th class="px-3 py-2">Produk</th>
                                <th class="px-3 py-2 text-right">Sistem</th>
                                <th class="px-3 py-2 text-right">Fisik</th>
                                <th class="px-3 py-2 text-right">Selisih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="r in opnameRows" :key="r.variant_id">
                                <td class="px-3 py-2 font-bold text-slate-900">{{ r.name }} <span class="font-mono text-[10px] text-slate-400">{{ r.sku }}</span></td>
                                <td class="px-3 py-2 text-right text-slate-600">{{ r.system_qty }}</td>
                                <td class="px-3 py-2 text-right">
                                    <input v-model.number="r.physical_qty" type="number" min="0" class="h-8 w-20 rounded border border-slate-300 px-2 text-right font-bold" />
                                </td>
                                <td :class="['px-3 py-2 text-right font-extrabold', r.physical_qty - r.system_qty === 0 ? 'text-slate-400' : r.physical_qty - r.system_qty > 0 ? 'text-green-700' : 'text-red-600']">
                                    {{ r.physical_qty - r.system_qty }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </form>

                <div class="rounded-[14px] border border-slate-200 bg-white p-4 text-xs">
                    <h3 class="mb-3 text-sm font-extrabold text-slate-900">Riwayat Opname</h3>
                    <div class="flex flex-col gap-2">
                        <p v-if="opnames.length === 0" class="text-slate-500">Belum ada riwayat opname.</p>
                        <div v-for="o in opnames" :key="o.id" class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <div class="font-mono font-bold text-slate-900">{{ o.opname_number }}</div>
                            <div class="mt-0.5 text-[11px] text-slate-500">{{ o.date }} · {{ o.user_name }} · {{ o.items_count }} item · Selisih: {{ o.total_diff }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: TRANSFER ANTAR CABANG (MULTI-ITEM) -->
            <div v-else-if="activeTab === 'transfer'" class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_380px]">
                <div class="flex flex-col gap-3">
                    <div v-for="t in transfers" :key="t.id" class="rounded-[14px] border border-slate-200 bg-white p-4 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <span class="font-mono font-extrabold text-slate-900">{{ t.transfer_number }}</span>
                                <span :class="['ml-2 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', t.status === 'received' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800']">
                                    {{ t.status }}
                                </span>
                                <div class="mt-1 text-slate-600">{{ t.from_branch }} &rarr; {{ t.to_branch }}</div>
                            </div>
                            <button
                                v-if="t.is_incoming && t.status === 'in_transit'"
                                type="button"
                                @click="openReceiveTransferModal(t)"
                                class="h-8 cursor-pointer rounded-md bg-teal-600 px-3 font-bold text-white hover:bg-teal-700"
                            >
                                Terima &amp; Cek Selisih
                            </button>
                        </div>
                        <div class="mt-2.5 flex flex-wrap gap-2 border-t border-slate-100 pt-2">
                            <span v-for="it in t.items" :key="it.id" class="rounded bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700 tabular-nums">
                                {{ it.name }}: kirim {{ it.sent_qty }} · terima {{ it.received_qty }}
                                <strong v-if="it.difference !== 0" class="text-red-600">(selisih {{ it.difference }})</strong>
                            </span>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submitTransfer" class="flex h-fit min-w-0 flex-col gap-3 rounded-[14px] border border-slate-200 bg-white p-4 text-xs">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-extrabold text-slate-900">Kirim Transfer Multi-Item</h3>
                        <button type="button" @click="addTransferRow" class="shrink-0 cursor-pointer rounded border border-teal-600 bg-teal-50 px-2 py-1 text-[11px] font-bold text-teal-700">+ Baris</button>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Cabang Tujuan</label>
                        <select v-model="transferToBranchId" required class="h-9 w-full min-w-0 rounded-md border border-slate-300 px-2.5">
                            <option v-for="b in otherBranches" :key="b.id" :value="b.id">{{ b.code }} — {{ b.name }}</option>
                        </select>
                    </div>
                    <div class="flex min-w-0 flex-col gap-2">
                        <div v-for="(row, idx) in transferItems" :key="idx" class="flex min-w-0 items-center gap-1.5">
                            <select v-model="row.variant_id" required class="h-8 w-full min-w-0 flex-1 truncate rounded border border-slate-300 px-2">
                                <option v-for="i in inventory" :key="i.variant_id" :value="i.variant_id">{{ i.sku }} — {{ i.product_name }} ({{ i.quantity }})</option>
                            </select>
                            <input v-model.number="row.qty" type="number" min="1" required class="h-8 w-16 shrink-0 rounded border border-slate-300 px-2 text-right font-bold tabular-nums" />
                            <button type="button" @click="removeTransferRow(idx)" :disabled="transferItems.length <= 1" class="grid h-8 w-6 shrink-0 cursor-pointer place-items-center rounded text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-30">&times;</button>
                        </div>
                    </div>
                    <input v-model="transferNotes" placeholder="Catatan pengiriman" class="h-9 rounded-md border border-slate-300 px-3" />
                    <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 font-bold text-white hover:bg-teal-700">Kirim Transfer ({{ transferItems.length }} item)</button>
                </form>
            </div>
        </div>

        <!-- MODAL: RECEIVE TRANSFER WITH DIFFERENCE -->
        <div v-if="receivingTransfer" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-5" @click.self="receivingTransfer = null">
            <div class="w-full max-w-[500px] overflow-hidden rounded-[14px] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                    <h2 class="text-[15px] font-extrabold text-slate-900">Terima Transfer — {{ receivingTransfer.transfer_number }}</h2>
                    <button type="button" @click="receivingTransfer = null" class="cursor-pointer text-lg font-bold text-slate-500">&times;</button>
                </div>
                <form @submit.prevent="submitReceiveTransfer" class="flex flex-col gap-3 p-5 text-xs">
                    <table class="w-full text-left tabular-nums border border-slate-200 rounded-lg overflow-hidden">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase">
                            <tr>
                                <th class="px-3 py-2">Produk</th>
                                <th class="px-3 py-2 text-right">Dikirim</th>
                                <th class="px-3 py-2 text-right">Diterima</th>
                                <th class="px-3 py-2 text-right">Selisih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="r in transferReceiveRows" :key="r.id">
                                <td class="px-3 py-2 font-bold text-slate-900">{{ r.name }}</td>
                                <td class="px-3 py-2 text-right">{{ r.sent_qty }}</td>
                                <td class="px-3 py-2 text-right">
                                    <input v-model.number="r.received_qty" type="number" min="0" class="h-8 w-20 rounded border border-slate-300 px-2 text-right font-bold" />
                                </td>
                                <td :class="['px-3 py-2 text-right font-extrabold', r.received_qty - r.sent_qty === 0 ? 'text-green-700' : 'text-red-600']">
                                    {{ r.received_qty - r.sent_qty }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" @click="receivingTransfer = null" class="h-9 cursor-pointer rounded-md border border-slate-300 bg-white px-4 font-bold">Batal</button>
                        <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 px-4 font-bold text-white hover:bg-teal-700">Konfirmasi Penerimaan</button>
                    </div>
                </form>
            </div>
        </div>
    </PosLayout>
</template>
