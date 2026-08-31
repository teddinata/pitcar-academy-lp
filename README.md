# Pitcar Academy Landing Page

Landing page conversion-oriented untuk Pitcar Academy — fokus WhatsApp CTA, SEO-friendly, responsif.

## 📁 Struktur Proyek

```
pitcar-academy-lp/
├── public/                 # Static assets (favicon, robots.txt)
├── src/
│   ├── components/         # Reusable components
│   │   ├── Header.astro
│   │   ├── Footer.astro
│   │   ├── HeroSection.astro
│   │   ├── PackageCard.astro
│   │   ├── AdvantageSection.astro
│   │   ├── FaqSection.astro
│   │   ├── CtaSection.astro
│   │   ├── SeoHead.astro
│   │   └── WhatsAppButton.astro
│   ├── content.config.ts   # ← EDIT INI untuk update konten!
│   ├── layouts/
│   │   └── BaseLayout.astro
│   ├── pages/
│   │   ├── index.astro     # Landing page utama
│   │   ├── faq.astro       # FAQ page
│   │   └── admin/          # RESERVED — LMS placeholder
│   │       └── index.astro
│   └── styles/
│       └── global.css
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
npm run dev        # http://localhost:4321
```

### Production Build
```bash
npm run build      # Output ke dist/
npm run preview    # Preview build locally
```

## ⚙️ Konfigurasi Konten

Edit `src/content.config.ts` untuk mengubah semua konten tanpa menyentuh kode:

- **Nomor WhatsApp**: Set `WA_PHONE_NUMBER` di file `.env` (format `62XXXXXXXXXX`)
- **Webhook pendaftaran**: Set `WA_WEBHOOK_URL` di file `.env`
- **Deploy GitHub Actions**: Tambahkan `WA_PHONE_NUMBER` dan `WA_WEBHOOK_URL` sebagai repository secrets
- **Paket Belajar**: Edit array `packages[]`
- **Keunggulan**: Edit array `advantages[]`
- **FAQ**: Edit array `faqs[]`
- **SEO Meta Tags**: Di masing-masing halaman (`index.astro`, `faq.astro`)

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
