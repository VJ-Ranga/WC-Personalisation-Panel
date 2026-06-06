# PUBLISHING.md — WC Personalisation Panel
> Step-by-step guide for delivering the plugin to a client OR publishing to WordPress.org.
> Run through this checklist before any release. Never skip steps.

---

## Pre-Publish Checklist (run before packaging)

### Code Quality
- [ ] `phpcs .` passes with zero errors and zero warnings
- [ ] WP_DEBUG = true: zero PHP notices, zero warnings on all test flows
- [ ] Browser console: zero errors on product page, cart, checkout
- [ ] All Phase 8 tests in TESTING.md are ticked

### Security
- [ ] Every AJAX call has nonce verification
- [ ] Every settings/meta save has nonce + capability check
- [ ] All user input sanitised, all output escaped
- [ ] Server-side whitelist on all option values
- [ ] Server-side text length enforcement confirmed

### Version Bump
- [ ] Update `WCPP_VERSION` constant in `wc-personalisation-panel.php`
- [ ] Update `Stable tag` in `readme.txt` to match
- [ ] Update `CHANGELOG.md` — move Unreleased items to the new version with today's date
- [ ] Update `Tested up to` in `readme.txt` to the current WordPress version

### Translation
- [ ] Run: `wp i18n make-pot . languages/wcpp.pot --domain=wcpp`
- [ ] Confirm .pot file is up to date (all new strings captured)

### Documentation
- [ ] `readme.txt` is accurate and complete
- [ ] `CHANGELOG.md` entry is written clearly for end users
- [ ] `FOLDER-STRUCTURE.md` reflects actual files in the plugin
- [ ] `BUILD-PLAN.md` — all phases marked complete

---

## Option A — Client Delivery (zip file)

### Build the Zip

1. Open the plugin folder: `C:\Users\VJR-LAP3\Local Sites\2202new\app\public\wp-content\plugins\wc-personalisation-panel`
2. **Remove dev-only files from the zip** (do not delete from your repo — just exclude):
   - `CLAUDE.md`
   - `BUILD-PLAN.md`
   - `FOLDER-STRUCTURE.md`
   - `DECISIONS.md`
   - `TESTING.md`
   - `LOCAL-DEV.md`
   - `PUBLISHING.md`
   - `phpcs.xml`
   - `wp-cli.yml`
   - `.editorconfig`
   - `CHANGELOG.md` (optional — can include for reference)
3. Zip the folder: `wc-personalisation-panel.zip`
4. Test the zip:
   - Install on a clean WordPress + WooCommerce site
   - Activate — confirm no errors
   - Configure and run a test order — confirm full flow works

### What to deliver to client
- [ ] `wc-personalisation-panel.zip`
- [ ] Short installation guide (refer to readme.txt Installation section)
- [ ] Admin guide: how to configure settings, how to enable per product
- [ ] Note: requires WordPress 6.0+ and WooCommerce (latest)

---

## Option B — WordPress.org Plugin Directory

> Only use this if publishing publicly. Not needed for client-only delivery.

### Preparation
- [ ] Create account at https://wordpress.org/plugins/developers/
- [ ] Submit plugin for review: https://wordpress.org/plugins/developers/add/
- [ ] Wait for approval (typically 1–4 weeks for new plugins)
- [ ] Receive SVN repository URL after approval

### SVN Commit (first release)

```bash
# Check out the SVN repo (replace YOUR-PLUGIN-SLUG)
svn co https://plugins.svn.wordpress.org/wc-personalisation-panel .

# Copy plugin files to trunk
cp -r /path/to/plugin/files/* trunk/

# Add screenshots to assets/ folder (not trunk)
# assets/screenshot-1.png, screenshot-2.png, etc.
# assets/banner-772x250.png  (directory banner)
# assets/icon-256x256.png    (plugin icon)

# Add and commit
svn add trunk/* --force
svn add assets/* --force
svn ci -m "First release - v1.0.0"

# Tag the release
svn cp trunk tags/1.0.0
svn ci -m "Tagging version 1.0.0"
```

### WordPress.org Asset Sizes
| Asset | Size |
|---|---|
| Plugin icon | 256×256 px (also 128×128) |
| Directory banner | 772×250 px (also 1544×500 @2x) |
| Screenshots | Max 1200px wide, .png or .jpg |

### After Publishing
- [ ] Confirm plugin is live at: `https://wordpress.org/plugins/wc-personalisation-panel/`
- [ ] Test install from WordPress admin → Plugins → Add New → search for plugin
- [ ] Update `readme.txt` → `Tested up to` whenever a new WordPress version releases

---

## Version Number Guide

| Change type | Example | Version bump |
|---|---|---|
| Bug fix only | Fix cart de-dup edge case | 1.0.0 → 1.0.1 |
| New feature, backward compatible | Add 2nd personalisation | 1.0.0 → 1.1.0 |
| Breaking change | Rename all meta keys | 1.0.0 → 2.0.0 |

---

## Post-Delivery Handoff Notes for Client

Include these notes when delivering to a client:

1. **Requires:** WordPress 6.0+, WooCommerce (latest recommended)
2. **Enable per product:** WP Admin → Products → Edit Product → Personalisation Settings meta box → tick Enable
3. **Global settings:** WP Admin → WooCommerce → Personalisation
4. **Customising design:** Copy `wp-content/plugins/wc-personalisation-panel/templates/` files into `wp-content/themes/YOUR-THEME/wcpp/` and edit
5. **Do not delete order item meta** — personalisation details are stored in the order. Deleting them is irreversible.
6. **Updates:** Always test on a staging site before updating on production

---

*Last updated: June 2026*
