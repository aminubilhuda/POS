import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

// Delegasi klik global untuk memilih/menandai baris tabel (memudahkan geser horizontal di HP/Tablet)
if (typeof document !== 'undefined') {
    document.addEventListener('click', (event) => {
        const row = event.target.closest('tbody tr');
        if (!row) return;

        // Abaikan baris kosong (empty state dengan colspan)
        if (row.querySelector('td[colspan]')) return;

        const tbody = row.closest('tbody');
        if (!tbody) return;

        const clickedInteractive = Boolean(
            event.target.closest('button, a, input, select, textarea, label')
        );
        const alreadySelected = row.classList.contains('is-selected');

        tbody.querySelectorAll('tr.is-selected').forEach((tr) => {
            if (tr !== row) tr.classList.remove('is-selected');
        });

        // Jika klik tombol/input di dalam baris, pastikan baris tetap terpilih; jika klik sel biasa, toggle
        if (clickedInteractive) {
            row.classList.add('is-selected');
        } else {
            row.classList.toggle('is-selected', !alreadySelected);
        }
    });
}

createInertiaApp({
    title: (title) => (title ? `${title} — NusaPOS Retail` : 'NusaPOS Retail — Kasir (POS) Multi-Cabang'),
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue', { eager: true });
        return pages[`./pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#0d9488',
    },
});
