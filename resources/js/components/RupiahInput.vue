<script setup>
import { computed, ref } from 'vue';
import { formatRupiah, parseRupiahInput } from '../lib/format';

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: 0,
    },
    prefix: {
        type: String,
        default: 'Rp. ',
    },
    placeholder: {
        type: String,
        default: 'Rp. 0',
    },
    max: {
        type: Number,
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    id: {
        type: String,
        default: undefined,
    },
    ariaLabel: {
        type: String,
        default: undefined,
    },
    inputClass: {
        type: String,
        default: 'h-9 w-full rounded-md border border-slate-300 bg-white px-3 text-xs font-bold text-slate-900 outline-none focus:border-teal-600 tabular-nums',
    },
});

const emit = defineEmits(['update:modelValue']);
const inputEl = ref(null);

const displayValue = computed(() => {
    const numeric = parseRupiahInput(props.modelValue);
    if (numeric === 0 && (props.modelValue === '' || props.modelValue === null || props.modelValue === undefined)) {
        return '';
    }
    const formatted = formatRupiah(numeric, false);
    return `${props.prefix}${formatted}`;
});

function handleInput(event) {
    let numeric = parseRupiahInput(event.target.value);
    if (props.max !== null && numeric > props.max) {
        numeric = props.max;
    }
    emit('update:modelValue', numeric);

    // Pastikan teks di input selalu tersinkronisasi dengan format Rp. x.xxx.xxx
    const formatted = `${props.prefix}${formatRupiah(numeric, false)}`;
    if (event.target.value !== formatted) {
        event.target.value = formatted;
    }
}

function handleFocus(event) {
    const el = event.target;
    requestAnimationFrame(() => {
        el.setSelectionRange(el.value.length, el.value.length);
    });
}

defineExpose({
    focus: () => inputEl.value?.focus(),
    select: () => inputEl.value?.select(),
});
</script>

<template>
    <input
        ref="inputEl"
        :id="id"
        type="text"
        inputmode="numeric"
        :value="displayValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        :aria-label="ariaLabel"
        :class="inputClass"
        @input="handleInput"
        @focus="handleFocus"
    />
</template>
