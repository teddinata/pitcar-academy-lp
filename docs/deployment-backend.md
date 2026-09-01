# Deploy backend Lead API

Frontend sudah hidup di `academy.pitcar.co.id`. Dokumen ini untuk backend
Laravel yang ada di folder `backend/`.

## Subdomain, bukan sub-path

Kamu menawarkan dua opsi. **Ambil `api-academy.pitcar.co.id`.**

Sub-path `academy.pitcar.co.id/admin` punya satu keunggulan nyata — sama-origin,
jadi CORS tidak diperlukan. Tapi harganya lebih mahal daripada kelihatannya:

- **Laravel dan Filament di bawah sub-path itu rewel.** Filament membangun URL
  aset dan redirect dari `APP_URL`. Salah sedikit, hasilnya redirect loop
  setelah login atau CSS panel tidak termuat — kelas bug yang sulit dilacak dan
  muncul hanya di produksi.
- **Deploy jadi terikat.** Satu konfigurasi nginx melayani dua aplikasi. Setiap
  kali landing page dirilis ulang — dan itu sering, karena isinya konten —
  ada risiko menyentuh routing API lead.
- **Kalau frontend ada di static host** (Vercel, Netlify, CDN), sub-path bahkan
  tidak mungkin tanpa reverse proxy di depan keduanya.

Sementara biaya subdomain hanya CORS, yang **sudah dibangun, teruji, dan
punya test yang lulus**. Tidak ada pekerjaan baru di sana.

Catatan kecil: `api.academy.pitcar.co.id` lebih lazim daripada
`api-academy.pitcar.co.id`. Pakai yang mana pun asal konsisten — dokumen ini
memakai nama yang kamu sebut.

## 1. DNS

```
A    api-academy.pitcar.co.id   →   <IP server>
```

Tunggu sampai `dig api-academy.pitcar.co.id +short` mengembalikan IP itu
sebelum meminta sertifikat SSL.

## 2. Server

Butuh PHP **8.3+** (Laravel 13), Composer, nginx, dan sebuah database.
Periksa dulu apa yang sudah ada — server ini juga menjalankan aplikasi lain:

```bash
php -v
systemctl is-active mysql mariadb postgresql
```

Kalau PHP belum ada, ganti `8.3` di bawah dengan versi yang kamu pasang:

```bash
sudo apt update
sudo apt install -y nginx php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-intl php8.3-mysql unzip git
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

**Versi PHP menentukan path socket FPM di konfigurasi nginx nanti.** Jangan
menebak — baca dari sistem:

```bash
ls /run/php/
```

**Pakai MySQL atau PostgreSQL, bukan SQLite.** Lokal memakai SQLite karena
praktis, tapi intake lead menulis di dalam transaksi dengan `lockForUpdate()`
untuk menjaga `lead_code` tetap unik. SQLite mengunci seluruh file saat menulis,
jadi dua submit bersamaan akan saling menunggu.

```sql
CREATE DATABASE pitcar_academy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pitcar'@'localhost' IDENTIFIED BY '<password kuat>';
GRANT ALL PRIVILEGES ON pitcar_academy.* TO 'pitcar'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Kode dan pemisahan document root

Repo sudah ada di server, di `/var/www/pitcar-academy-lp`. Backend tinggal
dipasang dependensinya:

```bash
cd /var/www/pitcar-academy-lp/backend
composer install --no-dev --optimize-autoloader
```

`--no-dev` penting: tanpa itu Pint, PHPUnit, dan Faker ikut terpasang di server.

### Periksa dulu document root frontend

Satu direktori kini memuat dua hal: file statis landing page di `dist/`, dan
seluruh source code termasuk `backend/.env` yang sebentar lagi berisi password
database.

```bash
grep -rn "root .*pitcar-academy-lp" /etc/nginx/sites-enabled/
```

Yang benar hanya ini:

```nginx
root /var/www/pitcar-academy-lp/dist;
```

Kalau tertulis `root /var/www/pitcar-academy-lp;` tanpa `/dist`, **perbaiki
sebelum membuat `.env`**. Tanpa `/dist`, seluruh isi repo dapat diunduh siapa
pun — `backend/.env`, `.git/`, source code — cukup dengan menebak nama file.

Aturan `location ~ /\. { deny all; }` di blok frontend menutup `.env` dan
`.git`, tapi itu jaring pengaman, bukan pengganti document root yang benar.

## 4. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Isi `.env`:

```dotenv
APP_NAME="Pitcar Academy"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api-academy.pitcar.co.id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=pitcar_academy
DB_USERNAME=pitcar
DB_PASSWORD=<password kuat>

# Hanya origin yang benar-benar memanggil API. Tanpa localhost di produksi.
LEAD_ALLOWED_ORIGINS=https://academy.pitcar.co.id

LEAD_FALLBACK_CONSULTANT_WHATSAPP=6285742228865
LEAD_SCORING_VERSION=2026-03
LEAD_RATE_LIMIT_PER_IP=10
LEAD_RATE_LIMIT_PER_WHATSAPP=3

# Webhook Cekat AI. Dipanggil dari job setelah lead tersimpan, bukan dari
# browser — jadi tidak ada preflight dan tidak ada URL yang bocor ke bundle.
LEAD_WEBHOOK_URL=https://workflows.cekat.ai/webhook-test/wa-academy

# Queue wajib database. Dengan `sync`, job berjalan di dalam request dan
# webhook yang lambat ikut memperlambat balasan ke pengunjung.
QUEUE_CONNECTION=database

# Kosongkan sampai kebijakan privasi diputuskan; command retensi jadi no-op.
LEAD_RETENTION_DAYS=
```

`APP_DEBUG=false` tidak bisa ditawar. Debug menyala berarti stack trace —
lengkap dengan kredensial database — tampil ke siapa pun yang memicu error.

## 5. Migrasi dan data awal

```bash
php artisan migrate --force

# Admin pertama. Kredensial lewat environment, bukan ditulis di file.
ADMIN_EMAIL=<email kamu> \
ADMIN_PASSWORD='<password acak panjang>' \
ADMIN_NAME='<nama>' \
php artisan db:seed --class=DashboardUserSeeder --force
```

Lalu isi roster Education Consultant lewat dashboard (`/admin/education-consultants`).
Tabel yang kosong berarti semua lead jatuh ke nomor fallback.

**Jangan jalankan `DemoLeadSeeder` di produksi** — ia menolak berjalan saat
`APP_ENV=production`, tapi lebih baik tidak mencoba.

## 6. Izin file

```bash
sudo chown -R www-data:www-data /var/www/pitcar-academy-lp/backend/storage \
                                /var/www/pitcar-academy-lp/backend/bootstrap/cache
sudo chmod -R 775 /var/www/pitcar-academy-lp/backend/storage \
                  /var/www/pitcar-academy-lp/backend/bootstrap/cache
```

## 7. nginx

```nginx
server {
    listen 80;
    server_name api-academy.pitcar.co.id;
    root /var/www/pitcar-academy-lp/backend/public;

    index index.php;
    charset utf-8;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    client_max_body_size 2m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        # Samakan dengan hasil `ls /run/php/`.
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Jangan pernah menyajikan file tersembunyi; .env ada di atas root tapi
    # aturan ini menutup kesalahan konfigurasi di kemudian hari.
    #
    # `(?!well-known)` wajib: tanpa itu certbot tidak bisa menyajikan
    # tantangan HTTP-01 dari /.well-known/acme-challenge/ dan penerbitan
    # sertifikat gagal dengan 403 yang membingungkan.
    location ~ /\.(?!well-known).* { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/api-academy /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d api-academy.pitcar.co.id
```

### Kalau domainnya di belakang Cloudflare

Periksa dulu — kalau `getent hosts api-academy.pitcar.co.id` mengembalikan
alamat Cloudflare (`104.16.x.x`, `172.64.x.x`, `2606:4700::/32`) dan bukan IP
server, TLS diterminasi di Cloudflare.

**Jangan jalankan certbot.** Origin cukup melayani HTTP di port 80, sama seperti
frontend. Tantangan HTTP-01 lewat proxy bisa gagal, dan sertifikatnya pun tidak
akan dipakai siapa pun.

Yang wajib ada gantinya: aplikasi harus mempercayai proxy itu. Sudah
dikonfigurasi di `bootstrap/app.php` lewat `CloudflareProxies::all()`.
Tanpa itu dua hal rusak diam-diam:

- Semua pengunjung terlihat sebagai satu alamat Cloudflare, jadi
  `LEAD_RATE_LIMIT_PER_IP` berlaku global — pengunjung sah saling memblokir
- Skema terbaca `http`, jadi Filament menyisipkan URL `http://` ke halaman
  `https://` dan login bisa redirect-loop

Daftar rentangnya sengaja eksplisit, bukan `*`: IP origin tetap bisa dihubungi
langsung, dan `*` akan membuat `X-Forwarded-For` palsu cukup untuk melewati
rate limit. Diuji di `tests/Feature/TrustedProxyTest.php`.

## 8. Queue worker — wajib

`QUEUE_CONNECTION=database` dan `NotifyNewLead` adalah job. **Tanpa worker,
job menumpuk di tabel dan tidak pernah dijalankan.** Lead tetap tersimpan
(insert database tidak bergantung pada job), tetapi notifikasi tidak pernah
terkirim dan tidak ada yang memberi tahu.

`/etc/systemd/system/pitcar-worker.service`:

```ini
[Unit]
Description=Pitcar Academy queue worker
After=network.target

[Service]
User=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/pitcar-academy-lp/backend
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now pitcar-worker
sudo systemctl status pitcar-worker
```

## 9. Scheduler

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/pitcar-academy-lp/backend && php artisan schedule:run >> /dev/null 2>&1
```

Belum ada tugas terjadwal yang aktif. Ini disiapkan untuk
`leads:apply-retention-policy` begitu periode retensi diputuskan, dan untuk
alert SLA follow-up yang belum dibuat.

## 10. Cache produksi

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Ulangi setiap kali `.env` berubah — nilai lama akan tetap terpakai kalau tidak.

## 11. Sambungkan frontend

Landing page dibangun oleh GitHub Actions, jadi nilainya masuk sebagai
**repository secret** (Settings → Secrets and variables → Actions), bukan file
`.env` di server — server hanya menerima hasil build:

```dotenv
PUBLIC_LEAD_API_BASE_URL=https://api-academy.pitcar.co.id
PUBLIC_EDUCATION_CONSULTANT_WHATSAPP=6285742228865
PUBLIC_GA_ID=G-FNT01JRZN7
SITE_URL=https://academy.pitcar.co.id
```

Lalu **build ulang dan deploy ulang**. Nilai `PUBLIC_*` ditanam saat build,
bukan dibaca saat runtime — mengubah variabel tanpa rebuild tidak berpengaruh
apa pun.

Cara tercepat memastikan berhasil: tombol submit form. Bertuliskan
**"Simpan & lanjut ke WhatsApp"** berarti API terbaca. Kalau masih
**"Lanjut ke WhatsApp"**, variabelnya tidak sampai dan lead tidak tersimpan.

## 12. Verifikasi setelah deploy

```bash
# API menerima lead
curl -i -X POST https://api-academy.pitcar.co.id/api/leads \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -H 'Origin: https://academy.pitcar.co.id' \
  -d '{"submission_id":"cek-deploy-0001","name":"Uji Deploy",
       "whatsapp_number":"081234567890","domicile":"Purwokerto",
       "goal":"mechanic_career","readiness":"nearest_batch",
       "program_interest":"basic","source_cta":"deploy_check","source":"website",
       "consent_at":"'"$(date -u +%Y-%m-%dT%H:%M:%SZ)"'",
       "attribution":{"landing_page":"https://academy.pitcar.co.id/","referrer":null,
       "utm_source":null,"utm_medium":null,"utm_campaign":null,
       "utm_content":null,"utm_term":null}}'
```

Yang harus terlihat:

- `201 Created` dengan `lead_code`, `score`, `qualification`, `whatsapp_url`
- Kirim ulang payload yang sama → `200 OK`, `lead_code` sama, tidak ada duplikat

CORS diperiksa lewat preflight, bukan dari respons POST:

```bash
# Origin yang benar → harus ada Access-Control-Allow-Origin
curl -sS -D - -o /dev/null -X OPTIONS https://api-academy.pitcar.co.id/api/leads \
  -H 'Origin: https://academy.pitcar.co.id' \
  -H 'Access-Control-Request-Method: POST' | grep -i access-control

# Origin asing → tidak boleh ada Access-Control-Allow-Origin sama sekali
curl -sS -D - -o /dev/null -X OPTIONS https://api-academy.pitcar.co.id/api/leads \
  -H 'Origin: https://contoh-asing.com' \
  -H 'Access-Control-Request-Method: POST' | grep -ci access-control-allow-origin
```

Yang pertama harus memunculkan `Access-Control-Allow-Origin`,
`Access-Control-Allow-Methods: POST, OPTIONS`, dan daftar header yang
diizinkan. Yang kedua harus mengembalikan `0`.

Kalau yang pertama juga kosong, `LEAD_ALLOWED_ORIGINS` belum memuat origin itu
— atau `php artisan config:cache` belum dijalankan ulang setelah `.env` diubah.
- `https://api-academy.pitcar.co.id/robots.txt` melarang `/admin` dan `/api`
- Panel di `https://api-academy.pitcar.co.id/admin` meminta login
- Lead uji tadi muncul di dashboard, lalu hapus
- `sudo systemctl status pitcar-worker` aktif, dan log memuat `lead.created`

Terakhir, kirim satu lead lewat form di landing page sungguhan dan pastikan ia
tersimpan **sebelum** WhatsApp terbuka.

## 13. Sebelum menyebut selesai

- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` sudah di-generate
- [ ] `LEAD_ALLOWED_ORIGINS` tanpa localhost
- [ ] Password admin acak dan tidak pernah masuk git
- [ ] `.env` tidak dapat diakses dari web
- [ ] SSL aktif, HTTP redirect ke HTTPS
- [ ] Backup database terjadwal — di dalamnya ada data pribadi orang
- [ ] Roster consultant terisi
- [ ] Rule scoring sudah di-acc sales
- [ ] Periode retensi diputuskan dan `LEAD_RETENTION_DAYS` diisi

Dua item terakhir bukan urusan teknis, tetapi dashboard ini menyimpan nama dan
nomor WhatsApp orang yang belum menjadi pelanggan. Siapa yang boleh
mengaksesnya dan berapa lama data disimpan sebaiknya sudah punya jawaban
sebelum lead pertama masuk.

## Deploy berikutnya

```bash
cd /var/www/pitcar-academy-lp
git pull
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo systemctl restart pitcar-worker
```

Restart worker itu penting: worker memuat kode ke memori dan akan terus
menjalankan versi lama sampai di-restart.
