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
    // Drop the file at public/trainers/roka.webp, then set:
    //   photo: '/trainers/roka.webp',
    // Left unset until the file exists — a missing path renders a broken
    // image on a live page, while the section already degrades to a
    // "Foto menyusul" placeholder when there is nothing to show.
    credentials: [
      '13 tahun pengalaman di industri otomotif',
      'Praktisi aktif di jaringan bengkel PITCAR',
    ],
  },
  {
    name: 'Dayat',
    role: 'Technical Trainer',
    // photo: '/trainers/dayat.webp',
    credentials: [
      '8 tahun pengalaman di industri otomotif',
      'Praktisi aktif di jaringan bengkel PITCAR',
    ],
  },
  {
    name: 'Hanif',
    role: 'Pengawas Pitcar Academy',
    // photo: '/trainers/hanif.webp',
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
