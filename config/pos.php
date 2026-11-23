<?php

return [
    /*
    |--------------------------------------------------------------------------
    | QZ Tray Digital Signing (Thermal 58mm & Cash Drawer)
    |--------------------------------------------------------------------------
    | Simpan private key RSA di luar folder public (mis. storage/app/private/qz).
    */
    'qz' => [
        'private_key_path' => env('QZ_PRIVATE_KEY_PATH', storage_path('app/private/qz/private-key.pem')),
        'certificate_path' => env('QZ_CERTIFICATE_PATH', storage_path('app/private/qz/digital-certificate.txt')),
        'signature_algorithm' => env('QZ_SIGNATURE_ALGORITHM', 'sha512'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retensi Backup Database Lokal (Hari)
    |--------------------------------------------------------------------------
    */
    'backup_retention_days' => (int) env('POS_BACKUP_RETENTION_DAYS', 14),
];
