#!/usr/bin/env python3
"""Rasterise DevLab's mark into the icon files browsers and iOS actually want.

    python resources/brand/build-icons.py

`public/favicon.svg` is the source of truth for the shape and needs no build —
every current browser prefers it, and it is the only one of the three that can
invert with the visitor's theme. This script exists for the two that cannot be
vectors: `favicon.ico`, which legacy surfaces still reach for, and
`apple-touch-icon.png`, which iOS rasterises onto a home screen.

It is stdlib-only on purpose. The alternative was adding `sharp` or ImageMagick
to the toolchain to generate three files that change roughly never, and
CLAUDE.md asks for a reason before a dependency. Sixty lines of supersampling
beats a build dependency here: the mark is a rounded rectangle with a
parallelogram cut out of it, which is about the easiest thing there is to
rasterise correctly.

THE MARK. `dev/lab` reads as a path and the slash is the character the name
turns on. The plate is the 3px-radius faceplate the whole interface is built
from, scaled; the slash is knocked out of it rather than drawn on it, so the
mark stays something set on the rack rather than a hole cut into it.
"""

import struct
import zlib
from pathlib import Path

PUBLIC = Path(__file__).resolve().parents[2] / "public"

# The mark, in the 48-unit space favicon.svg uses. Keep these two in step.
SIZE = 48.0
RADIUS = 5.0
SLASH = [(31.0, 10.0), (24.5, 10.0), (17.0, 38.0), (23.5, 38.0)]

# The fixed rasters take one treatment: the product's own dark ground with a
# light slash. Only the SVG inverts with the theme — a raster has to pick, and a
# dark plate is the one that survives a light browser tab strip, which is where
# a .ico is still reached for.
PLATE = (23, 29, 38)  # --background, dark
INK = (228, 233, 240)  # --foreground, dark

SS = 4  # supersampling factor per axis; 16 samples a pixel


def in_plate(x: float, y: float, radius: float) -> bool:
    """Inside the rounded rectangle."""
    if not (0.0 <= x <= SIZE and 0.0 <= y <= SIZE):
        return False
    if radius <= 0:
        return True
    cx = radius if x < radius else (SIZE - radius if x > SIZE - radius else x)
    cy = radius if y < radius else (SIZE - radius if y > SIZE - radius else y)
    if cx == x and cy == y:
        return True
    return (x - cx) ** 2 + (y - cy) ** 2 <= radius * radius


def in_slash(x: float, y: float) -> bool:
    """Inside the parallelogram, by consistent winding against every edge."""
    sign = None
    for i in range(len(SLASH)):
        ax, ay = SLASH[i]
        bx, by = SLASH[(i + 1) % len(SLASH)]
        cross = (bx - ax) * (y - ay) - (by - ay) * (x - ax)
        if cross == 0:
            continue
        this = cross > 0
        if sign is None:
            sign = this
        elif sign != this:
            return False
    return True


def render(px: int, radius: float, opaque: bool) -> bytes:
    """RGBA rows for the mark at `px` square."""
    step = SIZE / (px * SS)
    rows = bytearray()
    for py in range(px):
        rows.append(0)  # PNG filter type 0 for this scanline
        for pxi in range(px):
            plate = slash = 0
            for sy in range(SS):
                y = (py * SS + sy + 0.5) * step
                for sx in range(SS):
                    x = (pxi * SS + sx + 0.5) * step
                    if in_plate(x, y, radius):
                        plate += 1
                        if in_slash(x, y):
                            slash += 1
            total = SS * SS
            if plate == 0:
                rows.extend((0, 0, 0, 0) if not opaque else (*PLATE, 255))
                continue
            # Coverage of the slash *within* the plate decides the colour; the
            # plate's own coverage decides the alpha. Doing it in that order is
            # what keeps the corners smooth instead of fringed.
            t = slash / plate
            rgb = tuple(round(PLATE[i] + (INK[i] - PLATE[i]) * t) for i in range(3))
            alpha = 255 if opaque else round(255 * plate / total)
            rows.extend((*rgb, alpha))
    return bytes(rows)


def png(px: int, radius: float, opaque: bool) -> bytes:
    def chunk(tag: bytes, payload: bytes) -> bytes:
        return (
            struct.pack(">I", len(payload))
            + tag
            + payload
            + struct.pack(">I", zlib.crc32(tag + payload) & 0xFFFFFFFF)
        )

    header = struct.pack(">IIBBBBB", px, px, 8, 6, 0, 0, 0)  # 8-bit RGBA
    return (
        b"\x89PNG\r\n\x1a\n"
        + chunk(b"IHDR", header)
        + chunk(b"IDAT", zlib.compress(render(px, radius, opaque), 9))
        + chunk(b"IEND", b"")
    )


def ico(sizes: list[int]) -> bytes:
    """An ICO carrying PNG entries — supported everywhere that still reads .ico."""
    images = [png(s, RADIUS * s / SIZE, opaque=False) for s in sizes]
    offset = 6 + 16 * len(images)
    out = struct.pack("<HHH", 0, 1, len(images))
    for s, data in zip(sizes, images):
        out += struct.pack(
            "<BBBBHHII", s if s < 256 else 0, s if s < 256 else 0, 0, 0, 1, 32,
            len(data), offset,
        )
        offset += len(data)
    return out + b"".join(images)


if __name__ == "__main__":
    ico_bytes = ico([16, 32, 48])
    (PUBLIC / "favicon.ico").write_bytes(ico_bytes)
    print(f"favicon.ico          {len(ico_bytes):>6} bytes  (16, 32, 48)")

    # iOS masks the icon to its own squircle and composites opaquely, so this
    # one is full-bleed and square — rounded corners here would be rounded
    # twice, and transparency would be filled with white.
    touch = png(180, radius=0.0, opaque=True)
    (PUBLIC / "apple-touch-icon.png").write_bytes(touch)
    print(f"apple-touch-icon.png {len(touch):>6} bytes  (180, full-bleed)")
