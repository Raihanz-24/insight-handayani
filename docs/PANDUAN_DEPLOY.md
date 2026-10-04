# PANDUAN DEPLOY — HANDAYANI ANALYTICS

Panduan menerapkan aplikasi **Handayani Analytics** ke server produksi
(LiteSpeed / cPanel, contoh: `banksam6@calm`).

> **Aturan aman:** dokumen ini **tidak pernah** memuat rahasia asli. Semua
> tempat rahasia ditulis sebagai `CHANGE_ME`. Anda mengisinya sendiri di server.

---

## 0. Ringkasan prasyarat

| Kebutuhan | Nilai |
|---|---|
| PHP | 8.2+ (disarankan 8.2/8.3) |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl`, `fileinfo`, `bcmath` |
| Database | MySQL 8 / MariaDB 10.4+ |
| Composer | ada di server (atau upload `vendor/` dari lokal) |
| Node/npm | **tidak wajib** — aset Filament sudah di dalam `vendor/` |
| Cron | 1 (untuk scheduler) |

Asumsi path (samakan dengan server Anda):
- Aplikasi: `~/handayani-analytics`
- Domain/subdomain: `analytics.contoh.com` (CHANGE_ME)
- Document root diarahkan ke `~/handayani-analytics/public`

---

## 1. Upload kode

**Cara A — lewat Git (disarankan).** Di server:

```bash
cd ~
git clone <URL_REPO_ANDA> handayani-analytics
cd handayani-analytics
git checkout main
```

**Cara B — upload ZIP** isi repo ke `~/handayani-analytics` (tanpa `.env`, tanpa `vendor/` bila akan `composer install` di server).

> Jangan pernah meng-commit `.env`. File itu sudah masuk `.gitignore`.

---

## 2. Dependensi

```bash
cd ~/handayani-analytics
composer install --no-dev --optimize-autoloader
```

Bila server tidak punya Composer: jalankan
`composer install --no-dev --optimize-autoloader` **di lokal**, lalu upload
folder `vendor/` hasilnya (kecuali Anda punya cara lain). Pastikan
`vendor/leandrocfe/filament-apex-charts` ikut ter-upload.

---

## 3. Database

1. Buat database & user di cPanel:
   - Database: `CHANGE_ME_nama_db`
   - User: `CHANGE_ME_user_db`
   - Password: `CHANGE_ME_password_db`
   - Beri user **ALL PRIVILEGES** pada database tsb.

---

## 4. File `.env`

Salin contoh lalu isi:

```bash
cp .env.example .env
```

Isi nilai berikut (contoh — ganti `CHANGE_ME`):

```dotenv
APP_NAME="Handayani Analytics"
APP_ENV=production
APP_KEY=            # diisi pada langkah 5
APP_DEBUG=false
APP_URL=https://analytics.CHANGE_ME.com
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=CHANGE_ME_nama_db
DB_USERNAME=CHANGE_ME_user_db
DB_PASSWORD=CHANGE_ME_password_db

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

# Rahasia SerpApi — JANGAN bagikan. Isi di server saja.
SERPAPI_KEY=CHANGE_ME_SERPAPI_KEY
SERPAPI_HL=id

# Analitik
ANALYTICS_TIMEZONE=Asia/Jakarta
ANALYTICS_WEEK_STARTS_ON=1
ANALYTICS_ADMIN_PATH=CHANGE_ME_path_rahasia   # mis. hndy-analytics-8f2a

# Batas kuota SerpApi harian (aman)
SERPAPI_DAILY_SEARCH_LIMIT=40
SERPAPI_REVIEWS_MAX_PAGES=3

# data_id lokasi (sudah terisi dari hasil resolve link Maps)
SERPAPI_DATA_ID_RM_HANDAYANI=0x2dd7036fcefb89b5:0xbaf316dbc59eefd5
SERPAPI_DATA_ID_COTTAGE_PAITON=0x2dd7036fe2eb31d9:0x856417a7becac944
```

> `ANALYTICS_ADMIN_PATH` menentukan URL panel, mis. `/hndy-analytics-8f2a`
> menggantikan `/admin`. Amankan dengan nilai acak saat produksi.

---

## 5. Kunci aplikasi & migrasi

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force          # menanam 2 lokasi (idempoten)
php artisan storage:link             # bila gagal, lihat Catatan Storage
```

---

## 6. Akun awal (developer & user)

Aplikasi **sengaja tidak** membuat akun default (tidak ada kredensial bocor).
Buat akun developer pertama:

```bash
php artisan analytics:make-developer alamat@email.com \
  --name="Nama Anda" --password="CHANGE_ME_kuat"
```

Aturan password: **minimal 8 karakter, kombinasi huruf & angka.**

Buat akun **user** (read-only) dari dalam panel: menu
**Developer → Kelola Pengguna → Tambah**.

---

## 7. Optimasi cache

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Setiap kali Anda mengubah `.env`, jalankan `php artisan optimize:clear`.

---

## 8. Scheduler (pengambilan terjadwal)

Tempat dengan mode **Terjadwal** baru berjalan bila cron memanggil scheduler.

Tambahkan **1 baris cron** di cPanel → *Cron Jobs* (ganti path & PHP sesuai server):

```
* * * * * /usr/local/bin/php /home/banksam6/handayani-analytics/artisan schedule:run >> /dev/null 2>&1
```

> Jika tidak yakin lokasi PHP CLI, jalankan `which php` via SSH.
> Alternatif: sesuaikan dengan pola cron yang dipakai Logistik/Portal.

**Catatan penting:** pengambilan otomatis **100% dikendalikan dari UI**.
Scheduler tiap jam hanya menjalankan command; command sendirilah yang
menentukan tempat mana yang "due" (berdasarkan interval & jam per tempat).
Tempat mode **Nonaktif/Manual** tidak pernah diambil oleh cron.

---

## 9. Document root & keamanan

- Arahkan domain ke folder `public` (bukan root proyek).
  - Di cPanel: set *Document Root* subdomain ke `handayani-analytics/public`,
    **atau** gunakan `.htaccess` pengalih dari root ke `public`.
- Pastikan `.env` **tidak** dapat diakses via web. Berkas `.htaccess` standar
  Laravel sudah menolaknya karena document root di `public`.
- `APP_DEBUG=false` wajib pada produksi.
- Ganti `ANALYTICS_ADMIN_PATH` ke nilai acak.

---

## 10. Catatan Storage (LiteSpeed)

Bila `php artisan storage:link` gagal (server membatasi symlink dari PHP),
buat symlink manual via SSH:

```bash
cd ~/handayani-analytics
ln -s "$(pwd)/storage/app/public" public/storage
```

Verifikasi:

```bash
ls -la public/storage
curl -I https://analytics.CHANGE_ME.com/storage
```

> Pada server `banksam6@calm`, `php artisan tinker` dan fungsi `shell_exec`
> dinonaktifkan. Semua perintah di sini memakai `artisan`/`ln` biasa sehingga
> aman.

---

## 11. Uji cepat setelah deploy

```bash
# 1. Halaman login panel tampil
curl -I https://analytics.CHANGE_ME.com/CHANGE_ME_admin_path/login

# 2. Scheduler terdaftar
php artisan schedule:list

# 3. Snapshot manual untuk satu tempat (1 search + review)
php artisan analytics:snapshot-ratings --place=1

# 4. Cek sisa kuota yang tercatat
php artisan analytics:snapshot-ratings   # akan menampilkan sisa kuota di awal
```

Lalu buka panel → **Analitik → Statistik Rating** dan tekan
**Tempat & Analisis → Ambil Sekarang** untuk mengisi data.

---

## 12. Pembaruan (update) aplikasi

```bash
cd ~/handayani-analytics
php artisan down                      # (opsional) maintenance mode
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

---

## 13. Perintah artisan yang tersedia

| Perintah | Fungsi |
|---|---|
| `analytics:snapshot-ratings` | Ambil snapshot untuk tempat terjadwal yang due |
| `analytics:snapshot-ratings --force` | Ambil untuk **semua** tempat aktif (abaikan jadwal/mode off) |
| `analytics:snapshot-ratings --place=ID` | Ambil satu tempat saja |
| `analytics:snapshot-ratings --backfill` | **Tarik history review** (sekali saja; borong kuota) |
| `analytics:snapshot-ratings --backfill --days=7` | Backfill **hanya N hari terakhir** (hemat kuota) |
| `analytics:snapshot-ratings --no-reviews` | Hanya ringkasan rating (hemat kuota) |
| `analytics:make-developer <email>` | Buat/jadikan user sebagai developer |
| `analytics:export-data` | Ekspor data (places/reviews/snapshot/statistik) ke JSON |
| `analytics:import-data --path=FILE` | Impor data dari JSON ke server (idempoten) |
| `sso:assign-uuid <email>` | Tetapkan/tampilkan `portal_uuid` (tautan SSO) user |

### Catatan penting soal pengambilan review

- **Halaman 1** review selalu berisi **8 review** (tidak bisa diubah).
- **Halaman lanjutan** berisi hingga **20 review** (`num` 1–20).
- Token halaman lanjutan bisa sangat panjang — Google menyediakan **ratusan**
  review (untuk lokasi ini ratusan dari total ribuan).
- **Sync harian** otomatis berhenti begitu menemukan review lebih tua dari
  (hari ini − `SERPAPI_STOP_BEFORE_BUFFER_DAYS`), jadi biasanya hanya
  **1–2 request/hari**.
- **Backfill** (`--backfill`) menarik history review. Karena memakai banyak
  kuota, jalankan saat kuota harian masih penuh dan tunggu di hari berikutnya
  bila terputus (progress tersimpan, idempoten). Gunakan `--days=N` untuk
  membatasi hanya `N` hari terakhir (mis. `--days=7`).

### Memindahkan data lokal → server (backfill)

Data analitik disimpan di **database**, bukan di Git. Jadi setelah deploy &
`migrate` di server, pindahkan datanya:

```bash
# DI LOKAL: ekspor data
php artisan analytics:export-data
# → storage/app/analytics-export-<tanggal>.json

# Kirim file ke server (scp/SFTP), lalu DI SERVER:
php artisan analytics:import-data --path=/path/analytics-export-<tanggal>.json
```

Impor bersifat **idempoten** (aman dijalankan berulang): `places` dicocokkan
lewat `serpapi_data_id`/`name`, review lewat `place_id + review_key`, sehingga
tidak menggandakan data. Statistik tempat (`reviews_synced`, tanggal) dihitung
ulang otomatis.

> Langkah lengkap (termasuk cara unggah file ke server) ada di
> [`docs/CARA_PINDAH_DATA.md`](CARA_PINDAH_DATA.md). File data yang sudah
> disiapkan: `storage/app/analytics-data-server.json` ( tidak ikut Git — unggah
> manual ke server).

---

## 13b. SSO dengan Portal Handayani (opsional)

Insight bisa memakai SSO ke Handayani Portal. **Default OFF** — aman untuk
deploy dulu tanpa SSO, lalu aktifkan menyusul.

Ringkas:

1. Daftarkan aplikasi "Insight Handayani" di **Portal → Aplikasi**, catat
   `client_id`/`client_secret`.
2. Isi `.env`:

   ```dotenv
   SSO_ENABLED=false               # ubah ke true setelah siap
   SSO_PORTAL_BASE_URL=https://portal.handayani.my.id
   SSO_CLIENT_ID=CHANGE_ME
   SSO_CLIENT_SECRET=CHANGE_ME
   SSO_REDIRECT_URI=https://insight.handayani.my.id/sso/callback
   ```

3. Tautkan user: `php artisan sso:assign-uuid <email>` lalu isi UUID itu di
   Portal → **Tautan Akun** (+ beri **Hak Akses**).
4. Set `SSO_ENABLED=true` + `php artisan config:clear`.

> Panduan lengkap: [`docs/SSO_INSIGHT.md`](SSO_INSIGHT.md).

### Perilaku saat SSO aktif

- Login manual **diblokir** (server-side) & diganti pop-up menuju Portal.
- **Logout** kembali ke Portal.
- `SSO_ENABLED=false` → login manual normal kembali (rollback cepat).

### URL admin & halaman depan

- Path panel diatur `ANALYTICS_ADMIN_PATH` (nilai acak di produksi).
- `/` otomatis mengarah ke `/<ANALYTICS_ADMIN_PATH>`, jadi tidak ada halaman
  depan terpisah maupun URL admin yang mudah ditebak.

---

## 14. Checklist sebelum go-live

- [ ] `.env` terisi & `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `php artisan key:generate --force` sudah dijalankan
- [ ] `migrate --force` + `db:seed --force` sukses
- [ ] `SERPAPI_KEY` valid (uji "Ambil Sekarang")
- [ ] `ANALYTICS_ADMIN_PATH` sudah diganti ke nilai acak
- [ ] Akun developer & user sudah dibuat (password kuat)
- [ ] Cron `schedule:run` aktif
- [ ] Document root ke `public`; `public/storage` ada
- [ ] Cache (`config`/`route`/`view`) sudah dibangun
- [ ] Kuota SerpApi harian diset sesuai paket Anda
- [ ] (Opsional) Data hasil backfill lokal sudah diimpor: `analytics:import-data`
- [ ] (Opsional SSO) `SSO_CLIENT_ID`/`SECRET` terisi & `SSO_ENABLED=true`
- [ ] (Opsional SSO) User sudah punya `portal_uuid` & ditautkan di Portal
- [ ] (Opsional SSO) `SSO_REDIRECT_URI` terdaftar persis di Portal
