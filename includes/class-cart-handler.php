<?php
/**
 * Cart Handler — front-end button, panel render, cart hooks.
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
	 * Register hooks. Button placement is dynamic, based on global design.
	 *
	 * @return void
	 */
	public static function init() {
		$design    = WCPP_Settings_Store::get_design();
		$placement = $design['btn_placement'];

		switch ( $placement ) {
			case 'before_cart':
				// Above the Add to Cart button.
				add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_button' ) );
				break;
			case 'after_cart':
				// Right after the Add to Cart button (before Buy Now).
				add_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'render_button' ) );
				break;
			case 'after_form':
				// Under the whole purchase area (below Add to Cart AND Buy Now).
				add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'render_button' ) );
				break;
			case 'after_summary':
				// Below the product summary (price, meta, etc.).
				add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_button' ), 35 );
				break;
			// 'shortcode' = no auto hook; use [wcpp_button] or wcpp_render_button().
		}

		add_shortcode( 'wcpp_button', array( __CLASS__, 'shortcode_button' ) );

		add_action( 'wp_footer',                      array( __CLASS__, 'render_panel' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'attach_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data',      array( __CLASS__, 'display_in_cart' ), 10, 2 );
		add_action( 'wp_enqueue_scripts',             array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Build the inline CSS custom-property block from the design tokens.
	 *
	 * @param array $design Design settings.
	 * @return string
	 */
	private static function build_css_vars( $design ) {
		// Overlay rgba from hex + opacity.
		$hex     = ltrim( $design['overlay_color'], '#' );
		$r       = hexdec( substr( $hex, 0, 2 ) );
		$g       = hexdec( substr( $hex, 2, 2 ) );
		$b       = hexdec( substr( $hex, 4, 2 ) );
		$opacity = max( 0, min( 100, (int) $design['overlay_opacity'] ) ) / 100;
		$overlay = sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, $opacity );

		// Resolve the CSS font-family from the whitelist (safe value, never raw input).
		$fonts = WCPP_Settings_Store::fonts();
		$font  = isset( $fonts[ $design['font_family'] ] ) ? $fonts[ $design['font_family'] ] : 'inherit';

		$vars = array(
			'--wcpp-btn-bg'        => $design['btn_bg'],
			'--wcpp-btn-text'      => $design['btn_text_color'],
			'--wcpp-btn-radius'    => intval( $design['btn_radius'] ) . 'px',
			'--wcpp-panel-width'   => intval( $design['panel_width'] ) . 'px',
			'--wcpp-mobile-bp'     => intval( $design['mobile_bp'] ) . 'px',
			'--wcpp-panel-bg'      => $design['panel_bg'],
			'--wcpp-font'          => $font,
			'--wcpp-panel-radius'  => intval( $design['panel_radius'] ) . 'px',
			'--wcpp-overlay'       => $overlay,
			'--wcpp-anim'          => intval( $design['anim_speed'] ) . 'ms',
			'--wcpp-title-color'   => $design['title_color'],
			'--wcpp-progress'      => $design['progress_color'],
			'--wcpp-card-border'   => $design['card_border'],
			'--wcpp-card-selected' => $design['card_selected'],
			'--wcpp-img-size'      => intval( $design['card_img_size'] ) . 'px',
			'--wcpp-footer-btn'    => $design['footer_btn_color'],
		);

		$css = ':root{';
		foreach ( $vars as $k => $v ) {
			$css .= $k . ':' . $v . ';';
		}
		$css .= '}';

		return $css;
	}

	/**
	 * Enqueue front-end assets on product pages with a set assigned.
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

		wp_enqueue_style( 'wcpp-panel', WCPP_URL . 'assets/css/panel-default.css', array(), WCPP_VERSION );

		// Google font import — only for whitelisted fonts, URL fully escaped.
		$font_keys = array_keys( WCPP_Settings_Store::fonts() );
		if ( 'inherit' !== $design['font_family'] && in_array( $design['font_family'], $font_keys, true ) ) {
			$family    = rawurlencode( $design['font_family'] );
			$font_url  = 'https://fonts.googleapis.com/css2?family=' . $family . ':wght@400;600;700&display=swap';
			wp_enqueue_style(
				'wcpp-font',
				esc_url( $font_url ),
				array(),
				null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			);
		}

		// Inline design tokens.
		wp_add_inline_style( 'wcpp-panel', self::build_css_vars( $design ) );

		wp_enqueue_script( 'wcpp-panel', WCPP_URL . 'assets/js/wcpp-panel.js', array( 'jquery' ), WCPP_VERSION, true );

		// Trimmed front-end config — never expose the behaviour settings to the page.
		$front_config = array(
			'id'          => $config['id'],
			'name'        => $config['name'],
			'placements'  => $config['placements'],
			'button_text' => $config['button_text'],
			'set_price'   => isset( $config['set_price'] ) ? (float) $config['set_price'] : 0.0,
			'design'      => $config['design'],
		);

		wp_localize_script(
			'wcpp-panel',
			'wcpp',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'wcpp_nonce' ),
				'config'    => $front_config,
				'currency'  => array(
					'symbol'    => html_entity_decode( get_woocommerce_currency_symbol() ),
					'position'  => get_option( 'woocommerce_currency_pos', 'left' ),
					'decimals'  => wc_get_price_decimals(),
					'decimal'   => wc_get_price_decimal_separator(),
					'thousand'  => wc_get_price_thousand_separator(),
				),
				'i18n'    => array(
					'back'         => $design['back_text'],
					'next'         => $design['next_text'],
					'addToBag'     => $design['addbag_text'],
					'close'        => esc_html__( 'Close', 'wcpp' ),
					'confirmClose' => esc_html__( 'You\'ll lose your personalisation choices. Are you sure?', 'wcpp' ),
					'errorGeneric' => esc_html__( 'Something went wrong. Please try again.', 'wcpp' ),
					'summary'      => esc_html__( 'Review & Add', 'wcpp' ),
					'yourChoices'  => esc_html__( 'Your Choices', 'wcpp' ),
					'free'         => $design['free_label'],
					'adding'       => esc_html__( 'Adding...', 'wcpp' ),
					'stepOf'       => esc_html__( 'Step %1$d of %2$d', 'wcpp' ),
					'total'        => esc_html__( 'Personalisation total:', 'wcpp' ),
					'feeLabel'     => esc_html__( 'Personalisation fee', 'wcpp' ),
					'choosePlacement'        => esc_html__( 'Choose placement', 'wcpp' ),
					'choosePlacementHeading' => esc_html__( 'Where would you like it?', 'wcpp' ),
					'review'       => esc_html__( 'Review', 'wcpp' ),
					'addAnother'   => esc_html__( 'Add another placement', 'wcpp' ),
				),
			)
		);
	}

	/**
	 * Render the trigger button.
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
		$button_text = ! empty( $config['button_text'] ) ? $config['button_text'] : $design['btn_text'];
		$btn_style   = $design['btn_style'];
		$full_width  = ! empty( $design['btn_full_width'] );

		$template = wcpp_locate_template( 'button.php' );
		if ( $template ) {
			include $template;
		}
	}

	/**
	 * Shortcode handler for [wcpp_button].
	 *
	 * @return string
	 */
	public static function shortcode_button() {
		ob_start();
		self::render_button();
		return ob_get_clean();
	}

	/**
	 * Render the panel drawer in the footer.
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

		$design = $config['design'];

		$template = wcpp_locate_template( 'panel.php' );
		if ( $template ) {
			include $template;
		}
	}

	/**
	 * Attach personalisation data + unique key to the cart item.
	 *
	 * @param array $cart_item_data Cart item data.
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
	 * Display personalisation in cart and mini-cart.
	 *
	 * @param array $item_data Display data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function display_in_cart( $item_data, $cart_item ) {
		if ( empty( $cart_item['wcpp_data'] ) ) {
			return $item_data;
		}

		$data = $cart_item['wcpp_data'];

		if ( ! empty( $data['placements'] ) && is_array( $data['placements'] ) ) {
			foreach ( $data['placements'] as $placement ) {
				$lines = array();
				foreach ( ( $placement['selections'] ?? array() ) as $sel ) {
					$val  = isset( $sel['value'] ) ? $sel['value'] : ( $sel['choice_name'] ?? '' );
					$line = esc_html( $sel['step_name'] ) . ': ' . esc_html( $val );
					$price = isset( $sel['price'] ) ? (float) $sel['price'] : (float) ( $sel['choice_price'] ?? 0 );
					if ( $price > 0 ) {
						$line .= ' (+' . wp_strip_all_tags( wc_price( $price ) ) . ')';
					}
					$lines[] = $line;
				}
				$item_data[] = array(
					'name'  => esc_html( $placement['placement_name'] ),
					'value' => implode( '<br>', $lines ),
				);
			}
		}

		// Flat set fee line.
		if ( isset( $data['set_fee'] ) && (float) $data['set_fee'] > 0 ) {
			$item_data[] = array(
				'name'  => esc_html__( 'Personalisation fee', 'wcpp' ),
				'value' => wp_strip_all_tags( wc_price( $data['set_fee'] ) ),
			);
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
 * @param string $template_name Filename.
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
 * Template tag.
 *
 * @return void
 */
function wcpp_render_button() {
	WCPP_Cart_Handler::render_button();
}
