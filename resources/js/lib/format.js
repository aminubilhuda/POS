/**
 * Format integer rupiah tanpa desimal (sesuai prinsip desain blueprint 4.3).
 * Contoh: 1000000 -> "Rp. 1.000.000"
 */
export function formatRupiah(amount, withPrefix = true) {
    const value = Math.round(Number(amount) || 0);
    const formatted = Math.abs(value)
        .toString()
        .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const sign = value < 0 ? '−' : '';
    return withPrefix ? `${sign}Rp. ${formatted}` : `${sign}${formatted}`;
}

export function parseRupiahInput(raw) {
    const digits = String(raw ?? '').replace(/[^\d]/g, '');
    return digits ? parseInt(digits, 10) : 0;
}

export function generateShortUuid() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID().slice(0, 13);
    }
    return Math.random().toString(16).slice(2, 10) + '-' + Math.random().toString(16).slice(2, 6);
}
