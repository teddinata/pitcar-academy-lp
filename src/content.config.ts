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
   * One line explaining where `strikePriceDisplay` comes from. A crossed-out
   * number with no stated origin is the thing buyers distrust.
   *
   * !! VERIFY: the anchor must be a price the business genuinely charges or
   * will charge. An anchor nobody has ever paid is not a discount.
   */
  strikePriceNote?: string;
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
    savingsNote: 'Harga Founding Batch 2026',
    scarcityNote: 'Berlaku selama kuota Founding Batch masih tersedia.',
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
    savingsNote: 'Harga Founding Batch 2026',
    scarcityNote: 'Berlaku selama kuota Founding Batch masih tersedia.',
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
    strikePriceDisplay: 'Rp 15.000.000',
    strikePriceNote: 'harga 2 program jika diambil terpisah',
    savingsNote: 'Hemat Rp6.500.000 — Founding Batch 2026',
    scarcityNote: 'Berlaku selama kuota Founding Batch masih tersedia.',
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
      'Program Professional mencakup Level 1 dan Level 2 sekaligus, total 208 jam pembelajaran dan 2 bulan OJT. Dibanding mengambil Basic dan Advance terpisah pada harga Founding Batch, Professional lebih hemat Rp1.500.000; dibanding harga normal kedua program, hemat Rp6.500.000.',
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
    name: 'Trainer 1',
    role: 'Technical Trainer — Engine & EFI',
    credentials: [
      'Praktisi aktif jaringan bengkel PITCAR',
      'Spesialisasi engine dan sistem EFI',
      'Berpengalaman menangani diagnosis kendaraan customer',
    ],
  },
  {
    name: 'Trainer 2',
    role: 'Technical Trainer — General Repair',
    credentials: [
      'Praktisi aktif jaringan bengkel PITCAR',
      'Spesialisasi general repair dan chassis',
      'Mendampingi peserta selama praktik dan OJT',
    ],
  },
  {
    name: 'Trainer 3',
    role: 'Workshop Supervisor — OJT',
    credentials: [
      'Mengawasi ritme kerja dan SOP bengkel',
      'Menilai attitude dan kesiapan kerja peserta',
      'Menjembatani peserta dengan proses rekrutmen',
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
      'Tempat tinggal bagi peserta luar kota yang memenuhi kriteria, selama kuota Founding Batch masih tersedia.',
    publish: true,
  },
  {
    title: 'Harga Founding Batch',
    description: 'Investasi khusus peserta batch perdana, sebelum harga program disesuaikan.',
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
