<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PosLayout from '../../layouts/PosLayout.vue';

const props = defineProps({
    isOwner: { type: Boolean, default: false },
    setting: { type: Object, required: true },
    branches: { type: Array, required: true },
    users: { type: Array, default: () => [] },
    activityLogs: { type: Object, required: true },
    backups: { type: Array, default: () => [] },
    dbDriver: { type: String, default: 'sqlite' },
});

const activeTab = ref('printer'); // 'printer' | 'branches' | 'users' | 'logs' | 'backup'
const editingUser = ref(null);
const editingBranch = ref(null);
const restoringBackup = ref(null);
const creatingBackup = ref(false);

const restorePinForm = useForm({ pin: '' });
const uploadRestoreForm = useForm({ file: null, pin: '' });

const settingForm = useForm({
    tax_rate: props.setting.tax_rate,
    tax_inclusive: props.setting.tax_inclusive,
    receipt_prefix: props.setting.receipt_prefix,
    printer_name: props.setting.printer_name,
    paper_width: props.setting.paper_width,
    chars_per_line: props.setting.chars_per_line,
    auto_open_drawer: props.setting.auto_open_drawer,
    receipt_header: props.setting.receipt_header || '',
    receipt_footer: props.setting.receipt_footer || '',
});

const branchForm = useForm({
    code: '',
    name: '',
    address: '',
    phone: '',
});

const editBranchForm = useForm({
    name: '',
    address: '',
    phone: '',
    is_active: true,
});

const userForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'kasir',
    pin: '',
    branch_ids: [props.branches[0]?.id].filter(Boolean),
});

const editUserForm = useForm({
    name: '',
    role: 'kasir',
    password: '',
    pin: '',
    branch_ids: [],
});

function saveSettings() {
    settingForm.put('/settings/branch-setting', { preserveScroll: true });
}

function saveBranch() {
    branchForm.post('/settings/branches', {
        preserveScroll: true,
        onSuccess: () => branchForm.reset(),
    });
}

function openEditBranch(branch) {
    editingBranch.value = branch;
    editBranchForm.name = branch.name;
    editBranchForm.address = branch.address || '';
    editBranchForm.phone = branch.phone || '';
    editBranchForm.is_active = Boolean(branch.is_active);
}

function submitEditBranch() {
    if (!editingBranch.value) return;
    editBranchForm.put(`/settings/branches/${editingBranch.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingBranch.value = null;
        },
    });
}

function saveUser() {
    userForm.post('/settings/users', {
        preserveScroll: true,
        onSuccess: () => userForm.reset('name', 'email', 'password', 'pin'),
    });
}

function openEditUser(u) {
    editingUser.value = u;
    editUserForm.name = u.name;
    editUserForm.role = u.role;
    editUserForm.password = '';
    editUserForm.pin = '';
    editUserForm.branch_ids = [...(u.branch_ids || [])];
}

function submitEditUser() {
    if (!editingUser.value) return;
    editUserForm.put(`/settings/users/${editingUser.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingUser.value = null;
        },
    });
}

function toggleUser(u) {
    router.patch(`/settings/users/${u.id}/toggle`, {}, { preserveScroll: true });
}

function formatPageLabel(label) {
    if (label.includes('Previous') || label.includes('&laquo;')) return 'Sebelumnya';
    if (label.includes('Next') || label.includes('&raquo;')) return 'Berikutnya';
    return label;
}

function createBackupNow() {
    creatingBackup.value = true;
    router.post('/settings/backups', {}, {
        preserveScroll: true,
        onFinish: () => {
            creatingBackup.value = false;
        },
    });
}

function openRestoreModal(backup) {
    restoringBackup.value = backup;
    restorePinForm.reset();
    restorePinForm.clearErrors();
}

function submitRestoreFromList() {
    if (!restoringBackup.value) return;
    restorePinForm.post(`/settings/backups/${encodeURIComponent(restoringBackup.value.name)}/restore`, {
        preserveScroll: true,
        onSuccess: () => {
            restoringBackup.value = null;
            restorePinForm.reset();
        },
    });
}

function onBackupFileChange(event) {
    uploadRestoreForm.file = event.target.files?.[0] || null;
}

function submitUploadRestore() {
    if (!uploadRestoreForm.file) return;
    uploadRestoreForm.post('/settings/backups/upload-restore', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => uploadRestoreForm.reset(),
    });
}

function deleteBackup(name) {
    if (!confirm(`Hapus file backup ${name}?`)) return;
    router.delete(`/settings/backups/${encodeURIComponent(name)}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Pengaturan & Sistem" />

    <PosLayout active-menu="settings" title="Pengaturan & Sistem" subtitle="Pajak PPN, QZ Tray 58mm, edit cabang & user, serta activity log berpaginasi">
        <div class="mx-auto flex max-w-7xl flex-col gap-4">
            <div class="flex max-w-full gap-1.5 overflow-x-auto pb-1">
                <button
                    type="button"
                    @click="activeTab = 'printer'"
                    :class="['cursor-pointer whitespace-nowrap rounded-full border px-3.5 py-1.5 text-xs font-bold', activeTab === 'printer' ? 'border-teal-600 bg-teal-600 text-white' : 'border-slate-200 bg-white text-slate-600']"
                >
                    Pajak, Struk &amp; QZ Tray 58mm
                </button>
                <button
                    v-if="isOwner"
                    type="button"
                    @click="activeTab = 'branches'"
                    :class="['cursor-pointer whitespace-nowrap rounded-full border px-3.5 py-1.5 text-xs font-bold', activeTab === 'branches' ? 'border-teal-600 bg-teal-600 text-white' : 'border-slate-200 bg-white text-slate-600']"
                >
                    Cabang ({{ branches.length }})
                </button>
                <button
                    v-if="isOwner"
                    type="button"
                    @click="activeTab = 'users'"
                    :class="['cursor-pointer whitespace-nowrap rounded-full border px-3.5 py-1.5 text-xs font-bold', activeTab === 'users' ? 'border-teal-600 bg-teal-600 text-white' : 'border-slate-200 bg-white text-slate-600']"
                >
                    Pengguna &amp; Role ({{ users.length }})
                </button>
                <button
                    type="button"
                    @click="activeTab = 'logs'"
                    :class="['cursor-pointer whitespace-nowrap rounded-full border px-3.5 py-1.5 text-xs font-bold', activeTab === 'logs' ? 'border-teal-600 bg-teal-600 text-white' : 'border-slate-200 bg-white text-slate-600']"
                >
                    Activity Log ({{ activityLogs.total }})
                </button>
                <button
                    v-if="isOwner"
                    type="button"
                    @click="activeTab = 'backup'"
                    :class="['cursor-pointer whitespace-nowrap rounded-full border px-3.5 py-1.5 text-xs font-bold', activeTab === 'backup' ? 'border-teal-600 bg-teal-600 text-white' : 'border-slate-200 bg-white text-slate-600']"
                >
                    Backup &amp; Restore ({{ backups.length }})
                </button>
            </div>

            <!-- TAB 1: TAX, RECEIPT & QZ TRAY -->
            <form v-if="activeTab === 'printer'" @submit.prevent="saveSettings" class="grid max-w-3xl grid-cols-1 gap-4 rounded-[14px] border border-slate-200 bg-white p-4 text-xs sm:grid-cols-2 sm:p-5">
                <div class="flex flex-col gap-1">
                    <label class="font-bold">Persentase PPN (%)</label>
                    <input v-model.number="settingForm.tax_rate" type="number" min="0" max="100" required class="h-9 rounded-md border border-slate-300 px-3 tabular-nums" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="font-bold">Prefix Nomor Struk</label>
                    <input v-model="settingForm.receipt_prefix" required class="h-9 rounded-md border border-slate-300 px-3 font-mono" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="font-bold">Nama Printer QZ Tray</label>
                    <input v-model="settingForm.printer_name" required class="h-9 rounded-md border border-slate-300 px-3" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Lebar Kertas</label>
                        <select v-model.number="settingForm.paper_width" class="h-9 rounded-md border border-slate-300 px-2.5">
                            <option :value="58">58mm</option>
                            <option :value="80">80mm</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">Karakter/Baris</label>
                        <input v-model.number="settingForm.chars_per_line" type="number" min="24" max="48" class="h-9 rounded-md border border-slate-300 px-3 tabular-nums" />
                    </div>
                </div>
                <label class="flex items-center gap-2 font-semibold text-slate-700 sm:col-span-2">
                    <input v-model="settingForm.auto_open_drawer" type="checkbox" />
                    Buka cash drawer (RJ11 pulse) otomatis setelah pembayaran tunai
                </label>
                <div class="flex flex-col gap-1 sm:col-span-2">
                    <label class="font-bold">Teks Header Struk</label>
                    <textarea v-model="settingForm.receipt_header" rows="3" class="rounded-md border border-slate-300 p-2.5 font-mono text-xs" />
                </div>
                <div class="flex flex-col gap-1 sm:col-span-2">
                    <label class="font-bold">Teks Footer Struk</label>
                    <textarea v-model="settingForm.receipt_footer" rows="2" class="rounded-md border border-slate-300 p-2.5 font-mono text-xs" />
                </div>
                <div class="flex justify-end border-t border-slate-200 pt-3 sm:col-span-2">
                    <button type="submit" :disabled="settingForm.processing" class="h-9 w-full cursor-pointer rounded-md bg-teal-600 px-5 font-bold text-white hover:bg-teal-700 sm:w-auto">
                        Simpan Pengaturan Cabang
                    </button>
                </div>
            </form>

            <!-- TAB 2: BRANCHES (OWNER) -->
            <div v-else-if="activeTab === 'branches' && isOwner" class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_340px]">
                <div class="overflow-x-auto rounded-[14px] border border-slate-200 bg-white">
                    <table class="min-w-[460px] w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">Kode</th>
                                <th class="px-4 py-3">Nama Cabang</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="b in branches" :key="b.id">
                                <td class="px-4 py-3 font-mono font-bold text-teal-700">{{ b.code }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900">{{ b.name }}</div>
                                    <div class="text-[11px] text-slate-500">{{ b.address }} · {{ b.phone }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['rounded-full px-2 py-0.5 text-[10.5px] font-bold', b.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                                        {{ b.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" @click="openEditBranch(b)" class="cursor-pointer rounded border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:border-teal-600 hover:text-teal-700">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Edit Branch or Add Branch -->
                <form v-if="editingBranch" @submit.prevent="submitEditBranch" class="flex h-fit flex-col gap-3 rounded-[14px] border border-teal-600 bg-white p-4 text-xs">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-extrabold text-slate-900">Edit Cabang — {{ editingBranch.code }}</h3>
                        <button type="button" @click="editingBranch = null" class="cursor-pointer text-slate-500">&times;</button>
                    </div>
                    <input v-model="editBranchForm.name" required placeholder="Nama cabang" class="h-9 rounded-md border border-slate-300 px-3" />
                    <input v-model="editBranchForm.address" placeholder="Alamat cabang" class="h-9 rounded-md border border-slate-300 px-3" />
                    <input v-model="editBranchForm.phone" placeholder="Telepon" class="h-9 rounded-md border border-slate-300 px-3" />
                    <label class="flex items-center gap-2 font-semibold text-slate-700">
                        <input v-model="editBranchForm.is_active" type="checkbox" />
                        Cabang Aktif
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="editingBranch = null" class="h-9 cursor-pointer rounded-md border border-slate-300 bg-white px-3 font-bold">Batal</button>
                        <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 px-4 font-bold text-white hover:bg-teal-700">Simpan</button>
                    </div>
                </form>

                <form v-else @submit.prevent="saveBranch" class="flex h-fit flex-col gap-3 rounded-[14px] border border-slate-200 bg-white p-4 text-xs">
                    <h3 class="text-sm font-extrabold text-slate-900">Tambah Cabang Baru</h3>
                    <input v-model="branchForm.code" required placeholder="Kode (mis. CAB04)" class="h-9 rounded-md border border-slate-300 px-3 font-mono" />
                    <input v-model="branchForm.name" required placeholder="Nama cabang" class="h-9 rounded-md border border-slate-300 px-3" />
                    <input v-model="branchForm.address" placeholder="Alamat cabang" class="h-9 rounded-md border border-slate-300 px-3" />
                    <input v-model="branchForm.phone" placeholder="Telepon" class="h-9 rounded-md border border-slate-300 px-3" />
                    <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 font-bold text-white hover:bg-teal-700">Tambah Cabang</button>
                </form>
            </div>

            <!-- TAB 3: USERS & ROLES (OWNER) -->
            <div v-else-if="activeTab === 'users' && isOwner" class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_360px]">
                <div class="overflow-x-auto rounded-[14px] border border-slate-200 bg-white">
                    <table class="min-w-[540px] w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">Pengguna</th>
                                <th class="px-4 py-3">Role</th>
                                <th class="px-4 py-3">Cabang</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="u in users" :key="u.id" :class="{ 'opacity-60': !u.is_active }">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900">{{ u.name }}</div>
                                    <div class="text-[11px] text-slate-500">{{ u.email }} <span v-if="u.has_pin" class="text-teal-700">· PIN Aktif</span></div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 font-bold capitalize text-slate-700">{{ u.role }}</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px] text-slate-600">
                                    {{ u.role === 'owner' ? 'Semua' : (u.branches.join(', ') || '-') }}
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['rounded-full px-2 py-0.5 text-[10.5px] font-bold', u.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                                        {{ u.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-1.5">
                                        <button type="button" @click="openEditUser(u)" class="cursor-pointer rounded border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:border-teal-600 hover:text-teal-700">
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleUser(u)"
                                            :class="['cursor-pointer rounded border px-2.5 py-1 text-[11px] font-bold', u.is_active ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100' : 'border-teal-600 bg-teal-600 text-white hover:bg-teal-700']"
                                        >
                                            {{ u.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Edit User Form -->
                <form v-if="editingUser" @submit.prevent="submitEditUser" class="flex h-fit flex-col gap-3 rounded-[14px] border border-teal-600 bg-white p-4 text-xs">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-extrabold text-slate-900">Edit Pengguna — {{ editingUser.name }}</h3>
                        <button type="button" @click="editingUser = null" class="cursor-pointer text-slate-500">&times;</button>
                    </div>
                    <input v-model="editUserForm.name" required placeholder="Nama lengkap" class="h-9 rounded-md border border-slate-300 px-3" />
                    <select v-model="editUserForm.role" class="h-9 rounded-md border border-slate-300 px-2.5">
                        <option value="kasir">Kasir</option>
                        <option value="gudang">Gudang</option>
                        <option value="admin">Admin Cabang</option>
                        <option value="owner">Owner</option>
                    </select>
                    <input v-if="editUserForm.role === 'admin' || editUserForm.role === 'owner'" v-model="editUserForm.pin" type="password" placeholder="Reset PIN Admin (kosongkan jika tetap)" class="h-9 rounded-md border border-slate-300 px-3 font-mono" />
                    <input v-model="editUserForm.password" type="password" placeholder="Reset Password (kosongkan jika tetap)" class="h-9 rounded-md border border-slate-300 px-3" />
                    <div v-if="editUserForm.role !== 'owner'" class="flex flex-col gap-1 rounded-md border border-slate-200 bg-slate-50 p-2.5">
                        <span class="font-bold text-slate-600">Penugasan Cabang:</span>
                        <label v-for="b in branches" :key="b.id" class="flex items-center gap-2">
                            <input type="checkbox" :value="b.id" v-model="editUserForm.branch_ids" />
                            <span>{{ b.code }} — {{ b.name }}</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="editingUser = null" class="h-9 cursor-pointer rounded-md border border-slate-300 bg-white px-3 font-bold">Batal</button>
                        <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 px-4 font-bold text-white hover:bg-teal-700">Simpan Perubahan</button>
                    </div>
                </form>

                <!-- Create User Form -->
                <form v-else @submit.prevent="saveUser" class="flex h-fit flex-col gap-3 rounded-[14px] border border-slate-200 bg-white p-4 text-xs">
                    <h3 class="text-sm font-extrabold text-slate-900">Tambah Pengguna Baru</h3>
                    <input v-model="userForm.name" required placeholder="Nama lengkap" class="h-9 rounded-md border border-slate-300 px-3" />
                    <input v-model="userForm.email" type="email" required placeholder="Email login" class="h-9 rounded-md border border-slate-300 px-3" />
                    <input v-model="userForm.password" type="password" required placeholder="Kata sandi (min 6 karakter)" class="h-9 rounded-md border border-slate-300 px-3" />
                    <select v-model="userForm.role" class="h-9 rounded-md border border-slate-300 px-2.5">
                        <option value="kasir">Kasir</option>
                        <option value="gudang">Gudang</option>
                        <option value="admin">Admin Cabang</option>
                        <option value="owner">Owner</option>
                    </select>
                    <input v-if="userForm.role === 'admin' || userForm.role === 'owner'" v-model="userForm.pin" placeholder="PIN Otorisasi Void/Refund (6 digit)" class="h-9 rounded-md border border-slate-300 px-3 font-mono" />
                    <div v-if="userForm.role !== 'owner'" class="flex flex-col gap-1">
                        <span class="font-bold text-slate-600">Penugasan Cabang:</span>
                        <label v-for="b in branches" :key="b.id" class="flex items-center gap-2">
                            <input type="checkbox" :value="b.id" v-model="userForm.branch_ids" />
                            <span>{{ b.code }} — {{ b.name }}</span>
                        </label>
                    </div>
                    <button type="submit" class="h-9 cursor-pointer rounded-md bg-teal-600 font-bold text-white hover:bg-teal-700">Simpan Pengguna</button>
                </form>
            </div>

            <!-- TAB 4: PAGINATED ACTIVITY LOGS -->
            <div v-else-if="activeTab === 'logs'" class="overflow-hidden rounded-[14px] border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                <table class="min-w-[580px] w-full text-left text-xs">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-2.5">Waktu</th>
                            <th class="px-4 py-2.5">Cabang</th>
                            <th class="px-4 py-2.5">Pengguna</th>
                            <th class="px-4 py-2.5">Aksi</th>
                            <th class="px-4 py-2.5">Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="l in activityLogs.data" :key="l.id">
                            <td class="px-4 py-2.5 font-mono text-[11px] text-slate-500">{{ l.time }}</td>
                            <td class="px-4 py-2.5 font-mono font-bold text-slate-700">{{ l.branch }}</td>
                            <td class="px-4 py-2.5 font-semibold text-slate-900">{{ l.user }}</td>
                            <td class="px-4 py-2.5"><span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-[10.5px] font-bold text-slate-700">{{ l.action }}</span></td>
                            <td class="px-4 py-2.5 text-slate-600">{{ l.description }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3 text-xs tabular-nums">
                    <span class="text-slate-600">
                        Menampilkan <strong>{{ activityLogs.from ?? 0 }}</strong>–<strong>{{ activityLogs.to ?? 0 }}</strong> dari <strong>{{ activityLogs.total }}</strong> aktivitas
                    </span>
                    <nav v-if="activityLogs.links && activityLogs.links.length > 3" class="flex gap-1">
                        <template v-for="(link, idx) in activityLogs.links" :key="idx">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                :class="['inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2.5 font-bold', link.active ? 'border-teal-600 bg-teal-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-teal-600']"
                            >
                                {{ formatPageLabel(link.label) }}
                            </Link>
                            <span v-else class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-slate-200 bg-slate-100 px-2.5 text-slate-400">
                                {{ formatPageLabel(link.label) }}
                            </span>
                        </template>
                    </nav>
                </div>
            </div>

            <!-- TAB 5: BACKUP & RESTORE DATABASE (OWNER ONLY) -->
            <div v-else-if="activeTab === 'backup' && isOwner" class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_360px]">
                <div class="overflow-hidden rounded-[14px] border border-slate-200 bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3 text-xs">
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-900">Daftar Backup Database</h3>
                            <p class="text-[11px] text-slate-500">Driver aktif: <strong class="uppercase">{{ dbDriver }}</strong> · Backup otomatis terjadwal tiap 02:00 WIB</p>
                        </div>
                        <button
                            type="button"
                            :disabled="creatingBackup"
                            @click="createBackupNow"
                            class="h-9 cursor-pointer rounded-md bg-teal-600 px-4 text-xs font-bold text-white hover:bg-teal-700 disabled:opacity-50"
                        >
                            {{ creatingBackup ? 'Membuat Backup...' : '+ Buat Backup Sekarang' }}
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-[540px] w-full text-left text-xs tabular-nums">
                            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                                <tr>
                                    <th class="px-4 py-2.5">Nama File</th>
                                    <th class="px-4 py-2.5">Waktu</th>
                                    <th class="px-4 py-2.5 text-right">Ukuran</th>
                                    <th class="px-4 py-2.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="backups.length === 0">
                                    <td colspan="4" class="px-4 py-8 text-center text-slate-500">
                                        Belum ada file backup. Klik "Buat Backup Sekarang" untuk membuat cadangan pertama.
                                    </td>
                                </tr>
                                <tr v-for="b in backups" :key="b.name">
                                    <td class="px-4 py-2.5">
                                        <div class="font-mono font-bold text-slate-900">{{ b.name }}</div>
                                        <div class="text-[10.5px] text-slate-400">{{ b.driver }}</div>
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-[11px] text-slate-600">{{ b.created_at }}</td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-slate-700">{{ b.size_human }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <div class="flex justify-end gap-1.5">
                                            <a
                                                :href="`/settings/backups/${encodeURIComponent(b.name)}/download`"
                                                class="inline-flex h-7 items-center rounded border border-slate-300 bg-white px-2.5 text-[11px] font-bold text-slate-700 hover:border-teal-600 hover:text-teal-700"
                                            >
                                                Unduh
                                            </a>
                                            <button
                                                type="button"
                                                @click="openRestoreModal(b)"
                                                class="h-7 cursor-pointer rounded border border-amber-300 bg-amber-50 px-2.5 text-[11px] font-bold text-amber-900 hover:bg-amber-100"
                                            >
                                                Restore
                                            </button>
                                            <button
                                                type="button"
                                                @click="deleteBackup(b.name)"
                                                class="h-7 cursor-pointer rounded border border-red-200 bg-red-50 px-2.5 text-[11px] font-bold text-red-700 hover:bg-red-100"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Upload & Restore Form -->
                <form @submit.prevent="submitUploadRestore" class="flex h-fit flex-col gap-3 rounded-[14px] border border-slate-200 bg-white p-4 text-xs">
                    <h3 class="text-sm font-extrabold text-slate-900">Upload &amp; Restore Backup</h3>
                    <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-[11.5px] text-amber-900">
                        Sistem otomatis membuat <strong>safety backup</strong> sebelum menimpa database. Memerlukan <strong>PIN Owner</strong>.
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">File Backup (.sql / .sqlite)</label>
                        <input
                            type="file"
                            accept=".sql,.sqlite"
                            required
                            @change="onBackupFileChange"
                            class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-3 text-xs"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">PIN Otorisasi Owner</label>
                        <input
                            v-model="uploadRestoreForm.pin"
                            type="password"
                            required
                            placeholder="Masukkan PIN Owner"
                            class="h-9 rounded-md border border-slate-300 px-3 font-mono outline-none focus:border-teal-600"
                        />
                        <p v-if="uploadRestoreForm.errors.pin" class="font-semibold text-red-600">{{ uploadRestoreForm.errors.pin }}</p>
                    </div>
                    <button
                        type="submit"
                        :disabled="uploadRestoreForm.processing || !uploadRestoreForm.file"
                        class="h-9 cursor-pointer rounded-md bg-red-600 font-bold text-white hover:bg-red-700 disabled:opacity-50"
                    >
                        {{ uploadRestoreForm.processing ? 'Memproses Restore...' : 'Upload & Restore Database' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- MODAL: KONFIRMASI RESTORE DARI DAFTAR BACKUP -->
        <div v-if="restoringBackup" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-3 sm:p-5" @click.self="restoringBackup = null">
            <div class="w-full max-w-[420px] overflow-hidden rounded-[14px] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                    <h2 class="text-[15px] font-extrabold text-slate-900">Konfirmasi Restore Database</h2>
                    <button type="button" @click="restoringBackup = null" class="cursor-pointer text-lg font-bold text-slate-500">&times;</button>
                </div>
                <form @submit.prevent="submitRestoreFromList" class="flex flex-col gap-3 p-5 text-xs">
                    <div class="rounded-md border border-red-200 bg-red-100 px-3 py-2.5 text-red-900">
                        Database saat ini akan ditimpa dengan file <strong class="font-mono">{{ restoringBackup.name }}</strong>. Safety backup otomatis dibuat terlebih dahulu.
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-bold">PIN Otorisasi Owner</label>
                        <input
                            v-model="restorePinForm.pin"
                            type="password"
                            required
                            placeholder="Masukkan PIN Owner"
                            class="h-10 rounded-md border border-slate-300 px-3 font-mono text-sm outline-none focus:border-teal-600"
                        />
                        <p v-if="restorePinForm.errors.pin" class="font-semibold text-red-600">{{ restorePinForm.errors.pin }}</p>
                    </div>
                    <div class="mt-1 flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" @click="restoringBackup = null" class="h-9 cursor-pointer rounded-md border border-slate-300 bg-white px-4 font-bold">Batal</button>
                        <button type="submit" :disabled="restorePinForm.processing" class="h-9 cursor-pointer rounded-md bg-red-600 px-4 font-bold text-white hover:bg-red-700">
                            Verifikasi PIN &amp; Restore
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </PosLayout>
</template>
