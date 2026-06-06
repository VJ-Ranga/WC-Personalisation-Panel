# Changelog — WC Personalisation Panel
> All notable changes to this plugin are documented here.
> Format: [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
> Versioning: [Semantic Versioning](https://semver.org/)

---

## [Unreleased]

### Added
- Plugin scaffold with WooCommerce safety check (Phase 0)
- Full documentation set: CLAUDE.md, BUILD-PLAN.md, FOLDER-STRUCTURE.md, DECISIONS.md, TESTING.md, LOCAL-DEV.md, PUBLISHING.md

### Changed
- —

### Fixed
- —

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
