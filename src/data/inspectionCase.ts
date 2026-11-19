/**
 * Satu unit contoh untuk simulasi di halaman Kelas Inspeksi Mobil Bekas.
 *
 * Kode, nama item, arti kondisi (B / P / PP / K), nama pilar, dan teks
 * rekomendasi disalin dari "Form Inspeksi Mobil Bekas, Versi Pengguna".
 *
 * !! VERIFY dengan trainer sebelum publish: temuan pada unit contoh ini, kondisi
 * yang ditetapkan untuk tiap temuan, alasannya, dan status tiap pilar adalah
 * karangan untuk demonstrasi, belum ditinjau trainer Pitcar.
 */
export type Condition = 'B' | 'P' | 'PP' | 'K';

/** Arti tiap kondisi, sesuai bagian "Cara isi" di form. */
export const conditions: { code: Condition; name: string; meaning: string }[] = [
  { code: 'B', name: 'Baik', meaning: 'Normal' },
  { code: 'P', name: 'Perhatian', meaning: 'Pantau, minor' },
  { code: 'PP', name: 'Perlu perbaikan', meaning: 'Ada biaya repair' },
  { code: 'K', name: 'Kritis', meaning: 'Red flag, risiko besar' },
];

export interface CheckPoint {
  id: string;
  /** Kode item di form. */
  code: string;
  /** Nama item di form. */
  item: string;
  /** Label pendek untuk chip dan tooltip. */
  label: string;
  /** Letak di mobil: x dari bumper belakang, y dari tanah, z ke kanan (meter). */
  at: [number, number, number];
  /** Yang terlihat saat titik ini diperiksa. */
  observation: string;
  answer: Condition;
  why: string;
}

export const checkPoints: CheckPoint[] = [
  {
    id: 'rangka-no',
    code: '1.1',
    item: 'No. rangka sesuai STNK/BPKB',
    label: 'Nomor rangka',
    at: [3.42, 1.06, 0.42],
    observation: 'Nomor rangka yang tercetak di bodi sama dengan yang tertulis di STNK dan BPKB.',
    answer: 'B',
    why: 'Identitas kendaraan cocok. Ini gerbang pertama di form: kalau tidak cocok, keputusan beli dihentikan sampai jelas.',
  },
  {
    id: 'cat',
    code: '2.6',
    item: 'Cat: beda warna, cat ulang, dempul, karat, celah panel',
    label: 'Cat dan celah panel',
    at: [2.4, 0.82, 0.86],
    observation: 'Warna pintu depan kanan sedikit lebih terang daripada fender, dan celah panelnya melebar di bagian bawah.',
    answer: 'P',
    why: 'Tanda panel ini pernah dicat ulang. Belum tentu tabrakan berat, tetapi alasan kuat untuk memeriksa apa yang ada di baliknya.',
  },
  {
    id: 'sasis',
    code: '2.8',
    item: 'Sasis/rangka & indikasi pernah diluruskan',
    label: 'Rangka depan',
    at: [3.92, 0.3, 0.46],
    observation: 'Di rangka depan bagian bawah ada bekas las yang tidak rapi, dengan cat yang berbeda dari sekitarnya.',
    answer: 'K',
    why: 'Indikasi perbaikan struktur setelah tabrakan berat. Ini red flag: menyangkut keselamatan dan nilai jual kembali.',
  },
  {
    id: 'oli',
    code: '3.4',
    item: 'Kebocoran oli, cairan pendingin atau bahan bakar',
    label: 'Kebocoran oli',
    at: [3.55, 0.4, 0],
    observation: 'Ada rembesan oli yang masih basah di sambungan bak oli, tetapi belum menetes ke lantai.',
    answer: 'PP',
    why: 'Perlu diperbaiki, dan biayanya bisa dihitung. Temuan seperti ini yang dipakai sebagai dasar menawar.',
  },
  {
    id: 'pendingin',
    code: '3.7',
    item: 'Radiator, tutup radiator, reservoir & level cairan pendingin',
    label: 'Cairan pendingin',
    at: [3.92, 0.82, -0.3],
    observation: 'Cairan pendingin di reservoir berada di antara batas bawah dan batas atas, dan warnanya jernih.',
    answer: 'B',
    why: 'Level dan kondisinya normal. Tidak ada yang perlu dicatat selain tanda centang.',
  },
  {
    id: 'mil',
    code: '4.1',
    item: 'Lampu peringatan saat kontak ON: MIL, ABS, SRS, VSC/ESP',
    label: 'Lampu peringatan',
    at: [2.72, 1.06, 0.36],
    observation: 'Saat kontak ON semua lampu menyala. Setelah mesin hidup, lampu check engine tetap menyala.',
    answer: 'P',
    why: 'Penyebabnya belum bisa disimpulkan sebelum ECU dipindai. Sampai itu dilakukan, pilar ini belum boleh dianggap aman.',
  },
  {
    id: 'banjir',
    code: '6.4',
    item: 'Bau lembap, bekas air, lumpur atau endapan',
    label: 'Indikasi banjir',
    at: [2.0, 0.44, 0.2],
    observation: 'Karpet kering dan tidak berbau lembap. Tidak ada garis bekas air atau endapan di bawah jok.',
    answer: 'B',
    why: 'Tidak ada tanda pernah terendam di titik ini.',
  },
  {
    id: 'transmisi',
    code: '7.3',
    item: 'Respons D/R & perpindahan transmisi AT/CVT/MT',
    label: 'Respons transmisi',
    at: [2.78, 0.56, 0],
    observation: 'Saat tuas dipindah ke D lalu ke R, mobil langsung merespons, tanpa jeda dan tanpa hentakan.',
    answer: 'B',
    why: 'Respons normal. Jeda atau hentakan di sini adalah yang perlu diwaspadai.',
  },
  {
    id: 'shock',
    code: '8.1',
    item: 'Shock absorber depan/belakang & kebocoran',
    label: 'Shock absorber',
    at: [0.8, 0.62, 0.6],
    observation: 'Shock absorber belakang basah oleh oli, dan bodi memantul lebih dari sekali saat ditekan.',
    answer: 'PP',
    why: 'Shock absorber sudah bocor dan perlu diganti. Biayanya jelas, jadi masuk daftar temuan dan estimasi.',
  },
  {
    id: 'ban',
    code: '10.2',
    item: 'Telapak, kedalaman alur & keausan merata',
    label: 'Keausan ban',
    at: [3.45, 0.32, 0.86],
    observation: 'Ban depan kanan aus di sisi dalam saja, sementara sisi luarnya masih tebal.',
    answer: 'P',
    why: 'Keausan tidak merata bisa berasal dari setelan roda, kaki-kaki, atau rangka. Temuan ini menguatkan temuan di rangka depan.',
  },
];

export type PillarStatus = 'AMAN' | 'VERIFIKASI' | 'KRITIS';

export interface Pillar {
  name: string;
  focus: string;
  /** Titik yang harus sudah diperiksa sebelum status pilar ini terisi. */
  needs: string[];
  status: PillarStatus;
  note: string;
}

/** Nama dan fokus pilar sesuai halaman "Ringkasan keputusan pembelian" di form. */
export const pillars: Pillar[] = [
  { name: 'Dokumen', focus: 'No. rangka/mesin, legalitas, konsistensi identitas', needs: ['rangka-no'], status: 'AMAN', note: 'Nomor rangka cocok dengan dokumen.' },
  { name: 'Mesin', focus: 'Risiko kerusakan internal, overheating, overhaul', needs: ['oli', 'pendingin'], status: 'AMAN', note: 'Rembesan oli masuk daftar biaya perbaikan.' },
  { name: 'Chassis & Body', focus: 'Laka berat, repair struktur, banjir/korosi', needs: ['cat', 'sasis', 'banjir'], status: 'KRITIS', note: 'Bekas las di rangka depan.' },
  { name: 'Transmisi & Kaki-kaki', focus: 'Matic, sistem penggerak, steering dan suspensi', needs: ['transmisi', 'shock'], status: 'AMAN', note: 'Shock absorber bocor masuk daftar biaya perbaikan.' },
  { name: 'Scan & Kelistrikan', focus: 'DTC, warning, sensor/aktuator dan sistem keselamatan', needs: ['mil'], status: 'VERIFIKASI', note: 'Check engine menyala, ECU belum dipindai.' },
];

/** Teks rekomendasi disalin dari form. */
export const recommendation = {
  title: 'Sebaiknya tidak dilanjutkan',
  text: 'Ditemukan red flag terkonfirmasi pada legalitas, struktur, keselamatan, atau kerusakan mekanis besar yang tidak sebanding dengan manfaat pembelian.',
  hook: 'Dari luar, unit ini tampak mulus. Yang menentukan justru titik yang paling jarang dilihat pembeli: rangka di kolong depan.',
};
