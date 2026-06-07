# FOLDER-STRUCTURE.md — WC Personalisation Panel
> Every file explained. Reflects v0.6.0. Update this when adding/removing files.

```
wc-personalisation-panel/
│
├── wc-personalisation-panel.php   Bootstrap: header, constants, WC active-check
│                                   on plugins_loaded, top-level HPOS declaration,
│                                   loads + boots all classes, plugin action links.
├── uninstall.php                  Deletes sets + options ONLY if behaviour
│                                   "remove_on_uninstall" is on. Keeps order meta.
│
├── CLAUDE.md                      Standing rules + real architecture (read first).
├── BUILD-PLAN.md                  Phase/task log.
├── FOLDER-STRUCTURE.md            This file.
├── DECISIONS.md                   Architectural decisions + why.
├── TESTING.md                     Manual test checklist.
├── CHANGELOG.md                   Version history.
├── LOCAL-DEV.md                   Local by Flywheel env reference.
├── PUBLISHING.md                  Delivery / WP.org publishing guide.
├── readme.txt                     WordPress.org plugin readme.
├── phpcs.xml · wp-cli.yml · .editorconfig · .gitattributes · .gitignore
│
├── includes/
│   ├── class-settings-store.php     SINGLE source of truth. get()/get_set()
│   │                                read placements (+legacy fallback), design,
│   │                                behaviour, set_price. resolve_step() validates
│   │                                a choice OR text submission server-side.
│   │                                get_set_id() resolves product→apply_all→category.
│   ├── class-admin-menu.php         Top-level "Personalisation" menu + Settings
│   │                                submenu; removes the phantom duplicate item.
│   ├── class-settings-page.php      Global Panel Settings page (Design + Behaviour
│   │                                tabs). sanitize() merges per-tab (no wipe);
│   │                                font whitelist; capability filter.
│   ├── class-personalisation-cpt.php  The CPT + builder UI: placements (image,
│   │                                duplicate, delete) → steps (Choice|Text type)
│   │                                → choices (image/name/price). Category box,
│   │                                Pricing box (flat fee), Button-text box.
│   │                                save_placements() + admin list columns.
│   ├── class-cart-handler.php       Trigger button (placement-aware hooks),
│   │                                panel render, asset enqueue (design tokens
│   │                                as inline CSS vars, currency, trimmed config),
│   │                                add_cart_item_data (+unique key), cart display.
│   ├── class-price-calculator.php   apply_prices() = base_price + add-on
│   │                                (idempotent). calculate() sums selections.
│   ├── class-ajax-handler.php       wcpp_add_to_cart (+nopriv): validate each
│   │                                placement/step by ID via resolve_step,
│   │                                server-derive prices, add to cart.
│   ├── class-order-handler.php      Persist hidden _wcpp_* order meta;
│   │                                display per-placement in admin/order/email.
│   ├── class-email-handler.php      Reserved seam (order_item_meta_end covers email).
│   ├── class-product-meta.php       Per-product set selector (override category).
│   └── class-activator.php          Activation: seed wcpp_settings defaults.
│
├── assets/
│   ├── css/
│   │   ├── panel-default.css     Front-end. Design CSS custom properties,
│   │   │                         placement picker cards (.wcpp-select-card),
│   │   │                         choice grid/list, text-input step, review,
│   │   │                         mobile full-width. Luxury/editorial styling.
│   │   └── admin.css             Builder (placements/steps/choices), settings,
│   │                             order-meta display.
│   └── js/
│       ├── wcpp-panel.js         Front-end wizard (select→step→review), text
│       │                         steps, edit/remove, currency formatting.
│       ├── wcpp-admin.js         Builder: add/duplicate/delete placements,
│       │                         add steps (choice/text toggle), choices,
│       │                         shared image picker (choices + placements).
│       └── wcpp-settings.js      Colour pickers on the settings page.
│
├── templates/                   Theme-overridable (yourtheme/wcpp/…)
│   ├── button.php               Trigger button.
│   └── panel.php                Drawer shell (header, progress, content, footer).
│
└── languages/  wcpp.pot (generated in hardening phase)
```

## Naming
- Classes `WCPP_Pascal_Case` · functions `wcpp_snake` · constants `WCPP_UPPER`
- DB option `wcpp_settings` · post meta `_wcpp_*` · order item meta `_wcpp_*`
- IDs: placement `pl_*`, step `st_*`, choice `ch_*`
- JS classes `.wcpp-*` · events `.wcppPanel`

*Updated: v0.6.0*
