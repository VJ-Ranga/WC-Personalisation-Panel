<?php
/**
 * Cart Handler — all WooCommerce cart hooks.
 *
 * Hooks owned:
 *   - woocommerce_before_add_to_cart_button  (render the button)
 *   - woocommerce_add_cart_item_data         (attach data + unique key)
 *   - woocommerce_get_item_data              (display in cart / mini-cart)
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCPP_Cart_Handler
 */
class WCPP_Cart_Handler {

	/**
	 * Register all hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_button' ) );
		add_action( 'wp_footer',                             array( __CLASS__, 'render_panel' ) );
		add_filter( 'woocommerce_add_cart_item_data',        array( __CLASS__, 'attach_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data',             array( __CLASS__, 'display_in_cart' ), 10, 2 );
		add_action( 'wp_enqueue_scripts',                    array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue front-end CSS and JS on single product pages only.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( ! is_product() ) {
			return;
		}

		$product_id = get_the_ID();
		$config     = WCPP_Settings_Store::get( $product_id );

		if ( ! $config['enabled'] ) {
			return;
		}

		wp_enqueue_style(
			'wcpp-panel',
			WCPP_URL . 'assets/css/panel-default.css',
			array(),
			WCPP_VERSION
		);

		wp_enqueue_script(
			'wcpp-panel',
			WCPP_URL . 'assets/js/wcpp-panel.js',
			array( 'jquery' ),
			WCPP_VERSION,
			true
		);

		// Pass settings + AJAX info to JS.
		wp_localize_script(
			'wcpp-panel',
			'wcpp',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wcpp_nonce' ),
				'config'  => $config,
				'i18n'    => array(
					'addPersonalisation' => esc_html__( 'Add Personalisation', 'wcpp' ),
					'addToBag'           => esc_html__( 'Add to Bag', 'wcpp' ),
					'back'               => esc_html__( 'Back', 'wcpp' ),
					'close'              => esc_html__( 'Close', 'wcpp' ),
					'confirmClose'       => esc_html__( 'You\'ll lose your personalisation choices. Are you sure?', 'wcpp' ),
					'errorGeneric'       => esc_html__( 'Something went wrong. Please try again.', 'wcpp' ),
					'stepLocation'       => esc_html__( 'Choose Location', 'wcpp' ),
					'stepType'           => esc_html__( 'Choose Type', 'wcpp' ),
					'stepText'           => esc_html__( 'Enter Text', 'wcpp' ),
					'stepFont'           => esc_html__( 'Choose Font', 'wcpp' ),
					'stepColour'         => esc_html__( 'Choose Colour', 'wcpp' ),
					'stepSummary'        => esc_html__( 'Review & Add', 'wcpp' ),
				),
			)
		);
	}

	/**
	 * Render the panel drawer into wp_footer on product pages.
	 * Output once — JS controls show/hide.
	 *
	 * @return void
	 */
	public static function render_panel() {
		if ( ! is_product() ) {
			return;
		}

		$product_id = get_the_ID();
		$config     = WCPP_Settings_Store::get( $product_id );

		if ( ! $config['enabled'] ) {
			return;
		}

		$template = wcpp_locate_template( 'panel.php' );
		if ( $template ) {
			include $template;
		}
	}

	/**
	 * Render the "Add Personalisation" button on the product page.
	 * Only renders if personalisation is enabled for this product.
	 *
	 * @return void
	 */
	public static function render_button() {
		$product_id = get_the_ID();
		$config     = WCPP_Settings_Store::get( $product_id );

		if ( ! $config['enabled'] ) {
			return;
		}

		// Load template — theme can override via yourtheme/wcpp/button.php.
		$template = wcpp_locate_template( 'button.php' );
		if ( $template ) {
			include $template;
		}
	}

	/**
	 * Attach personalisation data to the cart item.
	 * Also adds a unique key so WooCommerce doesn't merge different monograms.
	 *
	 * @param array $cart_item_data Existing cart item data.
	 * @param int   $product_id     Product ID.
	 * @return array
	 */
	public static function attach_data( $cart_item_data, $product_id ) {
		// Data is posted via AJAX — this filter handles the standard add-to-cart flow too.
		// Phase 2: AJAX handler builds the data and passes it via add_to_cart() 5th argument.
		// This filter receives it here and adds the unique key.

		if ( ! empty( $cart_item_data['wcpp_data'] ) ) {
			// Unique key prevents WooCommerce merging items with the same product ID.
			$cart_item_data['wcpp_unique_key'] = md5( wp_json_encode( $cart_item_data['wcpp_data'] ) . microtime() );
		}

		return $cart_item_data;
	}

	/**
	 * Display personalisation data in the cart and mini-cart.
	 *
	 * @param array $item_data  Existing display data.
	 * @param array $cart_item  Cart item array.
	 * @return array
	 */
	public static function display_in_cart( $item_data, $cart_item ) {
		if ( empty( $cart_item['wcpp_data'] ) ) {
			return $item_data;
		}

		$data = $cart_item['wcpp_data'];

		// TODO: Phase 2+ — build full display from all data keys.
		if ( ! empty( $data['location'] ) ) {
			$item_data[] = array(
				'name'  => esc_html__( 'Personalisation Location', 'wcpp' ),
				'value' => esc_html( $data['location'] ),
			);
		}

		return $item_data;
	}
}

/**
 * Template locator — checks theme first, falls back to plugin templates.
 *
 * @param string $template_name Template filename (e.g. 'button.php').
 * @return string|false Full path to template, or false if not found.
 */
function wcpp_locate_template( $template_name ) {
	$theme_file  = get_stylesheet_directory() . '/wcpp/' . $template_name;
	$plugin_file = WCPP_PATH . 'templates/' . $template_name;

	if ( file_exists( $theme_file ) ) {
		return $theme_file;
	}
	if ( file_exists( $plugin_file ) ) {
		return $plugin_file;
	}
	return false;
}

/**
 * Template tag — render the personalisation button from theme templates.
 *
 * @return void
 */
function wcpp_render_button() {
	WCPP_Cart_Handler::render_button();
}
