# CARA PINDAH DATA LOKAL → SERVER

Data analitik (review, snapshot, statistik harian) disimpan di **database**,
bukan di Git. Jadi saat deploy, setelah aplikasi & migrasi siap di server,
pindahkan datanya dengan 2 langkah: **ekspor di lokal → impor di server**.

## File yang sudah disiapkan

```
storage/app/analytics-data-server.json   ← data hasil backfill lokal
```

File ini **tidak ikut Git** (ada di `.gitignore`), jadi unggah manual ke server.

---

## Langkah di LOKAL (sudah selesai)

```bash
php artisan analytics:export-data --path=storage/app/analytics-data-server.json
```

> Ingin ekspor ulang nanti? Jalankan perintah di atas kapan saja sebelum deploy.

---

## Langkah di SERVER

1. **Siapkan aplikasi & database dulu** (urutan penting):

   ```bash
   cd ~/handayani-analytics
   php artisan migrate --force
   php artisan db:seed --force   # bila ada seeder akun
   ```

2. **Unggah** `analytics-data-server.json` ke server, mis. ke `/tmp`:

   ```bash
   # dari komputer lokal:
   scp storage/app/analytics-data-server.json banksam6@calm:/tmp/
   ```

3. **Impor** di server:

   ```bash
   cd ~/handayani-analytics
   php artisan analytics:import-data --path=/tmp/analytics-data-server.json
   ```

   Keluaran yang diharapkan:
   ```
   places: 2 diproses (0 baru).
   reviews: 664 diproses.
   rating_snapshots: 3 diproses.
   daily_review_stats: 2 diproses.
   statistik tempat: diperbarui.
   Impor selesai: 664 review, 3 snapshot, 2 statistik harian.
   ```

---

## Catatan aman

- **Idempoten** — aman dijalankan berulang. `places` dicocokkan lewat
  `serpapi_data_id`/`name`, review lewat `place_id + review_key`, sehingga
  **tidak** menggandakan data.
- Setelah impor, pengaturan **jadwal** per tempat (mode/jam) tetap dari seeder
  server. Bila ingin menyamakan, atur lewat menu **Pengaturan Analisis**.
- Setelah impor, mulai besok **sync harian** akan menambah review baru
  otomatis (1–2 request/hari/tempat).
- **Jangan** pakai `--truncate` kecuali Anda yakin ingin mengosongkan dulu.
