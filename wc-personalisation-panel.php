<?php
/**
 * Plugin Name:       WC Personalisation Panel
 * Plugin URI:        https://github.com/VJ-Ranga/WC-Personalisation-Panel
 * Description:       Adds a slide-in personalisation drawer to WooCommerce product pages. Customers choose location, type, text, font and colour before adding to cart.
 * Version:           0.1.0
 * Author:            Cloudycode
 * Author URI:        https://github.com/VJ-Ranga
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wcpp
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 *
 * @package WC_Personalisation_Panel
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WCPP_VERSION',  '0.1.0' );
define( 'WCPP_PATH',     plugin_dir_path( __FILE__ ) );
define( 'WCPP_URL',      plugin_dir_url( __FILE__ ) );
define( 'WCPP_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main init — fires on plugins_loaded so WooCommerce is already registered.
 */
add_action( 'plugins_loaded', 'wcpp_init', 10 );

/**
 * Check WooCommerce is active, then boot the plugin.
 * If WooCommerce is missing, show an admin notice and stop — no fatal error.
 *
 * @return void
 */
function wcpp_init() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wcpp_notice_missing_woocommerce' );
		return;
	}

	// Load all classes.
	require_once WCPP_PATH . 'includes/class-activator.php';
	require_once WCPP_PATH . 'includes/class-settings-store.php';
	require_once WCPP_PATH . 'includes/class-price-calculator.php';
	require_once WCPP_PATH . 'includes/class-cart-handler.php';
	require_once WCPP_PATH . 'includes/class-order-handler.php';
	require_once WCPP_PATH . 'includes/class-ajax-handler.php';
	require_once WCPP_PATH . 'includes/class-admin-settings.php';
	require_once WCPP_PATH . 'includes/class-product-meta.php';
	require_once WCPP_PATH . 'includes/class-email-handler.php';

	// Boot each class.
	WCPP_Cart_Handler::init();
	WCPP_Order_Handler::init();
	WCPP_Ajax_Handler::init();
	WCPP_Admin_Settings::init();
	WCPP_Product_Meta::init();
	WCPP_Email_Handler::init();

	// Declare WooCommerce HPOS compatibility.
	add_action( 'before_woocommerce_init', 'wcpp_declare_hpos_compatibility' );

	// Load Elementor module only if Elementor is active — guarded.
	add_action( 'elementor/loaded', 'wcpp_maybe_load_elementor' );
}

/**
 * Admin notice — shown when WooCommerce is not active.
 *
 * @return void
 */
function wcpp_notice_missing_woocommerce() {
	?>
	<div class="notice notice-error is-dismissible">
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: WooCommerce plugin link */
					__( '<strong>WC Personalisation Panel</strong> requires %s to be installed and active.', 'wcpp' ),
					'<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
				)
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 *
 * @return void
 */
function wcpp_declare_hpos_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}

/**
 * Load Elementor module — only runs if Elementor fired its loaded action.
 * Core plugin must work 100% without this.
 *
 * @return void
 */
function wcpp_maybe_load_elementor() {
	$settings = WCPP_Settings_Store::get_global();
	if ( ! empty( $settings['elementor_enabled'] ) && file_exists( WCPP_PATH . 'elementor/class-elementor-module.php' ) ) {
		require_once WCPP_PATH . 'elementor/class-elementor-module.php';
		WCPP_Elementor_Module::init();
	}
}

// Activation / deactivation hooks — must be outside wcpp_init().
register_activation_hook( __FILE__, array( 'WCPP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WCPP_Activator', 'deactivate' ) );
