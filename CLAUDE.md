# CLAUDE.md — WC Personalisation Panel
> Master reference. Read this FIRST. Reflects the real built plugin (v0.7.7).
> If you are a fresh/local AI continuing this work: this file + FOLDER-STRUCTURE.md
> tell you everything. Detail: BUILD-PLAN.md · Decisions: DECISIONS.md.

---

## 1. WHAT THIS IS
A self-contained WordPress/WooCommerce plugin that adds a slide-in
personalisation drawer to product pages (monogramming/embroidery style),
modelled on the Olivia von Halle "Make it your own" drawer.

- Slug `wc-personalisation-panel` · text domain `wcpp` · prefix `wcpp_`/`WCPP_`
- PHP 7.4+ · OOP · **No third-party dependencies** (WP + WooCommerce only)
- GitHub: https://github.com/VJ-Ranga/WC-Personalisation-Panel
- Local site: `2202new` (Local by Flywheel) — see LOCAL-DEV.md

---

## 2. CORE DATA MODEL (the most important thing to understand)

```
Personalisation Set  (CPT: wcpp_personalisation)
 └── Placements[]          parent — each picked ONCE per order, has an image
       └── Steps[]         each step has a TYPE:
             • choice  → choices[] (name + image + price)
             • color   → choices[] (name + HEX colour + price) → swatches
             • text    → free text (placeholder, max_chars, price)
```

Placements own their steps so **choices can differ per placement**
(e.g. Front has 2 colours, Back has 3). That's the whole reason for the
parent→child structure.

### Post meta on each Set
| meta key | content |
|---|---|
| `_wcpp_placements` | the full nested array (below) |
| `_wcpp_assigned_categories` (array) + `_wcpp_apply_all` ('1'/'0') | where the set applies |
| `_wcpp_set_price` | flat one-time fee for the whole set |
| `_wcpp_button_text` | optional per-set trigger button label |
| `_wcpp_options` | LEGACY flat options — auto-wrapped as one placement on read |

### PHP shapes (what `_wcpp_placements` stores)
```php
placement = [ 'id'=>'pl_*', 'name', 'image_id', 'image_url', 'steps'=>[ step,… ] ]
step      = [ 'id'=>'st_*', 'name', 'type'=>'choice|color|text',
              'description'(optional subtitle shown below heading),
              // choice/color: 'choices'=>[ choice,… ]
              // text:        'placeholder','max_chars','price',
              //              'font_choices'=>[{id,name,family},…] (optional),
              //              'color_choices'=>[{id,name,color(hex)},…] (optional) ]
choice    = [ 'id'=>'ch_*', 'name', 'image_id', 'image_url', 'color'(hex), 'price' ]
```

### Normalised SELECTION (server output, used in cart/order/email)
```php
selection = [ 'step_id','step_name','type'('choice|color|text'),
              'value'(=choice name OR typed text),'price','image_url',
              'color','choice_id' ]
```

---

## 3. ADMIN STRUCTURE
Top-level menu **"Personalisation"** (`WCPP_Admin_Menu`):
- **Personalisation Sets** — the CPT builder
- **Add New Set**
- **Panel Settings** — global Design + Behaviour (one page, two tabs, `WCPP_Settings_Page`)

CPT capabilities = `capability_type => 'product'` (shop managers + admins only).
Per-product: a meta box lets you force a specific set (overrides category rules).

### The builder (per Set)
- **Placements** box: add / **Duplicate** / delete placements; each has an image.
- Inside each placement: **Steps** (Choices/Colours/Text selector) → choices/colour/text settings.
- **Pricing** box: flat set fee.
- **Apply to Product Categories** box (or "Apply to ALL").
- **Button Text (optional)** box.

---

## 4. GLOBAL SETTINGS (option `wcpp_settings`, read via WCPP_Settings_Store)
Design is GLOBAL (applies to every panel). Per-set you only override button text.
- **Design**: trigger button (text/style/colours/radius/placement/full-width),
  drawer (slide side/width/mobile bp/bg/font/radius/overlay/anim),
  header (title/colour), progress (show/bar|dots|text/colour),
  cards (layout list|grid2|grid3/border/selected/image size),
  footer buttons (next/back/add-to-bag text + colour),
  pricing (show per-choice price / show total / free label).
  Default card layout = **grid3**; placement picker uses its own full-width
  image cards (`.wcpp-select-card`).
- **Behaviour**: enabled, non_returnable, elementor, remove_on_uninstall.

⚠ Saving one tab must NOT wipe the other — `sanitize()` merges with the existing
option and only rebuilds the submitted tab (hidden `_tab` field tracks which).

---

## 5. DATA FLOW (sacred path)
```
Builder save → _wcpp_placements
Front JS (wcpp-panel.js): select placement → run its steps → review
   payload: [{ placement_id, steps:[{ step_id, choice_id | text }] }]
wp_ajax_(nopriv_)wcpp_add_to_cart:
   • resolve_step() validates EACH step by ID; text sanitised + length-capped
   • server RE-DERIVES every name/price (never trusts the browser)
   • total = flat set fee (once) + Σ choice/text prices
   • base_price captured (variation price if variable)
   • WC()->cart->add_to_cart(product, 1, variation_id, variation_attrs, wcpp_data) + unique key
woocommerce_before_calculate_totals → price = base_price + total (idempotent)
woocommerce_get_item_data           → cart / mini-cart
woocommerce_checkout_create_order_line_item → hidden _wcpp_* order meta
woocommerce_order_item_meta_end      → admin order + customer order + EMAIL
```

### Variable products
- On "Add Personalisation" click, JS checks for `form.variations_form`; if a
  variable product has no variation chosen, it shows a message and does NOT open.
- Chosen variation attributes (`attribute_*`) are captured and passed to
  add_to_cart; base price = the variation's price.

---

## 6. FRONT-END WIZARD (assets/js/wcpp-panel.js)
Phases: `select` (placement picker) → `step` → `review`.
State: `state.completed[]` + `state.current`.
- **`step` shows ALL of the placement's steps stacked in ONE scrollable panel**
  (`renderAllSteps` → `renderStepBody` per step), each under a "Step N" divider.
  Footer **Continue** (`goNext`) validates every step at once; if one is
  unanswered it scrolls to + shakes that section. Then → review.
- Each placement added **once** (used ones hidden in the picker).
- **Review**: each placement has **Edit** (re-opens with its values) + **Remove**.
  (No back button in Review — Edit covers changes.)
- Back arrow: in `step` → picker (or review if editing).
- Step body: choice → image cards; color → swatches; text → input+counter.
- Progress reflects how many steps are filled (live).
- Cards/swatches are `<div role="button" tabindex="0">` (NOT `<button>` — buttons
  clip tall images). Keyboard: Enter/Space activate.
- Errors shown via a **branded toast** — no `alert()` anywhere in the plugin.
- Prices formatted with WooCommerce currency (symbol/position/decimals).
- ES5, IIFE, events namespaced `.wcppPanel`, scoped to `#wcpp-panel`.

---

## 7. SECURITY (enforced)
- AJAX: `check_ajax_referer('wcpp_nonce')` + both `wp_ajax_`/`wp_ajax_nopriv_`.
- Every save: nonce + capability + autosave guard.
- Server trusts ONLY IDs; names/prices re-derived from the set.
- Text: `sanitize_text_field` + `max_chars` capped server-side.
- Colour: `sanitize_hex_color` on save.
- Prices recomputed server-side, applied in `before_calculate_totals`.
- Output escaped (`esc_html/attr/url`, `wc_price`, `wp_kses_post`).
- Order meta keys underscore-prefixed (hidden) → no raw double display.
- No SQL, no unserialize of untrusted data, no external HTTP.

---

## 8. ⚠ CACHE RULE (caused hours of confusion — don't repeat)
Assets are enqueued with `?ver=WCPP_VERSION`. **If you change any CSS or JS,
you MUST bump `WCPP_VERSION`** (in `wc-personalisation-panel.php`, both the
header and the constant) — otherwise browsers/optimizers keep serving the old
cached file and your change appears to "do nothing". Current: 0.7.7.

---

## 9. HOW TO MAKE COMMON CHANGES (for a local AI)
- **Add a step type**: add option in `render_step_block` (CPT) + a `wcpp-step-*`
  section; handle in `save_placements`; in `resolve_step` (settings store);
  in `renderStep` (panel.js); add CSS. Bump version.
- **Change button placement hooks**: `WCPP_Cart_Handler::init()` switch on
  `$design['btn_placement']`.
- **Add a design setting**: add to `design_defaults()` (store) + a field in
  `render_design_tab` + sanitise in `sanitize_design` (settings-page) + output
  as a CSS var in `build_css_vars` (cart-handler) + use the var in CSS.
- **Change cart/order display**: `display_in_cart` (cart-handler),
  `display_in_order` (order-handler).
- **Anything visual**: edit the CSS/JS, then **bump WCPP_VERSION**.
- After PHP edits: lint with the Local PHP binary (see LOCAL-DEV.md).

---

## 10. DON'T
- Don't add composer/npm/external libraries.
- Don't trust posted names/prices/length — re-derive/validate server-side.
- Don't store visible (non-underscore) order-item meta.
- Don't let one settings tab wipe the other.
- Don't use `<button>` for image cards (clips tall images — use div+role).
- Don't change CSS/JS without bumping WCPP_VERSION.
- Don't bind JS to theme/WC generic selectors — scope to `#wcpp-panel`.
- Don't add `Co-Authored-By` trailers to commits.

---
*v0.7.7 · placements model · choice/colour/text steps · sequential/stacked wizard · font+colour sub-pickers · grid2 placement picker · step descriptions · card image fit/aspect · inline validation badge · variable-product aware.*
