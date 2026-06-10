# Changelog — WC Personalisation Panel
> All notable changes to this plugin are documented here.
> Format: [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
> Versioning: [Semantic Versioning](https://semver.org/)

---

## [0.7.13] — 2026-06
### Security / Fixed
- **[Medium] CPT capability mismatch fully resolved** — the CPT now uses
  `capability_type => 'wcpp_personalisation'` with an explicit `capabilities`
  array mapping every access point (`edit_post`, `read_post`, `delete_post`,
  `publish_posts`, `create_posts`, etc.) directly to `manage_woocommerce`, and
  `map_meta_cap => false`. Previously `capability_type => 'product'` allowed
  custom roles with `edit_products` but not `manage_woocommerce` to reach the
  set editor via a direct URL even though the menu was not visible to them.
- **[Low] Plain-text email output fixed** — `woocommerce_order_item_meta_end`
  now registered with 4 accepted args (was 3). The 4th arg `$plain_text` passed
  by WooCommerce is checked at the top of `display_meta_end()`; if true the
  method returns early, preventing HTML markup from appearing as raw text in
  plain-text order emails.

## [0.7.12] — 2026-06
### Security / Fixed
- **[Medium] WooCommerce add-to-cart validation filter now applied** — the AJAX
  handler now runs `apply_filters('woocommerce_add_to_cart_validation', ...)` before
  calling `WC()->cart->add_to_cart()`, matching the behaviour of WooCommerce's own
  AJAX/form handlers. Third-party plugins that enforce purchase limits, subscription
  rules, or password-protected-product gates via this filter will now be respected.
- **[Medium] CPT save gate requires `manage_woocommerce`** — `save_all_meta()` now
  checks `current_user_can('manage_woocommerce')` in addition to `edit_post`. The
  CPT uses `capability_type => 'product'`, which means a custom role with
  `edit_products` but not `manage_woocommerce` could previously bypass the menu
  gate and save sets directly. Both capabilities are now required.
- **[Low] Negative prices rejected on save** — set fee, text-step price, and
  choice price are now clamped with `max(0, ...)` at save time. The HTML UI
  already has `min="0"` but a crafted POST could bypass it; server-side now
  enforces the floor.

## [0.7.11] — 2026-06
### Security
- **[High] Set-ID bypass closed** — `handle_add_to_cart()` previously accepted
  any posted `set_id` and loaded it directly via `get_set()`, bypassing the
  product-eligibility check entirely. The set is now always resolved from the
  product via `get($product_id)` (the authoritative path). A posted `set_id` is
  only accepted as a cross-check; a mismatch is rejected. This also restores the
  global `is_enabled()` master-switch gate which the old path skipped.
- **[High] `variation_id` not validated against the product** — a tampered
  `variation_id` from another product could capture a lower base price. The
  variation is now verified to exist, be a `variation` type, and have
  `get_parent_id() === $product_id`. Mismatches are rejected before the price
  is captured.
- **[Medium] Duplicate step submission bypass closed** — the step-count check
  (`count($selections) >= count($placement['steps'])`) could be satisfied by
  posting the same `step_id` twice, skipping other required steps. A
  `$seen_steps` guard now rejects any duplicate step ID — mirrors the existing
  `$seen_placements` guard already in place.
- **[Low] WC Blocks compatibility declaration corrected** — `cart_checkout_blocks`
  changed from `true` → `false`. Pricing and order persistence work correctly
  with Blocks, but cart-item personalisation display requires a Store API
  extension that is not yet implemented. Declaring `false` prevents a misleading
  green tick in WP Admin while the display gap exists.

## [0.7.10] — 2026-06
### Fixed
- **WooCommerce Blocks compatibility declared** — added `cart_checkout_blocks`
  feature declaration alongside the existing HPOS declaration. Removes the
  "plugin not declared compatible" admin warning on WC 7.6+. Price modification
  and order persistence already work with the WC Blocks Store API.
- **AJAX cart null-guard** — `handle_add_to_cart()` now checks `WC()->cart`
  is not null before calling `add_to_cart()`, preventing a fatal error on
  unusual server configurations where the WC cart object is unavailable.
- **Add-to-cart error messages** — when `add_to_cart()` returns false (e.g.
  product out of stock, not purchasable), the real WooCommerce error notice is
  now surfaced to the customer instead of a generic "try again" message.
- **Price calculator guard** — `apply_prices()` now uses `wp_doing_ajax()` and
  `REST_REQUEST` instead of `defined('DOING_AJAX')` so the idempotent price
  logic is correctly applied during WC Blocks REST API checkout calls.
- **Per-request set lookup cache** — `get_set_id()` now caches its result in a
  static property for the lifetime of the request, eliminating repeated
  `get_posts()` calls when the same product is resolved multiple times in one
  page load (product page + panel + mini-cart).
- **Uninstall transient cleanup** — `uninstall.php` now deletes the
  `wcpp_gh_release_cache` transient when the plugin is removed.

## [0.7.9] — 2026-06
### Added
- **GitHub releases update checker** — the plugin now polls the GitHub Releases
  API (`/repos/VJ-Ranga/WC-Personalisation-Panel/releases/latest`) every 12 hours
  and injects update info into the WordPress plugin-update transient. When a newer
  tag is published on GitHub, admins see the standard "update available" banner on
  the Plugins page and can one-click-update like any other plugin. A "Check for
  updates" link in the plugin row action links forces an immediate re-check.
  No external libraries — pure WP HTTP API + transient cache.

## [0.7.8] — 2026-06
### Changed
- **Padding & margin side by side** — "Button padding" and "Button margin"
  settings are now in a single row with both box-model controls next to each
  other (divider between them), halving the vertical space used.
- **Collapse toggle hover colour** — the placement ▲/▼ button now uses the
  same blue accent hover (background `#eef2ff`, border `#c7d2fe`, icon blue)
  as the Duplicate button, keeping all header actions visually consistent.

## [0.7.7] — 2026-06
### Fixed
- **Removed wrong font/colour sub-pickers from text steps** — the admin Font
  Options / Colour Options sections inside text steps have been stripped out.
  Font and colour choices already exist as separate step types (Choice image
  cards, Colour swatches). The wrong feature was added in 0.7.6 based on a
  misread of the original request.

### Added
- **Step locking in stacked mode** — in the customer-facing panel, steps 2, 3…
  are greyed out and unclickable until the previous step is answered. Answering
  a step immediately unlocks the next one (live, no page reload). This applies
  only to stacked flow; sequential flow already enforces order naturally.
- **Placement collapse/expand** — each placement card in the admin builder now
  has a chevron toggle button (▲/▼). Click it to collapse that placement's
  steps to just the header, making it easy to manage sets with many placements
  without scrolling through all steps at once.

## [0.7.6] — 2026-06
### Added
- **Text-step font & colour sub-pickers** — each Text step in the admin builder
  can now have an optional list of font options (name + CSS font-family) and
  colour swatches. The front-end renders clickable font cards (live preview) and
  colour swatches before the text input. Server validates the chosen font/colour
  ID against the configured set; font_name, font_family, color_name, color_hex
  are stored in cart meta and shown in orders/emails.
- **Inline validation error badge** — when Continue is clicked with an unanswered
  step, a "Required" badge appears on that step's heading (CSS pseudo-element)
  instead of a plain scroll-and-shake. Badge clears on interaction.
- **Placement picker grid2 layout** — new Design setting `placement_layout`
  (list / grid2). Grid2 renders placements side-by-side (2-column).
- **Step description field** — each step (any type) now has an optional
  Description/subtitle that appears below the step heading in the panel.
- **Card image fit & aspect ratio** — two new Design settings:
  `card_img_fit` (cover / contain) and `card_img_aspect` (square / landscape /
  portrait / auto). Driven by CSS custom properties `--wcpp-img-fit` and
  `--wcpp-img-aspect`; no JS needed.
- **Sequential step flow** — new Design setting `step_flow` (stacked / sequential).
  Sequential shows one step at a time with Next/Review navigation; stacked
  keeps the existing all-steps-visible behaviour. Continue button label changes
  to "Next" mid-flow and "Review" on the last step.

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
