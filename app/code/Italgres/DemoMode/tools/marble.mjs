// Procedural porcelain-slab (marble look) albedo generator. Writes raw RGB; PIL encodes it.
import { writeFileSync } from 'node:fs';

const N = 2048;
function hash(x, y, s) {
  let h = (x * 374761393 + y * 668265263 + s * 2147483647) | 0;
  h = Math.imul(h ^ (h >>> 13), 1274126177);
  return ((h ^ (h >>> 16)) >>> 0) / 4294967295;
}
const fade = (t) => t * t * t * (t * (t * 6 - 15) + 10);
function vnoise(x, y, s) {
  const xi = Math.floor(x), yi = Math.floor(y), xf = x - xi, yf = y - yi;
  const u = fade(xf), v = fade(yf);
  const a = hash(xi, yi, s), b = hash(xi + 1, yi, s), c = hash(xi, yi + 1, s), d = hash(xi + 1, yi + 1, s);
  return (a + (b - a) * u) + ((c + (d - c) * u) - (a + (b - a) * u)) * v;
}
function fbm(x, y, s, oct = 5) {
  let f = 0, amp = 0.5, fr = 1;
  for (let i = 0; i < oct; i++) { f += amp * vnoise(x * fr, y * fr, s + i * 17); fr *= 2.03; amp *= 0.5; }
  return f;
}
const hex = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
const mix = (a, b, t) => a.map((v, i) => v + (b[i] - v) * t);

function slab(p) {
  const out = Buffer.alloc(N * N * 3);
  const base1 = hex(p.base[0]), base2 = hex(p.base[1]), vein = hex(p.vein), fine = hex(p.fine);
  for (let j = 0; j < N; j++) {
    for (let i = 0; i < N; i++) {
      const x = i / N * 4, y = j / N * 4;
      // Domain warp for organic flow
      const wx = fbm(x * 0.6, y * 0.6, p.seed) * 2 - 1, wy = fbm(x * 0.6 + 7.3, y * 0.6 - 3.1, p.seed + 5) * 2 - 1;
      const cloud = fbm(x * 1.4 + wx, y * 1.4 + wy, p.seed + 11, 6);
      let c = mix(base1, base2, Math.min(1, Math.max(0, (cloud - 0.3) * 1.6)));
      // Main veins
      const t = (x * Math.cos(p.angle) + y * Math.sin(p.angle)) * p.freq + fbm(x + wx * 1.5, y + wy * 1.5, p.seed + 23, 6) * p.warp;
      let v = Math.pow(1 - Math.abs(Math.sin(Math.PI * t)), p.sharp);
      v *= Math.min(1, Math.max(0, fbm(x * 0.8, y * 0.8, p.seed + 31) * 2.2 - 0.55)); // veins fade in and out
      // Fine secondary veins
      const t2 = (x * Math.cos(p.angle + 1.1) + y * Math.sin(p.angle + 1.1)) * p.freq * 2.7 + fbm(x * 2 + wy, y * 2 + wx, p.seed + 41, 5) * p.warp * 1.4;
      let v2 = Math.pow(1 - Math.abs(Math.sin(Math.PI * t2)), p.sharp * 2.2) * Math.min(1, Math.max(0, fbm(x * 1.3, y * 1.3, p.seed + 53) * 2 - 0.6));
      c = mix(c, fine, Math.min(1, v2 * p.fineOpacity));
      c = mix(c, vein, Math.min(1, v * p.veinOpacity));
      const grain = (hash(i, j, p.seed) - 0.5) * 4; // tiny speckle so it is not plastic-flat
      const k = (j * N + i) * 3;
      out[k] = Math.max(0, Math.min(255, c[0] + grain));
      out[k + 1] = Math.max(0, Math.min(255, c[1] + grain));
      out[k + 2] = Math.max(0, Math.min(255, c[2] + grain));
    }
  }
  return out;
}

const slabs = {
  statuario:     { seed: 3,  base: ['#f4f4f2', '#e6e7e7'], vein: '#5f646b', fine: '#a3a8ae', angle: 0.55, freq: 0.55, warp: 3.2, sharp: 9,  veinOpacity: 0.9,  fineOpacity: 0.55 },
  calacatta_oro: { seed: 9,  base: ['#f8f5ef', '#eee7dc'], vein: '#a9864f', fine: '#8f8a83', angle: 0.9,  freq: 0.42, warp: 3.8, sharp: 7,  veinOpacity: 0.85, fineOpacity: 0.45 },
  nero_marquina: { seed: 21, base: ['#141416', '#1d1d20'], vein: '#ece9e3', fine: '#9b9893', angle: 0.35, freq: 0.6,  warp: 3.0, sharp: 14, veinOpacity: 0.95, fineOpacity: 0.5 },
  pietra_grey:   { seed: 33, base: ['#66686c', '#55575b'], vein: '#d8d8d5', fine: '#aeb0b2', angle: 1.2,  freq: 0.8,  warp: 2.6, sharp: 16, veinOpacity: 0.75, fineOpacity: 0.6 },
  crema_marfil:  { seed: 45, base: ['#ece1cd', '#ddcdb2'], vein: '#b99d74', fine: '#cdb795', angle: 0.7,  freq: 0.7,  warp: 2.8, sharp: 10, veinOpacity: 0.6,  fineOpacity: 0.5 },
};
const dir = process.argv[2];
for (const [name, p] of Object.entries(slabs)) {
  writeFileSync(`${dir}/${name}.rgb`, slab(p));
  console.log(name);
}
