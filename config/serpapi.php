<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SerpApi — Google Maps Reviews
|--------------------------------------------------------------------------
| Semua nilai sensitif (API key) hanya diambil dari .env dan TIDAK boleh
| di-hardcode. File ini hanya membaca konfigurasi.
|
| Dokumentasi: https://serpapi.com/google-maps-reviews-api
*/

return [

    /*
    | API key SerpApi. Kosong = integrasi nonaktif (mode manual saja).
    */
    'key' => env('SERPAPI_KEY'),

    /*
    | Base URL endpoint SerpApi.
    */
    'base_url' => env('SERPAPI_BASE_URL', 'https://serpapi.com/search'),

    /*
    | Engine Google Maps Reviews.
    */
    'engine' => env('SERPAPI_ENGINE', 'google_maps_reviews'),

    /*
    | Batas waktu & percobaan ulang panggilan HTTP (detik / kali).
    */
    'timeout' => (int) env('SERPAPI_TIMEOUT', 20),
    'retry' => (int) env('SERPAPI_RETRY', 2),
    'retry_delay' => (int) env('SERPAPI_RETRY_DELAY', 500), // milidetik

    /*
    | Bahasa hasil (hl). Catatan: engine `google_maps_reviews` TIDAK mendukung
    | parameter `gl` (country) — hanya `hl`. Lihat dokumentasi SerpApi.
    */
    'hl' => env('SERPAPI_HL', 'id'),

    /*
    | Batas pengaman kuota.
    |
    | - daily_search_limit : maksimum "search" (request) per hari.
    |   Setiap halaman hasil = 1 search. Guard akan menolak bila terlampaui.
    | - max_pages_per_place : maksimum halaman yang diambil per tempat
    |   per satu kali snapshot (mengendalikan pemakaian kuota).
    */
    'daily_search_limit' => (int) env('SERPAPI_DAILY_SEARCH_LIMIT', 40),
    'max_pages_per_place' => (int) env('SERPAPI_MAX_PAGES_PER_PLACE', 3),

    /*
    | Pengambilan REVIEW individual (untuk analitik distribusi bintang).
    |
    | - reviews_enabled   : aktifkan penyimpanan review individual.
    | - reviews_max_pages : maksimum halaman review per tempat per sinkronisasi
    |   (tiap halaman = 1 search). 1 halaman ≈ 8-20 review.
    */
    'reviews_enabled' => (bool) env('SERPAPI_REVIEWS_ENABLED', true),
    'reviews_max_pages' => (int) env('SERPAPI_REVIEWS_MAX_PAGES', 3),
];
