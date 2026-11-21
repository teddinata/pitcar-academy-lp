import { applyLivePrices, type CourseReviews } from './lib/livePrices';
import { parseRupiah, rupiah } from './lib/pricing';

export const collections = {
  // Content data — semua info bisnis di sini
};

// Package data
export interface PackageItem {
  id: string;
  level: string;
  name: string;
  subtitle: string;
  description: string;
  price: string;
  priceDisplay: string;
  strikePriceDisplay?: string;
  /**
   * Steps down to the package price, each crossed out and labelled. Every
   * figure here must be derivable from the other cards on the same screen —
   * that is what stops it reading as invented. For Professional:
   * Rp7.500.000 x 2 = Rp15.000.000 normal, Rp5.000.000 x 2 = Rp10.000.000
   * at the current price.
   *
   * !! VERIFY whenever any price changes.
   */
  priceLadder?: { label: string; value: string }[];
  savingsNote?: string;
  scarcityNote?: string;
  duration: string;
  hours: string;
  ojt: string;
  certificate: string;
  kickOff: string;
  materials: string[];
  outcomes: string[];
  highlight?: boolean;
  badge?: string;
  image?: string;
}

export const packages: PackageItem[] = [
  {
    id: 'basic',
    level: 'BASIC',
    name: 'Mechanic Level 1 — Maintenance',
    subtitle: 'Cocok untuk kamu yang ingin membangun fundamental sebagai mekanik.',
    description:
      'Program dasar untuk membangun kompetensi perawatan berkala, tune up, dan diagnosa dasar EFI.',
    price: '5000000',
    priceDisplay: 'Rp 5.000.000',
    strikePriceDisplay: 'Rp 7.500.000',
    savingsNote: 'Harga khusus angkatan pertama',
    scarcityNote: 'Berlaku selama kuota angkatan pertama masih tersedia.',
    duration: '1 Bulan',
    hours: '104 Jam',
    ojt: '2 Bulan OJT Bengkel',
    certificate: 'Sertifikat Kelulusan Pitcar Academy — Level 1',
    kickOff: 'Oktober 2026',
    image: '/pkg-basic.png',
    materials: [
      'Maintenance Mobil Bensin EFI',
      'Tune Up Mesin',
      'Service Berkala & SOP',
      'Sistem Rem',
      'Sistem Pendingin',
      'Basic EFI & Scanner',
    ],
    outcomes: [
      'Memahami kerja kendaraan & komponen utama',
      'Melakukan perawatan berkala & tune up',
      'Menggunakan alat pemeriksaan & scanner dasar',
      'Memiliki etos kerja & standar bengkel nyata',
    ],
  },
  {
    id: 'advanced',
    level: 'ADVANCE',
    name: 'Mechanic Level 2 — General Repair',
    subtitle: 'Untuk kamu yang sudah memiliki fundamental otomotif dan ingin meningkatkan kemampuan.',
    description:
      'Fokus pada pekerjaan general repair, sistem mekanikal utama, dan overhaul mesin.',
    price: '5000000',
    priceDisplay: 'Rp 5.000.000',
    strikePriceDisplay: 'Rp 7.500.000',
    savingsNote: 'Harga khusus angkatan pertama',
    scarcityNote: 'Berlaku selama kuota angkatan pertama masih tersedia.',
    duration: '1 Bulan',
    hours: '104 Jam',
    ojt: '2 Bulan OJT Bengkel',
    certificate: 'Sertifikat Kelulusan Pitcar Academy — Level 2',
    kickOff: 'November 2026',
    image: '/pkg-advanced.png',
    materials: [
      'General Repair Kendaraan',
      'Sistem Transmisi',
      'Sistem Kemudi (Steering)',
      'Sistem Suspensi',
      'Overhaul Engine',
      'Troubleshooting Lanjutan',
    ],
    outcomes: [
      'Menganalisis & merawat sistem general repair',
      'Melakukan overhaul engine sesuai standar',
      'Melakukan troubleshooting komponen mekanikal',
      'Siap menangani problem perbaikan di bengkel',
    ],
  },
  {
    id: 'professional',
    level: 'PROFESSIONAL',
    name: 'Mechanic Level 1 + Level 2',
    subtitle: 'Program lengkap untuk membangun kompetensi Maintenance dan General Repair sekaligus.',
    description:
      'Mencakup seluruh materi Level 1 & Level 2 untuk menguasai kompetensi mekanik secara menyeluruh.',
    price: '8500000',
    priceDisplay: 'Rp 8.500.000',
    priceLadder: [
      { label: 'Harga normal 2 program', value: 'Rp 15.000.000' },
      { label: 'Beli terpisah sekarang', value: 'Rp 10.000.000' },
    ],
    savingsNote: 'Hemat Rp6.500.000 dari harga normal',
    scarcityNote: 'Berlaku selama kuota angkatan pertama masih tersedia.',
    duration: '2 Bulan',
    hours: '208 Jam',
    ojt: '2 Bulan OJT Bengkel',
    certificate: 'Sertifikat Kelulusan Pitcar Academy — Professional',
    kickOff: 'Oktober 2026',
    image: '/pkg-professional.png',
    materials: [
      'Seluruh Materi Level 1 Maintenance',
      'Seluruh Materi Level 2 General Repair',
      'Diagnosa Kompleks & Troubleshooting',
      'Pembentukan Karakter & Disiplin Industri',
    ],
    outcomes: [
      'Menguasai maintenance & general repair menyeluruh',
      'Memiliki 2 bulan pengalaman OJT langsung di bengkel',
      'Siap mengikuti seleksi rekrutmen jaringan PITCAR',
    ],
    highlight: true,
    badge: 'Program Paling Lengkap',
  },
];

// Advantage / USP data matching draft-revision.md
export interface AdvantageItem {
  icon: string;
  title: string;
  description: string;
}

export const advantages: AdvantageItem[] = [
  {
    icon: 'wrench',
    title: 'Belajar dari Praktisi Bengkel',
    description: 'Materi & metode dikembangkan dari pengalaman operasional PITCAR menangani kendaraan pelanggan sehari-hari.',
  },
  {
    icon: 'cog',
    title: 'Praktik Intensif',
    description: 'Porsi pembelajaran dirancang agar peserta memegang alat, melakukan pemeriksaan, dan mengerjakan kendaraan langsung.',
  },
  {
    icon: 'clock',
    title: '2 Bulan On The Job Training',
    description: 'Setelah kelas, jalani 2 bulan OJT di lingkungan bengkel PITCAR untuk merasakan ritme dan standar pekerjaan nyata.',
  },
  {
    icon: 'users',
    title: 'Trainer Berpengalaman',
    description: 'Belajar bersama trainer yang memahami pekerjaan mekanik secara praktis dan berpengalaman di industri bengkel.',
  },
  {
    icon: 'shield',
    title: 'Sertifikasi Kompetensi',
    description: 'Peserta yang memenuhi standar kelulusan mendapatkan sertifikat Pitcar Academy sesuai level kompetensinya.',
  },
  {
    icon: 'trending-up',
    title: 'Tidak Hanya Hard Skill',
    description: 'Disiplin, tanggung jawab, komunikasi, problem solving, dan etos kerja menjadi bagian inti dari pembelajaran.',
  },
];

// FAQ data matching draft-revision.md
export interface FaqItem {
  question: string;
  answer: string;
}

export const faqs: FaqItem[] = [
  {
    question: 'Apakah harus lulusan SMK Otomotif?',
    answer:
      'Tidak. Program Basic dapat diikuti oleh siapa saja yang ingin memulai dan membangun fundamental otomotif dari awal.',
  },
  {
    question: 'Apa perbedaan Basic dan Advance?',
    answer:
      'Program Basic berfokus pada Maintenance (Perawatan Berkala, Tune Up, EFI Dasar), sedangkan Advance berfokus pada General Repair (Overhaul, Transmisi, Suspensi, Steering).',
  },
  {
    question: 'Saya ingin belajar keduanya. Program mana yang sebaiknya dipilih?',
    answer:
      'Program Professional mencakup Level 1 dan Level 2 sekaligus, total 208 jam pembelajaran dan 2 bulan OJT. Dibanding mengambil Basic dan Advance terpisah pada harga angkatan pertama, Professional lebih hemat Rp1.500.000; dibanding harga normal kedua program, hemat Rp6.500.000.',
  },
  {
    question: 'Di mana OJT dilakukan?',
    answer:
      'OJT dilaksanakan langsung di lingkungan bengkel yang ditentukan oleh jaringan Pitcar Academy.',
  },
  {
    question: 'Apakah setelah lulus pasti bekerja di PITCAR?',
    answer:
      'Tidak ada jaminan langsung diterima kerja. Namun peserta dengan kompetensi, attitude, dan performa yang memenuhi standar dapat memperoleh prioritas kesempatan mengikuti proses rekrutmen sesuai kebutuhan jaringan PITCAR.',
  },
  {
    question: 'Apakah bisa mencicil biaya pendidikan?',
    answer:
      'Skema dan pilihan pembayaran dapat ditanyakan langsung kepada tim pendaftaran kami via WhatsApp.',
  },
  {
    question: 'Apakah disediakan Mess?',
    answer:
      'Free Mess hanya berlaku untuk 10 pendaftar tercepat saja. Segera daftarkan diri Anda melalui WhatsApp untuk memastikan kuota.',
  },
  {
    question: 'Berapa lama program berlangsung?',
    answer:
      'Basic dan Advance masing-masing 104 jam kelas dan praktik, Professional 208 jam. Setelahnya ada 2 bulan OJT di bengkel jaringan PITCAR.',
  },
  {
    question: 'Apakah tools harus membawa sendiri?',
    answer:
      'Tidak. Peralatan disediakan selama sesi praktik, termasuk hand tools, SST, dan diagnostic scanner yang dipakai di bengkel.',
  },
  {
    question: 'Sertifikat apa yang didapat?',
    answer:
      'Sertifikat Kelulusan Pitcar Academy sesuai program yang diambil, diberikan kepada peserta yang memenuhi standar teori, praktik, attitude, dan assessment. Ini bukan sertifikasi kompetensi nasional BNSP/LSP.',
  },
  {
    question: 'Apakah ada batas usia?',
    answer:
      'Minimal 17 tahun dan sehat jasmani karena program melibatkan kerja fisik di area bengkel. Tidak ada batas usia maksimum selama peserta siap mengikuti SOP dan ritme kerja bengkel.',
  },
];

/* ============================================================================
   REVAMP CONTENT
   Everything below is page copy, kept here so it can be changed without
   touching components.
   ========================================================================== */

/**
 * Public contact details shown in the footer.
 *
 * These are meant to be read by visitors, so they live in content rather than
 * in env — unlike `PUBLIC_EDUCATION_CONSULTANT_WHATSAPP`, which decides where
 * a captured lead is routed and stays configurable per environment.
 *
 * Coordinates and the place name were taken from the Maps link itself, not
 * assumed. No street address is listed because none has been confirmed.
 */
export const contact = {
  whatsappDisplay: '+62 857-4222-8865',
  whatsappNumber: '6285742228865',
  placeName: 'PITCAR Service — Spesialis Tune Up, AC & Emergency',
  mapsUrl: 'https://maps.app.goo.gl/32a5XVu1S4MGKT3X6',
  latitude: -7.4358518,
  longitude: 109.2542999,
  website: 'https://pitcar.co.id',
};

/**
 * Announcement bar.
 *
 * Urgency here is quota-based on purpose. A date ("harga naik di September")
 * is stronger while it is true and a liability the morning after — and nobody
 * owns updating it. Set `deadline` only if there is a real, dated cutoff
 * someone has committed to maintaining.
 *
 * !! VERIFY: the saving figure must match the package cards.
 */
export interface Announcement {
  /** Decorative only — rendered aria-hidden so screen readers skip it. */
  emoji?: string;
  /** The offer. Lead with the number, not an adjective. */
  text: string;
  cta: string;
  href: string;
  publish: boolean;
}

export const announcement: Announcement = {
  emoji: '🔥',
  // "hingga 43%" is the ceiling, not the norm: Professional is 43.3%
  // (15jt -> 8,5jt), Basic and Advance are 33.3% (7,5jt -> 5jt). Recheck this
  // figure whenever a price changes — it is the one claim on the page a
  // prospect can verify with a calculator.
  text: 'HANYA UNTUK BATCH PERDANA 2026 — Hemat hingga 43% hanya untuk peserta angkatan pertama!',
  cta: 'Ambil sekarang',
  href: '#konsultasi',
  publish: true,
};

/**
 * Trust numbers.
 *
 * !! VERIFY BEFORE PUBLISHING !!
 * These came through the revamp brief as illustrative examples, not as
 * confirmed figures. Publishing an unverified operational claim on a page that
 * asks for Rp5 juta is a real risk. Confirm each one with operations, or set
 * `publish: false` to hide it.
 */
export interface TrustNumber {
  value: string;
  label: string;
  publish: boolean;
}

export const trustNumbers: TrustNumber[] = [
  { value: '18.000+', label: 'Kendaraan telah ditangani', publish: true },
  { value: '450+', label: 'Kendaraan ditangani setiap bulan', publish: true },
  { value: '5+', label: 'Jaringan bengkel aktif', publish: true },
];

export const trustStatement =
  'Kurikulum Pitcar Academy dikembangkan dari pengalaman operasional bengkel nyata, bukan hanya dari teori kelas.';

/** The gap the program exists to close. Stated before any curriculum. */
export interface ProblemItem {
  title: string;
  description: string;
}

export const problems: ProblemItem[] = [
  {
    title: 'Kurang jam terbang',
    description:
      'Sudah belajar teori, tetapi belum terbiasa mengerjakan kendaraan secara langsung dan berulang.',
  },
  {
    title: 'Bingung saat menghadapi kasus nyata',
    description:
      'Kondisi kendaraan customer sering berbeda dengan contoh yang ada di buku dan modul.',
  },
  {
    title: 'Belum siap dengan dunia kerja bengkel',
    description:
      'Skill teknis saja tidak cukup. Mekanik juga membutuhkan SOP, disiplin, komunikasi, dan tanggung jawab.',
  },
];

export const problemResolution =
  'Di Pitcar Academy, kamu belajar sampai mengalami situasi bengkel nyata.';

/** The five-stage method, shown horizontally on desktop and as a rail on mobile. */
export interface MethodStep {
  key: string;
  title: string;
  description: string;
}

export const learningMethod: MethodStep[] = [
  { key: 'learn', title: 'Learn', description: 'Membangun konsep dan fundamental sistem kendaraan.' },
  { key: 'practice', title: 'Practice', description: 'Praktik menggunakan tools dan kendaraan sungguhan.' },
  { key: 'ojt', title: 'OJT', description: 'Mengikuti aktivitas harian di bengkel yang beroperasi.' },
  { key: 'assessment', title: 'Assessment', description: 'Kompetensi teori, praktik, dan attitude dievaluasi.' },
  { key: 'career', title: 'Career', description: 'Menyiapkan kesiapan masuk dunia kerja otomotif.' },
];

/**
 * Capability statements, not a syllabus. People pay for what they will be able
 * to do, not for a list of subjects they will sit through.
 */
export const capabilities: string[] = [
  'Melakukan periodic maintenance sesuai SOP.',
  'Melakukan basic engine tune-up.',
  'Melakukan inspeksi sistem pengereman.',
  'Melakukan pemeriksaan cooling system.',
  'Menggunakan diagnostic scanner untuk basic diagnosis.',
  'Membaca hasil inspeksi dan menyusun rekomendasi pekerjaan.',
  'Bekerja dengan prosedur keselamatan dan standar bengkel.',
];

/**
 * Trainers.
 *
 * `photo` is intentionally optional: the cards render a typographic placeholder
 * until a real photograph exists. Inventing a stock portrait for a named
 * instructor would be a lie about who teaches the program.
 * Fill `name` and `photo` once operations supplies them.
 */
export interface Trainer {
  name: string;
  role: string;
  photo?: string;
  credentials: string[];
}

export const trainers: Trainer[] = [
  {
    name: 'Roka',
    role: 'Technical Trainer',
    photo: '/trainers/roka.webp',
    credentials: [
      '13 tahun pengalaman di industri otomotif',
      'Praktisi aktif di jaringan bengkel PITCAR',
    ],
  },
  {
    name: 'Dayat',
    role: 'Technical Trainer',
    photo: '/trainers/dayat.webp',
    credentials: [
      '8 tahun pengalaman di industri otomotif',
      'Praktisi aktif di jaringan bengkel PITCAR',
    ],
  },
  {
    name: 'Hanif',
    role: 'Pengawas Pitcar Academy',
    photo: '/trainers/hanif.webp',
    credentials: [
      'Mengawasi jalannya program dan ritme kerja bengkel',
      'Mendampingi peserta selama praktik dan OJT',
    ],
  },
];

/** Six phases, so an abstract product becomes something a person can picture. */
export interface JourneyPhase {
  phase: string;
  title: string;
  description: string;
}

export const studentJourney: JourneyPhase[] = [
  { phase: 'Fase 1', title: 'Fundamental', description: 'Teori dasar dan pengenalan sistem kendaraan.' },
  { phase: 'Fase 2', title: 'Guided Practice', description: 'Praktik pertama dengan pendampingan trainer.' },
  { phase: 'Fase 3', title: 'Intensive Practice', description: 'Diagnosis dan pengerjaan berbagai kasus kendaraan.' },
  { phase: 'Fase 4', title: 'On the Job Training', description: 'Mengikuti ritme kerja bengkel PITCAR yang beroperasi.' },
  { phase: 'Fase 5', title: 'Assessment', description: 'Evaluasi kompetensi teori, praktik, dan attitude.' },
  { phase: 'Fase 6', title: 'Graduation', description: 'Sertifikat kelulusan sesuai program yang diambil.' },
];

/** Equipment and environment. Photos replace the icons as they arrive. */
export interface FacilityItem {
  title: string;
  description: string;
  photo?: string;
}

export const facilities: FacilityItem[] = [
  { title: 'Lift & area kerja', description: 'Praktik pada posisi kerja yang sama dengan bengkel operasional.' },
  { title: 'Diagnostic scanner', description: 'Membaca data kendaraan untuk basic diagnosis, bukan sekadar demo.' },
  { title: 'Hand tools & SST', description: 'Peralatan yang benar-benar dipakai mekanik setiap hari.' },
  { title: 'Kendaraan praktik', description: 'Unit untuk latihan sebelum menangani kendaraan customer.' },
  { title: 'Ruang belajar', description: 'Sesi teori dan pembahasan kasus sebelum turun ke area kerja.' },
  { title: 'Lingkungan bengkel aktif', description: 'Belajar di tempat yang benar-benar melayani customer.' },
];

/**
 * Founding batch benefits.
 *
 * !! VERIFY: mess availability, quota, and period must be confirmed before
 * publishing. Set `publish: false` on anything not yet agreed.
 */
export interface BatchBenefit {
  title: string;
  description: string;
  publish: boolean;
}

export const foundingBatchBenefits: BatchBenefit[] = [
  {
    title: 'Mess gratis untuk kuota pertama',
    description:
      'Tempat tinggal bagi peserta luar kota yang memenuhi kriteria, selama kuota angkatan pertama masih tersedia.',
    publish: true,
  },
  {
    title: 'Harga khusus angkatan pertama',
    description: 'Harga yang hanya berlaku untuk angkatan pertama, sebelum biaya program disesuaikan.',
    publish: true,
  },
  {
    title: 'Kelas dengan jumlah peserta terbatas',
    description: 'Rasio trainer dan peserta dijaga agar setiap orang mendapat pendampingan praktik.',
    publish: true,
  },
];

/** Written to describe an opportunity, never to promise employment. */
export const careerStatement =
  'Peserta dengan performa dan kompetensi yang memenuhi kebutuhan perusahaan dapat memperoleh kesempatan mengikuti proses seleksi rekrutmen PITCAR.';

/** The buyer is often a parent, even when the student is not. */
export const parentReadiness: string[] = [
  'Disiplin waktu dan kehadiran',
  'Kepatuhan pada SOP kerja',
  'Keselamatan kerja',
  'Tanggung jawab atas pekerjaan',
  'Komunikasi dengan rekan dan atasan',
  'Problem solving di kasus nyata',
  'Attitude di lingkungan kerja profesional',
];

export const parentStatement =
  'Program OJT memberi peserta pengalaman memahami ritme dan tanggung jawab kerja sebelum benar-benar masuk ke dunia kerja.';

/**
 * Practical detail.
 *
 * !! VERIFY every row before publishing. The more a program costs, the more
 * precisely people expect these answered — and a wrong answer here is the kind
 * that reaches a consultant as a complaint.
 */
export interface ProgramFact {
  label: string;
  value: string;
}

export const programFacts: ProgramFact[] = [
  { label: 'Lokasi training', value: 'Workshop PITCAR — detail lokasi dikonfirmasi saat konsultasi' },
  { label: 'Jadwal', value: 'Senin–Jumat, sesi pagi hingga sore' },
  { label: 'Durasi kelas', value: 'Basic 104 jam · Advance 104 jam · Professional 208 jam' },
  { label: 'Periode OJT', value: '2 bulan di bengkel jaringan PITCAR' },
  { label: 'Peserta per batch', value: 'Terbatas, dikonfirmasi saat konsultasi' },
  { label: 'Tools', value: 'Disediakan selama sesi praktik' },
  { label: 'Assessment', value: 'Teori, praktik, dan attitude' },
  { label: 'Sertifikat', value: 'Sertifikat kelulusan Pitcar Academy' },
  { label: 'Syarat peserta', value: 'Minimal 17 tahun, sehat jasmani, siap mengikuti SOP bengkel' },
  { label: 'Batch terdekat', value: 'Oktober 2026' },
];

/* ============================================================================
   KELAS ONLINE
   Konten dummy yang realistis untuk review. Materi, video, dan checkout belum
   ada — halaman tetap noindex sampai `digitalCourse.ready` diubah ke true.
   ========================================================================== */

export interface Lesson {
  slug: string;
  title: string;
  duration: string;
  /** Dibuka tanpa membeli, sebagai contoh materi. */
  free?: boolean;
  /** Dummy progress untuk prototipe dashboard. */
  done?: boolean;
}

export interface CourseModule {
  title: string;
  summary: string;
  lessons: Lesson[];
}

export const courseModules: CourseModule[] = [
  {
    title: 'Mengenal kendaraan dari nol',
    summary: 'Peta besar sistem kendaraan sebelum masuk ke detail tiap bagian.',
    lessons: [
      { slug: 'selamat-datang', title: 'Selamat datang & cara belajar di kelas ini', duration: '4:12', free: true, done: true },
      { slug: 'anatomi-kendaraan', title: 'Anatomi kendaraan: 5 sistem utama', duration: '9:38', free: true, done: true },
      { slug: 'istilah-bengkel', title: 'Istilah bengkel yang wajib kamu tahu', duration: '11:05', free: true, done: true },
      { slug: 'alat-ukur-dasar', title: 'Alat ukur dasar dan cara membacanya', duration: '8:47', free: true, done: true },
    ],
  },
  {
    title: 'Mesin dan sistem pembakaran',
    summary: 'Bagaimana bensin, udara, dan api menjadi tenaga.',
    lessons: [
      { slug: 'siklus-4-langkah', title: 'Siklus 4 langkah, dijelaskan pelan-pelan', duration: '12:20', done: true },
      { slug: 'komponen-mesin', title: 'Komponen mesin dan fungsinya', duration: '14:03', done: true },
      { slug: 'sistem-bahan-bakar', title: 'Sistem bahan bakar: dari tangki ke injektor', duration: '10:41', done: true },
      { slug: 'gejala-mesin-bermasalah', title: 'Membaca gejala mesin bermasalah', duration: '13:29' },
      { slug: 'praktik-tune-up', title: 'Studi kasus: tune up mesin bensin', duration: '15:52' },
    ],
  },
  {
    title: 'Pendingin dan pelumasan',
    summary: 'Dua sistem yang paling sering diabaikan sampai mesin rusak.',
    lessons: [
      { slug: 'kenapa-mesin-panas', title: 'Kenapa mesin bisa overheat', duration: '9:14' },
      { slug: 'radiator-dan-coolant', title: 'Radiator, coolant, dan thermostat', duration: '11:36' },
      { slug: 'sistem-pelumasan', title: 'Sistem pelumasan dan pemilihan oli', duration: '12:48' },
      { slug: 'inspeksi-kebocoran', title: 'Inspeksi kebocoran: apa yang dicari', duration: '10:07' },
    ],
  },
  {
    title: 'Sistem pengereman',
    summary: 'Sistem paling berkaitan dengan keselamatan penumpang.',
    lessons: [
      { slug: 'prinsip-rem-hidrolik', title: 'Prinsip kerja rem hidrolik', duration: '10:55' },
      { slug: 'rem-cakram-tromol', title: 'Rem cakram vs tromol', duration: '9:22' },
      { slug: 'gejala-rem', title: 'Gejala rem bermasalah dan penyebabnya', duration: '12:11' },
      { slug: 'inspeksi-rem', title: 'Prosedur inspeksi rem sesuai SOP', duration: '13:40' },
    ],
  },
  {
    title: 'Diagnosis dengan scanner',
    summary: 'Membaca data langsung dari ECU — bagian yang paling membedakan mekanik modern dari yang menebak.',
    lessons: [
      { slug: 'listrik-dasar', title: 'Listrik dasar yang perlu dipahami dulu', duration: '11:18' },
      { slug: 'sensor-efi', title: 'Sensor EFI dan apa yang sebenarnya mereka baca', duration: '15:27' },
      { slug: 'menghubungkan-scanner', title: 'Menghubungkan scanner ke port OBD-II', duration: '8:52' },
      { slug: 'membaca-kode-dtc', title: 'Membaca kode DTC: struktur dan artinya', duration: '14:49' },
      { slug: 'live-data', title: 'Live data: mana yang normal, mana yang tidak', duration: '17:06' },
      { slug: 'studi-kasus-scanner', title: 'Studi kasus: check engine menyala — dari scan sampai kesimpulan', duration: '21:34' },
    ],
  },
  {
    title: 'Perawatan berkala & langkah selanjutnya',
    summary: 'Menyatukan semuanya menjadi prosedur kerja yang runtut.',
    lessons: [
      { slug: 'jadwal-servis', title: 'Jadwal servis dan apa yang dikerjakan', duration: '10:26' },
      { slug: 'checklist-inspeksi', title: 'Menyusun checklist inspeksi sendiri', duration: '12:15' },
      { slug: 'komunikasi-customer', title: 'Menjelaskan temuan ke pemilik kendaraan', duration: '9:58' },
      { slug: 'langkah-berikutnya', title: 'Ke mana setelah kelas ini', duration: '6:44' },
    ],
  },
];

export interface DigitalCourse {
  /** Ubah ke true hanya setelah materi, video, dan checkout benar-benar ada. */
  ready: boolean;
  /** Null selama payment gateway belum tersambung. */
  checkoutUrl: string | null;
  eyebrow: string;
  headline: string;
  subheadline: string;
  price: string;
  strikePrice?: string;
  access: string;
  outcomes: string[];
  includes: { title: string; description: string }[];
  guarantee?: string;
  requirements: string[];
}

export const digitalCourse: DigitalCourse = {
  ready: false,
  checkoutUrl: null,

  eyebrow: 'Kelas online',
  headline: 'Pahami dasar kerja mesin sebelum menyentuh kunci.',
  subheadline:
    'Materi fundamental otomotif yang bisa dipelajari dari rumah, disusun oleh trainer yang mengerjakan kendaraan customer setiap hari.',

  price: 'Rp 99.000',
  strikePrice: 'Rp 249.000',
  access: 'Akses selamanya',

  outcomes: [
    'Memahami cara kerja mesin bensin dan komponen utamanya',
    'Membaca gejala kerusakan dari suara, bau, dan indikator',
    'Mengenali prosedur perawatan berkala dan urutannya',
    'Memahami istilah teknis yang dipakai di bengkel',
  ],

  includes: [
    { title: 'Akses selamanya', description: 'Sekali beli, materi bisa diputar ulang kapan saja termasuk pembaruan.' },
    { title: 'Bisa ditonton di HP', description: 'Tidak perlu laptop. Materi dirancang untuk layar kecil.' },
    { title: 'Sertifikat penyelesaian', description: 'Diberikan setelah seluruh modul diselesaikan.' },
    { title: 'Tanya trainer', description: 'Kirim pertanyaan lewat grup peserta kelas online.' },
  ],

  guarantee: 'Garansi 7 hari uang kembali. Tidak cocok, uangmu kembali penuh.',

  requirements: [
    'Tidak perlu latar belakang otomotif',
    'Bisa diakses dari HP, tablet, atau laptop',
    'Butuh koneksi internet untuk memutar video',
  ],
};

/** Angka turunan — dihitung, bukan diketik, supaya tidak pernah meleset. */
const allLessons = courseModules.flatMap((m) => m.lessons);
const totalSeconds = allLessons.reduce((sum, l) => {
  const [min, sec] = l.duration.split(':').map(Number);
  return sum + min * 60 + sec;
}, 0);

export const courseStats = {
  modules: courseModules.length,
  lessons: allLessons.length,
  freeLessons: allLessons.filter((l) => l.free).length,
  completed: allLessons.filter((l) => l.done).length,
  totalDuration: `${Math.floor(totalSeconds / 3600)} jam ${Math.round((totalSeconds % 3600) / 60)} menit`,
};

export const digitalFaqs: FaqItem[] = [
  {
    question: 'Berapa lama saya bisa mengakses materinya?',
    answer: 'Selamanya. Sekali membeli, kamu bisa memutar ulang seluruh materi kapan saja, termasuk pembaruan yang kami tambahkan nanti.',
  },
  {
    question: 'Apakah bisa ditonton dari HP?',
    answer: 'Bisa. Seluruh materi dirancang untuk layar kecil dan dapat diakses dari HP, tablet, maupun laptop lewat browser.',
  },
  {
    question: 'Apakah dapat sertifikat?',
    answer: 'Ya, sertifikat penyelesaian kelas online diberikan setelah seluruh modul selesai. Ini berbeda dengan sertifikat kelulusan program offline yang mencakup praktik, OJT, dan assessment kompetensi.',
  },
  {
    question: 'Bisakah saya bertanya kalau ada yang tidak dipahami?',
    answer: 'Bisa. Peserta kelas online mendapat akses ke grup tanya jawab yang didampingi trainer.',
  },
  {
    question: 'Bagaimana kalau materinya tidak sesuai harapan?',
    answer: 'Ada garansi 7 hari uang kembali. Kalau menurutmu materinya tidak sesuai, uangmu kembali penuh tanpa perlu alasan panjang.',
  },
  {
    question: 'Apa bedanya dengan program offline Pitcar Academy?',
    answer:
      'Kelas online membangun pemahaman dasar. Program offline memberi praktik langsung pada kendaraan, OJT di bengkel yang beroperasi, dan assessment kompetensi.',
  },
];

/* ============================================================================
   KELAS INSPEKSI MOBIL BEKAS
   Produk digital pertama yang benar-benar dijual. Halaman: /kelas-online-inspeksi/
   ========================================================================== */

export interface InspectionCourse {
  /** Students who hold the class now, filled from the LMS at build time. */
  students?: number;
  /** Ratings and the reviews the team chose, filled from the LMS at build time. */
  reviews?: CourseReviews;
  /**
   * Link checkout platform pembayaran. Selama null, tombol beli membuka
   * WhatsApp dengan pesan yang sudah terisi — pembelian tetap bisa terjadi,
   * hanya belum otomatis.
   */
  checkoutUrl: string | null;
  name: string;
  /** Angka tanpa format, untuk structured data dan event analytics. */
  price: number;
  priceDisplay: string;
  strikePriceDisplay: string;
  priceLabel: string;
  headline: string;
  subheadline: string;
  tagline: string;
  includes: { title: string; description: string }[];
  /**
   * Area yang tercantum di form inspeksi, sesuai yang tampak di materi promosi.
   * !! VERIFY terhadap form final sebelum materi berubah.
   */
  inspectionAreas: { title: string; description: string }[];
  steps: { title: string; description: string }[];
  audience: string[];
  notFor: string[];
  refundNote: string;
}

export const inspectionCourse: InspectionCourse = {
  // Our own checkout (DOKU) since the move off Scalev. The slug is the course row in the LMS database.
  checkoutUrl: '/checkout/?kelas=kelas-online-inspeksi',
  name: 'Kelas Inspeksi Mobil Bekas',
  price: 99000,
  priceDisplay: 'Rp99.000',
  strikePriceDisplay: 'Rp499.000',
  priceLabel: 'Harga launch',
  headline: 'Salah Pilih Mobil Bekas Bisa Bikin Rugi Jutaan Rupiah.',
  subheadline:
    'Pelajari cara mengecek mobil bekas secara sistematis — apa yang perlu dicek, apa yang perlu diwaspadai, dan kapan sebuah temuan perlu dibawa ke mekanik — sebelum kamu mengeluarkan uang puluhan hingga ratusan juta. Kamu tidak perlu paham mesin dulu: kelas ini disusun untuk pemula dan first-time buyer.',
  tagline: 'Cek dulu. Pahami kondisinya. Baru putuskan.',

  includes: [
    {
      title: 'Video pembelajaran',
      description: 'Materi inspeksi mobil bekas yang bisa kamu putar ulang sebelum berangkat melihat unit.',
    },
    {
      title: 'Form Inspeksi Mobil Bekas',
      description: 'Checklist dan scoring kendaraan dalam satu form. Dibawa saat cek unit, diisi di tempat.',
    },
    {
      title: 'Ringkasan red flag',
      description: 'Daftar tanda bahaya yang tidak boleh kamu abaikan, dalam format yang cepat dibaca.',
    },
    {
      title: 'Lifetime access',
      description: 'Sekali bayar. Berguna lagi saat kamu, keluarga, atau teman mencari mobil berikutnya.',
    },
    {
      title: 'Akses langsung',
      description: 'Materi terbuka otomatis setelah pembayaran. Tidak perlu menunggu jadwal kelas.',
    },
  ],

  inspectionAreas: [
    { title: 'Eksterior', description: 'Bodi, cat, celah panel, kaca, lampu, dan ban.' },
    { title: 'Interior', description: 'Jok, dashboard, AC, fitur kabin, dan tanda pemakaian.' },
    { title: 'Mesin', description: 'Kondisi ruang mesin, kebocoran, suara, dan asap.' },
    { title: 'Transmisi', description: 'Perpindahan gigi, hentakan, dan respons saat jalan.' },
    { title: 'Kaki-kaki', description: 'Suspensi, kemudi, dan bunyi saat melewati jalan tidak rata.' },
    { title: 'Elektrikal', description: 'Lampu indikator, aki, kelistrikan, dan fitur elektronik.' },
  ],

  steps: [
    {
      title: 'Pelajari materinya',
      description: 'Tonton videonya dulu di rumah. Pahami urutan pengecekan dan apa arti setiap temuan.',
    },
    {
      title: 'Bawa form saat cek unit',
      description: 'Isi checklist area demi area. Tidak ada bagian yang terlewat karena terburu-buru atau terbawa suasana.',
    },
    {
      title: 'Lihat skor dan red flag',
      description: 'Scoring membantu membandingkan unit secara objektif. Red flag memberi tahu kapan harus berhenti atau minta pemeriksaan lanjutan.',
    },
    {
      title: 'Putuskan dengan tenang',
      description: 'Lanjut, tawar dengan dasar yang jelas, bawa ke mekanik untuk cek mendalam, atau tinggalkan.',
    },
  ],

  audience: [
    'Calon pembeli mobil bekas',
    'First-time buyer yang baru pertama kali membeli mobil',
    'Pemula yang belum tahu harus mulai mengecek dari mana',
    'Penghobi otomotif',
    'Siswa atau pemula otomotif yang ingin memahami dasar inspeksi kendaraan',
  ],

  // Ditulis terang-terangan: pembeli produk digital tanpa refund berhak tahu
  // batasnya sebelum membayar, bukan sesudah.
  notFor: [
    'Tidak menjadikan kamu inspector profesional.',
    'Tidak menggantikan pemeriksaan teknis mendalam oleh mekanik atau inspector profesional.',
    'Tidak menjamin sebuah kendaraan bebas dari masalah.',
  ],

  // Reassurance di titik CTA: hapus keraguan beli. Kebijakan no-refund tetap
  // dijawab jujur di FAQ (lihat inspectionFaqs) dan notFor di atas.
  refundNote:
    'Akses langsung setelah bayar · Dibantu admin via WhatsApp jika ada kendala · Bayar dengan QRIS, transfer bank, atau e-wallet',
};

export const inspectionFaqs: FaqItem[] = [
  {
    question: 'Saya tidak paham mesin sama sekali. Apakah bisa mengikuti?',
    answer:
      'Bisa. Kelas ini disusun untuk pemula dan first-time buyer. Fokusnya adalah apa yang perlu dicek dan apa yang perlu diwaspadai, bukan cara memperbaiki kendaraan.',
  },
  {
    question: 'Bagaimana cara mengakses materinya?',
    answer:
      'Akses materi terbuka otomatis setelah pembayaran berhasil. Kamu tidak perlu menunggu jadwal kelas atau konfirmasi manual.',
  },
  {
    question: 'Berapa lama saya bisa mengakses kelasnya?',
    answer:
      'Selamanya. Sekali bayar, kamu bisa membuka materi dan form inspeksi kapan saja, termasuk saat mencari mobil berikutnya.',
  },
  {
    question: 'Apa itu Form Inspeksi Mobil Bekas?',
    answer:
      'Form yang menggabungkan checklist dan scoring kendaraan. Kamu mengisinya saat melihat unit, area demi area, sehingga hasil pengecekan tercatat dan bisa dibandingkan antar-unit.',
  },
  {
    question: 'Setelah ikut kelas ini, apakah saya tidak perlu lagi membawa mekanik?',
    answer:
      'Tidak begitu. Kelas ini membantu pengecekan awal secara lebih sistematis dan membantu kamu tahu kapan sebuah temuan perlu diperiksa lebih lanjut. Untuk pemeriksaan teknis mendalam, tetap libatkan mekanik atau inspector profesional.',
  },
  {
    question: 'Apakah bisa refund?',
    answer:
      'Tidak. Karena produk digital dapat langsung diakses setelah pembayaran, pembelian bersifat final dan tidak dapat direfund. Baca dulu isi dan batasan kelas di halaman ini sebelum membeli.',
  },
];

/* ============================================================================
   KELAS DASAR PEMERIKSAAN & PERAWATAN AC MOBIL
   Produk digital kedua. Halaman: /kelas-online-ac/
   Semua isi diambil dari materi promosi; tidak ada klaim durasi video atau
   nama pengajar karena keduanya belum dikonfirmasi.
   ========================================================================== */

export interface AcCourse {
  /** Students who hold the class now, filled from the LMS at build time. */
  students?: number;
  /** Ratings and the reviews the team chose, filled from the LMS at build time. */
  reviews?: CourseReviews;
  checkoutUrl: string;
  name: string;
  /** Nama pendek untuk judul tab, katalog, dan event analytics. */
  shortName: string;
  /** Angka tanpa format, untuk structured data dan event analytics. */
  price: number;
  priceDisplay: string;
  strikePriceDisplay: string;
  /** Strike price minus price, from the two above, so it follows a live price. */
  readonly savingsDisplay: string;
  priceLabel: string;
  headline: string;
  subheadline: string;
  /** Kalimat kedua subheadline; disembunyikan di layar sempit supaya tombol beli tetap di atas lipatan. */
  subheadlineNote: string;
  tagline: string;
  /** Empat hal yang didapat, urutan sama dengan materi promosi. */
  features: { title: string; description: string }[];
  /** Empat video, berurutan. Judul dan isi persis seperti materi promosi. */
  modules: { title: string; description: string }[];
  pdf: { title: string; summary: string; contents: string[] };
  steps: { title: string; description: string }[];
  audience: string[];
  notFor: string[];
  /** Strip kepercayaan di bawah hero, dari footer materi promosi. */
  trust: string[];
  refundNote: string;
}

export const acCourse: AcCourse = {
  // Our own checkout (DOKU) since the move off Scalev. The slug is the course row in the LMS database.
  checkoutUrl: '/checkout/?kelas=kelas-online-ac',
  name: 'Kelas Dasar Pemeriksaan & Perawatan AC Mobil',
  shortName: 'Kelas AC Mobil',
  price: 99000,
  priceDisplay: 'Rp99.000',
  strikePriceDisplay: 'Rp499.000',
  get savingsDisplay() {
    return rupiah(parseRupiah(this.strikePriceDisplay) - this.price);
  },
  priceLabel: 'Harga spesial launching',
  headline: 'AC nggak dingin bukan berarti kurang freon.',
  subheadline:
    'Pelajari cara kerja setiap komponen AC mobil, cara memeriksanya dengan manifold gauge, cara melacak gangguan elektrikal, sampai cuci AC tanpa bongkar.',
  subheadlineNote: 'Empat video dan satu modul PDF, disusun mekanik bengkel spesialis tune up dan AC.',
  tagline: 'Periksa dulu. Temukan penyebabnya. Baru tangani.',

  features: [
    {
      title: '4 video pembelajaran',
      description: 'Dari mengenal komponen sampai cuci AC tanpa bongkar, berurutan seperti urutan kerja di bengkel.',
    },
    {
      title: 'Modul pendamping (PDF)',
      description: 'Rangkuman, ilustrasi, dan langkah pemeriksaan yang bisa dibuka dari HP saat kamu praktik.',
    },
    {
      title: 'Studi kasus & troubleshooting nyata',
      description: 'Gejala, pemeriksaan, dan analisis kerusakan dari kasus AC mobil yang ditangani di bengkel.',
    },
    {
      title: 'Akses selamanya',
      description: 'Sekali bayar. Putar ulang bagian yang kamu butuhkan setiap kali ketemu kasus baru.',
    },
  ],

  modules: [
    {
      title: 'Mengenal Komponen AC Mobil',
      description: 'Fungsi, letak, dan cara kerja setiap komponen AC: dari kompresor sampai evaporator.',
    },
    {
      title: 'General Check Up AC — Part 1',
      description: 'Cara menggunakan manifold gauge dan urutan pemeriksaan dasar sebelum menyimpulkan apa pun.',
    },
    {
      title: 'General Check Up AC — Part 2',
      description: 'Troubleshooting sistem elektrikal dan analisis kerusakan: melacak penyebab, bukan menebak.',
    },
    {
      title: 'Cuci AC Tanpa Bongkar',
      description: 'Pembersihan blower, evaporator, filter kabin, dan kondensor menggunakan alat endoskopi.',
    },
  ],

  pdf: {
    title: 'Modul Pendamping AC Mobil (PDF)',
    summary:
      'Rangkuman tiap video dalam format yang cepat dibaca, supaya kamu tidak perlu memutar ulang video saat tangan sedang di kap mesin.',
    contents: [
      'Rangkuman materi setiap video',
      'Ilustrasi komponen dan alur kerja AC',
      'Langkah pemeriksaan dan troubleshooting',
      'Panduan cuci AC tanpa bongkar',
      'Studi kasus dan soal latihan',
    ],
  },

  steps: [
    {
      title: 'Bayar, materi langsung terbuka',
      description: 'Setelah pembayaran berhasil, akses materi terbuka otomatis. Tidak perlu menunggu jadwal kelas.',
    },
    {
      title: 'Tonton empat videonya berurutan',
      description: 'Mulai dari komponen, lalu pemeriksaan, baru cuci AC. Urutannya memang dibuat seperti urutan kerja.',
    },
    {
      title: 'Buka modul PDF saat praktik',
      description: 'Langkah pemeriksaan dan troubleshooting ada di PDF, siap dibuka dari HP di samping mobil.',
    },
    {
      title: 'Ulangi setiap ketemu kasus baru',
      description: 'Akses selamanya. Saat menemukan gejala yang belum pernah kamu tangani, putar lagi bagian yang relevan.',
    },
  ],

  audience: [
    'Siswa SMK otomotif yang ingin paham AC mobil lebih dari yang ada di buku',
    'Mekanik pemula yang sering menerima keluhan AC tapi belum punya urutan periksa',
    'Penggemar otomotif yang ingin merawat AC mobilnya sendiri',
    'Pemilik mobil yang ingin paham kondisi AC-nya sebelum ke bengkel',
  ],

  // Ditulis terang-terangan, sebelum harga: pembeli produk digital tanpa
  // refund berhak tahu batasnya sebelum membayar.
  notFor: [
    'Bukan sertifikasi teknisi AC dan tidak menerbitkan sertifikat kompetensi.',
    'Alat seperti manifold gauge dan endoskopi tidak termasuk. Kelas ini mengajarkan cara memakainya.',
    'Tidak menggantikan praktik langsung dan pendampingan di bengkel untuk kasus yang rumit.',
    'Tidak menjamin setiap gangguan AC bisa kamu selesaikan sendiri.',
  ],

  trust: [
    'Materi dari mekanik berpengalaman',
    'Bisa diakses di HP dan laptop',
    'Pembayaran aman, proses cepat',
    'Cocok untuk siswa SMK, mekanik pemula, dan penggemar otomotif',
  ],

  refundNote:
    'Karena produk digital dapat langsung diakses setelah pembayaran, pembelian bersifat final dan tidak dapat direfund.',
};

export const acFaqs: FaqItem[] = [
  {
    question: 'Saya belum pernah pegang AC mobil sama sekali. Bisa ikut?',
    answer:
      'Bisa. Ini kelas dasar: video pertama mulai dari mengenal fungsi dan letak setiap komponen, baru masuk ke pemeriksaan dan troubleshooting.',
  },
  {
    question: 'Apakah saya harus punya manifold gauge atau endoskopi?',
    answer:
      'Tidak untuk mengikuti kelasnya. Video menunjukkan cara memakai alat-alat itu; alatnya baru kamu butuhkan saat mempraktikkan sendiri. Alat tidak termasuk dalam harga kelas.',
  },
  {
    question: 'Bagaimana cara mengakses materinya?',
    answer:
      'Akses materi terbuka otomatis setelah pembayaran berhasil. Kamu tidak perlu menunggu jadwal kelas atau konfirmasi manual.',
  },
  {
    question: 'Berapa lama saya bisa mengakses kelasnya?',
    answer:
      'Selamanya. Sekali bayar, empat video dan modul PDF bisa kamu buka kapan saja, dari HP maupun laptop.',
  },
  {
    question: 'Apa isi modul PDF-nya?',
    answer:
      'Rangkuman materi setiap video, ilustrasi komponen dan alur kerja, langkah pemeriksaan dan troubleshooting, panduan cuci AC tanpa bongkar, serta studi kasus dan soal latihan.',
  },
  {
    question: 'Apakah kelas ini mengajarkan cara isi freon?',
    answer:
      'Fokusnya pemeriksaan dan perawatan: mengenal komponen, general check up dengan manifold gauge, troubleshooting elektrikal, dan cuci AC tanpa bongkar. Dengan dasar itu kamu tahu kapan AC memang perlu ditambah refrigeran, dan kapan masalahnya ada di tempat lain.',
  },
  {
    question: 'Apakah bisa refund?',
    answer:
      'Tidak. Karena produk digital dapat langsung diakses setelah pembayaran, pembelian bersifat final dan tidak dapat direfund. Baca dulu isi dan batasan kelas di halaman ini sebelum membeli.',
  },
  {
    question: 'Apa bedanya dengan program offline Pitcar Academy?',
    answer:
      'Kelas online membangun pemahaman dasar yang bisa dipelajari dari rumah. Program offline memberi praktik langsung pada kendaraan, OJT di bengkel yang beroperasi, dan assessment kompetensi.',
  },
];

// Prices of the two online classes come from the LMS (admin › Produk online)
// at build time; the values written above are the fallback. Everything below,
// and every page that imports these objects, sees the live prices.
await applyLivePrices({ 'kelas-online-ac': acCourse, 'kelas-online-inspeksi': inspectionCourse });

/* ============================================================================
   KATALOG KELAS ONLINE
   Halaman: /kelas-online/ — etalase semua kelas online yang bisa dibeli.
   Setiap kartu menurunkan datanya dari objek kelas masing-masing supaya harga
   dan isi tidak pernah berbeda antara katalog dan halaman penjualan.
   ========================================================================== */

export interface OnlineCourseCard {
  id: string;
  /** Slug kelas di LMS (admin › Produk online), untuk harga yang diperbarui di browser. */
  slug: string;
  /** Halaman penjualan. */
  href: string;
  checkoutUrl: string;
  /** Siswa yang memegang kelas ini saat build; browser memperbaruinya. */
  students?: number;
  name: string;
  /** Satu kalimat yang membuat orang berhenti scroll. */
  hook: string;
  summary: string;
  price: number;
  priceDisplay: string;
  strikePriceDisplay: string;
  priceLabel: string;
  cover: string;
  coverSrcset: string;
  coverWidth: number;
  coverHeight: number;
  coverAlt: string;
  highlights: string[];
  /** Untuk siapa, dalam satu kalimat. */
  forWho: string;
  badge?: string;
}

export const onlineCourses: OnlineCourseCard[] = [
  {
    id: 'kelas-online-ac',
    slug: 'kelas-online-ac',
    href: '/kelas-online-ac/',
    checkoutUrl: acCourse.checkoutUrl,
    name: acCourse.name,
    hook: acCourse.headline,
    summary:
      'Cara kerja komponen, pemeriksaan dengan manifold gauge, troubleshooting elektrikal, dan cuci AC tanpa bongkar.',
    price: acCourse.price,
    priceDisplay: acCourse.priceDisplay,
    strikePriceDisplay: acCourse.strikePriceDisplay,
    students: acCourse.students,
    // Satu kosakata untuk atribut yang sama di kedua kartu, supaya yang
    // terbaca berbeda hanyalah perbedaan yang nyata.
    priceLabel: 'Harga launching',
    cover: '/kelas-ac/cover.webp',
    coverSrcset: '/kelas-ac/cover-640.webp 640w, /kelas-ac/cover.webp 754w',
    coverWidth: 754,
    coverHeight: 754,
    coverAlt:
      'Poster Kelas Dasar Pemeriksaan & Perawatan AC Mobil: mekanik PITCAR dan empat video, dari mengenal komponen sampai cuci AC tanpa bongkar.',
    highlights: acCourse.features.map((f) => f.title),
    forWho: 'Siswa SMK, mekanik pemula, dan penggemar otomotif',
    badge: 'Baru',
  },
  {
    id: 'kelas-inspeksi-mobil-bekas',
    slug: 'kelas-online-inspeksi',
    href: '/kelas-online-inspeksi/',
    checkoutUrl: inspectionCourse.checkoutUrl ?? '/kelas-online-inspeksi/#harga',
    name: inspectionCourse.name,
    hook: inspectionCourse.headline,
    summary:
      'Urutan pengecekan enam area, form inspeksi dengan scoring, dan ringkasan red flag untuk dibawa saat cek unit.',
    price: inspectionCourse.price,
    priceDisplay: inspectionCourse.priceDisplay,
    strikePriceDisplay: inspectionCourse.strikePriceDisplay,
    students: inspectionCourse.students,
    priceLabel: 'Harga launching',
    cover: '/kelas-inspeksi/cover.webp',
    coverSrcset: '/kelas-inspeksi/cover-640.webp 640w, /kelas-inspeksi/cover.webp 1000w',
    coverWidth: 1000,
    coverHeight: 1000,
    coverAlt:
      'Poster Kelas Inspeksi Mobil Bekas: trainer Pitcar Academy dengan form inspeksi berisi checklist eksterior, interior, mesin, transmisi, kaki-kaki, dan elektrikal.',
    highlights: ['Video pembelajaran', 'Form inspeksi + scoring', 'Ringkasan red flag', 'Akses selamanya'],
    forWho: 'Calon pembeli mobil bekas dan first-time buyer',
  },
];

export const onlineCourseFaqs: FaqItem[] = [
  {
    question: 'Bagaimana cara membeli kelasnya?',
    answer:
      'Buka halaman kelas yang kamu mau, tekan tombol beli, lalu isi form checkout dan bayar. Setelah pembayaran berhasil, akses materi terbuka otomatis.',
  },
  {
    question: 'Bisa ditonton dari HP?',
    answer: 'Bisa. Semua kelas online bisa diakses dari HP, tablet, maupun laptop lewat browser.',
  },
  {
    question: 'Berapa lama masa aksesnya?',
    answer: 'Selamanya. Sekali bayar, materi bisa diputar ulang kapan saja tanpa biaya tambahan.',
  },
  {
    question: 'Kalau mau ambil lebih dari satu kelas?',
    answer: 'Setiap kelas dibeli terpisah lewat halaman checkout masing-masing. Belum ada paket gabungan.',
  },
  {
    question: 'Apakah bisa refund?',
    answer:
      'Tidak. Karena produk digital dapat langsung diakses setelah pembayaran, pembelian bersifat final. Baca isi dan batasan tiap kelas di halamannya sebelum membeli.',
  },
  {
    question: 'Apa bedanya dengan program offline Pitcar Academy?',
    answer:
      'Kelas online membangun pemahaman yang bisa dipelajari dari rumah. Program offline memberi praktik langsung pada kendaraan, OJT di bengkel yang beroperasi, dan assessment kompetensi.',
  },
];

/* ============================================================================
   PROFIL SISWA — PROTOTIPE
   Data contoh. Belum ada autentikasi, jadi peserta di-hardcode.
   ========================================================================== */

export interface StudyDay {
  /** ISO date. */
  date: string;
  minutes: number;
}

export interface StudentProfile {
  name: string;
  email: string;
  phone: string;
  city: string;
  joinedAt: string;
  /** 28 hari terakhir, terbaru di akhir. */
  history: StudyDay[];
}

/** Aktivitas contoh: rajin di awal, sempat berhenti, lalu mulai lagi. */
function sampleHistory(): StudyDay[] {
  const pattern = [
    0, 0, 0, 0, 42, 38, 0,
    25, 31, 0, 0, 18, 44, 0,
    0, 0, 0, 0, 0, 0, 0,
    0, 0, 29, 22, 35, 14, 0,
  ];
  const today = new Date('2026-08-31T00:00:00Z');
  return pattern.map((minutes, i) => {
    const d = new Date(today);
    d.setUTCDate(d.getUTCDate() - (pattern.length - 1 - i));
    return { date: d.toISOString().slice(0, 10), minutes };
  });
}

export const studentProfile: StudentProfile = {
  name: 'Budi Santoso',
  email: 'budi.santoso@example.com',
  phone: '+62 812-3456-7890',
  city: 'Purwokerto',
  joinedAt: '2026-07-14',
  history: sampleHistory(),
};

/**
 * Statistik yang dihitung, bukan diketik.
 *
 * Sengaja TIDAK memuat peringkat, persentil, atau perbandingan dengan peserta
 * lain. Angka semacam itu tidak mengubah apa pun yang bisa dilakukan siswa
 * berikutnya — dan bagi yang tertinggal, justru alasan untuk berhenti.
 * Semua yang ada di bawah menjawab satu pertanyaan: apa langkah saya sekarang.
 */
function minutesOf(duration: string): number {
  const [m, s] = duration.split(':').map(Number);
  return m + s / 60;
}

const done = courseModules.flatMap((m) => m.lessons).filter((l) => l.done);
const remaining = courseModules.flatMap((m) => m.lessons).filter((l) => !l.done);
const activeDays = studentProfile.history.filter((d) => d.minutes > 0);

/** Rentetan hari belajar yang masih berjalan, dihitung dari hari terakhir. */
function currentStreak(): number {
  let streak = 0;
  for (let i = studentProfile.history.length - 1; i >= 0; i--) {
    if (studentProfile.history[i].minutes > 0) streak++;
    else if (streak > 0) break;
  }
  return streak;
}

const minutesLeft = remaining.reduce((sum, l) => sum + minutesOf(l.duration), 0);
const weeklyPace = activeDays.length > 0
  ? activeDays.reduce((s, d) => s + d.minutes, 0) / (studentProfile.history.length / 7)
  : 0;

export const studentStats = {
  lessonsDone: done.length,
  lessonsLeft: remaining.length,
  percent: Math.round((done.length / (done.length + remaining.length)) * 100),
  minutesWatched: Math.round(done.reduce((sum, l) => sum + minutesOf(l.duration), 0)),
  minutesLeft: Math.round(minutesLeft),
  streak: currentStreak(),
  activeDays: activeDays.length,
  /** Berapa minggu lagi jika ritme belajar bertahan seperti sekarang. */
  weeksToFinish: weeklyPace > 0 ? Math.max(1, Math.ceil(minutesLeft / weeklyPace)) : null,
  /** Modul yang belum disentuh sama sekali — ini langkah berikutnya. */
  untouchedModules: courseModules.filter((m) => m.lessons.every((l) => !l.done)).length,
};

/** Progres per modul, supaya siswa tahu persis di mana ia berhenti. */
export const moduleProgress = courseModules.map((m) => {
  const finished = m.lessons.filter((l) => l.done).length;
  return {
    title: m.title,
    finished,
    total: m.lessons.length,
    percent: Math.round((finished / m.lessons.length) * 100),
    nextLesson: m.lessons.find((l) => !l.done) ?? null,
  };
});
