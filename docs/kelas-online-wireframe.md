# Kelas online — wireframe

Halaman ada di `/kelas-online`, memakai design system yang sama. **Belum
terindeks** (`noindex` + tidak masuk sitemap) sampai `digitalCourse.ready`
diubah jadi `true` di `src/content.config.ts`.

## Kenapa halaman terpisah

Bukan karena "target iklannya berbeda" — itu alasan yang paling lemah. Yang
memaksa pemisahan adalah **bentuk funnel-nya berbeda, dan dua bentuk funnel
tidak bisa berbagi satu halaman**:

| | Program offline | Kelas online |
| --- | --- | --- |
| Keputusan | mingguan, sering melibatkan orang tua | menit, sendirian |
| Funnel | form lead → konsultasi WhatsApp | checkout langsung |
| Panjang | 15 section membangun kepercayaan | 7 section, produk di depan |
| Keberatan utama | lokasi, mess, durasi, karier | masa akses, isi materi, perangkat |
| Event Meta pixel | `Lead` | `InitiateCheckout` → `Purchase` |

Form konsultasi 6 field tepat untuk keputusan Rp5 juta dan salah untuk produk
beberapa ratus ribu — friksinya lebih mahal daripada harganya.

Soal pixel bukan detail kecil: Meta mengoptimasi pada event yang disuapkan,
dan satu ad set butuh sekitar 50 konversi per minggu untuk keluar dari
learning phase. Dua produk dengan nilai dan event berbeda di satu halaman
mengotori sinyal itu.

## Kenapa satu domain, bukan subdomain

Rute terpisah di domain yang sama: berbagi design system, header, footer, dan
domain authority. Subdomain hanya masuk akal kalau positioning-nya harus
dibedakan, dan di sini justru sebaliknya.

## Kenapa berakhir menunjuk ke program offline

Produk murah di brand yang sama dengan program Rp8,5 juta akan menggerus
persepsi harganya **kalau disajikan sebagai alternatif**. Disajikan sebagai
tangga, efeknya terbalik: kelas online jadi cara termurah mengakuisisi lead
untuk program mahal.

Ekonominya juga berubah. Pembelian Rp300 ribu dengan biaya iklan Rp150 ribu
nyaris tidak untung sebagai produk mandiri; sebagai mesin akuisisi lead untuk
program Rp8,5 juta, itu murah sekali.

## Struktur halaman

```
1  Hero          hasil belajar + harga + masa akses + CTA
2  Outcome       apa yang bisa dilakukan setelah selesai
3  Kurikulum     modul, jumlah pelajaran, durasi, satu modul gratis
4  Yang didapat  akses, perangkat, sertifikat, dukungan
5  Harga         checkout + garansi
6  FAQ           objection khas digital
7  Tangga        → program offline
```

Aturan halaman offline — *70% kualitas ditentukan foto* — di sini berubah jadi
**70% ditentukan apakah materinya benar-benar ditunjukkan**. Orang tidak bisa
menyentuh produk digital, jadi "isinya seperti apa" adalah pertanyaan nomor
satu. Slot preview di hero harus diisi rekaman layar pelajaran asli, bukan
ilustrasi.

## Yang masih placeholder

Semua angka: harga, durasi, jumlah pelajaran, isi modul. Seluruh jawaban FAQ
diawali `PLACEHOLDER` dan menyebut keputusan apa yang perlu diambil.

Tombol beli sengaja `disabled`: payment gateway berada di luar scope saat ini
(lihat PRD). Halaman tidak boleh dipublikasikan dengan tombol yang tidak
berfungsi.

## Keputusan yang masih dibutuhkan

1. **Materinya sudah ada atau masih rencana?** Tanpa materi, tidak ada yang
   bisa ditunjukkan di slot preview — dan itu bagian paling menentukan.
2. **Harga.** Di atas sekitar Rp2 juta, konsultasi mulai masuk akal lagi dan
   struktur halaman ini perlu ditinjau ulang.
3. **Platform pengiriman.** LMS sendiri di `/admin`, atau pihak ketiga
   (Mayar, Lynk, Hotmart)? Ini menentukan apakah checkout langsung bisa
   dibangun sama sekali.
4. **Pendapatan mandiri atau umpan untuk program offline?** Jawaban ini
   mengubah harga, panjang halaman, dan seberapa keras section tangga
   mendorong ke program offline.

Nomor 4 yang paling menentukan. Wireframe ini mengasumsikan **umpan**, sesuai
rekomendasi — mengubahnya ke produk mandiri berarti section tangga dilemahkan
dan halamannya perlu menjual nilai penuh.
