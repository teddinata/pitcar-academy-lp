# Release checklist Pitcar Academy lead funnel

Checklist ini memisahkan pekerjaan yang dapat diselesaikan di repository Astro dari pekerjaan yang membutuhkan repository Laravel, kredensial deployment, atau keputusan bisnis.

## Laravel Lead API (wajib sebelum production)

- Implementasikan `POST /api/leads` sesuai `docs/lead-api-contract.md`.
- Validasi dan normalisasi semua input di server, termasuk nomor WhatsApp Indonesia.
- Gunakan unique index/idempotency untuk `submission_id`; retry ID yang sama tidak boleh membuat lead baru.
- Generate `lead_code` unik dan stabil.
- Jalankan scoring dan qualification hanya di backend; simpan score, qualification, alasan score, dan histori perubahan.
- Terapkan rate limit serta spam protection. Siapkan response teruji untuk `201`, `422`, `429`, dan `5xx`.
- Batasi CORS ke origin production dan staging yang benar.
- Simpan attribution dalam struktur yang dapat di-query dan hindari PII mentah di log.
- Bila CRM/notifikasi gagal setelah database insert, pertahankan lead yang sudah tersimpan dan retry integrasi lewat queue.
- Kembalikan `whatsapp_url` HTTPS dengan host `wa.me` atau `api.whatsapp.com`; backend sebaiknya memilih consultant berdasarkan routing bisnis.

## Environment staging/production

Isi seluruh nilai berikut pada build environment, bukan di source code:

```dotenv
SITE_URL=https://academy.pitcar.co.id
PUBLIC_LEAD_API_BASE_URL=
PUBLIC_EDUCATION_CONSULTANT_WHATSAPP=
PUBLIC_EDUCATION_CONSULTANT_WHATSAPP_DISPLAY=
PUBLIC_GA_ID=
```

`PUBLIC_EDUCATION_CONSULTANT_WHATSAPP` hanya fallback jika API sukses tanpa `whatsapp_url`, atau jika pengguna secara sadar memilih fallback setelah API gagal. Jangan menaruh secret dalam variabel `PUBLIC_*`.

## Staging end-to-end

- Submit lead baru dan pastikan database insert terjadi sebelum WhatsApp dibuka.
- Submit ulang `submission_id` yang sama dan pastikan tidak ada duplikasi.
- Verifikasi response direct dan response berbungkus `{ "data": ... }`.
- Verifikasi mapping error `422`, rate limit `429`, timeout, offline, malformed JSON, dan `5xx`.
- Pastikan URL WhatsApp berisi `lead_code` dan ringkasan yang benar.
- Pastikan URL WhatsApp selain host yang diizinkan ditolak frontend.
- Verifikasi pending lead dapat di-retry dalam tujuh hari dan tidak bertahan setelah sukses.
- Verifikasi CTA paket memilih program yang sesuai pada mobile dan desktop.

## Analytics

- Tandai seluruh delapan event funnel sebagai custom events di GA4 bila diperlukan.
- Daftarkan custom dimensions untuk `source_cta`, `program_interest`, UTM, `lead_code`, dan `qualification` sesuai kebijakan data.
- Gunakan DebugView/Tag Assistant untuk memastikan urutan event dari CTA sampai `whatsapp_open`.
- Jangan mengirim nama, nomor WhatsApp, domisili, atau jawaban qualification ke analytics.

## SEO dan performance setelah deploy

- Periksa canonical, Open Graph, Twitter Card, JSON-LD, `/robots.txt`, dan `/sitemap.xml` dari domain production.
- Submit sitemap ke Google Search Console.
- Jalankan Lighthouse mobile dan desktop terhadap deployment production; jangan menggunakan skor dev/local sebagai skor release.
- Periksa LCP element, cache header, Brotli/gzip, TTFB, font loading, lazy loading, dan CLS.
- Target awal performance mobile minimal 85; target produk tetap 95 bila dapat dicapai tanpa merusak conversion/tracking.

## Keputusan bisnis yang masih diperlukan

- Rule scoring final, threshold qualification, dan alasan score yang perlu terlihat oleh sales.
- Primary audience dan primary offer untuk revamp landing page berikutnya.
- Nomor/routing Education Consultant, jam layanan, assignment, dan SLA follow-up.
- Definisi status lead, lost reason, notifikasi, CRM/dashboard, dan kebutuhan export.
- Validasi klaim batch, promo, kuota, mess, sertifikasi, peluang rekrutmen, jadwal, harga, serta cicilan.
- Privacy policy, dasar persetujuan, retention period, akses/hapus data, dan pemilik proses privasi.
- Bukti yang boleh dipublikasikan: alumni, testimoni, trainer, fasilitas, kurikulum, dan foto praktik.
