# CLAUDE.md — WC Personalisation Panel
> Read this first. Every session. No exceptions.
> This file = standing rules. Build plan detail = `BUILD-PLAN.md`. Folder map = `FOLDER-STRUCTURE.md`.

---

## WHAT ARE WE BUILDING

A **self-contained WordPress plugin** (zero external deps) that adds a slide-in personalisation drawer to WooCommerce product pages. Customer picks options through a 6-step wizard, choices attach to the cart item, survive checkout, appear in admin orders and emails.

- **Plugin slug:** `wc-personalisation-panel`
- **Text domain:** `wcpp`
- **PHP prefix:** `wcpp_` (functions) / `WCPP_` (constants/classes)
- **UX reference:** Olivia von Halle personalisation drawer — copy the UX pattern ONLY. Zero assets, zero copy, zero images from them.

---

## WIZARD FLOW (what the customer sees)

```
[Product Page]
    └── "Add Personalisation" button
            └── Slide-in drawer opens →
                    Step 1: Location   (e.g. chest, cuff, collar)
                    Step 2: Type       (text / symbol / initials)
                    Step 3: Text/Symbol input  (with live char counter)
                    Step 4: Font       (visual picker)
                    Step 5: Colour     (swatch picker)
                    Step 6: Summary → "Add to Bag"
```

Progress bar across top. Back button on every step. Mobile: full-width drawer.

---

## DATA FLOW — THE SACRED PATH (never break this)

```
Wizard JS  →  wp_ajax_wcpp_add_to_cart
           →  Server validates + sanitises everything
           →  woocommerce_add_cart_item_data  (attach data + unique_key)
           →  woocommerce_before_calculate_totals  (apply price)
           →  woocommerce_get_item_data  (show in cart/mini-cart)
           →  woocommerce_checkout_create_order_line_item  (persist to order)
           →  woocommerce_order_item_meta_end  (show in admin + customer order)
           →  woocommerce_email_order_meta  (show in emails)
```

> If this chain breaks at any point = wrong product shipped = refund + angry client.
> **Server is source of truth. JS is display only. Never trust posted price/length/option.**

---

## FOLDER STRUCTURE

```
wc-personalisation-panel/
├── wc-personalisation-panel.php      ← Bootstrap. Plugin header. Safety check.
├── CLAUDE.md                          ← This file.
├── BUILD-PLAN.md                      ← Phase-by-phase detail.
├── FOLDER-STRUCTURE.md                ← Full annotated file map.
│
├── includes/
│   ├── class-settings-store.php       ← ONLY place settings are read/merged. Ask it, don't scatter logic.
│   ├── class-cart-handler.php         ← All cart hooks.
│   ├── class-order-handler.php        ← All order/checkout hooks.
│   ├── class-ajax-handler.php         ← All AJAX endpoints (logged in + guest).
│   ├── class-admin-settings.php       ← Global settings page.
│   ├── class-product-meta.php         ← Per-product meta box.
│   ├── class-price-calculator.php     ← Flat + per-char pricing. Server-side only.
│   ├── class-email-handler.php        ← Email meta output.
│   └── class-activator.php            ← Activation/deactivation hooks.
│
├── assets/
│   ├── css/
│   │   ├── panel-default.css          ← Drawer styles. Full-width below breakpoint.
│   │   └── admin.css
│   ├── js/
│   │   ├── wcpp-panel.js              ← Wizard UI logic. Scoped. No generic selectors.
│   │   └── wcpp-admin.js
│   └── images/                        ← Plugin UI images only. No client assets.
│
├── templates/
│   ├── panel.php                      ← Drawer HTML. Overridable: yourtheme/wcpp/panel.php
│   └── button.php                     ← Button HTML. Overridable: yourtheme/wcpp/button.php
│
├── languages/
│   └── wcpp.pot                       ← Generated last (Phase 8).
│
└── elementor/                         ← LOAD ONLY IF elementor/loaded fired AND toggle is on.
    ├── class-elementor-module.php
    └── widgets/
        └── class-widget-panel.php
```

---

## BUILD PHASES — DO NOT SKIP AHEAD

### Phase 0 — Scaffold ✅ start here
- Create plugin file with proper header
- `class_exists('WooCommerce')` check on `plugins_loaded` — show admin notice + bail if absent
- Define constants: `WCPP_VERSION`, `WCPP_PATH`, `WCPP_URL`
- Autoloader or manual requires
- Plugin activates clean, no errors, shows in WP plugin list

### Phase 1 — Settings Backbone
- `class-settings-store.php`: reads global options + per-product meta, merges, returns clean array
  - Method: `WCPP_Settings_Store::get( $product_id )` → returns merged config
  - Global defaults live in `wcpp_global_settings` option
  - Per-product overrides in `_wcpp_product_settings` post meta
- Global settings page under WooCommerce menu
  - Fields: enable/disable, default locations[], default types[], default fonts[], default colours[], flat fee, per-char fee, non-returnable flag default
- Per-product meta box on product edit screen
  - Toggle: enable personalisation for this product
  - Overrides: locations, types, fonts, colours, pricing overrides
  - Capability check: `edit_product` + autosave guard (`if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;`)

### Phase 2 — VERTICAL SLICE (milestone gate — STOP HERE UNTIL PROVEN)
**Build Location step ONLY, full end-to-end:**
1. Button renders on product page via `woocommerce_before_add_to_cart_button`
2. Drawer opens, shows Location step with options from settings
3. Customer selects location, submits
4. AJAX handler validates nonce + sanitises location against allowed list
5. `woocommerce_add_cart_item_data` attaches `['wcpp_data']['location']` + `unique_key`
6. `woocommerce_get_item_data` shows "Personalisation: Chest" in cart
7. `woocommerce_checkout_create_order_line_item` saves meta to order item
8. `woocommerce_order_item_meta_end` shows it in admin order screen
9. `woocommerce_email_order_meta` shows it in confirmation email

**Before moving to Phase 3, confirm ALL of:**
- [ ] Logged-in user can complete flow
- [ ] Guest user can complete flow (nopriv AJAX works)
- [ ] Two different location choices = two separate cart lines (de-dup key works)
- [ ] Order saved correctly in admin
- [ ] Email shows the meta
- [ ] WP_DEBUG on = zero PHP warnings

### Phase 3 — Full Wizard
- Add remaining steps: Type, Text+counter, Symbol, Font, Colour
- Progress bar (CSS only, no library)
- Back button on every step (JS state machine, not page reload)
- Notices/validation per step (JS preview + server confirmation)
- Mobile: test at 360px, drawer goes full-width

### Phase 4 — Pricing
- `class-price-calculator.php`:
  - `WCPP_Price_Calculator::calculate( $product_id, $data )` → returns float
  - Supports: flat fee, per-character fee, or both
  - `woocommerce_before_calculate_totals` applies the price
- Live price preview in drawer (fetch from server via AJAX, don't compute in JS)
- Cart, order, email all show personalisation price line via `wc_price()` only

### Phase 5 — Edge Cases
- **Variable products:** payload carries `variation_id` + attributes. Block submit if no variation selected. Check with `$product->is_type('variable')`.
- **Non-returnable flag:** `wcpp_non_returnable` persisted on order line item. Shown in admin order + email with warning styling.
- **2nd personalisation:** restart wizard, store both as indexed array `wcpp_data[0]`, `wcpp_data[1]`. Display as "Personalisation 1" / "Personalisation 2".
- **WoodMart conflicts:** scope all JS to `#wcpp-panel`. Never bind `body`, `.add_to_cart_button`, or any WC/theme generic selectors. Test inside WoodMart quick-view.
- **Mobile overflow:** test at 360px. Panel full-width, scroll inside drawer, no body scroll bleed.

### Phase 6 — Placement
- Auto-inject via `woocommerce_before_add_to_cart_button` (default)
- Shortcode: `[wcpp_button]` renders the button anywhere
- Template tag: `wcpp_render_button()` for theme devs
- Test: default product layout, grouped products, variable products

### Phase 7 — Elementor Module (LAST, GUARDED)
- Guard: `if ( ! did_action('elementor/loaded') || ! wcpp_elementor_enabled() ) return;`
- Elementor widget wraps the same button template — no duplicated logic
- Core plugin must work perfectly with Elementor fully deactivated before touching this phase

### Phase 8 — Hardening
- PHPCS pass (WordPress Coding Standards)
- Full security audit (see Security section)
- Generate `.pot` file: `wp i18n make-pot . languages/wcpp.pot`
- All strings wrapped in `__()` / `esc_html__()` with domain `wcpp`
- Run full acceptance checklist from Definition of Done

---

## SECURITY RULES (mandatory, no exceptions)

### Every AJAX call
```php
check_ajax_referer( 'wcpp_nonce', 'nonce' );
```

### Every settings save (global)
```php
if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();
wp_verify_nonce( $_POST['wcpp_settings_nonce'], 'wcpp_save_settings' );
```

### Every product meta save
```php
if ( ! current_user_can( 'edit_product', $post_id ) ) return;
if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
wp_verify_nonce( $_POST['wcpp_product_nonce'], 'wcpp_product_meta' );
```

### Sanitise on INPUT
```php
$location = sanitize_text_field( $_POST['location'] );
$text     = sanitize_text_field( $_POST['text'] );
$colour   = sanitize_hex_color( $_POST['colour'] );
$flat_fee = floatval( $_POST['flat_fee'] );
$max_len  = intval( $_POST['max_chars'] );
```
**Then whitelist against allowed values from settings store — reject anything off-list.**

### Enforce server-side
- Max text length (never trust JS `maxlength`)
- Allowed char set (letters only? alphanumeric? store defines it)
- All option values (location, type, font, colour) whitelisted against product's allowed list

### Escape on OUTPUT
```php
esc_html( $location )        // text in HTML
esc_attr( $value )           // attributes
esc_url( $url )              // URLs
wc_price( $amount )          // prices — only ever use this
wp_kses_post( $html )        // rich text
```

### No hand-written SQL
Use WP/WC APIs. `$wpdb->prepare()` only if absolutely unavoidable.

---

## KEY WP/WC HOOKS — QUICK REFERENCE

| Hook | Class | Purpose |
|---|---|---|
| `plugins_loaded` | bootstrap | WC safety check + init |
| `woocommerce_before_add_to_cart_button` | `Cart_Handler` | Auto-inject button |
| `woocommerce_add_cart_item_data` | `Cart_Handler` | Attach data + unique_key |
| `woocommerce_get_item_data` | `Cart_Handler` | Display in cart/mini-cart |
| `woocommerce_before_calculate_totals` | `Price_Calculator` | Apply personalisation price |
| `woocommerce_checkout_create_order_line_item` | `Order_Handler` | Persist to order + non-returnable flag |
| `woocommerce_order_item_meta_end` | `Order_Handler` | Admin + customer order display |
| `woocommerce_email_order_meta` | `Email_Handler` | Show in emails |
| `wp_ajax_wcpp_add_to_cart` | `Ajax_Handler` | Logged-in submit |
| `wp_ajax_nopriv_wcpp_add_to_cart` | `Ajax_Handler` | Guest submit |
| `wp_ajax_wcpp_get_price` | `Ajax_Handler` | Live price preview |
| `wp_ajax_nopriv_wcpp_get_price` | `Ajax_Handler` | Guest price preview |
| `add_meta_boxes` | `Product_Meta` | Per-product meta box |
| `save_post_product` | `Product_Meta` | Save product settings |
| `admin_menu` | `Admin_Settings` | Global settings page |
| `admin_init` | `Admin_Settings` | Register settings |
| `wp_enqueue_scripts` | bootstrap | Front-end assets |
| `admin_enqueue_scripts` | bootstrap | Admin assets |

---

## CART DE-DUPLICATION — EXACT PATTERN (HIGH PRIORITY BUG IF WRONG)

```php
// In woocommerce_add_cart_item_data filter:
$cart_item_data['wcpp_data']       = $validated_personalisation;
$cart_item_data['wcpp_unique_key'] = md5( wp_json_encode( $validated_personalisation ) . microtime() );
return $cart_item_data;
```
Without `wcpp_unique_key`, WooCommerce merges items with identical product IDs. Two different monograms become one line. Customer gets wrong item. Always add this.

---

## SETTINGS STORE — HOW TO USE IT

```php
// Anywhere you need settings — call this, don't read options yourself:
$config = WCPP_Settings_Store::get( $product_id );

// Returns array like:
// [
//   'enabled'         => true,
//   'locations'       => ['chest', 'cuff', 'collar'],
//   'types'           => ['text', 'initials'],
//   'fonts'           => ['serif', 'script', 'block'],
//   'colours'         => ['#000000', '#FFFFFF', '#C0A882'],
//   'flat_fee'        => 15.00,
//   'per_char_fee'    => 0.00,
//   'max_chars'       => 3,
//   'non_returnable'  => true,
// ]
```

Product-level settings override global defaults inside the store. Nothing outside this class should merge settings.

---

## JS RULES

- All code inside IIFE: `(function($, wcpp) { ... })(jQuery, window.wcpp);`
- Settings from PHP via `wp_localize_script`: `window.wcpp = { ajaxUrl, nonce, config }`
- Wizard state in a plain JS object, not DOM attrs
- Events namespaced: `.wcppPanel` e.g. `$('#wcpp-panel').on('click.wcppPanel', ...)`
- Never bind to: `body`, `.add_to_cart_button`, `.cart`, `form.cart` or any WC/theme generic selector
- No `console.log` left in production code
- ES5 compatible (no arrow functions, no template literals) — WooCommerce's bundled jQuery environment

---

## TEMPLATE OVERRIDES

Templates use WooCommerce's standard override pattern:

```php
// In your template locator function:
function wcpp_get_template( $template_name ) {
    $theme_file  = get_stylesheet_directory() . '/wcpp/' . $template_name;
    $plugin_file = WCPP_PATH . 'templates/' . $template_name;
    return file_exists( $theme_file ) ? $theme_file : $plugin_file;
}
```

Theme devs copy `templates/panel.php` → `yourtheme/wcpp/panel.php` to override.

---

## PRICING — DISPLAY RULES

- Always format with `wc_price( $amount )` — never `number_format`, never `echo '$' . $amount`
- One calculation path: `WCPP_Price_Calculator::calculate( $product_id, $data )`
- Price shown in: drawer preview (fetched from server) → cart line → order line → email
- All four must agree. If they don't, the server calc is wrong — fix the calculator, not the display

---

## CRITICAL GOTCHAS CHECKLIST

Before marking any phase done, tick these relevant to that phase:

- [ ] Cart de-dup key added — two monograms = two cart lines
- [ ] Variable product: `variation_id` in payload, block if no variant selected
- [ ] Guest AJAX: both `wp_ajax_` and `wp_ajax_nopriv_` registered
- [ ] Server validates location/type/font/colour against product's allowed list
- [ ] Server enforces text max length — not just JS
- [ ] Price recalculated server-side in `woocommerce_before_calculate_totals`
- [ ] Non-returnable flag persisted on order line item
- [ ] `WP_DEBUG true` = zero PHP notices/warnings
- [ ] WoodMart quick-view tested
- [ ] 360px mobile tested — drawer full-width, no overflow
- [ ] Elementor deactivated = core still works 100%
- [ ] No external HTTP calls, no external libraries

---

## THEME COMPATIBILITY

Must work clean on:
1. **WoodMart** — most common conflict source (quick-view, AJAX cart, custom selectors)
2. **Elessi** — the theme on this install (check for JS conflicts with NASA Core companion plugin)
3. **Twenty Twenty-Five** — baseline test

Never hardcode theme class names or IDs. Always use WooCommerce hooks for placement.

---

## ASSET ENQUEUING PATTERN

```php
// Front-end
wp_enqueue_style(  'wcpp-panel', WCPP_URL . 'assets/css/panel-default.css', [], WCPP_VERSION );
wp_enqueue_script( 'wcpp-panel', WCPP_URL . 'assets/js/wcpp-panel.js', ['jquery'], WCPP_VERSION, true );
wp_localize_script( 'wcpp-panel', 'wcpp', [
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce'   => wp_create_nonce( 'wcpp_nonce' ),   // generated for both logged-in AND guest
    'config'  => WCPP_Settings_Store::get( get_the_ID() ),
]);
```

---

## DEFINITION OF DONE (full plugin)

- [ ] Admin enables personalisation per product from the WP admin, zero code
- [ ] Logged-in shopper completes full 6-step wizard, sees correct details in order + email
- [ ] Guest shopper completes full 6-step wizard, sees correct details in order + email
- [ ] Embroidery team reads all personalisation details in admin order screen
- [ ] Two different monograms on same product = two separate cart/order lines
- [ ] Personalisation price appears in cart, order, and email — all three agree
- [ ] Runs on WoodMart, Elessi, and Twenty Twenty-Five with zero errors
- [ ] Runs with Elementor active AND with Elementor deactivated
- [ ] `WP_DEBUG true` = zero PHP notices, zero PHP warnings
- [ ] Browser console = zero errors on product page, cart, checkout
- [ ] PHPCS passes WordPress Coding Standards
- [ ] All strings translatable, `.pot` generated
- [ ] No composer, npm, or external library dependencies

---

## NEVER DO

- Add `Co-Authored-By: Claude` (or anyone) to commit messages
- Add composer / npm / external library without explicit approval
- Bind JS to `body`, `.add_to_cart_button`, or any generic WC/theme selector
- Trust a price, length, or option value that came from the browser
- Build the Elementor module before core works without Elementor
- Move past Phase 2 without proving the full data flow on a real test order
- Scatter settings merge logic — it all goes through `class-settings-store.php`
- Use `echo $price` or `number_format` for prices — always `wc_price()`
- Write raw SQL — use WP/WC APIs or `$wpdb->prepare()`

---

## QUICK ORIENTATION FOR NEW SESSIONS

> If you're starting fresh on this project, here's the 60-second orientation:

1. Plugin is at `wp-content/plugins/wc-personalisation-panel/`
2. Entry point: `wc-personalisation-panel.php` (check if it exists — if not, Phase 0 not done)
3. Settings always go through `includes/class-settings-store.php`
4. Current phase? Check git log or ask — don't assume
5. Before writing any code, check what phase we're on and what's already built
6. Run with `WP_DEBUG true` always during dev (already set in wp-config on this Local install — confirm)
7. Test on this Local by Flywheel install: `2202new` with Elessi theme active

---

*Last updated: June 2026 | Plugin version target: 1.0.0*
