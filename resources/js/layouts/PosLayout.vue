<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    activeMenu: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        default: 'NusaPOS Retail',
    },
    subtitle: {
        type: String,
        default: '',
    },
    fullBleed: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const activeBranch = computed(() => page.props.activeBranch);
const accessibleBranches = computed(() => page.props.accessibleBranches || []);
const lowStockCount = computed(() => page.props.lowStockCount || 0);

const toastText = ref('');
const toastType = ref('success');
let toastTimer = null;

watch(
    () => [page.props.flash?.success, page.props.flash?.error],
    ([success, error]) => {
        const msg = error || success;
        if (!msg) return;
        toastText.value = msg;
        toastType.value = error ? 'error' : 'success';
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            toastText.value = '';
        }, 3600);
    },
    { immediate: true }
);

function handleBranchChange(event) {
    router.post(
        '/branch/switch',
        { branch_id: Number(event.target.value) },
        { preserveScroll: true }
    );
}

function logout() {
    router.post('/logout');
}

const navItems = computed(() => {
    const m = user.value?.modules || {};
    return [
        { key: 'pos', label: 'Kasir', href: '/pos', visible: m.pos },
        { key: 'products', label: 'Produk', href: '/products', visible: m.products },
        { key: 'inventory', label: 'Inventori', href: '/inventory', visible: m.inventory, badge: lowStockCount.value },
        { key: 'purchasing', label: 'Pembelian', href: '/purchasing', visible: m.purchasing },
        { key: 'reports', label: 'Laporan', href: '/reports', visible: m.reports },
    ].filter((item) => item.visible);
});

const mobileNavItems = computed(() => {
    const items = [...navItems.value];
    if (user.value?.modules?.settings) {
        items.push({ key: 'settings', label: 'Setelan', href: '/settings', visible: true });
    }
    return items;
});
</script>

<template>
    <div class="flex h-dvh flex-col overflow-hidden bg-[#f3f5f8] text-sm text-slate-900 md:grid md:grid-cols-[76px_1fr] md:grid-rows-[62px_1fr_36px]">
        <!-- Desktop & Tablet Left Sidebar Rail -->
        <aside class="hidden md:row-span-3 md:z-20 md:flex md:flex-col md:items-center md:gap-2 md:bg-slate-900 md:px-2 md:py-3.5 md:text-slate-50" aria-label="Navigasi Utama">
            <Link
                href="/pos"
                class="mb-3 grid h-11 w-11 place-items-center rounded-xl bg-teal-600 text-[17px] font-extrabold tracking-tight text-white shadow-sm"
                title="NusaPOS Retail"
            >
                NP
            </Link>

            <nav class="flex w-full flex-1 flex-col gap-1.5">
                <Link
                    v-for="item in navItems"
                    :key="item.key"
                    :href="item.href"
                    :class="[
                        'relative flex flex-col items-center justify-center gap-1 rounded-[10px] px-1 py-2.5 text-[10.5px] font-semibold transition-colors',
                        activeMenu === item.key
                            ? 'bg-teal-500/15 text-teal-300'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100',
                    ]"
                >
                    <span
                        v-if="activeMenu === item.key"
                        class="absolute -left-2 top-2.5 bottom-2.5 w-[3.5px] rounded-r bg-teal-400"
                    />
                    <span
                        v-if="item.badge > 0"
                        class="absolute top-1 right-2 grid h-4 min-w-4 place-items-center rounded-full bg-amber-500 px-1 text-[9.5px] font-extrabold text-slate-900 tabular-nums"
                    >
                        {{ item.badge }}
                    </span>

                    <svg v-if="item.key === 'pos'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="14" rx="2" />
                        <path d="M8 20h8M12 18v2M6 8h4M6 12h2M14 8h4v4h-4z" />
                    </svg>
                    <svg v-else-if="item.key === 'products'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
                        <path d="m3.3 7 8.7 5 8.7-5M12 22V12" />
                    </svg>
                    <svg v-else-if="item.key === 'inventory'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9h18v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z" />
                        <path d="M3 9V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4M9 14h6" />
                    </svg>
                    <svg v-else-if="item.key === 'purchasing'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10 17h4V5H2v12h3" />
                        <path d="M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5v8h1" />
                        <circle cx="7.5" cy="17.5" r="2.5" />
                        <circle cx="17.5" cy="17.5" r="2.5" />
                    </svg>
                    <svg v-else-if="item.key === 'reports'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3v18h18" />
                        <path d="m19 9-5 5-4-4-3 3" />
                    </svg>

                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div class="flex w-full flex-col items-center gap-1.5 border-t border-slate-700/50 pt-2.5">
                <Link
                    v-if="user?.modules?.settings"
                    href="/settings"
                    :class="[
                        'relative flex w-full flex-col items-center justify-center gap-1 rounded-[10px] px-1 py-2.5 text-[10.5px] font-semibold transition-colors',
                        activeMenu === 'settings'
                            ? 'bg-teal-500/15 text-teal-300'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100',
                    ]"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3" />
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.6.85 1 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z" />
                    </svg>
                    <span>Setelan</span>
                </Link>
            </div>
        </aside>

        <!-- Responsive Top Utility Bar -->
        <header class="z-15 flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-white px-3 py-2 md:col-start-2 md:flex-nowrap md:gap-4 md:px-5 md:py-0">
            <div class="flex min-w-0 items-center gap-2.5 md:gap-3.5">
                <Link
                    href="/pos"
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-teal-600 text-xs font-extrabold text-white md:hidden"
                >
                    NP
                </Link>

                <div class="flex min-w-0 flex-col">
                    <div class="flex items-center gap-1.5 text-[13.5px] font-extrabold tracking-tight text-slate-900 md:gap-2 md:text-[15px]">
                        <span class="truncate">{{ title }}</span>
                        <span class="hidden rounded-full border border-teal-200 bg-teal-50 px-1.5 py-0.5 text-[10px] font-bold text-teal-700 sm:inline-block">
                            Paket B
                        </span>
                    </div>
                    <span v-if="subtitle" class="hidden truncate text-[11.5px] text-slate-500 sm:block">{{ subtitle }}</span>
                </div>

                <div
                    v-if="activeBranch && accessibleBranches.length > 0"
                    class="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 md:gap-2 md:rounded-[10px] md:px-3 md:py-1.5"
                    title="Cakupan data dibatasi per cabang (BranchScope)"
                >
                    <svg class="hidden h-4 w-4 shrink-0 text-teal-600 sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9v.01M9 12v.01M9 15v.01" />
                    </svg>
                    <select
                        :value="activeBranch.id"
                        @change="handleBranchChange"
                        :disabled="accessibleBranches.length <= 1"
                        aria-label="Pilih Cabang"
                        class="max-w-[130px] cursor-pointer truncate border-none bg-transparent text-xs font-semibold text-slate-900 outline-none disabled:cursor-default sm:max-w-none md:text-[13px]"
                    >
                        <option v-for="branch in accessibleBranches" :key="branch.id" :value="branch.id">
                            {{ branch.code }} — {{ branch.name }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-1.5 md:gap-2.5">
                <slot name="topbar-actions" />

                <div v-if="user" class="flex items-center gap-1.5 rounded-full border border-slate-200 bg-white py-0.5 pr-1.5 pl-1 md:gap-2 md:py-1 md:pr-2.5 md:pl-1.5">
                    <div class="grid h-7 w-7 place-items-center rounded-full bg-slate-900 text-[11px] font-bold text-white md:h-[30px] md:w-[30px] md:text-xs">
                        {{ user.initials }}
                    </div>
                    <div class="hidden flex-col leading-tight lg:flex">
                        <span class="text-[12.5px] font-bold text-slate-900">{{ user.name }}</span>
                        <span class="text-[10.5px] capitalize text-slate-500">{{ user.role }} · {{ activeBranch?.code }}</span>
                    </div>
                    <button
                        type="button"
                        @click="logout"
                        title="Keluar"
                        class="cursor-pointer rounded-md px-1.5 py-1 text-[11px] font-bold text-slate-500 hover:bg-red-50 hover:text-red-600 md:ml-1 md:px-2"
                    >
                        Keluar
                    </button>
                </div>
            </div>
        </header>

        <!-- Page Body -->
        <div :class="fullBleed ? 'min-h-0 flex-1 overflow-hidden md:col-start-2' : 'min-h-0 flex-1 overflow-y-auto p-3 sm:p-5 md:col-start-2'">
            <slot />
        </div>

        <!-- Desktop & Tablet App Footer -->
        <footer class="hidden items-center justify-between gap-4 border-t border-slate-200 bg-white px-5 text-[11px] text-slate-500 md:col-start-2 md:flex">
            <div class="flex items-center gap-2 truncate">
                <span class="font-bold text-slate-700">NusaPOS Retail</span>
                <span>&copy; {{ new Date().getFullYear() }}</span>
                <span class="hidden sm:inline">· POS Retail Multi-Cabang</span>
            </div>
            <div class="flex items-center gap-3 whitespace-nowrap">
                <span v-if="activeBranch" class="font-mono font-semibold text-slate-600">
                    {{ activeBranch.code }} — {{ activeBranch.name }}
                </span>
                <span class="hidden lg:inline text-slate-400">·</span>
                <span class="hidden lg:inline">Thermal 58mm (QZ Tray) · IDR</span>
            </div>
        </footer>

        <!-- Mobile / Android Bottom Navigation Bar -->
        <nav
            class="z-30 flex h-14 shrink-0 items-center justify-around border-t border-slate-800 bg-slate-900 px-1 text-slate-400 md:hidden"
            aria-label="Navigasi Mobile"
        >
            <Link
                v-for="item in mobileNavItems"
                :key="item.key"
                :href="item.href"
                :class="[
                    'relative flex flex-1 flex-col items-center justify-center gap-0.5 py-1 text-[10px] font-bold transition-colors',
                    activeMenu === item.key ? 'text-teal-400' : 'text-slate-400 hover:text-slate-200',
                ]"
            >
                <span
                    v-if="item.badge > 0"
                    class="absolute top-0.5 right-1/4 grid h-3.5 min-w-3.5 place-items-center rounded-full bg-amber-500 px-1 text-[9px] font-extrabold text-slate-900 tabular-nums"
                >
                    {{ item.badge }}
                </span>

                <svg v-if="item.key === 'pos'" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="14" rx="2" />
                    <path d="M8 20h8M12 18v2" />
                </svg>
                <svg v-else-if="item.key === 'products'" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
                </svg>
                <svg v-else-if="item.key === 'inventory'" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9h18v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z" />
                    <path d="M3 9V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4" />
                </svg>
                <svg v-else-if="item.key === 'purchasing'" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10 17h4V5H2v12h3M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5v8h1" />
                    <circle cx="7.5" cy="17.5" r="2.5" />
                    <circle cx="17.5" cy="17.5" r="2.5" />
                </svg>
                <svg v-else-if="item.key === 'reports'" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 3v18h18" />
                    <path d="m19 9-5 5-4-4-3 3" />
                </svg>
                <svg v-else-if="item.key === 'settings'" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3" />
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4" />
                </svg>

                <span class="truncate">{{ item.label }}</span>
            </Link>
        </nav>

        <!-- Global Toast -->
        <div
            v-if="toastText"
            role="status"
            :class="[
                'fixed bottom-16 left-1/2 z-50 -translate-x-1/2 rounded-lg px-4 py-2.5 text-xs font-semibold text-white shadow-lg md:bottom-5',
                toastType === 'error' ? 'bg-red-600' : 'bg-slate-900',
            ]"
        >
            {{ toastText }}
        </div>
    </div>
</template>
