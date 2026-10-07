"""Generate FioraTable.glb: porcelain-slab coffee table with three top shapes and two bases.

Nodes: Top_Round / Top_Oval / Top_Rect (material "Porcelain"), Base_Single / Base_Twin (material "Base").
Units are metres, Y up, UVs on the slab tops are planar over a 1.4 m square so the slab texture keeps its scale.
"""
import json, math, struct, sys

H = 0.38          # table height
T = 0.02          # slab thickness (12 mm porcelain on a 8 mm backer)
C = 0.0025        # eased top edge
UVS = 1.4         # metres covered by one texture

def rounded_rect(w, d, r, seg=10):
    pts = []
    corners = [(w/2 - r, d/2 - r, 0), (-w/2 + r, d/2 - r, 90), (-w/2 + r, -d/2 + r, 180), (w/2 - r, -d/2 + r, 270)]
    for cx, cz, a0 in corners:
        for i in range(seg + 1):
            a = math.radians(a0 + 90 * i / seg)
            pts.append((cx + r * math.cos(a), cz + r * math.sin(a)))
    return pts

def ellipse(rx, rz, seg=128):
    return [(rx * math.cos(2 * math.pi * i / seg), rz * math.sin(2 * math.pi * i / seg)) for i in range(seg)]

def vnormals(pts):
    n = len(pts); out = []
    for i in range(n):
        x0, z0 = pts[i - 1]; x1, z1 = pts[i]; x2, z2 = pts[(i + 1) % n]
        e1 = (z1 - z0, -(x1 - x0)); e2 = (z2 - z1, -(x2 - x1))
        nx, nz = e1[0] + e2[0], e1[1] + e2[1]
        l = math.hypot(nx, nz) or 1
        out.append((nx / l, nz / l))
    # outline is counter-clockwise in x/z, so the edge normal (dz, -dx) already points outward
    return out

class Mesh:
    def __init__(self):
        self.p, self.n, self.uv, self.idx = [], [], [], []
    def v(self, p, n, uv):
        self.p.append(p); self.n.append(n); self.uv.append(uv); return len(self.p) - 1
    def tri(self, a, b, c):
        # Orient every triangle so its winding agrees with the vertex normals (front faces outward)
        pa, pb, pc = self.p[a], self.p[b], self.p[c]
        u = [pb[i] - pa[i] for i in range(3)]
        v = [pc[i] - pa[i] for i in range(3)]
        cr = (u[1] * v[2] - u[2] * v[1], u[2] * v[0] - u[0] * v[2], u[0] * v[1] - u[1] * v[0])
        nsum = [self.n[a][i] + self.n[b][i] + self.n[c][i] for i in range(3)]
        if sum(cr[i] * nsum[i] for i in range(3)) < 0:
            b, c = c, b
        self.idx += [a, b, c]

def slab(outline):
    """Porcelain slab: eased top edge, square bottom edge. Every face uses the top's planar
    mapping so the veining wraps over the edge like a mitred slab."""
    m = Mesh()
    ns = vnormals(outline)
    inset = [(x - nx * C, z - nz * C) for (x, z), (nx, nz) in zip(outline, ns)]
    top, bot = H, H - T
    n = len(outline)
    uv = lambda x, z: (0.5 + x / UVS, 0.5 + z / UVS)
    def cap(ring, y, ny):
        c = m.v((0, y, 0), (0, ny, 0), (0.5, 0.5))
        ids = [m.v((x, y, z), (0, ny, 0), uv(x, z)) for x, z in ring]
        for i in range(n):
            a, b = ids[i], ids[(i + 1) % n]
            m.tri(c, b, a) if ny > 0 else m.tri(c, a, b)
    cap(inset, top, 1)
    cap(outline, bot, -1)
    rings = [(inset, top), (outline, top - C), (outline, bot)]
    for k, dy in ((0, 0.7071), (1, 0.0)):
        (r0, y0), (r1, y1) = rings[k], rings[k + 1]
        dh = math.sqrt(1 - dy * dy)
        a_ids, b_ids = [], []
        for i in range(n + 1):
            j = i % n
            nn = (ns[j][0] * dh, dy, ns[j][1] * dh)
            a_ids.append(m.v((r0[j][0], y0, r0[j][1]), nn, uv(*outline[j])))
            b_ids.append(m.v((r1[j][0], y1, r1[j][1]), nn, uv(*outline[j])))
        for i in range(n):
            m.tri(a_ids[i], a_ids[i + 1], b_ids[i + 1]); m.tri(a_ids[i], b_ids[i + 1], b_ids[i])
    return m

def drum(m, cx, cz, r_bot, r_top, y0, y1, seg=96):
    slope = (r_bot - r_top) / (y1 - y0)
    l = math.hypot(1, slope)
    ring0, ring1 = [], []
    for i in range(seg + 1):
        a = 2 * math.pi * i / seg
        ca, sa = math.cos(a), math.sin(a)
        nrm = (ca / l, slope / l, sa / l)
        u = i / seg * (2 * math.pi * r_bot) / 0.5
        ring0.append(m.v((cx + r_bot * ca, y0, cz + r_bot * sa), nrm, (u, y0 / 0.5)))
        ring1.append(m.v((cx + r_top * ca, y1, cz + r_top * sa), nrm, (u, y1 / 0.5)))
    for i in range(seg):
        m.tri(ring0[i], ring1[i + 1], ring0[i + 1]); m.tri(ring0[i], ring1[i], ring1[i + 1])
    for y, r, ny in ((y1, r_top, 1), (y0, r_bot, -1)):
        c = m.v((cx, y, cz), (0, ny, 0), (0.5, 0.5))
        ids = [m.v((cx + r * math.cos(2 * math.pi * i / seg), y, cz + r * math.sin(2 * math.pi * i / seg)), (0, ny, 0),
                   (0.5 + math.cos(2 * math.pi * i / seg) * r, 0.5 + math.sin(2 * math.pi * i / seg) * r)) for i in range(seg)]
        for i in range(seg):
            a, b = ids[i], ids[(i + 1) % seg]
            m.tri(c, b, a) if ny > 0 else m.tri(c, a, b)
    return m

def base_single():
    m = Mesh(); drum(m, 0, 0, 0.21, 0.17, 0.0, H - T); return m

def base_twin():
    m = Mesh()
    for cx in (-0.30, 0.30):
        drum(m, cx, 0, 0.14, 0.11, 0.0, H - T)
    return m

parts = [
    ('Top_Round', slab(ellipse(0.45, 0.45)), 0, True),
    ('Top_Oval', slab(ellipse(0.65, 0.35)), 0, False),
    ('Top_Rect', slab(rounded_rect(1.20, 0.65, 0.05)), 0, False),
    ('Base_Single', base_single(), 1, True),
    ('Base_Twin', base_twin(), 1, False),
]

bin_ = bytearray(); views = []; accessors = []; meshes = []; nodes = []
def add_view(data, target):
    while len(bin_) % 4: bin_.append(0)
    views.append({'buffer': 0, 'byteOffset': len(bin_), 'byteLength': len(data), 'target': target})
    bin_.extend(data); return len(views) - 1
for name, m, mat, visible in parts:
    pos = b''.join(struct.pack('<3f', *p) for p in m.p)
    nor = b''.join(struct.pack('<3f', *n) for n in m.n)
    uv = b''.join(struct.pack('<2f', u, 1 - v) for u, v in m.uv)
    big = len(m.p) > 65535
    ind = b''.join(struct.pack('<I' if big else '<H', i) for i in m.idx)
    acc0 = len(accessors)
    accessors += [
        {'bufferView': add_view(pos, 34962), 'componentType': 5126, 'count': len(m.p), 'type': 'VEC3',
         'min': [min(p[k] for p in m.p) for k in range(3)], 'max': [max(p[k] for p in m.p) for k in range(3)]},
        {'bufferView': add_view(nor, 34962), 'componentType': 5126, 'count': len(m.n), 'type': 'VEC3'},
        {'bufferView': add_view(uv, 34962), 'componentType': 5126, 'count': len(m.uv), 'type': 'VEC2'},
        {'bufferView': add_view(ind, 34963), 'componentType': 5125 if big else 5123, 'count': len(m.idx), 'type': 'SCALAR'},
    ]
    meshes.append({'name': name, 'primitives': [{'attributes': {'POSITION': acc0, 'NORMAL': acc0 + 1, 'TEXCOORD_0': acc0 + 2},
                                                 'indices': acc0 + 3, 'material': mat}]})
    node = {'name': name, 'mesh': len(meshes) - 1}
    if not visible:
        node['extras'] = {'hidden': True}
    nodes.append(node)
nodes.append({'name': 'FioraTable', 'children': list(range(len(parts)))})
while len(bin_) % 4: bin_.append(0)
gltf = {
    'asset': {'version': '2.0', 'generator': 'italgres-demo make_table.py'},
    'scene': 0, 'scenes': [{'nodes': [len(nodes) - 1]}], 'nodes': nodes, 'meshes': meshes,
    'materials': [
        {'name': 'Porcelain', 'pbrMetallicRoughness': {'baseColorFactor': [0.95, 0.95, 0.94, 1], 'metallicFactor': 0, 'roughnessFactor': 0.12}},
        {'name': 'Base', 'pbrMetallicRoughness': {'baseColorFactor': [0.78, 0.62, 0.36, 1], 'metallicFactor': 1, 'roughnessFactor': 0.35}},
    ],
    'accessors': accessors, 'bufferViews': views, 'buffers': [{'byteLength': len(bin_)}],
}
js = json.dumps(gltf, separators=(',', ':')).encode()
js += b' ' * ((4 - len(js) % 4) % 4)
out = open(sys.argv[1], 'wb')
out.write(struct.pack('<4sII', b'glTF', 2, 12 + 8 + len(js) + 8 + len(bin_)))
out.write(struct.pack('<I4s', len(js), b'JSON')); out.write(js)
out.write(struct.pack('<I4s', len(bin_), b'BIN\x00')); out.write(bin_)
print('vertices', sum(len(m.p) for _, m, _, _ in parts), 'bytes', 12 + 16 + len(js) + len(bin_))
