#!/usr/bin/env python3
"""
Download Unsplash photos for shop catalogue items, square-crop to 1024x1024,
and apply a light corner navy vignette (same look as auction restyles).
"""

from __future__ import annotations

import io
import urllib.request
from pathlib import Path

from PIL import Image, ImageDraw, ImageEnhance, ImageFilter, ImageOps

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "public" / "images" / "products"
SIZE = 1024

# Featured shop products are all watches; include full Watches category (12).
SOURCES: dict[str, str] = {
    "classic-leather-strap-dress-watch.png": "https://images.unsplash.com/photo-1524592094714-0f0654e20314?auto=format&fit=crop&w=1600&q=80",
    "steel-bracelet-business-watch.png": "https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=1600&q=80",
    "slim-minimalist-watch.png": "https://images.unsplash.com/photo-1522312346375-d1a52e2b99b3?auto=format&fit=crop&w=1600&q=80",
    "rose-gold-bracelet-watch.png": "https://images.unsplash.com/photo-1547996160-81dfa63595aa?auto=format&fit=crop&w=1600&q=80",
    "crystal-bezel-fashion-watch.png": "https://images.unsplash.com/photo-1587836374828-4dbafa94cf0e?auto=format&fit=crop&w=1600&q=80",
    "canvas-nato-strap-casual-watch.png": "https://images.unsplash.com/photo-1434056886845-dac89ffe9b56?auto=format&fit=crop&w=1600&q=80",
    "minimalist-mesh-strap-watch.png": "https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?auto=format&fit=crop&w=1600&q=80",
    "fitness-tracker-smartwatch.png": "https://images.unsplash.com/photo-1579586337278-3befd40fd17a?auto=format&fit=crop&w=1600&q=80",
    "call-function-smartwatch.png": "https://images.unsplash.com/photo-1617043786394-f977fa12eddf?auto=format&fit=crop&w=1600&q=80",
    "digital-cartoon-print-watch.png": "https://images.unsplash.com/photo-1551816230-ef5deaed4a26?auto=format&fit=crop&w=1600&q=80",
    "his-hers-matching-watch-set.png": "https://images.unsplash.com/photo-1612817159949-195b6eb9e31a?auto=format&fit=crop&w=1600&q=80",
    "watch-bracelet-gift-box.png": "https://images.unsplash.com/photo-1609587312208-cea54be969e7?auto=format&fit=crop&w=1600&q=80",
}


def square_cover(img: Image.Image, size: int = SIZE) -> Image.Image:
    img = ImageOps.exif_transpose(img).convert("RGB")
    return ImageOps.fit(img, (size, size), method=Image.Resampling.LANCZOS, centering=(0.5, 0.45))


def corner_vignette(size: int = SIZE) -> Image.Image:
    mask = Image.new("L", (size, size), 255)
    draw = ImageDraw.Draw(mask)
    cx = cy = size // 2
    clear_r = int(size * 0.72)
    for radius in range(int(size * 0.92), clear_r, -2):
        t = (radius - clear_r) / (size * 0.92 - clear_r)
        alpha = int(255 * (1.0 - 0.55 * t))
        draw.ellipse([cx - radius, cy - radius, cx + radius, cy + radius], fill=alpha)
    return mask.filter(ImageFilter.GaussianBlur(24))


def restyle(photo: Image.Image) -> Image.Image:
    subject = square_cover(photo)
    subject = ImageEnhance.Contrast(subject).enhance(1.04)
    navy = Image.new("RGB", (SIZE, SIZE), (10, 22, 42))
    return Image.composite(subject, navy, corner_vignette())


def download(url: str) -> bytes:
    req = urllib.request.Request(
        url,
        headers={"User-Agent": "UniqBidImagePipeline/1.0 (local asset prep)"},
    )
    with urllib.request.urlopen(req, timeout=60) as response:
        return response.read()


def process(filename: str, url: str) -> Path:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    print(f"-> {filename}")
    raw = download(url)
    photo = Image.open(io.BytesIO(raw))
    final = restyle(photo)
    out_path = OUT_DIR / filename
    final.save(out_path, format="PNG", optimize=True)
    print(f"  saved {out_path.relative_to(ROOT)} ({final.size[0]}x{final.size[1]})")
    return out_path


def main() -> None:
    for filename, url in SOURCES.items():
        process(filename, url)
    print("Done.")


if __name__ == "__main__":
    main()
