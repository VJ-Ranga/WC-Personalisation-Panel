# DECISIONS.md — WC Personalisation Panel
> Log of architectural decisions. Read before proposing changes.
> Each entry = why we chose this, what we rejected, and what would make us revisit it.

---

## DEC-001 — No Composer or NPM Dependencies
**Decision:** Plugin uses zero external package managers or libraries.
**Why:** WordPress plugins must be self-contained. Adding composer/npm creates a build step that breaks on client servers, complicates delivery, and adds maintenance overhead. WordPress + WooCommerce APIs cover everything we need.
**Rejected:** Using Guzzle for HTTP, Carbon for dates, any npm build pipeline.
**Revisit if:** A specific feature genuinely cannot be done with WP/WC APIs (unlikely).

---

## DEC-002 — Vanilla JS, ES5, No Build Step
**Decision:** Front-end JS is plain ES5, IIFE-wrapped, one file per concern.
**Why:** WooCommerce ships with jQuery. Themes may be old. We cannot guarantee Babel will run on the client server. ES5 works everywhere without transpilation.
**Rejected:** React, Vue, Alpine.js, webpack, Vite, any modern JS toolchain.
**Revisit if:** Plugin needs to support a complex UI that becomes unmanageable in vanilla JS (not the case here).

---

## DEC-003 — Settings Store as Single Source of Truth
**Decision:** `WCPP_Settings_Store::get( $product_id )` is the only place that reads and merges settings.
**Why:** Without a central store, merge logic scatters across handlers and gets inconsistent. One call, one array, one place to debug if settings are wrong.
**Rejected:** Reading `get_option`/`get_post_meta` directly inside cart handlers, AJAX handlers, etc.
**Revisit if:** Never. This is structural.

---

## DEC-004 — Server-Side Price Only
**Decision:** Prices are calculated server-side exclusively. JS shows a preview fetched via AJAX.
**Why:** If JS calculates the price, a user can manipulate the posted price. Server-side recalc in `woocommerce_before_calculate_totals` ensures the correct price is always charged.
**Rejected:** Calculating price in JS from config values and posting it.
**Revisit if:** Never. This is a security requirement.

---

## DEC-005 — Cart De-duplication via Unique Key
**Decision:** Every `woocommerce_add_cart_item_data` call adds `wcpp_unique_key = md5(data + microtime())`.
**Why:** WooCommerce merges cart items with identical product IDs. Without a unique key, adding the same product with two different monograms collapses to one line. Wrong item gets shipped.
**Rejected:** Relying on WooCommerce's default behaviour.
**Revisit if:** Never. WooCommerce's merge behaviour will not change.

---

## DEC-006 — Theme-Agnostic via WooCommerce Hooks + Overridable Templates
**Decision:** Button and panel are injected via WooCommerce hooks, not hardcoded into theme templates. Templates follow WooCommerce override pattern (yourtheme/wcpp/).
**Why:** Plugin must work on WoodMart, Elessi, and Twenty Twenty-Five. Hardcoding to one theme's markup breaks the others.
**Rejected:** Editing theme files, binding to theme-specific CSS selectors.
**Revisit if:** A client theme has a fundamentally incompatible product page structure (then add a placement shortcode as the fallback — already planned in Phase 6).

---

## DEC-007 — Elementor Support is Optional and Loaded Late
**Decision:** All Elementor code lives in `/elementor/` and loads only if `did_action('elementor/loaded')` AND a toggle is on. Core must work with Elementor fully off.
**Why:** Elementor is not always present. Coupling core to Elementor breaks non-Elementor installs. Building Elementor first and core second is the wrong order.
**Rejected:** Loading Elementor code unconditionally, building the Elementor widget before core works.
**Revisit if:** Elementor becomes a hard requirement (it won't — client may switch builders).

---

## DEC-008 — OOP, WordPress Coding Standards, PHPCS
**Decision:** All PHP is object-oriented, prefixed with `wcpp_`/`WCPP_`, and must pass `phpcs` with WordPress Coding Standards.
**Why:** Professional delivery standard. Client may hand this to another developer. PHPCS-clean code is readable and maintainable.
**Rejected:** Procedural PHP, global scope functions without prefix, skipping PHPCS.
**Revisit if:** Never. This is a delivery standard.

---

## DEC-009 — Data Integrity Over UI Polish
**Decision:** If there is a tradeoff between a smoother UI and reliable data, data wins every time.
**Why:** A lost monogram = wrong product shipped = refund + angry client + damage to Cloudycode reputation. A slightly clunky UX is recoverable. A fulfilment error is not.
**Rejected:** Optimistic UI updates before server confirmation, trusting browser data.
**Revisit if:** Never. Embroidery/personalisation is a one-way physical action.

---

## DEC-010 — Guest Checkout Must Work
**Decision:** Both `wp_ajax_` and `wp_ajax_nopriv_` are registered for every AJAX endpoint.
**Why:** WooCommerce allows guest checkout by default. If guest AJAX fails, guests cannot add personalised items. That is a revenue-blocking bug.
**Rejected:** Requiring login to use personalisation.
**Revisit if:** Client explicitly disables guest checkout site-wide (then document the change here).

---

## DEC-011 — Placements own their Steps (parent→child)
**Decision:** A Set has Placements; each Placement has its own Steps/Choices.
**Why:** Choices must differ per placement (Front 2 colours, Back 3 colours) and
text differs per placement (Front "JR", Back "RANGA"). A flat shared-steps model
cannot express this. Rejected: step-level multi-select (can't carry per-placement
values), and deep arbitrary nesting (over-engineering). One parent level is enough.

## DEC-012 — Global design, per-set content
**Decision:** All visual design lives in the global Panel Settings page; a Set
only defines steps/choices/categories/price + an optional button-text override.
**Why:** A store wants ONE consistent panel look across all sets; editing design
per set is repetitive and error-prone.

## DEC-013 — Three step types: choice / colour / text
**Decision:** Steps are typed. choice = name+image; colour = name+hex (swatches);
text = free input (placeholder/max_chars/price).
**Why:** Real monogramming needs typed text + named colours, not just images.

## DEC-014 — Cards are <div role="button">, not <button>
**Decision:** Clickable cards use divs with role/tabindex + Enter/Space.
**Why:** `<button>` does not grow to fit a tall image child, so with
overflow:hidden the image gets clipped to a thin strip. Divs size correctly.

## DEC-015 — Bump WCPP_VERSION on every CSS/JS change
**Decision:** Asset URLs use `?ver=WCPP_VERSION`; bump it whenever CSS/JS changes.
**Why:** Without bumping, browsers/optimizers serve the cached old file and the
change appears to do nothing (this cost hours of debugging).

## DEC-016 — Price baked into the line item price
**Decision:** Personalisation total is added to the cart line price in
`woocommerce_before_calculate_totals` (base_price + add-on, idempotent).
**Why:** WooCommerce then handles subtotal, tax, and coupons natively. Base price
is captured at add-time so the hook firing multiple times never compounds.

---

*Last updated: 2026-06 (v0.6.7)*
*Add new decisions as DEC-017, etc.*
