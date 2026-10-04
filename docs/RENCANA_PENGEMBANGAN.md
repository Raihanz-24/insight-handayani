# Rencana Pengembangan — HANDAYANI ANALYTICS

> Dokumen ini adalah **rencana**, bukan hasil akhir. Semua tahap butuh persetujuan
> Anda sebelum dieksekusi. Tanda ⏸ = STOP-GATE (menunggu keputusan Anda).

---

## 1. Ringkasan

**Handayani Analytics** = aplikasi internal untuk **analitik operasional**:

| Modul | Isi |
|---|---|
| **Guest Analytics** | Statistik jumlah **kendaraan yang masuk** per minggu. Input **manual** oleh admin → diolah menjadi grafik/statistik. |
| **Rating Analytics** | **Distribusi bintang**: berapa orang memberi bintang 1, 2, 3, 4, 5 untuk 2 lokasi: **Rumah Makan Handayani Paiton** & **Cottage Wisata Paiton**. Plus **tren total ulasan** dari waktu ke waktu. Filter periode (berdasarkan **tanggal review**): by tanggal / minggu ini / bulan ini. |

**Stack:** Laravel 12 + Filament 3.3.55 (satu ekosistem dengan Portal & Logistik).
**Pengguna:** hanya admin internal (login Filament).

### Keputusan yang sudah disepakati

| Item | Keputusan |
|---|---|
| Folder | `D:\laragon\www\handayani-analytics` |
| Database | `handayani_analytics` (terpisah penuh) |
| Sumber rating | **SerpApi** (`engine=google_maps_reviews`) |
| Cara ambil rating | Snapshot terjadwal (harian) + tombol manual |
| Data rating | Rating rata-rata + jumlah ulasan (snapshot) **dan** review individual (rating + tanggal) |
| Auth | Admin internal saja (Filament) |
| Filament | v3.3.55 (CVE-2026-48500 sudah ditambal) |

---

## 2. Kondisi Saat Ini (Tahap 1 — SELESAI ✅)

- ✅ Laravel 12.69.3 terpasang di `D:\laragon\www\handayani-analytics`
- ✅ Filament **v3.3.55** terpasang; `composer audit` bersih (0 advisories)
- ✅ Panel admin di `/admin`; `AdminPanelProvider` dibuat; aset ter-publish
- ✅ MySQL `handayani_analytics` (+ `_test`) dibuat; migrasi dasar jalan
- ✅ Admin awal: `admin@handayani.local` (password sementara — **wajib diganti**)
- ✅ `.env` / `.env.example` dikonfigurasi (MySQL, locale `id`, zona waktu)
- ✅ Halaman login Filament terverifikasi **HTTP 200**

> ⚠️ **Catatan keamanan:** password admin contoh `Analytics#2026` bersifat
> sementara dan **hanya untuk lokal**. Ganti sebelum deploy.

---

## 3. Arsitektur Data (usulan)

### Modul Guest (input per minggu)

```
guest_entries
├─ id
├─ week_start         (date; SENIN awal minggu, unik)
├─ week_end           (date; MINGGU akhir minggu — diturunkan)
├─ vehicles           (integer, jumlah kendaraan masuk minggu itu)
├─ note               (nullable, text)
├─ entered_by         (FK users, nullable)
├─ timestamps
└─ UNIQUE(week_start)
```

> Satu baris = **satu minggu** (Senin s/d Minggu). Admin memilih minggu lewat
> date picker; sistem otomatis menormalkan ke Senin sebagai `week_start`.
> Statistik bulanan/tahunan = **agregasi** dari baris mingguan ini.

### Modul Rating (SerpApi)

```
places                                    (lokasi yang dipantau)
├─ id
├─ name              (mis. "Rumah Makan Handayani Paiton")
├─ type              (enum: restaurant, cottage)
├─ serpapi_data_id   (nullable; ID Google Maps)
├─ serpapi_place_id  (nullable; alternatif penanda)
├─ query             (nullable; teks pencarian cadangan)
├─ is_active         (bool)
├─ timestamps

rating_snapshots                          (1 baris = 1 kali ambil per tempat)
├─ id
├─ place_id          (FK places)
├─ captured_at       (datetime; waktu pengambilan)
├─ captured_date     (date; untuk agregasi harian)
├─ rating            (decimal 2,1  mis. 4.6)
├─ reviews_count     (integer; total ulasan kumulatif saat itu)
├─ source            (enum: serpapi, manual)
├─ status            (enum: ok, error)
├─ error_message     (nullable)
├─ timestamps
└─ UNIQUE(place_id, captured_date, source)  → hindari duplikat harian

reviews                                   (review individual, opsional/bertahap)
├─ id
├─ place_id          (FK places)
├─ review_key        (string; hash unik agar idempoten saat re-fetch)
├─ author_name       (nullable)
├─ rating            (integer 1..5)
├─ review_date       (date; dari iso_date SerpApi)
├─ snippet           (nullable, text)
├─ timestamps
└─ UNIQUE(place_id, review_key)
```

### Kebutuhan agregasi & filter

| Filter | Sumber data |
|---|---|
| **By tanggal** (rentang bebas) | `rating_snapshots.captured_date` / `guest_entries.week_start` |
| **Minggu ini** | `whereBetween(week_start, [startOfWeek, endOfWeek])` / `captured_date` |
| **Bulan ini** | agregasi baris mingguan yang beririsan dengan bulan tersebut |

> **Penting:** Statistik rating mingguan/bulanan **hanya akurat mulai dari tanggal
> aplikasi mulai menyimpan snapshot.** Data lama tidak bisa diambil ulang dari
> SerpApi (tidak menyimpan histori). Untuk periode sebelum app jalan, grafik
> kosong — ini wajar dan akan dijelaskan di UI.

---

## 4. Integrasi SerpApi — Desain

- **Service:** `App\Services\SerpApi\SerpApiClient` (wrapper HTTP `Http::`).
- **Endpoint:** `GET https://serpapi.com/search?engine=google_maps_reviews&data_id=...` (atau `place_id`).
- **Field diambil:** `place_info.rating`, `place_info.reviews`, daftar `reviews[]` (rating, `iso_date`, `user.name`, `snippet`).
- **Kuota:** konfigurasi `SERPAPI_DAILY_SEARCH_LIMIT` sebagai **guard** — sebelum
  request, cek jumlah pemakaian hari ini (tabel `serpapi_usage` atau cache).
  Jika limit tercapai → tolak/jadwalkan ulang, **bukan** tetap jalan.
- **Paginasi:** tiap tempat bisa perlu 1–3 halaman (`next_page_token`). Setiap
  halaman = 1 search. Dibatasi `max_pages` per tempat agar kuota terkendali.
- **Mode `manual`:** tetap didukung — admin bisa input rating manual (kolom
  `source=manual`). Berguna bila API down / kuota habis.
- **Rate limit & retry:** `SERPAPI_TIMEOUT`, `SERPAPI_RETRY`, backoff.
- **Keamanan:** API key **hanya** di `.env` (server-side). Tidak pernah dibocorkan
  ke frontend.

---

## 5. Panel Filament (usulan struktur)

```
/admin
├─ Dashboard
│   ├─ Widget: Kendaraan Masuk (minggu ini vs minggu lalu)
│   ├─ Widget: Rating Rumah Makan (nilai + tren)
│   ├─ Widget: Rating Cottage (nilai + tren)
│   └─ Widget: Status snapshot terakhir (kapan, sukses/gagal)
│
├─ Guest Analytics
│   ├─ Input Kendaraan (per hari)
│   ├─ Daftar Entri (CRUD + filter periode)
│   └─ Statistik Mingguan (tabel + grafik)
│
├─ Rating Analytics
│   ├─ Tempat (CRUD; 2 lokasi ter-seed)
│   ├─ Snapshot (daftar; tombol "Ambil Sekarang")
│   ├─ Review (daftar review individual + filter)
│   └─ Statistik (grafik tren: by tanggal / minggu ini / bulan ini)
│
└─ Pengaturan (opsional): kuota SerpApi, zona waktu, jadwal
```

**Fitur filter periode** (dipakai di Guest & Rating):
- Preset cepat: **Hari Ini / Minggu Ini / Bulan Ini / 7 Hari / 30 Hari / Custom**
- Date-range picker untuk "by tanggal" bebas.

**Grafik:** memakai **ApexCharts** via paket `leandrocfe/filament-apex-charts`
(dependensi pihak ketiga, kompatibel Filament 3).

---

## 6. Scheduler (otomatis harian)

- Command: `php artisan analytics:snapshot-ratings`
  - Loop semua `places` aktif → ambil via SerpApi → simpan `rating_snapshots`
    (+ `reviews` bila diaktifkan) → catat pemakaian kuota.
  - Tangani error per tempat (1 gagal ≠ hentikan semua).
- Command: `php artisan analytics:purge-old` (opsional; bersihkan review lama).
- **Registrasi scheduler:** `routes/console.php` (Laravel 11+ gaya baru) —
  `Schedule::command('analytics:snapshot-ratings')->dailyAt(config hour)`.
- **Di server:** butuh **cron** `* * * * * php /path/artisan schedule:run`.
  (Ini prasyarat deploy — akan dicatat di panduan deploy.)

---

## 7. Keamanan & Kualitas

- Input manual divalidasi (integer ≥ 0, tanggal tidak duplikat).
- Guard kuota SerpApi (cegah tagihan/limit terlampaui).
- Tidak ada API key di kode/commit; `.env.example` hanya placeholder.
- Tes: unit + feature (agregasi minggu/bulan, client SerpApi di-fake, guard kuota).
- Pint (code style) dijalankan tiap tahap.
- `composer audit` dijaga bersih.

---

## 8. Tahapan Eksekusi (Roadmap)

| Tahap | Isi | Status |
|---|---|---|
| **1. Fondasi** | Install Laravel + Filament + MySQL + admin + `.env` | ✅ SELESAI |
| **2. Konfigurasi & Dokumen** | `config/analytics.php`, `config/serpapi.php`, dokumen ini, `.env.example` | ✅ SELESAI |
| **2b. ApexCharts** | Install `leandrocfe/filament-apex-charts` + verifikasi panel | ⏸ |
| **3. Migrasi & Model** | Tabel `places`, `guest_entries`, `rating_snapshots`, `serpapi_usage` + Model + relasi | ⏸ |
| **4. SerpApi Client** | `SerpApiClient` + guard kuota + fake untuk tes | ⏸ |
| **5. Filament — Guest** | Resource input & daftar entri mingguan + filter periode | ⏸ |
| **6. Filament — Rating** | Resource Places, Snapshots (+ tombol ambil) | ⏸ |
| **7. Statistik & Widget** | Chart ApexCharts (by tanggal/minggu/bulan), widget dashboard | ⏸ |
| **8. Snapshot Otomatis** | Command + scheduler + logging | ⏸ |
| **9. Pengujian** | Tes agregasi, klien, guard kuota; Pint | ⏸ |
| **10. Deploy** | Panduan deploy server (LiteSpeed/cPanel) + cron | ⏸ |

**Setiap tahap = STOP-GATE** (saya berhenti & minta persetujuan Anda).

---

## 9. Keputusan Final (sudah dijawab) ✅

1. **Grafik:** **ApexCharts** (`leandrocfe/filament-apex-charts`).
2. **Review individual:** **tidak** disimpan; cukup **rating + jumlah ulasan**
   (snapshot harian). Tabel `reviews` ditunda.
3. **Input guest:** **per minggu** (Senin–Minggu; `week_start` unik).
4. **Bahasa UI:** **Indonesia** sepenuhnya.
5. **Tema:** **netral (biru/abu)** dulu; brand Handayani menyusul bila diinginkan.

Konsekuensi yang sudah tercermin di dokumen:
- Skema `guest_entries` memakai `week_start`/`week_end` (bukan `entry_date`).
- `config/analytics.php` `store_reviews=false` (default).
- Roadmap menambah tahap "install ApexCharts".

---

## 10. Risiko & Catatan

| Risiko | Mitigasi |
|---|---|
| SerpApi kuota habis / limit | Guard kuota + mode manual + backoff |
| Data histori kosong di awal | Jelaskan di UI; data terisi sejak app jalan |
| `data_id` lokasi salah/berubah | Simpan `query` cadangan; validasi saat seed |
| Review berubah/dihapus di Google | `review_key` idempoten; snapshot kumulatif tetap akurat |
| Cron tidak jalan di server | Catat di panduan deploy + command verifikasi |
| CVE Filament | Sudah pakai v3.3.55 (ditambal); `composer audit` dimonitor |
