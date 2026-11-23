<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PosLayout from '../../layouts/PosLayout.vue';
import { formatRupiah } from '../../lib/format';

const props = defineProps({
    canViewAllBranches: { type: Boolean, default: false },
    scopeLabel: { type: String, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
    chartSeries: { type: Array, default: () => [] },
    dailySales: { type: Array, required: true },
    topProducts: { type: Array, required: true },
    cashierPerformance: { type: Array, required: true },
});

const maxChartValue = computed(() => {
    const maxVal = Math.max(...props.chartSeries.map((c) => Math.max(c.net_sales, c.gross_profit)), 0);
    return maxVal > 0 ? maxVal : 1;
});

function barHeightPct(val) {
    if (!val || val <= 0) return '4%';
    return `${Math.max(8, Math.round((val / maxChartValue.value) * 100))}%`;
}

const filterState = reactive({
    scope: props.filters.scope,
    from: props.filters.from,
    to: props.filters.to,
});

function applyFilters() {
    router.get('/reports', filterState, { preserveState: true, preserveScroll: true });
}

const exportQuery = computed(() => {
    const params = new URLSearchParams({
        scope: filterState.scope,
        from: filterState.from,
        to: filterState.to,
    });
    return params.toString();
});
</script>

<template>
    <Head title="Laporan & Laba Rugi" />

    <PosLayout active-menu="reports" title="Laporan & Analitik" :subtitle="`Cakupan: ${scopeLabel}`">
        <div class="mx-auto flex max-w-7xl flex-col gap-4">
            <!-- Filter Bar & Exports -->
            <div class="flex flex-wrap items-end justify-between gap-3 rounded-[14px] border border-slate-200 bg-white p-4 text-xs">
                <div class="flex flex-wrap items-end gap-3">
                    <div v-if="canViewAllBranches" class="flex flex-col gap-1">
                        <label class="font-bold text-slate-600">Cakupan Cabang</label>
                        <select v-model="filterState.scope" @change="applyFilters" class="h-9 rounded-md border border-slate-300 px-3 font-semibold">
                            <option value="branch">Cabang Aktif Saja</option>
                            <option value="all">Semua Cabang (Gabungan)</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold text-slate-600">Dari Tanggal</label>
                        <input v-model="filterState.from" type="date" class="h-9 rounded-md border border-slate-300 px-3" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold text-slate-600">Sampai Tanggal</label>
                        <input v-model="filterState.to" type="date" class="h-9 rounded-md border border-slate-300 px-3" />
                    </div>
                    <button type="button" @click="applyFilters" class="h-9 cursor-pointer rounded-md bg-teal-600 px-4 font-bold text-white hover:bg-teal-700">
                        Terapkan
                    </button>
                </div>

                <div class="flex gap-2">
                    <a
                        :href="`/reports/export/excel?${exportQuery}`"
                        class="inline-flex h-9 items-center rounded-md border border-slate-300 bg-white px-3.5 font-bold text-slate-800 hover:border-teal-600 hover:text-teal-700"
                    >
                        Ekspor Excel (.csv)
                    </a>
                    <a
                        :href="`/reports/export/pdf?${exportQuery}`"
                        target="_blank"
                        class="inline-flex h-9 items-center rounded-md bg-slate-900 px-3.5 font-bold text-white hover:bg-slate-800"
                    >
                        Cetak / PDF
                    </a>
                </div>
            </div>

            <!-- Profit & Loss KPI Grid -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 tabular-nums">
                <div class="rounded-[12px] border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">Penjualan Bersih ({{ summary.transactions_count }} trx)</div>
                    <div class="mt-1 text-xl font-extrabold text-slate-900">{{ formatRupiah(summary.net_sales) }}</div>
                    <div class="mt-0.5 text-[11px] text-slate-500">PPN: {{ formatRupiah(summary.tax_total) }}</div>
                </div>

                <div class="rounded-[12px] border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">HPP (Harga Pokok Penjualan)</div>
                    <div class="mt-1 text-xl font-extrabold text-slate-900">{{ formatRupiah(summary.cogs) }}</div>
                    <div class="mt-0.5 text-[11px] text-slate-500">Berdasarkan snapshot modal</div>
                </div>

                <div class="rounded-[12px] border border-teal-200 bg-teal-50 p-4">
                    <div class="text-xs font-semibold text-teal-800">Laba Kotor (Margin {{ summary.margin_pct }}%)</div>
                    <div class="mt-1 text-xl font-extrabold text-teal-800">{{ formatRupiah(summary.gross_profit) }}</div>
                    <div class="mt-0.5 text-[11px] text-teal-700">Penjualan Bersih − HPP</div>
                </div>

                <div class="rounded-[12px] border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">Nilai Persediaan Stok</div>
                    <div class="mt-1 text-xl font-extrabold text-slate-900">{{ formatRupiah(summary.inventory_value) }}</div>
                    <div class="mt-0.5 text-[11px] text-slate-500">Stok aktif × harga modal</div>
                </div>
            </div>

            <!-- 7-Day Solid Bar Chart -->
            <div v-if="chartSeries.length > 0" class="rounded-[14px] border border-slate-200 bg-white p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-extrabold text-slate-900">Grafik Penjualan Bersih vs Laba Kotor (7 Hari Terakhir)</h3>
                    <div class="flex items-center gap-4 text-xs font-semibold text-slate-600">
                        <span class="inline-flex items-center gap-1.5"><i class="inline-block h-2.5 w-2.5 rounded-xs bg-teal-600" />Penjualan Bersih</span>
                        <span class="inline-flex items-center gap-1.5"><i class="inline-block h-2.5 w-2.5 rounded-xs bg-slate-900" />Laba Kotor</span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <div class="grid h-48 min-w-[320px] grid-cols-7 items-end gap-2 border-b border-slate-200 px-1 pt-4 pb-2 sm:gap-3 sm:px-2">
                        <div v-for="bar in chartSeries" :key="bar.date" class="flex h-full flex-col items-center justify-end gap-2">
                            <div class="flex h-36 items-end gap-1 sm:gap-1.5">
                                <div
                                    class="w-3.5 rounded-t bg-teal-600 transition-all sm:w-5"
                                    :style="{ height: barHeightPct(bar.net_sales) }"
                                    :title="`Penjualan: ${formatRupiah(bar.net_sales)}`"
                                />
                                <div
                                    class="w-3.5 rounded-t bg-slate-900 transition-all sm:w-5"
                                    :style="{ height: barHeightPct(bar.gross_profit) }"
                                    :title="`Laba Kotor: ${formatRupiah(bar.gross_profit)}`"
                                />
                            </div>
                            <span class="text-[10px] font-bold text-slate-600 sm:text-[11px]">{{ bar.label }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Row -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <!-- Produk Terlaris -->
                <div class="overflow-hidden rounded-[14px] border border-slate-200 bg-white">
                    <div class="border-b border-slate-200 px-4 py-3 text-sm font-extrabold text-slate-900">
                        Produk Terlaris
                    </div>
                    <table class="w-full text-left text-xs tabular-nums">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                            <tr>
                                <th class="px-4 py-2.5">Produk</th>
                                <th class="px-4 py-2.5 text-right">Qty</th>
                                <th class="px-4 py-2.5 text-right">Omzet</th>
                                <th class="px-4 py-2.5 text-right">Laba</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="topProducts.length === 0">
                                <td colspan="4" class="px-4 py-6 text-center text-slate-500">Belum ada data penjualan.</td>
                            </tr>
                            <tr v-for="(item, idx) in topProducts" :key="idx">
                                <td class="px-4 py-2.5">
                                    <div class="font-bold text-slate-900">{{ item.product_name }}</div>
                                    <div class="text-[11px] text-slate-400">{{ item.variant_name }}</div>
                                </td>
                                <td class="px-4 py-2.5 text-right font-bold">{{ item.qty_sold }}</td>
                                <td class="px-4 py-2.5 text-right">{{ formatRupiah(item.revenue) }}</td>
                                <td class="px-4 py-2.5 text-right font-bold text-teal-700">{{ formatRupiah(item.profit) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Penjualan Harian & Kinerja Kasir -->
                <div class="flex flex-col gap-4">
                    <div class="overflow-hidden rounded-[14px] border border-slate-200 bg-white">
                        <div class="border-b border-slate-200 px-4 py-3 text-sm font-extrabold text-slate-900">
                            Penjualan Harian
                        </div>
                        <table class="w-full text-left text-xs tabular-nums">
                            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                                <tr>
                                    <th class="px-4 py-2.5">Tanggal</th>
                                    <th class="px-4 py-2.5 text-right">Transaksi</th>
                                    <th class="px-4 py-2.5 text-right">PPN</th>
                                    <th class="px-4 py-2.5 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="d in dailySales" :key="d.date">
                                    <td class="px-4 py-2.5 font-mono font-bold text-slate-800">{{ d.date }}</td>
                                    <td class="px-4 py-2.5 text-right">{{ d.transactions }}</td>
                                    <td class="px-4 py-2.5 text-right text-slate-500">{{ formatRupiah(d.tax_amount) }}</td>
                                    <td class="px-4 py-2.5 text-right font-bold text-slate-900">{{ formatRupiah(d.grand_total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="overflow-hidden rounded-[14px] border border-slate-200 bg-white">
                        <div class="border-b border-slate-200 px-4 py-3 text-sm font-extrabold text-slate-900">
                            Kinerja Kasir
                        </div>
                        <table class="w-full text-left text-xs tabular-nums">
                            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                                <tr>
                                    <th class="px-4 py-2.5">Pengguna</th>
                                    <th class="px-4 py-2.5">Role</th>
                                    <th class="px-4 py-2.5 text-right">Transaksi</th>
                                    <th class="px-4 py-2.5 text-right">Total Penjualan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(c, idx) in cashierPerformance" :key="idx">
                                    <td class="px-4 py-2.5 font-bold text-slate-900">{{ c.cashier_name }}</td>
                                    <td class="px-4 py-2.5 capitalize text-slate-600">{{ c.role }}</td>
                                    <td class="px-4 py-2.5 text-right">{{ c.transactions }}</td>
                                    <td class="px-4 py-2.5 text-right font-bold text-teal-700">{{ formatRupiah(c.total_sales) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </PosLayout>
</template>
