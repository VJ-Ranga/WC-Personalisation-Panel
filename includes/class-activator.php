<?php
/**
 * Handles plugin activation and deactivation.
 *
 * @package WC_Personalisation_Panel
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCPP_Activator
 *
 * Runs on plugin activate / deactivate.
 * Does NOT delete data on deactivate — only on uninstall (see uninstall.php).
 */
class WCPP_Activator {

	/**
	 * Runs on plugin activation.
	 * Seeds default global settings if not already set.
	 *
	 * @return void
	 */
	public static function activate() {

		// Seed global settings (design + behaviour) with defaults if not set.
		if ( false === get_option( 'wcpp_settings' ) ) {
			require_once WCPP_PATH . 'includes/class-settings-store.php';
			update_option(
				'wcpp_settings',
				array(
					'design'    => WCPP_Settings_Store::design_defaults(),
					'behaviour' => WCPP_Settings_Store::behaviour_defaults(),
				),
				false
			);
		}

		// Clean up the old option from the previous architecture.
		delete_option( 'wcpp_global_settings' );

		flush_rewrite_rules();
	}

	/**
	 * Runs on plugin deactivation.
	 * Flush rewrite rules only — does NOT delete any data.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
