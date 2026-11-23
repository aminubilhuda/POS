<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title inertia>NusaPOS Retail — Kasir (POS) Multi-Cabang</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="bg-[#f3f5f8] text-slate-900 antialiased">
    @inertia
</body>
</html>
