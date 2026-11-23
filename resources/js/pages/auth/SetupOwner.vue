<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    branch_code: 'CAB01',
    branch_name: 'Toko Pusat',
    branch_address: '',
    branch_phone: '',
    tax_rate: 11,
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    pin: '',
});

function submit() {
    form.post('/setup');
}
</script>

<template>
    <Head title="Pendaftaran Owner Pertama" />

    <div class="flex min-h-screen flex-col items-center justify-between bg-[#f3f5f8] p-4 sm:p-6">
        <div class="flex w-full flex-1 items-center justify-center">
            <div class="w-full max-w-[540px] rounded-[16px] border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="mb-5 flex items-center gap-3.5">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-teal-600 text-lg font-extrabold text-white">
                        NP
                    </div>
                    <div>
                        <h1 class="text-lg font-extrabold tracking-tight text-slate-900">Pendaftaran Owner Awal</h1>
                        <p class="text-xs text-slate-500">Form ini hanya muncul sekali saat sistem belum memiliki pengguna.</p>
                    </div>
                </div>

                <div class="mb-5 rounded-lg border border-teal-200 bg-teal-50 px-3.5 py-2.5 text-xs text-teal-900">
                    Setelah akun <strong>Owner</strong> dibuat, halaman pendaftaran ini akan <strong>ditutup permanen</strong>. Penambahan Admin, Kasir, dan Gudang dilakukan dari menu <em>Setelan &rarr; Pengguna</em>.
                </div>

                <form @submit.prevent="submit" class="grid grid-cols-1 gap-3.5 text-xs sm:grid-cols-2">
                    <div class="border-b border-slate-200 pb-1 text-[11px] font-extrabold tracking-wider text-slate-400 uppercase sm:col-span-2">
                        1. Informasi Toko / Cabang Pertama
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="font-bold text-slate-700">Kode Cabang</label>
                        <input v-model="form.branch_code" required class="h-10 rounded-lg border border-slate-300 px-3 font-mono uppercase outline-none focus:border-teal-600" />
                        <p v-if="form.errors.branch_code" class="text-red-600">{{ form.errors.branch_code }}</p>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="font-bold text-slate-700">Nama Cabang / Toko</label>
                        <input v-model="form.branch_name" required class="h-10 rounded-lg border border-slate-300 px-3 outline-none focus:border-teal-600" />
                    </div>

                    <div class="flex flex-col gap-1 sm:col-span-2">
                        <label class="font-bold text-slate-700">Alamat Toko (untuk header struk)</label>
                        <input v-model="form.branch_address" placeholder="Jl. Contoh No. 1, Jakarta" class="h-10 rounded-lg border border-slate-300 px-3 outline-none focus:border-teal-600" />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="font-bold text-slate-700">Nomor Telepon Toko</label>
                        <input v-model="form.branch_phone" placeholder="(021) 555-0100" class="h-10 rounded-lg border border-slate-300 px-3 outline-none focus:border-teal-600" />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="font-bold text-slate-700">PPN Default (%)</label>
                        <input v-model.number="form.tax_rate" type="number" min="0" max="100" required class="h-10 rounded-lg border border-slate-300 px-3 tabular-nums outline-none focus:border-teal-600" />
                    </div>

                    <div class="mt-2 border-b border-slate-200 pb-1 text-[11px] font-extrabold tracking-wider text-slate-400 uppercase sm:col-span-2">
                        2. Akun Owner
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="font-bold text-slate-700">Nama Lengkap Owner</label>
                        <input v-model="form.name" required placeholder="Nama pemilik usaha" class="h-10 rounded-lg border border-slate-300 px-3 outline-none focus:border-teal-600" />
                        <p v-if="form.errors.name" class="text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="font-bold text-slate-700">Email Login</label>
                        <input v-model="form.email" type="email" required placeholder="owner@tokoanda.com" class="h-10 rounded-lg border border-slate-300 px-3 outline-none focus:border-teal-600" />
                        <p v-if="form.errors.email" class="text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="font-bold text-slate-700">Kata Sandi (min. 8 karakter)</label>
                        <input v-model="form.password" type="password" required minlength="8" class="h-10 rounded-lg border border-slate-300 px-3 outline-none focus:border-teal-600" />
                        <p v-if="form.errors.password" class="text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="font-bold text-slate-700">Konfirmasi Kata Sandi</label>
                        <input v-model="form.password_confirmation" type="password" required minlength="8" class="h-10 rounded-lg border border-slate-300 px-3 outline-none focus:border-teal-600" />
                    </div>

                    <div class="flex flex-col gap-1 sm:col-span-2">
                        <label class="font-bold text-slate-700">PIN Otorisasi Void &amp; Refund (4–8 digit angka)</label>
                        <input v-model="form.pin" type="password" inputmode="numeric" required placeholder="Contoh: 6 digit angka rahasia" class="h-10 rounded-lg border border-slate-300 px-3 font-mono outline-none focus:border-teal-600" />
                        <p v-if="form.errors.pin" class="text-red-600">{{ form.errors.pin }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="mt-2 h-11 cursor-pointer rounded-lg bg-teal-600 text-sm font-extrabold text-white hover:bg-teal-700 disabled:opacity-60 sm:col-span-2"
                    >
                        {{ form.processing ? 'Menyiapkan Sistem...' : 'Daftarkan Owner & Mulai Aplikasi' }}
                    </button>
                </form>
            </div>
        </div>

        <footer class="mt-6 text-center text-[11.5px] text-slate-500">
            <strong class="text-slate-700">NusaPOS Retail</strong> &copy; {{ new Date().getFullYear() }} · Setup Awal Satu Kali
        </footer>
    </div>
</template>
