# CLAUDE.md — WC Personalisation Panel
> Read this first, every session. This reflects the ACTUAL built plugin (v0.6.0).
> Detail: BUILD-PLAN.md · File map: FOLDER-STRUCTURE.md · Decisions: DECISIONS.md

---

## WHAT THIS IS
A self-contained WordPress/WooCommerce plugin that adds a slide-in
personalisation drawer to product pages. Modelled on the Olivia von Halle
"Make it your own" drawer (UX pattern only — no copied assets).

- Slug: `wc-personalisation-panel` · Text domain: `wcpp`
- Prefix: `wcpp_` / `WCPP_` · PHP 7.4+ · OOP · WP Coding Standards
- **No third-party dependencies.** WP + WooCommerce core APIs only.

---

## CORE DATA MODEL (memorise this)

A **Personalisation Set** (CPT `wcpp_personalisation`) contains **Placements**.
Each Placement has its own **Steps**. Each Step is either **Choices** or **Text**.

```
Set "Shirts"
 ├── Placement "Front"   (image, parent — picked once per order)
 │     ├── Step "Your Text"  type=text   (placeholder, max_chars, price)
 │     ├── Step "Font"       type=choice (Serif, Script)
 │     └── Step "Colour"     type=choice (White, Black)        ← 2 colours
 └── Placement "Back"    (image)
       ├── Step "Your Text"  type=text
       ├── Step "Font"       type=choice (Serif, Script)
       └── Step "Colour"     type=choice (White, Black, Gold)  ← 3 colours
```

Why placements own their steps: choices can differ per placement
(e.g. Front 2 colours, Back 3). This is the parent→child structure.

### Stored meta (on the CPT post)
- `_wcpp_placements`  — the full nested array (placements → steps → choices)
- `_wcpp_assigned_categories` / `_wcpp_apply_all` — where the set applies
- `_wcpp_set_price`   — flat one-time fee for the whole set
- `_wcpp_button_text` — optional per-set trigger button label override
- `_wcpp_options`     — LEGACY (flat options); auto-wrapped as one placement

### Placement / step / choice shapes
```php
placement = [ 'id', 'name', 'image_id', 'image_url', 'steps' => [...] ]
step       = [ 'id', 'name', 'type'=>'choice|text',
               // choice: 'choices' => [...]
               // text:   'placeholder', 'max_chars', 'price' ]
choice     = [ 'id', 'name', 'image_id', 'image_url', 'price' ]
```

### Normalised selection (server output, used in cart/order/email)
```php
selection = [ 'step_id','step_name','type','value','price','image_url','choice_id' ]
// value = chosen choice name OR the typed text
```

---

## ADMIN MENU (top-level "Personalisation")
- **Personalisation Sets** — the CPT builder (placements/steps/choices)
- **Add New Set**
- **Panel Settings** — global Design + Behaviour (one page, two tabs)

Class `WCPP_Admin_Menu` builds the top menu; the CPT attaches via
`show_in_menu`. CPT capabilities = `capability_type => 'product'` (shop
managers + admins only).

---

## GLOBAL SETTINGS (option `wcpp_settings`, via WCPP_Settings_Store)
Two groups. **Design is global** (applies to every panel). Per-set you can
only override the button text + set steps/price/categories.

- **Design**: trigger button (text/style/colours/radius/placement/full-width),
  drawer (slide side/width/mobile bp/bg/font/radius/overlay/anim),
  header (title/colour), progress (show/bar|dots|text/colour),
  choice cards (layout list|grid2|grid3/border/selected/image size),
  footer buttons (next/back/add-to-bag text + colour),
  pricing (show per-choice price / show total / free label).
- **Behaviour**: enabled, non_returnable, elementor, remove_on_uninstall.

⚠ Saving one tab MUST NOT wipe the other — `sanitize()` merges with existing
and only rebuilds the submitted tab (tracked via hidden `_tab` field).

Default card layout = `grid3`; placement picker uses its own full-width
image cards (`.wcpp-select-card`). 512×512 source images recommended.

---

## DATA FLOW (the sacred path)
```
Builder (CPT save)  →  _wcpp_placements
Front-end JS        →  picks placement → runs its steps → review
                       payload: [{placement_id, steps:[{step_id, choice_id|text}]}]
wp_ajax_wcpp_add_to_cart (+nopriv)
   → resolve_step() validates EACH step by ID; text sanitised + length-capped
   → server re-derives every name + price (never trusts the browser)
   → total = flat set fee + Σ choice/text prices
   → WC()->cart->add_to_cart(..., ['wcpp_data'=>...]) + unique key
woocommerce_before_calculate_totals → price = base_price + total (idempotent)
woocommerce_get_item_data            → cart / mini-cart display
woocommerce_checkout_create_order_line_item → hidden _wcpp_* order meta
woocommerce_order_item_meta_end      → admin order + customer order + EMAILS
```

---

## SECURITY (enforced, verified)
- Nonce on AJAX (`check_ajax_referer`) + both `wp_ajax_`/`wp_ajax_nopriv_`.
- Every save: nonce + capability + autosave guard.
- Server trusts ONLY IDs; names/prices re-derived from the set; text
  `sanitize_text_field` + `max_chars` capped server-side.
- Prices recomputed server-side; applied in `before_calculate_totals`.
- Output escaped (`esc_html/attr/url`, `wc_price`, `wp_kses_post`).
- Order meta keys are underscore-prefixed (hidden) → no raw double-display.
- No SQL, no unserialize of untrusted data, no external calls.

---

## FRONT-END WIZARD (assets/js/wcpp-panel.js)
Phases: `select` (placement picker) → `step` (that placement's steps) →
`review`. State: `state.completed[]` (finished placements) + `state.current`.
- Each placement can be added **once** (used ones hidden in the picker).
- Review: each placement has **Edit** (re-opens with values) + **Remove**.
- Back: step>0 → prev step; first step → placement picker; editing → review.
- Prices formatted with WooCommerce currency settings (symbol/position).
- ES5, IIFE, events namespaced `.wcppPanel`, scoped to `#wcpp-panel`.

---

## KEY HOOKS
| Hook | Owner |
|---|---|
| woocommerce_after_add_to_cart_form (default placement) | Cart_Handler |
| woocommerce_add_cart_item_data | Cart_Handler (unique key) |
| woocommerce_get_item_data | Cart_Handler |
| woocommerce_before_calculate_totals | Price_Calculator |
| woocommerce_checkout_create_order_line_item | Order_Handler |
| woocommerce_order_item_meta_end | Order_Handler (order + email) |
| wp_ajax_(nopriv_)wcpp_add_to_cart | Ajax_Handler |
| before_woocommerce_init | HPOS declaration (top-level) |

---

## DON'T
- Don't add composer/npm/external libraries.
- Don't trust posted names/prices/length — re-derive/validate server-side.
- Don't store visible (non-underscore) order-item meta.
- Don't let one settings tab wipe the other.
- Don't bind JS to theme/WC generic selectors — scope to `#wcpp-panel`.
- Don't add `Co-Authored-By` trailers to commits.

---
*v0.6.0 · Updated to reflect the real architecture (placements model).*
