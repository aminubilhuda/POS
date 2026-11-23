<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Laporan POS — {{ $scopeLabel }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #0f172a; margin: 28px; font-size: 13px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #475569; margin-bottom: 18px; font-size: 12px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
        .card { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; }
        .card span { display: block; font-size: 11px; color: #64748b; }
        .card strong { font-size: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #e2e8f0; padding: 7px 10px; text-align: left; }
        th { background: #f8fafc; font-size: 11px; text-transform: uppercase; color: #475569; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .no-print { margin-bottom: 16px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding:8px 14px;background:#0d9488;color:#fff;border:none;border-radius:6px;font-weight:700;cursor:pointer;">
            Cetak / Simpan PDF
        </button>
    </div>

    <h1>Laporan Penjualan &amp; Laba Rugi — NusaPOS Retail</h1>
    <div class="meta">Cakupan: <strong>{{ $scopeLabel }}</strong> · Periode: {{ $filters['from'] }} s/d {{ $filters['to'] }}</div>

    <div class="grid">
        <div class="card"><span>Penjualan Bersih</span><strong>Rp {{ number_format($summary['net_sales'], 0, ',', '.') }}</strong></div>
        <div class="card"><span>HPP (Modal)</span><strong>Rp {{ number_format($summary['cogs'], 0, ',', '.') }}</strong></div>
        <div class="card"><span>Laba Kotor ({{ $summary['margin_pct'] }}%)</span><strong>Rp {{ number_format($summary['gross_profit'], 0, ',', '.') }}</strong></div>
    </div>

    <h3>Produk Terlaris</h3>
    <table>
        <thead>
            <tr><th>Produk</th><th>Varian</th><th class="num">Qty</th><th class="num">Omzet</th><th class="num">Laba Kotor</th></tr>
        </thead>
        <tbody>
            @foreach($topProducts as $item)
            <tr>
                <td>{{ $item['product_name'] }}</td>
                <td>{{ $item['variant_name'] }}</td>
                <td class="num">{{ $item['qty_sold'] }}</td>
                <td class="num">Rp {{ number_format($item['revenue'], 0, ',', '.') }}</td>
                <td class="num">Rp {{ number_format($item['profit'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3>Kinerja Kasir</h3>
    <table>
        <thead>
            <tr><th>Kasir</th><th>Role</th><th class="num">Transaksi</th><th class="num">Total Penjualan</th></tr>
        </thead>
        <tbody>
            @foreach($cashierPerformance as $row)
            <tr>
                <td>{{ $row['cashier_name'] }}</td>
                <td>{{ ucfirst($row['role']) }}</td>
                <td class="num">{{ $row['transactions'] }}</td>
                <td class="num">Rp {{ number_format($row['total_sales'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
