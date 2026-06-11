# WC Personalisation Panel

> A slide-in personalisation drawer for WooCommerce product pages — let customers customise before they add to cart.

Built for embroidery, monogramming, engraving, and any product that needs customer personalisation. Clean, mobile-first drawer UI with a step-by-step wizard, live pricing preview, and full WooCommerce order integration.

---

## Features

- **Slide-in drawer** — triggered from the product page, fully responsive
- **Step-by-step wizard** — placement picker → choices → review before adding to cart
- **Three step types** — Choice (image cards), Colour (swatches), Text (free input with font/colour options)
- **Flexible pricing** — flat set fee + per-choice prices; charge once (one-time) or per unit ordered
- **Variable product support** — works with size/colour variations; base price captured from the selected variation
- **Multiple sets with priority** — assign different sets to different product categories; priority resolves overlaps
- **Cart & order summary** — personalisation details shown in cart, order confirmation, and emails
- **Reorder support** — restores personalisation from a previous order
- **Non-returnable notice** — configurable per set, shown in cart and order
- **Auto-updates from GitHub** — no wordpress.org required; updates appear in WP Admin → Plugins

---

## Requirements

| | Minimum |
|---|---|
| WordPress | 6.0+ |
| WooCommerce | 7.0+ |
| PHP | 7.4+ |

---

## Installation

1. Go to **Plugins → Add New → Upload Plugin**
2. Upload `wc-personalisation-panel-x.x.x.zip`
3. Click **Activate**

The **Personalisation** menu will appear in the WordPress admin sidebar.

---

## Quick Start

### 1. Create a Personalisation Set

Go to **Personalisation → Add New Set**

- Add one or more **placements** (e.g. Front, Back, Sleeve) — each with an image
- Inside each placement, add **steps**:
  - **Choice** — image cards the customer picks from
  - **Colour** — colour swatches
  - **Text** — free text input (supports font and colour options)
- Set a **flat fee** and choose **One-time** or **Per item** pricing mode
- Assign to product categories (or apply to all products)

### 2. Set Priority (optional)

If multiple sets could match the same product, set a **priority** (1 = highest). The lowest number wins. Default is 10.

### 3. Override per product (optional)

On any product edit page, use the **Personalisation Set** meta box to force a specific set for that product.

---

## How Pricing Works

| Mode | What it means |
|---|---|
| **One-time** | Flat fee + all choice prices charged once, regardless of quantity |
| **Per item** | Flat fee + all choice prices multiplied by quantity |

Prices are always calculated server-side. The browser never sends prices — only IDs.

---

## Settings

Go to **Personalisation → Panel Settings**

- **Design tab** — button style, drawer width, card layout, colours, animations
- **Behaviour tab** — enable/disable, non-returnable default, Elementor support

---

## Security

- Nonce-verified AJAX endpoint
- All prices re-derived server-side from set data — browser values are ignored
- Set resolved from the product server-side — posted set ID is only a cross-check
- Variation ownership validated — prevents base-price substitution attacks
- Duplicate step submissions rejected
- WooCommerce add-to-cart validation filter honoured
- All output escaped; order meta keys underscore-prefixed (hidden from raw display)

---

## WooCommerce Compatibility

| Feature | Status |
|---|---|
| High-Performance Order Storage (HPOS) | Compatible |
| Classic cart & checkout | Compatible |
| Block-based cart & checkout | Personalisation display not yet supported |

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full version history.

---

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)

---

*Built by [Cloudycode](https://github.com/VJ-Ranga)*
