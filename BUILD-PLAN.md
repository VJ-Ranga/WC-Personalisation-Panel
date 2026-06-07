# BUILD-PLAN.md — WC Personalisation Panel
> Phase-by-phase dev tasks. Update status as you go. This is the living work log.
> Rules live in CLAUDE.md. This file is the task detail.

---

## CURRENT STATE (v0.6.7) — what is BUILT and working
- [x] Plugin scaffold, WooCommerce safety check, HPOS, uninstall.
- [x] CPT "Personalisation Sets" under a top-level menu + Add New.
- [x] Builder: Placements (image, **duplicate**, delete) → Steps → Choices.
- [x] Step types: **Choices (image)**, **Colours (swatches)**, **Text input**.
- [x] Category + apply-all + per-product set assignment.
- [x] Flat set price + per-choice/per-text prices.
- [x] Global Panel Settings (Design + Behaviour tabs; per-tab save merge).
- [x] Front wizard: pick placement → steps → review; each placement once.
- [x] Review: per-placement **Edit** + **Remove** + "Add another".
- [x] Full-width image placement cards; colour swatches; text + counter.
- [x] Server validates by ID, re-derives names/prices; text sanitised+capped.
- [x] Price baked into line price; cart + order + EMAIL show all selections.
- [x] WooCommerce currency formatting; variable-product aware.

## NOT yet done (candidate next steps — see the UI/UX/security/compat review)
- [ ] Product-type guard (hide button on external/grouped products).
- [ ] Quick-view modal support.
- [ ] Loading spinner + success toast (stay-on-page option).
- [ ] Focus trap + fuller a11y.
- [ ] Text allowed-character rule (letters/numbers only).
- [ ] Cached-nonce refresh for page-cache plugins.
- [ ] Generate `.pot`; finalise i18n.
- [ ] Cart/Checkout Blocks display check.

---

## Status Legend
- `[ ]` Not started
- `[~]` In progress
- `[x]` Done
- `[!]` Blocked / issue

---

## Phase 0 — Scaffold + Safety Check
**Goal:** Plugin file exists, activates clean, WooCommerce check works.

- [ ] Create `wc-personalisation-panel.php` with correct plugin header
- [ ] Define constants: `WCPP_VERSION`, `WCPP_PATH`, `WCPP_URL`, `WCPP_BASENAME`
- [ ] Hook into `plugins_loaded` — check `class_exists('WooCommerce')`
- [ ] If WooCommerce missing: hook `admin_notices`, show dismissible error notice, return early — no fatal
- [ ] Simple autoloader or manual `require_once` chain
- [ ] Create all empty class files with correct namespace/class declarations
- [ ] Create `index.php` (silent) in every folder (WordPress security standard)
- [ ] Activate plugin in Local — confirm: activates clean, no errors, shows in plugin list
- [ ] Deactivate WooCommerce — confirm: admin notice shows, no fatal error
- [ ] Re-activate WooCommerce — confirm: back to normal

**Done when:** Plugin activates/deactivates cleanly with and without WooCommerce active.

---

## Phase 1 — Settings Backbone
**Goal:** Admin can configure personalisation globally and per product. No front-end yet.

### 1A — Settings Store
- [ ] Build `includes/class-settings-store.php`
  - Static method: `WCPP_Settings_Store::get( $product_id )` → returns merged array
  - Reads global: `get_option('wcpp_global_settings', [])`
  - Reads per-product: `get_post_meta( $product_id, '_wcpp_product_settings', true )`
  - Per-product values override global defaults
  - Returns clean, typed array (see CLAUDE.md for shape)
- [ ] Unit-test the merge logic manually: set global, set override, confirm merge is correct

### 1B — Global Settings Page
- [ ] Register settings page under WooCommerce menu: `woocommerce → Personalisation`
- [ ] Use `register_setting()` + `add_settings_section()` + `add_settings_field()`
- [ ] Fields:
  - Enable plugin globally (checkbox)
  - Locations (textarea, comma-separated or repeater)
  - Types (checkboxes: text / initials / symbol)
  - Fonts (textarea, comma-separated slugs)
  - Colours (textarea, hex values comma-separated)
  - Flat fee (number input, 2 decimal)
  - Per-character fee (number input, 2 decimal)
  - Max characters (number input)
  - Non-returnable by default (checkbox)
- [ ] Save with nonce: `wcpp_settings_nonce` / `wcpp_save_settings`
- [ ] Capability check: `manage_woocommerce`
- [ ] Sanitise all inputs on save (see CLAUDE.md security rules)
- [ ] Confirm settings save and reload correctly

### 1C — Per-Product Meta Box
- [ ] Register meta box on `post.php` / `post-new.php` for `product` post type
- [ ] Fields:
  - Enable personalisation for this product (checkbox)
  - Override locations (textarea — blank = use global)
  - Override types (checkboxes)
  - Override fonts (textarea)
  - Override colours (textarea)
  - Override pricing (flat fee, per-char fee)
  - Override max chars
  - Non-returnable (checkbox)
- [ ] Save with nonce: `wcpp_product_nonce` / `wcpp_product_meta`
- [ ] Capability check: `edit_product`
- [ ] Autosave guard: `if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;`
- [ ] Sanitise all on save
- [ ] Confirm meta saves, reloads, and overrides global in settings store

**Done when:** Admin can set global settings, override per product, and `WCPP_Settings_Store::get($id)` returns correct merged values.

---

## Phase 2 — VERTICAL SLICE (Location step only, full data flow)
**MILESTONE GATE — Do not continue to Phase 3 until every checkbox below is ticked.**

### 2A — Front-End Button
- [ ] `class-cart-handler.php` hooks `woocommerce_before_add_to_cart_button`
- [ ] Loads `templates/button.php` via theme-first locator
- [ ] Button only renders if `WCPP_Settings_Store::get( get_the_ID() )['enabled']` is true
- [ ] Button has correct attributes: `id="wcpp-open-panel"`, `data-product-id`, `data-nonce`
- [ ] Enqueue `panel-default.css` and `wcpp-panel.js` on product pages only
- [ ] `wp_localize_script` passes: `ajaxUrl`, `nonce`, `config` (location list only for now)

### 2B — Drawer + Location Step
- [ ] `templates/panel.php` renders the drawer HTML (hidden by default, CSS `transform: translateX(100%)`)
- [ ] JS: button click → add class to drawer → slide in (CSS transition)
- [ ] JS: render Location step — list of buttons, one per location from `wcpp.config.locations`
- [ ] JS: user clicks a location → stores selection in state object `{ location: 'chest' }`
- [ ] JS: "Add to Bag" button → triggers AJAX submit

### 2C — AJAX Handler
- [ ] `class-ajax-handler.php`
- [ ] Register: `wp_ajax_wcpp_add_to_cart` (logged in)
- [ ] Register: `wp_ajax_nopriv_wcpp_add_to_cart` (guest — mandatory)
- [ ] Handler steps:
  1. `check_ajax_referer( 'wcpp_nonce', 'nonce' )` — die if invalid
  2. Get `$product_id` from POST, `intval`, verify product exists
  3. Get config: `WCPP_Settings_Store::get( $product_id )`
  4. Get `$location` from POST, `sanitize_text_field`
  5. Whitelist: `if ( ! in_array( $location, $config['locations'], true ) ) wp_send_json_error()`
  6. Build `$personalisation = [ 'location' => $location ]`
  7. `WC()->cart->add_to_cart( $product_id, 1, 0, [], [ 'wcpp_data' => $personalisation ] )`
  8. `wp_send_json_success([ 'message' => 'Added', 'cart_url' => wc_get_cart_url() ])`

### 2D — Cart Item Data
- [ ] Hook `woocommerce_add_cart_item_data`
- [ ] Attach `wcpp_data` to cart item
- [ ] Attach `wcpp_unique_key` = `md5( wp_json_encode( $data ) . microtime() )` — prevents WC merging
- [ ] Confirm: add same product twice with different locations = two separate cart lines

### 2E — Cart Display
- [ ] Hook `woocommerce_get_item_data`
- [ ] If `$cart_item['wcpp_data']` exists, return array with label "Personalisation" and the location value
- [ ] Confirm: shows correctly in cart page and mini-cart

### 2F — Order Persistence
- [ ] Hook `woocommerce_checkout_create_order_line_item`
- [ ] Save all `wcpp_data` keys as order item meta: `wc_add_order_item_meta( $item_id, 'wcpp_location', $data['location'] )`
- [ ] Confirm: place test order, view in WP Admin → Orders → line item shows personalisation meta

### 2G — Admin Order Display
- [ ] Hook `woocommerce_order_item_meta_end`
- [ ] Output personalisation data as readable HTML in admin order screen
- [ ] Escape all output: `esc_html()`

### 2H — Email Display
- [ ] Hook `woocommerce_email_order_meta`
- [ ] Output personalisation data in order confirmation email
- [ ] Trigger test email from admin: WooCommerce → Orders → Resend email

### Phase 2 Sign-off Checklist
- [ ] Logged-in user: button → location → add → cart shows personalisation → place order → order shows personalisation → email shows personalisation
- [ ] Guest user: same full flow without logging in
- [ ] Two different locations on same product = two separate cart lines (not merged)
- [ ] Invalid location posted = AJAX returns error, not added to cart
- [ ] WP_DEBUG = true = zero PHP notices or warnings throughout entire flow
- [ ] Browser console = zero JavaScript errors

**STOP. Do not start Phase 3 until all boxes above are ticked.**

---

## Phase 3 — Full Wizard (All 6 Steps)
**Goal:** Complete multi-step wizard with all steps, validation, back button, progress bar.

- [ ] JS state machine: `currentStep`, `selections` object, `steps[]` config array
- [ ] Step 2 — Type: show allowed types from config, store selection
- [ ] Step 3 — Text input with live character counter, enforce max from config
  - [ ] Show/hide text input OR symbol picker based on Step 2 selection
  - [ ] Symbol step: grid of symbols from config, click to select
- [ ] Step 4 — Font picker: visual grid showing font name in that font
- [ ] Step 5 — Colour picker: swatches from config hex values
- [ ] Step 6 — Summary: show all choices before final submit
- [ ] Progress bar: CSS only, updates on each step advance
- [ ] Back button on every step: goes to previous step, retains selections
- [ ] Validation: can't advance without selection on required steps
- [ ] Close button: confirm dialog if selections made ("You'll lose your personalisation")
- [ ] Mobile: test every step at 360px — drawer full width, scrolls inside, no body scroll

---

## Phase 4 — Pricing
**Goal:** Correct price applied in cart, order, and email. All three agree.

- [ ] Build `includes/class-price-calculator.php`
  - `WCPP_Price_Calculator::calculate( $product_id, $data )` → returns float
  - Flat fee: `$config['flat_fee']`
  - Per-char fee: `$config['per_char_fee'] * strlen( $data['text'] )`
  - Both can combine: `flat_fee + ( per_char_fee * char_count )`
- [ ] Hook `woocommerce_before_calculate_totals`: loop cart items, apply price if `wcpp_data` present
- [ ] AJAX endpoint `wcpp_get_price` (logged in + nopriv): returns server-calculated price for live preview
- [ ] Drawer: after each step change, fetch price preview from server via AJAX, display in summary
- [ ] Never calculate price in JS — always fetch from server
- [ ] Cart line shows personalisation price
- [ ] Order line shows personalisation price
- [ ] Email shows personalisation price
- [ ] All four (drawer preview, cart, order, email) show the same number
- [ ] All formatted with `wc_price()` only

---

## Phase 5 — Edge Cases
**Goal:** Nothing breaks on variable products, mobile, WoodMart, 2nd personalisation.

- [ ] **Variable products:**
  - Detect `$product->is_type('variable')` before rendering button
  - AJAX handler: validate `variation_id` is present and valid
  - Payload carries `variation_id` and `attributes` array
  - Block "Add to Bag" in JS if no variation selected yet
- [ ] **Non-returnable flag:**
  - Persist `wcpp_non_returnable` on order line item during checkout
  - Admin order screen: show "⚠ Non-returnable item" in personalisation meta
  - Email: include non-returnable notice
- [ ] **2nd personalisation:**
  - Define: restart wizard from Step 1, stores as `wcpp_data[0]` and `wcpp_data[1]`
  - Display in cart/order as "Personalisation 1" / "Personalisation 2"
  - Max 2 personalisations per item (configurable)
- [ ] **WoodMart conflicts:**
  - Install WoodMart theme on this Local install (or test on separate install)
  - Verify button renders in standard product layout
  - Verify button renders inside WoodMart quick-view
  - Verify no JS conflicts with WoodMart's AJAX add-to-cart
- [ ] **Mobile (360px):**
  - Drawer is full-width
  - All steps scroll within drawer, page body does not scroll
  - Touch targets minimum 44px
  - Font sizes readable without zoom
  - All steps reachable without horizontal scroll

---

## Phase 6 — Placement Methods
**Goal:** Button works via auto-hook, shortcode, and template tag.

- [ ] Auto-inject via `woocommerce_before_add_to_cart_button` (default, already done in Phase 2)
- [ ] Shortcode: `[wcpp_button]` renders button anywhere on the page
- [ ] Template tag: `<?php wcpp_render_button(); ?>` for theme developers
- [ ] All three methods use the same `templates/button.php` template
- [ ] Test shortcode in: page, post, widget area, Elementor text widget
- [ ] Test template tag in: `single-product.php` override

---

## Phase 7 — Elementor Module (guarded, last)
**Prerequisite: Core works 100% with Elementor deactivated — confirm before starting.**

- [ ] Confirm core works with Elementor fully off (run full Phase 2 checklist again)
- [ ] Create `elementor/class-elementor-module.php` — loads ONLY if:
  ```php
  did_action('elementor/loaded') && wcpp_elementor_enabled()
  ```
- [ ] Elementor widget: wraps the same button template, no duplicated logic
- [ ] Declare Elementor compatibility in main plugin file:
  ```php
  add_action( 'elementor/loaded', function() {
      add_action( 'elementor/editor/after_enqueue_styles', ... );
  });
  ```
- [ ] Test: Elementor page with `[wcpp_button]` widget — wizard works correctly
- [ ] Test: Deactivate Elementor — core plugin still works without any errors

---

## Phase 8 — Hardening + Publishing Prep
**Goal:** Production ready. Zero warnings. Translatable. Documented.**

### Security Pass
- [ ] Every AJAX call has `check_ajax_referer`
- [ ] Every settings save has `wp_verify_nonce` + capability check
- [ ] Every output is escaped
- [ ] Server-side whitelist on all option values (location, type, font, colour)
- [ ] Server-side max length enforced on text
- [ ] No raw `$_POST` used without sanitisation
- [ ] No hand-written SQL

### PHPCS Pass
- [ ] Run `phpcs .` from plugin root — zero errors, zero warnings
- [ ] Fix all issues found

### i18n
- [ ] All user-facing strings wrapped in `__()` / `esc_html__()`  with domain `wcpp`
- [ ] Run: `wp i18n make-pot . languages/wcpp.pot --domain=wcpp`
- [ ] Confirm `.pot` file generated correctly

### Performance
- [ ] Assets only enqueued on product pages (not sitewide)
- [ ] Admin assets only enqueued on admin screens that need them
- [ ] No N+1 queries in cart/order loops

### Docs
- [ ] Update `readme.txt` with final version, tested WP/WC versions
- [ ] Update `CHANGELOG.md` with 1.0.0 entry
- [ ] Update `DECISIONS.md` with any decisions made during build
- [ ] Full `TESTING.md` manual checklist completed and signed off

### Final Acceptance
- [ ] Run full checklist from TESTING.md
- [ ] Test on: Elessi, WoodMart, Twenty Twenty-Five
- [ ] Test with: Elementor on, Elementor off
- [ ] WP_DEBUG true = zero notices/warnings on all test scenarios
- [ ] Browser console = zero errors on all test scenarios
- [ ] Confirm plugin is ready for delivery/publishing (see PUBLISHING.md)

---

## Current Phase Status

| Phase | Status | Notes |
|---|---|---|
| Phase 0 — Scaffold | `[x]` Done | Activate plugin in Local to confirm |
| Phase 1 — Settings | `[x]` Done | Test settings page + meta box in admin |
| Phase 2 — Vertical Slice | `[ ]` Not started | Gate milestone |
| Phase 3 — Full Wizard | `[ ]` Not started | — |
| Phase 4 — Pricing | `[ ]` Not started | — |
| Phase 5 — Edge Cases | `[ ]` Not started | — |
| Phase 6 — Placement | `[ ]` Not started | — |
| Phase 7 — Elementor | `[ ]` Not started | — |
| Phase 8 — Hardening | `[ ]` Not started | — |

---

*Last updated: June 2026*
