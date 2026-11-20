import { acCourse } from '../content.config';
import { livePriceAttrs } from '../lib/livePrices';

/**
 * Komponen yang bisa dibuka di mobil x-ray pada homepage.
 *
 * !! VERIFY dengan trainer sebelum publish: penjelasan teknis di sini ditulis
 * dari prinsip kerja umum kendaraan, belum ditinjau trainer Pitcar.
 *
 * `program` hanya boleh menunjuk materi yang benar-benar tercantum di
 * `packages[].materials` atau kelas online yang sudah dijual. Kurikulum yang
 * tercantum adalah mobil bensin EFI, jadi komponen khusus karburator dan diesel
 * memakai `program.note` dan tidak mengklaim ada materinya.
 */
export type FuelGroup = 'bensin' | 'diesel';
export type Fuel = 'efi' | 'karbu' | 'commonrail' | 'injeksi';
export type Slot = 'mesin' | 'pendingin' | 'rem' | 'bahanbakar' | 'ac' | 'transmisi' | 'suspensi';

/** Satu turunan sistem bahan bakar di bawah Bensin atau Diesel. */
export interface Vehicle {
  id: Fuel;
  group: FuelGroup;
  label: string;
  /** Kelas kendaraan yang digambar. Bentuknya generik, bukan model pabrikan. */
  body: string;
  /** Tujuh komponen kendaraan ini, berurutan sesuai chip, untuk transmisi manual. */
  parts: string[];
}

export interface CarPart {
  id: string;
  /** Letak komponen di mobil; beberapa komponen berbagi satu letak antar jenis mobil. */
  slot: Slot;
  /** Label pendek pada callout dan chip. */
  label: string;
  name: string;
  job: string;
  /** Urutan kerja. Berurutan karena memang proses, bukan daftar fitur. */
  steps: string[];
  symptom: string;
  program: {
    /** Nilai `data-program-interest` pada form lead. */
    interest: 'basic' | 'advanced';
    level: string;
    material: string;
    /** Menggantikan kalimat standar bila materinya tidak tercantum di kurikulum. */
    note?: string;
  };
  /** Kelas online yang membahas komponen ini, bila ada. */
  online?: { href: string; label: string; price: string; live: ReturnType<typeof livePriceAttrs> };
}

export const carParts: CarPart[] = [
  {
    id: 'mesin',
    slot: 'mesin',
    label: 'Mesin',
    name: 'Mesin bensin 4 langkah',
    job: 'Mengubah campuran bensin dan udara menjadi putaran yang menggerakkan roda.',
    steps: [
      'Isap: piston turun, katup masuk terbuka, campuran udara dan bensin terisap ke silinder.',
      'Kompresi: kedua katup tertutup, piston naik dan memampatkan campuran.',
      'Usaha: busi memercikkan api, ledakan mendorong piston turun. Hanya langkah ini yang menghasilkan tenaga.',
      'Buang: piston naik lagi, katup buang terbuka, gas sisa keluar ke knalpot.',
    ],
    symptom: 'Mesin pincang, tarikan berat, atau boros sering berawal dari busi, filter udara, atau kompresi yang turun.',
    program: { interest: 'basic', level: 'Basic', material: 'Tune Up Mesin' },
  },
  {
    id: 'pendingin',
    slot: 'pendingin',
    label: 'Pendingin',
    name: 'Sistem pendingin mesin',
    job: 'Menjaga suhu kerja mesin tetap stabil dengan memindahkan panas ke udara luar.',
    steps: [
      'Water pump mendorong coolant mengelilingi blok mesin dan menyerap panasnya.',
      'Selama mesin masih dingin, thermostat menutup jalur ke radiator supaya suhu kerja cepat tercapai.',
      'Setelah panas, thermostat membuka dan coolant mengalir ke radiator.',
      'Kisi radiator dan kipas membuang panas ke udara, lalu coolant yang sudah dingin kembali ke mesin.',
    ],
    symptom: 'Jarum suhu naik saat macet biasanya mengarah ke kipas, coolant yang berkurang, atau radiator tersumbat.',
    program: { interest: 'basic', level: 'Basic', material: 'Sistem Pendingin' },
  },
  {
    id: 'rem',
    slot: 'rem',
    label: 'Rem',
    name: 'Rem cakram hidrolik',
    job: 'Mengubah injakan kaki menjadi jepitan kuat pada cakram yang ikut berputar dengan roda.',
    steps: [
      'Pedal diinjak, piston di master silinder menekan minyak rem.',
      'Minyak rem tidak bisa dimampatkan, jadi tekanannya diteruskan lewat pipa ke setiap roda.',
      'Piston kaliper terdorong dan menjepitkan kampas ke cakram.',
      'Gesekan mengubah gerak mobil menjadi panas, roda melambat.',
    ],
    symptom: 'Pedal terasa dalam atau empuk biasanya tanda minyak rem berkurang, ada udara di jalur, atau kampas menipis.',
    program: { interest: 'basic', level: 'Basic', material: 'Sistem Rem' },
  },
  {
    id: 'ecu',
    slot: 'bahanbakar',
    label: 'ECU & sensor',
    name: 'ECU, sensor, dan scanner',
    job: 'Komputer mesin membaca sensor lalu mengatur semprotan bensin dan waktu pengapian.',
    steps: [
      'Sensor membaca kondisi mesin: jumlah udara masuk, suhu, putaran, dan sisa oksigen di gas buang.',
      'ECU menghitung berapa lama injektor menyemprot dan kapan busi menyala.',
      'Bila bacaan sensor di luar batas, ECU menyimpan kode kerusakan (DTC) dan menyalakan lampu check engine.',
      'Scanner yang dicolok ke port OBD-II membaca kode itu beserta data sensor secara langsung.',
    ],
    symptom: 'Check engine menyala tidak selalu berarti sensor rusak. Kodenya menunjukkan sistem mana yang perlu diperiksa dulu.',
    program: { interest: 'basic', level: 'Basic', material: 'Basic EFI & Scanner' },
  },
  {
    id: 'ac',
    slot: 'ac',
    label: 'AC',
    name: 'Sistem AC mobil',
    job: 'Memindahkan panas dari kabin ke luar mobil lewat refrigeran yang terus bersirkulasi.',
    steps: [
      'Kompresor memampatkan refrigeran menjadi gas panas bertekanan tinggi.',
      'Di kondensor, gas itu melepas panas ke udara luar dan berubah menjadi cair.',
      'Katup ekspansi menurunkan tekanannya, suhu refrigeran turun drastis.',
      'Di evaporator, refrigeran dingin menyerap panas udara kabin. Blower meniupkan udara yang sudah dingin.',
    ],
    symptom: 'AC tidak dingin belum tentu kurang freon. Bisa kompresor, kondensor kotor, atau kelistrikan.',
    program: { interest: 'basic', level: 'Basic', material: 'Sistem Pendingin' },
    online: { href: '/kelas-online-ac/', label: 'Kelas online AC mobil', price: acCourse.priceDisplay, live: livePriceAttrs('kelas-online-ac', acCourse) },
  },
  {
    id: 'transmisi',
    slot: 'transmisi',
    label: 'Transmisi',
    name: 'Transmisi',
    job: 'Menyesuaikan putaran mesin dengan kebutuhan roda: kuat saat mulai jalan, cepat saat melaju.',
    steps: [
      'Kopling menyambung dan memutus putaran mesin ke transmisi.',
      'Gigi rendah: roda gigi kecil memutar roda gigi besar. Putaran roda lambat, tetapi dorongannya besar.',
      'Gigi tinggi: perbandingannya dibalik. Putaran roda cepat dengan dorongan lebih kecil.',
      'Putaran diteruskan ke roda penggerak lewat poros dan gardan.',
    ],
    symptom: 'Gigi sulit masuk atau bunyi kasar saat pindah gigi sering terkait kopling atau oli transmisi.',
    program: { interest: 'advanced', level: 'Advance', material: 'Sistem Transmisi' },
  },
  {
    id: 'suspensi',
    slot: 'suspensi',
    label: 'Suspensi',
    name: 'Suspensi',
    job: 'Menjaga ban tetap menapak di jalan dan meredam guncangan sebelum sampai ke bodi.',
    steps: [
      'Roda melewati benjolan dan terdorong ke atas.',
      'Pegas memendek dan menyimpan tenaga guncangan itu.',
      'Shock absorber menahan pegas agar tidak memantul berulang-ulang.',
      'Bodi tetap stabil, ban kembali menapak di jalan.',
    ],
    symptom: 'Mobil terus mengayun setelah melewati polisi tidur menandakan shock absorber sudah lemah.',
    program: { interest: 'advanced', level: 'Advance', material: 'Sistem Suspensi' },
  },
  {
    id: 'mesin-diesel',
    slot: 'mesin',
    label: 'Mesin',
    name: 'Mesin diesel 4 langkah',
    job: 'Membakar solar dengan panas udara yang dimampatkan, tanpa busi, lalu mengubahnya menjadi putaran.',
    steps: [
      'Isap: piston turun dan hanya udara yang terisap ke silinder, tanpa bahan bakar.',
      'Kompresi: udara dimampatkan jauh lebih rapat daripada di mesin bensin sampai suhunya sangat tinggi.',
      'Usaha: injektor menyemprotkan solar ke udara panas itu. Solar menyala sendiri dan mendorong piston turun.',
      'Buang: piston naik lagi dan gas sisa keluar ke knalpot, sering kali sambil memutar turbo.',
    ],
    symptom: 'Asap hitam pekat, susah hidup saat dingin, atau tenaga hilang biasanya mengarah ke injektor, filter solar, atau kompresi.',
    program: {
      interest: 'advanced',
      level: 'Advance',
      material: 'Overhaul Engine',
      note: 'Kurikulum yang tercantum berfokus pada mobil bensin EFI. Prinsip overhaul mesin dipelajari di level Advance; tanyakan ke tim kami apakah unit diesel ikut dipraktikkan di angkatanmu.',
    },
  },
  {
    id: 'karburator',
    slot: 'bahanbakar',
    label: 'Karburator',
    name: 'Karburator',
    job: 'Mencampur bensin dengan udara secara mekanis, tanpa komputer, sebelum masuk ke mesin.',
    steps: [
      'Piston mengisap udara lewat saringan udara dan masuk ke karburator.',
      'Di venturi, saluran menyempit. Udara mengalir lebih cepat dan tekanannya turun.',
      'Tekanan rendah itu mengisap bensin dari ruang pelampung lewat main jet, lalu bensin mengabut di aliran udara.',
      'Katup gas, yang terhubung ke pedal, mengatur berapa banyak campuran yang masuk ke mesin.',
    ],
    symptom: 'Idle tidak stabil, brebet, atau boros sering berasal dari jet tersumbat, pelampung bermasalah, atau setelan campuran yang bergeser.',
    program: {
      interest: 'basic',
      level: 'Basic',
      material: 'Tune Up Mesin',
      note: 'Kurikulum yang tercantum berfokus pada mobil bensin EFI. Tanyakan ke tim kami apakah setelan karburator ikut dibahas di materi Tune Up Mesin angkatanmu.',
    },
  },
  {
    id: 'commonrail',
    slot: 'bahanbakar',
    label: 'Common rail',
    name: 'Common rail, ECU, dan injektor diesel',
    job: 'Menyimpan solar bertekanan sangat tinggi di satu pipa, lalu menyemprotkannya secara elektronik ke tiap silinder.',
    steps: [
      'Solar dari tangki disaring, lalu pompa tekanan tinggi menaikkan tekanannya.',
      'Solar bertekanan itu ditampung di rail, pipa yang dipakai bersama oleh semua injektor.',
      'ECU membaca sensor, termasuk sensor tekanan rail, lalu menentukan kapan dan berapa lama injektor membuka.',
      'Injektor menyemprotkan kabut solar langsung ke ruang bakar, dan sisa solar kembali ke tangki.',
    ],
    symptom: 'Mesin pincang, susah hidup, atau check engine pada diesel modern sering terkait injektor, tekanan rail yang turun, atau solar yang tercampur air.',
    program: {
      interest: 'basic',
      level: 'Basic',
      material: 'Basic EFI & Scanner',
      note: 'Kurikulum yang tercantum berfokus pada mobil bensin EFI. Cara membaca sensor dan kode kerusakan dengan scanner dipelajari di level Basic; tanyakan ke tim kami soal cakupan diesel common rail.',
    },
  },
  {
    id: 'pompa-injeksi',
    slot: 'bahanbakar',
    label: 'Pompa injeksi',
    name: 'Pompa injeksi mekanis dan nozzle',
    job: 'Menakar dan menekan solar secara mekanis, tanpa komputer, lalu mengirimnya ke tiap silinder pada saat yang tepat.',
    steps: [
      'Solar dari tangki disaring lalu masuk ke pompa injeksi yang diputar mesin.',
      'Poros nok di dalam pompa mengangkat plunger. Tiap plunger menekan solar untuk satu silinder.',
      'Solar bertekanan mengalir lewat pipa baja ke nozzle, mengangkat jarumnya, lalu menyembur sebagai kabut.',
      'Pedal gas dan governor memutar plunger untuk menambah atau mengurangi solar. Udara masuk tidak diatur.',
    ],
    symptom: 'Asap hitam, mesin pincang, atau susah hidup pada diesel lama sering berasal dari nozzle yang menetes, timing pompa yang bergeser, atau udara di jalur solar.',
    program: {
      interest: 'advanced',
      level: 'Advance',
      material: 'Troubleshooting Lanjutan',
      note: 'Kurikulum yang tercantum berfokus pada mobil bensin EFI. Tanyakan ke tim kami apakah diesel konvensional ikut dibahas di angkatanmu.',
    },
  },
  {
    id: 'transmisi-matic',
    slot: 'transmisi',
    label: 'Transmisi',
    name: 'Transmisi otomatis',
    job: 'Memindahkan gigi sendiri dengan tekanan oli, tanpa pedal kopling.',
    steps: [
      'Torque converter meneruskan putaran mesin lewat oli, sehingga mesin tetap hidup saat mobil berhenti.',
      'Pompa oli membangkitkan tekanan, dan valve body mengarahkannya ke kopling yang tepat.',
      'Kopling menahan atau melepas bagian dari roda gigi planetary. Kombinasinya menentukan gigi yang aktif.',
      'Putaran keluar dari carrier planetary ke poros propeler, lalu ke gardan.',
    ],
    symptom: 'Perpindahan gigi menghentak, putaran mesin naik tanpa diikuti laju, atau telat masuk gigi sering terkait oli transmisi (ATF), solenoid, atau kopling yang aus.',
    program: {
      interest: 'advanced',
      level: 'Advance',
      material: 'Sistem Transmisi',
      note: 'Advance, materi Sistem Transmisi. Tanyakan ke tim kami apakah transmisi otomatis ikut dipraktikkan di angkatanmu.',
    },
  },
];

const base = ['mesin', 'pendingin', 'rem', 'ecu', 'ac', 'transmisi', 'suspensi'];
const swap = (changes: Record<string, string>) => base.map((id) => changes[id] ?? id);

export const fuelGroups: { id: FuelGroup; label: string }[] = [
  { id: 'bensin', label: 'Bensin' },
  { id: 'diesel', label: 'Diesel' },
];

export const vehicles: Vehicle[] = [
  { id: 'efi', group: 'bensin', label: 'EFI', body: 'MPV bensin EFI', parts: base },
  { id: 'karbu', group: 'bensin', label: 'Karburator', body: 'Minibus bensin karburator', parts: swap({ ecu: 'karburator' }) },
  { id: 'commonrail', group: 'diesel', label: 'Common rail', body: 'SUV diesel common rail', parts: swap({ mesin: 'mesin-diesel', ecu: 'commonrail' }) },
  { id: 'injeksi', group: 'diesel', label: 'Konvensional', body: 'Minibus diesel konvensional', parts: swap({ mesin: 'mesin-diesel', ecu: 'pompa-injeksi' }) },
];

/** Komponen yang menggantikan `transmisi` bila transmisi matic dipilih. */
export const MATIC_PART = 'transmisi-matic';

/**
 * Contoh mobil di Indonesia untuk tiap kombinasi sistem bahan bakar dan transmisi.
 *
 * !! VERIFY dengan trainer sebelum publish: daftar ini ditulis dari pengetahuan
 * umum, belum diperiksa per tahun produksi dan varian. Banyak model berganti
 * sistem di tengah masa edarnya, jadi generasinya ikut disebut.
 * `matic` di sini berarti matic konvensional (torque converter), sesuai model
 * yang digambar, bukan CVT.
 */
export interface Examples {
  modern: string;
  classic: string;
}

export const examples: Record<Fuel, { manual: Examples; matic: Examples }> = {
  efi: {
    manual: {
      modern: 'Toyota Avanza, Daihatsu Xenia, Honda Brio, Suzuki Ertiga.',
      classic: 'Generasi awal injeksi: Toyota Kijang kapsul EFI, Toyota Soluna, Honda City Type Z.',
    },
    matic: {
      modern: 'Toyota Rush, Daihatsu Terios, Suzuki Ertiga, Toyota Innova bensin. Banyak mobil baru lain memakai CVT, jenis matic yang cara kerjanya berbeda.',
      classic: 'Toyota Kijang kapsul LGX matic, Toyota Great Corolla matic.',
    },
  },
  karbu: {
    manual: {
      modern: 'Tidak ada. Mobil baru sudah tidak memakai karburator karena tidak bisa memenuhi aturan emisi.',
      classic: 'Toyota Kijang Super dan Grand, Suzuki Carry 1.0, Daihatsu Zebra, Suzuki Katana.',
    },
    matic: {
      modern: 'Tidak ada.',
      classic: 'Jarang ditemui. Beberapa sedan era 1980-an, misalnya Honda Accord Prestige matic dan Mercedes-Benz 230 (W123).',
    },
  },
  commonrail: {
    manual: {
      modern: 'Toyota Innova diesel, Toyota Fortuner, Mitsubishi Pajero Sport, Toyota Hilux, Isuzu D-Max.',
      classic: 'Belum ada yang tergolong klasik. Generasi awalnya di Indonesia: Toyota Kijang Innova diesel (2004) dan Mitsubishi Pajero Sport Dakar (2009).',
    },
    matic: {
      modern: 'Mitsubishi Pajero Sport, Toyota Fortuner, Toyota Innova diesel, Isuzu mu-X.',
      classic: 'Belum ada yang tergolong klasik. Generasi awalnya: Toyota Kijang Innova diesel matic dan Mitsubishi Pajero Sport Dakar.',
    },
  },
  injeksi: {
    manual: {
      modern: 'Hampir tidak ada lagi. Sejak aturan emisi Euro 4 berlaku untuk diesel, mobil baru beralih ke common rail. Isuzu Panther dan Mitsubishi L300 termasuk yang terakhir memakainya.',
      classic: 'Isuzu Panther, Toyota Kijang diesel, Mitsubishi L300, Daihatsu Taft.',
    },
    matic: {
      modern: 'Tidak ada.',
      classic: 'Isuzu Panther matic, Mercedes-Benz 300D (W123 dan W124).',
    },
  },
};
