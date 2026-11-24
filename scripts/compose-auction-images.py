#!/usr/bin/env python3
"""
Download Unsplash source photos, square-crop to 1024x1024, and apply a
light corner vignette only — product stays sharp with no centre haze.

Supports optional multi-image collages (e.g. solar panel + battery + inverter).
"""

from __future__ import annotations

import io
import urllib.request
from pathlib import Path

from PIL import Image, ImageDraw, ImageEnhance, ImageFilter, ImageOps

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "public" / "images" / "auctions"
SIZE = 1024

# Preferred client/local masters (used when present). Values: path, or (path, mode, centering)
# mode: "cover" | "contain"
LOCAL_SOURCES: dict[str, Path | tuple[Path, str, tuple[float, float]]] = {
    "dairy-cow.png": (
        Path(r"C:/Users/ERICK M/Downloads/New Images/Dairy Cow.jfif"),
        "cover",
        (0.40, 0.52),
    ),
    "solar-kit.png": (
        Path(r"C:/Users/ERICK M/Downloads/New Images/Solar Kit updated.jpg"),
        "contain",
        (0.5, 0.5),
    ),
    "tv-woofer.png": (
        Path(r"C:/Users/ERICK M/Downloads/New Images/TV & Woofer.jpg"),
        "contain",
        (0.5, 0.5),
    ),
    "school-fee.png": (
        Path(r"C:/Users/ERICK M/Downloads/New Images/School Fees.png"),
        "contain",
        (0.5, 0.5),
    ),
    "water-tank.png": (
        Path(r"C:/Users/ERICK M/Downloads/New Images/Water Tank.jfif"),
        "contain",
        (0.5, 0.5),
    ),
    "iphone-16.png": (
        Path(r"C:/Users/ERICK M/Downloads/New Images/iPhone 16.webp"),
        "contain",
        (0.5, 0.5),
    ),
    "iphone-17.png": (
        Path(r"C:/Users/ERICK M/Downloads/New Images/iPhone 17.png"),
        "contain",
        (0.5, 0.5),
    ),
    "samsung-s24.png": (
        Path(r"C:/Users/ERICK M/Downloads/New Images/samsung s24 ultra.jfif"),
        "contain",
        (0.5, 0.5),
    ),
    "samsung-s25.png": (
        Path(r"C:/Users/ERICK M/Downloads/New Images/samsung s25 ultra.webp"),
        "contain",
        (0.5, 0.5),
    ),
}

# Remote fallbacks (optional centering tuple biases the square crop)
SOURCES: dict[str, str | tuple[str, tuple[float, float]]] = {
    "samsung-phone.png": "https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=1600&q=80",
    "tv-woofer.png": (
        "https://images.unsplash.com/photo-1461151304267-38535e780c79?auto=format&fit=crop&w=1600&q=80",
        (0.72, 0.62),
    ),
    "dairy-cow.png": "https://images.unsplash.com/photo-1446126102442-f6b2b73257fd?auto=format&fit=crop&w=1600&q=80",
    "rent-keys.png": "https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=1600&q=80",
    "iphone-16.png": "https://images.unsplash.com/photo-1695048133142-1a20484d2569?auto=format&fit=crop&w=1600&q=80",
    "iphone-17.png": "https://images.unsplash.com/photo-1695048133142-1a20484d2569?auto=format&fit=crop&w=1600&q=80",
    "samsung-s24.png": "https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?auto=format&fit=crop&w=1600&q=80",
    "samsung-s25.png": "https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?auto=format&fit=crop&w=1600&q=80",
    "school-fee.png": "https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=1600&q=80",
    "water-tank.png": "https://images.unsplash.com/photo-1759062761071-29fd6a608b21?auto=format&fit=crop&w=1600&q=80",
    "magic-box.png": "https://images.unsplash.com/photo-1549465220-1a8b9238cd48?auto=format&fit=crop&w=1600&q=80",
    "dollar-shop.png": "https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?auto=format&fit=crop&w=1600&q=80",
}

# Multi-source collages rendered as one square asset (fallback when no local solar master)
COLLAGES: dict[str, list[str]] = {
    "solar-kit.png": [
        "https://images.unsplash.com/photo-1508514177221-188b1cf16e9d?auto=format&fit=crop&w=1200&q=80",
        "https://images.pexels.com/photos/257736/pexels-photo-257736.jpeg?auto=compress&cs=tinysrgb&w=1200",
        "https://images.pexels.com/photos/9875416/pexels-photo-9875416.jpeg?auto=compress&cs=tinysrgb&w=1200",
    ],
}


def square_cover(img: Image.Image, size: int = SIZE, centering: tuple[float, float] = (0.5, 0.45)) -> Image.Image:
    img = ImageOps.exif_transpose(img).convert("RGB")
    return ImageOps.fit(img, (size, size), method=Image.Resampling.LANCZOS, centering=centering)


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


def restyle(
    photo: Image.Image,
    centering: tuple[float, float] = (0.5, 0.45),
    mode: str = "cover",
) -> Image.Image:
    photo = ImageOps.exif_transpose(photo).convert("RGB")
    if max(photo.size) < 900:
        scale = 900 / max(photo.size)
        photo = photo.resize(
            (int(photo.width * scale), int(photo.height * scale)),
            Image.Resampling.LANCZOS,
        )

    if mode == "contain":
        fitted = ImageOps.contain(photo, (SIZE, SIZE), method=Image.Resampling.LANCZOS)
        subject = Image.new("RGB", (SIZE, SIZE), (10, 22, 42))
        subject.paste(fitted, ((SIZE - fitted.width) // 2, (SIZE - fitted.height) // 2))
    else:
        subject = square_cover(photo, centering=centering)

    subject = ImageEnhance.Contrast(subject).enhance(1.06)
    subject = ImageEnhance.Color(subject).enhance(1.05)
    subject = subject.filter(ImageFilter.UnsharpMask(radius=1.2, percent=110, threshold=2))
    navy = Image.new("RGB", (SIZE, SIZE), (10, 22, 42))
    return Image.composite(subject, navy, corner_vignette())


def download(url: str) -> bytes:
    req = urllib.request.Request(
        url,
        headers={"User-Agent": "UniqBidImagePipeline/1.0 (local asset prep)"},
    )
    with urllib.request.urlopen(req, timeout=60) as response:
        return response.read()


def collage_three(urls: list[str]) -> Image.Image:
    """Hero panel on top 60%, battery + inverter side-by-side on bottom 40%."""
    assert len(urls) == 3
    panels = [Image.open(io.BytesIO(download(url))).convert("RGB") for url in urls]

    canvas = Image.new("RGB", (SIZE, SIZE), (10, 22, 42))
    top_h = int(SIZE * 0.58)
    gap = 10
    bottom_h = SIZE - top_h - gap
    half_w = (SIZE - gap) // 2

    top = ImageOps.fit(panels[0], (SIZE, top_h), method=Image.Resampling.LANCZOS, centering=(0.5, 0.4))
    left = ImageOps.fit(panels[1], (half_w, bottom_h), method=Image.Resampling.LANCZOS, centering=(0.5, 0.5))
    right = ImageOps.fit(panels[2], (SIZE - half_w - gap, bottom_h), method=Image.Resampling.LANCZOS, centering=(0.5, 0.5))

    canvas.paste(top, (0, 0))
    canvas.paste(left, (0, top_h + gap))
    canvas.paste(right, (half_w + gap, top_h + gap))

    # Soft navy frame
    return Image.composite(canvas, Image.new("RGB", (SIZE, SIZE), (10, 22, 42)), corner_vignette())


def process_local(filename: str, spec: Path | tuple[Path, str, tuple[float, float]]) -> Path | None:
    if isinstance(spec, tuple):
        path, mode, centering = spec
    else:
        path, mode, centering = spec, "cover", (0.5, 0.45)

    if not path.exists():
        return None

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    print(f"-> {filename} (local: {path.name})")
    photo = Image.open(path)
    final = restyle(photo, centering=centering, mode=mode)
    out_path = OUT_DIR / filename
    final.save(out_path, format="PNG", optimize=True)
    print(f"  saved {out_path.relative_to(ROOT)} ({final.size[0]}x{final.size[1]})")
    return out_path


def process(filename: str, source: str | tuple[str, tuple[float, float]]) -> Path:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    if isinstance(source, tuple):
        url, centering = source
    else:
        url, centering = source, (0.5, 0.45)

    print(f"-> {filename}")
    raw = download(url)
    photo = Image.open(io.BytesIO(raw))
    final = restyle(photo, centering=centering, mode="cover")
    out_path = OUT_DIR / filename
    final.save(out_path, format="PNG", optimize=True)
    print(f"  saved {out_path.relative_to(ROOT)} ({final.size[0]}x{final.size[1]})")
    return out_path


def process_collage(filename: str, urls: list[str]) -> Path:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    print(f"-> {filename} (collage)")
    final = collage_three(urls)
    out_path = OUT_DIR / filename
    final.save(out_path, format="PNG", optimize=True)
    print(f"  saved {out_path.relative_to(ROOT)} ({final.size[0]}x{final.size[1]})")
    return out_path


def main() -> None:
    done: set[str] = set()

    for filename, spec in LOCAL_SOURCES.items():
        if process_local(filename, spec) is not None:
            done.add(filename)

    for filename, source in SOURCES.items():
        if filename in done:
            continue
        process(filename, source)

    for filename, urls in COLLAGES.items():
        if filename in done:
            continue
        process_collage(filename, urls)

    print("Done.")


if __name__ == "__main__":
    main()