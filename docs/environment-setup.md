# Panduan environment

Dua aplikasi, dua environment terpisah:

- **Frontend Astro** (root repo) — static site, variabel dibaca **saat build**.
- **Backend Laravel** (`backend/`) — variabel dibaca **saat runtime**.

Perbedaan ini penting dan sering jadi sumber bug. Baca bagian "Kesalahan yang sering terjadi" sebelum deploy.

---

## Frontend (Astro)

### Variabel

| Variabel | Wajib | Contoh | Keterangan |
| --- | --- | --- | --- |
| `SITE_URL` | ya | `https://academy.pitcar.co.id` | Dasar canonical, `og:url`, sitemap, robots. Tanpa trailing slash, tanpa path. |
| `PUBLIC_LEAD_API_BASE_URL` | tidak | `https://api.academy.pitcar.co.id` | **Origin saja**, tanpa `/api`. Kosong = mode WhatsApp-langsung. |
| `PUBLIC_EDUCATION_CONSULTANT_WHATSAPP` | ya | `628123456789` | Nomor fallback. Digit saja. |
| `PUBLIC_EDUCATION_CONSULTANT_WHATSAPP_DISPLAY` | tidak | `+62 812-3456-789` | Yang ditampilkan di footer. Boleh diformat. |
| `PUBLIC_GA_ID` | tidak | `G-XXXXXXXXXX` | Kosong = analytics tidak dimuat sama sekali. |

### Cara mengisi

**Lokal** — buat `.env` di root (sudah di-gitignore):

```dotenv
SITE_URL=https://academy.pitcar.co.id
PUBLIC_LEAD_API_BASE_URL=http://127.0.0.1:8000
PUBLIC_EDUCATION_CONSULTANT_WHATSAPP=628123456789
PUBLIC_EDUCATION_CONSULTANT_WHATSAPP_DISPLAY=+62 812-3456-789
PUBLIC_GA_ID=
```

**Production / CI** — tidak perlu file `.env`. Set sebagai environment variable di
Vercel, Netlify, Cloudflare Pages, atau GitHub Actions; Astro membacanya dari
process environment saat build. Sudah diverifikasi di repo ini.

```bash
SITE_URL=https://academy.pitcar.co.id \
PUBLIC_LEAD_API_BASE_URL=https://api.academy.pitcar.co.id \
PUBLIC_EDUCATION_CONSULTANT_WHATSAPP=628123456789 \
PUBLIC_GA_ID=G-XXXXXXXXXX \
npm run build
```

### Cara memastikan nilainya benar

Setelah build, nilainya ada di dalam bundle:

```bash
grep -o 'api\.academy\.pitcar\.co\.id' dist/_astro/*.js   # base URL masuk
grep -o '<span data-submit-label>[^<]*</span>' dist/index.html
```

Label tombol adalah indikator paling cepat:

- `Simpan & lanjut ke WhatsApp` → API terkonfigurasi, lead akan disimpan dulu.
- `Lanjut ke WhatsApp` → mode WhatsApp-langsung, **tidak ada lead yang tersimpan**.

---

## Kesalahan yang sering terjadi

**1. Menambahkan `/api` di base URL.**
Frontend menambahkan `/api/leads` sendiri.

```dotenv
PUBLIC_LEAD_API_BASE_URL=https://api.academy.pitcar.co.id       # benar
PUBLIC_LEAD_API_BASE_URL=https://api.academy.pitcar.co.id/api   # salah → /api/api/leads
```

**2. Lupa skema.** `new URL()` akan gagal dan form menampilkan error.

```dotenv
PUBLIC_LEAD_API_BASE_URL=api.academy.pitcar.co.id    # salah
PUBLIC_LEAD_API_BASE_URL=https://api.academy...      # benar
```

**3. HTTP dari halaman HTTPS.** Browser memblokir mixed content. Production harus HTTPS.

**4. Mengubah env lalu hanya restart.** `PUBLIC_*` **ditanam saat build**, bukan
dibaca saat runtime. Setiap perubahan wajib **rebuild + redeploy**. Ini beda
dengan Laravel yang cukup `php artisan config:clear`.

**5. Menaruh rahasia di `PUBLIC_*`.** Semua nilai `PUBLIC_*` ikut ke bundle
browser dan bisa dibaca siapa pun. API key, token WhatsApp Business, kredensial
CRM — semuanya hanya di backend.

**6. Nomor WhatsApp salah format.** Harus digit saja dengan awalan `62`:

```dotenv
PUBLIC_EDUCATION_CONSULTANT_WHATSAPP=628123456789     # benar
PUBLIC_EDUCATION_CONSULTANT_WHATSAPP=+62 812-3456-789 # salah, link wa.me rusak
```

**7. Lupa mendaftarkan origin frontend di CORS backend.** Form akan gagal dengan
error jaringan meski API hidup. Lihat `LEAD_ALLOWED_ORIGINS` di bawah.

---

## Backend (Laravel)

`backend/.env`. Nilai di bawah dibaca saat runtime — cukup `php artisan config:clear`.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.academy.pitcar.co.id

DB_CONNECTION=mysql
DB_DATABASE=pitcar_academy
DB_USERNAME=
DB_PASSWORD=

# Origin frontend yang boleh memanggil API. Production tanpa localhost.
LEAD_ALLOWED_ORIGINS=https://academy.pitcar.co.id

# Nomor cadangan bila tidak ada consultant yang cocok.
LEAD_FALLBACK_CONSULTANT_WHATSAPP=628123456789

LEAD_CODE_PREFIX=PA
LEAD_SCORING_VERSION=2026-01
LEAD_RATE_LIMIT_PER_IP=10
LEAD_RATE_LIMIT_PER_WHATSAPP=3

# Kosong = data lead disimpan selamanya. Isi setelah privacy policy diputuskan.
LEAD_RETENTION_DAYS=
LEAD_EXPORT_FULL_NUMBER=true

# Admin pertama untuk dashboard (dipakai sekali oleh DashboardUserSeeder).
ADMIN_NAME=
ADMIN_EMAIL=
ADMIN_PASSWORD=

QUEUE_CONNECTION=database
```

---

## Pasangan nilai per environment

| | Frontend `PUBLIC_LEAD_API_BASE_URL` | Backend `LEAD_ALLOWED_ORIGINS` |
| --- | --- | --- |
| Lokal | `http://127.0.0.1:8000` | `http://localhost:4321,http://localhost:4331` |
| Staging | `https://api-staging.academy.pitcar.co.id` | `https://staging.academy.pitcar.co.id` |
| Production | `https://api.academy.pitcar.co.id` | `https://academy.pitcar.co.id` |

Keduanya harus cocok. Kalau tidak, browser memblokir request dan form jatuh ke
mode gagal — pengunjung tetap dapat tombol WhatsApp, tapi lead tidak tersimpan.

---

## Urutan go-live

1. Deploy backend, `php artisan migrate --force`.
2. Seed admin: `ADMIN_EMAIL=... ADMIN_PASSWORD=... php artisan db:seed --class=DashboardUserSeeder --force`.
3. Login ke `/admin`, isi roster Education Consultant.
4. Set `LEAD_ALLOWED_ORIGINS` ke origin frontend production.
5. Set `PUBLIC_LEAD_API_BASE_URL` di build frontend, lalu **rebuild dan redeploy**.
6. Submit satu lead percobaan; pastikan muncul di dashboard **sebelum** WhatsApp terbuka.
7. Jalankan Lighthouse terhadap domain production.
8. Hapus lead percobaan dari dashboard.
