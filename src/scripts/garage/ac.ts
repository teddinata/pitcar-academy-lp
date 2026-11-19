import * as THREE from 'three';
import type { Assembly } from './bench';
import { type Mats, type V3, box, cyl, ring, grp } from './kit';

/**
 * Sistem AC utuh untuk halaman kelas online AC: satu putaran refrigeran dari
 * kompresor kembali ke kompresor, dengan enam kondisi rusak.
 *
 * !! VERIFY dengan trainer sebelum publish: nama, fungsi, dan perilaku tiap
 * kondisi ditulis dari prinsip kerja umum AC mobil, belum ditinjau trainer
 * Pitcar. Bentuk dan tata letaknya disederhanakan.
 */

/** How each fault changes the machine. Keys match `acCases[].id`. */
const BEHAVIOUR: Record<string, { flow: number; clutch: boolean; fan: number; blower: number; sparse: boolean }> = {
  sehat: { flow: 1, clutch: true, fan: 1, blower: 1, sparse: false },
  freon: { flow: 0.55, clutch: true, fan: 1, blower: 1, sparse: true },
  kondensor: { flow: 0.8, clutch: true, fan: 0, blower: 1, sparse: false },
  kompresor: { flow: 0.3, clutch: true, fan: 1, blower: 1, sparse: false },
  ekspansi: { flow: 0.12, clutch: true, fan: 1, blower: 1, sparse: true },
  kopling: { flow: 0, clutch: false, fan: 1, blower: 1, sparse: false },
  kabin: { flow: 1, clutch: true, fan: 1, blower: 0.25, sparse: false },
};
const behaviour = (state: string) => BEHAVIOUR[state] ?? BEHAVIOUR.sehat;

const hot = new THREE.MeshBasicMaterial({ color: 0xff5a4a });
// Cold refrigerant is drawn in ink: near white at night, graphite on paper.
const cold = new THREE.MeshBasicMaterial({ color: 0xe9f1f6 });

/** A pipe and the beads of refrigerant that travel along it. */
function line(points: V3[], radius: number, pipeMat: THREE.Material, beadMat: THREE.Material) {
  const curve = new THREE.CatmullRomCurve3(points.map((p) => new THREE.Vector3(...p)));
  const pipe = new THREE.Mesh(new THREE.TubeGeometry(curve, 48, radius, 10), pipeMat);
  const count = Math.max(3, Math.round(curve.getLength() / 0.085));
  const beads = Array.from({ length: count }, () => new THREE.Mesh(new THREE.SphereGeometry(radius * 1.55, 12, 8), beadMat));
  const length = curve.getLength();
  let travelled = 0;
  let last = 0;
  const run = (t: number, state: string) => {
    const b = behaviour(state);
    // Integrate, so changing the fault changes speed without the beads jumping.
    travelled += ((t - last) * 0.22 * b.flow) / length;
    last = t;
    beads.forEach((bead, i) => {
      bead.position.copy(curve.getPointAt((travelled + i / count) % 1));
      // Nothing moves, and nothing is drawn moving, while the compressor is off.
      bead.visible = b.flow > 0 && !(b.sparse && i % 2 === 1);
    });
  };
  run(0, 'sehat');
  return { objs: [pipe, ...beads], run };
}

export function acSystem(m: Mats): Assembly {
  // Own instance, so the high side can go cold without recolouring every red part.
  const hotPipe = (m.red as THREE.MeshPhysicalMaterial).clone();
  const coldPipe = (m.alu as THREE.MeshStandardMaterial).clone();
  const discharge = line([[-0.2, -0.14, 0.26], [-0.22, 0.02, 0.27], [-0.4, 0.16, 0.25], [-0.585, 0.17, 0.24]], 0.011, hotPipe, hot);
  const liquidA = line([[-0.585, -0.16, -0.24], [-0.52, 0.02, -0.34], [-0.44, 0.115, -0.38]], 0.009, hotPipe, hot);
  const liquidB = line([[-0.4, 0.115, -0.38], [-0.1, 0.14, -0.38], [0.2, 0.12, -0.3], [0.31, 0.09, -0.06]], 0.009, hotPipe, hot);
  const suction = line([[0.31, 0.05, 0], [0.2, -0.05, 0.14], [0, -0.16, 0.26], [-0.1, -0.15, 0.26]], 0.015, coldPipe, cold);

  const spin = (n: number, r: number) => Array.from({ length: n }, (_, i) => {
    const a = (i / n) * Math.PI * 2;
    return [Math.cos(a) * r, Math.sin(a) * r, a] as const;
  });
  let fanAngle = 0;
  let blowerAngle = 0;
  let hubAngle = 0;
  let fanLast = 0;
  let blowerLast = 0;
  let hubLast = 0;

  return {
    title: 'Sistem AC mobil',
    // The cold side is drawn in ink: pale at night, graphite on paper.
    theme: (dark) => {
      cold.color.set(dark ? 0xe9f1f6 : 0x57534e);
      coldPipe.color.set(dark ? 0xdcdddf : 0x6f6b66);
    },
    subs: [
      {
        name: 'Kompresor',
        info: 'Jantung sistem AC. Mengisap gas refrigeran bertekanan rendah lalu memampatkannya menjadi gas panas bertekanan tinggi.',
        out: [0, -0.3, 0.22],
        make: () => [
          cyl(0.075, 0.2, m.alu, [-0.18, -0.22, 0.26], 'x'),
          cyl(0.079, 0.03, m.dark, [-0.075, -0.22, 0.26], 'x'),
          cyl(0.018, 0.07, m.dark, [-0.2, -0.15, 0.26]),
          cyl(0.02, 0.07, m.dark, [-0.1, -0.15, 0.26]),
          box(0.05, 0.03, 0.2, m.steel, [-0.18, -0.3, 0.26]),
        ],
      },
      {
        name: 'Kopling magnet & puli',
        info: 'Puli selalu berputar bersama mesin. Kompresor baru ikut berputar saat kopling magnet mendapat arus dan menempel ke puli.',
        out: [-0.26, -0.3, 0.22],
        make: () => [
          grp([cyl(0.088, 0.036, m.dark, [0, 0, 0], 'x', 0.088, 44), ...[0.07, 0.058].map((r) => ring(r, 0.004, m.steel, [-0.019, 0, 0], 'x'))], [-0.31, -0.22, 0.26]),
          grp([cyl(0.05, 0.012, m.steel, [0, 0, 0], 'x', 0.05, 32), ...spin(3, 0.032).map(([y, z]) => cyl(0.007, 0.016, m.alu, [0, y, z], 'x'))], [-0.335, -0.22, 0.26]),
        ],
        run: ([pulley, hub], t, state) => {
          pulley.rotation.x = t * 5;
          if (behaviour(state).clutch) hubAngle += (t - hubLast) * 5;
          hubLast = t;
          hub.rotation.x = hubAngle;
        },
      },
      {
        name: 'Kondensor',
        info: 'Dipasang di depan radiator. Gas panas dari kompresor melepas panasnya ke udara luar di sini dan berubah menjadi cair.',
        out: [-0.32, 0, 0],
        make: () => [
          box(0.022, 0.36, 0.56, m.alu, [-0.6, 0, 0]),
          ...Array.from({ length: 9 }, (_, i) => box(0.03, 0.007, 0.56, m.steel, [-0.6, -0.16 + i * 0.04, 0])),
          cyl(0.014, 0.38, m.dark, [-0.6, 0, 0.285]),
          cyl(0.014, 0.38, m.dark, [-0.6, 0, -0.285]),
        ],
      },
      {
        name: 'Kipas kondensor',
        info: 'Menarik udara melewati kondensor saat mobil berhenti atau berjalan pelan. Bila mati, panas tidak terbuang.',
        out: [-0.06, 0.4, 0],
        make: () => [
          ring(0.17, 0.01, m.dark, [-0.5, 0, 0], 'x'),
          grp([cyl(0.04, 0.03, m.steel, [0, 0, 0], 'x'), ...spin(5, 0.095).map(([y, z, a]) => box(0.006, 0.05, 0.13, m.dark, [0, y, z], [a + Math.PI / 2 + 0.2, 0, 0]))], [-0.5, 0, 0]),
        ],
        run: ([, fan], t, state) => {
          fanAngle += (t - fanLast) * 9 * behaviour(state).fan;
          fanLast = t;
          fan.rotation.x = fanAngle;
        },
      },
      {
        name: 'Receiver drier',
        info: 'Menampung refrigeran cair, menyaring kotoran, dan menyerap uap air. Uap air yang lolos bisa membeku di katup ekspansi.',
        out: [-0.12, 0.06, -0.3],
        make: () => [cyl(0.036, 0.2, m.dark, [-0.42, 0, -0.38]), cyl(0.04, 0.02, m.alu, [-0.42, 0.105, -0.38]), cyl(0.012, 0.012, m.glass, [-0.42, 0.12, -0.36])],
      },
      {
        name: 'Pressure switch',
        info: 'Sakelar pengaman. Memutus arus kopling magnet bila tekanan terlalu rendah atau terlalu tinggi, supaya kompresor tidak rusak.',
        out: [0, 0.34, -0.3],
        make: () => [cyl(0.012, 0.05, m.brass, [-0.12, 0.165, -0.38]), box(0.03, 0.024, 0.03, m.dark, [-0.12, 0.2, -0.38])],
      },
      {
        name: 'Katup ekspansi',
        info: 'Menyemprotkan refrigeran cair lewat celah sempit. Tekanannya jatuh, dan suhunya ikut turun drastis.',
        out: [0, 0.36, -0.06],
        make: () => [box(0.05, 0.08, 0.07, m.brass, [0.33, 0.07, -0.03]), cyl(0.02, 0.03, m.steel, [0.33, 0.125, -0.03])],
      },
      {
        name: 'Evaporator',
        info: 'Berada di balik dasbor. Refrigeran dingin menyerap panas dari udara kabin yang ditiup melewati siripnya.',
        out: [0.3, 0, -0.14],
        make: () => [
          box(0.09, 0.22, 0.3, m.alu, [0.56, 0.08, -0.06]),
          ...Array.from({ length: 13 }, (_, i) => box(0.1, 0.224, 0.004, m.steel, [0.56, 0.08, -0.2 + i * 0.0235])),
        ],
      },
      {
        name: 'Blower',
        info: 'Kipas kabin. Mendorong udara melewati filter dan evaporator, lalu keluar dari kisi-kisi AC.',
        out: [0.32, 0, 0.16],
        make: () => [
          ring(0.105, 0.008, m.dark, [0.56, 0.08, 0.24]),
          grp([
            cyl(0.025, 0.11, m.steel, [0, 0, 0], 'z'),
            cyl(0.085, 0.006, m.dark, [0, 0, 0.05], 'z', 0.085, 32),
            cyl(0.085, 0.006, m.dark, [0, 0, -0.05], 'z', 0.085, 32),
            ...spin(16, 0.074).map(([x, y, a]) => box(0.026, 0.005, 0.1, m.dark, [x, y, 0], [0, 0, a + 0.5])),
          ], [0.56, 0.08, 0.3]),
        ],
        run: ([, cage], t, state) => {
          blowerAngle += (t - blowerLast) * 8 * behaviour(state).blower;
          blowerLast = t;
          cage.rotation.z = blowerAngle;
        },
      },
      {
        name: 'Filter kabin',
        info: 'Menyaring debu sebelum udara masuk ke blower dan evaporator. Filter yang penuh membuat angin AC lemah.',
        out: [0.32, 0, 0.42],
        make: () => [
          box(0.2, 0.2, 0.012, m.dark, [0.56, 0.08, 0.47]),
          ...Array.from({ length: 10 }, (_, i) => box(0.008, 0.18, 0.03, m.glass, [0.48 + i * 0.018, 0.08, 0.47])),
        ],
      },
      {
        name: 'Pipa tekanan tinggi',
        info: 'Jalur merah: gas panas dari kompresor ke kondensor, lalu refrigeran cair ke receiver drier dan katup ekspansi.',
        out: [0, 0.3, 0],
        make: () => [...discharge.objs, ...liquidA.objs, ...liquidB.objs],
        run: (_objs, t, state) => {
          // With the compressor idle the line is no longer hot, so it is no longer red.
          hotPipe.color.set(behaviour(state).clutch ? 0xcc0000 : 0x7b7e82);
          discharge.run(t, state); liquidA.run(t, state); liquidB.run(t, state);
        },
      },
      {
        name: 'Pipa tekanan rendah',
        info: 'Jalur dingin: gas bertekanan rendah dari evaporator kembali ke kompresor. Pipa ini terasa dingin dan berembun saat AC bekerja.',
        out: [0, -0.34, 0],
        make: () => suction.objs,
        run: (_objs, t, state) => suction.run(t, state),
      },
      {
        name: 'Port servis',
        info: 'Tempat memasang selang manifold gauge: satu di sisi tekanan rendah, satu di sisi tekanan tinggi. Dari sini tekanan sistem dibaca.',
        out: [0, 0.28, 0.3],
        make: () => [
          cyl(0.011, 0.045, m.steel, [0.1, -0.085, 0.2]), cyl(0.014, 0.014, m.alu, [0.1, -0.058, 0.2]),
          cyl(0.011, 0.045, m.steel, [-0.31, 0.135, 0.262]), cyl(0.014, 0.014, m.red, [-0.31, 0.162, 0.262]),
        ],
      },
    ],
  };
}
