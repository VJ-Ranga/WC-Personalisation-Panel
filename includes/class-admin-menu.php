<?php
/**
 * Admin Menu — builds the top-level "Personalisation" menu with two
 * separate items: Personalisation Sets (the builder) and Panel Settings.
 *
 * Menu structure:
 *   Personalisation                (top-level, dashicon)
 *     ├── Personalisation Sets     (CPT list — the builder)
 *     ├── Add New Set
 *     └── Panel Settings           (design + behaviour)
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCPP_Admin_Menu
 */
class WCPP_Admin_Menu {

	/**
	 * Top-level menu slug. The CPT attaches to this via show_in_menu.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'wcpp-personalisation';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'clean_submenu' ), 999 );
	}

	/**
	 * Register the top-level menu + the Settings submenu.
	 * The CPT's own submenus (Sets list, Add New) attach automatically
	 * because the CPT is registered with show_in_menu = MENU_SLUG.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Personalisation', 'wcpp' ),
			__( 'Personalisation', 'wcpp' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			'__return_null',
			'dashicons-admin-customizer',
			56 // Just below WooCommerce.
		);

		// Panel Settings — separate menu item.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Panel Settings', 'wcpp' ),
			__( 'Panel Settings', 'wcpp' ),
			'manage_woocommerce',
			'wcpp-settings',
			array( 'WCPP_Settings_Page', 'render_page' )
		);
	}

	/**
	 * Remove the phantom duplicate first submenu item that WordPress
	 * auto-creates for the top-level menu page (it repeats the parent name).
	 *
	 * @return void
	 */
	public static function clean_submenu() {
		global $submenu;

		if ( empty( $submenu[ self::MENU_SLUG ] ) ) {
			return;
		}

		foreach ( $submenu[ self::MENU_SLUG ] as $key => $item ) {
			// The phantom item has the menu slug itself as its link (index 2).
			if ( isset( $item[2] ) && self::MENU_SLUG === $item[2] ) {
				unset( $submenu[ self::MENU_SLUG ][ $key ] );
			}
		}
	}
}
