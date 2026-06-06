# TESTING.md — WC Personalisation Panel
> Manual test checklist. Run the relevant section before marking a phase done.
> Tick boxes as you go. If anything fails, log it at the bottom of this file.

---

## Before Any Testing Session

- [ ] WP_DEBUG is set to `true` in wp-config.php
- [ ] Browser DevTools console is open
- [ ] PHP error log is clear: delete or clear `wp-content/debug.log`
- [ ] Plugin is activated in WP Admin → Plugins

---

## Phase 0 Tests — Scaffold

- [ ] Plugin appears in WP Admin → Plugins list with correct name and version
- [ ] Plugin activates without any errors or warnings
- [ ] Plugin deactivates without any errors or warnings
- [ ] **WooCommerce off test:**
  - Deactivate WooCommerce
  - Go to WP Admin → Dashboard
  - See admin notice: "WC Personalisation Panel requires WooCommerce"
  - No fatal PHP error, no white screen
  - Reactivate WooCommerce — notice disappears

---

## Phase 1 Tests — Settings

### Global Settings
- [ ] WP Admin → WooCommerce → Personalisation page exists and loads
- [ ] Fill in all fields, click Save — page reloads, values are saved
- [ ] Check: locations saved correctly (comma-separated or however stored)
- [ ] Check: flat fee saved as number, not string
- [ ] Check: non-returnable checkbox saves on/off correctly
- [ ] Check: settings visible after page refresh (not lost on reload)

### Per-Product Meta Box
- [ ] Open any product in WP Admin → Products → Edit Product
- [ ] Meta box "Personalisation Settings" appears on the right panel or below content
- [ ] Enable personalisation checkbox — save product — checkbox stays ticked on reload
- [ ] Override locations — save — value persists on reload
- [ ] Set per-product flat fee — save — value persists
- [ ] Confirm: `WCPP_Settings_Store::get( $product_id )` returns per-product override, not global (add temporary `error_log( print_r( $config, true ) );` to verify)

---

## Phase 2 Tests — Vertical Slice (GATE — ALL MUST PASS)

### Setup
- [ ] Enable personalisation on a simple product (use the meta box from Phase 1)
- [ ] Set at least 3 locations in settings: e.g. "Chest", "Cuff", "Collar"

### Button + Drawer
- [ ] Go to the product page on the front end
- [ ] "Add Personalisation" button is visible below the add-to-cart button
- [ ] Click button → drawer slides in from the right
- [ ] Drawer shows list of location buttons (Chest, Cuff, Collar)
- [ ] Close button (X) closes the drawer
- [ ] No JavaScript errors in browser console

### Logged-In User Flow
- [ ] Log in as a customer account
- [ ] Go to product page, open drawer, select "Chest"
- [ ] Click "Add to Bag"
- [ ] Success message appears (or redirect to cart)
- [ ] Go to Cart page
- [ ] Product line shows: "Personalisation: Chest" below the product name
- [ ] Go to Checkout, complete order (use test payment or Stripe test mode)
- [ ] Go to WP Admin → WooCommerce → Orders → open the new order
- [ ] Order line item shows: "Personalisation: Chest" in the meta section
- [ ] Click "Resend New Order" email → open email
- [ ] Email shows personalisation details: "Personalisation: Chest"

### Guest User Flow
- [ ] Log out
- [ ] Go to product page, open drawer, select "Cuff"
- [ ] Click "Add to Bag"
- [ ] Cart shows: "Personalisation: Cuff"
- [ ] Complete checkout as guest (no account)
- [ ] Check order in admin: shows "Personalisation: Cuff"
- [ ] Check order confirmation email: shows "Personalisation: Cuff"

### Cart De-duplication
- [ ] Add same product with "Chest" personalisation → cart has 1 line
- [ ] Add same product again with "Cuff" personalisation → cart now has 2 separate lines
- [ ] Confirm: NOT merged into 1 line with quantity 2

### Security
- [ ] Open browser DevTools → Network tab
- [ ] Submit the drawer form
- [ ] Find the AJAX request to `admin-ajax.php`
- [ ] Modify the `location` value to something not in the allowed list (e.g. "hacked")
- [ ] Confirm: server returns error, item not added to cart

### Debug Check
- [ ] Open `wp-content/debug.log` (if WP_DEBUG_LOG is on)
- [ ] Zero PHP notices, zero warnings during the entire flow above

---

## Phase 3 Tests — Full Wizard

- [ ] All 6 steps render in order: Location → Type → Text/Symbol → Font → Colour → Summary
- [ ] Progress bar advances on each step
- [ ] Back button on every step goes to previous step
- [ ] Back button retains previous selection (not reset)
- [ ] Cannot advance without making a selection on each required step
- [ ] Text step: character counter works (e.g. shows "2/3" for 3-char max)
- [ ] Text step: cannot enter more than max characters
- [ ] Symbol step: clicking a symbol selects it (visual feedback)
- [ ] Font step: font names display in their own font face
- [ ] Colour step: swatches show correct hex colours
- [ ] Summary step: shows all chosen options correctly
- [ ] Close drawer mid-wizard: confirm dialog appears "You'll lose your personalisation"
- [ ] Confirm close: drawer closes, state resets
- [ ] Cancel close: drawer stays open, state preserved
- [ ] **Mobile (360px):** open DevTools → device emulation → 360px wide
  - [ ] All steps visible and scrollable within drawer
  - [ ] Page body does not scroll when drawer is open
  - [ ] All tap targets are large enough (44px min)
  - [ ] No horizontal overflow

---

## Phase 4 Tests — Pricing

- [ ] Set flat fee = £10.00 in settings
- [ ] Open drawer, complete all steps → summary shows "Personalisation: £10.00"
- [ ] Add to cart → cart line shows product price + personalisation price
- [ ] Cart total = product price + £10.00
- [ ] Place order → order line shows "Personalisation fee: £10.00"
- [ ] Email shows "Personalisation fee: £10.00"
- [ ] All four values (drawer, cart, order, email) show £10.00 — they agree
- [ ] Set per-char fee = £2.00, enter 3 characters → preview shows £6.00
- [ ] Set flat fee £5 + per-char £2, enter 3 chars → preview shows £11.00
- [ ] **Manipulation test:**
  - Open network tab, find price AJAX call
  - Modify the response or post a fake price
  - Confirm: cart/order still shows server-calculated price, not the manipulated one

---

## Phase 5 Tests — Edge Cases

### Variable Products
- [ ] Enable personalisation on a variable product (e.g. has Size: S/M/L)
- [ ] Go to product page — "Add Personalisation" button is visible but disabled (or not shown until variant selected)
- [ ] Select a size variant → "Add Personalisation" button becomes active
- [ ] Complete wizard → add to cart
- [ ] Cart shows: correct variant + personalisation details
- [ ] Order shows: correct variation ID in line item meta

### Non-Returnable Flag
- [ ] Enable "non-returnable" on a product
- [ ] Complete order with personalisation
- [ ] Admin order screen shows: "⚠ Non-returnable item" or similar warning
- [ ] Customer email shows non-returnable notice

### WoodMart (if available)
- [ ] Switch theme to WoodMart
- [ ] Product page: button renders correctly
- [ ] Wizard completes successfully
- [ ] WoodMart quick-view: open quick-view → button visible → wizard works
- [ ] No JS errors in console

### Mobile 360px
- [ ] Test all wizard steps at 360px (see Phase 3 mobile tests above)
- [ ] Drawer is full-width at mobile breakpoint
- [ ] No content clipped or hidden behind drawer edges

---

## Phase 6 Tests — Placement

- [ ] Auto-hook: button appears on standard product page (already tested above)
- [ ] Shortcode: add `[wcpp_button]` to a WordPress page → button renders → wizard works
- [ ] Template tag: add `<?php wcpp_render_button(); ?>` to a theme template → button renders

---

## Phase 7 Tests — Elementor

- [ ] **With Elementor active:** Elementor widget appears in panel → drag to page → button renders → wizard works
- [ ] **With Elementor deactivated:**
  - Deactivate Elementor, Header Footer Elementor, NASA Core
  - Go to product page
  - Button renders, wizard works, full Phase 2 flow completes
  - Zero PHP errors, zero console errors
  - Re-activate Elementor — back to normal

---

## Phase 8 Tests — Hardening

- [ ] `phpcs .` from plugin root → 0 errors, 0 warnings
- [ ] `wp i18n make-pot . languages/wcpp.pot` → .pot file generated, contains all strings
- [ ] All strings in PHP files use `__()` or `esc_html__()` with domain `wcpp`
- [ ] Run full Phase 2 checklist on: Elessi theme
- [ ] Run full Phase 2 checklist on: WoodMart theme
- [ ] Run full Phase 2 checklist on: Twenty Twenty-Five theme
- [ ] Run full Phase 2 checklist with: Elementor on
- [ ] Run full Phase 2 checklist with: Elementor off
- [ ] WP_DEBUG true throughout all of the above → debug.log = empty (no notices/warnings)
- [ ] Browser console = zero errors throughout all of the above

---

## Bug Log

> Add issues found during testing here. Format: [Date] Phase X — Description — Status

| Date | Phase | Description | Status |
|---|---|---|---|
| — | — | — | — |

---

*Last updated: June 2026*
