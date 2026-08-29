# Menjalankan semuanya di lokal

Dua aplikasi harus hidup bersamaan: Laravel (API + dashboard) di `:8000` dan
Astro (landing page) di `:4321`. Langkah di bawah sudah diuji dari database
kosong sampai lead hasil isi form muncul di dashboard.

## Prasyarat

| | Minimal | Cek |
| --- | --- | --- |
| Node | **18.20.8** (Astro 5) | `node -v` |
| PHP | 8.3 | `php -v` |
| Composer | 2.x | `composer -V` |

> **Node 16 tidak bisa.** `npm run dev` akan langsung berhenti dengan
> `Node.js v16.x is not supported by Astro`. Kalau pakai nvm:
> `nvm use 22` (atau `nvm install 22`) sebelum menjalankan perintah npm apa pun.

## 1. Backend

```bash
cd backend
composer install
cp .env.example .env          # lewati kalau .env sudah ada
php artisan key:generate
php artisan migrate
```

Buka `backend/.env` dan pastikan origin frontend diizinkan:

```dotenv
LEAD_ALLOWED_ORIGINS=http://localhost:4321,http://localhost:4331
LEAD_FALLBACK_CONSULTANT_WHATSAPP=628111222333
```

Buat admin pertama — kredensial dari environment, bukan ditulis di kode:

```bash
ADMIN_EMAIL=admin@pitcar.test \
ADMIN_PASSWORD=rahasia-panjang-1234 \
ADMIN_NAME="Admin Pitcar" \
php artisan db:seed --class=DashboardUserSeeder --force
```

Isi data contoh supaya dashboard tidak kosong (2 consultant + 8 lead yang
tersebar di semua band kualifikasi, dibuat lewat service intake asli sehingga
kode, skor dan routing-nya sama seperti produksi):

```bash
php artisan db:seed --class=DemoLeadSeeder --force
```

Jalankan:

```bash
php artisan serve --port=8000
```

## 2. Frontend

Terminal baru, dari root repo:

```bash
nvm use 22        # wajib kalau default node kamu masih 16
npm install
```

Buat `.env` di root:

```dotenv
SITE_URL=http://localhost:4321
PUBLIC_LEAD_API_BASE_URL=http://127.0.0.1:8000
PUBLIC_EDUCATION_CONSULTANT_WHATSAPP=628111222333
PUBLIC_EDUCATION_CONSULTANT_WHATSAPP_DISPLAY=+62 811-1222-333
PUBLIC_GA_ID=
```

```bash
npm run dev       # http://localhost:4321
```

## 3. Alamat

| | URL |
| --- | --- |
| Landing page | http://localhost:4321 |
| Dashboard | http://127.0.0.1:8000/admin |
| API | `POST http://127.0.0.1:8000/api/leads` |

## 4. Uji alur lengkap

1. Buka http://localhost:4321
2. Klik CTA salah satu paket — program di form harus **otomatis terpilih**.
3. Isi langkah 1 (nama, WhatsApp, domisili) lalu **Lanjutkan**.
4. Isi langkah 2, centang persetujuan, klik **Simpan & lanjut ke WhatsApp**.
5. Status berubah jadi `Data tersimpan dengan kode PA-2026-0000xx` **sebelum**
   WhatsApp terbuka. Itu inti dari perubahan ini — lead disimpan lebih dulu.
6. Buka dashboard, login, lead tadi ada di paling atas beserta skor dan
   consultant yang menerimanya.

Kalau tombol bertuliskan **"Lanjut ke WhatsApp"** (bukan "Simpan & lanjut"),
berarti `PUBLIC_LEAD_API_BASE_URL` tidak terbaca — form berjalan di mode
WhatsApp-langsung dan **tidak menyimpan lead**. Perbaiki `.env` lalu restart
`npm run dev`.

## 5. Uji jalur kegagalan

Matikan `php artisan serve`, lalu kirim form lagi. Yang benar terjadi:

- WhatsApp **tidak** terbuka otomatis;
- muncul pesan "Data belum masuk ke database";
- tersedia tombol coba lagi, salin ringkasan, dan lanjut via WhatsApp;
- salinan lead disimpan di perangkat sampai tujuh hari.

Nyalakan lagi backend-nya, klik **Coba simpan lagi** — lead tersimpan dengan
`submission_id` yang sama, jadi tidak ada duplikat.

## 6. Menguji mode tanpa API

Kosongkan `PUBLIC_LEAD_API_BASE_URL`, restart `npm run dev`. Form langsung
membuka WhatsApp dengan ringkasan lengkap dan baris
`Kode lead: belum tersedia`. Tidak ada lead yang tersimpan — ini mode untuk
merilis landing page sebelum API siap.

## Menjalankan test

```bash
# backend: 108 test
cd backend && php artisan test

# frontend: type check dan build
npm run check
npm run build
```

## Masalah yang sering muncul

| Gejala | Sebab |
| --- | --- |
| `Node.js v16 is not supported` | `nvm use 22` |
| Form error jaringan, backend hidup | origin belum ada di `LEAD_ALLOWED_ORIGINS`; `php artisan config:clear` |
| Tombol tertulis "Lanjut ke WhatsApp" | `PUBLIC_LEAD_API_BASE_URL` kosong atau belum restart dev server |
| `/api/api/leads` di network tab | base URL kelebihan `/api` |
| `429 Terlalu banyak percobaan` | batas 3 lead/jam per nomor. Pakai nomor lain, atau naikkan `LEAD_RATE_LIMIT_PER_WHATSAPP` |
| Dashboard kosong | `php artisan db:seed --class=DemoLeadSeeder --force` |
| Tidak bisa login | user belum di-seed, atau `is_active` false |

## Reset

```bash
cd backend
php artisan migrate:fresh --force
ADMIN_EMAIL=admin@pitcar.test ADMIN_PASSWORD=rahasia-panjang-1234 \
  php artisan db:seed --class=DashboardUserSeeder --force
php artisan db:seed --class=DemoLeadSeeder --force
```
