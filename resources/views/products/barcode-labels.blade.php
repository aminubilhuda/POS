<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Cetak Label Barcode — NusaPOS Retail</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 20px; color: #0f172a; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; }
        .btn { padding: 8px 14px; background: #0d9488; color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 13px; }
        .sheet { display: grid; grid-template-columns: repeat({{ $size === '50x30' ? 2 : 3 }}, 1fr); gap: 10px; max-width: 720px; }
        .sticker { border: 1px dashed #94a3b8; border-radius: 6px; padding: 10px 8px; text-align: center; page-break-inside: avoid; }
        .name { font-size: 11px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 5px; }
        .bars { height: 34px; margin: 0 8px 4px; background: repeating-linear-gradient(90deg, #0f172a 0 2px, #fff 2px 4px, #0f172a 4px 5px, #fff 5px 8px, #0f172a 8px 11px, #fff 11px 13px); }
        .code { font-family: ui-monospace, monospace; font-size: 10px; color: #475569; }
        .price { font-size: 13px; font-weight: 800; margin-top: 2px; font-variant-numeric: tabular-nums; }
        @media print { .toolbar { display: none; } body { margin: 4mm; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <div><strong>Cetak Label Barcode ({{ $size }}mm)</strong> · {{ count($labels) }} stiker siap cetak</div>
        <button class="btn" onclick="window.print()">Cetak Sekarang</button>
    </div>

    <div class="sheet">
        @foreach($labels as $label)
        <div class="sticker">
            <div class="name">{{ $label['name'] }}</div>
            <div class="bars"></div>
            <div class="code">{{ $label['barcode'] }} · {{ $label['sku'] }}</div>
            <div class="price">Rp {{ number_format($label['price'], 0, ',', '.') }}</div>
        </div>
        @endforeach
    </div>
</body>
</html>
