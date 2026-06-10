# Changelog — WC Personalisation Panel
> All notable changes to this plugin are documented here.
> Format: [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
> Versioning: [Semantic Versioning](https://semver.org/)

---

## [0.7.5] — 2026-06
### Added
- **4-side padding & margin controls** — trigger button padding and margin now use
  a DevTools-style box-model UI (Top centred above, Left · box · Right in the
  middle, Bottom below) instead of paired V/H fields. Each side is independently
  controllable from the Design settings tab.
- CSS custom properties renamed to 4-side tokens (`--wcpp-btn-pad-top/right/bottom/left`,
  `--wcpp-btn-margin-top/right/bottom/left`) for clean shorthand output.
- `.wcpp-sides` admin CSS component: box-model layout with styled number inputs,
  focus ring, and a labelled centre box.

## [0.7.4] — 2026-06
### Added
- **Button margin bottom** setting — shown alongside margin top on a single row
  ("Top / Bottom") so both sides are controlled in one place.

## [0.7.3] — 2026-06
### Added
- **9 new design settings** — all now driven by CSS custom properties so changes
  take effect without touching code:
  - *Trigger button*: Padding V, Padding H, Margin top, Font size, Border width,
    Letter spacing
  - *Panel content*: Content padding (left/right inside the drawer)
  - *Footer buttons*: Border radius, Font size, Padding V (top/bottom)

## [0.7.2] — 2026-06
### Added
- **Button animation setting** — choose from 6 styles in Panel Settings → Design:
  Lift (default), Pulse (idle glow ring), Shine (hover sweep), Scale (hover grow),
  Bounce (springy hover), None. Rendered as a `wcpp-button--anim-*` CSS class.

## [0.7.1] — 2026-06
### Changed
- **Non-returnable default**: changed to OFF — the notice is opt-in (admin must
  enable it in Behaviour settings before it shows in the cart).
- **UI polish**: header separator, pill-style step badges, selected card tint,
  larger swatches, left-accent review cards, better mobile padding, stronger
  footer shadow, improved non-returnable notice styling in cart.
- Panel radius increased from 8 px to 10 px; progress bar thicker (3 px);
  overlay blur deepened slightly.
### Fixed
- Admin step builder "Add Step" dropdown was missing the **Colours** option
  (only worked on existing PHP-rendered steps). Added `typeColor` i18n key and
  `<option value="color">` in `buildStepHTML()`.
- `WCPP_Price_Calculator::calculate()` used wrong key `choice_price` instead of
  `price` when summing selections (method was unused in main flow; fixed for
  future use).

## [0.7.0] — 2026-06
### Changed
- **Wizard flow**: after picking a placement, ALL its steps now show stacked in
  one scrollable panel (Step 1, Step 2, Step 3…) like the OvH reference, instead
  of one step at a time. Footer "Continue" validates all steps at once and
  scrolls to the first unanswered one. Progress reflects steps filled.
- Modernised admin builder + Panel Settings page UI.
- Replaced browser alert() with a branded toast.
### Fixed
- Order screen no longer shows raw `_wcpp_*` meta (hidden + clean block in admin).

## [0.6.7] — 2026-06
### Added
- **Colour step type**: pick a hex colour + name in the builder; front-end
  shows premium circular swatches.
- **Variable product handling**: blocks the panel until a variation is chosen
  and passes variation attributes to add_to_cart; base price = variation price.
### Changed
- Cards are `<div role=button>` not `<button>` (buttons clipped tall images).
- Placement picker = full-width image cards; images shown in full (no crop).
- Removed the Review back button (per-placement **Edit** covers changes).
### Fixed
- Image clipping (root cause: `<button>` + cache); simplified image CSS.
- **Cache rule documented**: bump WCPP_VERSION on every CSS/JS change.
- Back button returns to correct previous screen.

## [0.6.0] — 2026-06

### Added
- **Personalisation Sets** as a CPT under a top-level "Personalisation" menu.
- **Placements → Steps → Choices** model: each placement (Front/Back…) owns
  its own steps, so choices can differ per placement (e.g. 2 colours front,
  3 back). Solves front/back with different text + different options.
- **Text-input step type** (monogram/name/initials) with placeholder, max
  characters and optional price; validated + length-capped server-side.
- **Placement images** + a dedicated full-width image-card picker (OvH style).
- **Duplicate placement** button in the builder (build Front → duplicate → Back).
- **Flat set price** (one-time fee per set) on top of per-choice prices.
- **Add another placement** + **Edit / Remove** in the Review screen
  (each placement picked once per order).
- Global **Panel Settings** page (Design + Behaviour tabs): full control of
  button, drawer, header, progress, cards, footer, pricing display.
- Button placement options incl. default "under all buttons"
  (after Add to Cart & Buy Now).
- Category-level + per-product set assignment.
- WooCommerce currency formatting in the panel; HPOS compatibility; uninstall.php.

### Changed
- Settings split: global design lives in Panel Settings (not per set).
- Default card layout = 3-column grid; placement picker uses image cards.
- Order/email meta hidden (underscore keys) + rendered as a clean block.

### Fixed
- Activation fatal (activator loaded before hooks).
- Settings tab save no longer wipes the other tab.
- Price never compounds (idempotent base + add-on).
- Placement card image clipping (fixed-height box + object-fit contain).
- Back button returns to the correct previous screen on the first step.
- Site-visibility access regression (reverted capability/HPOS changes to
  WC-standard product capability type).

---

## [1.0.0] — TBD
> First production release.

### Added
- "Add Personalisation" button on WooCommerce product pages
- Slide-in drawer with 6-step wizard (Location → Type → Text/Symbol → Font → Colour → Summary)
- Personalisation data attached to cart items and order line items
- Personalisation details shown in cart, mini-cart, admin orders, and order emails
- Admin global settings page under WooCommerce menu
- Per-product personalisation meta box
- Flat fee and per-character pricing with server-side recalculation
- Cart de-duplication (unique key per personalisation)
- Variable product support (variation ID carried in payload)
- Non-returnable item flag with admin order and email notice
- Guest checkout support
- Shortcode: `[wcpp_button]`
- Template tag: `wcpp_render_button()`
- Theme-overridable templates (`yourtheme/wcpp/panel.php`, `yourtheme/wcpp/button.php`)
- Elementor widget (optional, loaded only when Elementor is active)
- Translation-ready with `.pot` file
- Compatible with: WoodMart, Elessi, Twenty Twenty-Five
- Compatible with: Elementor on and off
- Zero external dependencies

---

*Start adding entries above this line as work progresses.*
