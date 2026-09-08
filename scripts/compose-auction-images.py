#!/usr/bin/env python3
"""
Download Unsplash source photos, square-crop to 1024x1024, and apply a
light corner vignette only — product stays sharp with no centre haze.
"""

from __future__ import annotations

import io
import urllib.request
from pathlib import Path

from PIL import Image, ImageDraw, ImageEnhance, ImageFilter, ImageOps

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "public" / "images" / "auctions"
SIZE = 1024

SOURCES: dict[str, str] = {
    "samsung-phone.png": "https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=1600&q=80",
    "tv-woofer.png": "https://images.unsplash.com/photo-1593784991095-a205069470b6?auto=format&fit=crop&w=1600&q=80",
    "dairy-cow.png": "https://images.unsplash.com/photo-1570042225831-d98fa7577f1e?auto=format&fit=crop&w=1600&q=80",
    "rent-keys.png": "https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=1600&q=80",
    "solar-kit.png": "https://images.unsplash.com/photo-1509391366360-2e959784a276?auto=format&fit=crop&w=1600&q=80",
}


def square_cover(img: Image.Image, size: int = SIZE) -> Image.Image:
    img = ImageOps.exif_transpose(img).convert("RGB")
    return ImageOps.fit(img, (size, size), method=Image.Resampling.LANCZOS, centering=(0.5, 0.45))


def corner_vignette(size: int = SIZE) -> Image.Image:
    """
    Darken only the outer corners. Centre stays fully clear (white = no darken).
    Returned as an L mask where 255 = leave photo alone, 0 = full navy darken.
    """
    mask = Image.new("L", (size, size), 255)
    draw = ImageDraw.Draw(mask)
    cx = cy = size // 2
    clear_r = int(size * 0.72)  # large clear centre
    for radius in range(int(size * 0.92), clear_r, -2):
        t = (radius - clear_r) / (size * 0.92 - clear_r)
        # At clear_r → 255; toward outer → darker (lower)
        alpha = int(255 * (1.0 - 0.55 * t))
        draw.ellipse([cx - radius, cy - radius, cx + radius, cy + radius], fill=alpha)
    return mask.filter(ImageFilter.GaussianBlur(24))


def restyle(photo: Image.Image) -> Image.Image:
    subject = square_cover(photo)
    subject = ImageEnhance.Contrast(subject).enhance(1.04)

    navy = Image.new("RGB", (SIZE, SIZE), (10, 22, 42))
    # Soft corner frame only — never washes the middle
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
