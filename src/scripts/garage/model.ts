import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { MeshoptDecoder } from 'three/examples/jsm/libs/meshopt_decoder.module.js';

/**
 * "2015 Toyota Avanza G 1.3" by Raysh, CC BY 4.0:
 * https://sketchfab.com/3d-models/2015-toyota-avanza-g-13-711adccbd846427b9dbca71e27fee50b
 * UVs dropped (it has no textures), then quantized and meshopt-compressed with gltf-transform
 * at full detail: simplifying dents the door panels. The credit is shown under each stage.
 */
const URL = '/models/avanza.glb';

/** What each source material is on the car, by its name in the file. */
const ROLES: Record<string, Role> = {
  'Material.069': 'paint',
  'Material.060': 'glass',
  // Tyres, rims and hub caps, front and rear.
  'Material.072': 'wheel', 'Material.073': 'wheel', 'Material.074': 'wheel', 'Tex_0009_0_dds.001': 'wheel', 'Material.068': 'wheel',
  'Material.065': 'wheel', 'Material.066': 'wheel', 'Material.067': 'wheel', 'Tex_0009_0_dds.002': 'wheel', 'Material.071': 'wheel',
};

export type Role = 'paint' | 'glass' | 'wheel' | 'trim';

/**
 * Where the file's axles and ground sit, in its own units. The file faces -z
 * with y up; wheel centres come from the tyre meshes.
 */
const FRONT_Z = -16.05;
const REAR_Z = 14.25;
const GROUND_Y = -8.2;

let pending: Promise<THREE.Group | null> | null = null;

/** Loads the body once per page; resolves to null when it cannot be fetched or parsed. */
export function loadBodyModel(): Promise<THREE.Group | null> {
  pending ??= new GLTFLoader()
    .setMeshoptDecoder(MeshoptDecoder)
    .loadAsync(URL)
    .then((gltf) => {
      gltf.scene.traverse((o) => {
        const mesh = o as THREE.Mesh;
        if (!mesh.isMesh) return;
        const material = mesh.material as THREE.MeshStandardMaterial;
        mesh.userData.role = ROLES[material.name] ?? 'trim';
        // The rims lost their texture on export and read as flat white: give them machined alloy instead.
        if (material.name.startsWith('Tex_0009')) {
          material.color.set(0x8a8e93);
          material.metalness = 0.9;
          material.roughness = 0.32;
        }
        // The template's buffers are shared by every car built from it.
        mesh.userData.keep = true;
      });
      return gltf.scene;
    })
    .catch(() => null);
  return pending;
}

/**
 * Wraps a copy of the model so its axles land on `frontAxle` and `rearAxle`
 * (metres from the rear bumper, x towards the nose) with the tyres on y = 0.
 */
export function placeBody(model: THREE.Group, frontAxle: number, rearAxle: number): THREE.Group {
  const k = (frontAxle - rearAxle) / (REAR_Z - FRONT_Z);
  const holder = new THREE.Group();
  holder.add(model.clone(true));
  // A turn, not a mirror: the steering wheel stays on the right.
  holder.rotation.y = -Math.PI / 2;
  holder.scale.setScalar(k);
  holder.position.set(rearAxle + REAR_Z * k, -GROUND_Y * k, 0);
  return holder;
}
