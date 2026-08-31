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

## Dashboard siswa

Prototipe area belajar ada di `/kelas` (dashboard) dan `/kelas/[slug]`
(pemutar pelajaran) — 27 halaman pelajaran ter-generate dari satu sumber data.

App shell-nya terpisah dari halaman marketing: `CourseLayout` tidak membawa
bar promo maupun CTA konsultasi, karena orang yang sudah membeli tidak perlu
dijual lagi. Yang dibawanya: progres, toggle tema, dan identitas peserta.

Dashboard menaruh satu aksi di paling atas — *lanjutkan belajar*, menunjuk ke
pelajaran pertama yang belum selesai, bukan pelajaran pertama. Itu satu-satunya
hal yang dibutuhkan orang yang kembali.

Halaman pelajaran menahan daftar materi tetap terlihat di sisi kanan dan
menggulirkannya ke pelajaran yang sedang dibuka, supaya peserta di modul lima
tidak membuka halaman dan melihat modul satu.

Angka progres, durasi total, dan jumlah pelajaran **dihitung dari data**, bukan
diketik — jadi tidak bisa meleset saat materi bertambah.

Semua halaman area belajar `noindex`. Belum ada autentikasi: peserta
di-hardcode dan progres hanya bertahan di memori halaman. Itu yang membedakan
prototipe ini dari LMS sungguhan.

## Profil siswa

Ada di `/kelas/profil`, dijangkau lewat avatar di header.

Semua statistik menjawab satu pertanyaan: **apa langkah saya sekarang.**

| Statistik | Kenapa berguna |
| --- | --- |
| Progres sertifikat | Menyebut sisa pelajaran, bukan hanya persentase |
| Waktu belajar | Bukti usaha yang sudah dikeluarkan |
| Rentetan hari | Alasan untuk membuka materi hari ini |
| Perkiraan selesai | Membantu merencanakan, bukan sekadar mengukur |
| Ritme 28 hari | Pola lebih jujur daripada satu angka total |
| Progres per modul | Menunjukkan persis di mana ia berhenti |

Yang **sengaja tidak ada**: peringkat, persentil, dan perbandingan dengan
peserta lain. Angka semacam itu tidak mengubah apa pun yang bisa dilakukan
siswa berikutnya, dan bagi yang tertinggal justru jadi alasan berhenti. Lencana
tanpa makna juga dilewati.

Kalender ritme diselaraskan ke hari sungguhan dengan mengisi kolom kosong di
awal — tanpa itu ia cuma 28 kotak yang tidak memberi tahu hari apa seseorang
biasanya belajar.

Setiap angka dihitung dari data pelajaran dan riwayat, bukan diketik.

Bagian akun memuat data diri, riwayat pembelian, dan bantuan. Semua tombol
aksi `disabled` karena belum ada sistem akun.

## Yang masih placeholder

Isi kurikulum sudah realistis (6 modul, 27 pelajaran, 5 jam 18 menit, dengan
*Diagnosis dengan scanner* sebagai modul inti), tetapi **materinya belum ada**.
Setiap slot video masih kosong dan deskripsi pelajaran masih generik.

Harga Rp99.000 dari Rp249.000, empat pelajaran pertama gratis.

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
