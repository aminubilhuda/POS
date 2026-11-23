<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Masuk" />

    <div class="flex min-h-screen flex-col items-center justify-between bg-[#f3f5f8] p-4 sm:p-6">
        <div class="flex w-full flex-1 items-center justify-center">
            <div class="w-full max-w-[420px] rounded-[16px] border border-slate-200 bg-white p-6 shadow-sm sm:p-7">
                <div class="mb-6 flex items-center gap-3.5">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-teal-600 text-lg font-extrabold text-white">
                        NP
                    </div>
                    <div>
                        <h1 class="text-lg font-extrabold tracking-tight text-slate-900">NusaPOS Retail</h1>
                        <p class="text-xs text-slate-500">Aplikasi POS Retail Multi-Cabang</p>
                    </div>
                </div>

                <form @submit.prevent="submit" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="email" class="text-xs font-bold text-slate-700">Email Pengguna</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            placeholder="nama@tokoanda.com"
                            autocomplete="username"
                            class="h-11 rounded-lg border border-slate-300 px-3.5 text-sm outline-none focus:border-teal-600 focus:ring-3 focus:ring-teal-600/15"
                        />
                        <p v-if="form.errors.email" class="text-xs font-semibold text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="password" class="text-xs font-bold text-slate-700">Kata Sandi</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            placeholder="Masukkan kata sandi"
                            autocomplete="current-password"
                            class="h-11 rounded-lg border border-slate-300 px-3.5 text-sm outline-none focus:border-teal-600 focus:ring-3 focus:ring-teal-600/15"
                        />
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600">
                        <input v-model="form.remember" type="checkbox" />
                        <span>Ingat sesi saya di perangkat ini</span>
                    </label>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="mt-1 h-11 cursor-pointer rounded-lg bg-teal-600 text-sm font-extrabold text-white transition-colors hover:bg-teal-700 disabled:opacity-60"
                    >
                        {{ form.processing ? 'Memverifikasi...' : 'Masuk ke Aplikasi' }}
                    </button>
                </form>
            </div>
        </div>

        <footer class="mt-6 text-center text-[11.5px] text-slate-500">
            <strong class="text-slate-700">NusaPOS Retail</strong> &copy; {{ new Date().getFullYear() }} · Aplikasi POS Retail Multi-Cabang
        </footer>
    </div>
</template>
