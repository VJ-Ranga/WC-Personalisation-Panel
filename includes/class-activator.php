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

		// Seed global settings with sensible defaults if not already saved.
		if ( false === get_option( 'wcpp_global_settings' ) ) {
			$defaults = array(
				'enabled'          => false,
				'locations'        => array( 'Chest', 'Cuff', 'Collar', 'Hem' ),
				'types'            => array( 'text', 'initials', 'symbol' ),
				'fonts'            => array( 'serif', 'script', 'block' ),
				'colours'          => array( '#000000', '#FFFFFF', '#C0A882', '#C41E3A' ),
				'flat_fee'         => '0.00',
				'per_char_fee'     => '0.00',
				'max_chars'        => 3,
				'non_returnable'   => true,
				'elementor_enabled'=> false,
			);
			update_option( 'wcpp_global_settings', $defaults, false );
		}

		// Flush rewrite rules.
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
