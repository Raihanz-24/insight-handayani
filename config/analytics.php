<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Analytics — konfigurasi agregasi & jadwal
|--------------------------------------------------------------------------
*/

return [

    /*
    | Zona waktu untuk agregasi minggu/bulan (bukan UTC).
    | Filter "minggu ini"/"bulan ini" mengikuti zona ini.
    */
    'timezone' => env('ANALYTICS_TIMEZONE', 'Asia/Jakarta'),

    /*
    | Jam (0-23) snapshot rating otomatis dijalankan, dalam zona waktu di atas.
    */
    'snapshot_hour' => (int) env('ANALYTICS_SNAPSHOT_HOUR', 2),

    /*
    | Awal minggu: 1 = Senin, 0 = Minggu (mengikuti Carbon).
    */
    'week_starts_on' => (int) env('ANALYTICS_WEEK_STARTS_ON', 1),

    /*
    | Path panel admin Filament. Ubah ke nilai acak saat produksi agar tidak
    | mudah ditebak (mis. hndy-analytics-8f2a). Default: admin.
    */
    'admin_path' => env('ANALYTICS_ADMIN_PATH', 'admin'),

    /*
    | Apakah menyimpan review individual (selain rating + jumlah ulasan).
    | true  -> butuh kuota lebih & tabel `reviews` terisi.
    | false -> hanya snapshot rating + jumlah ulasan.
    */
    'store_reviews' => (bool) env('ANALYTICS_STORE_REVIEWS', false),

];
