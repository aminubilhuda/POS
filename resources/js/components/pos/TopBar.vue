<script setup>
defineProps({
    branches: {
        type: Array,
        required: true,
    },
    selectedBranchCode: {
        type: String,
        required: true,
    },
    dateDisplay: {
        type: String,
        required: true,
    },
    shift: {
        type: Object,
        required: true,
    },
    heldCount: {
        type: Number,
        default: 0,
    },
    cashier: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['update:selectedBranchCode', 'open-shift', 'open-hold']);
</script>

<template>
    <header class="col-start-2 z-15 flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-5">
        <div class="flex min-w-0 items-center gap-3.5">
            <div class="flex flex-col">
                <div class="flex items-center gap-2 text-[15px] font-extrabold tracking-tight text-slate-900">
                    <span>NusaPOS Retail</span>
                    <span class="rounded-full border border-teal-200 bg-teal-50 px-1.5 py-0.5 text-[10px] font-bold text-teal-700">
                        Paket B
                    </span>
                </div>
                <span class="text-[11.5px] text-slate-500">Terminal Kasir · {{ dateDisplay }}</span>
            </div>

            <div
                class="flex items-center gap-2 rounded-[10px] border border-slate-200 bg-slate-50 px-3 py-1.5"
                title="Cakupan data dibatasi per cabang (BranchScope)"
            >
                <svg class="h-4 w-4 shrink-0 text-teal-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9v.01M9 12v.01M9 15v.01" />
                </svg>
                <select
                    :value="selectedBranchCode"
                    @change="emit('update:selectedBranchCode', $event.target.value)"
                    aria-label="Pilih Cabang"
                    class="cursor-pointer border-none bg-transparent text-[13px] font-semibold text-slate-900 outline-none"
                >
                    <option v-for="branch in branches" :key="branch.code" :value="branch.code">
                        {{ branch.code }} — {{ branch.name }}
                    </option>
                </select>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <button
                type="button"
                @click="emit('open-shift')"
                title="Klik untuk lihat ringkasan & tutup shift"
                class="inline-flex cursor-pointer items-center gap-2 whitespace-nowrap rounded-full border border-green-200 bg-green-100 px-3 py-1.5 text-xs font-semibold text-green-700 transition-colors hover:border-green-300 hover:bg-green-200/70"
            >
                <span class="h-2 w-2 rounded-full bg-green-700 ring-3 ring-green-700/20" />
                <span>{{ shift.code }} · {{ shift.status }}</span>
            </button>

            <div
                class="hidden items-center gap-2 whitespace-nowrap rounded-full border border-green-200 bg-green-100 px-3 py-1.5 text-xs font-semibold text-green-700 xl:inline-flex"
                title="QZ Tray WebSocket terhubung ke printer thermal 58mm & cash drawer RJ11"
            >
                <span class="h-2 w-2 rounded-full bg-green-700" />
                <span>QZ Tray 58mm</span>
            </div>

            <button
                type="button"
                @click="emit('open-hold')"
                title="Lihat transaksi yang ditahan (Hold)"
                class="inline-flex cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-full border border-amber-200 bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-800 transition-colors hover:border-amber-300 hover:bg-amber-200/70 tabular-nums"
            >
                <span>Hold ({{ heldCount }})</span>
            </button>

            <div class="flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1 pr-3 pl-1.5">
                <div class="grid h-[30px] w-[30px] place-items-center rounded-full bg-slate-900 text-xs font-bold text-white">
                    {{ cashier.initials }}
                </div>
                <div class="flex flex-col leading-tight">
                    <span class="text-[12.5px] font-bold text-slate-900">{{ cashier.name }}</span>
                    <span class="text-[10.5px] text-slate-500">{{ cashier.role }} · {{ selectedBranchCode }}</span>
                </div>
            </div>
        </div>
    </header>
</template>
