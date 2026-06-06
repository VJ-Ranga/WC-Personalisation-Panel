<?php
/**
 * Cart Handler — all WooCommerce cart hooks.
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
	 * Enqueue front-end assets on product pages that have a set assigned.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( ! is_product() ) {
			return;
		}

		$product_id = get_the_ID();
		$config     = WCPP_Settings_Store::get( $product_id );

		if ( ! $config ) {
			return;
		}

		$design = $config['design'];

		wp_enqueue_style(
			'wcpp-panel',
			WCPP_URL . 'assets/css/panel-default.css',
			array(),
			WCPP_VERSION
		);

		// Output design as CSS custom properties.
		$font_face = '';
		if ( 'inherit' !== $design['font_family'] ) {
			$font_name = str_replace( array( "'", '"' ), '', explode( ',', $design['font_family'] )[0] );
			$font_face = "@import url('https://fonts.googleapis.com/css2?family=" . urlencode( $font_name ) . ":wght@400;600&display=swap');";
		}

		$css = $font_face . '
		#wcpp-panel, #wcpp-overlay, .wcpp-button {
			--wcpp-primary:       ' . sanitize_hex_color( $design['primary_color'] ) . ';
			--wcpp-panel-width:   ' . absint( $design['panel_width'] ) . 'px;
			--wcpp-font:          ' . esc_attr( $design['font_family'] ) . ';
			--wcpp-radius:        ' . absint( $design['border_radius'] ) . 'px;
		}';
		wp_add_inline_style( 'wcpp-panel', $css );

		wp_enqueue_script(
			'wcpp-panel',
			WCPP_URL . 'assets/js/wcpp-panel.js',
			array( 'jquery' ),
			WCPP_VERSION,
			true
		);

		wp_localize_script(
			'wcpp-panel',
			'wcpp',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wcpp_nonce' ),
				'config'  => $config,
				'i18n'    => array(
					'addToBag'     => esc_html__( 'Add to Bag', 'wcpp' ),
					'back'         => esc_html__( 'Back', 'wcpp' ),
					'next'         => esc_html__( 'Next', 'wcpp' ),
					'close'        => esc_html__( 'Close', 'wcpp' ),
					'confirmClose' => esc_html__( 'You\'ll lose your personalisation choices. Are you sure?', 'wcpp' ),
					'errorGeneric' => esc_html__( 'Something went wrong. Please try again.', 'wcpp' ),
					'summary'      => esc_html__( 'Review & Add', 'wcpp' ),
					'yourChoices'  => esc_html__( 'Your Choices', 'wcpp' ),
					'free'         => esc_html__( 'Free', 'wcpp' ),
					'adding'       => esc_html__( 'Adding...', 'wcpp' ),
				),
			)
		);
	}

	/**
	 * Render the "Add Personalisation" button on the product page.
	 *
	 * @return void
	 */
	public static function render_button() {
		$product_id = get_the_ID();
		$config     = WCPP_Settings_Store::get( $product_id );

		if ( ! $config ) {
			return;
		}

		$design      = $config['design'];
		$button_text = ! empty( $design['button_text'] ) ? $design['button_text'] : __( 'Add Personalisation', 'wcpp' );
		$btn_style   = $design['button_style'] ?? 'outline';

		$template = wcpp_locate_template( 'button.php' );
		if ( $template ) {
			include $template;
		}
	}

	/**
	 * Render the panel drawer into wp_footer on product pages.
	 *
	 * @return void
	 */
	public static function render_panel() {
		if ( ! is_product() ) {
			return;
		}

		$product_id = get_the_ID();
		$config     = WCPP_Settings_Store::get( $product_id );

		if ( ! $config ) {
			return;
		}

		$template = wcpp_locate_template( 'panel.php' );
		if ( $template ) {
			include $template;
		}
	}

	/**
	 * Attach personalisation data + unique key to cart item.
	 *
	 * @param array $cart_item_data Existing cart item data.
	 * @param int   $product_id     Product ID.
	 * @return array
	 */
	public static function attach_data( $cart_item_data, $product_id ) {
		if ( ! empty( $cart_item_data['wcpp_data'] ) ) {
			$cart_item_data['wcpp_unique_key'] = md5( wp_json_encode( $cart_item_data['wcpp_data'] ) . microtime() );
		}
		return $cart_item_data;
	}

	/**
	 * Display personalisation data in cart and mini-cart.
	 *
	 * @param array $item_data Existing display data.
	 * @param array $cart_item Cart item array.
	 * @return array
	 */
	public static function display_in_cart( $item_data, $cart_item ) {
		if ( empty( $cart_item['wcpp_data'] ) ) {
			return $item_data;
		}

		$data = $cart_item['wcpp_data'];

		if ( ! empty( $data['selections'] ) && is_array( $data['selections'] ) ) {
			foreach ( $data['selections'] as $sel ) {
				$display = esc_html( $sel['choice_name'] );
				if ( isset( $sel['choice_price'] ) && (float) $sel['choice_price'] > 0 ) {
					$display .= ' (+' . wc_price( $sel['choice_price'] ) . ')';
				}
				$item_data[] = array(
					'name'  => esc_html( $sel['option_name'] ),
					'value' => $display,
				);
			}
		}

		if ( ! empty( $data['non_returnable'] ) ) {
			$item_data[] = array(
				'name'  => '',
				'value' => '<span class="wcpp-non-returnable">&#9888; ' . esc_html__( 'Non-returnable', 'wcpp' ) . '</span>',
			);
		}

		return $item_data;
	}
}

/**
 * Theme-first template locator.
 *
 * @param string $template_name Filename e.g. 'button.php'.
 * @return string|false
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
 * Template tag for themes.
 *
 * @return void
 */
function wcpp_render_button() {
	WCPP_Cart_Handler::render_button();
}
