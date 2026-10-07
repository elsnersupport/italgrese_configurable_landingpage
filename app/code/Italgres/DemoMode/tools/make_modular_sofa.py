"""Generate ModularSofa.glb: a sectional sofa built from separate elements (Lucca).

The GLB holds one hidden template per element; the configurator's "Compose" action clones the chosen
templates into a row from left to right and mirrors the right-hand end (param "mirror").

Hierarchy (metres, Y up, front = +Z, every element centred on x = 0, backs aligned at z = -D/2):
  LuccaSofa
    Element_Arm       1 seat with an arm on its left side          extras: hidden, moduleWidth
    Element_Chaise    chaise longue with a short arm on its left side
    Element_Ottoman   armless seat whose left end has no back (open end)
    Element_Seat      1 seat, no arm (successive element)
    Element_Seat15    1.5 seats, no arm (successive element)
  Each element: Seat_Base, Seat_Cushion, Back_Cushion, Headrest, Arm_Panel, Armrest   material "Cover"
                Piping_*                                                             material "Piping"
                Foot_*                                                               material "Feet"

Run: python3 make_modular_sofa.py ModularSofa.glb
"""
import json
import math
import struct
import sys

D = 1.00          # standard seat depth
CHAISE_D = 1.62   # chaise longue depth
FOOT_H = 0.035    # black puck feet
BASE_TOP = 0.32   # seam between the base and the seat cushion
SEAT_TOP = 0.425
BACK_TOP = 0.575  # lower back cushion top
HEAD_TOP = 0.815  # headrest top
BACK_D = 0.23     # lower back depth
PANEL_W = 0.13    # arm side panel
SEAT_ARM = 0.95   # seat width of the elements with an arm (and the ottoman)
GAP = 0.004       # seam between neighbouring parts
PIPE_R = 0.0045


class Mesh:
    def __init__(self):
        self.p, self.n, self.uv, self.idx = [], [], [], []

    def v(self, p, n, uv):
        self.p.append(p)
        self.n.append(n)
        self.uv.append(uv)
        return len(self.p) - 1

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


def norm(v):
    l = math.sqrt(sum(c * c for c in v)) or 1.0
    return tuple(c / l for c in v)


def cross(a, b):
    return (a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0])


def dot(a, b):
    return sum(a[i] * b[i] for i in range(3))


def axis_samples(h, r, k=7, mid=4):
    """Sample positions from -h to h, dense in the rounded bands."""
    out = []
    for i in range(k):
        out.append(-h + r * i / k)
    for i in range(mid + 1):
        out.append(-h + r + (2 * (h - r)) * i / mid)
    for i in range(1, k + 1):
        out.append(h - r + r * i / k)
    return out


def rounded_box(w, h, d, r, top_bulge=0.0, front_bulge=0.0, side_bulge=0.0):
    """Rounded box centred at the origin; optional pillow bulge on the top (+Y), front (+Z) or sides (±X)."""
    m = Mesh()
    hx, hy, hz = w / 2, h / 2, d / 2
    r = min(r, hx, hy, hz)
    sx, sy, sz = axis_samples(hx, r), axis_samples(hy, r), axis_samples(hz, r)

    def bulge(p, n, axis, amount, ua, ub, ha, hb):
        u, v = p[ua] / ha, p[ub] / hb
        f = max(0.0, (1 - u * u) * (1 - v * v))
        p[axis] += amount * f * n[axis]
        da = amount * (-2 * p[ua] / (ha * ha)) * (1 - v * v)
        db = amount * (-2 * p[ub] / (hb * hb)) * (1 - u * u)
        n = list(n)
        n[ua] -= da * n[axis]
        n[ub] -= db * n[axis]
        return p, list(norm(n))

    def project(x, y, z):
        ix = max(-(hx - r), min(hx - r, x))
        iy = max(-(hy - r), min(hy - r, y))
        iz = max(-(hz - r), min(hz - r, z))
        n = norm((x - ix, y - iy, z - iz))
        p = [ix + n[0] * r, iy + n[1] * r, iz + n[2] * r]
        n = list(n)
        if top_bulge and n[1] > 0.5:
            p, n = bulge(p, n, 1, top_bulge, 0, 2, hx, hz)
        if front_bulge and n[2] > 0.5:
            p, n = bulge(p, n, 2, front_bulge, 0, 1, hx, hy)
        if side_bulge and abs(n[0]) > 0.5:
            p, n = bulge(p, n, 0, side_bulge, 1, 2, hy, hz)
        return tuple(p), tuple(n)

    def uv_for(p, n):
        ax = max(range(3), key=lambda i: abs(n[i]))
        if ax == 0:
            return (p[2], p[1])
        if ax == 1:
            return (p[0], p[2])
        return (p[0], p[1])

    faces = [
        (lambda a, b: (hx, a, b), sy, sz),
        (lambda a, b: (-hx, a, b), sy, sz),
        (lambda a, b: (a, hy, b), sx, sz),
        (lambda a, b: (a, -hy, b), sx, sz),
        (lambda a, b: (a, b, hz), sx, sy),
        (lambda a, b: (a, b, -hz), sx, sy),
    ]
    for build, sa, sb in faces:
        ids = []
        for a in sa:
            row = []
            for b in sb:
                p, n = project(*build(a, b))
                row.append(m.v(p, n, uv_for(p, n)))
            ids.append(row)
        for i in range(len(sa) - 1):
            for j in range(len(sb) - 1):
                a, b, c, d2 = ids[i][j], ids[i + 1][j], ids[i + 1][j + 1], ids[i][j + 1]
                m.tri(a, b, c)
                m.tri(a, c, d2)
    return m


def tube(points, r=PIPE_R, seg=10, closed=True, up=None):
    """Sweeps a circle along a polyline (piping). `up` is the loop plane normal for a twist-free frame."""
    m = Mesh()
    n = len(points)
    pts = points + [points[0]] if closed else points
    count = len(pts)
    tangents = []
    for i in range(count):
        if closed:
            a, b = points[(i - 1) % n], points[(i + 1) % n]
        else:
            a, b = pts[max(i - 1, 0)], pts[min(i + 1, count - 1)]
        tangents.append(norm(tuple(b[k] - a[k] for k in range(3))))
    t0 = tangents[0]
    ref = up or ((0, 1, 0) if abs(t0[1]) < 0.9 else (1, 0, 0))
    nrm = norm(tuple(ref[k] - dot(ref, t0) * t0[k] for k in range(3)))
    rings = []
    s = 0.0
    for i in range(count):
        t = tangents[i]
        nrm = norm(tuple(nrm[k] - dot(nrm, t) * t[k] for k in range(3)))
        bi = cross(t, nrm)
        if i:
            s += math.dist(pts[i], pts[i - 1])
        ring = []
        for j in range(seg + 1):
            a = 2 * math.pi * j / seg
            d = tuple(math.cos(a) * nrm[k] + math.sin(a) * bi[k] for k in range(3))
            ring.append(m.v(tuple(pts[i][k] + r * d[k] for k in range(3)), d, (s, a * r)))
        rings.append(ring)
    for i in range(count - 1):
        for j in range(seg):
            a, b, c, d2 = rings[i][j], rings[i + 1][j], rings[i + 1][j + 1], rings[i][j + 1]
            m.tri(a, b, c)
            m.tri(a, c, d2)
    if not closed:
        for i, sign in ((0, -1), (count - 1, 1)):
            t = tangents[i]
            nn = tuple(sign * t[k] for k in range(3))
            c = m.v(pts[i], nn, (0, 0))
            ids = [m.v(m.p[rings[i][j]], nn, (0, 0)) for j in range(seg)]
            for j in range(seg):
                m.tri(c, ids[j], ids[(j + 1) % seg])
    return m


def rrect_loop(hu, hv, rc, steps=6):
    """Closed rounded-rectangle path (u, v) around the origin, corners of radius rc."""
    rc = min(rc, hu, hv)
    out = []
    corners = ((hu - rc, hv - rc, 0), (-(hu - rc), hv - rc, 90), (-(hu - rc), -(hv - rc), 180), (hu - rc, -(hv - rc), 270))
    for cu, cv, start in corners:
        for i in range(steps + 1):
            a = math.radians(start + 90 * i / steps)
            out.append((cu + rc * math.cos(a), cv + rc * math.sin(a)))
    return out


def bend_out(m, h, amount):
    """Curves a part outwards (-X) towards its top, quadratically: the flare of a scroll arm."""
    for i, (p, n) in enumerate(zip(m.p, m.n)):
        t = min(1.0, max(0.0, (p[1] + h / 2) / h))
        m.p[i] = (p[0] - amount * t * t, p[1], p[2])
        slope = -2 * amount * t / h  # d(x)/d(y)
        m.n[i] = norm((n[0], n[1] - slope * n[0], n[2]))
    return m


def edge_offset(half, r):
    """Where piping sits on a rounded edge: the 45 degree point of the corner arc, nudged outwards."""
    return half - r + r * math.sqrt(0.5) + PIPE_R * 0.45


def foot(r=0.024, h=FOOT_H, seg=28):
    m = Mesh()
    ring0, ring1 = [], []
    for i in range(seg + 1):
        a = 2 * math.pi * i / seg
        ca, sa = math.cos(a), math.sin(a)
        nrm = (ca, 0, sa)
        ring0.append(m.v((r * 0.92 * ca, -h / 2, r * 0.92 * sa), nrm, (i / seg * 0.15, 0)))
        ring1.append(m.v((r * ca, h / 2, r * sa), nrm, (i / seg * 0.15, h)))
    for i in range(seg):
        m.tri(ring0[i], ring1[i + 1], ring0[i + 1])
        m.tri(ring0[i], ring1[i], ring1[i + 1])
    c = m.v((0, -h / 2, 0), (0, -1, 0), (0, 0))
    ids = [m.v((r * 0.92 * math.cos(2 * math.pi * i / seg), -h / 2, r * 0.92 * math.sin(2 * math.pi * i / seg)), (0, -1, 0), (0, 0)) for i in range(seg)]
    for i in range(seg):
        m.tri(c, ids[i], ids[(i + 1) % seg])
    return m


def quat(axis, angle):
    s = math.sin(angle / 2)
    return [axis[0] * s, axis[1] * s, axis[2] * s, math.cos(angle / 2)]


MATERIALS = {
    'Cover': {'name': 'Cover', 'pbrMetallicRoughness': {'baseColorFactor': [0.80, 0.77, 0.71, 1], 'metallicFactor': 0, 'roughnessFactor': 0.92}},
    'Piping': {'name': 'Piping', 'pbrMetallicRoughness': {'baseColorFactor': [0.80, 0.77, 0.71, 1], 'metallicFactor': 0, 'roughnessFactor': 0.92}},
    'Feet': {'name': 'Feet', 'pbrMetallicRoughness': {'baseColorFactor': [0.035, 0.035, 0.035, 1], 'metallicFactor': 0, 'roughnessFactor': 0.55}},
}
MAT_INDEX = {k: i for i, k in enumerate(MATERIALS)}


class Part:
    def __init__(self, name, mesh, material, t=(0, 0, 0), rot=None):
        self.name, self.mesh, self.material, self.t, self.rot = name, mesh, material, t, rot


def seat_block(x0, x1, depth, parts, suffix=''):
    """Base and seat cushion between x0 and x1 (element space), from the back plane forward `depth`."""
    w = x1 - x0
    cx = (x0 + x1) / 2
    zb = -D / 2
    base_h = BASE_TOP - FOOT_H
    rb = 0.02
    parts.append(Part('Seat_Base' + suffix, rounded_box(w - 2 * GAP, base_h, depth - GAP, rb, front_bulge=0.006),
                      'Cover', (cx, FOOT_H + base_h / 2, zb + depth / 2)))
    sink = 0.035  # the cushion's lower rounding sits inside the base: the seam reads as a piped crease
    cush_d = depth - BACK_D + 0.03
    ch = SEAT_TOP - BASE_TOP + sink
    rc = 0.055
    cz = zb + depth - cush_d / 2 + 0.002
    cy = BASE_TOP - sink + ch / 2
    parts.append(Part('Seat_Cushion' + suffix, rounded_box(w - 2 * GAP - 0.004, ch, cush_d, rc, top_bulge=0.024, front_bulge=0.016),
                      'Cover', (cx, cy, cz)))
    # Piping: around the seat cushion top and along the cushion/base seam
    hu = edge_offset((w - 2 * GAP - 0.004) / 2, rc)
    hv = edge_offset(cush_d / 2, rc)
    yt = cy + edge_offset(ch / 2, rc)
    loop = [(cx + u, yt, cz + v) for u, v in rrect_loop(hu, hv, 0.02)]
    parts.append(Part('Piping_Seat' + suffix, tube(loop, up=(0, 1, 0)), 'Piping'))
    hu = edge_offset((w - 2 * GAP) / 2, rb)
    hv = edge_offset((depth - GAP) / 2, rb)
    ys = FOOT_H + base_h / 2 + edge_offset(base_h / 2, rb)
    loop = [(cx + u, ys, zb + depth / 2 + v) for u, v in rrect_loop(hu, hv, 0.02)]
    parts.append(Part('Piping_Seam' + suffix, tube(loop, up=(0, 1, 0)), 'Piping'))


def back_block(x0, x1, parts, suffix=''):
    """Lower back cushion with a separate headrest above it, both with piped front edges."""
    w = x1 - x0 - 2 * GAP
    cx = (x0 + x1) / 2
    zb = -D / 2
    bh = BACK_TOP - BASE_TOP + 0.01
    rb = 0.05
    lean = quat((1, 0, 0), -0.07)
    parts.append(Part('Back_Cushion' + suffix, rounded_box(w, bh, BACK_D, rb, front_bulge=0.014),
                      'Cover', (cx, BASE_TOP + bh / 2, zb + BACK_D / 2 + 0.005), lean))
    hh = HEAD_TOP - BACK_TOP + 0.012
    hd = 0.16
    rh = 0.055
    head_t = (cx, BACK_TOP - 0.012 + hh / 2, zb + hd / 2 + 0.012)
    head_r = quat((1, 0, 0), -0.05)
    parts.append(Part('Headrest' + suffix, rounded_box(w - 0.004, hh, hd, rh, front_bulge=0.012), 'Cover', head_t, head_r))
    # Piping round the front face of the headrest and along the top of the lower back
    zf = edge_offset(hd / 2, rh)
    loop = [(u, v, zf) for u, v in rrect_loop(edge_offset((w - 0.004) / 2, rh), edge_offset(hh / 2, rh), 0.03)]
    parts.append(Part('Piping_Head' + suffix, tube(loop, up=(0, 0, 1)), 'Piping', head_t, head_r))
    zf = edge_offset(BACK_D / 2, rb)
    loop = [(u, v, zf) for u, v in rrect_loop(edge_offset(w / 2, rb), edge_offset(bh / 2, rb), 0.035)]
    parts.append(Part('Piping_Back' + suffix, tube(loop, up=(0, 0, 1)), 'Piping', (cx, BASE_TOP + bh / 2, zb + BACK_D / 2 + 0.005), lean))


def arm_block(x_outer, depth, parts, proud=0.0):
    """Left arm: a slim upholstered side panel with a soft armrest pillow that overhangs the seat.
    `proud` pushes the panel's outer face out past a seat block that runs underneath it (chaise)."""
    zb = -D / 2
    ph = 0.50 - FOOT_H * 0.4
    rp = 0.045
    pcx = x_outer + PANEL_W / 2 - proud
    pt = (pcx, FOOT_H * 0.4 + ph / 2, zb + depth / 2)
    flare = 0.05  # the panel curves out towards the top, flowing into the armrest
    parts.append(Part('Arm_Panel', bend_out(rounded_box(PANEL_W - GAP, ph, depth, rp, side_bulge=0.008), ph, flare), 'Cover', pt))
    # Piping round the outer face of the panel
    xo = -edge_offset((PANEL_W - GAP) / 2, rp)
    loop = [(xo, v, u) for u, v in rrect_loop(edge_offset(depth / 2, rp), edge_offset(ph / 2, rp), 0.05)]
    parts.append(Part('Piping_Arm', bend_out(tube(loop, up=(-1, 0, 0)), ph, flare), 'Piping', pt))
    aw, ah, ad = 0.31, 0.13, depth - 0.04
    at = (x_outer + aw / 2 - flare - 0.008 - proud, 0.51, zb + 0.02 + ad / 2)
    parts.append(Part('Armrest', rounded_box(aw, ah, ad, 0.062, top_bulge=0.022, side_bulge=0.01, front_bulge=0.012), 'Cover', at,
                      quat((0, 0, 1), -0.12)))


def feet(xs, zs, parts):
    for i, x in enumerate(xs):
        for j, z in enumerate(zs):
            parts.append(Part(f'Foot_{i}{j}', foot(), 'Feet', (x, FOOT_H / 2, z)))


def element_arm(depth=D, arm_depth=None):
    """Seat with an arm on its left side; with a long depth it is the chaise longue, whose seat runs the
    full width and carries the short arm on its back corner."""
    parts = []
    w = PANEL_W + SEAT_ARM
    x0 = -w / 2
    chaise = depth > D
    seat_block(x0 if chaise else x0 + PANEL_W, w / 2, depth, parts)
    back_block(x0 + PANEL_W, w / 2, parts)
    arm_block(x0, arm_depth or D - 0.03, parts, proud=0.004 if chaise else 0.0)
    feet([x0 + 0.07, w / 2 - 0.07], [-D / 2 + 0.07, -D / 2 + depth - 0.08], parts)
    return w, parts


def element_ottoman():
    """Armless seat whose outer (left) end is open: the back covers only the inner part."""
    parts = []
    w = PANEL_W + SEAT_ARM
    seat_block(-w / 2, w / 2, D, parts)
    back_block(w / 2 - 0.66, w / 2, parts)
    feet([-w / 2 + 0.07, w / 2 - 0.07], [-D / 2 + 0.07, D / 2 - 0.08], parts)
    return w, parts


def element_seat(w):
    parts = []
    seat_block(-w / 2, w / 2, D, parts)
    back_block(-w / 2, w / 2, parts)
    feet([-w / 2 + 0.07, w / 2 - 0.07], [-D / 2 + 0.07, D / 2 - 0.08], parts)
    return w, parts


ELEMENTS = [
    ('Element_Arm', element_arm()),
    ('Element_Chaise', element_arm(CHAISE_D, D - 0.08)),
    ('Element_Ottoman', element_ottoman()),
    ('Element_Seat', element_seat(0.74)),
    ('Element_Seat15', element_seat(1.06)),
]

bin_ = bytearray()
views, accessors, meshes, nodes = [], [], [], []


def add_view(data, target):
    while len(bin_) % 4:
        bin_.append(0)
    views.append({'buffer': 0, 'byteOffset': len(bin_), 'byteLength': len(data), 'target': target})
    bin_.extend(data)
    return len(views) - 1


def add_mesh(name, m, material):
    pos = b''.join(struct.pack('<3f', *p) for p in m.p)
    nor = b''.join(struct.pack('<3f', *n) for n in m.n)
    uv = b''.join(struct.pack('<2f', u, 1 - v) for u, v in m.uv)
    big = len(m.p) > 65535
    ind = b''.join(struct.pack('<I' if big else '<H', i) for i in m.idx)
    a0 = len(accessors)
    accessors.extend([
        {'bufferView': add_view(pos, 34962), 'componentType': 5126, 'count': len(m.p), 'type': 'VEC3',
         'min': [min(p[k] for p in m.p) for k in range(3)], 'max': [max(p[k] for p in m.p) for k in range(3)]},
        {'bufferView': add_view(nor, 34962), 'componentType': 5126, 'count': len(m.n), 'type': 'VEC3'},
        {'bufferView': add_view(uv, 34962), 'componentType': 5126, 'count': len(m.uv), 'type': 'VEC2'},
        {'bufferView': add_view(ind, 34963), 'componentType': 5125 if big else 5123, 'count': len(m.idx), 'type': 'SCALAR'},
    ])
    meshes.append({'name': name, 'primitives': [{'attributes': {'POSITION': a0, 'NORMAL': a0 + 1, 'TEXCOORD_0': a0 + 2},
                                                 'indices': a0 + 3, 'material': MAT_INDEX[material]}]})
    return len(meshes) - 1


root_children = []
for name, (width, parts) in ELEMENTS:
    ids = []
    for part in parts:
        node = {'name': part.name, 'mesh': add_mesh(part.name, part.mesh, part.material), 'translation': list(part.t)}
        if part.rot:
            node['rotation'] = part.rot
        nodes.append(node)
        ids.append(len(nodes) - 1)
    nodes.append({'name': name, 'children': ids, 'extras': {'hidden': True, 'moduleWidth': round(width, 4)}})
    root_children.append(len(nodes) - 1)
nodes.append({'name': 'LuccaSofa', 'children': root_children})

while len(bin_) % 4:
    bin_.append(0)
gltf = {
    'asset': {'version': '2.0', 'generator': 'italgres-demo make_modular_sofa.py'},
    'scene': 0, 'scenes': [{'nodes': [len(nodes) - 1]}], 'nodes': nodes, 'meshes': meshes,
    'materials': list(MATERIALS.values()), 'accessors': accessors, 'bufferViews': views,
    'buffers': [{'byteLength': len(bin_)}],
}
js = json.dumps(gltf, separators=(',', ':')).encode()
js += b' ' * ((4 - len(js) % 4) % 4)
with open(sys.argv[1], 'wb') as out:
    out.write(struct.pack('<4sII', b'glTF', 2, 12 + 8 + len(js) + 8 + len(bin_)))
    out.write(struct.pack('<I4s', len(js), b'JSON'))
    out.write(js)
    out.write(struct.pack('<I4s', len(bin_), b'BIN\x00'))
    out.write(bin_)
print('elements', len(ELEMENTS), 'meshes', len(meshes), 'bytes', 12 + 16 + len(js) + len(bin_))
