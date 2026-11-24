#!/usr/bin/env python3
"""
Generate shop product images for selected catalogue categories from Unsplash.
"""

from __future__ import annotations

import io
import json
import urllib.request
from pathlib import Path

from PIL import Image, ImageDraw, ImageEnhance, ImageFilter, ImageOps

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "public" / "images" / "products"
CATALOGUE = ROOT / "database" / "data" / "catalogue_launch.json"
SIZE = 1024

# slug -> Unsplash CDN URL (Unsplash License)
SOURCES: dict[str, str] = {
    # Perfumes & Fragrances
    "designer-inspired-men-s-edp-100ml": "https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=1600&q=80",
    "branded-men-s-edt-100ml": "https://images.unsplash.com/photo-1594035910387-fea47794261f?auto=format&fit=crop&w=1600&q=80",
    "designer-inspired-women-s-edp-100ml": "https://images.unsplash.com/photo-1563170351-be82bc888aa4?auto=format&fit=crop&w=1600&q=80",
    "branded-women-s-edt-100ml": "https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?auto=format&fit=crop&w=1600&q=80",
    "concentrated-perfume-oil-12ml": "https://images.unsplash.com/photo-1615634260167-c8cdede054de?auto=format&fit=crop&w=1600&q=80",
    "fruity-floral-body-mist-250ml": "https://images.unsplash.com/photo-1608571423902-eed4a5ad8108?auto=format&fit=crop&w=1600&q=80",
    "men-s-perfume-deodorant-gift-set": "https://images.unsplash.com/photo-1708265020242-8646f0d15d01?auto=format&fit=crop&w=1600&q=80",
    "women-s-perfume-lotion-gift-set": "https://images.unsplash.com/photo-1595425970377-c9703cf48b6d?auto=format&fit=crop&w=1600&q=80",
    "4-piece-travel-perfume-set": "https://images.unsplash.com/photo-1774280347934-9c74dff6ab2e?auto=format&fit=crop&w=1600&q=80",
    "soy-scented-candle-single": "https://images.unsplash.com/photo-1603006905003-be475563bc59?auto=format&fit=crop&w=1600&q=80",
    # Fashion Accessories
    "structured-tote-handbag": "https://images.unsplash.com/photo-1590874103328-eac38a683ce7?auto=format&fit=crop&w=1600&q=80",
    "small-crossbody-bag": "https://images.unsplash.com/photo-1569484221992-2a453658fff3?auto=format&fit=crop&w=1600&q=80",
    "men-s-leather-bifold-wallet": "https://images.unsplash.com/photo-1620109176813-e91290f6c795?auto=format&fit=crop&w=1600&q=80",
    "women-s-compact-wallet": "https://images.unsplash.com/photo-1563013544-824ae1b704d3?auto=format&fit=crop&w=1600&q=80",
    "classic-polarised-sunglasses": "https://images.unsplash.com/photo-1511499767150-a48a237f0083?auto=format&fit=crop&w=1600&q=80",
    # Jewellery
    "stainless-steel-pendant-necklace": "https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?auto=format&fit=crop&w=1600&q=80",
    "beaded-charm-bracelet": "https://images.unsplash.com/photo-1611591437281-460bfbe1220a?auto=format&fit=crop&w=1600&q=80",
    "stud-earring-set-5-pair-pack": "https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?auto=format&fit=crop&w=1600&q=80",
    # Women's Beauty & Personal
    "makeup-brush-set-10-12-pcs": "https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=1600&q=80",
    "silk-scrunchie-set": "https://images.unsplash.com/photo-1522338140262-f46f5913618a?auto=format&fit=crop&w=1600&q=80",
    "claw-clips-set-assorted": "https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=1600&q=80",
    # Men's Grooming & Accessories
    "men-s-beard-grooming-kit": "https://images.unsplash.com/photo-1697301440098-a95844278c2b?auto=format&fit=crop&w=1600&q=80",
    "beard-oil-comb-set": "https://images.unsplash.com/photo-1673350963924-cb4267d9b6eb?auto=format&fit=crop&w=1600&q=80",
    "men-s-minimalist-card-wallet": "https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=1600&q=80",
    "grooming-gift-box-wallet-belt-cufflinks": "https://images.unsplash.com/photo-1549465220-1a8b9238cd48?auto=format&fit=crop&w=1600&q=80",
    # Gifts & Hampers
    "birthday-gift-box": "https://images.unsplash.com/photo-1549465220-1a8b9238cd48?auto=format&fit=crop&w=1600&q=80",
    "his-hers-set": "https://images.unsplash.com/photo-1513885535751-8b9238bd345a?auto=format&fit=crop&w=1600&q=80",
    "executive-man": "https://images.unsplash.com/photo-1607083206869-4c7672e72a8a?auto=format&fit=crop&w=1600&q=80",
    "executive-woman": "https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?auto=format&fit=crop&w=1600&q=80",
    "date-night-box": "https://images.unsplash.com/photo-1518199266791-5375a83190b7?auto=format&fit=crop&w=1600&q=80",
    "self-care-box": "https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=1600&q=80",
    "new-mum-box": "https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4?auto=format&fit=crop&w=1600&q=80",
    "teen-girl-box": "https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?auto=format&fit=crop&w=1600&q=80",
    "teen-boy-box": "https://images.unsplash.com/photo-1607082349566-187342175e2f?auto=format&fit=crop&w=1600&q=80",
    "young-gentleman": "https://images.unsplash.com/photo-1607083206869-4c7672e72a8a?auto=format&fit=crop&w=1600&q=80",
    "young-lady": "https://images.unsplash.com/photo-1513885535751-8b9238bd345a?auto=format&fit=crop&w=1600&q=80",
    "kids-birthday-box": "https://images.unsplash.com/photo-1530103862676-de8c9debad1d?auto=format&fit=crop&w=1600&q=80",
    "back-to-school-gadget-box": "https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=1600&q=80",
    "travel-essentials": "https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=1600&q=80",
    "grooming-box": "https://images.unsplash.com/photo-1673350963997-fc802e774580?auto=format&fit=crop&w=1600&q=80",
    "perfume-watch-set": "https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=1600&q=80",
    "watch-wallet-set": "https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=1600&q=80",
    "mother-s-day-box": "https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=1600&q=80",
    "father-s-day-box": "https://images.unsplash.com/photo-1607083206869-4c7672e72a8a?auto=format&fit=crop&w=1600&q=80",
    "anniversary-box": "https://images.unsplash.com/photo-1518199266791-5375a83190b7?auto=format&fit=crop&w=1600&q=80",
    "corporate-executive-box": "https://images.unsplash.com/photo-1549465220-1a8b9238cd48?auto=format&fit=crop&w=1600&q=80",
    "bridesmaid-box": "https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=1600&q=80",
    "beauty-hamper": "https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1600&q=80",
    "romance-hamper": "https://images.unsplash.com/photo-1518199266791-5375a83190b7?auto=format&fit=crop&w=1600&q=80",
    "client-appreciation-hamper": "https://images.unsplash.com/photo-1513885535751-8b9238bd345a?auto=format&fit=crop&w=1600&q=80",
    "new-employee-welcome-box": "https://images.unsplash.com/photo-1607083206869-4c7672e72a8a?auto=format&fit=crop&w=1600&q=80",
    "end-of-year-hamper": "https://images.unsplash.com/photo-1513885535751-8b9238bd345a?auto=format&fit=crop&w=1600&q=80",
    "christmas-hamper": "https://images.unsplash.com/photo-1512389142860-9c449e58a543?auto=format&fit=crop&w=1600&q=80",
    "valentine-s-couple-box": "https://images.unsplash.com/photo-1518199266791-5375a83190b7?auto=format&fit=crop&w=1600&q=80",
    "graduation-box": "https://images.unsplash.com/photo-1627556704302-624286467c65?auto=format&fit=crop&w=1600&q=80",
    "baby-shower-hamper": "https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4?auto=format&fit=crop&w=1600&q=80",
    "wedding-guest-gift-box": "https://images.unsplash.com/photo-1511795409834-ef04bbd61622?auto=format&fit=crop&w=1600&q=80",
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


def process(slug: str, url: str) -> Path:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    filename = f"{slug}.png"
    print(f"-> {filename}")
    raw = download(url)
    photo = Image.open(io.BytesIO(raw))
    final = restyle(photo)
    out_path = OUT_DIR / filename
    final.save(out_path, format="PNG", optimize=True)
    print(f"  saved {out_path.relative_to(ROOT)}")
    return out_path


def update_catalogue() -> None:
    data = json.loads(CATALOGUE.read_text(encoding="utf-8"))
    updated = 0
    for product in data.get("products", []):
        slug = product.get("slug")
        if slug in SOURCES:
            product["image"] = f"/images/products/{slug}.png"
            updated += 1
    CATALOGUE.write_text(json.dumps(data, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"catalogue updated: {updated} products")


def main() -> None:
    failed: list[str] = []
    for slug, url in SOURCES.items():
        try:
            process(slug, url)
        except Exception as exc:  # noqa: BLE001 - collect failures, continue batch
            failed.append(f"{slug}: {exc}")
            print(f"  FAILED {slug}: {exc}")

    if failed:
        print(f"\n{len(failed)} failed:")
        for item in failed:
            print(f"  - {item}")
        raise SystemExit(1)

    update_catalogue()
    print("Done.")


if __name__ == "__main__":
    main()
