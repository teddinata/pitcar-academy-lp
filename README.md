# Pitcar Academy Landing Page

Landing page conversion-oriented untuk Pitcar Academy. Funnel utama: CTA → short lead form → Laravel Lead API → WhatsApp Education Consultant.

## 📁 Struktur Proyek

```
pitcar-academy-lp/
├── public/                 # Static image and favicon assets
├── src/
│   ├── components/         # Reusable components
│   │   ├── Header.astro
│   │   ├── Footer.astro
│   │   ├── HeroSection.astro
│   │   ├── PackageCard.astro
│   │   ├── AdvantageSection.astro
│   │   ├── FaqSection.astro
│   │   ├── CtaSection.astro
│   │   ├── LeadForm.astro
│   │   ├── FunnelAnalytics.astro
│   │   ├── SeoHead.astro
│   │   └── WhatsAppButton.astro
│   ├── content.config.ts   # ← EDIT INI untuk update konten!
│   ├── lib/leads.ts        # Typed Lead API client dan WhatsApp fallback
│   ├── layouts/
│   │   └── BaseLayout.astro
│   ├── pages/
│   │   ├── index.astro     # Landing page utama
│   │   ├── faq.astro       # FAQ page
│   │   ├── robots.txt.ts   # Generated dari SITE_URL
│   │   ├── sitemap.xml.ts  # Generated dari SITE_URL
│   │   └── admin/          # RESERVED — LMS placeholder
│   │       └── index.astro
│   └── styles/
│       └── global.css
├── backend/                # Laravel Lead API (POST /api/leads) — see backend/README.md
├── docs/                   # Lead API contract, release checklist, revamp proposal
├── astro.config.mjs
├── tailwind.config.js
├── Dockerfile
├── nginx.conf
└── package.json
```

## 🚀 Quick Start

### Development
```bash
npm install
cp .env.example .env
npm run dev        # http://localhost:4321
```

Node 18.20.8+ is required (Astro 5). The lead API lives in `backend/`:

```bash
cd backend && composer install && php artisan migrate && php artisan serve
```

Then set `PUBLIC_LEAD_API_BASE_URL=http://127.0.0.1:8000` in `.env` and restart
the dev server.

### Two delivery modes

The form adapts to whether a lead API is configured at build time:

| `PUBLIC_LEAD_API_BASE_URL` | Behaviour |
| --- | --- |
| set | Lead is stored first; WhatsApp opens only after a successful response, carrying the `lead_code`. On failure nothing auto-opens — the visitor gets retry, copy-summary and a WhatsApp fallback. |
| empty | WhatsApp-direct: the API call is skipped and WhatsApp opens with the full summary. No lead row is created. |

The second mode exists so the landing page can ship before the API is live
without showing visitors an error. Analytics tags every funnel event with
`delivery_mode` (`api` or `whatsapp_direct`) so the two are separable in GA4.

### Production Build
```bash
npm run build      # Output ke dist/
npm run preview    # Preview build locally
```

## ⚙️ Konfigurasi Konten

Edit `src/content.config.ts` untuk mengubah konten bisnis tanpa menyentuh komponen:

- **Paket Belajar**: Edit array `packages[]`
- **Keunggulan**: Edit array `advantages[]`
- **FAQ**: Edit array `faqs[]`
- **SEO Meta Tags**: Di masing-masing halaman (`index.astro`, `faq.astro`)

Konfigurasi integrasi melalui environment variables:

```dotenv
SITE_URL=https://academy.pitcar.co.id
PUBLIC_LEAD_API_BASE_URL=https://api.example.com
PUBLIC_EDUCATION_CONSULTANT_WHATSAPP=6281234567890
PUBLIC_EDUCATION_CONSULTANT_WHATSAPP_DISPLAY=+62 812-3456-7890
PUBLIC_GA_ID=G-XXXXXXXXXX
```

Semua `PUBLIC_*` terlihat di browser. Jangan menyimpan API key, token, atau credential Laravel/CRM di repository ini.

## Lead funnel

1. CTA mengarah ke form `#konsultasi`; CTA paket otomatis memilih program.
2. Form menangkap data kontak, qualification fields, UTM, referrer, dan sumber CTA.
3. Frontend mengirim typed payload ke `POST /api/leads`.
4. Setelah sukses, frontend membuka `whatsapp_url` dari backend atau membuat fallback URL dari nomor consultant.
5. Bila API gagal, pengguna diberi peringatan jelas dan pending lead disimpan lokal untuk retry—bukan dibuang diam-diam.

Attribution first-touch disimpan selama sesi agar UTM dan source CTA tidak hilang ketika pengunjung berpindah antara homepage dan FAQ. Analytics funnel mengirim event berikut tanpa PII:

- `cta_click`
- `lead_form_view`
- `lead_form_start`
- `lead_form_step_1_complete`
- `lead_submit`
- `lead_submit_success`
- `lead_submit_failed`
- `whatsapp_open`

Event membawa `source_cta`, `program_interest`, dan UTM. Event sukses/WhatsApp juga membawa `lead_code` serta `qualification` bila sudah tersedia.

Kontrak lengkap Laravel: [`docs/lead-api-contract.md`](docs/lead-api-contract.md).

## SEO dan social preview

- `astro.config.mjs` adalah sumber origin situs.
- Canonical, `og:url`, `og:image`, dan Twitter Card dinormalisasi menggunakan `URL`, sehingga input relatif maupun absolut tidak akan di-join dua kali.
- `sitemap.xml` dibuat saat build dari `src/pages/sitemap.xml.ts`.
- `robots.txt` dibuat saat build dari `src/pages/robots.txt.ts` agar domain staging/production mengikuti `SITE_URL`.
- JSON-LD organisasi, program, penawaran, dan FAQ dirender di `<head>`.
- OG image tersedia di `public/og-image.webp` dengan ukuran 1200×630.

Checklist integrasi backend, staging, analytics, dan release tersedia di [`docs/release-checklist.md`](docs/release-checklist.md).

## 🐳 Deploy ke VPS (Docker)

```bash
# Build image
docker build -t pitcar-academy-lp .

# Run container
docker run -d \
  --name pitcar-academy \
  --restart unless-stopped \
  -p 80:80 \
  -v /etc/letsencrypt:/etc/letsencrypt:ro \
  pitcar-academy-lp
```

## 🔒 Nginx + Let's Encrypt

```bash
# Install certbot
sudo apt install certbot python3-certbot-nginx

# Get certificate
sudo certbot --nginx -d academy.pitcar.co.id

# Restart nginx
sudo systemctl restart nginx
```

## 🔧 Deploy via GitHub Actions

```yaml
# .github/workflows/deploy.yml
name: Deploy to VPS
on:
  push:
    branches: [main]

jobs:
  build-deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: 20
          
      - name: Install & Build
        run: |
          npm ci
          npm run build
          
      - name: Deploy to VPS
        uses: appleboy/scp-action@master
        with:
          host: ${{ secrets.VPS_HOST }}
          username: ${{ secrets.VPS_USER }}
          key: ${{ secrets.SSH_KEY }}
          source: "dist/*"
          target: "/var/www/pitcar-academy/"
          
      - name: Reload Nginx
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.VPS_HOST }}
          username: ${{ secrets.VPS_USER }}
          key: ${{ secrets.SSH_KEY }}
          script: sudo systemctl reload nginx
```

## 📱 Routes

| Route | Deskripsi |
|-------|-----------|
| `/` | Landing Page utama |
| `/faq` | Halaman FAQ lengkap |
| `/admin` | Placeholder — siap jadi LMS (React/Vue) |

## 🔮 Roadmap ke LMS

Untuk menambahkan LMS di `/admin`:

1. Hapus `src/pages/admin/index.astro`
2. Buat app baru: `cd src/pages/admin && npx create-react-app .` atau `npm create vue@latest`
3. Update `nginx.conf` dengan proxy pass ke dev server saat development
4. Atau gunakan monorepo approach (Astro root + React/Vue subdir)

---

Built with Astro + Tailwind CSS • Hosted on VPS
