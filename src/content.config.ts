export const collections = {
  // Content data — semua info bisnis di sini
};

// WhatsApp configuration (not a collection, just exported config)
export const whatsappConfig = {
  phoneNumber: import.meta.env.WA_PHONE_NUMBER || '',
  messages: {
    hero: encodeURIComponent(
      'Halo Pitcar Academy, saya tertarik dengan program pelatihan mekanik. Bisa info lebih lanjut?'
    ),
    packageBasic: encodeURIComponent(
      'Halo, saya tertarik dengan PAKET BASIC Maintenance Mobil EFI - Rp 5.000.000. Bagaimana cara daftarnya?'
    ),
    packageAdvanced: encodeURIComponent(
      'Halo, saya tertarik dengan PAKET ADVANCE General Repair Mobil EFI - Rp 5.000.000. Bagaimana cara daftarnya?'
    ),
    packageProfessional: encodeURIComponent(
      'Halo, saya tertarik dengan PAKET PROFESSIONAL Level 1&2 Maintenance & General Repair - Rp 8.500.000. Bagaimana cara daftarnya?'
    ),
    sticky: encodeURIComponent(
      'Halo Pitcar Academy, saya mau tanya tentang program pelatihan mekanik.'
    ),
    faq: encodeURIComponent(
      'Halo Pitcar Academy, saya punya pertanyaan setelah membaca FAQ. Bisa dibantu?'
    ),
    ctaBottom: encodeURIComponent(
      'Halo Pitcar Academy, saya ingin mendaftar program pelatihan mekanik!'
    ),
  },
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
    savingsNote: 'Promo Early Bird Agustus',
    scarcityNote: 'Harga naik Rp 500.000 di bulan September!',
    duration: '1 Bulan',
    hours: '104 Jam',
    ojt: '2 Bulan OJT Bengkel',
    certificate: 'Sertifikat Kompetensi Level 1 Pitcar Academy',
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
    savingsNote: 'Promo Early Bird Agustus',
    scarcityNote: 'Harga naik Rp 500.000 di bulan September!',
    duration: '1 Bulan',
    hours: '104 Jam',
    ojt: '2 Bulan OJT Bengkel',
    certificate: 'Sertifikat Kompetensi Level 2 Pitcar Academy',
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
    strikePriceDisplay: 'Rp 11.000.000',
    savingsNote: 'Hemat Rp2.500.000 + Early Bird Agustus',
    scarcityNote: 'Harga naik Rp 500.000 di bulan September!',
    duration: '2 Bulan',
    hours: '208 Jam',
    ojt: '2 Bulan OJT Bengkel',
    certificate: 'Sertifikat Professional Mechanic Pitcar Academy',
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
      'Program Professional mencakup Level 1 dan Level 2 sekaligus dengan total 208 jam pembelajaran dan 2 bulan OJT, serta lebih hemat Rp1.500.000.',
  },
  {
    question: 'Di mana lokasi pelatihan?',
    answer:
      'Pelatihan Pitcar Academy dilaksanakan di Purwokerto, Banyumas, Jawa Tengah. Sesi praktik dan OJT berlangsung di lingkungan bengkel yang ditentukan oleh jaringan Pitcar Academy.',
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
];

