# SSO — INSIGHT HANDAYANI ↔ PORTAL HANDAYANI

Insight Handayani bisa memakai **SSO (Opsi B)** ke Handayani Portal: Portal
jadi "pintu" identitas, Insight jadi aplikasi client.

> **Default OFF.** SSO hanya aktif bila `SSO_ENABLED=true`. Selama OFF, login
> manual di Insight berjalan normal — sehingga Anda bisa deploy dulu tanpa
> SSO, lalu mengaktifkannya menyusul.

---

## Apa yang berubah saat SSO AKTIF

| Perilaku | SSO OFF (default) | SSO ON |
|---|---|---|
| Halaman login Insight | Form login manual terbuka | **Ditutup pop-up** "Masuk melalui Portal Handayani" |
| Login manual (server) | Berfungsi | **Ditolak** (server-side, bukan hanya UI) |
| Tombol login | — | Mengarah ke `/sso/login` → Portal |
| Logout | Kembali ke login Insight | **Kembali ke Portal** |
| Root `/` | Redirect ke path panel | Redirect ke path panel (sama) |

---

## 1. Daftarkan aplikasi di Portal

Di **Portal Handayani** → menu **Aplikasi**, buat aplikasi baru:

| Kolom | Nilai contoh |
|---|---|
| Nama | Insight Handayani |
| Slug | `insight-handayani` (CHANGE_ME sesuai selera) |
| Base URL | `https://insight.handayani.my.id` (CHANGE_ME) |
| SSO login path | `/sso/login` |
| Status | `active` |

Setelah tersimpan, Portal menghasilkan **client_id** & **client_secret**.
Catat keduanya — akan dipakai di `.env` Insight.

> Pastikan `redirect_uri` yang dipakai Insight **persis** terdaftar di Portal
> (biasanya `https://insight.handayani.my.id/sso/callback`).

---

## 2. Isi `.env` di Insight

```dotenv
SSO_ENABLED=false                # ubah ke true setelah semua siap
SSO_PORTAL_BASE_URL=https://portal.handayani.my.id
SSO_CLIENT_ID=CHANGE_ME
SSO_CLIENT_SECRET=CHANGE_ME
SSO_REDIRECT_URI=https://insight.handayani.my.id/sso/callback
SSO_STATE_TTL=300
```

Setelah mengisi, jalankan:

```bash
php artisan config:clear
```

---

## 3. Tautkan user (portal_uuid)

SSO **tidak** membuat user otomatis. Setiap user Insight yang akan login via
Portal harus punya `portal_uuid` yang **sama** dengan yang ada di Portal
(menu **Tautan Akun**).

Generate UUID lokal & tampilkan:

```bash
php artisan sso:assign-uuid admin@handayani.local
# → membuat portal_uuid baru & menampilkan nilainya
```

Atau tetapkan UUID tertentu (bila UUID sudah ada dari Portal):

```bash
php artisan sso:assign-uuid admin@handayani.local --uuid=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
```

Cek tanpa mengubah:

```bash
php artisan sso:assign-uuid admin@handayani.local --show
```

Lalu **masukkan UUID yang sama** di Portal → **Tautan Akun** untuk user itu.
Sekaligus pastikan user punya akses ke aplikasi di Portal (**Hak Akses**).

---

## 4. Aktifkan SSO

```bash
# di server, setelah client_id/secret & portal_uuid siap:
SSO_ENABLED=true
php artisan config:clear
```

Uji:
1. Buka `https://insight.handayani.my.id/` → redirect ke panel.
2. Karena belum login, muncul halaman login dengan **pop-up Portal**.
3. Klik "Login dengan Portal Handayani" → diarahkan ke Portal.
4. Setelah login di Portal → kembali ke Insight → masuk ke dashboard.
5. Klik logout → kembali ke halaman utama Portal.

---

## 5. Rollback (bila Portal bermasalah)

Cukup set `SSO_ENABLED=false` lalu `php artisan config:clear`. Login manual
langsung aktif kembali — tidak ada user yang terkunci.

---

## Catatan keamanan

- `client_secret` **jangan** masuk repo (sudah di `.gitignore` via `.env`).
- SSO memerlukan **HTTPS** di lingkungan non-lokal (dijaga di `SsoClientService`).
- `state` divalidasi satu kali pakai (cegah CSRF/replay) + TTL.
- Tidak ada `portal_uuid` tak dikenal → **ditolak** (tanpa auto-create).
