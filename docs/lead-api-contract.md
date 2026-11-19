# Lead API Contract

Frontend Pitcar Academy tetap berupa static Astro site. Penyimpanan, deduplikasi, scoring, dan integrasi CRM dijalankan oleh backend Laravel.

> **Status:** kontrak ini sudah diimplementasikan di `backend/` (Laravel 13).
> Lihat `backend/README.md` untuk konfigurasi, routing consultant, dan test.

## Configuration

```dotenv
PUBLIC_LEAD_API_BASE_URL=https://api.example.com
PUBLIC_EDUCATION_CONSULTANT_WHATSAPP=6281234567890
```

Nilai `PUBLIC_*` masuk ke bundle browser dan **bukan tempat untuk API key atau credential**. Secret CRM, token WhatsApp API, database credential, dan aturan scoring privat hanya boleh berada di backend Laravel.

Frontend membentuk endpoint berikut dari base URL:

```text
POST {PUBLIC_LEAD_API_BASE_URL}/api/leads
```

## Request

Headers:

```http
Accept: application/json
Content-Type: application/json
X-Submission-Id: web-unique-id
```

Body:

```json
{
  "submission_id": "82dc1c18-106d-4b48-a93b-b26acbc86a4e",
  "name": "Budi",
  "whatsapp_number": "6281234567890",
  "domicile": "Purwokerto",
  "goal": "mechanic_career",
  "readiness": "need_payment_plan",
  "program_interest": "basic",
  "source_cta": "package_basic",
  "source": "website",
  "consent_at": "2026-08-29T08:00:00.000Z",
  "attribution": {
    "landing_page": "https://academy.pitcar.co.id/?utm_source=instagram",
    "referrer": "https://instagram.com/",
    "utm_source": "instagram",
    "utm_medium": "paid_social",
    "utm_campaign": "batch_october",
    "utm_content": "mechanic_video",
    "utm_term": null
  }
}
```

Enum yang saat ini dikirim frontend:

- `goal`: `mechanic_career`, `upskill`, `open_workshop`, `automotive_knowledge`
- `readiness`: `nearest_batch`, `family_discussion`, `need_payment_plan`, `exploring`
- `program_interest`: `basic`, `advanced`, `professional`, `undecided`

### Short form (sejak 2026-08)

Form dipangkas dari 8 field menjadi 6 — tiga diketik, tiga tinggal tap —
karena tujuan landing page adalah memasukkan sebanyak mungkin orang ke funnel,
bukan menyelesaikan qualification di halaman pertama.

Yang dihapus dan alasannya:

- `activity` — nice-to-have, bukan penentu konversi. Education Consultant bisa
  menanyakannya gratis di WhatsApp.
- `timeline` dan `investment_readiness` — digabung menjadi satu pertanyaan
  `readiness`. Menanyakan kesiapan finansial di halaman pertama membuat lead
  mundur sebelum sempat mengobrol.

Ketiganya masih **diterima** backend sebagai `nullable`, supaya landing page
lama yang masih ter-cache di browser pengunjung tidak mendadak kena `422`.
Kolomnya tetap ada di database agar lead lama tidak kehilangan data.

Sisa qualification dikerjakan saat percakapan: skor awal dihitung dari form,
lalu diperbarui consultant setelah bicara. Itu lebih sehat daripada memaksa
satu form panjang menilai kualitas lead di muka.

`submission_id` harus memiliki unique index atau idempotency handling. Retry dari browser menggunakan ID yang sama agar gangguan jaringan tidak membuat lead duplikat.

## Success response

`201 Created` untuk lead baru, `200 OK` untuk replay idempotent dari
`submission_id` yang sudah tersimpan (body identik, `message` berbeda):

```json
{
  "lead_code": "PA-2026-000123",
  "score": 78,
  "qualification": "qualified",
  "whatsapp_url": "https://wa.me/6281234567890?text=...",
  "message": "Lead berhasil dibuat"
}
```

Frontend juga menerima object yang dibungkus dalam `{ "data": { ... } }`. Field wajib hanya `lead_code`; `score`, `qualification`, `whatsapp_url`, dan `message` boleh `null` atau belum tersedia. Jika backend mengembalikan `whatsapp_url`, frontend hanya menerima HTTPS URL dengan host `wa.me` atau `api.whatsapp.com`.

Backend sebaiknya menghasilkan `whatsapp_url` agar routing consultant dapat ditentukan berdasarkan program, domisili, jam kerja, atau qualification. Bila URL tidak dikirim, frontend membentuk fallback dari `PUBLIC_EDUCATION_CONSULTANT_WHATSAPP`.

## Mode tanpa API

Bila `PUBLIC_LEAD_API_BASE_URL` kosong saat build, frontend tidak memanggil API
sama sekali. Form tetap mengumpulkan data yang sama, lalu langsung membuka
WhatsApp dengan ringkasan lengkap dan baris `Kode lead: belum tersedia`. Tidak
ada lead yang tersimpan di database pada mode ini.

Mode ini ada agar landing page dapat dirilis sebelum API hidup tanpa
menampilkan error ke pengunjung. Seluruh event funnel membawa parameter
`delivery_mode` (`api` atau `whatsapp_direct`) sehingga kedua mode dapat
dipisahkan di GA4.

## Error responses

Validasi menggunakan status `422`:

```json
{
  "message": "Data lead belum lengkap.",
  "errors": {
    "whatsapp_number": ["Nomor WhatsApp tidak valid."]
  }
}
```

Gunakan `429` untuk rate limit (dengan header `Retry-After`), `413` untuk
payload yang terlalu besar, dan `5xx` untuk gangguan server. Pada timeout, error jaringan, atau respons yang tidak valid, frontend:

1. tidak membuka WhatsApp secara otomatis;
2. memberi tahu bahwa data belum masuk database;
3. menyimpan satu pending lead di perangkat selama maksimal tujuh hari;
4. menawarkan retry, copy ringkasan, dan WhatsApp fallback bila nomor consultant dikonfigurasi.

## Laravel implementation notes

- Jadikan endpoint stateless di route API dan allow CORS hanya untuk domain production/staging yang diperlukan.
- Validasi dan normalisasi nomor WhatsApp di server; jangan mempercayai nilai score dari browser.
- Simpan attribution sebagai kolom terstruktur atau JSON yang dapat di-query.
- Jalankan lead scoring di server agar rule dapat berubah tanpa rebuild landing page.
- Simpan `score`, `qualification`, alasan scoring, serta timestamp perubahan status untuk audit funnel.
- Terapkan rate limiting, spam protection, logging tanpa menulis PII mentah secara berlebihan, dan retention policy sesuai kebijakan bisnis.
- Jika CRM/notification gagal setelah insert database, tetap kembalikan lead yang sudah tersimpan dan retry integrasi melalui queue.

## Scoring

Tiga sinyal dari form: kesiapan (maks 40), tujuan (maks 30), program (maks 30).
Bobotnya diskalakan agar jawaban terbaik berjumlah tepat **100** — field-nya
bernama `score` dan akan dibaca sebagai persentase entah memang begitu atau
tidak, jadi plafon 80 membuat lead sempurna terlihat kurang.

Poin tetap menjumlah persis ke skor yang disimpan, sehingga `scoring_reasons`
bisa dibaca consultant sebagai penjelasan angkanya.

Threshold: `hot` >= 85, `qualified` >= 70, `nurture` >= 55. Tiga pertanyaan
hanya menghasilkan 23 skor berbeda, jadi band-nya tidak bisa jatuh di angka
bulat tanpa merusak sebaran. Angka ini memberi hot 14% / qualified 33% /
nurture 39% / low 14%.

**Semua angka di atas masih menunggu persetujuan sales.** Ubah di
`config/leads.php` dan naikkan `LEAD_SCORING_VERSION` agar skor lama tetap
dapat dijelaskan.
