<?php
/**
 * Plugin Name:       WC Personalisation Panel
 * Plugin URI:        https://github.com/VJ-Ranga/WC-Personalisation-Panel
 * Description:       Adds a slide-in personalisation drawer to WooCommerce product pages. Customers choose location, type, text, font and colour before adding to cart.
 * Version:           0.7.10
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
define( 'WCPP_VERSION',  '0.7.10' );
define( 'WCPP_PATH',     plugin_dir_path( __FILE__ ) );
define( 'WCPP_URL',      plugin_dir_url( __FILE__ ) );
define( 'WCPP_BASENAME', plugin_basename( __FILE__ ) );

// GitHub update checker — loaded independently (does not require WooCommerce).
require_once plugin_dir_path( __FILE__ ) . 'includes/class-github-updater.php';
WCPP_GitHub_Updater::init();

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
	require_once WCPP_PATH . 'includes/class-settings-store.php';
	require_once WCPP_PATH . 'includes/class-admin-menu.php';
	require_once WCPP_PATH . 'includes/class-settings-page.php';
	require_once WCPP_PATH . 'includes/class-personalisation-cpt.php';
	require_once WCPP_PATH . 'includes/class-price-calculator.php';
	require_once WCPP_PATH . 'includes/class-cart-handler.php';
	require_once WCPP_PATH . 'includes/class-order-handler.php';
	require_once WCPP_PATH . 'includes/class-ajax-handler.php';
	require_once WCPP_PATH . 'includes/class-product-meta.php';
	require_once WCPP_PATH . 'includes/class-email-handler.php';

	// Boot each class.
	WCPP_Admin_Menu::init();
	WCPP_Settings_Page::init();
	WCPP_Personalisation_CPT::init();
	WCPP_Price_Calculator::init();
	WCPP_Cart_Handler::init();
	WCPP_Order_Handler::init();
	WCPP_Ajax_Handler::init();
	WCPP_Product_Meta::init();
	WCPP_Email_Handler::init();

	// Declare WooCommerce HPOS compatibility.
	add_action( 'before_woocommerce_init', 'wcpp_declare_hpos_compatibility' );

	// Add Settings link in the plugins list.
	add_filter( 'plugin_action_links_' . WCPP_BASENAME, 'wcpp_plugin_action_links' );

	// Load Elementor module only if Elementor is active — guarded.
	add_action( 'elementor/loaded', 'wcpp_maybe_load_elementor' );
}

/**
 * Add Settings + Docs links to the plugin row in WP Admin → Plugins.
 *
 * @param array $links Existing action links.
 * @return array
 */
function wcpp_plugin_action_links( $links ) {
	$custom = array(
		'sets'     => '<a href="' . esc_url( admin_url( 'edit.php?post_type=wcpp_personalisation' ) ) . '">' . esc_html__( 'Sets', 'wcpp' ) . '</a>',
		'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=wcpp-settings' ) ) . '">' . esc_html__( 'Settings', 'wcpp' ) . '</a>',
	);
	return array_merge( $custom, $links );
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
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS)
 * and the Cart/Checkout Blocks feature (WC 7.6+).
 *
 * HPOS: orders stored in custom tables, not post meta.
 * cart_checkout_blocks: the plugin works with the block-based cart/checkout.
 *   Price modification via woocommerce_before_calculate_totals and order
 *   persistence via woocommerce_checkout_create_order_line_item are both
 *   compatible with the Store API used by WC Blocks.
 *   Note: cart item display (woocommerce_get_item_data) shows in the classic
 *   cart — WC Blocks uses the Store API for item display which requires a
 *   separate Store API extension if that context ever needs it.
 *
 * @return void
 */
function wcpp_declare_hpos_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables',  __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}

/**
 * Load Elementor module — only runs if Elementor fired its loaded action.
 * Core plugin must work 100% without this.
 *
 * @return void
 */
function wcpp_maybe_load_elementor() {
	$behaviour = WCPP_Settings_Store::get_behaviour();
	if ( ! empty( $behaviour['elementor'] ) && file_exists( WCPP_PATH . 'elementor/class-elementor-module.php' ) ) {
		require_once WCPP_PATH . 'elementor/class-elementor-module.php';
		WCPP_Elementor_Module::init();
	}
}

// Activator must be loaded HERE — outside wcpp_init() — because activation
// fires before plugins_loaded, so the class must exist at activation time.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-activator.php';

register_activation_hook( __FILE__, array( 'WCPP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WCPP_Activator', 'deactivate' ) );
