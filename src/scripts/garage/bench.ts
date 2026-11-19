import * as THREE from 'three';
import type { Fuel, Slot } from './car';
import { type Mats, type V3, box, cyl, ball, ring, rod, tube, helix, lathe, grp, gear, nudge } from './kit';

/**
 * Komponen di meja kerja: tiap sistem dipecah menjadi bagian yang bisa dipilih
 * dan dibongkar.
 *
 * !! VERIFY dengan trainer sebelum publish: nama dan fungsi bagian ditulis dari
 * prinsip kerja umum, belum ditinjau trainer Pitcar. Bentuknya disederhanakan
 * dan tidak mengikuti satu merek atau tipe kendaraan.
 */
export interface SubDef {
  name: string;
  info: string;
  /** Offset when fully taken apart. */
  out: V3;
  make: () => THREE.Object3D[];
  /**
   * Moves the piece while the system is running. `objs` is what `make` returned,
   * `t` is seconds, `state` names the condition the system is in (a fault, or '').
   */
  run?: (objs: THREE.Object3D[], t: number, state: string) => void;
}

/** Crank speed for everything that turns with the engine, in radians per second. */
const W = 5;
const pulse = (o: THREE.Object3D, on: boolean) => o.scale.setScalar(on ? 1.7 : 1);

export interface Assembly {
  title: string;
  subs: SubDef[];
  /** Recolours anything that must read differently on paper than at night. */
  theme?: (dark: boolean) => void;
}

const four = [-0.24, -0.08, 0.08, 0.24];

function engine(fuel: Fuel, m: Mats): Assembly {
  const subs: SubDef[] = [];

  const diesel = fuel === 'commonrail' || fuel === 'injeksi';
  if (diesel) {
    subs.push({
      name: fuel === 'injeksi' ? 'Nozzle injektor' : 'Injektor',
      info: fuel === 'injeksi'
        ? 'Membuka sendiri saat tekanan solar dari pompa injeksi cukup tinggi, lalu mengabutkannya ke ruang bakar. Mesin diesel tidak memakai busi.'
        : 'Menyemprotkan solar bertekanan sangat tinggi langsung ke ruang bakar. Mesin diesel tidak memakai busi.',
      out: [0, 0.7, 0],
      make: () => four.flatMap((x) => [cyl(0.018, 0.17, m.steel, [x, 0.44, 0]), cyl(0.03, 0.05, m.red, [x, 0.5, 0])]),
    });
  } else {
    subs.push({
      name: fuel === 'efi' ? 'Koil & busi' : 'Busi & kabel busi',
      info: fuel === 'efi'
        ? 'Koil menaikkan tegangan aki menjadi puluhan ribu volt, busi memercikkannya di ruang bakar. ECU yang menentukan waktunya.'
        : 'Busi memercikkan api di ruang bakar. Tegangan tingginya dikirim dari koil lewat distributor dan kabel busi.',
      out: [0, 0.7, 0],
      make: () => four.flatMap((x) => [cyl(0.028, 0.13, m.dark, [x, 0.44, 0]), cyl(0.036, 0.03, m.red, [x, 0.515, 0])]),
    });
  }

  subs.push(
    {
      name: 'Tutup klep',
      info: 'Menutup mekanisme katup dan menahan oli agar tidak keluar. Rembes oli di sini biasanya karena paking tutup klep mengeras.',
      out: [0, 0.5, 0],
      make: () => [
        box(0.7, 0.07, 0.27, m.red, [0, 0.345, 0]),
        ...[-0.16, 0, 0.16].map((x) => box(0.012, 0.076, 0.272, m.dark, [x, 0.345, 0])),
      ],
    },
    {
      name: 'Poros nok (camshaft)',
      info: 'Tonjolan nok menekan katup supaya terbuka pada saat yang tepat. Putarannya setengah putaran poros engkol.',
      out: [0, 0.36, 0],
      make: () => [grp([
        cyl(0.018, 0.7, m.alu, [0, 0, 0], 'x'),
        ...four.flatMap((x, i) => [-0.035, 0.035].map((d, j) => {
          const a = (i * 2 + j) * 0.9;
          return cyl(0.028, 0.022, m.steel, [x + d, Math.cos(a) * 0.012, Math.sin(a) * 0.012], 'x');
        })),
      ], [0, 0.31, 0])],
      run: ([shaft], t) => { shaft.rotation.x = (t * W) / 2; },
    },
    {
      name: 'Kepala silinder',
      info: 'Menutup bagian atas silinder dan menjadi tempat katup serta ruang bakar. Bila melengkung karena overheat, kompresi bocor.',
      out: [0, 0.22, 0],
      make: () => [box(0.72, 0.11, 0.3, m.steel, [0, 0.24, 0])],
    },
    {
      name: 'Gasket kepala silinder',
      info: 'Perapat antara blok dan kepala silinder. Menahan tekanan pembakaran sekaligus memisahkan jalur oli dan air pendingin.',
      out: [0, 0.1, 0],
      make: () => [box(0.72, 0.008, 0.32, m.brass, [0, 0.178, 0])],
    },
    {
      name: 'Blok silinder',
      info: 'Rangka utama mesin. Di dalamnya ada lubang silinder tempat piston bergerak naik turun.',
      out: [0, 0, 0],
      make: () => [box(0.72, 0.34, 0.32, m.dark, [0, 0, 0]), ...four.map((x) => cyl(0.062, 0.004, m.steel, [x, 0.171, 0]))],
    },
    {
      name: 'Piston & stang piston',
      info: 'Piston menerima dorongan pembakaran. Stang piston meneruskannya ke poros engkol.',
      out: [0, 0.02, 0.62],
      make: () => four.map((x) => grp([
        cyl(0.058, 0.07, m.alu, [x, 0.08, 0]),
        cyl(0.059, 0.006, m.dark, [x, 0.1, 0]),
        cyl(0.059, 0.006, m.dark, [x, 0.085, 0]),
        box(0.026, 0.17, 0.02, m.steel, [x, -0.04, 0]),
        cyl(0.03, 0.022, m.steel, [x, -0.13, 0], 'x'),
      ])),
      // Cylinders 1 and 4 rise together while 2 and 3 fall.
      run: (pistons, t) => pistons.forEach((p, i) => nudge(p, 'y', 0.035 * Math.cos(t * W + (i === 0 || i === 3 ? 0 : Math.PI)))),
    },
    {
      name: 'Poros engkol',
      info: 'Mengubah gerak naik turun piston menjadi putaran. Dari sini tenaga diteruskan ke roda gila dan transmisi.',
      out: [0, -0.3, 0],
      make: () => [grp([
        cyl(0.028, 0.78, m.steel, [0, 0, 0], 'x'),
        ...four.flatMap((x, i) => {
          const k = i % 3 === 0 ? 1 : -1;
          return [box(0.03, 0.1, 0.06, m.steel, [x - 0.03, 0.025 * k, 0]), box(0.03, 0.1, 0.06, m.steel, [x + 0.03, 0.025 * k, 0]), cyl(0.02, 0.06, m.alu, [x, 0.05 * k, 0], 'x')];
        }),
      ], [0, -0.2, 0])],
      run: ([crank], t) => { crank.rotation.x = -t * W; },
    },
    {
      name: 'Karter oli',
      info: 'Bak penampung oli mesin. Pompa oli mengisap dari sini lalu mengalirkannya ke bagian yang bergesekan.',
      out: [0, -0.54, 0],
      make: () => [box(0.68, 0.1, 0.28, m.dark, [0, -0.25, 0]), cyl(0.014, 0.02, m.brass, [-0.2, -0.31, 0])],
    },
    {
      name: 'Roda gila (flywheel)',
      info: 'Piringan berat yang menyimpan tenaga putar supaya putaran mesin halus. Kopling menempel di permukaannya.',
      out: [-0.32, 0, 0],
      make: () => [grp([
        gear(0.175, 0.03, m.dark, [0, 0, 0], 'x', 56),
        cyl(0.12, 0.04, m.steel, [0, 0, 0], 'x'),
        ...[0, 1, 2, 3, 4, 5].map((n) => cyl(0.012, 0.046, m.dark, [0, Math.cos(n * 1.047) * 0.06, Math.sin(n * 1.047) * 0.06], 'x')),
      ], [-0.4, -0.2, 0])],
      run: ([wheel], t) => { wheel.rotation.x = -t * W; },
    },
    {
      name: 'Timing belt & puli',
      info: 'Menyamakan putaran poros engkol dan poros nok. Bila putus saat mesin hidup, katup bisa bertabrakan dengan piston.',
      out: [0.34, 0, 0],
      make: () => [
        box(0.03, 0.46, 0.26, m.dark, [0.385, 0.06, 0]),
        gear(0.075, 0.03, m.alu, [0.42, -0.2, 0], 'x', 20),
        gear(0.05, 0.02, m.red, [0.41, 0.31, 0], 'x', 14),
        box(0.012, 0.5, 0.012, m.rubber, [0.425, 0.055, 0.058]),
        box(0.012, 0.5, 0.012, m.rubber, [0.425, 0.055, -0.058]),
      ],
    },
    {
      name: 'Filter oli',
      info: 'Menyaring kotoran dan serbuk logam dari oli. Diganti bersamaan dengan oli mesin saat servis berkala.',
      out: [0.1, -0.12, -0.38],
      make: () => [cyl(0.045, 0.1, m.alu, [0.2, -0.08, -0.21], 'z')],
    },
    {
      name: fuel === 'karbu' ? 'Intake manifold & karburator' : fuel === 'efi' ? 'Intake manifold & throttle body' : 'Intake manifold',
      info: fuel === 'karbu'
        ? 'Karburator mencampur bensin dengan udara, manifold membaginya ke tiap silinder.'
        : fuel === 'efi'
          ? 'Throttle body mengatur jumlah udara masuk, manifold membaginya ke tiap silinder. Bensin disemprotkan injektor di dekat katup masuk.'
          : 'Membagi udara ke tiap silinder. Pada diesel yang masuk hanya udara; solar disemprotkan belakangan.',
      out: [0, 0.08, -0.44],
      make: () => [
        cyl(0.045, 0.6, m.alu, [0, 0.3, -0.3], 'x'),
        ...four.map((x) => tube([[x, 0.24, -0.15], [x, 0.28, -0.22], [x, 0.3, -0.29]], 0.024, m.alu)),
        ...(fuel === 'karbu'
          ? [cyl(0.04, 0.08, m.steel, [0, 0.38, -0.3]), cyl(0.12, 0.05, m.dark, [0, 0.44, -0.3]), cyl(0.124, 0.01, m.red, [0, 0.47, -0.3])]
          : fuel === 'efi'
            ? [cyl(0.05, 0.06, m.steel, [-0.33, 0.3, -0.3], 'x'), box(0.03, 0.04, 0.03, m.red, [-0.33, 0.36, -0.3])]
            : []),
      ],
    },
  );

  if (fuel === 'commonrail') {
    subs.push({
      name: 'Turbocharger',
      info: 'Turbin diputar gas buang, lalu memampatkan udara masuk supaya lebih banyak solar bisa terbakar.',
      out: [-0.22, 0.2, 0.3],
      make: () => [cyl(0.085, 0.06, m.alu, [-0.3, 0.2, 0.22], 'z'), cyl(0.075, 0.06, m.dark, [-0.3, 0.2, 0.29], 'z'), cyl(0.03, 0.08, m.red, [-0.3, 0.2, 0.36], 'z')],
    });
  }
  if (fuel === 'karbu') {
    subs.push({
      name: 'Distributor',
      info: 'Membagi tegangan tinggi dari koil ke busi sesuai urutan pengapian. Di dalamnya ada platina atau pemicu elektronik.',
      out: [0.24, 0.12, 0.3],
      make: () => [cyl(0.04, 0.1, m.dark, [0.3, 0.1, 0.21]), cyl(0.046, 0.035, m.red, [0.3, 0.165, 0.21])],
    });
  }

  return {
    title: diesel ? 'Mesin diesel 4 silinder' : 'Mesin bensin 4 silinder',
    subs,
  };
}

function cooling(m: Mats): Assembly {
  const blades = [0, 1, 2, 3, 4, 5].map((n) => {
    const a = (n / 6) * Math.PI * 2;
    return box(0.15, 0.05, 0.006, m.dark, [Math.cos(a) * 0.1, Math.sin(a) * 0.1, 0.16], [0.35, 0, a]);
  });
  return {
    title: 'Sistem pendingin',
    subs: [
      {
        name: 'Radiator',
        info: 'Coolant panas mengalir di pipa tipis bersirip, panasnya dibuang ke udara yang lewat.',
        out: [0, 0, 0.3],
        make: () => [
          box(0.62, 0.4, 0.035, m.alu, [0, 0, 0.25]),
          ...Array.from({ length: 15 }, (_, i) => box(0.006, 0.4, 0.04, m.steel, [-0.28 + i * 0.04, 0, 0.25])),
          box(0.66, 0.05, 0.05, m.dark, [0, 0.225, 0.25]),
          box(0.66, 0.05, 0.05, m.dark, [0, -0.225, 0.25]),
        ],
      },
      {
        name: 'Tutup radiator',
        info: 'Menjaga tekanan sistem supaya titik didih coolant naik. Jangan dibuka saat mesin panas.',
        out: [0, 0.22, 0.3],
        make: () => [cyl(0.036, 0.03, m.red, [-0.2, 0.265, 0.25])],
      },
      {
        name: 'Shroud kipas',
        info: 'Mengarahkan isapan kipas supaya udara benar-benar melewati kisi radiator.',
        out: [0, 0, 0.12],
        make: () => [ring(0.19, 0.012, m.dark, [0, 0, 0.2])],
      },
      {
        name: 'Kipas pendingin',
        info: 'Menarik udara lewat radiator saat mobil berjalan pelan atau berhenti.',
        out: [0, 0, -0.03],
        make: () => [grp([cyl(0.045, 0.04, m.steel, [0, 0, 0.16], 'z'), ...blades])],
        run: ([fan], t) => { fan.rotation.z = t * 9; },
      },
      {
        name: 'Water pump',
        info: 'Mendorong coolant berkeliling antara mesin dan radiator. Diputar oleh belt mesin.',
        out: [0, 0, -0.22],
        make: () => [cyl(0.07, 0.06, m.alu, [0, -0.02, -0.28], 'z'), cyl(0.09, 0.02, m.dark, [0, -0.02, -0.23], 'z')],
      },
      {
        name: 'Thermostat',
        info: 'Katup yang membuka sendiri saat coolant panas. Selama mesin dingin ia menutup jalur ke radiator.',
        out: [0.2, 0.22, -0.2],
        make: () => [ball(0.045, m.brass, [0.18, 0.2, -0.28]), cyl(0.03, 0.06, m.alu, [0.18, 0.2, -0.24], 'z')],
      },
      {
        name: 'Selang atas (panas)',
        info: 'Membawa coolant panas dari mesin ke bagian atas radiator.',
        out: [0.1, 0.32, 0],
        make: () => [tube([[0.18, 0.2, -0.22], [0.22, 0.3, -0.05], [0.2, 0.27, 0.15], [0.2, 0.225, 0.24]], 0.022, m.red)],
      },
      {
        name: 'Selang bawah (dingin)',
        info: 'Mengembalikan coolant yang sudah didinginkan dari bawah radiator ke water pump.',
        out: [-0.1, -0.32, 0],
        make: () => [tube([[-0.2, -0.225, 0.24], [-0.24, -0.3, 0.05], [-0.15, -0.2, -0.2], [-0.05, -0.06, -0.28]], 0.022, m.rubber)],
      },
      {
        name: 'Tangki cadangan',
        info: 'Menampung coolant yang memuai saat panas dan mengembalikannya saat dingin. Tinggi coolant diperiksa di sini.',
        out: [0.26, 0, 0.1],
        make: () => [box(0.1, 0.18, 0.08, m.glass, [0.48, 0.05, 0.2]), cyl(0.02, 0.02, m.dark, [0.48, 0.15, 0.2])],
      },
    ],
  };
}

/** Wheel angle over a four-second loop: spin, brake to a stop, hold, release. */
const brakeAngle = (t: number) => {
  const p = t % 4;
  const turn = p < 2 ? p : p < 2.6 ? 2 + (p - 2) - (p - 2) ** 2 / 1.2 : p < 3.4 ? 2.3 : 2.3 + (p - 3.4) ** 2 / 1.2;
  return (Math.floor(t / 4) * 2.6 + turn) * 5;
};
const squeeze = (t: number) => {
  const p = t % 4;
  return Math.min(1, Math.max(0, (p - 2) / 0.25)) - Math.min(1, Math.max(0, (p - 3.3) / 0.15));
};

function brake(m: Mats): Assembly {
  return {
    title: 'Rem cakram',
    subs: [
      {
        name: 'Cakram',
        info: 'Piringan baja yang ikut berputar dengan roda. Permukaannya dijepit kampas untuk memperlambat mobil.',
        out: [0, 0, 0],
        make: () => [grp([
          cyl(0.22, 0.024, m.steel, [0, 0, 0], 'x', 0.22, 56),
          cyl(0.1, 0.05, m.steel, [0.035, 0, 0], 'x'),
          ...Array.from({ length: 12 }, (_, n) => cyl(0.008, 0.026, m.dark, [0, Math.cos(n * 0.5236) * (n % 2 ? 0.15 : 0.18), Math.sin(n * 0.5236) * (n % 2 ? 0.15 : 0.18)], 'x')),
        ])],
        run: ([disc], t) => { disc.rotation.x = brakeAngle(t); },
      },
      {
        name: 'Hub & baut roda',
        info: 'Dudukan cakram dan roda. Di dalamnya ada bearing yang membuat roda berputar bebas.',
        out: [-0.32, 0, 0],
        make: () => [grp([
          cyl(0.07, 0.08, m.dark, [-0.06, 0, 0], 'x'),
          ...[0, 1, 2, 3, 4].map((n) => {
            const a = (n / 5) * Math.PI * 2;
            return cyl(0.008, 0.1, m.alu, [0.05, Math.cos(a) * 0.06, Math.sin(a) * 0.06], 'x');
          }),
        ])],
        run: ([hub], t) => { hub.rotation.x = brakeAngle(t); },
      },
      {
        name: 'Kampas luar',
        info: 'Bahan gesek yang menekan cakram dari sisi luar. Menipis seiring pemakaian dan harus diganti sebelum habis.',
        out: [0.18, 0.12, 0],
        make: () => [grp([box(0.012, 0.07, 0.13, m.brass, [0.02, 0.16, 0]), box(0.006, 0.08, 0.14, m.dark, [0.03, 0.16, 0])])],
        run: ([pad], t) => nudge(pad, 'x', -0.002 * squeeze(t)),
      },
      {
        name: 'Kampas dalam',
        info: 'Pasangan kampas di sisi dalam. Didorong langsung oleh piston kaliper.',
        out: [-0.18, 0.12, 0],
        make: () => [grp([box(0.012, 0.07, 0.13, m.brass, [-0.02, 0.16, 0]), box(0.006, 0.08, 0.14, m.dark, [-0.03, 0.16, 0])])],
        run: ([pad], t) => nudge(pad, 'x', 0.002 * squeeze(t)),
      },
      {
        name: 'Kaliper',
        info: 'Rumah penjepit yang memegang kampas di kedua sisi cakram.',
        out: [0, 0.38, 0],
        make: () => [
          box(0.14, 0.04, 0.17, m.red, [0, 0.225, 0]),
          box(0.03, 0.12, 0.17, m.red, [0.055, 0.165, 0]),
          box(0.03, 0.12, 0.17, m.red, [-0.055, 0.165, 0]),
        ],
      },
      {
        name: 'Piston kaliper',
        info: 'Terdorong oleh tekanan minyak rem, lalu menekan kampas ke cakram.',
        out: [-0.36, 0.38, 0],
        make: () => [cyl(0.03, 0.04, m.alu, [-0.085, 0.16, 0], 'x')],
        run: ([piston], t) => nudge(piston, 'x', 0.008 * squeeze(t)),
      },
      {
        name: 'Selang rem',
        info: 'Menyalurkan tekanan minyak rem dari pipa di bodi ke kaliper yang ikut bergerak bersama roda.',
        out: [-0.18, 0.5, -0.14],
        make: () => [tube([[-0.07, 0.2, 0], [-0.2, 0.3, -0.1], [-0.3, 0.25, -0.2]], 0.009, m.rubber), ball(0.014, m.brass, [-0.07, 0.2, 0])],
      },
    ],
  };
}

function efi(m: Mats): Assembly {
  const screen = new THREE.MeshBasicMaterial({ color: 0xcc0000 });
  return {
    title: 'ECU, sensor, dan scanner',
    subs: [
      {
        name: 'ECU',
        info: 'Komputer mesin. Membaca semua sensor lalu menghitung lama semprotan injektor dan waktu pengapian.',
        out: [0, 0, 0],
        make: () => [
          box(0.3, 0.05, 0.22, m.alu, [0, 0, 0]),
          box(0.2, 0.03, 0.03, m.dark, [0, 0, 0.125]),
          ...[-0.1, -0.05, 0, 0.05, 0.1].map((x) => box(0.012, 0.012, 0.2, m.steel, [x, 0.03, 0])),
        ],
      },
      {
        name: 'Sensor aliran udara',
        info: 'Mengukur jumlah udara yang masuk ke mesin. Dari angka ini ECU menentukan banyaknya bensin.',
        out: [-0.25, 0.12, -0.1],
        make: () => [cyl(0.05, 0.14, m.dark, [-0.45, 0.05, -0.14], 'z'), box(0.04, 0.04, 0.05, m.red, [-0.45, 0.11, -0.14])],
      },
      {
        name: 'Sensor suhu mesin',
        info: 'Memberi tahu ECU apakah mesin masih dingin. Saat dingin, campuran dibuat lebih kaya.',
        out: [-0.3, 0, 0],
        make: () => [cyl(0.014, 0.07, m.brass, [-0.45, 0, 0.03], 'x'), cyl(0.022, 0.02, m.dark, [-0.48, 0, 0.03], 'x', 0.022, 6)],
      },
      {
        name: 'Sensor oksigen',
        info: 'Mengukur sisa oksigen di gas buang, sehingga ECU tahu campuran terlalu kaya atau terlalu kurus.',
        out: [-0.25, -0.12, 0.15],
        make: () => [cyl(0.016, 0.09, m.steel, [-0.45, 0, 0.17], 'x'), tube([[-0.5, 0, 0.17], [-0.56, 0.03, 0.2], [-0.6, 0, 0.26]], 0.006, m.dark)],
      },
      {
        name: 'Sensor putaran mesin',
        info: 'Membaca posisi dan kecepatan poros engkol. Tanpa sinyal ini mesin tidak bisa hidup.',
        out: [-0.12, -0.16, 0.26],
        make: () => [cyl(0.018, 0.06, m.dark, [-0.28, 0, 0.3]), box(0.06, 0.008, 0.03, m.steel, [-0.26, 0.02, 0.3])],
      },
      {
        name: 'Injektor',
        info: 'Katup listrik yang menyemprotkan bensin. ECU mengatur berapa milidetik ia terbuka.',
        out: [0.26, 0.12, -0.05],
        make: () => [
          cyl(0.015, 0.34, m.steel, [0.45, 0.06, -0.05], 'z'),
          ...[-0.17, -0.09, -0.01, 0.07].flatMap((z) => [cyl(0.014, 0.07, m.dark, [0.45, 0.02, z]), cyl(0.008, 0.02, m.red, [0.45, -0.02, z])]),
        ],
        // Firing order 1-3-4-2: one injector opens at a time.
        run: (objs, t) => [0, 2, 3, 1].forEach((c, n) => pulse(objs[2 + c * 2], Math.floor(t * 4) % 4 === n)),
      },
      {
        name: 'Koil pengapian',
        info: 'Menerima perintah ECU lalu membangkitkan tegangan tinggi untuk busi.',
        out: [0.22, -0.06, 0.28],
        make: () => [0.25, 0.33, 0.41, 0.49].flatMap((x) => [cyl(0.02, 0.1, m.dark, [x, 0, 0.3]), cyl(0.026, 0.02, m.red, [x, 0.06, 0.3])]),
        run: (objs, t) => [0, 2, 3, 1].forEach((c, n) => pulse(objs[1 + c * 2], Math.floor(t * 4 + 0.5) % 4 === n)),
      },
      {
        name: 'Scanner OBD-II',
        info: 'Alat diagnosa yang dicolok ke port OBD-II. Membaca kode kerusakan dan data sensor langsung dari ECU.',
        out: [0, 0.1, 0.34],
        make: () => [
          box(0.12, 0.022, 0.2, m.dark, [0, 0, 0.42]),
          box(0.09, 0.004, 0.09, screen, [0, 0.013, 0.39]),
          tube([[0, 0, 0.32], [0.04, -0.02, 0.24], [0, 0, 0.14]], 0.007, m.rubber),
        ],
      },
    ],
  };
}

function carburettor(m: Mats): Assembly {
  return {
    title: 'Karburator',
    subs: [
      {
        name: 'Saringan udara',
        info: 'Menyaring debu sebelum udara masuk karburator. Saringan kotor membuat campuran terlalu kaya dan boros.',
        out: [0, 0.44, 0],
        make: () => [cyl(0.2, 0.07, m.dark, [0, 0.37, 0], 'y', 0.2, 48), cyl(0.205, 0.012, m.red, [0, 0.41, 0], 'y', 0.205, 48)],
      },
      {
        name: 'Katup choke',
        info: 'Menutup sebagian saluran udara saat mesin dingin supaya campuran lebih kaya dan mesin mudah hidup.',
        out: [0, 0.24, 0],
        make: () => [grp([cyl(0.062, 0.006, m.brass), cyl(0.006, 0.17, m.steel, [0, 0, 0], 'z')], [0, 0.27, 0], [0, 0, 0.5])],
      },
      {
        name: 'Badan karburator & venturi',
        info: 'Saluran yang menyempit di tengah. Di penyempitan itu udara mengalir lebih cepat dan tekanannya turun.',
        out: [0, 0, 0],
        make: () => [lathe([[0.07, 0.31], [0.082, 0.31], [0.082, 0.22], [0.06, 0.14], [0.082, 0.05], [0.082, -0.06], [0.07, -0.06], [0.07, 0.05], [0.046, 0.14], [0.07, 0.22], [0.07, 0.31]], m.alu)],
      },
      {
        name: 'Nosel utama',
        info: 'Ujung saluran bensin di venturi. Tekanan rendah di sini mengisap bensin keluar dan mengabutkannya.',
        out: [0, 0.04, 0.32],
        make: () => [cyl(0.007, 0.09, m.brass, [0.05, 0.14, 0], 'x')],
      },
      {
        name: 'Katup gas',
        info: 'Terhubung ke pedal gas. Makin terbuka, makin banyak campuran masuk ke mesin.',
        out: [0, -0.2, 0],
        make: () => [grp([cyl(0.066, 0.006, m.steel), cyl(0.006, 0.2, m.dark, [0, 0, 0], 'z')], [0, -0.02, 0], [0, 0, 0.25])],
        // Pedal down, pedal up.
        run: ([valve], t) => { valve.rotation.z = 0.25 + 0.6 * (1 - Math.cos(t * 1.4)); },
      },
      {
        name: 'Ruang pelampung',
        info: 'Bak kecil penampung bensin di samping karburator. Tinggi bensin di sini menentukan kaya atau kurusnya campuran.',
        out: [0.26, 0, 0],
        make: () => [box(0.12, 0.11, 0.12, m.glass, [0.15, 0.1, 0])],
      },
      {
        name: 'Pelampung & jarum',
        info: 'Pelampung naik mengikuti bensin lalu jarumnya menutup saluran masuk, mirip pelampung tandon air.',
        out: [0.26, 0.22, 0],
        make: () => [grp([cyl(0.036, 0.04, m.brass, [0.15, 0.1, 0]), cyl(0.005, 0.05, m.steel, [0.15, 0.145, 0])])],
        run: ([float], t) => nudge(float, 'y', 0.008 * Math.sin(t * 1.4)),
      },
      {
        name: 'Main jet',
        info: 'Lubang terukur yang membatasi aliran bensin ke nosel. Jet tersumbat membuat mesin brebet.',
        out: [0.2, -0.2, 0],
        make: () => [cyl(0.01, 0.024, m.brass, [0.1, 0.05, 0], 'y', 0.01, 6)],
      },
      {
        name: 'Sekrup setelan idle',
        info: 'Mengatur campuran dan putaran saat pedal gas dilepas. Disetel saat tune up.',
        out: [0, -0.1, 0.28],
        make: () => [cyl(0.008, 0.07, m.brass, [0, -0.03, 0.1], 'z'), cyl(0.014, 0.012, m.dark, [0, -0.03, 0.14], 'z')],
      },
      {
        name: 'Dudukan ke intake manifold',
        info: 'Sambungan karburator ke mesin. Paking yang bocor di sini membuat udara palsu masuk dan idle tidak stabil.',
        out: [0, -0.36, 0],
        make: () => [box(0.22, 0.016, 0.17, m.dark, [0, -0.072, 0])],
      },
    ],
  };
}

function commonRail(m: Mats): Assembly {
  const xs = [0, 0.11, 0.22, 0.33];
  return {
    title: 'Sistem common rail',
    subs: [
      {
        name: 'Filter solar',
        info: 'Menyaring kotoran dan memisahkan air dari solar. Air yang lolos merusak pompa dan injektor.',
        out: [-0.26, 0, 0],
        make: () => [cyl(0.05, 0.13, m.dark, [-0.5, 0, 0]), cyl(0.056, 0.03, m.alu, [-0.5, 0.08, 0])],
      },
      {
        name: 'Pompa tekanan tinggi',
        info: 'Diputar mesin, menaikkan tekanan solar hingga lebih dari seribu bar sebelum dikirim ke rail.',
        out: [-0.12, -0.22, 0],
        make: () => [cyl(0.07, 0.16, m.alu, [-0.25, -0.05, 0], 'x'), grp([gear(0.085, 0.02, m.red, [0, 0, 0], 'x', 22)], [-0.34, -0.05, 0])],
        run: ([, drive], t) => { drive.rotation.x = (t * W) / 2; },
      },
      {
        name: 'Common rail',
        info: 'Pipa penampung solar bertekanan tinggi. Semua injektor mengambil dari rail yang sama.',
        out: [0, 0.26, 0],
        make: () => [cyl(0.022, 0.5, m.steel, [0.16, 0.2, 0], 'x')],
      },
      {
        name: 'Sensor tekanan rail',
        info: 'Melaporkan tekanan di dalam rail ke ECU, yang lalu mengatur kerja pompa.',
        out: [0.22, 0.3, 0],
        make: () => [cyl(0.017, 0.05, m.red, [0.435, 0.2, 0], 'x')],
      },
      {
        name: 'Pipa tekanan tinggi',
        info: 'Pipa baja tebal dari pompa ke rail dan dari rail ke tiap injektor.',
        out: [0, 0.1, 0.2],
        make: () => [
          ...xs.map((x) => tube([[x, 0.2, 0.02], [x, 0.24, 0.1], [x, 0.1, 0.14]], 0.006, m.steel)),
          tube([[-0.2, 0.01, 0], [-0.14, 0.14, 0], [-0.08, 0.2, 0]], 0.007, m.steel),
        ],
      },
      {
        name: 'Injektor',
        info: 'Dibuka secara elektronik oleh ECU. Menyemprotkan solar sebagai kabut halus, bisa beberapa kali dalam satu langkah.',
        out: [0, -0.16, 0.36],
        make: () => xs.flatMap((x) => [cyl(0.016, 0.17, m.steel, [x, 0.01, 0.14]), cyl(0.024, 0.05, m.red, [x, 0.08, 0.14]), cyl(0.006, 0.03, m.brass, [x, -0.09, 0.14])]),
        run: (objs, t) => [0, 2, 3, 1].forEach((c, n) => pulse(objs[1 + c * 3], Math.floor(t * 4) % 4 === n)),
      },
      {
        name: 'ECU',
        info: 'Menentukan tekanan rail, kapan injektor membuka, dan berapa lama.',
        out: [0, -0.1, -0.3],
        make: () => [box(0.22, 0.04, 0.16, m.alu, [0.16, -0.1, -0.25]), box(0.14, 0.03, 0.03, m.dark, [0.16, -0.1, -0.16])],
      },
    ],
  };
}

function ac(m: Mats): Assembly {
  return {
    title: 'Sistem AC',
    subs: [
      {
        name: 'Kompresor',
        info: 'Memampatkan refrigeran menjadi gas panas bertekanan tinggi. Diputar mesin lewat belt.',
        out: [-0.2, -0.16, 0],
        make: () => [cyl(0.08, 0.18, m.alu, [-0.35, -0.15, 0], 'x'), box(0.06, 0.03, 0.06, m.dark, [-0.33, -0.06, 0])],
      },
      {
        name: 'Kopling magnet & puli',
        info: 'Menyambungkan puli ke kompresor saat AC dinyalakan. Bunyi klik saat AC hidup berasal dari sini.',
        out: [-0.44, -0.16, 0],
        make: () => [grp([
          cyl(0.088, 0.03, m.dark, [0, 0, 0], 'x', 0.088, 40),
          cyl(0.04, 0.034, m.red, [0, 0, 0], 'x'),
          ...[0, 1, 2].map((n) => cyl(0.012, 0.036, m.alu, [0, Math.cos(n * 2.094) * 0.062, Math.sin(n * 2.094) * 0.062], 'x')),
        ], [-0.47, -0.15, 0])],
        run: ([pulley], t) => { pulley.rotation.x = t * W; },
      },
      {
        name: 'Kondensor',
        info: 'Dipasang di depan radiator. Di sini refrigeran melepas panas ke udara luar dan berubah menjadi cair.',
        out: [0, 0.1, 0.3],
        make: () => [
          box(0.5, 0.3, 0.02, m.alu, [0, 0.1, 0.35]),
          ...[-0.1, -0.03, 0.04, 0.11, 0.18, 0.25].map((y) => box(0.5, 0.008, 0.026, m.steel, [0, y, 0.35])),
        ],
      },
      {
        name: 'Receiver drier',
        info: 'Menyaring kotoran dan menyerap uap air dari refrigeran cair.',
        out: [0.3, 0.1, 0.25],
        make: () => [cyl(0.035, 0.2, m.dark, [0.33, 0.1, 0.3]), cyl(0.02, 0.02, m.alu, [0.33, 0.21, 0.3])],
      },
      {
        name: 'Katup ekspansi',
        info: 'Menurunkan tekanan refrigeran secara tiba-tiba sehingga suhunya turun drastis.',
        out: [0.36, 0.16, -0.05],
        make: () => [box(0.05, 0.06, 0.04, m.brass, [0.35, 0.05, -0.12])],
      },
      {
        name: 'Evaporator',
        info: 'Berada di balik dasbor. Refrigeran dingin menyerap panas udara kabin yang ditiup melewatinya.',
        out: [0.05, 0.05, -0.3],
        make: () => [
          box(0.3, 0.2, 0.08, m.alu, [0.1, 0.05, -0.3]),
          ...[-0.1, -0.05, 0, 0.05, 0.1, 0.15, 0.2, 0.25].map((x) => box(0.006, 0.2, 0.086, m.steel, [x - 0.025, 0.05, -0.3])),
        ],
      },
      {
        name: 'Blower',
        info: 'Kipas kabin. Meniupkan udara melewati evaporator lalu ke kisi-kisi AC.',
        out: [-0.3, 0.1, -0.36],
        make: () => [grp([
          cyl(0.03, 0.14, m.steel, [0, 0, 0], 'x'),
          cyl(0.09, 0.008, m.dark, [-0.06, 0, 0], 'x', 0.09, 36),
          cyl(0.09, 0.008, m.dark, [0.06, 0, 0], 'x', 0.09, 36),
          ...Array.from({ length: 14 }, (_, n) => box(0.12, 0.006, 0.03, m.dark, [0, Math.cos(n * 0.4488) * 0.078, Math.sin(n * 0.4488) * 0.078], [n * 0.4488 + 0.5, 0, 0])),
        ], [-0.2, 0.05, -0.32])],
        run: ([cage], t) => { cage.rotation.x = t * 8; },
      },
      {
        name: 'Pipa tekanan tinggi',
        info: 'Jalur refrigeran panas dari kompresor ke kondensor, lalu ke katup ekspansi.',
        out: [0, 0.34, 0],
        make: () => [
          tube([[-0.33, -0.05, 0], [-0.3, 0.2, 0.2], [-0.24, 0.22, 0.35]], 0.009, m.red),
          tube([[0.25, 0.1, 0.35], [0.33, 0.0, 0.3], [0.36, 0.02, 0.05], [0.35, 0.05, -0.1]], 0.009, m.red),
        ],
      },
      {
        name: 'Pipa tekanan rendah',
        info: 'Jalur refrigeran dingin dari evaporator kembali ke kompresor.',
        out: [0, -0.3, 0],
        make: () => [tube([[-0.05, 0, -0.3], [-0.2, -0.12, -0.2], [-0.3, -0.16, -0.08]], 0.012, m.steel)],
      },
    ],
  };
}

function gearbox(m: Mats): Assembly {
  const gx = [0.1, 0, -0.1, -0.2];
  const main = [0.045, 0.055, 0.065, 0.075];
  return {
    title: 'Kopling & transmisi manual',
    subs: [
      {
        name: 'Plat kopling',
        info: 'Piringan berlapis bahan gesek di antara roda gila dan plat penekan. Saat menipis, kopling selip.',
        out: [0.62, 0, 0],
        make: () => [cyl(0.15, 0.012, m.dark, [0.52, 0.05, 0], 'x', 0.15, 48), ring(0.125, 0.014, m.red, [0.52, 0.05, 0], 'x'), cyl(0.04, 0.03, m.steel, [0.52, 0.05, 0], 'x')],
      },
      {
        name: 'Plat penekan (matahari)',
        info: 'Pegasnya menjepit plat kopling ke roda gila. Saat pedal diinjak, jepitan itu dilepas.',
        out: [0.42, 0, 0],
        make: () => [cyl(0.16, 0.03, m.steel, [0.49, 0.05, 0], 'x', 0.16, 48), cyl(0.06, 0.04, m.dark, [0.485, 0.05, 0], 'x')],
      },
      {
        name: 'Release bearing',
        info: 'Mendorong pegas matahari saat pedal kopling diinjak. Bunyi berdecit saat pedal diinjak sering berasal dari sini.',
        out: [0.28, 0, 0],
        make: () => [cyl(0.04, 0.03, m.alu, [0.44, 0.05, 0], 'x')],
      },
      {
        name: 'Rumah kopling',
        info: 'Menghubungkan transmisi ke blok mesin dan menutup mekanisme kopling.',
        out: [0.13, 0, 0],
        make: () => [cyl(0.13, 0.16, m.alu, [0.3, 0.02, 0], 'x', 0.2, 40)],
      },
      {
        name: 'Poros input',
        info: 'Menerima putaran mesin lewat plat kopling dan memutar poros counter.',
        out: [0.14, 0.22, 0],
        make: () => [grp([cyl(0.018, 0.26, m.steel, [0.02, 0, 0], 'x'), gear(0.04, 0.03, m.steel, [-0.1, 0, 0])], [0.3, 0.05, 0])],
        run: ([shaft], t) => { shaft.rotation.x = t * W; },
      },
      {
        name: 'Poros utama & roda gigi',
        info: 'Tiap pasang roda gigi punya perbandingan sendiri. Sinkromes menyamakan putaran sebelum gigi tersambung.',
        out: [0, 0.22, 0],
        make: () => [grp([
          cyl(0.018, 0.46, m.steel, [-0.08, 0, 0], 'x'),
          ...gx.map((x, i) => gear(main[i], 0.035, m.steel, [x, 0, 0])),
          ...[0.05, -0.15].map((x) => cyl(0.04, 0.025, m.red, [x, 0, 0], 'x')),
        ], [0, 0.05, 0])],
        // Shown in first gear: the smallest counter gear drives the largest main gear.
        run: ([shaft], t) => { shaft.rotation.x = t * W * (0.04 / 0.07) * (0.035 / 0.075); },
      },
      {
        name: 'Poros counter',
        info: 'Deretan roda gigi yang selalu berputar bersama poros input dan menjadi pasangan roda gigi poros utama.',
        out: [0, -0.2, 0],
        make: () => [grp([
          cyl(0.016, 0.5, m.dark, [-0.03, 0, 0], 'x'),
          gear(0.07, 0.03, m.dark, [0.2, 0, 0]),
          ...gx.map((x, i) => gear(0.11 - main[i], 0.035, m.dark, [x, 0, 0])),
        ], [0, -0.06, 0])],
        run: ([shaft], t) => { shaft.rotation.x = -t * W * (0.04 / 0.07); },
      },
      {
        name: 'Garpu pemindah',
        info: 'Menggeser sinkromes ke roda gigi yang dipilih saat tuas persneling digerakkan.',
        out: [0, 0.44, 0],
        make: () => [cyl(0.008, 0.36, m.alu, [-0.05, 0.16, 0], 'x'), box(0.02, 0.07, 0.07, m.alu, [0.05, 0.125, 0]), box(0.02, 0.07, 0.07, m.alu, [-0.15, 0.125, 0])],
      },
      {
        name: 'Tuas persneling',
        info: 'Meneruskan gerakan tangan pengemudi ke garpu pemindah.',
        out: [0, 0.64, 0],
        make: () => [cyl(0.008, 0.24, m.dark, [-0.2, 0.3, 0]), ball(0.028, m.red, [-0.2, 0.43, 0])],
      },
      {
        name: 'Rumah transmisi',
        info: 'Menopang kedua poros dan menampung oli transmisi yang melumasi roda gigi.',
        out: [0, 0, -0.42],
        make: () => [box(0.48, 0.27, 0.22, m.alu, [-0.05, 0, 0])],
      },
      {
        name: 'Poros output',
        info: 'Mengirim putaran yang sudah disesuaikan ke poros propeler, lalu ke gardan.',
        out: [-0.3, 0, 0],
        make: () => [cyl(0.03, 0.12, m.steel, [-0.37, 0.05, 0], 'x'), cyl(0.045, 0.03, m.dark, [-0.44, 0.05, 0], 'x')],
      },
    ],
  };
}

/** Wheel travel over a bump, in metres. */
const travel = (t: number) => 0.028 * Math.sin(t * 4);
const ride = (objs: THREE.Object3D[], t: number) => objs.forEach((o) => nudge(o, 'y', travel(t)));

function suspension(m: Mats): Assembly {
  return {
    title: 'Suspensi depan MacPherson',
    subs: [
      {
        name: 'Dudukan atas',
        info: 'Mengikat suspensi ke bodi. Karetnya meredam getaran, bearingnya membuat roda bisa dibelokkan.',
        out: [0, 0.3, 0],
        make: () => [cyl(0.07, 0.03, m.dark, [0, 0.46, 0]), ...[0, 1, 2].map((n) => cyl(0.006, 0.05, m.alu, [Math.cos(n * 2.09) * 0.05, 0.49, Math.sin(n * 2.09) * 0.05]))],
      },
      {
        name: 'Pegas koil',
        info: 'Menopang berat mobil dan menyerap guncangan dengan cara memendek.',
        out: [-0.32, 0.04, 0],
        make: () => [helix(0.062, 0.13, 0.43, 6, 0.011, m.red)],
        // The top stays on the body; the bottom follows the wheel.
        run: ([spring], t) => {
          const k = 1 - travel(t) / 0.3;
          spring.scale.y = k;
          spring.position.y = 0.43 * (1 - k);
        },
      },
      {
        name: 'Shock absorber',
        info: 'Menahan pegas agar tidak memantul berulang. Oli di dalamnya dipaksa lewat lubang kecil sehingga gerakan teredam.',
        out: [0, 0, 0],
        make: () => [cyl(0.03, 0.3, m.dark, [0, -0.02, 0]), cyl(0.075, 0.012, m.steel, [0, 0.125, 0]), cyl(0.011, 0.34, m.alu, [0, 0.28, 0])],
        run: ([body, seat], t) => ride([body, seat], t),
      },
      {
        name: 'Knuckle',
        info: 'Penghubung antara strut, lower arm, dan hub roda. Kemudi membelokkan roda dengan memutar knuckle.',
        out: [0.06, -0.14, 0],
        make: () => [box(0.06, 0.2, 0.08, m.steel, [0.03, -0.24, 0]), box(0.04, 0.03, 0.14, m.steel, [0.03, -0.24, -0.08])],
        run: ride,
      },
      {
        name: 'Hub & cakram',
        info: 'Tempat roda dipasang. Bearing di dalam hub membuat roda berputar halus.',
        out: [0.34, -0.14, 0],
        make: () => [cyl(0.16, 0.02, m.steel, [0.12, -0.24, 0], 'x', 0.16, 48), cyl(0.06, 0.07, m.dark, [0.1, -0.24, 0], 'x')],
        run: ride,
      },
      {
        name: 'Lower arm',
        info: 'Lengan ayun yang menahan posisi roda terhadap rangka. Bushing karetnya getas seiring usia.',
        out: [-0.14, -0.36, 0],
        make: () => [
          rod([-0.36, -0.36, 0.13], [0.02, -0.36, 0], 0.016, m.dark),
          rod([-0.36, -0.36, -0.13], [0.02, -0.36, 0], 0.016, m.dark),
          rod([-0.36, -0.36, 0.13], [-0.36, -0.36, -0.13], 0.014, m.dark),
          cyl(0.026, 0.05, m.rubber, [-0.36, -0.36, 0.13], 'x'),
          cyl(0.026, 0.05, m.rubber, [-0.36, -0.36, -0.13], 'x'),
        ],
        run: ride,
      },
      {
        name: 'Ball joint',
        info: 'Sendi peluru antara lower arm dan knuckle. Bila oblak, terdengar bunyi gluduk di jalan rusak.',
        out: [0.12, -0.54, 0],
        make: () => [ball(0.026, m.red, [0.03, -0.35, 0]), cyl(0.008, 0.05, m.steel, [0.03, -0.32, 0])],
        run: ride,
      },
      {
        name: 'Link stabilizer',
        info: 'Menghubungkan suspensi ke batang stabilizer yang mengurangi limbung saat menikung.',
        out: [0, -0.1, 0.32],
        make: () => [rod([-0.05, -0.3, 0.1], [-0.05, -0.05, 0.1], 0.008, m.steel), ball(0.014, m.dark, [-0.05, -0.3, 0.1]), ball(0.014, m.dark, [-0.05, -0.05, 0.1])],
        run: ride,
      },
    ],
  };
}

function injectionPump(m: Mats): Assembly {
  const xs = [-0.15, -0.05, 0.05, 0.15];
  return {
    title: 'Pompa injeksi in-line',
    subs: [
      {
        name: 'Filter solar',
        info: 'Menyaring kotoran dan memisahkan air dari solar sebelum masuk ke pompa yang presisinya sangat tinggi.',
        out: [-0.28, 0.05, 0],
        make: () => [cyl(0.05, 0.13, m.dark, [-0.5, 0, 0]), cyl(0.056, 0.03, m.alu, [-0.5, 0.08, 0])],
      },
      {
        name: 'Pompa priming',
        info: 'Pompa tangan untuk mengisi jalur dan membuang udara setelah filter diganti atau solar habis.',
        out: [-0.1, -0.1, 0.3],
        make: () => [cyl(0.03, 0.08, m.steel, [-0.2, -0.02, 0.09]), cyl(0.036, 0.03, m.red, [-0.2, 0.035, 0.09])],
        run: ([, knob], t) => nudge(knob, 'y', 0.012 * Math.max(0, Math.sin(t * 3))),
      },
      {
        name: 'Rumah pompa',
        info: 'Menampung poros nok, plunger, dan oli pelumas pompa. Dibaut ke samping mesin dan diputar oleh roda gigi timing.',
        out: [0, 0, -0.3],
        make: () => [box(0.44, 0.2, 0.12, m.alu, [0, 0, 0])],
      },
      {
        name: 'Poros nok pompa',
        info: 'Diputar mesin dengan setengah putaran poros engkol. Tiap noknya mengangkat satu plunger.',
        out: [0, -0.28, 0],
        make: () => [grp([
          cyl(0.016, 0.52, m.steel, [0, 0, 0], 'x'),
          ...xs.map((x, i) => cyl(0.03, 0.03, m.steel, [x, Math.cos(i * 1.571) * 0.012, Math.sin(i * 1.571) * 0.012], 'x')),
        ], [0, -0.06, 0])],
        run: ([shaft], t) => { shaft.rotation.x = (t * W) / 2; },
      },
      {
        name: 'Plunger & barrel',
        info: 'Plunger naik dan menekan solar hingga ratusan bar. Memutar plunger mengubah banyaknya solar yang dikirim.',
        out: [0, 0.2, 0],
        make: () => xs.map((x) => grp([cyl(0.014, 0.1, m.steel, [x, 0.06, 0]), cyl(0.02, 0.03, m.brass, [x, 0.02, 0])])),
        run: (plungers, t) => plungers.forEach((p, i) => nudge(p, 'y', 0.012 * Math.max(0, Math.cos((t * W) / 2 - i * 1.571)))),
      },
      {
        name: 'Katup penyalur',
        info: 'Katup satu arah di atas tiap plunger. Menjaga tekanan sisa di pipa supaya nozzle menutup dengan tegas.',
        out: [0, 0.4, 0],
        make: () => xs.map((x) => cyl(0.02, 0.05, m.dark, [x, 0.13, 0], 'y', 0.014, 6)),
      },
      {
        name: 'Governor',
        info: 'Pengatur putaran mekanis. Bandulnya menggeser batang pengatur supaya mesin tidak mati di idle dan tidak melampaui putaran maksimum.',
        out: [-0.3, 0, 0],
        make: () => [cyl(0.085, 0.1, m.alu, [-0.28, 0, 0], 'x', 0.085, 36), cyl(0.03, 0.02, m.dark, [-0.34, 0, 0], 'x')],
      },
      {
        name: 'Tuas gas',
        info: 'Terhubung ke pedal gas. Lewat governor ia memutar plunger, bukan membuka katup udara seperti pada mesin bensin.',
        out: [-0.36, 0.26, 0],
        make: () => [grp([box(0.016, 0.14, 0.016, m.dark, [0, 0.06, 0]), ball(0.016, m.red, [0, 0.13, 0])], [-0.28, 0.09, 0.02])],
        run: ([lever], t) => { lever.rotation.z = 0.5 * Math.sin(t * 1.4); },
      },
      {
        name: 'Timer & roda gigi penggerak',
        info: 'Memajukan saat penyemprotan ketika putaran mesin naik, supaya solar sempat terbakar.',
        out: [0.3, 0, 0],
        make: () => [grp([gear(0.08, 0.03, m.dark, [0, 0, 0], 'x', 22), cyl(0.05, 0.05, m.steel, [-0.03, 0, 0], 'x')], [0.27, -0.06, 0])],
        run: ([drive], t) => { drive.rotation.x = (t * W) / 2; },
      },
      {
        name: 'Pipa tekanan tinggi',
        info: 'Pipa baja tebal dengan panjang yang dibuat sama, supaya tiap silinder disemprot pada saat yang sama tepatnya.',
        out: [0, 0.52, 0.12],
        make: () => xs.map((x, i) => tube([[x, 0.16, 0], [x, 0.26, 0.04], [0.02 + i * 0.1, 0.3, 0.18], [0.02 + i * 0.1, 0.22, 0.26]], 0.006, m.steel)),
      },
      {
        name: 'Nozzle injektor',
        info: 'Pegas di dalamnya menahan jarum tertutup. Jarum terangkat saat tekanan solar melewati tekanan bukanya.',
        out: [0.08, 0.3, 0.44],
        make: () => xs.flatMap((_, i) => [cyl(0.018, 0.14, m.steel, [0.02 + i * 0.1, 0.15, 0.26]), cyl(0.006, 0.03, m.red, [0.02 + i * 0.1, 0.065, 0.26])]),
        run: (objs, t) => [0, 2, 3, 1].forEach((c, n) => pulse(objs[1 + c * 2], Math.floor((t * W) / Math.PI) % 4 === n)),
      },
    ],
  };
}

function automatic(m: Mats): Assembly {
  const planets = [0, 1, 2].map((n) => n * 2.094);
  return {
    title: 'Transmisi otomatis',
    subs: [
      {
        name: 'Flex plate',
        info: 'Pelat tipis pengganti roda gila. Menghubungkan poros engkol ke torque converter dan membawa gigi starter.',
        out: [0.66, 0, 0],
        make: () => [grp([gear(0.17, 0.012, m.dark, [0, 0, 0], 'x', 56), ...[0, 1, 2, 3].map((n) => cyl(0.014, 0.016, m.alu, [0, Math.cos(n * 1.571) * 0.1, Math.sin(n * 1.571) * 0.1], 'x'))], [0.5, 0, 0])],
        run: ([plate], t) => { plate.rotation.x = t * W; },
      },
      {
        name: 'Torque converter',
        info: 'Kopling fluida. Pompa di dalamnya melempar oli ke turbin, sehingga mesin tetap hidup saat mobil berhenti dengan gigi masuk.',
        out: [0.44, 0, 0],
        make: () => {
          const shell = lathe([[0.03, -0.05], [0.1, -0.06], [0.15, -0.035], [0.16, 0], [0.15, 0.035], [0.1, 0.06], [0.03, 0.05]], m.steel);
          shell.rotation.z = -Math.PI / 2;
          return [grp([shell, ...Array.from({ length: 8 }, (_, n) => box(0.02, 0.012, 0.03, m.dark, [0.045, Math.cos(n * 0.785) * 0.13, Math.sin(n * 0.785) * 0.13], [n * 0.785, 0, 0]))], [0.42, 0, 0])];
        },
        run: ([converter], t) => { converter.rotation.x = t * W; },
      },
      {
        name: 'Rumah converter',
        info: 'Menghubungkan transmisi ke blok mesin dan menutup torque converter.',
        out: [0.18, 0, 0],
        make: () => [cyl(0.13, 0.2, m.alu, [0.32, 0, 0], 'x', 0.21, 40)],
      },
      {
        name: 'Pompa oli',
        info: 'Diputar langsung oleh torque converter. Tekanan olinya yang menggerakkan semua kopling di dalam transmisi.',
        out: [0.02, 0.3, 0],
        make: () => [cyl(0.1, 0.03, m.alu, [0.2, 0, 0], 'x', 0.1, 36), cyl(0.045, 0.036, m.brass, [0.2, 0, 0], 'x')],
      },
      {
        name: 'Paket kopling',
        info: 'Tumpukan plat yang dijepit tekanan oli. Kopling mana yang dijepit menentukan gigi yang aktif.',
        out: [-0.02, 0.32, 0],
        make: () => Array.from({ length: 7 }, (_, n) => cyl(0.095, 0.008, n % 2 ? m.steel : m.brass, [0.13 - n * 0.012, 0, 0], 'x', 0.095, 36)),
      },
      {
        name: 'Roda gigi matahari',
        info: 'Roda gigi di pusat susunan planetary. Bisa menjadi masukan, keluaran, atau ditahan diam.',
        out: [-0.06, 0, 0.42],
        make: () => [grp([gear(0.034, 0.05, m.red, [0, 0, 0], 'x', 12)], [-0.06, 0, 0])],
        run: ([sun], t) => { sun.rotation.x = t * W; },
      },
      {
        name: 'Roda gigi planet & carrier',
        info: 'Tiga roda gigi kecil yang mengelilingi roda gigi matahari. Carrier memegang porosnya dan ikut berputar.',
        out: [-0.06, -0.3, 0.22],
        make: () => [grp([
          cyl(0.09, 0.008, m.dark, [-0.03, 0, 0], 'x', 0.09, 36),
          ...planets.map((a) => grp([gear(0.028, 0.04, m.steel, [0, 0, 0], 'x', 10)], [0, Math.cos(a) * 0.062, Math.sin(a) * 0.062])),
        ], [-0.06, 0, 0])],
        // Ring held still: the carrier turns at sun speed x Zs / (Zs + Zr).
        run: ([carrier], t) => {
          carrier.rotation.x = t * W * (12 / 44);
          carrier.children.slice(1).forEach((p) => { p.rotation.x = -t * W * (12 / 44) * (32 / 10); });
        },
      },
      {
        name: 'Roda gigi cincin',
        info: 'Roda gigi bergigi dalam yang melingkupi planet. Menahan atau melepasnya mengubah perbandingan gigi.',
        out: [-0.06, 0.3, -0.1],
        make: () => [ring(0.1, 0.012, m.alu, [-0.06, 0, 0], 'x'), cyl(0.112, 0.05, m.glass, [-0.06, 0, 0], 'x', 0.112, 40)],
      },
      {
        name: 'Valve body',
        info: 'Labirin saluran oli dan katup solenoid. Otak hidrolik yang memutuskan kapan gigi berpindah.',
        out: [0, -0.34, 0],
        make: () => [
          box(0.34, 0.03, 0.18, m.alu, [-0.03, -0.14, 0]),
          ...[-0.06, -0.02, 0.02, 0.06].map((z) => box(0.3, 0.008, 0.012, m.dark, [-0.03, -0.122, z])),
          ...[-0.12, -0.04, 0.04].map((x) => cyl(0.014, 0.05, m.red, [x, -0.14, 0.11], 'z')),
        ],
      },
      {
        name: 'Karter & filter ATF',
        info: 'Menampung oli transmisi (ATF). ATF yang gelap atau berbau gosong tanda kopling di dalam mulai aus.',
        out: [0, -0.56, 0],
        make: () => [box(0.38, 0.05, 0.2, m.dark, [-0.03, -0.19, 0]), box(0.2, 0.012, 0.12, m.brass, [-0.03, -0.16, 0])],
      },
      {
        name: 'Rumah transmisi',
        info: 'Menopang susunan roda gigi dan menjadi saluran oli bertekanan.',
        out: [0, 0, -0.46],
        make: () => [box(0.46, 0.26, 0.24, m.alu, [-0.05, 0, 0])],
      },
      {
        name: 'Tuas selector',
        info: 'Memilih P, R, N, atau D. Di posisi P sebuah pengunci mekanis menahan poros output.',
        out: [0, 0.62, 0],
        make: () => [box(0.16, 0.02, 0.05, m.dark, [-0.15, 0.18, 0]), box(0.02, 0.1, 0.02, m.steel, [-0.15, 0.24, 0]), box(0.04, 0.05, 0.04, m.red, [-0.15, 0.3, 0])],
      },
      {
        name: 'Poros output',
        info: 'Mengirim putaran dari carrier planetary ke poros propeler.',
        out: [-0.3, 0, 0],
        make: () => [cyl(0.03, 0.14, m.steel, [-0.36, 0, 0], 'x'), cyl(0.045, 0.03, m.dark, [-0.44, 0, 0], 'x')],
      },
    ],
  };
}

export function buildAssembly(slot: Slot, fuel: Fuel, matic: boolean, m: Mats): Assembly {
  switch (slot) {
    case 'mesin': return engine(fuel, m);
    case 'pendingin': return cooling(m);
    case 'rem': return brake(m);
    case 'bahanbakar': return fuel === 'efi' ? efi(m) : fuel === 'karbu' ? carburettor(m) : fuel === 'commonrail' ? commonRail(m) : injectionPump(m);
    case 'ac': return ac(m);
    case 'transmisi': return matic ? automatic(m) : gearbox(m);
    case 'suspensi': return suspension(m);
  }
}
