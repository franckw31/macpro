#!/usr/bin/env python3
"""
Génère les icônes CardEventPro en ajoutant un badge "PRO" doré
à partir de l'icône CardEvent existante.
"""

from PIL import Image, ImageDraw, ImageFont
import os

SOURCE = "/Users/franck/Desktop/Viendez.com/xcode/CardEvent/CardEvent/Assets.xcassets/AppIcon.appiconset/icon-1024.png"
DEST_DIR = "/Users/franck/Desktop/Viendez.com/xcode/CardEventPro/CardEventPro/Assets.xcassets/AppIcon.appiconset"

SIZES = {
    "icon-20@2x.png":       40,
    "icon-20@3x.png":       60,
    "icon-29@2x.png":       58,
    "icon-29@3x.png":       87,
    "icon-40@2x.png":       80,
    "icon-40@3x.png":       120,
    "icon-60@2x.png":       120,
    "icon-60@3x.png":       180,
    "icon-ipad-20@1x.png":  20,
    "icon-ipad-20@2x.png":  40,
    "icon-ipad-29@1x.png":  29,
    "icon-ipad-29@2x.png":  58,
    "icon-ipad-40@1x.png":  40,
    "icon-ipad-40@2x.png":  80,
    "icon-ipad-76@1x.png":  76,
    "icon-ipad-76@2x.png":  152,
    "icon-ipad-83_5@2x.png":167,
    "icon-1024.png":         1024,
}

# Polices Bold disponibles sur macOS (dans l'ordre de préférence)
FONT_PATHS = [
    "/System/Library/Fonts/HelveticaNeue.ttc",
    "/System/Library/Fonts/Helvetica.ttc",
    "/System/Library/Fonts/SFNSDisplay.ttf",
    "/Library/Fonts/Arial Bold.ttf",
    "/System/Library/Fonts/Supplemental/Arial Bold.ttf",
    "/System/Library/Fonts/Supplemental/Futura.ttc",
    "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
]

def get_font(size):
    for fp in FONT_PATHS:
        if os.path.exists(fp):
            try:
                return ImageFont.truetype(fp, size)
            except Exception:
                continue
    return ImageFont.load_default()

def add_pro_badge(img):
    """
    Ajoute un badge doré 'PRO' en bas à droite de l'icône 1024x1024.
    Design : rectangle arrondi doré avec ombre, texte bleu marine en gras.
    """
    W, H = img.size
    assert W == 1024 and H == 1024, f"Taille source inattendue: {W}x{H}"

    result = img.convert("RGBA")
    overlay = Image.new("RGBA", result.size, (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)

    # ── Dimensions du badge ──────────────────────────────────────────
    badge_w  = 310
    badge_h  = 112
    margin   = 46
    radius   = 30

    x1 = W - badge_w - margin
    y1 = H - badge_h - margin
    x2 = W - margin
    y2 = H - margin

    # ── Ombre portée ─────────────────────────────────────────────────
    for i in range(8, 0, -1):
        alpha = int(160 * (i / 8) ** 2)
        draw.rounded_rectangle(
            [x1 + i, y1 + i, x2 + i, y2 + i],
            radius=radius, fill=(0, 0, 0, alpha)
        )

    # ── Fond doré (dégradé simulé par deux couches) ───────────────────
    # Couche principale : or chaud
    draw.rounded_rectangle([x1, y1, x2, y2], radius=radius, fill=(218, 165, 32, 255))
    # Reflet lumineux en haut
    draw.rounded_rectangle(
        [x1 + 3, y1 + 3, x2 - 3, y1 + badge_h // 2],
        radius=radius - 2,
        fill=(255, 220, 80, 80)
    )
    # Bordure intérieure fine or clair
    draw.rounded_rectangle(
        [x1 + 2, y1 + 2, x2 - 2, y2 - 2],
        radius=radius - 1,
        outline=(255, 235, 120, 180),
        width=2
    )

    # ── Texte "PRO" ───────────────────────────────────────────────────
    font_size = 74
    font = get_font(font_size)
    text = "PRO"

    bbox = draw.textbbox((0, 0), text, font=font)
    tw = bbox[2] - bbox[0]
    th = bbox[3] - bbox[1]
    tx = x1 + (badge_w - tw) // 2 - bbox[0]
    ty = y1 + (badge_h - th) // 2 - bbox[1]

    # Ombre du texte
    draw.text((tx + 2, ty + 2), text, font=font, fill=(0, 0, 0, 80))
    # Texte principal bleu marine
    draw.text((tx, ty), text, font=font, fill=(10, 20, 60, 255))

    # ── Composition ───────────────────────────────────────────────────
    result = Image.alpha_composite(result, overlay)
    return result.convert("RGB")


def main():
    print(f"Source : {SOURCE}")
    print(f"Destination : {DEST_DIR}\n")

    src = Image.open(SOURCE)
    master = add_pro_badge(src)

    # Sauvegarde preview dans /tmp
    preview_path = "/tmp/icon_pro_preview_1024.png"
    master.save(preview_path, "PNG")
    print(f"Preview 1024x1024 : {preview_path}\n")

    # Génération de toutes les tailles
    for filename, size in sorted(SIZES.items(), key=lambda x: -x[1]):
        dest_path = os.path.join(DEST_DIR, filename)
        resized = master.resize((size, size), Image.LANCZOS)
        resized.save(dest_path, "PNG")
        print(f"✓ {filename:30s}  {size}×{size} px")

    print("\n✅ Toutes les icônes générées avec succès.")


if __name__ == "__main__":
    main()
