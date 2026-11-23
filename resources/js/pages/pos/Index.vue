<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import PosLayout from '../../layouts/PosLayout.vue';
import RupiahInput from '../../components/RupiahInput.vue';
import { formatRupiah, parseRupiahInput } from '../../lib/format';
import { printViaQzTray } from '../../lib/qz';

const props = defineProps({
    shift: { type: Object, default: null },
    settings: { type: Object, required: true },
    categories: { type: Array, required: true },
    customers: { type: Array, required: true },
    products: { type: Array, required: true },
    heldSales: { type: Array, default: () => [] },
    recentSales: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
});

const page = usePage();
const activeBranch = computed(() => page.props.activeBranch);

const searchQuery = ref('');
const selectedCategory = ref('all');
const selectedCustomerId = ref(null);
const showCustomerPicker = ref(false);

const cart = ref([]);
const discountType = ref('nominal'); // 'nominal' | 'percent'
const discountValueNum = ref(0);
const paymentMethodCode = ref('cash');
const cashAmountNum = ref(0);
const clientUuid = ref(generateFullUuid());

const activeModal = ref(null); // 'receipt' | 'hold' | 'void' | 'shift' | 'history' | null
const activeReceipt = ref(null);
const isSubmitting = ref(false);
const mobilePane = ref('catalog'); // 'catalog' | 'cart' (for screens < lg)

// Void / Refund form
const voidMode = ref('void'); // 'void' | 'refund'
const voidInvoice = ref('');
const voidPin = ref('');
const voidReason = ref('');
const refundItemSelection = ref([]);

const selectedRefundSale = computed(() => {
    return props.recentSales.find((s) => s.invoice_number === voidInvoice.value) || null;
});

watch([voidInvoice, voidMode], () => {
    if (voidMode.value === 'refund' && selectedRefundSale.value) {
        refundItemSelection.value = selectedRefundSale.value.items.map((it) => ({
            sale_item_id: it.id,
            product_name: it.product_name,
            unit_name: it.unit_name,
            max_qty: it.quantity,
            unit_net: it.quantity > 0 ? Math.round(it.subtotal / it.quantity) : it.unit_price,
            selected: true,
            qty: 1,
        }));
    } else {
        refundItemSelection.value = [];
    }
});

const estimatedPartialRefund = computed(() => {
    if (!selectedRefundSale.value) return 0;
    const sub = refundItemSelection.value
        .filter((r) => r.selected && r.qty > 0)
        .reduce((s, r) => s + r.unit_net * r.qty, 0);
    const tax = Math.round((sub * (selectedRefundSale.value.tax_rate || 0)) / 100);
    return sub + tax;
});

// Shift open/close form
const openingCashNum = ref(500000);
const closingCashNum = ref('');

const searchInputRef = ref(null);
const discountInputRef = ref(null);

function generateFullUuid() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
}

// Watch flash receipt after successful DB checkout
watch(
    () => page.props.flash?.receipt,
    async (receipt) => {
        if (!receipt) return;
        activeReceipt.value = receipt;
        activeModal.value = 'receipt';
        cart.value = [];
        discountValueNum.value = 0;
        cashAmountNum.value = 0;
        selectedCustomerId.value = null;
        clientUuid.value = generateFullUuid();
        await printViaQzTray(receipt, props.settings);
    }
);

const activeCustomer = computed(() => {
    return props.customers.find((c) => c.id === selectedCustomerId.value) || props.customers[0];
});

const nextInvoicePreview = computed(() => {
    const seq = String(props.settings.next_sequence || 1).padStart(4, '0');
    return `${props.settings.receipt_prefix}/${activeBranch.value?.code || 'CAB01'}/${props.settings.date_code}/${seq}`;
});

const categoryCounts = computed(() => {
    const counts = { all: props.products.length };
    for (const p of props.products) {
        counts[p.category] = (counts[p.category] || 0) + 1;
    }
    return counts;
});

const filteredProducts = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return props.products.filter((p) => {
        const matchCat = selectedCategory.value === 'all' || p.category === selectedCategory.value;
        if (!matchCat) return false;
        if (!q) return true;
        return (
            p.name.toLowerCase().includes(q) ||
            p.sku.toLowerCase().includes(q) ||
            p.barcode.toLowerCase().includes(q) ||
            p.variant.toLowerCase().includes(q)
        );
    });
});

const cartDetails = computed(() => {
    return cart.value
        .map((entry) => {
            const product = props.products.find((p) => p.id === entry.variant_id);
            if (!product) return null;
            const unitObj = entry.product_unit_id
                ? (product.product_units || []).find((u) => u.id === entry.product_unit_id)
                : null;
            const conversionQty = unitObj ? unitObj.conversion_qty : 1;
            const unitPrice = unitObj ? unitObj.sell_price : product.price;
            const unitLabel = unitObj ? unitObj.label : product.unit;
            const gross = unitPrice * entry.qty;

            let itemDiscount = 0;
            if (entry.manual_discount) {
                const dVal = parseRupiahInput(entry.discount_value);
                itemDiscount =
                    entry.discount_type === 'percent'
                        ? Math.round((gross * Math.min(100, dVal)) / 100)
                        : dVal;
            } else {
                itemDiscount = (product.promo_discount || 0) * entry.qty * conversionQty;
            }
            itemDiscount = Math.min(gross, itemDiscount);

            return {
                entry,
                product,
                qty: entry.qty,
                conversionQty,
                unitPrice,
                unitLabel,
                gross,
                itemDiscount,
                net: Math.max(0, gross - itemDiscount),
            };
        })
        .filter(Boolean);
});

const totalCartQty = computed(() => cartDetails.value.reduce((s, i) => s + i.qty, 0));
const grossSubtotal = computed(() => cartDetails.value.reduce((s, i) => s + i.gross, 0));
const totalItemDiscount = computed(() => cartDetails.value.reduce((s, i) => s + i.itemDiscount, 0));
const subtotalAfterItem = computed(() => Math.max(0, grossSubtotal.value - totalItemDiscount.value));

const trxDiscountValue = computed(() => parseRupiahInput(discountValueNum.value));
const trxDiscountAmount = computed(() => {
    if (discountType.value === 'percent') {
        return Math.round((subtotalAfterItem.value * Math.min(100, trxDiscountValue.value)) / 100);
    }
    return Math.min(subtotalAfterItem.value, trxDiscountValue.value);
});

const totalAllDiscount = computed(() => totalItemDiscount.value + trxDiscountAmount.value);
const taxableAmount = computed(() => Math.max(0, subtotalAfterItem.value - trxDiscountAmount.value));
const taxAmount = computed(() => Math.round((taxableAmount.value * props.settings.tax_rate) / 100));
const grandTotal = computed(() => taxableAmount.value + taxAmount.value);

const cashPaid = computed(() => (paymentMethodCode.value === 'cash' ? parseRupiahInput(cashAmountNum.value) : grandTotal.value));
const changeAmount = computed(() => Math.max(0, cashPaid.value - grandTotal.value));
const isCashShort = computed(() => paymentMethodCode.value === 'cash' && cartDetails.value.length > 0 && cashPaid.value < grandTotal.value);

const quickDenominations = computed(() => {
    const total = grandTotal.value;
    if (total <= 0) return [50000, 100000, 200000];
    const base = Math.ceil(total / 50000) * 50000;
    return [base, base + 50000, base + 100000];
});

const thumbClassMap = {
    amber: 'bg-amber-100 text-amber-800',
    blue: 'bg-blue-100 text-blue-700',
    teal: 'bg-teal-100 text-teal-700',
    rose: 'bg-rose-100 text-rose-700',
    emerald: 'bg-green-100 text-green-700',
    violet: 'bg-violet-100 text-violet-700',
};

function getCartQty(variantId) {
    return cart.value.find((i) => i.variant_id === variantId)?.qty || 0;
}

function addToCart(product) {
    if (!product.is_bundle && product.stock <= 0) return;
    const found = cart.value.find((i) => i.variant_id === product.id);
    if (found) {
        if (!product.is_bundle && found.qty >= product.stock) return;
        found.qty += 1;
    } else {
        cart.value.push({
            variant_id: product.id,
            qty: 1,
            product_unit_id: null,
            manual_discount: false,
            discount_type: 'nominal',
            discount_value: Number(product.promo_discount || 0),
        });
    }
    if (parseRupiahInput(cashAmountNum.value) === 0) {
        cashAmountNum.value = grandTotal.value;
    }
}

function changeCartUnit(entry, rawVal) {
    entry.product_unit_id = rawVal ? Number(rawVal) : null;
}

function onManualItemDiscountChange(entry, val) {
    entry.manual_discount = true;
    entry.discount_value = val;
}

function toggleItemDiscountType(entry) {
    entry.manual_discount = true;
    entry.discount_type = entry.discount_type === 'percent' ? 'nominal' : 'percent';
}

function updateQty(variantId, delta) {
    const idx = cart.value.findIndex((i) => i.variant_id === variantId);
    if (idx === -1) return;
    const product = props.products.find((p) => p.id === variantId);
    const next = cart.value[idx].qty + delta;
    if (next <= 0) {
        cart.value.splice(idx, 1);
        return;
    }
    if (product && !product.is_bundle && next > product.stock) return;
    cart.value[idx].qty = next;
}

function removeFromCart(variantId) {
    cart.value = cart.value.filter((i) => i.variant_id !== variantId);
}

function clearCart() {
    cart.value = [];
    discountValueNum.value = 0;
    cashAmountNum.value = 0;
}

function handleBarcodeEnter() {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return;
    const exact = props.products.find((p) => p.barcode.toLowerCase() === q || p.sku.toLowerCase() === q);
    const target = exact || (filteredProducts.value.length === 1 ? filteredProducts.value[0] : null);
    if (target) {
        addToCart(target);
        searchQuery.value = '';
    }
}

function submitCheckout() {
    if (!props.shift) {
        activeModal.value = 'shift';
        return;
    }
    if (cartDetails.value.length === 0 || isCashShort.value || isSubmitting.value) return;

    isSubmitting.value = true;
    router.post(
        '/pos/checkout',
        {
            client_uuid: clientUuid.value,
            customer_id: selectedCustomerId.value,
            items: cart.value.map((i) => ({
                variant_id: i.variant_id,
                qty: i.qty,
                product_unit_id: i.product_unit_id || null,
                ...(i.manual_discount
                    ? {
                          discount_type: i.discount_type || 'nominal',
                          discount_value: parseRupiahInput(i.discount_value),
                      }
                    : {}),
            })),
            trx_discount_type: discountType.value,
            trx_discount_value: trxDiscountValue.value,
            payment_method_code: paymentMethodCode.value,
            paid_amount: cashPaid.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
            },
        }
    );
}

function submitHold() {
    if (cart.value.length === 0) {
        activeModal.value = 'hold';
        return;
    }
    router.post(
        '/pos/hold',
        {
            customer_id: selectedCustomerId.value,
            items: cart.value,
            trx_discount_type: discountType.value,
            trx_discount_value: trxDiscountValue.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                cart.value = [];
                discountValueNum.value = 0;
                selectedCustomerId.value = null;
            },
        }
    );
}

function resumeHold(held) {
    cart.value = (held.payload?.items || []).map((i) => ({ ...i }));
    selectedCustomerId.value = held.customer_id;
    discountType.value = held.payload?.trx_discount_type || 'nominal';
    discountValueNum.value = Number(held.payload?.trx_discount_value || 0);
    router.delete(`/pos/hold/${held.id}`, { preserveScroll: true });
    activeModal.value = null;
}

function deleteHold(id) {
    router.delete(`/pos/hold/${id}`, { preserveScroll: true });
}

function submitOpenShift() {
    router.post(
        '/pos/shifts/open',
        { opening_cash: parseRupiahInput(openingCashNum.value) },
        {
            preserveScroll: true,
            onSuccess: () => {
                activeModal.value = null;
            },
        }
    );
}

function submitCloseShift() {
    router.post(
        '/pos/shifts/close',
        { closing_cash: parseRupiahInput(closingCashNum.value) },
        {
            preserveScroll: true,
            onSuccess: () => {
                closingCashNum.value = '';
                activeModal.value = null;
            },
        }
    );
}

function openVoidModal(invoiceNo = '') {
    voidInvoice.value = invoiceNo || props.recentSales[0]?.invoice_number || '';
    voidPin.value = '';
    voidReason.value = '';
    voidMode.value = 'void';
    activeModal.value = 'void';
}

function submitVoidOrRefund() {
    const endpoint = voidMode.value === 'refund' ? '/pos/refund' : '/pos/void';
    const payload = {
        invoice_number: voidInvoice.value,
        pin: voidPin.value,
        reason: voidReason.value,
    };
    if (voidMode.value === 'refund' && refundItemSelection.value.length > 0) {
        payload.items = refundItemSelection.value
            .filter((r) => r.selected && Number(r.qty) > 0)
            .map((r) => ({ sale_item_id: r.sale_item_id, qty: Number(r.qty) }));
    }
    router.post(endpoint, payload, {
        preserveScroll: true,
        onSuccess: () => {
            activeModal.value = null;
        },
    });
}

function openReceiptPreview(sale) {
    activeReceipt.value = sale;
    activeModal.value = 'receipt';
}

async function triggerQzPrint() {
    if (!activeReceipt.value) return;
    await printViaQzTray(activeReceipt.value, props.settings);
    activeModal.value = null;
}

const shiftDiff = computed(() => {
    if (!props.shift || closingCashNum.value === '' || closingCashNum.value === null) return null;
    return parseRupiahInput(closingCashNum.value) - props.shift.expected_cash;
});

function handleKeydown(e) {
    if (e.key === 'F2') {
        e.preventDefault();
        searchInputRef.value?.focus();
    } else if (e.key === 'F4') {
        e.preventDefault();
        submitHold();
    } else if (e.key === 'F8') {
        e.preventDefault();
        discountInputRef.value?.focus();
    } else if (e.key === 'F9') {
        e.preventDefault();
        submitCheckout();
    } else if (e.key === 'Escape' && activeModal.value) {
        activeModal.value = null;
    }
}

onMounted(() => window.addEventListener('keydown', handleKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <Head title="Kasir (POS)" />

    <PosLayout
        active-menu="pos"
        title="NusaPOS Retail"
        :subtitle="`Terminal Kasir · ${settings.date_display}`"
        :full-bleed="true"
    >
        <template #topbar-actions>
            <button
                type="button"
                @click="activeModal = 'shift'"
                :class="[
                    'inline-flex cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 text-[11px] font-semibold transition-colors md:gap-2 md:px-3 md:py-1.5 md:text-xs',
                    shift
                        ? 'border-green-200 bg-green-100 text-green-700 hover:bg-green-200/70'
                        : 'border-red-200 bg-red-100 text-red-700 hover:bg-red-200/70',
                ]"
            >
                <span :class="['h-2 w-2 rounded-full', shift ? 'bg-green-700' : 'bg-red-600']" />
                <span>{{ shift ? shift.code : 'Buka Shift' }}</span>
                <span class="hidden sm:inline">{{ shift ? '· Aktif' : '' }}</span>
            </button>

            <div
                class="hidden items-center gap-2 whitespace-nowrap rounded-full border border-green-200 bg-green-100 px-3 py-1.5 text-xs font-semibold text-green-700 xl:inline-flex"
                :title="`Printer: ${settings.printer_name}`"
            >
                <span class="h-2 w-2 rounded-full bg-green-700" />
                <span>QZ Tray {{ settings.paper_width }}mm</span>
            </div>

            <button
                type="button"
                @click="activeModal = 'hold'"
                class="inline-flex cursor-pointer items-center gap-1 whitespace-nowrap rounded-full border border-amber-200 bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-200/70 tabular-nums md:px-3 md:py-1.5 md:text-xs"
            >
                Hold ({{ heldSales.length }})
            </button>
        </template>

        <main class="flex h-full flex-col overflow-hidden lg:grid lg:grid-cols-[1fr_380px] xl:grid-cols-[1fr_420px]">
            <!-- MOBILE & TABLET PORTRAIT PANE SWITCHER (< lg) -->
            <div class="grid shrink-0 grid-cols-2 gap-1.5 border-b border-slate-200 bg-white px-3 py-2 lg:hidden">
                <button
                    type="button"
                    @click="mobilePane = 'catalog'"
                    :class="[
                        'h-9 cursor-pointer rounded-lg text-xs font-extrabold transition-colors',
                        mobilePane === 'catalog'
                            ? 'bg-teal-600 text-white'
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200',
                    ]"
                >
                    Katalog Produk ({{ filteredProducts.length }})
                </button>
                <button
                    type="button"
                    @click="mobilePane = 'cart'"
                    :class="[
                        'h-9 cursor-pointer rounded-lg text-xs font-extrabold transition-colors tabular-nums',
                        mobilePane === 'cart'
                            ? 'bg-teal-600 text-white'
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200',
                    ]"
                >
                    Keranjang ({{ totalCartQty }}) · {{ formatRupiah(grandTotal) }}
                </button>
            </div>

            <!-- LEFT PANE: CATALOG -->
            <section
                :class="[
                    'min-h-0 flex-1 flex-col gap-2.5 overflow-hidden px-3 py-3 sm:gap-3.5 sm:px-5 sm:py-4.5',
                    mobilePane === 'catalog' ? 'flex' : 'hidden lg:flex',
                ]"
            >
                <!-- Shift warning banner if closed -->
                <div
                    v-if="!shift"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-[10px] border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900 sm:px-4 sm:py-2.5"
                >
                    <span><strong>Shift kasir belum dibuka.</strong> Buka shift sebelum memproses transaksi.</span>
                    <button
                        type="button"
                        @click="activeModal = 'shift'"
                        class="cursor-pointer rounded-md bg-teal-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-teal-700"
                    >
                        Buka Shift
                    </button>
                </div>

                <!-- Search & Quick Actions -->
                <div class="flex flex-wrap items-center gap-2 sm:flex-nowrap sm:gap-2.5">
                    <div class="relative flex w-full flex-1 items-center">
                        <svg class="pointer-events-none absolute left-3.5 h-4.5 w-4.5 text-teal-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 5v14M7 5v14M11 5v14M15 5v14M19 5v14M21 5v14" />
                        </svg>
                        <input
                            ref="searchInputRef"
                            v-model="searchQuery"
                            type="search"
                            placeholder="Scan barcode atau cari nama / SKU..."
                            @keydown.enter.prevent="handleBarcodeEnter"
                            class="h-10 w-full rounded-[10px] border-[1.5px] border-slate-300 bg-white pr-3 pl-10 text-sm font-medium outline-none placeholder:text-slate-400 focus:border-teal-600 focus:ring-3 focus:ring-teal-600/15 sm:h-[46px] sm:pr-32 sm:pl-11"
                        />
                        <span class="pointer-events-none absolute right-3 hidden rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-600 sm:inline-flex">
                            Scanner Siap · F2
                        </span>
                    </div>

                    <div class="flex w-full gap-2 sm:w-auto">
                        <button
                            type="button"
                            @click="activeModal = 'history'"
                            class="inline-flex h-10 flex-1 cursor-pointer items-center justify-center gap-1.5 whitespace-nowrap rounded-[10px] border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 hover:border-teal-600 hover:bg-teal-50 hover:text-teal-700 sm:h-[46px] sm:flex-initial sm:px-3.5 sm:text-[13px]"
                        >
                            <span>Riwayat / Struk</span>
                        </button>

                        <button
                            type="button"
                            @click="openVoidModal()"
                            class="inline-flex h-10 flex-1 cursor-pointer items-center justify-center gap-1.5 whitespace-nowrap rounded-[10px] border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 hover:border-teal-600 hover:bg-teal-50 hover:text-teal-700 sm:h-[46px] sm:flex-initial sm:px-3.5 sm:text-[13px]"
                        >
                            <span>Void / Refund</span>
                        </button>
                    </div>
                </div>

                <!-- Categories -->
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 sm:gap-2">
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            type="button"
                            @click="selectedCategory = cat.id"
                            :class="[
                                'inline-flex cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-full border px-3 py-1 text-xs font-semibold transition-colors sm:px-3.5 sm:py-1.5 sm:text-[12.5px]',
                                selectedCategory === cat.id
                                    ? 'border-teal-600 bg-teal-600 text-white'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900',
                            ]"
                        >
                            <span>{{ cat.label }}</span>
                            <span
                                :class="[
                                    'rounded-full px-1.5 py-0.5 text-[10.5px] tabular-nums sm:text-[11px]',
                                    selectedCategory === cat.id ? 'bg-white/20 text-white' : 'bg-[#f3f5f8] text-slate-600',
                                ]"
                            >
                                {{ categoryCounts[cat.id] || 0 }}
                            </span>
                        </button>
                    </div>
                    <span class="hidden whitespace-nowrap text-xs text-slate-500 sm:inline">
                        Stok <strong class="text-slate-700">{{ activeBranch?.code }}</strong>
                    </span>
                </div>

                <!-- Product Grid -->
                <div class="min-h-0 flex-1 overflow-y-auto pr-0.5 sm:pr-1">
                    <div class="grid grid-cols-2 gap-2.5 pb-4 sm:grid-cols-3 sm:gap-3 lg:grid-cols-2 xl:grid-cols-[repeat(auto-fill,minmax(185px,1fr))]">
                        <article
                            v-for="product in filteredProducts"
                            :key="product.id"
                            @click="addToCart(product)"
                            :class="[
                                'flex flex-col gap-1.5 rounded-[12px] border-[1.5px] p-2.5 transition-all sm:gap-2 sm:rounded-[14px] sm:p-3.5',
                                !product.is_bundle && product.stock <= 0
                                    ? 'cursor-not-allowed border-slate-200 bg-white opacity-60'
                                    : getCartQty(product.id) > 0
                                      ? 'cursor-pointer border-teal-600 bg-teal-50 hover:-translate-y-0.5'
                                      : 'cursor-pointer border-slate-200 bg-white hover:-translate-y-0.5 hover:border-teal-600',
                            ]"
                        >
                            <div class="flex items-start justify-between gap-1.5">
                                <div :class="['grid h-9 w-9 shrink-0 place-items-center rounded-lg text-sm font-bold sm:h-11 sm:w-11 sm:rounded-[10px] sm:text-lg', thumbClassMap[product.thumb_color] || 'bg-slate-100 text-slate-700']">
                                    {{ product.thumb_code }}
                                </div>

                                <span v-if="product.is_bundle" class="rounded-full border border-violet-200 bg-violet-100 px-1.5 py-0.5 text-[10px] font-bold text-violet-700 sm:px-2 sm:text-[10.5px]">
                                    Bundling
                                </span>
                                <span v-else-if="product.stock <= 0" class="rounded-full border border-red-200 bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-700 sm:px-2 sm:text-[10.5px]">
                                    Habis: 0
                                </span>
                                <span v-else-if="product.stock <= product.min_stock" class="rounded-full border border-amber-200 bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 tabular-nums sm:px-2 sm:text-[10.5px]">
                                    Menipis: {{ product.stock }}
                                </span>
                                <span v-else class="rounded-full border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 tabular-nums sm:px-2 sm:text-[10.5px]">
                                    Stok: {{ product.stock }} {{ product.unit }}
                                </span>
                            </div>

                            <div class="flex flex-1 flex-col gap-0.5">
                                <span class="truncate font-mono text-[10px] text-slate-400 sm:text-[10.5px]">{{ product.barcode }} · {{ product.sku }}</span>
                                <h3 class="line-clamp-2 text-xs leading-snug font-bold text-slate-900 sm:text-[13.5px]">{{ product.name }}</h3>
                                <div class="mt-0.5 flex flex-wrap gap-1">
                                    <span class="rounded bg-[#f3f5f8] px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">{{ product.variant }}</span>
                                    <span v-if="product.unit_note" class="hidden rounded bg-[#f3f5f8] px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 sm:inline-block">{{ product.unit_note }}</span>
                                    <span v-if="product.promo_label" class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">{{ product.promo_label }}</span>
                                </div>
                            </div>

                            <div class="flex items-end justify-between border-t border-dashed border-slate-200 pt-1.5 sm:pt-2">
                                <div>
                                    <div class="text-[13.5px] font-extrabold text-teal-700 tabular-nums sm:text-[15px]">{{ formatRupiah(product.price) }}</div>
                                    <div class="text-[10px] font-medium text-slate-400 sm:text-[11px]">/ {{ product.unit }}</div>
                                </div>
                                <span v-if="getCartQty(product.id) > 0" class="rounded-full bg-teal-600 px-2 py-0.5 text-[10.5px] font-extrabold text-white tabular-nums">
                                    {{ getCartQty(product.id) }}x
                                </span>
                            </div>
                        </article>
                    </div>
                </div>

                <!-- Mobile Floating Sticky Checkout Bar (< lg) -->
                <div
                    v-if="cartDetails.length > 0"
                    class="flex shrink-0 items-center justify-between gap-3 rounded-xl bg-slate-900 px-3.5 py-2.5 text-white shadow-md lg:hidden"
                >
                    <div class="tabular-nums">
                        <div class="text-[11px] text-slate-300">{{ totalCartQty }} item di keranjang</div>
                        <div class="text-sm font-extrabold text-teal-300">{{ formatRupiah(grandTotal) }}</div>
                    </div>
                    <button
                        type="button"
                        @click="mobilePane = 'cart'"
                        class="h-9 cursor-pointer rounded-lg bg-teal-600 px-4 text-xs font-extrabold text-white hover:bg-teal-500"
                    >
                        Keranjang &amp; Bayar &rarr;
                    </button>
                </div>

                <!-- Desktop Shortcuts -->
                <div class="hidden items-center justify-between gap-3 rounded-[10px] border border-slate-200 bg-white px-3.5 py-2 text-[11.5px] text-slate-600 md:flex">
                    <div class="flex flex-wrap items-center gap-3.5">
                        <span><kbd class="rounded border border-slate-300 bg-[#f3f5f8] px-1.5 py-0.5 font-mono text-[10.5px] font-bold text-slate-900">F2</kbd> Scan / Cari</span>
                        <span><kbd class="rounded border border-slate-300 bg-[#f3f5f8] px-1.5 py-0.5 font-mono text-[10.5px] font-bold text-slate-900">F4</kbd> Hold</span>
                        <span><kbd class="rounded border border-slate-300 bg-[#f3f5f8] px-1.5 py-0.5 font-mono text-[10.5px] font-bold text-slate-900">F8</kbd> Diskon</span>
                        <span><kbd class="rounded border border-slate-300 bg-[#f3f5f8] px-1.5 py-0.5 font-mono text-[10.5px] font-bold text-slate-900">F9</kbd> Bayar &amp; Cetak</span>
                    </div>
                    <span class="font-mono text-slate-400">UUID: {{ clientUuid.slice(0, 13) }}</span>
                </div>
            </section>

            <!-- RIGHT PANE: CART -->
            <aside
                :class="[
                    'min-h-0 flex-1 flex-col overflow-hidden bg-white lg:flex lg:h-full lg:border-l lg:border-slate-200',
                    mobilePane === 'cart' ? 'flex' : 'hidden lg:flex',
                ]"
            >
                <div class="flex flex-col gap-2 border-b border-slate-200 px-3.5 py-2.5 sm:gap-2.5 sm:px-4.5 sm:py-3.5">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex flex-col">
                            <span class="text-[10.5px] font-bold tracking-wider text-slate-400 uppercase">Nomor Struk Berikutnya</span>
                            <span class="font-mono text-xs font-bold text-slate-900 sm:text-[13px]">{{ nextInvoicePreview }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                @click="mobilePane = 'catalog'"
                                class="inline-flex h-8 cursor-pointer items-center rounded-md border border-teal-600 bg-teal-50 px-2.5 text-[11.5px] font-bold text-teal-700 lg:hidden"
                            >
                                + Produk
                            </button>
                            <button type="button" @click="submitHold" class="inline-flex h-8 cursor-pointer items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2.5 text-[11.5px] font-semibold text-slate-600 hover:bg-slate-100">
                                Hold
                            </button>
                            <button type="button" @click="clearCart" class="inline-flex h-8 cursor-pointer items-center rounded-md border border-slate-200 bg-slate-50 px-2.5 text-[11.5px] font-semibold text-slate-600 hover:border-red-300 hover:bg-red-50 hover:text-red-600">
                                Kosongkan
                            </button>
                        </div>
                    </div>

                    <div class="rounded-[10px] border border-slate-200 bg-slate-50 px-3 py-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[12.5px]">
                                <strong class="text-slate-900">{{ activeCustomer?.name }}</strong>
                                <span class="ml-1 text-[11px] text-slate-400">{{ activeCustomer?.label }}</span>
                            </span>
                            <button type="button" @click="showCustomerPicker = !showCustomerPicker" class="cursor-pointer text-[11.5px] font-bold text-teal-700 hover:underline">
                                {{ showCustomerPicker ? 'Tutup' : 'Pilih Pelanggan' }}
                            </button>
                        </div>
                        <div v-if="showCustomerPicker" class="mt-2 flex flex-wrap gap-1.5 border-t border-slate-200 pt-2">
                            <button
                                v-for="cust in customers"
                                :key="String(cust.id)"
                                type="button"
                                @click="selectedCustomerId = cust.id; showCustomerPicker = false"
                                :class="[
                                    'cursor-pointer rounded-md border px-2 py-1 text-[11px] font-semibold',
                                    selectedCustomerId === cust.id ? 'border-teal-600 bg-teal-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-teal-600',
                                ]"
                            >
                                {{ cust.name }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cart Items -->
                <div class="flex flex-1 flex-col gap-2.5 overflow-y-auto px-4.5 py-3">
                    <div v-if="cartDetails.length === 0" class="grid flex-1 place-items-center rounded-[10px] border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                        <div>
                            <p class="text-sm font-bold text-slate-700">Keranjang Kosong</p>
                            <p class="mt-1 text-xs text-slate-500">Scan barcode atau klik produk di katalog untuk memulai transaksi.</p>
                        </div>
                    </div>

                    <div v-for="item in cartDetails" :key="item.product.id" class="flex flex-col gap-2 rounded-[10px] border border-slate-200 bg-white px-3 py-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="text-[13px] font-bold text-slate-900">{{ item.product.name }}</div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11.5px] text-slate-500 tabular-nums">
                                    <span>{{ formatRupiah(item.unitPrice) }} / {{ item.unitLabel }}</span>
                                    <span v-if="item.itemDiscount > 0" class="rounded bg-red-100 px-1.5 py-0.5 text-[10.5px] font-bold text-red-700">
                                        Diskon −{{ formatRupiah(item.itemDiscount) }}
                                    </span>
                                </div>
                            </div>
                            <button type="button" @click="removeFromCart(item.product.id)" class="grid h-6 w-6 cursor-pointer place-items-center rounded text-slate-400 hover:bg-red-100 hover:text-red-600">&times;</button>
                        </div>

                        <!-- Pilih Satuan Bertingkat & Diskon Manual per Item -->
                        <div class="grid grid-cols-2 gap-1.5">
                            <select
                                v-if="item.product.product_units && item.product.product_units.length > 0"
                                :value="item.entry.product_unit_id ?? ''"
                                @change="changeCartUnit(item.entry, $event.target.value)"
                                aria-label="Pilih satuan"
                                class="h-7 rounded border border-slate-300 bg-slate-50 px-1.5 text-[11px] font-semibold text-slate-700 outline-none focus:border-teal-600"
                            >
                                <option value="">{{ item.product.unit }} ({{ formatRupiah(item.product.price) }})</option>
                                <option v-for="pu in item.product.product_units" :key="pu.id" :value="pu.id">
                                    {{ pu.label }} — {{ formatRupiah(pu.sell_price) }}
                                </option>
                            </select>
                            <div v-else class="flex h-7 items-center rounded border border-slate-200 bg-slate-50 px-2 text-[11px] text-slate-500">
                                Satuan: {{ item.product.unit }}
                            </div>

                            <div class="flex h-7 overflow-hidden rounded border border-slate-300 bg-white">
                                <RupiahInput
                                    v-if="item.entry.discount_type !== 'percent'"
                                    :model-value="item.entry.discount_value"
                                    @update:model-value="onManualItemDiscountChange(item.entry, $event)"
                                    placeholder="Rp. 0"
                                    aria-label="Diskon item"
                                    input-class="min-w-0 flex-1 border-none px-2 text-[11px] font-semibold outline-none tabular-nums"
                                />
                                <input
                                    v-else
                                    :value="item.entry.discount_value"
                                    @input="onManualItemDiscountChange(item.entry, $event.target.value)"
                                    type="number"
                                    min="0"
                                    max="100"
                                    placeholder="0"
                                    aria-label="Diskon item persen"
                                    class="min-w-0 flex-1 border-none px-2 text-[11px] font-semibold outline-none tabular-nums"
                                />
                                <button
                                    type="button"
                                    @click="toggleItemDiscountType(item.entry)"
                                    title="Klik untuk ganti Rp / %"
                                    class="cursor-pointer bg-teal-600 px-2 text-[10.5px] font-bold text-white"
                                >
                                    {{ item.entry.discount_type === 'percent' ? '%' : 'Rp' }}
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="inline-flex items-center overflow-hidden rounded-md border border-slate-300 bg-slate-50">
                                <button type="button" @click="updateQty(item.product.id, -1)" class="grid h-[26px] w-7 cursor-pointer place-items-center font-bold hover:bg-slate-200">−</button>
                                <span class="h-[26px] min-w-[30px] border-x border-slate-200 bg-white text-center text-[12.5px] leading-[26px] font-bold tabular-nums">{{ item.qty }}</span>
                                <button type="button" @click="updateQty(item.product.id, 1)" class="grid h-[26px] w-7 cursor-pointer place-items-center font-bold hover:bg-slate-200">+</button>
                            </div>
                            <div class="text-right tabular-nums">
                                <span v-if="item.itemDiscount > 0" class="block text-[11px] text-slate-400 line-through">{{ formatRupiah(item.gross) }}</span>
                                <span class="text-[13.5px] font-extrabold text-slate-900">{{ formatRupiah(item.net) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Checkout Footer -->
                <div class="flex flex-col gap-2.5 border-t border-slate-200 bg-slate-50 px-4.5 pt-3.5 pb-4">
                    <div class="flex items-center gap-2">
                        <label for="trx-discount" class="whitespace-nowrap text-xs font-semibold text-slate-600">Diskon Trx</label>
                        <div class="flex h-8 flex-1 overflow-hidden rounded-md border border-slate-300 bg-white">
                            <RupiahInput
                                v-if="discountType === 'nominal'"
                                ref="discountInputRef"
                                id="trx-discount"
                                v-model="discountValueNum"
                                aria-label="Nominal diskon transaksi"
                                input-class="min-w-0 flex-1 border-none px-2.5 text-[12.5px] font-semibold outline-none tabular-nums"
                            />
                            <input
                                v-else
                                ref="discountInputRef"
                                id="trx-discount"
                                v-model.number="discountValueNum"
                                type="number"
                                min="0"
                                max="100"
                                class="min-w-0 flex-1 border-none px-2.5 text-[12.5px] font-semibold outline-none tabular-nums"
                                aria-label="Persen diskon transaksi"
                            />
                            <div class="flex border-l border-slate-200">
                                <button type="button" @click="discountType = 'nominal'" :class="['cursor-pointer px-2.5 text-[11.5px] font-bold', discountType === 'nominal' ? 'bg-teal-600 text-white' : 'bg-slate-50 text-slate-600']">Rp</button>
                                <button type="button" @click="discountType = 'percent'" :class="['cursor-pointer px-2.5 text-[11.5px] font-bold', discountType === 'percent' ? 'bg-teal-600 text-white' : 'bg-slate-50 text-slate-600']">%</button>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1 text-[12.5px] tabular-nums">
                        <div class="flex justify-between text-slate-600"><span>Subtotal ({{ totalCartQty }} item)</span><span>{{ formatRupiah(grossSubtotal) }}</span></div>
                        <div v-if="totalAllDiscount > 0" class="flex justify-between text-red-600"><span>Total Diskon</span><span>−{{ formatRupiah(totalAllDiscount) }}</span></div>
                        <div class="flex justify-between text-slate-600"><span>PPN ({{ settings.tax_rate }}%)</span><span>{{ formatRupiah(taxAmount) }}</span></div>
                        <div class="mt-1 flex items-baseline justify-between border-t-[1.5px] border-dashed border-slate-300 pt-2">
                            <span class="text-[13.5px] font-bold text-slate-900">Total Tagihan</span>
                            <span class="text-[22px] font-extrabold text-teal-700">{{ formatRupiah(grandTotal) }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-1.5">
                        <button
                            v-for="pm in paymentMethods"
                            :key="pm.code"
                            type="button"
                            @click="paymentMethodCode = pm.code"
                            :class="[
                                'flex cursor-pointer flex-col items-center gap-0.5 rounded-md border-[1.5px] px-2 py-1.5 text-center',
                                paymentMethodCode === pm.code ? 'border-teal-600 bg-teal-50 text-teal-700' : 'border-slate-200 bg-white text-slate-900',
                            ]"
                        >
                            <span class="text-xs font-bold">{{ pm.name }}</span>
                            <span class="text-[9.5px] font-semibold text-slate-400">{{ pm.opens_drawer ? 'Buka Laci' : 'Non-Tunai' }}</span>
                        </button>
                    </div>

                    <div v-if="paymentMethodCode === 'cash'" class="flex flex-col gap-1.5">
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex flex-col rounded-md border border-slate-300 bg-white px-2.5 py-1.5">
                                <span class="text-[10.5px] font-semibold text-slate-400">Uang Diterima</span>
                                <RupiahInput
                                    v-model="cashAmountNum"
                                    input-class="w-full border-none bg-transparent text-sm font-extrabold text-slate-900 outline-none tabular-nums"
                                />
                            </label>
                            <div :class="['flex flex-col rounded-md border px-2.5 py-1.5', isCashShort ? 'border-red-200 bg-red-100 text-red-700' : 'border-green-200 bg-green-100 text-green-700']">
                                <span class="text-[10.5px] font-semibold">{{ isCashShort ? 'Kurang Bayar' : 'Kembalian' }}</span>
                                <strong class="text-sm font-extrabold tabular-nums">{{ isCashShort ? formatRupiah(grandTotal - cashPaid) : formatRupiah(changeAmount) }}</strong>
                            </div>
                        </div>

                        <div class="flex gap-1.5 tabular-nums">
                            <button type="button" @click="cashAmountNum = grandTotal" class="flex-1 cursor-pointer rounded-md border border-slate-200 bg-white py-1 text-[11px] font-bold text-slate-600 hover:border-teal-600 hover:bg-teal-50 hover:text-teal-700">
                                Uang Pas
                            </button>
                            <button v-for="d in quickDenominations" :key="d" type="button" @click="cashAmountNum = d" class="flex-1 cursor-pointer rounded-md border border-slate-200 bg-white py-1 text-[11px] font-bold text-slate-600 hover:border-teal-600 hover:bg-teal-50 hover:text-teal-700">
                                {{ formatRupiah(d) }}
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-[1fr_auto] gap-2">
                        <button
                            type="button"
                            :disabled="isSubmitting || cartDetails.length === 0 || isCashShort"
                            @click="submitCheckout"
                            class="flex h-12 cursor-pointer flex-col items-center justify-center rounded-[10px] bg-teal-600 px-4 text-white transition-colors hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span class="text-sm font-extrabold">{{ isSubmitting ? 'Menyimpan...' : 'Bayar & Cetak Struk (F9)' }}</span>
                            <span class="text-[10px] opacity-90">Simpan ke DB · QZ Tray {{ settings.paper_width }}mm</span>
                        </button>
                        <button type="button" @click="submitHold" class="flex h-12 cursor-pointer items-center rounded-[10px] border-[1.5px] border-slate-300 bg-white px-3.5 text-[12.5px] font-bold text-slate-900 hover:border-amber-600 hover:bg-amber-100 hover:text-amber-800">
                            Hold (F4)
                        </button>
                    </div>
                </div>
            </aside>
        </main>

        <!-- MODAL: RECEIPT 58MM -->
        <div v-if="activeModal === 'receipt' && activeReceipt" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-3 sm:p-5" @click.self="activeModal = null">
            <div class="max-h-[90dvh] w-full max-w-[420px] overflow-y-auto rounded-[14px] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-4.5 py-3.5">
                    <h2 class="text-[15px] font-extrabold text-slate-900">Struk Thermal {{ settings.paper_width }}mm</h2>
                    <button type="button" @click="activeModal = null" class="cursor-pointer text-lg font-bold text-slate-500">&times;</button>
                </div>
                <div class="flex flex-col gap-3 p-4.5">
                    <div class="rounded-md border border-green-200 bg-green-100 px-3 py-2 text-xs text-green-800">
                        <strong>QZ Tray:</strong> {{ settings.printer_name }} · Laci kasir {{ settings.auto_open_drawer ? 'buka otomatis' : 'manual' }}.
                    </div>
                    <div class="mx-auto w-[264px] border border-dashed border-slate-300 bg-[#fffdf9] px-3.5 py-4 font-mono text-[11px] leading-snug text-slate-800 tabular-nums">
                        <div class="whitespace-pre-line text-center font-bold">{{ settings.receipt_header }}</div>
                        <hr class="my-2 border-t border-dashed border-slate-400" />
                        <div class="flex justify-between"><span>No:</span><span>{{ activeReceipt.invoice_number }}</span></div>
                        <div class="flex justify-between"><span>Tgl:</span><span>{{ activeReceipt.timestamp }}</span></div>
                        <div class="flex justify-between"><span>Kasir:</span><span>{{ activeReceipt.cashier_name }}</span></div>
                        <hr class="my-2 border-t border-dashed border-slate-400" />
                        <div v-for="(it, idx) in activeReceipt.items" :key="idx" class="mb-1">
                            <div class="font-bold">{{ it.product_name }}</div>
                            <div class="flex justify-between">
                                <span>{{ it.quantity }}x {{ formatRupiah(it.unit_price, false) }}</span>
                                <span>{{ formatRupiah(it.quantity * it.unit_price, false) }}</span>
                            </div>
                        </div>
                        <hr class="my-2 border-t border-dashed border-slate-400" />
                        <div class="flex justify-between"><span>Subtotal</span><span>{{ formatRupiah(activeReceipt.subtotal, false) }}</span></div>
                        <div v-if="activeReceipt.trx_discount_amount > 0" class="flex justify-between"><span>Diskon Trx</span><span>-{{ formatRupiah(activeReceipt.trx_discount_amount, false) }}</span></div>
                        <div class="flex justify-between"><span>PPN {{ activeReceipt.tax_rate }}%</span><span>{{ formatRupiah(activeReceipt.tax_amount, false) }}</span></div>
                        <div class="flex justify-between font-bold"><span>TOTAL</span><span>{{ formatRupiah(activeReceipt.grand_total) }}</span></div>
                        <hr class="my-2 border-t border-dashed border-slate-400" />
                        <div class="flex justify-between"><span>{{ activeReceipt.payment_method }}</span><span>{{ formatRupiah(activeReceipt.paid_total) }}</span></div>
                        <div class="flex justify-between font-bold"><span>KEMBALI</span><span>{{ formatRupiah(activeReceipt.change_amount) }}</span></div>
                        <hr class="my-2 border-t border-dashed border-slate-400" />
                        <div class="whitespace-pre-line text-center">{{ settings.receipt_footer }}</div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4.5 py-3">
                    <button type="button" @click="activeModal = null" class="h-9 cursor-pointer rounded-md border border-slate-300 bg-white px-3.5 text-xs font-bold">Tutup</button>
                    <button type="button" @click="triggerQzPrint" class="h-9 cursor-pointer rounded-md bg-teal-600 px-4 text-xs font-bold text-white hover:bg-teal-700">Cetak via QZ Tray</button>
                </div>
            </div>
        </div>

        <!-- MODAL: HOLD LIST -->
        <div v-if="activeModal === 'hold'" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-3 sm:p-5" @click.self="activeModal = null">
            <div class="max-h-[90dvh] w-full max-w-[440px] overflow-y-auto rounded-[14px] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-4.5 py-3.5">
                    <h2 class="text-[15px] font-extrabold text-slate-900">Transaksi Ditahan (Hold)</h2>
                    <button type="button" @click="activeModal = null" class="cursor-pointer text-lg font-bold text-slate-500">&times;</button>
                </div>
                <div class="flex flex-col gap-2.5 p-4.5">
                    <p v-if="heldSales.length === 0" class="py-6 text-center text-xs text-slate-500">Belum ada transaksi yang ditahan.</p>
                    <div v-for="h in heldSales" :key="h.id" class="flex items-center justify-between rounded-[10px] border border-slate-200 bg-slate-50 p-3">
                        <div>
                            <div class="text-[13px] font-bold text-slate-900">{{ h.customer_name }} · {{ h.total_items }} item</div>
                            <div class="font-mono text-[11px] text-slate-500 tabular-nums">{{ h.code }} · {{ h.time }} · {{ formatRupiah(h.estimated_total) }}</div>
                        </div>
                        <div class="flex gap-1.5">
                            <button type="button" @click="deleteHold(h.id)" class="h-8 cursor-pointer rounded-md border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-600 hover:text-red-600">Hapus</button>
                            <button type="button" @click="resumeHold(h)" class="h-8 cursor-pointer rounded-md bg-teal-600 px-3 text-xs font-bold text-white hover:bg-teal-700">Lanjutkan</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: HISTORY / REPRINT -->
        <div v-if="activeModal === 'history'" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-3 sm:p-5" @click.self="activeModal = null">
            <div class="max-h-[90dvh] w-full max-w-[600px] overflow-y-auto rounded-[14px] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-4.5 py-3.5">
                    <h2 class="text-[15px] font-extrabold text-slate-900">Riwayat Transaksi ({{ activeBranch?.code }})</h2>
                    <button type="button" @click="activeModal = null" class="cursor-pointer text-lg font-bold text-slate-500">&times;</button>
                </div>
                <div class="max-h-[420px] overflow-y-auto p-4.5">
                    <div v-if="recentSales.length === 0" class="py-8 text-center text-xs text-slate-500">Belum ada transaksi.</div>
                    <div v-else class="flex flex-col gap-2">
                        <div v-for="sale in recentSales" :key="sale.id" class="flex items-center justify-between rounded-[10px] border border-slate-200 bg-slate-50 px-3.5 py-2.5">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-bold text-slate-900">{{ sale.invoice_number }}</span>
                                    <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', sale.status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">{{ sale.status }}</span>
                                </div>
                                <div class="mt-0.5 text-[11.5px] text-slate-500 tabular-nums">
                                    {{ sale.timestamp }} · {{ sale.cashier_name }} · <strong class="text-slate-900">{{ formatRupiah(sale.grand_total) }}</strong>
                                </div>
                            </div>
                            <div class="flex gap-1.5">
                                <button type="button" @click="openReceiptPreview(sale)" class="h-8 cursor-pointer rounded-md border border-slate-300 bg-white px-2.5 text-xs font-bold text-slate-700 hover:border-teal-600 hover:text-teal-700">
                                    Cetak Ulang
                                </button>
                                <button v-if="sale.status === 'completed'" type="button" @click="openVoidModal(sale.invoice_number)" class="h-8 cursor-pointer rounded-md border border-red-200 bg-red-50 px-2.5 text-xs font-bold text-red-700 hover:bg-red-100">
                                    Void/Refund
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: VOID / REFUND PARSIAL (PIN ADMIN) -->
        <div v-if="activeModal === 'void'" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-3 sm:p-5" @click.self="activeModal = null">
            <div class="max-h-[90dvh] w-full max-w-[520px] overflow-y-auto rounded-[14px] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-4.5 py-3.5">
                    <h2 class="text-[15px] font-extrabold text-slate-900">Otorisasi Void / Refund Parsial</h2>
                    <button type="button" @click="activeModal = null" class="cursor-pointer text-lg font-bold text-slate-500">&times;</button>
                </div>
                <form @submit.prevent="submitVoidOrRefund" class="flex flex-col gap-3 p-4.5">
                    <div class="rounded-md border border-red-200 bg-red-100 px-3 py-2 text-xs text-red-900">
                        Memerlukan <strong>PIN Otorisasi Admin / Owner</strong>. Stok dikembalikan otomatis via <code>stock_movements</code> dan dicatat di <em>Activity Log</em>.
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" @click="voidMode = 'void'" :class="['h-9 cursor-pointer rounded-md border text-xs font-bold', voidMode === 'void' ? 'border-red-600 bg-red-600 text-white' : 'border-slate-300 bg-white text-slate-700']">Void Penuh</button>
                        <button type="button" @click="voidMode = 'refund'" :class="['h-9 cursor-pointer rounded-md border text-xs font-bold', voidMode === 'refund' ? 'border-red-600 bg-red-600 text-white' : 'border-slate-300 bg-white text-slate-700']">Refund Parsial / Item</button>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold">Nomor Struk</label>
                        <input v-model="voidInvoice" required class="h-10 rounded-md border border-slate-300 px-3 font-mono text-sm outline-none focus:border-teal-600" />
                    </div>

                    <!-- Partial Refund Item Picker -->
                    <div v-if="voidMode === 'refund' && refundItemSelection.length > 0" class="rounded-lg border border-slate-200 bg-slate-50 p-2.5 text-xs">
                        <div class="mb-2 flex items-center justify-between font-bold text-slate-700">
                            <span>Pilih Item &amp; Qty Retur</span>
                            <span class="text-red-700 tabular-nums">Est. Refund: {{ formatRupiah(estimatedPartialRefund) }}</span>
                        </div>
                        <div class="flex max-h-40 flex-col gap-1.5 overflow-y-auto">
                            <div v-for="r in refundItemSelection" :key="r.sale_item_id" class="flex items-center justify-between gap-2 rounded bg-white px-2.5 py-1.5 border border-slate-200">
                                <label class="flex flex-1 cursor-pointer items-center gap-2">
                                    <input v-model="r.selected" type="checkbox" />
                                    <span class="font-semibold text-slate-900">{{ r.product_name }}</span>
                                    <span class="text-[11px] text-slate-400">(beli {{ r.max_qty }} {{ r.unit_name }})</span>
                                </label>
                                <input
                                    v-model.number="r.qty"
                                    type="number"
                                    min="1"
                                    :max="r.max_qty"
                                    :disabled="!r.selected"
                                    class="h-7 w-16 rounded border border-slate-300 px-2 text-right font-bold tabular-nums disabled:opacity-40"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold">Alasan</label>
                        <input v-model="voidReason" required placeholder="Contoh: Salah scan barang / kemasan rusak" class="h-10 rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-teal-600" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-bold">PIN Admin</label>
                        <input v-model="voidPin" type="password" required placeholder="6 digit PIN Admin" class="h-10 rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-teal-600" />
                    </div>
                    <div class="mt-1 flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" @click="activeModal = null" class="h-9 cursor-pointer rounded-md border border-slate-300 bg-white px-3.5 text-xs font-bold">Batal</button>
                        <button type="submit" class="h-9 cursor-pointer rounded-md bg-red-600 px-4 text-xs font-bold text-white hover:bg-red-700">Verifikasi &amp; Proses</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: OPEN / CLOSE SHIFT -->
        <div v-if="activeModal === 'shift'" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-3 sm:p-5" @click.self="activeModal = null">
            <div class="max-h-[90dvh] w-full max-w-[440px] overflow-y-auto rounded-[14px] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-4.5 py-3.5">
                    <h2 class="text-[15px] font-extrabold text-slate-900">{{ shift ? `${shift.code} — ${activeBranch?.code}` : 'Buka Shift Kasir Baru' }}</h2>
                    <button type="button" @click="activeModal = null" class="cursor-pointer text-lg font-bold text-slate-500">&times;</button>
                </div>

                <!-- Buka Shift -->
                <form v-if="!shift" @submit.prevent="submitOpenShift" class="flex flex-col gap-3.5 p-4.5">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-900">Modal Awal Laci Kas</label>
                        <RupiahInput
                            v-model="openingCashNum"
                            required
                            input-class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm font-bold text-slate-900 outline-none focus:border-teal-600 tabular-nums"
                        />
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" @click="activeModal = null" class="h-9 cursor-pointer rounded-md border border-slate-300 bg-white px-3.5 text-xs font-bold">Batal</button>
                        <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 px-4 text-xs font-bold text-white hover:bg-teal-700">Buka Shift</button>
                    </div>
                </form>

                <!-- Tutup Shift -->
                <form v-else @submit.prevent="submitCloseShift" class="flex flex-col gap-3.5 p-4.5">
                    <div class="grid grid-cols-2 gap-2.5 tabular-nums">
                        <div class="rounded-[10px] border border-slate-200 bg-slate-50 p-2.5">
                            <div class="text-[11px] text-slate-500">Waktu Buka</div>
                            <div class="text-sm font-extrabold">{{ shift.opened_at }}</div>
                        </div>
                        <div class="rounded-[10px] border border-slate-200 bg-slate-50 p-2.5">
                            <div class="text-[11px] text-slate-500">Transaksi</div>
                            <div class="text-sm font-extrabold">{{ shift.transactions_count }} selesai</div>
                        </div>
                        <div class="rounded-[10px] border border-slate-200 bg-slate-50 p-2.5">
                            <div class="text-[11px] text-slate-500">Modal Awal</div>
                            <div class="text-sm font-extrabold">{{ formatRupiah(shift.opening_cash) }}</div>
                        </div>
                        <div class="rounded-[10px] border border-slate-200 bg-slate-50 p-2.5">
                            <div class="text-[11px] text-slate-500">Penjualan Tunai</div>
                            <div class="text-sm font-extrabold text-teal-700">{{ formatRupiah(shift.cash_sales) }}</div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs tabular-nums">
                        <span class="font-semibold text-slate-600">Estimasi Kas di Laci</span>
                        <strong class="text-sm font-extrabold text-slate-900">{{ formatRupiah(shift.expected_cash) }}</strong>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-900">Hitung Fisik Kas Laci</label>
                        <RupiahInput
                            v-model="closingCashNum"
                            required
                            :placeholder="formatRupiah(shift.expected_cash)"
                            input-class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm font-bold text-slate-900 outline-none focus:border-teal-600 tabular-nums"
                        />
                        <div v-if="shiftDiff !== null" class="flex justify-between text-xs font-bold tabular-nums">
                            <span class="text-slate-600">Selisih Kas:</span>
                            <span :class="shiftDiff === 0 ? 'text-green-700' : shiftDiff > 0 ? 'text-blue-700' : 'text-red-600'">
                                {{ shiftDiff === 0 ? 'Sesuai (Rp 0)' : formatRupiah(shiftDiff) }}
                            </span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" @click="activeModal = null" class="h-9 cursor-pointer rounded-md border border-slate-300 bg-white px-3.5 text-xs font-bold">Kembali</button>
                        <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 px-4 text-xs font-bold text-white hover:bg-teal-700">Tutup Shift</button>
                    </div>
                </form>
            </div>
        </div>
    </PosLayout>
</template>
