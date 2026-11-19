import * as THREE from 'three';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { type BodyMats, type Car, type Fuel, type Slot, buildCar, makeShellMaterial } from './car';
import { type Assembly, buildAssembly } from './bench';
import { type Mats, makeMats, setActive, disposeTree, grp } from './kit';
import { loadBodyModel } from './model';
import { liveryPaint, loadLiveryFont, sideLivery } from './livery';

export type { Fuel, Slot };

/** A numbered check point floating on the car. */
export interface Hotspot {
  id: string;
  label: string;
  /** Position in the car's own metres: x from the rear bumper, y up, z to the right. */
  at: [number, number, number];
}

export type SpotState = 'open' | 'active' | 'done';

export interface Hover {
  slot?: Slot;
  sub?: number;
  spot?: string;
  /** Pointer position inside the canvas, in CSS pixels. */
  x: number;
  y: number;
}

export interface GarageOptions {
  fuel: Fuel;
  matic: boolean;
  slot: Slot;
  /** A system was clicked on the car. */
  onPick: (slot: Slot) => void;
  /** A system was double-clicked: open it on the bench. */
  onOpen: (slot: Slot) => void;
  /** A piece was clicked on the bench. */
  onSub: (index: number) => void;
  onHover: (hover: Hover | null) => void;
  /** Average of how far the pieces are pulled apart, 0..1, while it changes. */
  onExplode: (t: number) => void;
  /** Shows this one system on the bench and never builds a car around it. */
  custom?: (mats: Mats) => Assembly;
  /** Check points drawn on the car; with these the body stays painted on arrival. */
  hotspots?: Hotspot[];
  onSpot?: (id: string) => void;
}

export interface Garage {
  setConfig(fuel: Fuel, matic: boolean): void;
  setSlot(slot: Slot): void;
  /** Painted body (true) or see-through shell (false). */
  setSolid(solid: boolean): void;
  /** Puts the current system on the bench, pulled apart by `explode` (0..1), and returns its pieces. */
  openBench(explode?: number): { title: string; subs: { name: string; info: string }[]; canRun: boolean };
  closeBench(): void;
  /** Pulls every piece apart by the same amount, 0..1. */
  setExplode(t: number): void;
  /** Highlights one piece; with `pull`, also lifts it out if it is still in place. */
  selectSub(index: number | null, pull?: boolean): void;
  /** Highlights several pieces at once, e.g. the suspects of a fault. */
  mark(indices: number[]): void;
  /** Names the condition passed to every piece's `run`. */
  setState(state: string): void;
  /** Redraws one check point with new text (a number, or a result code) and state. */
  setSpot(id: string, text: string, state: SpotState): void;
  setRunning(on: boolean): void;
  nudge(dYaw: number, dPitch: number): void;
  zoom(factor: number): void;
}

const FOV = 26;
const LIGHT_DIR = new THREE.Vector3(0.45, 1, 0.55).normalize();

/** A shadow catcher and a neon grid, both fading out towards the edge. */
function floorMaterials() {
  const grid = new THREE.ShaderMaterial({
    transparent: true,
    depthWrite: false,
    uniforms: { uColor: { value: new THREE.Color(0xcc0000) }, uCells: { value: 24 }, uAlpha: { value: 0.5 } },
    vertexShader: `varying vec2 vUv; void main() { vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }`,
    fragmentShader: `
      uniform vec3 uColor; uniform float uCells; uniform float uAlpha; varying vec2 vUv;
      void main() {
        vec2 g = vUv * uCells;
        vec2 d = abs(fract(g - 0.5) - 0.5) / fwidth(g);
        float line = 1.0 - min(min(d.x, d.y), 1.0);
        float fade = 1.0 - smoothstep(0.2, 1.0, length(vUv - 0.5) * 2.0);
        gl_FragColor = vec4(uColor, line * fade * uAlpha);
      }`,
  });
  const shadow = new THREE.ShadowMaterial({ opacity: 0.5 });
  return { grid, shadow };
}

export async function createGarage(canvas: HTMLCanvasElement, opts: GarageOptions): Promise<Garage | null> {
  let renderer: THREE.WebGLRenderer;
  try {
    renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  } catch {
    return null;
  }
  // The modelled body downloads while the scene is set up; without it the drawn body stands in.
  const modelLoad = opts.custom ? Promise.resolve(null) : loadBodyModel();
  const fontLoad = opts.custom ? Promise.resolve() : loadLiveryFont();
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.15;
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;

  const scene = new THREE.Scene();
  const pmrem = new THREE.PMREMGenerator(renderer);
  scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
  pmrem.dispose();

  const key = new THREE.DirectionalLight(0xffffff, 2.2);
  key.castShadow = true;
  key.shadow.mapSize.set(2048, 2048);
  key.shadow.bias = -0.0004;
  key.shadow.normalBias = 0.02;
  scene.add(key, key.target);
  // Coloured back light: the red edge that makes metal read as lit by signage.
  const rim = new THREE.DirectionalLight(0xff2a1a, 0);
  scene.add(rim, rim.target);
  const underglow = new THREE.PointLight(0xcc0000, 0, 6, 1.6);
  scene.add(underglow);

  const camera = new THREE.PerspectiveCamera(FOV, 2, 0.05, 80);
  const mats = makeMats();
  // Graphite base coat under the red and white Pitcar livery.
  const paint = new THREE.MeshPhysicalMaterial({
    color: 0x2b2e33, metalness: 0.8, roughness: 0.32, clearcoat: 1, clearcoatRoughness: 0.05,
    transparent: true, side: THREE.DoubleSide,
  });
  const livery = liveryPaint(paint);
  const aniso = Math.min(8, renderer.capabilities.getMaxAnisotropy());
  const glass = new THREE.MeshPhysicalMaterial({
    color: 0x0c1014, metalness: 0.3, roughness: 0.04, transparent: true, side: THREE.DoubleSide, depthWrite: false,
  });
  const xray = makeShellMaterial();
  const line = new THREE.LineBasicMaterial({ color: 0xa8a29e, transparent: true });
  const body: BodyMats = { paint, glass, xray, line };

  const floorMats = floorMaterials();
  const plane = new THREE.PlaneGeometry(1, 1);
  const shadowCatcher = new THREE.Mesh(plane, floorMats.shadow);
  shadowCatcher.receiveShadow = true;
  const gridMesh = new THREE.Mesh(plane, floorMats.grid);
  gridMesh.position.z = 0.002;
  const floor = grp([shadowCatcher, gridMesh]);
  floor.rotation.x = -Math.PI / 2;
  scene.add(floor);

  // ---- check points: always drawn on top, so a point under the floor is still reachable ----
  const spots = new THREE.Group();
  const spotById = new Map<string, THREE.Sprite>();
  function drawSpot(sprite: THREE.Sprite, text: string, spotState: SpotState) {
    const c = document.createElement('canvas');
    c.width = c.height = 96;
    const g = c.getContext('2d')!;
    const fill = spotState === 'open' ? '#cc0000' : spotState === 'active' ? '#fafaf9' : '#0a0a0a';
    const ink = spotState === 'active' ? '#cc0000' : '#fafaf9';
    g.fillStyle = fill;
    g.fillRect(8, 8, 80, 80);
    g.lineWidth = 6;
    // On paper the pale active tile needs an edge; at night it stands out by itself.
    const edged = spotState === 'active' && !document.documentElement.classList.contains('dark');
    g.strokeStyle = spotState === 'done' ? '#fafaf9' : edged ? '#cc0000' : fill;
    g.strokeRect(8, 8, 80, 80);
    g.fillStyle = ink;
    g.font = `700 ${text.length > 1 ? 40 : 52}px 'Barlow Condensed', Arial Narrow, sans-serif`;
    g.textAlign = 'center';
    g.textBaseline = 'middle';
    g.fillText(text, 48, 51);
    sprite.material.map?.dispose();
    sprite.material.map = new THREE.CanvasTexture(c);
    sprite.material.map.colorSpace = THREE.SRGBColorSpace;
    sprite.material.needsUpdate = true;
    sprite.userData.drawn = [text, spotState];
  }
  (opts.hotspots ?? []).forEach((spot, i) => {
    const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ depthTest: false, transparent: true }));
    sprite.position.set(...spot.at);
    sprite.scale.setScalar(0.2);
    sprite.renderOrder = 20;
    sprite.userData.spot = spot.id;
    drawSpot(sprite, String(i + 1), 'open');
    spotById.set(spot.id, sprite);
    spots.add(sprite);
  });
  scene.add(spots);

  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');

  // ---- state ----
  let fuel = opts.fuel;
  let matic = opts.matic;
  let slot = opts.slot;
  let car = null as unknown as Car;
  let model: THREE.Group | null = null;
  let bench: {
    group: THREE.Group; asm: Assembly; pieces: THREE.Group[]; made: THREE.Object3D[][];
    t: number[]; goal: number[]; near: THREE.Sphere; far: THREE.Sphere;
  } | null = null;
  let activeSub: number | null = null;
  let marked: number[] = [];
  let state = '';
  let running = false;
  let runTime = 0;
  let last = 0;

  const view = { yaw: 0.05, pitch: 0.16, zoom: 1.12, solid: 1 };
  const goal = { yaw: 0.62, pitch: 0.2, zoom: 1, solid: 1 };
  let frame = 0;

  /** Every mesh casts and takes shadows unless it says otherwise in `userData.shadow`. */
  const shadows = (root: THREE.Object3D) => root.traverse((o) => {
    if (!(o as THREE.Mesh).isMesh) return;
    const mode = o.userData.shadow as 'cast' | 'none' | undefined;
    o.castShadow = mode !== 'none';
    o.receiveShadow = !mode;
  });

  function stage(center: THREE.Vector3, radius: number, floorY: number) {
    floor.position.set(center.x, floorY, center.z);
    floor.scale.setScalar(radius * 5);
    floorMats.grid.uniforms.uCells.value = bench ? 20 : 26;
    key.position.copy(center).addScaledVector(LIGHT_DIR, radius * 6);
    key.target.position.copy(center);
    const cam = key.shadow.camera;
    cam.left = cam.bottom = -radius * 1.5;
    cam.right = cam.top = radius * 1.5;
    cam.near = radius;
    cam.far = radius * 12;
    cam.updateProjectionMatrix();
    rim.position.copy(center).add(new THREE.Vector3(-radius * 3, radius * 1.2, -radius * 4));
    rim.target.position.copy(center);
  }

  function mountCar() {
    if (car) { scene.remove(car.group); disposeTree(car.group); }
    car = buildCar(fuel, matic, mats, body, model);
    livery.set(sideLivery(car.livery, aniso));
    shadows(car.group);
    scene.add(car.group);
    underglow.position.set(car.center.x, 0.12, 0);
    const part = car.parts.get(slot);
    if (part) setActive(part, true, mats.active);
    stage(car.center, car.radius, 0);
  }

  function applyBody() {
    const s = view.solid;
    paint.opacity = 0.05 + 0.95 * s;
    paint.depthWrite = s > 0.97;
    glass.opacity = 0.06 + 0.6 * s;
    xray.uniforms.uFade.value = 1 - s;
    line.opacity = 1 - 0.75 * s;
    car?.fades.forEach((m) => {
      m.opacity = s;
      m.depthWrite = s > 0.97;
      // Fully faded trim is skipped instead of drawn at zero opacity.
      m.visible = s > 0.01;
    });
  }

  function applyTheme() {
    const css = getComputedStyle(document.documentElement);
    const token = (name: string) => new THREE.Color(`rgb(${css.getPropertyValue(name).trim().split(/\s+/).join(',')})`);
    const dark = document.documentElement.classList.contains('dark');
    xray.uniforms.uColor.value.copy(token('--muted'));
    xray.uniforms.uFace.value = dark ? 0.045 : 0.07;
    xray.uniforms.uRim.value = dark ? 0.6 : 0.7;
    line.color.copy(token('--muted'));
    floorMats.grid.uniforms.uColor.value.copy(token('--accent'));
    floorMats.grid.uniforms.uAlpha.value = dark ? 0.42 : 0.22;
    floorMats.shadow.opacity = dark ? 0.6 : 0.28;
    // Paper has no light to cast: the red light exists in the dark theme only.
    underglow.intensity = dark ? 5 : 0;
    rim.intensity = dark ? 3.2 : 0;
    bench?.asm.theme?.(dark);
    scene.environmentIntensity = dark ? 0.8 : 1.05;
    // Lower exposure on paper keeps bare aluminium from bleaching into the ground.
    renderer.toneMappingExposure = dark ? 1.15 : 0.95;
    spotById.forEach((sprite) => {
      const [text, spotState] = sprite.userData.drawn as [string, SpotState];
      drawSpot(sprite, text, spotState);
    });
    invalidate();
  }

  // ---- camera ----
  const target = new THREE.Vector3();
  const spread = () => (bench ? bench.t.reduce((a, b) => a + b, 0) / bench.t.length : 0);

  function frameCamera() {
    let radius: number;
    if (bench) {
      const k = spread();
      target.lerpVectors(bench.near.center, bench.far.center, k);
      radius = bench.near.radius + (bench.far.radius - bench.near.radius) * k;
    } else {
      target.copy(car.center);
      radius = car.radius;
    }
    const v = THREE.MathUtils.degToRad(FOV) / 2;
    const h = Math.atan(Math.tan(v) * camera.aspect);
    // The car is far longer than it is tall, so fit its length to the width and
    // its height to the height instead of wrapping it in one sphere.
    const fit = bench
      ? (radius / Math.sin(Math.min(v, h))) * 0.88
      : Math.max((radius * 1.14) / Math.tan(h), (car.center.y * 2.2) / Math.tan(v));
    const dist = fit * view.zoom;
    camera.position.set(
      target.x + dist * Math.sin(view.yaw) * Math.cos(view.pitch),
      target.y + dist * Math.sin(view.pitch),
      target.z + dist * Math.cos(view.yaw) * Math.cos(view.pitch),
    );
    camera.lookAt(target);
  }

  function resize() {
    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    if (!w || !h) return;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    invalidate();
  }

  function tick(now: number) {
    frame = 0;
    const dt = Math.min(0.1, last ? (now - last) / 1000 : 1 / 60);
    last = now;
    let moving = false;
    // Time-based easing, so a slow device takes the same time, not longer.
    const approach = (from: number, to: number, speed = 10) => {
      if (reduce.matches || Math.abs(to - from) < 0.0005) return to;
      moving = true;
      return from + (to - from) * (1 - Math.exp(-speed * dt));
    };
    view.yaw = approach(view.yaw, goal.yaw);
    view.pitch = approach(view.pitch, goal.pitch);
    view.zoom = approach(view.zoom, goal.zoom);
    view.solid = approach(view.solid, goal.solid, 4);
    applyBody();

    if (bench) {
      const b = bench;
      const before = spread();
      b.pieces.forEach((piece, i) => {
        b.t[i] = approach(b.t[i], b.goal[i]);
        const [x, y, z] = b.asm.subs[i].out;
        piece.position.set(x * b.t[i], y * b.t[i], z * b.t[i]);
      });
      if (spread() !== before) opts.onExplode(spread());
      if (running) {
        runTime += dt;
        b.asm.subs.forEach((sub, i) => sub.run?.(b.made[i], runTime, state));
        moving = true;
      }
    }
    if (!bench && !car) return;
    frameCamera();
    renderer.render(scene, camera);
    if (moving) invalidate();
    else last = 0;
  }

  function invalidate() {
    if (!frame) frame = requestAnimationFrame(tick);
  }

  // ---- pointer: drag turns, click picks, double-click opens ----
  const ray = new THREE.Raycaster();
  const ndc = new THREE.Vector2();
  let drag: { id: number; x: number; y: number; moved: number } | null = null;

  function hit(ev: MouseEvent): Hover | null {
    const r = canvas.getBoundingClientRect();
    ndc.set(((ev.clientX - r.left) / r.width) * 2 - 1, -((ev.clientY - r.top) / r.height) * 2 + 1);
    ray.setFromCamera(ndc, camera);
    const at = { x: ev.clientX - r.left, y: ev.clientY - r.top };
    if (!bench && spots.children.length) {
      const spot = ray.intersectObjects(spots.children, false)[0];
      if (spot) return { spot: spot.object.userData.spot, ...at };
    }
    const roots = bench ? bench.pieces : [...car.parts.values()];
    const found = ray.intersectObjects(roots, true)[0];
    for (let o: THREE.Object3D | null = found?.object ?? null; o; o = o.parent) {
      if (o.userData.slot) return { slot: o.userData.slot, ...at };
      if (o.userData.sub !== undefined) return { sub: o.userData.sub, ...at };
    }
    return null;
  }

  canvas.addEventListener('pointerdown', (ev) => {
    drag = { id: ev.pointerId, x: ev.clientX, y: ev.clientY, moved: 0 };
    canvas.setPointerCapture(ev.pointerId);
    opts.onHover(null);
  });
  canvas.addEventListener('pointermove', (ev) => {
    if (!drag) {
      if (ev.pointerType !== 'mouse') return;
      // A painted body hides the parts, so only check points can be pointed at.
      const found = hit(ev);
      const h = !bench && view.solid > 0.5 && !found?.spot ? null : found;
      canvas.style.cursor = h ? 'pointer' : 'grab';
      opts.onHover(h);
      return;
    }
    if (ev.pointerId !== drag.id) return;
    const dx = ev.clientX - drag.x;
    const dy = ev.clientY - drag.y;
    drag.x = ev.clientX;
    drag.y = ev.clientY;
    drag.moved += Math.abs(dx) + Math.abs(dy);
    canvas.style.cursor = 'grabbing';
    api.nudge(-dx * 0.008, dy * 0.006);
  });
  const release = (ev: PointerEvent) => {
    if (!drag || ev.pointerId !== drag.id) return;
    const wasClick = drag.moved < 6 && ev.type === 'pointerup';
    drag = null;
    canvas.style.cursor = 'grab';
    if (!wasClick) return;
    const h = hit(ev);
    if (h?.spot) opts.onSpot?.(h.spot);
    else if (h?.slot && view.solid < 0.5) opts.onPick(h.slot);
    else if (h?.sub !== undefined) opts.onSub(h.sub);
  };
  canvas.addEventListener('pointerup', release);
  canvas.addEventListener('pointercancel', release);
  canvas.addEventListener('pointerleave', () => opts.onHover(null));
  canvas.addEventListener('dblclick', (ev) => {
    if (bench) return;
    const h = hit(ev);
    if (h?.slot) opts.onOpen(h.slot);
  });
  canvas.addEventListener('keydown', (ev) => {
    const step: Record<string, [number, number]> = {
      ArrowLeft: [-0.2, 0], ArrowRight: [0.2, 0], ArrowUp: [0, 0.12], ArrowDown: [0, -0.12],
    };
    const d = step[ev.key];
    if (d) { ev.preventDefault(); api.nudge(d[0], d[1]); }
    if (ev.key === '+' || ev.key === '=') api.zoom(0.85);
    if (ev.key === '-') api.zoom(1.18);
  });
  // Ctrl/pinch-zoom only, so an ordinary scroll over the model still scrolls the page.
  canvas.addEventListener('wheel', (ev) => {
    if (!ev.ctrlKey && !ev.metaKey) return;
    ev.preventDefault();
    api.zoom(ev.deltaY > 0 ? 1.08 : 0.92);
  }, { passive: false });

  function highlight() {
    bench?.pieces.forEach((piece, i) => setActive(piece, i === activeSub || marked.includes(i), mats.active));
    invalidate();
  }

  function dropBench() {
    if (!bench) return;
    scene.remove(bench.group);
    disposeTree(bench.group);
    bench = null;
    activeSub = null;
    marked = [];
    running = false;
  }

  const api: Garage = {
    setConfig(nextFuel, nextMatic) {
      if (nextFuel === fuel && nextMatic === matic) return;
      api.closeBench();
      fuel = nextFuel;
      matic = nextMatic;
      mountCar();
      invalidate();
    },
    setSlot(next) {
      if (bench) api.closeBench();
      const prev = car.parts.get(slot);
      if (prev) setActive(prev, false, mats.active);
      slot = next;
      const part = car.parts.get(slot);
      if (part) setActive(part, true, mats.active);
      invalidate();
    },
    setSolid(solid) {
      goal.solid = solid ? 1 : 0;
      invalidate();
    },
    openBench(explodeTo = 1) {
      dropBench();
      const asm = opts.custom ? opts.custom(mats) : buildAssembly(slot, fuel, matic, mats);
      const made = asm.subs.map((sub) => sub.make());
      const pieces = made.map((objs, i) => {
        const g = grp(objs);
        g.userData.sub = i;
        return g;
      });
      const group = grp(pieces);
      shadows(group);
      const bounds = (t: number) => {
        pieces.forEach((p, i) => p.position.set(asm.subs[i].out[0] * t, asm.subs[i].out[1] * t, asm.subs[i].out[2] * t));
        return new THREE.Box3().setFromObject(group);
      };
      const farBox = bounds(1);
      const far = farBox.getBoundingSphere(new THREE.Sphere());
      const near = bounds(0).getBoundingSphere(new THREE.Sphere());
      bench = { group, asm, pieces, made, near, far, t: pieces.map(() => 0), goal: pieces.map(() => explodeTo) };
      if (car) car.group.visible = false;
      spots.visible = false;
      underglow.visible = false;
      scene.add(group);
      asm.theme?.(document.documentElement.classList.contains('dark'));
      stage(far.center, far.radius, farBox.min.y - 0.06);
      view.zoom = 1.25;
      goal.zoom = 1;
      goal.yaw = 0.7;
      goal.pitch = 0.42;
      runTime = 0;
      invalidate();
      return {
        title: asm.title,
        subs: asm.subs.map(({ name, info }) => ({ name, info })),
        canRun: asm.subs.some((sub) => sub.run),
      };
    },
    closeBench() {
      if (!bench || opts.custom) return;
      dropBench();
      spots.visible = true;
      car.group.visible = true;
      underglow.visible = true;
      stage(car.center, car.radius, 0);
      view.zoom = 1.12;
      goal.zoom = 1;
      goal.pitch = 0.2;
      invalidate();
    },
    setExplode(t) {
      if (!bench) return;
      bench.goal.fill(Math.min(1, Math.max(0, t)));
      invalidate();
    },
    selectSub(index, pull = true) {
      if (!bench) return;
      activeSub = index;
      if (index !== null && pull && bench.goal[index] < 0.5) bench.goal[index] = 1;
      highlight();
    },
    mark(indices) {
      marked = indices;
      highlight();
    },
    setState(next) {
      state = next;
      invalidate();
    },
    setSpot(id, text, spotState) {
      const sprite = spotById.get(id);
      if (!sprite) return;
      drawSpot(sprite, text, spotState);
      invalidate();
    },
    setRunning(on) {
      running = on && !!bench;
      invalidate();
    },
    nudge(dYaw, dPitch) {
      goal.yaw += dYaw;
      goal.pitch = Math.min(1.25, Math.max(bench ? -0.5 : 0.03, goal.pitch + dPitch));
      invalidate();
    },
    zoom(factor) {
      goal.zoom = Math.min(1.5, Math.max(0.45, goal.zoom * factor));
      invalidate();
    },
  };

  [model] = await Promise.all([modelLoad, fontLoad]);
  if (!opts.custom) mountCar();
  applyTheme();
  resize();
  new ResizeObserver(resize).observe(canvas);
  new MutationObserver(applyTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
  canvas.style.cursor = 'grab';
  // The car arrives painted, then turns to glass to show what is inside.
  // An inspection starts from the outside, so with check points the paint stays on.
  if (!opts.hotspots) {
    if (reduce.matches || opts.custom) view.solid = goal.solid = 0;
    else setTimeout(() => api.setSolid(false), 1300);
  }

  return api;
}
