# WordPress.org Plugin Assets

This directory holds the visual assets required by WordPress.org for the plugin listing.
They are uploaded to the SVN `assets/` directory (not bundled in the plugin ZIP).

**All filenames must be lowercase.** WordPress.org rejects mixed-case filenames.

---

## Required Assets

| File | Dimensions | Max Size | Format | Purpose |
|---|---|---|---|---|
| `icon-128x128.png` | 128 x 128 px | 1 MB | PNG (no transparency) | Plugin icon — 1x, shown in search results and plugin list |
| `icon-256x256.png` | 256 x 256 px | 1 MB | PNG (no transparency) | Plugin icon — 2x HiDPI/Retina |
| `banner-772x250.png` | 772 x 250 px | 4 MB | PNG or JPG | Directory listing header — 1x |
| `banner-1544x500.png` | 1544 x 500 px | 4 MB | PNG or JPG | Directory listing header — 2x HiDPI/Retina |
| `screenshot-1.png` | Min 1200 px wide | 10 MB | PNG or JPG | Screenshot 1: the plugin settings page inside WP Admin |
| `screenshot-2.png` | Min 1200 px wide | 10 MB | PNG or JPG | Screenshot 2: Claude Desktop / Claude Code connected to the site via MCP |

> SVN `assets/` path reference: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/

---

## Brand Style Guide

### Product
- **Name:** Bricks MCP
- **Tagline:** "Talk to your website. It listens."
- **Domain:** aiforbricks.com

### Color Palette

| Role | Value | Notes |
|---|---|---|
| Background (dark) | `#0D1117` | Near-black, feels like a code editor / AI terminal |
| Background (light alt) | `#FFFFFF` | Clean white for screenshot contexts |
| Primary accent | `#00C2FF` | Electric cyan — AI, intelligence, speed |
| Secondary accent | `#7C3AED` | Deep violet — premium, creative |
| Text (on dark) | `#F0F6FF` | Off-white, easy on the eyes |
| Text (on light) | `#111827` | Near-black body text |
| Bricks brand ref | `#F5631A` | Bricks Builder's orange — use sparingly as a nod/bridge |

### Typography
- Headlines: A geometric sans-serif — Inter, Geist, or similar. Bold/black weight.
- Body: Inter Regular or similar system-safe font.
- Avoid decorative or script fonts.

### Logo / Icon Concept

Two concept directions — pick one and be consistent across all assets:

**Option A — Brick + Circuit**
A stylised "B" (for Bricks) constructed from brick shapes, with a subtle neural/circuit line pattern integrated into the letterform. Accent color: electric cyan `#00C2FF` on a dark navy background.

**Option B — Speech Bubble + Brick**
A minimal chat/speech bubble whose tail terminates in a small brick or the Bricks "B" mark. Implies conversation with the builder. Works well as a flat icon. Accent color: violet `#7C3AED` or cyan on dark.

For both options: the icon should read clearly at 128 px — keep shapes bold, avoid fine lines.

---

## Per-Asset Design Brief

### icon-128x128.png / icon-256x256.png

- Square canvas, no transparency (WordPress.org will add rounded corners via CSS)
- Background: solid dark `#0D1117` or a short diagonal gradient from `#0D1117` to `#0A1628`
- Centered logo mark (Option A or B above)
- Do NOT include the product name text — it is too small to read
- Deliver both sizes from the same master vector; export at 128 px and 256 px

### banner-772x250.png / banner-1544x500.png

- Landscape hero banner shown at the top of the plugin's WordPress.org page
- Background: dark `#0D1117` with a subtle radial glow or grid/circuit texture in `#00C2FF` at ~10% opacity
- Left side: logo mark + product name "Bricks MCP" in white, large bold type
- Right side (optional): light illustration of a chat interface overlaid on a Bricks canvas, or abstract neural lines
- Tagline below name: *"Talk to your website. It listens."* in `#00C2FF` or `#F0F6FF`
- Keep critical content away from the outer 40 px edge — it may be cropped on smaller displays
- Deliver both sizes from the same master; the 1544 x 500 px version is simply 2x

### screenshot-1.png

**Subject:** The plugin settings page inside WordPress Admin (`Settings > Bricks MCP`).

Capture or mockup should show:
- The WordPress Admin sidebar visible on the left
- The settings panel including: Enable/Disable toggle, API key field (value masked), rate limiting controls
- A clean browser chrome (no personal bookmarks bar)
- Use the default WP Admin color scheme (not a custom admin theme)
- Recommended: 1440 x 900 px browser window, then crop to content or export full-page

**Caption (used in readme.txt):** `Settings page — configure authentication and rate limiting.`

### screenshot-2.png

**Subject:** Claude Desktop (or Claude Code CLI) actively communicating with a Bricks Builder site via MCP.

Two acceptable approaches:

1. **Real screenshot** — Claude Desktop open with an actual conversation where the user asks Claude to create or modify a Bricks page, and the MCP tool calls are visible in the UI (the small tool-use disclosures Claude Desktop shows).

2. **Designed mockup** — A split composition:
   - Left panel: a simplified chat UI showing a user message like *"Add a hero section with a headline and CTA button"* and Claude's response confirming it was done.
   - Right panel: a browser window showing the resulting Bricks page live.
   - Both panels sit on the dark banner background.

**Caption (used in readme.txt):** `Claude Desktop connected to your site — describe changes in plain English.`

---

## Delivery Checklist

- [ ] `icon-128x128.png` exported and verified at exactly 128 x 128 px
- [ ] `icon-256x256.png` exported and verified at exactly 256 x 256 px
- [ ] `banner-772x250.png` exported and verified at exactly 772 x 250 px
- [ ] `banner-1544x500.png` exported and verified at exactly 1544 x 500 px
- [ ] `screenshot-1.png` — real WP Admin screenshot or faithful mockup
- [ ] `screenshot-2.png` — Claude Desktop screenshot or designed mockup
- [ ] All filenames are lowercase
- [ ] No file exceeds its size limit
- [ ] No PNG uses transparency (icons/banners) unless intentional for screenshots

---

## License

Bricks MCP is licensed under the [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).
