import * as THREE from 'three';

/**
 * The Pitcar livery: a red blade sweeping forward along the lower body with the
 * wordmark on the doors, twin red stripes over the nose, roof and tail, and
 * PITCAR plates front and rear.
 *
 * Neither body has texture coordinates (the model's were dropped, the drawn
 * shell never had any), so the paint shader projects the artwork from the car's
 * own axes: sides along z, stripes as a function of z alone.
 */

const RED = '#cc0000';
const WHITE = '#fafaf9';
const INK = '#0a0a0a';
const FONT = "'Barlow Condensed', 'Arial Narrow', sans-serif";

/** Body dimensions the artwork is laid out against, in metres. */
export interface LiveryFit {
  len: number;
  frontAxle: number;
  rearAxle: number;
  wheelR: number;
  clearance: number;
  /** Window sill height at mid-car. */
  belt: number;
}

export interface Livery {
  texture: THREE.Texture;
  /** x0, length, y0, height of the area the side artwork covers. */
  box: THREE.Vector4;
}

/** The wordmark is set in the site's display face; wait for it before drawing. */
export function loadLiveryFont(): Promise<unknown> {
  if (!document.fonts?.load) return Promise.resolve();
  return document.fonts.load(`700 100px ${FONT}`).catch(() => undefined);
}

const PX = 480; // pixels per metre
const sides = new Map<string, Livery>();

/**
 * Two rows: the right flank (nose to the right) on top, the left flank (nose to
 * the left) underneath. Each flank is drawn on its own so the lettering reads
 * the right way round and the blade points at the nose on both.
 */
export function sideLivery(fit: LiveryFit, anisotropy = 4): Livery {
  const key = Object.values(fit).join();
  const hit = sides.get(key);
  if (hit) return hit;

  const y0 = Math.max(0, fit.clearance - 0.12);
  const h = fit.belt + 0.06 - y0;
  const W = Math.min(4096, THREE.MathUtils.ceilPowerOfTwo(fit.len * PX));
  const H = THREE.MathUtils.ceilPowerOfTwo(h * PX);
  const canvas = document.createElement('canvas');
  canvas.width = W;
  canvas.height = H * 2;
  const g = canvas.getContext('2d')!;

  const drawFlank = (noseRight: boolean, top: number) => {
    // Metres along the car and above the ground to canvas pixels for this row.
    const sx = W / fit.len;
    const sy = H / h;
    const X = (x: number) => (noseRight ? x : fit.len - x) * sx;
    const Y = (y: number) => top + (y0 + h - y) * sy;
    const poly = (pts: [number, number][], fill: string) => {
      g.beginPath();
      pts.forEach(([x, y], i) => (i ? g.lineTo(X(x), Y(y)) : g.moveTo(X(x), Y(y))));
      g.closePath();
      g.fillStyle = fill;
      g.fill();
    };

    g.save();
    g.beginPath();
    g.rect(0, top, W, H);
    g.clip();

    // The blade: tall at the tail, cutting down to a point ahead of the front door.
    const sill = fit.clearance + 0.1;
    const tip = fit.frontAxle - fit.wheelR - 0.38;
    const tail = fit.belt - 0.1;
    const slope = (tail - sill) / tip; // metres of drop per metre forward
    const edge = (x: number) => tail - slope * x;
    const below = y0 - 0.2;
    poly([[-0.1, tail + slope * 0.1], [tip, sill], [tip + 0.35, below], [-0.1, below]], RED);

    // A white pinstripe riding just above the blade, running past its tip.
    const gap = 0.035;
    const pin = 0.018;
    const pinEnd = tip + 0.5;
    poly([
      [-0.1, edge(-0.1) + gap], [pinEnd, edge(pinEnd) + gap],
      [pinEnd, edge(pinEnd) + gap + pin], [-0.1, edge(-0.1) + gap + pin],
    ], WHITE);

    // Speed slashes in front of the tip, shrinking towards the nose.
    let at = tip + 0.08;
    for (const [w, tall] of [[0.09, 0.3], [0.065, 0.24], [0.045, 0.18]] as const) {
      const lean = tall * 0.55;
      poly([[at, sill], [at + w, sill], [at + w + lean, sill + tall], [at + lean, sill + tall]], RED);
      at += w + 0.07;
    }

    // Wordmark on the doors, leaning forward like the blade.
    const mid = (fit.rearAxle + fit.frontAxle) / 2;
    const capY = fit.clearance + (fit.belt - fit.clearance) * 0.56;
    const size = Math.min(0.27, (fit.belt - fit.clearance) * 0.34) * sy;
    g.save();
    g.translate(X(mid), Y(capY));
    g.transform(1, 0, noseRight ? -0.22 : 0.22, 1, 0, 0);
    g.textAlign = 'center';
    g.textBaseline = 'alphabetic';
    g.font = `700 ${size}px ${FONT}`;
    g.lineJoin = 'round';
    g.lineWidth = size * 0.1;
    g.strokeStyle = INK;
    g.strokeText('PITCAR', 0, 0);
    g.fillStyle = WHITE;
    g.fillText('PITCAR', 0, 0);
    // ACADEMY on a black tag under the wordmark, so it reads over the pinstripe it crosses.
    const sub = size * 0.36;
    g.font = `700 ${sub}px ${FONT}`;
    if ('letterSpacing' in g) (g as CanvasRenderingContext2D & { letterSpacing: string }).letterSpacing = `${sub * 0.3}px`;
    const word = g.measureText('ACADEMY').width;
    const tagW = word + sub * 0.7;
    const tagH = sub * 1.25;
    const tagY = size * 0.14;
    g.fillStyle = INK;
    g.fillRect(-tagW / 2, tagY, tagW, tagH);
    g.fillStyle = RED;
    g.fillRect(-tagW / 2, tagY, sub * 0.14, tagH);
    g.fillStyle = WHITE;
    g.fillText('ACADEMY', sub * 0.2, tagY + tagH * 0.82);
    g.restore();

    g.restore();
  };

  drawFlank(true, 0);
  drawFlank(false, H);

  const texture = new THREE.CanvasTexture(canvas);
  texture.colorSpace = THREE.SRGBColorSpace;
  texture.anisotropy = anisotropy;
  const livery = { texture, box: new THREE.Vector4(0, fit.len, y0, h) };
  sides.set(key, livery);
  return livery;
}

/**
 * Patches the shared paint so it carries the livery. `set` swaps the artwork
 * when a different body is mounted.
 */
export function liveryPaint(paint: THREE.MeshPhysicalMaterial) {
  const uniforms = {
    uLivSide: { value: null as THREE.Texture | null },
    uLivBox: { value: new THREE.Vector4(0, 1, 0, 1) },
    uLivOn: { value: 0 },
    uLivRed: { value: new THREE.Color(RED) },
    uLivWhite: { value: new THREE.Color(WHITE) },
    // Half-widths of the red stripe, and of the white pinstripe's inner and outer edges.
    uLivStripe: { value: new THREE.Vector3(0.13, 0.155, 0.175) },
  };

  paint.onBeforeCompile = (shader) => {
    Object.assign(shader.uniforms, uniforms);
    shader.vertexShader = shader.vertexShader
      .replace('#include <common>', '#include <common>\nvarying vec3 vLivP;\nvarying vec3 vLivN;')
      .replace(
        '#include <project_vertex>',
        `#include <project_vertex>
        vLivP = (modelMatrix * vec4(transformed, 1.0)).xyz;
        vLivN = normalize(mat3(modelMatrix) * objectNormal);`,
      );
    shader.fragmentShader = shader.fragmentShader
      .replace(
        '#include <common>',
        `#include <common>
        varying vec3 vLivP;
        varying vec3 vLivN;
        uniform sampler2D uLivSide;
        uniform vec4 uLivBox;
        uniform float uLivOn;
        uniform vec3 uLivRed;
        uniform vec3 uLivWhite;
        uniform vec3 uLivStripe;`,
      )
      .replace(
        '#include <color_fragment>',
        `#include <color_fragment>
        float livAmt = 0.0;
        if (uLivOn > 0.5) {
          vec3 ln = normalize(vLivN);
          // Flanks take the side artwork; everything facing up, forward or back takes the stripes.
          float flank = smoothstep(0.45, 0.75, abs(ln.z));
          vec2 st = vec2((vLivP.x - uLivBox.x) / uLivBox.y, (vLivP.y - uLivBox.z) / uLivBox.w);
          float inside = step(0.0, st.x) * step(st.x, 1.0) * step(0.0, st.y) * step(st.y, 1.0);
          bool right = vLivP.z > 0.0;
          vec2 tuv = vec2(right ? st.x : 1.0 - st.x, clamp(st.y, 0.002, 0.998) * 0.5 + (right ? 0.5 : 0.0));
          vec4 art = texture2D(uLivSide, tuv);
          float sideAmt = art.a * inside * flank;
          diffuseColor.rgb = mix(diffuseColor.rgb, art.rgb, sideAmt);

          float az = abs(vLivP.z);
          float aa = max(fwidth(az), 1e-4);
          float red = 1.0 - smoothstep(uLivStripe.x - aa, uLivStripe.x + aa, az);
          float white = smoothstep(uLivStripe.y - aa, uLivStripe.y + aa, az) * (1.0 - smoothstep(uLivStripe.z - aa, uLivStripe.z + aa, az));
          float top = 1.0 - flank;
          diffuseColor.rgb = mix(diffuseColor.rgb, uLivRed, red * top);
          diffuseColor.rgb = mix(diffuseColor.rgb, uLivWhite, white * top);
          livAmt = max(sideAmt, (red + white) * top);
        }`,
      )
      .replace(
        '#include <metalnessmap_fragment>',
        // Vinyl over metallic paint: the graphics read flatter than the base coat.
        '#include <metalnessmap_fragment>\nmetalnessFactor = mix(metalnessFactor, 0.15, livAmt);',
      )
      .replace(
        '#include <roughnessmap_fragment>',
        '#include <roughnessmap_fragment>\nroughnessFactor = mix(roughnessFactor, 0.38, livAmt);',
      );
  };

  return {
    set(livery: Livery | null) {
      uniforms.uLivOn.value = livery ? 1 : 0;
      if (!livery) return;
      uniforms.uLivSide.value = livery.texture;
      uniforms.uLivBox.value.copy(livery.box);
    },
  };
}

let plateTexture: THREE.CanvasTexture | null = null;

/** An Indonesian civilian plate, white since 2022, reading PITCAR. */
function plateArt(anisotropy: number) {
  if (plateTexture) return plateTexture;
  const c = document.createElement('canvas');
  c.width = 1024;
  c.height = 320;
  const g = c.getContext('2d')!;
  g.fillStyle = WHITE;
  g.beginPath();
  g.roundRect(0, 0, 1024, 320, 26);
  g.fill();
  g.strokeStyle = INK;
  g.lineWidth = 16;
  g.beginPath();
  g.roundRect(18, 18, 988, 284, 18);
  g.stroke();
  g.fillStyle = INK;
  g.textAlign = 'center';
  g.textBaseline = 'alphabetic';
  g.font = `700 236px ${FONT}`;
  if ('letterSpacing' in g) (g as CanvasRenderingContext2D & { letterSpacing: string }).letterSpacing = '18px';
  g.fillText('PITCAR', 521, 238);
  // Month and year of validity, under the number.
  g.font = `600 44px ${FONT}`;
  if ('letterSpacing' in g) (g as CanvasRenderingContext2D & { letterSpacing: string }).letterSpacing = '6px';
  g.fillText('11 · 31', 515, 288);
  plateTexture = new THREE.CanvasTexture(c);
  plateTexture.colorSpace = THREE.SRGBColorSpace;
  plateTexture.anisotropy = anisotropy;
  return plateTexture;
}

const PLATE_W = 0.43;
const PLATE_H = 0.135;

/**
 * Plates on the front and rear faces of `body`, found by casting a ray at each
 * bumper so they sit on whatever surface the body has there. The material is
 * returned so the plates fade out with the paint.
 */
export function mountPlates(body: THREE.Object3D, fit: LiveryFit, heights: { front: number; rear: number }, anisotropy = 4) {
  const material = new THREE.MeshStandardMaterial({
    map: plateArt(anisotropy), roughness: 0.45, metalness: 0.1, transparent: true,
    polygonOffset: true, polygonOffsetFactor: -2,
  });
  const backing = new THREE.MeshStandardMaterial({ color: 0x18181b, roughness: 0.7, transparent: true });
  body.updateMatrixWorld(true);
  const caster = new THREE.Raycaster();
  const plates = new THREE.Group();

  const face = (dir: 1 | -1, y: number) => {
    // Rays over the plate's whole footprint, keeping the outermost hit: a grille
    // opening or a recessed panel must not swallow it behind the bumper lip.
    let surface = dir > 0 ? -Infinity : Infinity;
    for (const dy of [-PLATE_H / 2, 0, PLATE_H / 2]) {
      for (const z of [-PLATE_W / 2, 0, PLATE_W / 2]) {
        caster.set(new THREE.Vector3(dir > 0 ? fit.len + 2 : -2, y + dy, z), new THREE.Vector3(-dir, 0, 0));
        const hit = caster.intersectObject(body, true).find((h) => (h.object as THREE.Mesh).isMesh && h.object.userData.role !== 'glass');
        if (hit) surface = dir > 0 ? Math.max(surface, hit.point.x) : Math.min(surface, hit.point.x);
      }
    }
    if (!Number.isFinite(surface)) surface = dir > 0 ? fit.len : 0;

    const plate = new THREE.Mesh(new THREE.PlaneGeometry(PLATE_W, PLATE_H), material);
    const back = new THREE.Mesh(new THREE.BoxGeometry(0.012, PLATE_H + 0.016, PLATE_W + 0.016), backing);
    plate.rotation.y = (dir * Math.PI) / 2;
    plate.position.set(surface + dir * 0.016, y, 0);
    back.position.set(surface + dir * 0.008, y, 0);
    for (const m of [plate, back]) {
      m.userData.shadow = 'none';
      m.renderOrder = 3;
    }
    plates.add(back, plate);
  };
  face(1, heights.front);
  face(-1, heights.rear);
  return { plates, materials: [material, backing] };
}
