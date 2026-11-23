<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PosLayout from '../../layouts/PosLayout.vue';
import RupiahInput from '../../components/RupiahInput.vue';
import { formatRupiah } from '../../lib/format';

const props = defineProps({
    purchaseOrders: { type: Array, required: true },
    receipts: { type: Array, required: true },
    returns: { type: Array, required: true },
    suppliers: { type: Array, required: true },
    variants: { type: Array, required: true },
});

const activeTab = ref('po'); // 'po' | 'receipts' | 'returns'
const payingPo = ref(null);
const receivingPo = ref(null);
const receiveRows = ref([]);
const receiveNotes = ref('');

// Multi-item PO builder
const poSupplierId = ref(props.suppliers[0]?.id || '');
const poNotes = ref('');
const poItems = ref([
    {
        variant_id: props.variants[0]?.id || '',
        qty: 10,
        unit_cost: props.variants[0]?.cost_price || 0,
    },
]);

const poGrandTotal = computed(() =>
    poItems.value.reduce((sum, row) => sum + (Number(row.qty) || 0) * (Number(row.unit_cost) || 0), 0)
);

function addPoRow() {
    const fallback = props.variants[poItems.value.length % props.variants.length] || props.variants[0];
    poItems.value.push({
        variant_id: fallback?.id || '',
        qty: 10,
        unit_cost: fallback?.cost_price || 0,
    });
}

function removePoRow(idx) {
    if (poItems.value.length <= 1) return;
    poItems.value.splice(idx, 1);
}

function syncRowCost(row) {
    const v = props.variants.find((x) => x.id === Number(row.variant_id));
    if (v) row.unit_cost = v.cost_price;
}

function submitPo() {
    router.post(
        '/purchasing/orders',
        {
            supplier_id: poSupplierId.value,
            notes: poNotes.value,
            items: poItems.value.map((r) => ({
                variant_id: Number(r.variant_id),
                qty: Number(r.qty),
                unit_cost: Number(r.unit_cost),
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                poNotes.value = '';
                poItems.value = [
                    {
                        variant_id: props.variants[0]?.id || '',
                        qty: 10,
                        unit_cost: props.variants[0]?.cost_price || 0,
                    },
                ];
            },
        }
    );
}

function openReceiveModal(po) {
    receivingPo.value = po;
    receiveNotes.value = '';
    receiveRows.value = po.items
        .filter((i) => i.remaining_qty > 0)
        .map((i) => ({
            po_item_id: i.id,
            name: i.name,
            sku: i.sku,
            ordered_qty: i.ordered_qty,
            already_received: i.received_qty,
            remaining_qty: i.remaining_qty,
            received_qty: i.remaining_qty,
        }));
}

function submitPartialReceive() {
    if (!receivingPo.value) return;
    router.post(
        `/purchasing/orders/${receivingPo.value.id}/receive`,
        {
            notes: receiveNotes.value || 'Penerimaan barang',
            items: receiveRows.value.map((r) => ({
                po_item_id: r.po_item_id,
                received_qty: Math.max(0, Math.min(r.remaining_qty, Number(r.received_qty) || 0)),
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                receivingPo.value = null;
            },
        }
    );
}

const payForm = useForm({
    amount: 0,
    payment_method: 'transfer',
    reference_no: '',
    notes: '',
});

const returnForm = useForm({
    supplier_id: props.suppliers[0]?.id || '',
    variant_id: props.variants[0]?.id || '',
    qty: 1,
    reason: '',
});

function openPayModal(po) {
    payingPo.value = po;
    payForm.amount = po.remaining_debt;
    payForm.reference_no = '';
    payForm.notes = '';
}

function submitPayment() {
    payForm.post(`/purchasing/orders/${payingPo.value.id}/pay`, {
        preserveScroll: true,
        onSuccess: () => {
            payingPo.value = null;
        },
    });
}

function submitReturn() {
    returnForm.post('/purchasing/returns', {
        preserveScroll: true,
        onSuccess: () => {
            returnForm.reason = '';
            returnForm.qty = 1;
        },
    });
}
</script>

<template>
    <Head title="Pembelian & Supplier" />

    <PosLayout active-menu="purchasing" title="Pembelian (Purchasing)" subtitle="PO multi-item, penerimaan barang parsial/penuh, hutang supplier & retur pembelian">
        <div class="mx-auto flex max-w-7xl flex-col gap-4">
            <div class="flex max-w-full gap-1.5 overflow-x-auto pb-1">
                <button
                    v-for="tab in [
                        { id: 'po', label: `Purchase Order (${purchaseOrders.length})` },
                        { id: 'receipts', label: `Penerimaan Barang (${receipts.length})` },
                        { id: 'returns', label: `Retur ke Supplier (${returns.length})` },
                    ]"
                    :key="tab.id"
                    type="button"
                    @click="activeTab = tab.id"
                    :class="[
                        'cursor-pointer whitespace-nowrap rounded-full border px-3.5 py-1.5 text-xs font-bold',
                        activeTab === tab.id ? 'border-teal-600 bg-teal-600 text-white' : 'border-slate-200 bg-white text-slate-600',
                    ]"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- TAB 1: PURCHASE ORDERS -->
            <div v-if="activeTab === 'po'" class="flex flex-col gap-4">
                <!-- Multi-Item Create PO Card -->
                <form @submit.prevent="submitPo" class="rounded-[14px] border border-slate-200 bg-white p-4 text-xs">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-extrabold text-slate-900">Buat Purchase Order (Multi-Item)</h3>
                        <button type="button" @click="addPoRow" class="h-8 cursor-pointer rounded-md border border-teal-600 bg-teal-50 px-3 font-bold text-teal-700 hover:bg-teal-100">
                            + Tambah Baris Produk
                        </button>
                    </div>

                    <div class="mb-3 grid grid-cols-1 gap-2.5 md:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <label class="font-bold text-slate-600">Supplier</label>
                            <select v-model="poSupplierId" required class="h-9 rounded-md border border-slate-300 px-2.5">
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.code }} — {{ s.name }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="font-bold text-slate-600">Catatan PO</label>
                            <input v-model="poNotes" placeholder="Catatan PO (opsional)" class="h-9 rounded-md border border-slate-300 px-3" />
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-slate-200">
                        <table class="w-full text-left tabular-nums">
                            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                                <tr>
                                    <th class="px-3 py-2">Produk / Varian</th>
                                    <th class="px-3 py-2 text-right w-28">Qty</th>
                                    <th class="px-3 py-2 text-right w-36">Harga Beli (Rp)</th>
                                    <th class="px-3 py-2 text-right w-36">Subtotal</th>
                                    <th class="px-3 py-2 w-16"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(row, idx) in poItems" :key="idx">
                                    <td class="px-3 py-2">
                                        <select v-model="row.variant_id" @change="syncRowCost(row)" required class="h-8 w-full rounded border border-slate-300 px-2">
                                            <option v-for="v in variants" :key="v.id" :value="v.id">{{ v.sku }} — {{ v.name }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <input v-model.number="row.qty" type="number" min="1" required class="h-8 w-20 rounded border border-slate-300 px-2 text-right font-bold" />
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <RupiahInput
                                            v-model="row.unit_cost"
                                            required
                                            input-class="h-8 w-32 rounded border border-slate-300 bg-white px-2 text-right text-xs font-bold outline-none focus:border-teal-600 tabular-nums"
                                        />
                                    </td>
                                    <td class="px-3 py-2 text-right font-extrabold text-slate-900">
                                        {{ formatRupiah((Number(row.qty) || 0) * (Number(row.unit_cost) || 0)) }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button type="button" @click="removePoRow(idx)" :disabled="poItems.length <= 1" class="cursor-pointer rounded px-2 py-1 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-30">
                                            &times;
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 flex items-center justify-between">
                        <div class="text-sm tabular-nums">
                            Total PO ({{ poItems.length }} baris): <strong class="text-teal-700">{{ formatRupiah(poGrandTotal) }}</strong>
                        </div>
                        <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 px-5 font-bold text-white hover:bg-teal-700">
                            Simpan Purchase Order
                        </button>
                    </div>
                </form>

                <!-- PO List -->
                <div class="flex flex-col gap-3">
                    <div v-for="po in purchaseOrders" :key="po.id" class="rounded-[14px] border border-slate-200 bg-white p-4 text-xs tabular-nums">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-sm font-extrabold text-slate-900">{{ po.po_number }}</span>
                                    <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', po.status === 'received' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800']">
                                        {{ po.status }}
                                    </span>
                                    <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', po.payment_status === 'paid' ? 'bg-teal-100 text-teal-800' : 'bg-red-100 text-red-700']">
                                        {{ po.payment_status }}
                                    </span>
                                </div>
                                <div class="mt-1 text-slate-600">{{ po.supplier_name }} · {{ po.ordered_at }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-extrabold text-slate-900">{{ formatRupiah(po.total_amount) }}</div>
                                <div v-if="po.remaining_debt > 0" class="text-[11px] font-bold text-red-600">
                                    Sisa Hutang: {{ formatRupiah(po.remaining_debt) }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-1.5 border-t border-slate-100 pt-2.5">
                            <span v-for="it in po.items" :key="it.id" class="rounded bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                {{ it.name }}: {{ it.received_qty }}/{{ it.ordered_qty }} @ {{ formatRupiah(it.unit_cost) }}
                            </span>
                        </div>

                        <div class="mt-3 flex justify-end gap-2">
                            <button
                                v-if="po.status !== 'received'"
                                type="button"
                                @click="openReceiveModal(po)"
                                class="h-8 cursor-pointer rounded-md bg-teal-600 px-3 font-bold text-white hover:bg-teal-700"
                            >
                                Terima Barang (Parsial / Penuh)
                            </button>
                            <button
                                v-if="po.remaining_debt > 0"
                                type="button"
                                @click="openPayModal(po)"
                                class="h-8 cursor-pointer rounded-md border border-slate-300 bg-white px-3 font-bold text-slate-800 hover:border-teal-600 hover:text-teal-700"
                            >
                                Bayar Hutang
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: GOODS RECEIPTS -->
            <div v-else-if="activeTab === 'receipts'" class="overflow-x-auto rounded-[14px] border border-slate-200 bg-white">
                <table class="min-w-[540px] w-full text-left text-xs tabular-nums">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-3">No. Penerimaan</th>
                            <th class="px-4 py-3">No. PO</th>
                            <th class="px-4 py-3">Supplier</th>
                            <th class="px-4 py-3 text-right">Total Qty</th>
                            <th class="px-4 py-3">Diterima Oleh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="r in receipts" :key="r.id">
                            <td class="px-4 py-3 font-mono font-bold text-slate-900">{{ r.receipt_number }}</td>
                            <td class="px-4 py-3 font-mono text-slate-600">{{ r.po_number }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ r.supplier_name }}</td>
                            <td class="px-4 py-3 text-right font-bold text-green-700">+{{ r.total_qty }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ r.user_name }} · {{ r.received_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- TAB 3: PURCHASE RETURNS -->
            <div v-else-if="activeTab === 'returns'" class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_340px]">
                <div class="overflow-x-auto rounded-[14px] border border-slate-200 bg-white">
                    <table class="min-w-[520px] w-full text-left text-xs tabular-nums">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">No. Retur</th>
                                <th class="px-4 py-3">Supplier</th>
                                <th class="px-4 py-3 text-right">Qty</th>
                                <th class="px-4 py-3 text-right">Nilai</th>
                                <th class="px-4 py-3">Alasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="ret in returns" :key="ret.id">
                                <td class="px-4 py-3 font-mono font-bold text-slate-900">{{ ret.return_number }}</td>
                                <td class="px-4 py-3 font-semibold">{{ ret.supplier_name }}</td>
                                <td class="px-4 py-3 text-right font-bold text-red-600">−{{ ret.items_count }}</td>
                                <td class="px-4 py-3 text-right font-bold">{{ formatRupiah(ret.total_amount) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ ret.reason }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form @submit.prevent="submitReturn" class="flex h-fit flex-col gap-3 rounded-[14px] border border-slate-200 bg-white p-4 text-xs">
                    <h3 class="text-sm font-extrabold text-slate-900">Catat Retur ke Supplier</h3>
                    <select v-model="returnForm.supplier_id" required class="h-9 rounded-md border border-slate-300 px-2.5">
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <select v-model="returnForm.variant_id" required class="h-9 rounded-md border border-slate-300 px-2.5">
                        <option v-for="v in variants" :key="v.id" :value="v.id">{{ v.sku }} — {{ v.name }}</option>
                    </select>
                    <input v-model.number="returnForm.qty" type="number" min="1" required placeholder="Jumlah retur" class="h-9 rounded-md border border-slate-300 px-3 tabular-nums" />
                    <input v-model="returnForm.reason" required placeholder="Alasan retur (mis. kemasan rusak)" class="h-9 rounded-md border border-slate-300 px-3" />
                    <button type="submit" class="h-9 cursor-pointer rounded-md bg-red-600 font-bold text-white hover:bg-red-700">Proses Retur &amp; Kurangi Stok</button>
                </form>
            </div>
        </div>

        <!-- MODAL: PARTIAL GOODS RECEIPT -->
        <div v-if="receivingPo" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-5" @click.self="receivingPo = null">
            <div class="w-full max-w-[540px] overflow-hidden rounded-[14px] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                    <h2 class="text-[15px] font-extrabold text-slate-900">Penerimaan Barang — {{ receivingPo.po_number }}</h2>
                    <button type="button" @click="receivingPo = null" class="cursor-pointer text-lg font-bold text-slate-500">&times;</button>
                </div>
                <form @submit.prevent="submitPartialReceive" class="flex flex-col gap-3 p-5 text-xs">
                    <p class="text-slate-600">Masukkan jumlah barang yang diterima saat ini (boleh sebagian/parsial).</p>
                    <table class="w-full text-left tabular-nums border border-slate-200 rounded-lg overflow-hidden">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase">
                            <tr>
                                <th class="px-3 py-2">Produk</th>
                                <th class="px-3 py-2 text-right">Order</th>
                                <th class="px-3 py-2 text-right">Sdh Terima</th>
                                <th class="px-3 py-2 text-right">Terima Skrg</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="r in receiveRows" :key="r.po_item_id">
                                <td class="px-3 py-2 font-bold text-slate-900">{{ r.name }} <span class="font-mono text-[10px] text-slate-400">{{ r.sku }}</span></td>
                                <td class="px-3 py-2 text-right">{{ r.ordered_qty }}</td>
                                <td class="px-3 py-2 text-right text-slate-500">{{ r.already_received }}</td>
                                <td class="px-3 py-2 text-right">
                                    <input v-model.number="r.received_qty" type="number" min="0" :max="r.remaining_qty" class="h-8 w-20 rounded border border-slate-300 px-2 text-right font-bold" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <input v-model="receiveNotes" placeholder="Catatan penerimaan (opsional, mis. pengiriman tahap 1)" class="h-9 rounded-md border border-slate-300 px-3" />
                    <div class="mt-1 flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" @click="receivingPo = null" class="h-9 cursor-pointer rounded-md border border-slate-300 bg-white px-4 font-bold">Batal</button>
                        <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 px-4 font-bold text-white hover:bg-teal-700">Simpan GRN &amp; Tambah Stok</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: PAY SUPPLIER -->
        <div v-if="payingPo" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-5" @click.self="payingPo = null">
            <div class="w-full max-w-[400px] overflow-hidden rounded-[14px] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                    <h2 class="text-[15px] font-extrabold text-slate-900">Bayar Hutang {{ payingPo.po_number }}</h2>
                    <button type="button" @click="payingPo = null" class="cursor-pointer text-lg font-bold text-slate-500">&times;</button>
                </div>
                <form @submit.prevent="submitPayment" class="flex flex-col gap-3 p-5 text-xs">
                    <div class="rounded-md bg-slate-50 p-2.5 text-slate-700 tabular-nums">
                        Sisa hutang: <strong>{{ formatRupiah(payingPo.remaining_debt) }}</strong>
                    </div>
                    <RupiahInput v-model="payForm.amount" :max="payingPo.remaining_debt" required />
                    <select v-model="payForm.payment_method" class="h-9 rounded-md border border-slate-300 px-2.5">
                        <option value="transfer">Transfer Bank</option>
                        <option value="cash">Tunai</option>
                    </select>
                    <input v-model="payForm.reference_no" placeholder="No. referensi transfer (opsional)" class="h-9 rounded-md border border-slate-300 px-3" />
                    <div class="mt-1 flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" @click="payingPo = null" class="h-9 cursor-pointer rounded-md border border-slate-300 bg-white px-4 font-bold">Batal</button>
                        <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 px-4 font-bold text-white hover:bg-teal-700">Simpan Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>
    </PosLayout>
</template>
