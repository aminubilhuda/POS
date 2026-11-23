<script setup>
defineProps({
    lowStockCount: {
        type: Number,
        default: 0,
    },
    activeMenu: {
        type: String,
        default: 'kasir',
    },
});

const emit = defineEmits(['select-menu']);
</script>

<template>
    <aside
        class="row-span-2 z-20 flex flex-col items-center gap-2 bg-slate-900 px-2 py-3.5 text-slate-50"
        aria-label="Navigasi Utama"
    >
        <!-- Brand Mark (solid color, no gradient) -->
        <div
            class="mb-3 grid h-11 w-11 place-items-center rounded-xl bg-teal-600 text-[17px] font-extrabold tracking-tight text-white shadow-sm"
            title="NusaPOS Retail"
        >
            NP
        </div>

        <nav class="flex w-full flex-1 flex-col gap-1.5">
            <button
                type="button"
                @click="emit('select-menu', 'kasir')"
                :class="[
                    'relative flex flex-col items-center justify-center gap-1 rounded-[10px] px-1 py-2.5 text-[10.5px] font-semibold transition-colors cursor-pointer',
                    activeMenu === 'kasir'
                        ? 'bg-teal-500/15 text-teal-300'
                        : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100',
                ]"
            >
                <span
                    v-if="activeMenu === 'kasir'"
                    class="absolute -left-2 top-2.5 bottom-2.5 w-[3.5px] rounded-r bg-teal-400"
                />
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="14" rx="2" />
                    <path d="M8 20h8M12 18v2M6 8h4M6 12h2M14 8h4v4h-4z" />
                </svg>
                <span>Kasir</span>
            </button>

            <button
                type="button"
                @click="emit('select-menu', 'produk')"
                class="relative flex flex-col items-center justify-center gap-1 rounded-[10px] px-1 py-2.5 text-[10.5px] font-semibold text-slate-400 transition-colors hover:bg-slate-800 hover:text-slate-100 cursor-pointer"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
                    <path d="m3.3 7 8.7 5 8.7-5M12 22V12" />
                </svg>
                <span>Produk</span>
            </button>

            <button
                type="button"
                @click="emit('select-menu', 'inventori')"
                :title="`${lowStockCount} produk stok menipis atau habis`"
                class="relative flex flex-col items-center justify-center gap-1 rounded-[10px] px-1 py-2.5 text-[10.5px] font-semibold text-slate-400 transition-colors hover:bg-slate-800 hover:text-slate-100 cursor-pointer"
            >
                <span
                    v-if="lowStockCount > 0"
                    class="absolute top-1 right-2 grid h-4 min-w-4 place-items-center rounded-full bg-amber-500 px-1 text-[9.5px] font-extrabold text-slate-900 tabular-nums"
                >
                    {{ lowStockCount }}
                </span>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9h18v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z" />
                    <path d="M3 9V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4M9 14h6" />
                </svg>
                <span>Inventori</span>
            </button>

            <button
                type="button"
                @click="emit('select-menu', 'pembelian')"
                class="relative flex flex-col items-center justify-center gap-1 rounded-[10px] px-1 py-2.5 text-[10.5px] font-semibold text-slate-400 transition-colors hover:bg-slate-800 hover:text-slate-100 cursor-pointer"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10 17h4V5H2v12h3" />
                    <path d="M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5v8h1" />
                    <circle cx="7.5" cy="17.5" r="2.5" />
                    <circle cx="17.5" cy="17.5" r="2.5" />
                </svg>
                <span>Pembelian</span>
            </button>

            <button
                type="button"
                @click="emit('select-menu', 'laporan')"
                class="relative flex flex-col items-center justify-center gap-1 rounded-[10px] px-1 py-2.5 text-[10.5px] font-semibold text-slate-400 transition-colors hover:bg-slate-800 hover:text-slate-100 cursor-pointer"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 3v18h18" />
                    <path d="m19 9-5 5-4-4-3 3" />
                </svg>
                <span>Laporan</span>
            </button>
        </nav>

        <div class="flex w-full flex-col items-center gap-1.5 border-t border-slate-700/50 pt-2.5">
            <button
                type="button"
                @click="emit('select-menu', 'pengaturan')"
                class="flex w-full flex-col items-center justify-center gap-1 rounded-[10px] px-1 py-2.5 text-[10.5px] font-semibold text-slate-400 transition-colors hover:bg-slate-800 hover:text-slate-100 cursor-pointer"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3" />
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.6.85 1 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z" />
                </svg>
                <span>Setelan</span>
            </button>
        </div>
    </aside>
</template>
