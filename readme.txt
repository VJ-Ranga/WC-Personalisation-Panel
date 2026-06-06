=== WC Personalisation Panel ===
Contributors: cloudycode
Tags: woocommerce, personalisation, monogram, embroidery, customisation
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A slide-in personalisation drawer for WooCommerce product pages. Customers pick location, type, text, font, and colour before adding to cart.

== Description ==

WC Personalisation Panel adds a professional personalisation experience to any WooCommerce product. Customers click "Add Personalisation", choose their options through a guided multi-step wizard, and add to cart — with their choices saved through checkout, visible in orders, and included in emails.

**Features:**

* Slide-in drawer with 6-step wizard (Location, Type, Text/Symbol, Font, Colour, Summary)
* Personalisation details shown in cart, mini-cart, admin orders, and customer emails
* Per-product settings with global defaults
* Flat fee and per-character pricing (server-calculated)
* Variable product support
* Non-returnable item flag for personalised items
* Guest checkout support
* Shortcode and template tag for flexible placement
* Theme-overridable templates
* Optional Elementor widget
* Zero external dependencies

**Compatible themes:** WoodMart, Elessi, Twenty Twenty-Five (and any well-coded WooCommerce theme)

**Note:** WooCommerce must be installed and active.

== Installation ==

1. Upload the `wc-personalisation-panel` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins screen in WordPress admin
3. Go to **WooCommerce → Personalisation** to configure global settings
4. On any product, find the **Personalisation Settings** meta box to enable and customise per product

== Frequently Asked Questions ==

= Does this work with variable products? =
Yes. The personalisation wizard is available after the customer selects a product variation.

= Does it work for guest customers? =
Yes. Guest checkout is fully supported.

= Can I customise the drawer design? =
Yes. Copy `templates/panel.php` and/or `templates/button.php` from the plugin folder into `yourtheme/wcpp/` and edit freely.

= Does it work without Elementor? =
Yes. Elementor is optional. The plugin works on any WooCommerce theme.

= Where does the personalisation data appear? =
In the cart, mini-cart, checkout order summary, admin order screen, and all WooCommerce order emails.

= Can one product have two personalisations? =
Yes (configurable). Customers can add a second personalisation from the cart page.

== Screenshots ==

1. "Add Personalisation" button on the product page
2. Slide-in drawer — Location step
3. Drawer — Text input step with character counter
4. Drawer — Summary step before adding to bag
5. Cart page showing personalisation details
6. Admin order screen showing personalisation meta
7. Global settings page under WooCommerce menu
8. Per-product meta box on the product edit screen

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.
