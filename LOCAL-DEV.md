# LOCAL-DEV.md — Local by Flywheel Environment
> Dev environment reference for the wc-personalisation-panel plugin.
> Update this file if anything changes in Local settings.

---

## ⚡ PRACTICAL COMMANDS (verified — for a local AI continuing this work)

There is NO `wp` CLI on PATH and the CLI PHP has NO mysql extension and cannot
bootstrap WP (it dies with "Requirements Not Met"). So use these instead.

**Lint PHP** (Local's PHP binary):
```
PHP="C:/Users/VJR-LAP3/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe"
"$PHP" -l path/to/file.php
```
**Lint JS**: `node -c path/to/file.js`

**MySQL** (port 10042, NOT 3306):
```
MYSQL="C:/Users/VJR-LAP3/AppData/Roaming/Local/lightning-services/mysql-8.4.0/bin/win64/bin/mysql.exe"
"$MYSQL" -u root -proot -P 10042 -h 127.0.0.1 local -e "SQL…"
```

**Test the front-end via curl** — the store is in WooCommerce "Coming Soon"
mode (`woocommerce_coming_soon='yes'`), which hides product pages from guests.
Toggle it off, curl, toggle back on:
```
"$MYSQL" … -e "UPDATE wp_options SET option_value='no'  WHERE option_name='woocommerce_coming_soon';"
curl -sL "http://2202new.local:10029/?p=PRODUCT_ID" -o /tmp/p.html
"$MYSQL" … -e "UPDATE wp_options SET option_value='yes' WHERE option_name='woocommerce_coming_soon';"
```
(Admins bypass coming-soon, so the logged-in user sees pages normally.)

**To run a one-off WP/WC script** (e.g. create products): write a temp PHP file
in the webroot that `require __DIR__.'/wp-load.php';`, guard it with a `?k=` key,
curl it once via the web server (NOT CLI), then DELETE it.

## Test data (current)
- Set "Shirts" (post **3822**): placements Front/Back, text steps, 2 vs 3 colours,
  flat fee $5. Assigned to categories 167 (Shirts) + 176 (T-shirts).
- Simple product to test: **1273** "Gray T-shirt for men" (in Shirts cat).
- Variable product to test: **3827** "Test Personalisation Hoodie" (Size S/M/L).
- Currency: USD ($). Plugin version constant: see WCPP_VERSION.

---

## Local Site Details

| Setting | Value |
|---|---|
| Site name | 2202new |
| Local site path | `C:\Users\VJR-LAP3\Local Sites\2202new` |
| WordPress root | `C:\Users\VJR-LAP3\Local Sites\2202new\app\public` |
| Plugin path | `C:\Users\VJR-LAP3\Local Sites\2202new\app\public\wp-content\plugins\wc-personalisation-panel` |
| Web server | nginx |
| PHP | See Local app → site → PHP version tab |
| MySQL | Local (via Local's bundled MySQL) |
| DB name | `local` |
| DB user | `root` |
| DB password | `root` |
| DB host | `localhost` |
| Table prefix | `wp_` |
| Active theme | Elessi (elessi-theme) + child theme |

---

## Active Plugins on this Install

| Plugin | Purpose in dev |
|---|---|
| WooCommerce | Core dependency — must be active always |
| Elementor | Test Elementor compatibility (Phase 7) |
| Header Footer Elementor | Part of Elementor stack |
| NASA Core | Elessi companion — watch for JS conflicts |
| Revslider | On Elessi — watch for conflicts |
| Contact Form 7 | Low risk — ignore |
| Instagram Feed | Low risk — ignore |
| YITH WooCommerce Compare | Low risk — ignore |

---

## WP_DEBUG Status

```php
// Current setting in wp-config.php:
define( 'WP_DEBUG', false );   // ← CHANGE TO true DURING DEVELOPMENT

// Recommended dev settings — add to wp-config.php above "stop editing":
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );      // logs to /wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', false ); // don't show on screen (use log)
define( 'SCRIPT_DEBUG', true );      // loads unminified JS/CSS
```

> Always develop with WP_DEBUG true. Plugin must produce zero notices and warnings.

---

## Accessing the Site

- **Frontend:** Start the site in Local app → click Open Site
- **Admin:** Open Site → `/wp-admin` | user: check Local credentials
- **phpMyAdmin:** Local app → site → Database tab → Open phpMyAdmin
- **Log files:**
  - nginx errors: `C:\Users\VJR-LAP3\Local Sites\2202new\logs\nginx\error.log`
  - PHP errors: `C:\Users\VJR-LAP3\Local Sites\2202new\logs\php\error.log`
  - WP debug log (when enabled): `wp-content/debug.log`

---

## PHP Configuration (from Local)

| Setting | Value |
|---|---|
| max_execution_time | 1200s |
| memory_limit | 256M |
| max_input_vars | 4000 |
| post_max_size | 1000M |
| error_reporting | E_ALL & ~E_DEPRECATED |

---

## Running WP-CLI Commands

Local by Flywheel bundles WP-CLI. Access it via:

**Option A — Local's built-in terminal:**
Local app → site → Open Site Shell → then run `wp` commands directly

**Option B — Windows terminal with wp-cli.yml:**
```bash
# From inside the plugin folder (wp-cli.yml points to WP root):
wp plugin activate wc-personalisation-panel
wp plugin deactivate wc-personalisation-panel
wp cache flush

# Generate .pot translation file:
wp i18n make-pot . languages/wcpp.pot --domain=wcpp

# Check for PHP errors in WP context:
wp eval 'echo "OK";'

# Export database backup:
wp db export ../../../sql/backup.sql
```

---

## Known Issues on This Install

| Issue | Status | Notes |
|---|---|---|
| WooCommerce cron error (`wc_admin_process_orders_milestone`) | Non-critical | Known WC bug, doesn't affect functionality |
| Elessi + NASA Core JS | Watch | Scope all plugin JS to `#wcpp-panel` to avoid conflicts |

---

## Test Accounts to Create

Before Phase 2 testing, create these in WP admin:

| Account | Email | Purpose |
|---|---|---|
| Test Customer (logged in) | `test@test.com` | Logged-in checkout flow |
| — (none needed) | — | Guest: just use no login |

---

## Elementor Compatibility Testing

- Phase 7 only — do not test earlier
- To test WITH Elementor: activate Elementor + Header Footer Elementor + NASA Core
- To test WITHOUT Elementor: deactivate all three, run full checkout flow, confirm zero errors
- Elementor must be both on AND off without breaking the core plugin

---

*Last updated: June 2026*
