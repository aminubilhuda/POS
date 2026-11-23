import { formatRupiah } from './format';

/**
 * Integrasi QZ Tray (ESC/POS 58mm + Cash Drawer RJ11 pulse)
 * Referensi: blueprint-pos-retail.md Bagian 6
 */
export function buildEscPosReceipt(receipt, settings = {}) {
    const width = Number(settings.chars_per_line) || 32;
    const hr = '-'.repeat(width);

    const line = (left, right = '') => {
        const l = String(left);
        const r = String(right);
        const space = Math.max(1, width - l.length - r.length);
        return l + ' '.repeat(space) + r;
    };

    const commands = [
        '\x1B\x40', // Initialize printer
        '\x1B\x61\x01', // Align center
        ...(String(settings.receipt_header || `NUSAPOS RETAIL`).split('\n')),
        hr,
        '\x1B\x61\x00', // Align left
        line('No:', receipt.invoice_number),
        line('Tgl:', receipt.timestamp),
        line('Kasir:', receipt.cashier_name),
        ...(receipt.customer_name ? [line('Plg:', receipt.customer_name)] : []),
        hr,
    ];

    for (const item of receipt.items || []) {
        commands.push(String(item.product_name).slice(0, width));
        commands.push(
            line(
                `${item.quantity}x ${formatRupiah(item.unit_price, false)}`,
                formatRupiah(item.quantity * item.unit_price, false)
            )
        );
        if (item.discount_amount > 0) {
            commands.push(line('  Diskon Item', `-${formatRupiah(item.discount_amount, false)}`));
        }
    }

    commands.push(hr);
    commands.push(line('Subtotal', formatRupiah(receipt.subtotal, false)));
    if (receipt.trx_discount_amount > 0) {
        commands.push(line('Diskon Trx', `-${formatRupiah(receipt.trx_discount_amount, false)}`));
    }
    commands.push(line(`PPN ${receipt.tax_rate}%`, formatRupiah(receipt.tax_amount, false)));
    commands.push(line('TOTAL', formatRupiah(receipt.grand_total)));
    commands.push(hr);
    commands.push(line(String(receipt.payment_method || 'TUNAI').toUpperCase(), formatRupiah(receipt.paid_total)));
    commands.push(line('KEMBALI', formatRupiah(receipt.change_amount)));
    commands.push(hr);
    commands.push('\x1B\x61\x01');
    commands.push(...String(settings.receipt_footer || 'Terima kasih!').split('\n'));
    commands.push('\n\n\n');
    commands.push('\x1D\x56\x41\x03'); // Partial cut

    if (settings.auto_open_drawer && receipt.opens_drawer) {
        // ESC p 0 25 250 — pulse RJ11 cash drawer
        commands.push('\x1B\x70\x00\x19\xFA');
    }

    return commands;
}

let qzSecurityConfigured = false;

function configureQzSecurity(qz) {
    if (qzSecurityConfigured || !qz?.security) return;
    qzSecurityConfigured = true;

    qz.security.setCertificatePromise((resolve, reject) => {
        fetch('/pos/qz/cert', { credentials: 'same-origin' })
            .then((res) => (res.status === 200 ? res.text() : ''))
            .then((cert) => resolve(cert))
            .catch(reject);
    });

    if (typeof qz.security.setSignatureAlgorithm === 'function') {
        qz.security.setSignatureAlgorithm('SHA512');
    }

    qz.security.setSignaturePromise((toSign) => {
        return (resolve, reject) => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            fetch('/pos/qz/sign', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ request: toSign }),
            })
                .then((res) => res.json())
                .then((data) => (data.signature ? resolve(data.signature) : reject(new Error(data.error || 'Sign failed'))))
                .catch(reject);
        };
    });
}

export async function printViaQzTray(receipt, settings = {}) {
    const payload = buildEscPosReceipt(receipt, settings);

    if (typeof window !== 'undefined' && window.qz) {
        try {
            configureQzSecurity(window.qz);
            if (!window.qz.websocket.isActive()) {
                await window.qz.websocket.connect();
            }
            const printerName = settings.printer_name || (await window.qz.printers.getDefault());
            const config = window.qz.configs.create(printerName);
            await window.qz.print(config, payload.map((l) => `${l}\n`));
            return { printed: true, mode: 'qz-tray', printer: printerName };
        } catch (err) {
            return { printed: false, mode: 'fallback', error: err?.message || 'QZ Tray gagal terhubung' };
        }
    }

    // Fallback: simulasi cetak di browser saat QZ Tray desktop belum terpasang
    return {
        printed: true,
        mode: 'simulated',
        printer: settings.printer_name || 'Thermal 58mm (Simulasi)',
        escPosLines: payload.length,
    };
}
