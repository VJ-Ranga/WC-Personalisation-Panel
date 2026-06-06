# FOLDER-STRUCTURE.md — WC Personalisation Panel
> Every file and folder explained. When adding a new file, add it here too.
> If a file's purpose isn't clear from this doc, the doc is wrong — update it.

---

```
wc-personalisation-panel/
│
│   ── ROOT FILES ──────────────────────────────────────────────────────────
│
├── wc-personalisation-panel.php
│       Plugin bootstrap. WordPress reads this for the plugin header.
│       Defines constants. Checks WooCommerce is active. Loads autoloader.
│       Hooks: plugins_loaded (priority 10).
│       Nothing else should be bootstrapped from here — delegate to classes.
│
├── CLAUDE.md
│       Standing rules for AI-assisted development. Read every session.
│       Contains: architecture rules, security rules, non-negotiables, key hooks.
│
├── BUILD-PLAN.md
│       Phase-by-phase task list with checkboxes. The living work log.
│       Update status as phases complete.
│
├── FOLDER-STRUCTURE.md
│       This file. Annotated map of every file.
│
├── LOCAL-DEV.md
│       Local by Flywheel environment details. Paths, credentials, PHP config.
│       Dev commands, known issues, test accounts.
│
├── DECISIONS.md
│       Log of architectural decisions and why they were made.
│       Prevents re-debating settled choices in future sessions.
│
├── TESTING.md
│       Manual test checklist. Step-by-step what to click and what to verify.
│       One section per phase. Run before marking any phase done.
│
├── CHANGELOG.md
│       Version history. Updated before every release.
│       Format: Keep a Changelog (keepachangelog.com).
│
├── readme.txt
│       WordPress.org standard plugin readme.
│       Required for publishing to WordPress.org.
│       Contains: description, installation, FAQ, changelog, screenshots list.
│
├── PUBLISHING.md
│       Step-by-step guide to deliver the plugin to a client or publish to WP.org.
│       Includes: pre-publish checklist, zip packaging, SVN instructions.
│
├── phpcs.xml
│       PHP CodeSniffer config. Run: phpcs . from plugin root.
│       Sets WordPress Coding Standards, wcpp prefix rules, text domain.
│
├── wp-cli.yml
│       WP-CLI config. Points to Local by Flywheel WP installation.
│       Lets you run `wp plugin activate wc-personalisation-panel` from here.
│
├── .editorconfig
│       Code editor formatting rules. Tabs for PHP/CSS/JS (WordPress standard).
│       Keeps formatting consistent across VS Code, PhpStorm, etc.
│
│   ── INCLUDES (PHP Classes) ────────────────────────────────────────────
│
├── includes/
│   │
│   ├── class-settings-store.php
│   │       THE ONLY place settings are read and merged.
│   │       Public API: WCPP_Settings_Store::get( $product_id ) → array
│   │       Reads: wcpp_global_settings option + _wcpp_product_settings post meta.
│   │       Per-product values override global defaults.
│   │       Everything else calls this — never read options/meta directly.
│   │
│   ├── class-cart-handler.php
│   │       All WooCommerce cart hooks.
│   │       Hooks owned:
│   │         - woocommerce_before_add_to_cart_button (render button)
│   │         - woocommerce_add_cart_item_data (attach data + unique_key)
│   │         - woocommerce_get_item_data (display in cart/mini-cart)
│   │
│   ├── class-order-handler.php
│   │       All WooCommerce order/checkout hooks.
│   │       Hooks owned:
│   │         - woocommerce_checkout_create_order_line_item (persist meta to order)
│   │         - woocommerce_order_item_meta_end (display in admin + customer order)
│   │
│   ├── class-ajax-handler.php
│   │       All AJAX endpoints. Both logged-in and guest versions.
│   │       Endpoints:
│   │         - wp_ajax_wcpp_add_to_cart
│   │         - wp_ajax_nopriv_wcpp_add_to_cart
│   │         - wp_ajax_wcpp_get_price
│   │         - wp_ajax_nopriv_wcpp_get_price
│   │       Every handler: validate nonce → sanitise → whitelist → respond.
│   │
│   ├── class-admin-settings.php
│   │       Global settings page under WooCommerce admin menu.
│   │       Hooks owned: admin_menu, admin_init.
│   │       Capability required: manage_woocommerce.
│   │
│   ├── class-product-meta.php
│   │       Per-product meta box on the product edit screen.
│   │       Hooks owned: add_meta_boxes, save_post_product.
│   │       Capability required: edit_product.
│   │
│   ├── class-price-calculator.php
│   │       Server-side price calculation. The one source of truth for pricing.
│   │       Public API: WCPP_Price_Calculator::calculate( $product_id, $data ) → float
│   │       Supports: flat fee, per-character fee, or both combined.
│   │       Hook owned: woocommerce_before_calculate_totals (apply price to cart).
│   │
│   ├── class-email-handler.php
│   │       Personalisation output in WooCommerce order emails.
│   │       Hook owned: woocommerce_email_order_meta.
│   │
│   └── class-activator.php
│           Plugin activation and deactivation callbacks.
│           Activation: set default options if not set, flush rewrite rules.
│           Deactivation: flush rewrite rules (do NOT delete data on deactivate).
│           Uninstall: handled in uninstall.php (deletes data only on uninstall).
│
│   ── ASSETS ────────────────────────────────────────────────────────────
│
├── assets/
│   │
│   ├── css/
│   │   ├── panel-default.css
│   │   │       Main drawer styles. Slide-in animation. Step layout.
│   │   │       Progress bar. Font picker. Colour swatches. Mobile: full-width below breakpoint.
│   │   │       NO theme-specific selectors. Scoped to #wcpp-panel.
│   │   │
│   │   └── admin.css
│   │           Admin meta box and settings page styles only.
│   │           Loaded on admin screens only.
│   │
│   └── js/
│       ├── wcpp-panel.js
│       │       Wizard UI. All front-end interactivity.
│       │       IIFE wrapped. ES5. Reads window.wcpp (localised from PHP).
│       │       State machine: currentStep, selections object.
│       │       Events namespaced: .wcppPanel
│       │       AJAX calls to wcpp_add_to_cart and wcpp_get_price.
│       │       No console.log in production. No generic WC/theme selectors.
│       │
│       └── wcpp-admin.js
│               Admin JS: meta box dynamic fields, settings page enhancements.
│               Loaded on admin screens only.
│
│   ── TEMPLATES ─────────────────────────────────────────────────────────
│
├── templates/
│   │       Theme-overridable templates. Standard WooCommerce override pattern.
│   │       Theme devs copy to: yourtheme/wcpp/panel.php (or button.php)
│   │
│   ├── panel.php
│   │       The drawer HTML. Hidden by default (CSS transform).
│   │       Contains: drawer wrapper, step containers (empty, filled by JS), close button.
│   │       JS reads this structure — do not change IDs/classes without updating JS.
│   │
│   └── button.php
│           The "Add Personalisation" button HTML.
│           Auto-injected via woocommerce_before_add_to_cart_button hook.
│           Also used by shortcode and template tag.
│
│   ── LANGUAGES ─────────────────────────────────────────────────────────
│
├── languages/
│   └── wcpp.pot
│           Translation template. Generated in Phase 8.
│           Command: wp i18n make-pot . languages/wcpp.pot --domain=wcpp
│           All strings use text domain: wcpp
│
│   ── ELEMENTOR (Phase 7, guarded) ──────────────────────────────────────
│
└── elementor/
    │       Loads ONLY IF: did_action('elementor/loaded') AND elementor toggle is on.
    │       Core plugin must work 100% with this entire folder absent/ignored.
    │
    ├── class-elementor-module.php
    │       Elementor module bootstrap. Registers the widget. Declares compatibility.
    │
    └── widgets/
        └── class-widget-panel.php
                Elementor widget. Wraps templates/button.php — no duplicated logic.
                Appears in Elementor editor panel under "WooCommerce" category.
```

---

## File Naming Conventions

| Type | Convention | Example |
|---|---|---|
| PHP classes | `class-` + kebab-case | `class-settings-store.php` |
| PHP functions files | kebab-case | `helpers.php` |
| CSS files | kebab-case | `panel-default.css` |
| JS files | `wcpp-` prefix + kebab-case | `wcpp-panel.js` |
| Templates | kebab-case | `panel.php`, `button.php` |
| Doc files | UPPERCASE | `CLAUDE.md`, `BUILD-PLAN.md` |

---

## Class Naming Conventions

| Element | Convention | Example |
|---|---|---|
| PHP class | `WCPP_` + PascalCase | `WCPP_Settings_Store` |
| PHP function | `wcpp_` + snake_case | `wcpp_render_button()` |
| PHP constant | `WCPP_` + SCREAMING_SNAKE | `WCPP_VERSION` |
| JS variable | camelCase | `currentStep`, `wcppPanel` |
| CSS class | `wcpp-` + kebab-case | `.wcpp-panel`, `.wcpp-step` |
| CSS id | `wcpp-` + kebab-case | `#wcpp-panel`, `#wcpp-open` |
| DB option | `wcpp_` + snake_case | `wcpp_global_settings` |
| Post meta key | `_wcpp_` + snake_case | `_wcpp_product_settings` |
| Order item meta | `wcpp_` + snake_case | `wcpp_location`, `wcpp_text` |
| AJAX action | `wcpp_` + snake_case | `wcpp_add_to_cart` |
| Nonce action | `wcpp_` + snake_case | `wcpp_nonce` |

---

*Last updated: June 2026*
