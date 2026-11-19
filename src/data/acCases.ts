/**
 * Kasus "AC kurang dingin" untuk simulasi di halaman kelas online AC.
 *
 * !! VERIFY dengan trainer sebelum publish: pola tekanan, penjelasan, dan
 * video yang ditunjuk ditulis dari prinsip kerja umum AC mobil, belum ditinjau
 * trainer Pitcar. Polanya sengaja kualitatif (rendah, normal, tinggi) tanpa
 * angka, karena angka normal bergantung pada suhu udara dan tipe mobil.
 *
 * `suspects` harus sama persis dengan nama bagian di `scripts/garage/ac.ts`.
 * `video` adalah indeks ke `acCourse.modules`.
 */
export type GaugeLevel = 'vakum' | 'sangat rendah' | 'rendah' | 'normal' | 'tinggi' | 'sangat tinggi';

export interface AcCase {
  id: string;
  label: string;
  low: GaugeLevel;
  high: GaugeLevel;
  /** Apa yang terjadi di dalam sistem. */
  what: string;
  /** Pertanyaan yang dibiarkan terbuka dan dijawab di kelas. */
  question: string;
  suspects: string[];
  video: number;
}

export const acCases: AcCase[] = [
  {
    id: 'sehat',
    label: 'AC sehat',
    low: 'normal',
    high: 'normal',
    what: 'Kompresor memampatkan, kondensor membuang panas, evaporator menyerap panas kabin. Ini pola yang harus kamu kenali dulu sebelum bisa mengenali yang salah.',
    question: 'Normal itu berapa? Jawabannya berubah mengikuti suhu udara, dan itu yang pertama dipelajari saat memakai manifold gauge.',
    suspects: [],
    video: 1,
  },
  {
    id: 'freon',
    label: 'Refrigeran kurang',
    low: 'rendah',
    high: 'rendah',
    what: 'Isi sistem berkurang, hampir selalu karena bocor. Tekanan turun di kedua sisi dan udara yang keluar hanya sejuk.',
    question: 'Menambah freon membuatnya dingin lagi, untuk sementara. Bocornya di mana?',
    suspects: ['Kondensor', 'Evaporator', 'Pipa tekanan tinggi'],
    video: 1,
  },
  {
    id: 'kondensor',
    label: 'Kondensor kepanasan',
    low: 'tinggi',
    high: 'sangat tinggi',
    what: 'Sirip kondensor tertutup kotoran atau kipasnya mati, jadi panas tidak terbuang. Tekanan sisi tinggi melonjak dan AC hanya dingin saat mobil melaju.',
    question: 'Di kasus ini freon tidak kurang sama sekali. Menambahnya justru menaikkan tekanan yang sudah terlalu tinggi. Lalu apa yang dibersihkan, dan bagaimana caranya?',
    suspects: ['Kondensor', 'Kipas kondensor'],
    video: 3,
  },
  {
    id: 'kompresor',
    label: 'Kompresor lemah',
    low: 'tinggi',
    high: 'rendah',
    what: 'Kompresor berputar tetapi tidak lagi mampu memampatkan. Selisih tekanan antara kedua sisi mengecil dan udara tidak dingin.',
    question: 'Kompresor adalah komponen termahal di sistem ini. Bagaimana memastikannya sebelum memutuskan ganti?',
    suspects: ['Kompresor'],
    video: 2,
  },
  {
    id: 'ekspansi',
    label: 'Katup ekspansi tersumbat',
    low: 'vakum',
    high: 'normal',
    what: 'Aliran refrigeran tertahan di katup ekspansi. Sisi rendah turun sampai mendekati vakum dan evaporator hampir tidak mendapat refrigeran.',
    question: 'Di gauge, pola ini bisa terlihat mirip refrigeran kurang. Apa yang membedakannya?',
    suspects: ['Katup ekspansi', 'Receiver drier'],
    video: 2,
  },
  {
    id: 'kopling',
    label: 'Kopling magnet mati',
    low: 'tinggi',
    high: 'sangat rendah',
    what: 'Puli berputar tetapi kompresor diam, karena kopling magnet tidak mendapat arus. Tanpa kompresor, tekanan kedua sisi saling menyamakan: sisi rendah naik, sisi tinggi jatuh jauh di bawah normalnya.',
    question: 'Penyebabnya bisa sekring, relay, pressure switch, atau kabel. Diperiksa dari mana dulu?',
    suspects: ['Kopling magnet & puli', 'Pressure switch'],
    video: 2,
  },
  {
    id: 'kabin',
    label: 'Filter & evaporator kotor',
    low: 'normal',
    high: 'normal',
    what: 'Sistem refrigerannya sehat, tetapi udara tidak bisa lewat. Angin dari kisi-kisi lemah dan sering berbau.',
    question: 'Gauge menunjukkan normal, padahal keluhannya sama. Tanpa membongkar dasbor, bagaimana melihat kondisi evaporator?',
    suspects: ['Filter kabin', 'Evaporator', 'Blower'],
    video: 3,
  },
];
