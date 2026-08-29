# Audit conversion dan proposal revamp landing page

Dokumen ini adalah arahan P2. Implementasi visual besar sebaiknya dimulai setelah bukti bisnis dan prioritas audience dikonfirmasi. Lead form, API abstraction, attribution, dan funnel analytics yang sudah ada harus dipertahankan.

## Audit singkat kondisi saat ini

### Yang sudah kuat

- Satu tujuan utama: konsultasi program melalui short lead form.
- Harga, durasi, jam belajar, OJT, materi, outcome, dan tanggal mulai sudah terlihat pada kartu program.
- Hero memakai dokumentasi bengkel dan menjelaskan kombinasi teori, praktik, serta OJT.
- Klaim kerja sudah diberi batasan yang jujur di FAQ.
- CTA paket membawa program interest; CTA lintas halaman membawa source CTA dan first-touch UTM.
- Pengalaman mobile tidak memiliki horizontal overflow pada breakpoint utama.

### Gap conversion yang perlu ditutup

- Headline hero masih cukup generik dan belum segera menyebut audience utama yang paling diprioritaskan.
- Bukti kepercayaan belum muncul cukup awal; pengunjung melihat beberapa section penjelasan sebelum bukti trainer, fasilitas, alumni, atau hasil nyata.
- Rasio praktik dibanding teori belum dinyatakan sebagai angka yang dapat diverifikasi.
- Identitas dan kredibilitas instruktur belum spesifik.
- Testimoni/alumni serta outcome terverifikasi belum tersedia.
- Informasi cicilan masih diarahkan ke konsultasi tanpa penjelasan minimum tentang skema yang tersedia.
- Jadwal batch, tempat belajar, kuota, promo, mess, dan rekrutmen perlu satu sumber fakta yang disetujui bisnis agar copy tidak bertentangan.
- Privacy policy dan retention policy belum tersedia untuk mendukung consent form.
- Beberapa section masih memakai pola kartu berulang; revamp dapat memperkuat ritme dengan dokumentasi bengkel, kurikulum, dan bukti operasional yang lebih konkret.

## Information architecture yang disarankan

1. Announcement yang faktual dan dapat diperbarui.
2. Header ringkas: Program, Cara Belajar, Biaya, FAQ, CTA konsultasi.
3. Hero: audience utama + hasil yang realistis + bukti bengkel nyata + CTA.
4. Trust strip: fakta operasional terverifikasi, bukan vanity metrics.
5. Outcome: kompetensi yang dapat dilakukan setelah tiap level.
6. Metode belajar: teori, praktik, asesmen, lalu OJT—sertakan rasio bila sudah disahkan.
7. Pilihan program: audience fit, prerequisite, outcome, durasi, jadwal, dan harga.
8. Kurikulum ringkas: kelompok kompetensi, bukan daftar panjang tanpa hierarchy.
9. Fasilitas dan instruktur: foto nyata, nama/peran, pengalaman yang dapat dibuktikan.
10. Alumni/testimoni: hanya data dan kutipan yang memiliki izin publikasi.
11. Biaya dan pembayaran: harga final, apa yang termasuk, cicilan, refund/reschedule bila ada.
12. FAQ: fokus pada objection terakhir sebelum konsultasi.
13. Lead form: konteks singkat, reassurance privasi, dan ekspektasi follow-up.
14. Footer: lokasi, jalur resmi, privacy policy, dan identitas badan usaha bila relevan.

## Wireframe mobile-first

```text
┌──────────────────────────────┐
│ Announcement faktual        │
├──────────────────────────────┤
│ Logo                 Menu   │
├──────────────────────────────┤
│ Cocok untuk [audience utama] │
│ Headline hasil + bengkel     │
│ nyata                       │
│ Supporting proof/caveat      │
│ [Konsultasi program gratis]  │
│ [Lihat program & biaya]      │
│ Foto praktik utama           │
├──────────────────────────────┤
│ Trust facts (2–3 fakta)      │
├──────────────────────────────┤
│ Setelah belajar, kamu bisa…  │
│ Outcome level-based          │
├──────────────────────────────┤
│ Cara belajar                 │
│ Teori → Praktik → OJT        │
├──────────────────────────────┤
│ Program Basic               │
│ fit • outcome • waktu • Rp   │
│ [Konsultasikan Basic]        │
│                              │
│ Program Advance             │
│ ...                          │
│                              │
│ Program Professional        │
│ ...                          │
├──────────────────────────────┤
│ Kurikulum ringkas            │
├──────────────────────────────┤
│ Bengkel, alat, instruktur    │
│ Foto + fakta terverifikasi   │
├──────────────────────────────┤
│ Alumni/testimoni             │
├──────────────────────────────┤
│ Biaya, isi paket, cicilan    │
├──────────────────────────────┤
│ FAQ keputusan                │
├──────────────────────────────┤
│ Form konsultasi 2 langkah    │
│ Ekspektasi follow-up/privacy │
├──────────────────────────────┤
│ Footer resmi                 │
└──────────────────────────────┘
```

## Data yang harus dikumpulkan sebelum desain besar

- Primary audience dan offer yang paling menguntungkan/strategis.
- Foto praktik asli beresolusi tinggi beserta izin penggunaan.
- Profil instruktur dan bukti pengalaman.
- Rasio teori/praktik, metode asesmen, serta contoh jadwal harian.
- Outcome alumni, testimoni, dan izin publikasi.
- Detail fasilitas, kapasitas batch, lokasi, mess, dan OJT.
- Harga final, periode promo, cicilan, biaya yang termasuk/tidak termasuk.
- Kebijakan rekrutmen yang membedakan “kesempatan seleksi” dari jaminan kerja.
- Privacy policy, retention period, serta SLA Education Consultant.

## Guardrails implementasi

- Jangan memindahkan lead capture kembali menjadi direct WhatsApp.
- Jangan menambah carousel, scroll animation seragam, atau JavaScript berat.
- Gunakan satu foto utama yang kuat sebelum menambah banyak foto generik.
- Hindari klaim, angka, badge, countdown, dan scarcity yang belum disahkan.
- Pertahankan input minimal 16 px, touch target minimal 44 px, focus state, reduced motion, dan target WCAG AA.
- Uji perubahan pada production build dan ukur conversion event, bukan hanya estetika.
