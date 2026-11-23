<script setup>
import { computed, ref } from 'vue';
import { formatRupiah, parseRupiahInput } from '../../lib/format';

const props = defineProps({
    activeModal: {
        type: String,
        default: null, // 'receipt' | 'hold' | 'void' | 'shift' | null
    },
    receiptData: {
        type: Object,
        default: null,
    },
    branch: {
        type: Object,
        required: true,
    },
    heldSales: {
        type: Array,
        required: true,
    },
    shift: {
        type: Object,
        required: true,
    },
    cashier: {
        type: Object,
        required: true,
    },
    lastInvoiceNo: {
        type: String,
        required: true,
    },
    isReprint: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'confirm-payment', 'resume-hold', 'delete-hold', 'submit-void', 'close-shift']);

const voidInvoiceNo = ref(props.lastInvoiceNo);
const adminPin = ref('');
const voidError = ref('');

const actualCashInput = ref('');
const expectedCash = computed(() => props.shift.opening_cash + props.shift.cash_sales);
const actualCashValue = computed(() => parseRupiahInput(actualCashInput.value));
const cashDifference = computed(() => (actualCashInput.value.trim() ? actualCashValue.value - expectedCash.value : null));

function handleVoidSubmit() {
    if (adminPin.value.trim().length < 4) {
        voidError.value = 'Masukkan minimal 4 digit PIN Admin untuk otorisasi.';
        return;
    }
    voidError.value = '';
    emit('submit-void', { invoiceNo: voidInvoiceNo.value, pin: adminPin.value });
    adminPin.value = '';
}
</script>

<template>
    <!-- MODAL 1: PREVIEW & CETAK STRUK THERMAL 58MM -->
    <div
        v-if="activeModal === 'receipt' && receiptData"
        class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-5 backdrop-blur-[3px]"
        role="dialog"
        aria-modal="true"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-[440px] overflow-hidden rounded-[14px] bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4.5 py-3.5">
                <h2 class="text-[15px] font-extrabold text-slate-900">
                    {{ isReprint ? 'Cetak Ulang Struk Thermal 58mm' : 'Preview Struk Thermal 58mm' }}
                </h2>
                <button
                    type="button"
                    @click="emit('close')"
                    aria-label="Tutup"
                    class="grid h-7.5 w-7.5 cursor-pointer place-items-center rounded-lg text-lg font-bold text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                >
                    &times;
                </button>
            </div>

            <div class="flex flex-col gap-3.5 p-4.5">
                <div class="rounded-md border border-green-200 bg-green-100 px-3 py-2.5 text-xs leading-relaxed text-green-800">
                    <strong>QZ Tray Terhubung:</strong> {{ branch.printer }} · Cash drawer RJ11 otomatis terbuka setelah cetak.
                </div>

                <div class="mx-auto w-[264px] border border-dashed border-slate-300 bg-[#fffdf9] px-3.5 py-4 font-mono text-[11px] leading-snug text-slate-800 shadow-sm tabular-nums">
                    <div class="text-center font-bold">NUSAPOS RETAIL — {{ branch.code }}</div>
                    <div class="text-center">{{ branch.address }}</div>
                    <div class="text-center">Telp: {{ branch.phone }}</div>
                    <hr class="my-2 border-t border-dashed border-slate-400" />

                    <div class="flex justify-between gap-2"><span>No:</span><span>{{ receiptData.invoiceNo }}</span></div>
                    <div class="flex justify-between gap-2"><span>Tgl:</span><span>{{ receiptData.timestamp }}</span></div>
                    <div class="flex justify-between gap-2"><span>Kasir:</span><span>{{ cashier.name }}</span></div>
                    <div v-if="receiptData.customerName" class="flex justify-between gap-2">
                        <span>Pelanggan:</span><span>{{ receiptData.customerName }}</span>
                    </div>
                    <hr class="my-2 border-t border-dashed border-slate-400" />

                    <div v-for="item in receiptData.items" :key="item.product.id" class="mb-1.5">
                        <div class="font-bold">{{ item.product.name }}</div>
                        <div class="flex justify-between gap-2">
                            <span>{{ item.qty }} x {{ formatRupiah(item.product.price, false) }}</span>
                            <span>{{ formatRupiah(item.grossSubtotal, false) }}</span>
                        </div>
                        <div v-if="item.itemDiscount > 0" class="flex justify-between gap-2 text-slate-600">
                            <span>&nbsp;&nbsp;Diskon Item</span>
                            <span>-{{ formatRupiah(item.itemDiscount, false) }}</span>
                        </div>
                    </div>

                    <hr class="my-2 border-t border-dashed border-slate-400" />
                    <div class="flex justify-between gap-2">
                        <span>Subtotal</span>
                        <span>{{ formatRupiah(receiptData.subtotalAfterItemDiscount, false) }}</span>
                    </div>
                    <div v-if="receiptData.trxDiscount > 0" class="flex justify-between gap-2">
                        <span>Diskon Trx</span>
                        <span>-{{ formatRupiah(receiptData.trxDiscount, false) }}</span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span>PPN {{ receiptData.taxRate }}%</span>
                        <span>{{ formatRupiah(receiptData.taxAmount, false) }}</span>
                    </div>
                    <div class="mt-0.5 flex justify-between gap-2 font-bold">
                        <span>TOTAL</span>
                        <span>{{ formatRupiah(receiptData.grandTotal) }}</span>
                    </div>
                    <hr class="my-2 border-t border-dashed border-slate-400" />
                    <div class="flex justify-between gap-2">
                        <span>{{ receiptData.paymentMethodLabel.toUpperCase() }}</span>
                        <span>{{ formatRupiah(receiptData.cashPaid) }}</span>
                    </div>
                    <div class="flex justify-between gap-2 font-bold">
                        <span>KEMBALI</span>
                        <span>{{ formatRupiah(receiptData.change) }}</span>
                    </div>
                    <hr class="my-2 border-t border-dashed border-slate-400" />
                    <div class="text-center">Terima kasih telah berbelanja!</div>
                    <div class="text-center">Barang yg dibeli tdk dpt dikembalikan</div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4.5 py-3">
                <button
                    type="button"
                    @click="emit('close')"
                    class="inline-flex h-9 cursor-pointer items-center justify-center rounded-md border border-slate-300 bg-white px-3.5 text-[12.5px] font-bold text-slate-700 hover:bg-slate-100"
                >
                    Tutup
                </button>
                <button
                    type="button"
                    @click="emit('confirm-payment')"
                    class="inline-flex h-9 cursor-pointer items-center justify-center rounded-md bg-teal-600 px-4 text-[12.5px] font-bold text-white hover:bg-teal-700"
                >
                    {{ isReprint ? 'Cetak Ulang via QZ Tray' : 'Kirim ke QZ Tray & Buka Laci' }}
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL 2: TRANSAKSI HOLD -->
    <div
        v-if="activeModal === 'hold'"
        class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-5 backdrop-blur-[3px]"
        role="dialog"
        aria-modal="true"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-[440px] overflow-hidden rounded-[14px] bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4.5 py-3.5">
                <h2 class="text-[15px] font-extrabold text-slate-900">Transaksi Ditahan (Hold)</h2>
                <button
                    type="button"
                    @click="emit('close')"
                    aria-label="Tutup"
                    class="grid h-7.5 w-7.5 cursor-pointer place-items-center rounded-lg text-lg font-bold text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                >
                    &times;
                </button>
            </div>

            <div class="flex flex-col gap-2.5 p-4.5">
                <div v-if="heldSales.length === 0" class="rounded-lg border border-dashed border-slate-300 py-8 text-center text-xs text-slate-500">
                    Tidak ada transaksi yang ditahan saat ini.
                </div>

                <div
                    v-for="sale in heldSales"
                    :key="sale.code"
                    class="flex items-center justify-between gap-2.5 rounded-[10px] border border-slate-200 bg-slate-50 p-3"
                >
                    <div class="flex flex-col gap-0.5">
                        <strong class="text-[13px] text-slate-900">
                            {{ sale.customer_name }} · {{ sale.totalQty }} item
                        </strong>
                        <span class="font-mono text-[11.5px] text-slate-500 tabular-nums">
                            {{ sale.code }} · {{ sale.time }} · {{ formatRupiah(sale.totalAmount) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            @click="emit('delete-hold', sale.code)"
                            class="inline-flex h-8 cursor-pointer items-center justify-center rounded-md border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-600 hover:border-red-300 hover:bg-red-50 hover:text-red-600"
                        >
                            Hapus
                        </button>
                        <button
                            type="button"
                            @click="emit('resume-hold', sale.code)"
                            class="inline-flex h-8 cursor-pointer items-center justify-center rounded-md bg-teal-600 px-3 text-xs font-bold text-white hover:bg-teal-700"
                        >
                            Lanjutkan
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-4.5 py-3">
                <button
                    type="button"
                    @click="emit('close')"
                    class="inline-flex h-9 cursor-pointer items-center justify-center rounded-md border border-slate-300 bg-white px-3.5 text-[12.5px] font-bold text-slate-700 hover:bg-slate-100"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL 3: OTORISASI VOID / REFUND (PIN ADMIN) -->
    <div
        v-if="activeModal === 'void'"
        class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-5 backdrop-blur-[3px]"
        role="dialog"
        aria-modal="true"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-[440px] overflow-hidden rounded-[14px] bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4.5 py-3.5">
                <h2 class="text-[15px] font-extrabold text-slate-900">Otorisasi Void / Refund</h2>
                <button
                    type="button"
                    @click="emit('close')"
                    aria-label="Tutup"
                    class="grid h-7.5 w-7.5 cursor-pointer place-items-center rounded-lg text-lg font-bold text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                >
                    &times;
                </button>
            </div>

            <form class="flex flex-col gap-3.5 p-4.5" @submit.prevent="handleVoidSubmit">
                <div class="rounded-md border border-red-200 bg-red-100 px-3 py-2.5 text-xs leading-relaxed text-red-900">
                    Sesuai kebijakan hak akses, pembatalan (void) atau pengembalian dana (refund) memerlukan otorisasi
                    <strong>Admin Cabang</strong> dan dicatat di <em>Activity Log</em>.
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="void-inv" class="text-xs font-bold text-slate-900">Nomor Transaksi</label>
                    <input
                        id="void-inv"
                        v-model="voidInvoiceNo"
                        type="text"
                        class="h-10 rounded-md border border-slate-300 px-3 font-mono text-sm outline-none focus:border-teal-600"
                    />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="admin-pin" class="text-xs font-bold text-slate-900">PIN Otorisasi Admin</label>
                    <input
                        id="admin-pin"
                        v-model="adminPin"
                        type="password"
                        placeholder="Masukkan 6 digit PIN Admin"
                        class="h-10 rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-teal-600"
                    />
                    <p v-if="voidError" class="text-xs font-semibold text-red-600">{{ voidError }}</p>
                </div>

                <div class="mt-1 flex justify-end gap-2 border-t border-slate-200 pt-3">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="inline-flex h-9 cursor-pointer items-center justify-center rounded-md border border-slate-300 bg-white px-3.5 text-[12.5px] font-bold text-slate-700 hover:bg-slate-100"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-9 cursor-pointer items-center justify-center rounded-md bg-red-600 px-4 text-[12.5px] font-bold text-white hover:bg-red-700"
                    >
                        Verifikasi &amp; Proses
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 4: RINGKASAN SHIFT AKTIF -->
    <div
        v-if="activeModal === 'shift'"
        class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-5 backdrop-blur-[3px]"
        role="dialog"
        aria-modal="true"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-[440px] overflow-hidden rounded-[14px] bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4.5 py-3.5">
                <h2 class="text-[15px] font-extrabold text-slate-900">{{ shift.code }} — {{ branch.code }}</h2>
                <button
                    type="button"
                    @click="emit('close')"
                    aria-label="Tutup"
                    class="grid h-7.5 w-7.5 cursor-pointer place-items-center rounded-lg text-lg font-bold text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                >
                    &times;
                </button>
            </div>

            <div class="flex flex-col gap-3.5 p-4.5">
                <div class="grid grid-cols-2 gap-2.5 tabular-nums">
                    <div class="flex flex-col gap-0.5 rounded-[10px] border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <span class="text-[11px] text-slate-500">Kasir &amp; Waktu Buka</span>
                        <strong class="text-[14.5px] font-extrabold text-slate-900">{{ cashier.name.split(' ')[0] }} · {{ shift.opened_at }}</strong>
                    </div>
                    <div class="flex flex-col gap-0.5 rounded-[10px] border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <span class="text-[11px] text-slate-500">Transaksi Selesai</span>
                        <strong class="text-[14.5px] font-extrabold text-slate-900">{{ shift.transactions_count }} Transaksi</strong>
                    </div>
                    <div class="flex flex-col gap-0.5 rounded-[10px] border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <span class="text-[11px] text-slate-500">Modal Awal Laci</span>
                        <strong class="text-[14.5px] font-extrabold text-slate-900">{{ formatRupiah(shift.opening_cash) }}</strong>
                    </div>
                    <div class="flex flex-col gap-0.5 rounded-[10px] border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <span class="text-[11px] text-slate-500">Penjualan Tunai</span>
                        <strong class="text-[14.5px] font-extrabold text-teal-700">{{ formatRupiah(shift.cash_sales) }}</strong>
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs tabular-nums">
                    <span class="font-semibold text-slate-600">Estimasi Kas Seharusnya di Laci</span>
                    <strong class="text-sm font-extrabold text-slate-900">{{ formatRupiah(expectedCash) }}</strong>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="actual-cash" class="text-xs font-bold text-slate-900">
                        Hitung Fisik Kas Laci Saat Tutup Shift (Rp)
                    </label>
                    <input
                        id="actual-cash"
                        v-model="actualCashInput"
                        type="text"
                        :placeholder="`Contoh: ${formatRupiah(expectedCash, false)}`"
                        class="h-10 rounded-md border border-slate-300 px-3 text-sm font-bold outline-none focus:border-teal-600 tabular-nums"
                    />
                    <div v-if="cashDifference !== null" class="flex justify-between text-xs font-bold tabular-nums">
                        <span class="text-slate-600">Selisih Kas:</span>
                        <span :class="cashDifference === 0 ? 'text-green-700' : cashDifference > 0 ? 'text-blue-700' : 'text-red-600'">
                            {{ cashDifference === 0 ? 'Sesuai (Rp 0)' : formatRupiah(cashDifference) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4.5 py-3">
                <button
                    type="button"
                    @click="emit('close')"
                    class="inline-flex h-9 cursor-pointer items-center justify-center rounded-md border border-slate-300 bg-white px-3.5 text-[12.5px] font-bold text-slate-700 hover:bg-slate-100"
                >
                    Kembali ke Kasir
                </button>
                <button
                    type="button"
                    @click="emit('close-shift', actualCashValue)"
                    class="inline-flex h-9 cursor-pointer items-center justify-center rounded-md bg-teal-600 px-4 text-[12.5px] font-bold text-white hover:bg-teal-700"
                >
                    Tutup Shift &amp; Cetak Rekap
                </button>
            </div>
        </div>
    </div>
</template>
