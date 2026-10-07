/**
 * Italgres furniture configurator: Three.js viewer.
 *
 * The storefront Alpine component owns the configuration state and calls `viewer.apply(look)`
 * whenever a selection changes. The viewer resolves that look onto the loaded GLB:
 *   - materials / colours / surface props per part (matched by mesh, material or node name)
 *   - parametric width ("smart stretch": the centre stretches, ends and rigid parts move)
 *   - part visibility, model swaps
 *   - modules: one module repeated N times, or a row composed of different elements (sectionals)
 * and adds the presentation layer: studio lighting, soft shadows, camera presets,
 * live dimensions, hover/click part picking, element previews and snapshots.
 */
import {
    ACESFilmicToneMapping,
    Box3,
    CanvasTexture,
    Color,
    DirectionalLight,
    Group,
    HemisphereLight,
    LessEqualDepth,
    LineBasicMaterial,
    LineSegments,
    BufferGeometry,
    Float32BufferAttribute,
    Mesh,
    MeshBasicMaterial,
    MeshPhysicalMaterial,
    NeutralToneMapping,
    PCFShadowMap,
    PerspectiveCamera,
    PlaneGeometry,
    PMREMGenerator,
    Raycaster,
    RepeatWrapping,
    Scene,
    ShadowMaterial,
    SRGBColorSpace,
    TextureLoader,
    Vector2,
    Vector3,
    WebGLRenderer,
    MathUtils,
} from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

// Lens per camera preset: the catalogue front view uses a wider lens from closer, like a product photo
const LENS = { default: 28, front: 38 };

const easeInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
const easeOut = (t) => 1 - Math.pow(1 - t, 3);

/** Glob ("fabric*", "!legs") to case-insensitive RegExp */
function globRegExp(pattern) {
    const escaped = pattern.trim().replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*');
    return new RegExp(`^${escaped}$`, 'i');
}

function parsePatterns(value) {
    if (!value) return [];
    return (Array.isArray(value) ? value : String(value).split(/[,\n]+/))
        .map((p) => p.trim())
        .filter(Boolean);
}

function namesMatch(patterns, names) {
    return patterns.some((p) => {
        const re = globRegExp(p);
        return names.some((n) => n && re.test(n));
    });
}

export class ConfiguratorViewer {
    /**
     * @param {HTMLElement} container element the canvas fills
     * @param {object} opts { onProgress(0-1), onLoaded(model), onPick(names), onHover(names|null), onDimensions(dims),
     *                        view: camera preset the piece opens on ('angle' by default) }
     */
    constructor(container, opts = {}) {
        this.container = container;
        this.opts = opts;
        this.materials = {};
        this.models = {};
        this.textureCache = new Map();
        this.originals = new WeakMap();
        this.materialCache = new Map();
        this.animations = [];
        this.fades = new Set();
        this.dirty = true;
        this.stretch = { current: 0, target: 0 };
        this.look = null;
        this.modelDef = null;
        this.showDims = false;
        this.loadToken = 0;
        this.clock = performance.now();

        const renderer = new WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.outputColorSpace = SRGBColorSpace;
        renderer.toneMapping = NeutralToneMapping ?? ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.0;
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = PCFShadowMap;
        renderer.setClearColor(0x000000, 0);
        container.appendChild(renderer.domElement);
        renderer.domElement.classList.add('ig-canvas-el');
        this.renderer = renderer;

        this.scene = new Scene();
        const pmrem = new PMREMGenerator(renderer);
        this.scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.035).texture;
        this.scene.environmentIntensity = 0.8;
        pmrem.dispose();

        this.camera = new PerspectiveCamera(LENS.default, 1, 0.01, 100);
        this.camera.position.set(2.2, 1.2, 3.4);

        const controls = new OrbitControls(this.camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.08;
        controls.enablePan = false;
        controls.rotateSpeed = 0.7;
        controls.zoomSpeed = 0.8;
        controls.maxPolarAngle = MathUtils.degToRad(86);
        controls.autoRotateSpeed = 0.6;
        controls.addEventListener('start', () => {
            this.stopCameraTween();
            this.userInteracting = true;
            this.setAutoRotate(false, true);
        });
        controls.addEventListener('end', () => {
            this.userInteracting = false;
        });
        controls.addEventListener('change', () => {
            this.dirty = true;
        });
        this.controls = controls;

        // Lighting: image-based studio + a soft key light that casts the floor shadow
        const hemi = new HemisphereLight(0xffffff, 0xd8cfc2, 0.35);
        this.scene.add(hemi);
        const key = new DirectionalLight(0xffffff, 1.5);
        key.position.set(2.5, 4.5, 3.2);
        key.castShadow = true;
        key.shadow.mapSize.set(2048, 2048);
        key.shadow.radius = 6;
        key.shadow.bias = -0.0004;
        key.shadow.normalBias = 0.02;
        this.scene.add(key);
        this.scene.add(key.target);
        this.keyLight = key;

        // Floor: shadow catcher plus a soft contact blob
        const floor = new Mesh(new PlaneGeometry(30, 30), new ShadowMaterial({ opacity: 0.16 }));
        floor.rotation.x = -Math.PI / 2;
        floor.receiveShadow = true;
        this.scene.add(floor);
        this.floor = floor;
        const blob = new Mesh(
            new PlaneGeometry(1, 1),
            new MeshBasicMaterial({ map: this.makeBlobTexture(), transparent: true, depthWrite: false, opacity: 0.55 })
        );
        blob.rotation.x = -Math.PI / 2;
        blob.position.y = 0.0015;
        blob.renderOrder = -1;
        this.scene.add(blob);
        this.blob = blob;

        this.root = new Group();
        this.scene.add(this.root);
        this.dimGroup = new Group();
        this.dimGroup.visible = false;
        this.scene.add(this.dimGroup);
        this.dimLabels = opts.dimLayer || null;
        this.hotspotLayer = opts.hotspotLayer || null;
        this.showHotspots = false;

        this.loader = new GLTFLoader();
        this.textureLoader = new TextureLoader();
        this.raycaster = new Raycaster();
        this.pointer = new Vector2();

        this.bindPointer();
        this.resizeObserver = new ResizeObserver(() => this.resize());
        this.resizeObserver.observe(container);
        this.resize();
        this.loop = this.loop.bind(this);
        this.raf = requestAnimationFrame(this.loop);
    }

    /* ------------------------------------------------------------------ setup */

    makeBlobTexture() {
        const c = document.createElement('canvas');
        c.width = c.height = 128;
        const g = c.getContext('2d');
        const grd = g.createRadialGradient(64, 64, 0, 64, 64, 64);
        grd.addColorStop(0, 'rgba(0,0,0,0.55)');
        grd.addColorStop(0.45, 'rgba(0,0,0,0.25)');
        grd.addColorStop(1, 'rgba(0,0,0,0)');
        g.fillStyle = grd;
        g.fillRect(0, 0, 128, 128);
        const t = new CanvasTexture(c);
        t.colorSpace = SRGBColorSpace;
        return t;
    }

    setLibrary(materials = {}, models = {}, defaultModel = null) {
        this.materials = materials;
        this.models = models;
        this.defaultModel = defaultModel;
    }

    resize() {
        const w = this.container.clientWidth || 1;
        const h = this.container.clientHeight || 1;
        this.renderer.setSize(w, h, false);
        this.camera.aspect = w / h;
        this.camera.updateProjectionMatrix();
        this.dirty = true;
    }

    bindPointer() {
        const el = this.renderer.domElement;
        let down = null;
        el.addEventListener('pointerdown', (e) => {
            down = { x: e.clientX, y: e.clientY };
        });
        el.addEventListener('pointerup', (e) => {
            if (!down) return;
            const moved = Math.hypot(e.clientX - down.x, e.clientY - down.y);
            down = null;
            if (moved > 6) return;
            const hit = this.pick(e);
            if (hit && this.opts.onPick) this.opts.onPick(hit.names, hit.mesh);
        });
        let pending = false;
        el.addEventListener('pointermove', (e) => {
            if (e.pointerType !== 'mouse' || this.userInteracting || pending) return;
            pending = true;
            requestAnimationFrame(() => {
                pending = false;
                const hit = this.pick(e);
                this.setHover(hit ? hit.mesh : null);
            });
        });
        el.addEventListener('pointerleave', () => this.setHover(null));
    }

    pick(e) {
        const rect = this.renderer.domElement.getBoundingClientRect();
        this.pointer.set(((e.clientX - rect.left) / rect.width) * 2 - 1, -((e.clientY - rect.top) / rect.height) * 2 + 1);
        this.raycaster.setFromCamera(this.pointer, this.camera);
        const meshes = [];
        this.root.traverse((o) => {
            if (o.isMesh && o.visible && !o.userData.isFadeOverlay && this.isVisibleDeep(o)) meshes.push(o);
        });
        const hit = this.raycaster.intersectObjects(meshes, false)[0];
        return hit ? { mesh: hit.object, names: hit.object.userData.names || [] } : null;
    }

    isVisibleDeep(o) {
        for (let p = o; p; p = p.parent) if (!p.visible) return false;
        return true;
    }

    setHover(mesh) {
        if (this.hovered === mesh) return;
        if (this.hovered && this.hovered.material && this.hovered.material.emissive) {
            this.hovered.material.emissive.setRGB(0, 0, 0);
        }
        this.hovered = mesh;
        this.renderer.domElement.style.cursor = mesh ? 'pointer' : '';
        if (mesh && mesh.material && mesh.material.emissive) {
            mesh.material.emissive.setRGB(0.045, 0.04, 0.035);
        }
        if (this.opts.onHover) this.opts.onHover(mesh ? mesh.userData.names : null);
        this.dirty = true;
    }

    /* ------------------------------------------------------------------ model */

    /**
     * @param {object} def { code, url, baseWidthCm, stretchZone, rigidParts[], uvScale, rotationY }
     */
    async loadModel(def) {
        const token = ++this.loadToken;
        this.opts.onProgress?.(0);
        const gltf = await this.loader.loadAsync(def.url, (e) => {
            if (e.lengthComputable) this.opts.onProgress?.(e.loaded / e.total);
        });
        if (token !== this.loadToken) return false;

        const model = gltf.scene;
        model.rotation.y = MathUtils.degToRad(def.rotationY || 0);
        model.updateMatrixWorld(true);

        model.traverse((o) => {
            if (o.userData && o.userData.hidden) o.visible = false;
            o.userData.baseVisible = o.visible;
            if (!o.isMesh) return;
            o.castShadow = true;
            o.receiveShadow = true;
            o.geometry = o.geometry.clone();
            this.originals.set(o, o.material);
            const names = [o.name, o.material && o.material.name];
            for (let p = o.parent; p && p !== model; p = p.parent) names.push(p.name);
            o.userData.names = names.filter(Boolean);
        });

        // Centre on the floor using the parts visible by default
        const box = this.visibleBox(model);
        const centre = box.getCenter(new Vector3());
        const holder = new Group();
        model.position.set(-centre.x, -box.min.y, -centre.z);
        holder.add(model);
        holder.updateMatrixWorld(true);

        // Swap into the scene
        this.disposeModel();
        this.root.add(holder);
        this.model = holder;
        this.modelDef = def;
        this.stretch = { current: 0, target: 0 };
        this.prepareStretch(def);
        this.baseBox = this.visibleBox(holder);
        this.cmPerUnit = def.baseWidthCm && this.baseBox.max.x > this.baseBox.min.x
            ? def.baseWidthCm / (this.baseBox.max.x - this.baseBox.min.x)
            : 100;
        this.appliedLook = new WeakMap();
        this.repeat = null;
        this.compose = null;
        this.templates = new Map();
        this.updateStage(true);
        this.opts.onProgress?.(1);
        this.opts.onLoaded?.(def);
        this.dirty = true;
        return true;
    }

    disposeModel() {
        if (!this.model) return;
        this.root.remove(this.model);
        this.model.traverse((o) => {
            if (o.isMesh) o.geometry.dispose();
        });
        this.model = null;
    }

    visibleBox(object) {
        object.updateMatrixWorld(true);
        const box = new Box3();
        object.traverse((o) => {
            if (o.isMesh && this.isVisibleDeep(o) && !o.userData.isFadeOverlay) {
                if (!o.geometry.boundingBox) o.geometry.computeBoundingBox();
                box.union(o.geometry.boundingBox.clone().applyMatrix4(o.matrixWorld));
            }
        });
        if (box.isEmpty()) box.set(new Vector3(-0.5, 0, -0.5), new Vector3(0.5, 1, 0.5));
        return box;
    }

    /* --------------------------------------------------------------- stretch */

    /**
     * Precomputes per-vertex data so the width can change in real time:
     * vertices inside the stretch zone scale, vertices outside shift, and rigid parts
     * (legs, feet) move as whole islands so they never deform.
     */
    prepareStretch(def) {
        const rigid = parsePatterns(def.rigidParts);
        const rootInverse = this.model.matrixWorld.clone().invert();
        const box = this.visibleBox(this.model);
        this.stretchInfo = {
            halfWidth: (box.max.x - box.min.x) / 2,
            centre: (box.max.x + box.min.x) / 2,
            zone: Math.min(0.98, Math.max(0.05, def.stretchZone || 0.7)),
            meshes: [],
        };
        this.model.traverse((o) => {
            if (!o.isMesh) return;
            const pos = o.geometry.attributes.position;
            const rel = rootInverse.clone().multiply(o.matrixWorld);
            const inv = rel.clone().invert();
            const e = inv.elements;
            const dir = new Vector3(e[0], e[1], e[2]); // local direction of +X in model space
            const orig = Float32Array.from(pos.array);
            const rootX = new Float32Array(pos.count);
            const v = new Vector3();
            for (let i = 0; i < pos.count; i++) {
                v.fromBufferAttribute(pos, i).applyMatrix4(rel);
                rootX[i] = v.x;
            }
            let anchorX = rootX;
            if (rigid.length && namesMatch(rigid, o.userData.names)) {
                anchorX = this.islandCentres(o.geometry, rootX);
            }
            this.stretchInfo.meshes.push({ mesh: o, pos, orig, anchorX, dir });
        });
    }

    /** Union-find over shared vertices (index buffer + coincident positions) */
    islandCentres(geometry, rootX) {
        const pos = geometry.attributes.position;
        const n = pos.count;
        const parent = new Int32Array(n);
        for (let i = 0; i < n; i++) parent[i] = i;
        const find = (i) => {
            while (parent[i] !== i) {
                parent[i] = parent[parent[i]];
                i = parent[i];
            }
            return i;
        };
        const union = (a, b) => {
            const ra = find(a);
            const rb = find(b);
            if (ra !== rb) parent[ra] = rb;
        };
        const seen = new Map();
        for (let i = 0; i < n; i++) {
            const key = `${Math.round(pos.getX(i) * 1e4)},${Math.round(pos.getY(i) * 1e4)},${Math.round(pos.getZ(i) * 1e4)}`;
            const first = seen.get(key);
            if (first === undefined) seen.set(key, i);
            else union(i, first);
        }
        const index = geometry.index;
        const triCount = index ? index.count : n;
        for (let t = 0; t < triCount; t += 3) {
            const a = index ? index.getX(t) : t;
            const b = index ? index.getX(t + 1) : t + 1;
            const c = index ? index.getX(t + 2) : t + 2;
            union(a, b);
            union(b, c);
        }
        const sum = new Map();
        for (let i = 0; i < n; i++) {
            const r = find(i);
            const s = sum.get(r) || [0, 0];
            s[0] += rootX[i];
            s[1] += 1;
            sum.set(r, s);
        }
        const out = new Float32Array(n);
        for (let i = 0; i < n; i++) {
            const s = sum.get(find(i));
            out[i] = s[0] / s[1];
        }
        return out;
    }

    applyStretch(d) {
        const info = this.stretchInfo;
        if (!info) return;
        const a = info.halfWidth * info.zone;
        const shift = (x) => {
            const rx = x - info.centre;
            if (Math.abs(rx) <= a) return rx * (d / a);
            return Math.sign(rx) * d;
        };
        for (const m of info.meshes) {
            const arr = m.pos.array;
            const { orig, anchorX, dir } = m;
            for (let i = 0, j = 0; i < anchorX.length; i++, j += 3) {
                const dx = shift(anchorX[i]);
                arr[j] = orig[j] + dir.x * dx;
                arr[j + 1] = orig[j + 1] + dir.y * dx;
                arr[j + 2] = orig[j + 2] + dir.z * dx;
            }
            m.pos.needsUpdate = true;
            m.mesh.geometry.computeBoundingSphere();
            m.mesh.geometry.boundingBox = null;
        }
        this.stretch.current = d;
        this.dirty = true;
    }

    /** Width in cm -> half-width delta in model units */
    stretchFor(widthCm) {
        if (!widthCm || !this.modelDef || !this.stretchInfo) return 0;
        const base = this.modelDef.baseWidthCm || widthCm;
        return ((widthCm / base - 1) * this.stretchInfo.halfWidth * 2) / 2;
    }

    /* ------------------------------------------------------------- materials */

    loadTexture(url, srgb, repeat) {
        if (!url) return Promise.resolve(null);
        const key = `${url}|${repeat}`;
        if (!this.textureCache.has(key)) {
            this.textureCache.set(key, this.textureLoader.loadAsync(url).then((t) => {
                t.wrapS = t.wrapT = RepeatWrapping;
                t.repeat.set(repeat, repeat);
                t.anisotropy = Math.min(8, this.renderer.capabilities.getMaxAnisotropy());
                if (srgb) t.colorSpace = SRGBColorSpace;
                return t;
            }).catch(() => null));
        }
        return this.textureCache.get(key);
    }

    async buildMaterial(mesh, look) {
        const original = this.originals.get(mesh) || mesh.material;
        const key = `${original.uuid}|${JSON.stringify(look)}`;
        if (this.materialCache.has(key)) return this.materialCache.get(key);
        const promise = (async () => {
            let mat;
            const def = look.material ? this.materials[look.material] : null;
            if (def) {
                const repeat = (def.repeat || 1) * (this.modelDef?.uvScale || 1);
                const [map, normalMap, roughnessMap] = await Promise.all([
                    this.loadTexture(def.map, true, repeat),
                    this.loadTexture(def.normalMap, false, repeat),
                    this.loadTexture(def.roughnessMap, false, repeat),
                ]);
                mat = new MeshPhysicalMaterial({
                    name: def.code,
                    color: new Color(def.color || '#ffffff'),
                    map,
                    normalMap,
                    roughnessMap,
                    roughness: def.roughness ?? 0.8,
                    metalness: def.metalness ?? 0,
                    sheen: def.sheen ?? 0,
                    sheenRoughness: 0.55,
                    sheenColor: new Color(def.sheenColor || '#ffffff'),
                    clearcoat: def.clearcoat ?? 0,
                    clearcoatRoughness: 0.08,
                });
                if (normalMap) mat.normalScale.set(def.normalScale ?? 1, def.normalScale ?? 1);
            } else {
                mat = original.clone();
            }
            if (look.color) {
                // The library colour acts as dye depth: option colours are multiplied by it
                mat.color = new Color(look.color);
                if (def && def.color) mat.color.multiply(new Color(def.color));
                // Velvet sheen picks up the dye colour; only a hint of white keeps the pile from looking chalky
                if (def && def.sheen) mat.sheenColor = new Color(look.color).lerp(new Color('#ffffff'), 0.08);
            }
            for (const [prop, value] of Object.entries(look.props || {})) {
                if (prop in mat && typeof mat[prop] === 'number') mat[prop] = value;
            }
            if (mat.emissive) mat.emissive.setRGB(0, 0, 0);
            mat.userData.lookKey = key;
            return mat;
        })();
        this.materialCache.set(key, promise);
        return promise;
    }

    crossfade(mesh, material, animate) {
        if (mesh.material === material) return;
        if (mesh.userData.fade) mesh.userData.fade.finish();
        if (this.hovered === mesh) this.setHover(null);
        if (!animate || !this.isVisibleDeep(mesh)) {
            mesh.material = material;
            this.dirty = true;
            return;
        }
        const overlayMat = material.clone();
        overlayMat.transparent = true;
        overlayMat.opacity = 0;
        overlayMat.depthFunc = LessEqualDepth;
        overlayMat.depthWrite = false;
        const overlay = new Mesh(mesh.geometry, overlayMat);
        overlay.userData.isFadeOverlay = true;
        overlay.renderOrder = 2;
        mesh.add(overlay);
        const fade = {
            finish: () => {
                mesh.remove(overlay);
                overlayMat.dispose();
                mesh.material = material;
                mesh.userData.fade = null;
                this.dirty = true;
            },
        };
        mesh.userData.fade = fade;
        this.animate(520, (t) => {
            overlayMat.opacity = easeOut(t);
        }, () => {
            if (mesh.userData.fade === fade) fade.finish();
        });
    }

    /* ----------------------------------------------------------------- apply */

    /**
     * @param {object} look {
     *   model: code|null, widthCm: number|null,
     *   parts: [{ target, material, color, props }],   // in step order, later wins
     *   visibility: [{ target }],                         // "!name" hides
     *   repeat: { target, count },                        // one module side by side
     *   compose: [{ target, mirror, step }]               // elements left to right
     * }
     */
    async apply(look, { animate = true } = {}) {
        this.look = look;
        let fresh = false;
        const wanted = look.model && this.models[look.model] ? this.models[look.model] : this.defaultModel;
        if (wanted && (!this.modelDef || wanted.code !== this.modelDef.code)) {
            const ok = await this.loadModel(wanted);
            if (!ok || this.look !== look) return;
            animate = false;
            fresh = true;
        }
        if (!this.model) return;

        // Modules first (repeated seats, composed elements), so new copies get visibility and materials below
        if (look.repeat && look.repeat.target) this.applyRepeat(look.repeat, animate);
        if (look.compose) this.applyCompose(look.compose, animate);

        // Visibility: reset to the model defaults, then apply in order
        this.model.traverse((o) => {
            if (o !== this.model && o.userData.baseVisible !== undefined) o.visible = o.userData.baseVisible;
        });
        for (const rule of look.visibility || []) {
            for (const pattern of parsePatterns(rule.target)) {
                const hide = pattern.startsWith('!');
                const re = globRegExp(hide ? pattern.slice(1) : pattern);
                this.model.traverse((o) => {
                    if (o.isMesh && o.userData.isFadeOverlay) return;
                    if (o.name && re.test(o.name)) o.visible = !hide;
                });
            }
        }

        // Appearance per mesh
        const jobs = [];
        this.model.traverse((mesh) => {
            if (!mesh.isMesh || mesh.userData.isFadeOverlay) return;
            const meshLook = {};
            for (const part of look.parts || []) {
                if (!namesMatch(parsePatterns(part.target), mesh.userData.names)) continue;
                if (part.material) {
                    meshLook.material = part.material;
                    delete meshLook.color;
                }
                if (part.color) meshLook.color = part.color;
                if (part.props) meshLook.props = { ...(meshLook.props || {}), ...part.props };
            }
            const key = JSON.stringify(meshLook);
            if (this.appliedLook.get(mesh) === key) return;
            this.appliedLook.set(mesh, key);
            // Apply when ready unless a newer choice for this mesh came in meanwhile (fast clicking)
            jobs.push(this.buildMaterial(mesh, meshLook).then((mat) => {
                if (this.appliedLook.get(mesh) === key) this.crossfade(mesh, mat, animate);
            }));
        });

        // Width
        const target = this.stretchFor(look.widthCm);
        if (Math.abs(target - this.stretch.current) > 1e-5) {
            const from = this.stretch.current;
            this.stretch.target = target;
            if (animate) {
                this.animate(750, (t) => this.applyStretch(from + (target - from) * easeInOut(t)), () => this.updateStage());
            } else {
                this.applyStretch(target);
            }
        }
        await Promise.all(jobs);
        // A freshly loaded model is framed on what the look built (a composed row is empty at load)
        this.updateStage(fresh);
        this.dirty = true;
    }

    /* --------------------------------------------------------------- modules */

    /**
     * Finds the module node to repeat and the siblings that must slide outwards (arms, ends).
     */
    setupRepeat(pattern) {
        const re = globRegExp(pattern);
        let template = null;
        this.model.traverse((o) => {
            if (!template && o.name && re.test(o.name) && !o.userData.isRepeatClone) template = o;
        });
        if (!template) return null;
        this.model.updateMatrixWorld(true);
        const box = new Box3().setFromObject(template);
        const centre = (box.min.x + box.max.x) / 2;
        const others = template.parent.children
            .filter((c) => c !== template && !c.userData.isRepeatClone)
            .map((c) => {
                const b = new Box3().setFromObject(c);
                const cx = b.isEmpty() ? c.position.x : (b.min.x + b.max.x) / 2;
                return { node: c, baseX: c.position.x, side: Math.sign(cx - centre) };
            });
        return { pattern, template, parent: template.parent, width: box.max.x - box.min.x, baseX: template.position.x, others, modules: [template], count: 1, anim: null };
    }

    cloneModule(template) {
        template.traverse((o) => o.userData.fade && o.userData.fade.finish());
        const clone = template.clone(true);
        clone.userData.isRepeatClone = true;
        const src = [];
        const dst = [];
        template.traverse((o) => src.push(o));
        clone.traverse((o) => dst.push(o));
        src.forEach((o, i) => {
            if (o.isMesh) this.originals.set(dst[i], this.originals.get(o) || o.material);
        });
        return clone;
    }

    /**
     * Lays out `count` copies of the module side by side; new seats grow in, removed ones shrink away
     * and the arms slide to the new ends.
     */
    applyRepeat(spec, animate) {
        if (!this.repeat || this.repeat.pattern !== spec.target) this.repeat = this.setupRepeat(spec.target);
        const r = this.repeat;
        if (!r) return false;
        const count = Math.max(1, Math.min(12, Math.round(spec.count || 1)));
        if (count === r.count) return false;
        if (r.anim) {
            this.animations = this.animations.filter((a) => a !== r.anim);
            r.anim.done();
        }
        const fromModules = r.modules.map((m) => m.position.x);
        const fromOthers = r.others.map((o) => o.node.position.x);
        const added = [];
        while (r.modules.length < count) {
            const clone = this.cloneModule(r.template);
            r.parent.add(clone);
            r.modules.push(clone);
            added.push(clone);
        }
        const removed = r.modules.splice(count);
        r.count = count;
        const targetX = (i) => r.baseX + (i - (count - 1) / 2) * r.width;
        const half = ((count - 1) * r.width) / 2;
        const othersTarget = r.others.map((o) => o.baseX + o.side * half);
        added.forEach((m) => {
            m.position.x = targetX(r.modules.indexOf(m));
            m.scale.setScalar(animate ? 0.001 : 1);
        });
        const start = r.modules.map((m, i) => (i < fromModules.length ? fromModules[i] : m.position.x));
        const step = (t) => {
            const e = easeInOut(t);
            r.modules.forEach((m, i) => { m.position.x = start[i] + (targetX(i) - start[i]) * e; });
            r.others.forEach((o, j) => { o.node.position.x = fromOthers[j] + (othersTarget[j] - fromOthers[j]) * e; });
            added.forEach((m) => m.scale.setScalar(Math.max(0.001, easeOut(t))));
            removed.forEach((m) => m.scale.setScalar(Math.max(0.001, 1 - easeOut(Math.min(1, t * 1.6)))));
        };
        const done = () => {
            step(1);
            removed.forEach((m) => m.parent && m.parent.remove(m));
            r.anim = null;
            this.updateStage();
        };
        if (animate) {
            r.anim = this.animate(700, step, done);
        } else {
            done();
        }
        return true;
    }

    /** Element template (node authored hidden in the GLB) by name or glob, never one of our copies */
    findTemplate(pattern) {
        if (!pattern || !this.model) return null;
        if (!this.templates.has(pattern)) {
            const re = globRegExp(pattern);
            let found = null;
            this.model.traverse((o) => {
                if (!found && o.name && re.test(o.name) && !o.isMesh && !o.userData.isRepeatClone) found = o;
            });
            this.templates.set(pattern, found);
        }
        return this.templates.get(pattern);
    }

    /** Width an element takes in the row: the GLB's extras.moduleWidth, else its bounding box */
    moduleWidth(template) {
        if (template.userData.moduleWidth) return template.userData.moduleWidth;
        const box = new Box3().setFromObject(template);
        return box.isEmpty() ? 1 : box.max.x - box.min.x;
    }

    /** Lets a click on an element open the step that chose it (names carry "step:<option id>") */
    tagStep(node, step) {
        node.traverse((o) => {
            if (!o.isMesh) return;
            const names = (o.userData.names || []).filter((n) => !String(n).startsWith('step:'));
            if (step !== null && step !== undefined) names.unshift(`step:${step}`);
            o.userData.names = names;
        });
    }

    /**
     * Builds a row of different elements (sectional sofa): each entry clones its template, right-hand ends
     * are mirrored, and the row is laid out edge to edge and centred. Elements that stay slide to their new
     * place, new ones grow in, removed ones shrink away.
     * @param {Array<{target: string, mirror: boolean, step: (number|null)}>} list left to right
     */
    applyCompose(list, animate) {
        const entries = list
            .map((e) => ({ ...e, template: this.findTemplate(e.target) }))
            .filter((e) => e.template);
        if (!this.compose) {
            if (!entries.length) return false;
            const row = new Group();
            row.name = 'Composition';
            row.userData.baseVisible = true;
            entries[0].template.parent.add(row);
            this.compose = { row, items: [], signature: '', anim: null };
        }
        const c = this.compose;
        const keyOf = (e) => `${e.template.uuid}|${e.mirror ? 1 : 0}`;
        const signature = entries.map((e) => `${keyOf(e)}|${e.step ?? ''}`).join(',');
        if (signature === c.signature) return false;
        c.signature = signature;
        if (c.anim) {
            this.animations = this.animations.filter((a) => a !== c.anim);
            c.anim.done();
        }

        // Keep the elements that are still wanted, in order; everything else is new or goes
        const old = c.items;
        const kept = new Set();
        let cursor = 0;
        const items = entries.map((e) => {
            const key = keyOf(e);
            for (let i = cursor; i < old.length; i++) {
                if (!kept.has(i) && old[i].key === key) {
                    kept.add(i);
                    cursor = i + 1;
                    this.tagStep(old[i].node, e.step);
                    return { ...old[i], added: false };
                }
            }
            const node = this.cloneModule(e.template);
            node.visible = true;
            node.userData.baseVisible = true;
            node.position.set(0, e.template.position.y, e.template.position.z);
            this.tagStep(node, e.step);
            c.row.add(node);
            return { key, node, sign: e.mirror ? -1 : 1, width: this.moduleWidth(e.template), added: true };
        });
        const removed = old.filter((_, i) => !kept.has(i));
        c.items = items;

        const total = items.reduce((sum, it) => sum + it.width, 0);
        let x = -total / 2;
        const targetX = items.map((it) => {
            const t = x + it.width / 2;
            x += it.width;
            return t;
        });
        const fromX = items.map((it, i) => (it.added ? targetX[i] : it.node.position.x));
        const setScale = (it, s) => it.node.scale.set(it.sign * s, s, s);
        items.forEach((it) => it.added && setScale(it, animate ? 0.001 : 1));
        const step = (t) => {
            const e = easeInOut(t);
            items.forEach((it, i) => {
                it.node.position.x = fromX[i] + (targetX[i] - fromX[i]) * e;
                if (it.added) setScale(it, Math.max(0.001, easeOut(t)));
            });
            removed.forEach((it) => setScale(it, Math.max(0.001, 1 - easeOut(Math.min(1, t * 1.6)))));
        };
        const done = () => {
            step(1);
            removed.forEach((it) => it.node.parent && it.node.parent.remove(it.node));
            c.anim = null;
            this.updateStage();
        };
        if (animate) {
            // Frame the finished row straight away, so the camera and the elements move together
            step(1);
            removed.forEach((it) => { it.node.visible = false; });
            c.finalBox = this.visibleBox(this.model);
            removed.forEach((it) => { it.node.visible = true; });
            step(0);
            c.anim = this.animate(700, step, done);
            this.updateStage();
        } else {
            done();
        }
        return true;
    }

    /**
     * Renders a preview of each element template in its current materials, for the option tiles.
     * Draws into a corner of the main canvas and copies it out, then redraws the scene in the same task,
     * so nothing flashes on screen.
     * @param {Array<{target: string, mirror: boolean}>} list
     * @returns {Object<string, string>} "target|0" / "target|1" (mirrored) => PNG data URL
     */
    renderThumbnails(list, width = 300, height = 225) {
        const out = {};
        if (!this.model || !list.length) return out;
        const renderer = this.renderer;
        const canvas = renderer.domElement;
        const pr = renderer.getPixelRatio();
        const cssW = this.container.clientWidth || 1;
        const cssH = this.container.clientHeight || 1;
        let vw = Math.min(cssW, 420);
        let vh = (vw * height) / width;
        if (vh > cssH) {
            vh = cssH;
            vw = (vh * width) / height;
        }
        const holder = new Group();
        this.scene.add(holder);
        const hidden = [this.root, this.blob, this.dimGroup].map((o) => [o, o.visible]);
        hidden.forEach(([o]) => { o.visible = false; });
        const cam = new PerspectiveCamera(22, vw / vh, 0.01, 60);
        renderer.setScissorTest(true);
        renderer.setViewport(0, 0, vw, vh);
        renderer.setScissor(0, 0, vw, vh);
        const bw = vw * pr;
        const bh = vh * pr;
        for (const entry of list) {
            const template = this.findTemplate(entry.target);
            if (!template) continue;
            const node = this.cloneModule(template);
            node.visible = true;
            node.position.set(0, 0, 0);
            node.scale.set(entry.mirror ? -1 : 1, 1, 1);
            holder.add(node);
            const box = this.visibleBox(holder);
            const centre = box.getCenter(new Vector3());
            const radius = box.getSize(new Vector3()).length() / 2;
            const dir = new Vector3(entry.mirror ? 0.62 : -0.62, 0.5, 1).normalize();
            cam.position.copy(centre).addScaledVector(dir, radius / Math.sin(MathUtils.degToRad(cam.fov / 2)));
            cam.lookAt(centre);
            cam.updateMatrixWorld();
            renderer.clear();
            renderer.render(this.scene, cam);

            // Crop to the element (projected box corners), keeping the tile's aspect ratio
            let x0 = Infinity; let x1 = -Infinity; let y0 = Infinity; let y1 = -Infinity;
            for (const x of [box.min.x, box.max.x]) for (const y of [box.min.y, box.max.y]) for (const z of [box.min.z, box.max.z]) {
                const p = new Vector3(x, y, z).project(cam);
                x0 = Math.min(x0, ((p.x + 1) / 2) * bw); x1 = Math.max(x1, ((p.x + 1) / 2) * bw);
                y0 = Math.min(y0, ((1 - p.y) / 2) * bh); y1 = Math.max(y1, ((1 - p.y) / 2) * bh);
            }
            let cw = (x1 - x0) * 1.1;
            let ch = (y1 - y0) * 1.16;
            if (cw / ch > width / height) ch = (cw * height) / width;
            else cw = (ch * width) / height;
            const sx = (x0 + x1) / 2 - cw / 2;
            const sy = (y0 + y1) / 2 - ch / 2 + (canvas.height - bh);
            const tile = document.createElement('canvas');
            tile.width = width;
            tile.height = height;
            tile.getContext('2d').drawImage(canvas, sx, sy, cw, ch, 0, 0, width, height);
            out[`${entry.target}|${entry.mirror ? 1 : 0}`] = tile.toDataURL('image/png');
            holder.remove(node);
        }
        this.scene.remove(holder);
        hidden.forEach(([o, v]) => { o.visible = v; });
        renderer.setScissorTest(false);
        renderer.setViewport(0, 0, cssW, cssH);
        renderer.render(this.scene, this.camera);
        this.dirty = true;
        return out;
    }

    setHotspotsVisible(on) {
        this.showHotspots = on;
        if (this.hotspotLayer) this.hotspotLayer.style.display = on ? '' : 'none';
        this.dirty = true;
    }

    /** Keeps the "+" buttons above both ends of the piece (inside the stage), even while it grows */
    positionHotspots() {
        if (!this.showHotspots || !this.hotspotLayer || !this.model) return;
        const box = this.animations.length ? this.visibleBox(this.model) : this.currentBox;
        if (!box) return;
        const w = this.container.clientWidth;
        const h = this.container.clientHeight;
        const y = box.max.y + 0.12;
        const z = box.min.z + 0.1;
        const anchors = { left: new Vector3(box.min.x + 0.12, y, z), right: new Vector3(box.max.x - 0.12, y, z) };
        for (const [side, anchor] of Object.entries(anchors)) {
            const el = this.hotspotLayer.querySelector(`[data-hotspot="${side}"]`);
            if (!el) continue;
            const p = anchor.project(this.camera);
            const half = (el.offsetWidth || 120) / 2 + 12;
            const x = Math.min(w - half, Math.max(half, ((p.x + 1) / 2) * w));
            el.style.visibility = p.z > 1 ? 'hidden' : '';
            el.style.transform = `translate(-50%, -50%) translate(${x}px, ${((1 - p.y) / 2) * h}px)`;
        }
    }

    /* ----------------------------------------------------------------- stage */

    updateStage(fitCamera = false) {
        if (!this.model) return;
        // While composed elements move, stage for where they end up
        const box = this.compose && this.compose.anim ? this.compose.finalBox.clone() : this.visibleBox(this.model);
        const size = box.getSize(new Vector3());
        this.currentBox = box;
        this.blob.scale.set(size.x * 1.25 + 0.15, size.z * 1.35 + 0.15, 1);
        this.blob.position.x = (box.min.x + box.max.x) / 2;
        this.blob.position.z = (box.min.z + box.max.z) / 2;

        const r = Math.max(size.x, size.z) * 0.8 + 0.5;
        const cam = this.keyLight.shadow.camera;
        cam.left = cam.bottom = -r;
        cam.right = cam.top = r;
        cam.near = 0.1;
        cam.far = 15;
        cam.updateProjectionMatrix();

        this.controls.minDistance = Math.max(size.x, size.y, size.z) * 0.6;
        this.controls.maxDistance = Math.max(size.x, size.y, size.z) * 5 + 2;
        if (fitCamera) {
            this.setView(this.opts.view || 'angle', false);
            this.fitSize = size.clone();
        } else if (this.fitSize) {
            // Reframe when the footprint changes noticeably (wider sofa, oval vs round top)
            const grew = Math.max(Math.abs(size.x - this.fitSize.x) / this.fitSize.x, Math.abs(size.z - this.fitSize.z) / this.fitSize.z);
            if (grew > 0.04) {
                this.fitSize = size.clone();
                this.setView(this.currentView || 'angle', true);
            }
        }
        this.buildDimensions(box);
        this.opts.onDimensions?.(this.getDimensions());
        this.dirty = true;
    }

    getDimensions() {
        if (!this.currentBox) return null;
        const s = this.currentBox.getSize(new Vector3());
        const k = this.cmPerUnit || 100;
        return { width: Math.round(s.x * k), depth: Math.round(s.z * k), height: Math.round(s.y * k) };
    }

    buildDimensions(box) {
        this.dimGroup.clear();
        const pad = 0.08;
        const tick = 0.035;
        const y0 = 0.004;
        const pts = [];
        const seg = (a, b) => pts.push(...a, ...b);
        // Width along the front edge
        const zf = box.max.z + pad;
        seg([box.min.x, y0, zf], [box.max.x, y0, zf]);
        seg([box.min.x, y0, zf - tick], [box.min.x, y0, zf + tick]);
        seg([box.max.x, y0, zf - tick], [box.max.x, y0, zf + tick]);
        // Depth along the right side
        const xr = box.max.x + pad;
        seg([xr, y0, box.min.z], [xr, y0, box.max.z]);
        seg([xr - tick, y0, box.min.z], [xr + tick, y0, box.min.z]);
        seg([xr - tick, y0, box.max.z], [xr + tick, y0, box.max.z]);
        // Height at the back right corner
        seg([xr, 0, box.min.z], [xr, box.max.y, box.min.z]);
        seg([xr - tick, box.max.y, box.min.z], [xr + tick, box.max.y, box.min.z]);
        const g = new BufferGeometry();
        g.setAttribute('position', new Float32BufferAttribute(pts, 3));
        const lines = new LineSegments(g, new LineBasicMaterial({ color: 0x8c7a62, transparent: true, opacity: 0.9 }));
        this.dimGroup.add(lines);
        this.dimAnchors = {
            width: new Vector3((box.min.x + box.max.x) / 2, y0, zf),
            depth: new Vector3(xr, y0, (box.min.z + box.max.z) / 2),
            height: new Vector3(xr, box.max.y / 2, box.min.z),
        };
    }

    setDimensionsVisible(on) {
        this.showDims = on;
        this.dimGroup.visible = on;
        if (this.dimLabels) this.dimLabels.style.display = on ? '' : 'none';
        this.dirty = true;
    }

    positionDimLabels() {
        if (!this.showDims || !this.dimLabels || !this.dimAnchors) return;
        const dims = this.getDimensions();
        const w = this.container.clientWidth;
        const h = this.container.clientHeight;
        for (const key of ['width', 'depth', 'height']) {
            const el = this.dimLabels.querySelector(`[data-dim="${key}"]`);
            if (!el) continue;
            const p = this.dimAnchors[key].clone().project(this.camera);
            el.style.transform = `translate(-50%, -50%) translate(${((p.x + 1) / 2) * w}px, ${((1 - p.y) / 2) * h}px)`;
            el.textContent = `${dims[key]} cm`;
        }
    }

    /* ---------------------------------------------------------------- camera */

    setView(name, animate = true) {
        if (!this.currentBox) return;
        this.currentView = name;
        const box = this.currentBox;
        const size = box.getSize(new Vector3());
        const maxDim = Math.max(size.x, size.y * 1.4, size.z);
        const fov = MathUtils.degToRad(LENS.default);
        const aspect = Math.max(this.camera.aspect, 0.6);
        const dist = (maxDim / 2 / Math.tan(fov / 2)) * (aspect < 1 ? 1.02 / aspect : 1.18);
        // Catalogue front view: fill the width (or the height for tall pieces), seen from slightly above
        const frontFov = MathUtils.degToRad(LENS.front);
        const frontDist = Math.max(
            (size.x * 1.03) / (2 * Math.tan(frontFov / 2) * aspect),
            (size.y * 2.1) / (2 * Math.tan(frontFov / 2))
        ) + size.z * 0.4;
        const cy = size.y * 0.45;
        // Views look at the footprint centre (a chaise longue pushes it forward)
        const cx = (box.min.x + box.max.x) / 2;
        const cz = (box.min.z + box.max.z) / 2;
        const at = (x, y, z) => new Vector3(cx + x, y, cz + z);
        const views = {
            front: [at(0, size.y * 0.5 + frontDist * 0.16, frontDist), at(0, size.y * 0.32, 0)],
            angle: [at(dist * 0.62, size.y * 0.75 + dist * 0.18, dist * 0.8), at(0, cy, 0)],
            side: [at(dist * 0.95, size.y * 0.6, 0.0001), at(0, cy, 0)],
            top: [at(0, dist * 1.05, 0.0001), at(0, 0, 0)],
            detail: [
                new Vector3(box.max.x + size.x * 0.12, size.y * 0.35, box.max.z + Math.max(size.z, 0.6) * 0.9),
                new Vector3(box.max.x - size.x * 0.12, size.y * 0.25, box.max.z - size.z * 0.25),
            ],
        };
        const [pos, target] = views[name] || views.angle;
        const lens = LENS[name] || LENS.default;
        const setLens = (value) => {
            this.camera.fov = value;
            this.camera.updateProjectionMatrix();
        };
        if (!animate) {
            setLens(lens);
            this.camera.position.copy(pos);
            this.controls.target.copy(target);
            this.controls.update();
            this.dirty = true;
            return;
        }
        this.stopCameraTween();
        const fromPos = this.camera.position.clone();
        const fromTarget = this.controls.target.clone();
        const fromLens = this.camera.fov;
        this.cameraTween = this.animate(950, (t) => {
            const k = easeInOut(t);
            setLens(fromLens + (lens - fromLens) * k);
            this.camera.position.lerpVectors(fromPos, pos, k);
            this.controls.target.lerpVectors(fromTarget, target, k);
        });
    }

    stopCameraTween() {
        if (this.cameraTween) {
            this.animations = this.animations.filter((a) => a !== this.cameraTween);
            this.cameraTween = null;
        }
    }

    setAutoRotate(on, fromUser = false) {
        this.controls.autoRotate = on;
        if (fromUser && this.opts.onAutoRotate) this.opts.onAutoRotate(on);
        this.dirty = true;
    }

    /* ------------------------------------------------------------- snapshot */

    /**
     * Renders the current view onto the backdrop colour. With crop, frames the piece (4:3) instead of
     * returning the whole stage, which is what emails and admin thumbnails want.
     */
    snapshot(background = '#f3efe9', width = 1600, crop = false) {
        this.finishTransitions();
        const canvas = this.renderer.domElement;
        const showDims = this.dimGroup.visible;
        this.dimGroup.visible = false;
        this.renderer.render(this.scene, this.camera);
        let sx = 0;
        let sy = 0;
        let sw = canvas.width;
        let sh = canvas.height;
        if (crop && this.currentBox) {
            const b = this.currentBox;
            const xs = [];
            const ys = [];
            for (const x of [b.min.x, b.max.x]) for (const y of [b.min.y, b.max.y]) for (const z of [b.min.z, b.max.z]) {
                const p = new Vector3(x, y, z).project(this.camera);
                xs.push(((p.x + 1) / 2) * canvas.width);
                ys.push(((1 - p.y) / 2) * canvas.height);
            }
            let x0 = Math.min(...xs);
            let x1 = Math.max(...xs);
            let y0 = Math.min(...ys);
            let y1 = Math.max(...ys);
            const padX = (x1 - x0) * 0.1;
            const padY = (y1 - y0) * 0.14;
            x0 -= padX; x1 += padX; y0 -= padY; y1 += padY * 1.4;
            let cw = x1 - x0;
            let ch = y1 - y0;
            if (cw / ch > 4 / 3) {
                const nh = cw * 3 / 4;
                y0 -= (nh - ch) / 2;
                ch = nh;
            } else {
                const nw = ch * 4 / 3;
                x0 -= (nw - cw) / 2;
                cw = nw;
            }
            sx = Math.max(0, x0);
            sy = Math.max(0, y0);
            sw = Math.min(canvas.width - sx, cw);
            sh = Math.min(canvas.height - sy, ch);
        }
        const out = document.createElement('canvas');
        out.width = width;
        out.height = Math.round((width * sh) / sw);
        const g = out.getContext('2d');
        const grd = g.createLinearGradient(0, 0, 0, out.height);
        const bg = Array.isArray(background) ? background : [background, background];
        grd.addColorStop(0, bg[0]);
        grd.addColorStop(1, bg[1]);
        g.fillStyle = grd;
        g.fillRect(0, 0, out.width, out.height);
        g.drawImage(canvas, sx, sy, sw, sh, 0, 0, out.width, out.height);
        this.dimGroup.visible = showDims;
        this.dirty = true;
        return out;
    }

    /* ------------------------------------------------------------ animation */

    /** Jump every running fade/move to its end state (used before snapshots) */
    finishTransitions() {
        while (this.animations.length) {
            const running = this.animations;
            this.animations = [];
            for (const a of running) {
                a.step(1);
                if (a === this.cameraTween) this.cameraTween = null;
                a.done?.();
            }
        }
    }

    animate(duration, step, done) {
        const anim = { start: performance.now(), duration, step, done };
        this.animations.push(anim);
        this.dirty = true;
        return anim;
    }

    loop(now) {
        this.raf = requestAnimationFrame(this.loop);
        const dt = Math.min(0.1, (now - this.clock) / 1000);
        this.clock = now;
        if (this.animations.length) {
            const running = this.animations;
            this.animations = [];
            for (const a of running) {
                const t = Math.min(1, (now - a.start) / a.duration);
                a.step(t);
                if (t < 1) this.animations.push(a);
                else {
                    if (a === this.cameraTween) this.cameraTween = null;
                    a.done?.();
                }
            }
            this.dirty = true;
        }
        const changed = this.controls.update(dt);
        if (changed || this.dirty || this.controls.autoRotate) {
            this.dirty = false;
            this.renderer.render(this.scene, this.camera);
            this.positionDimLabels();
            this.positionHotspots();
        }
    }

    dispose() {
        cancelAnimationFrame(this.raf);
        this.resizeObserver.disconnect();
        this.controls.dispose();
        this.disposeModel();
        this.renderer.dispose();
        this.renderer.domElement.remove();
    }
}

export default ConfiguratorViewer;
