<?php
/**
 * AJAX Handler — add personalised item to cart.
 * Public endpoint (logged-in + guest). Trusts nothing from the browser
 * except IDs; every name and price is re-derived from the set server-side.
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCPP_Ajax_Handler
 */
class WCPP_Ajax_Handler {

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_wcpp_add_to_cart',        array( __CLASS__, 'handle_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_wcpp_add_to_cart', array( __CLASS__, 'handle_add_to_cart' ) );
	}

	/**
	 * Handle the add-to-cart AJAX request.
	 *
	 * @return void
	 */
	public static function handle_add_to_cart() {

		// 1. Nonce.
		check_ajax_referer( 'wcpp_nonce', 'nonce' );

		// 2. Product.
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'wcpp' ) ) );
		}

		// 3. Resolve the set — prefer posted set_id, fall back to product rules.
		$set_id = isset( $_POST['set_id'] ) ? absint( $_POST['set_id'] ) : 0;
		$set    = $set_id ? WCPP_Settings_Store::get_set( $set_id ) : null;
		if ( ! $set ) {
			$set = WCPP_Settings_Store::get( $product_id );
		}
		if ( ! $set ) {
			wp_send_json_error( array( 'message' => __( 'Personalisation is not available for this product.', 'wcpp' ) ) );
		}

		// 4. Decode selections. Only IDs are trusted; everything else is derived.
		$raw        = isset( $_POST['selections'] ) ? wp_unslash( $_POST['selections'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- json_decoded + per-field validated below.
		$posted     = is_string( $raw ) ? json_decode( $raw, true ) : null;

		if ( ! is_array( $posted ) || empty( $posted ) ) {
			wp_send_json_error( array( 'message' => __( 'Please complete all personalisation steps.', 'wcpp' ) ) );
		}

		// 5. Build a clean, server-derived selection for every posted ID pair.
		$clean_selections = array();
		foreach ( $posted as $sel ) {
			$option_id = isset( $sel['option_id'] ) ? sanitize_text_field( $sel['option_id'] ) : '';
			$choice_id = isset( $sel['choice_id'] ) ? sanitize_text_field( $sel['choice_id'] ) : '';

			$resolved = self::resolve_choice( $set, $option_id, $choice_id );
			if ( null === $resolved ) {
				wp_send_json_error( array( 'message' => __( 'One of your choices is no longer available. Please try again.', 'wcpp' ) ) );
			}
			$clean_selections[] = $resolved;
		}

		// 6. Require a choice for every option in the set.
		if ( count( $clean_selections ) < count( $set['options'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please complete all personalisation steps.', 'wcpp' ) ) );
		}

		// 7. Variable product — needs a chosen variation.
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$product      = wc_get_product( $product_id );
		if ( $product && $product->is_type( 'variable' ) && ! $variation_id ) {
			wp_send_json_error( array( 'message' => __( 'Please select a product option before personalising.', 'wcpp' ) ) );
		}

		// 8. Capture the base price now (the price the variation/product sells at)
		//    so the price calculator can apply our add-on idempotently.
		$priced_product = $variation_id ? wc_get_product( $variation_id ) : $product;
		$base_price     = $priced_product ? (float) $priced_product->get_price() : 0.0;

		// 9. Server-side total from the set choice prices.
		$total_price = WCPP_Price_Calculator::calculate( $clean_selections );

		// 10. Non-returnable honours the global Behaviour setting.
		$non_returnable = ! empty( $set['behaviour']['non_returnable'] );

		$personalisation = array(
			'set_id'         => $set['id'],
			'set_name'       => $set['name'],
			'selections'     => $clean_selections,
			'base_price'     => $base_price,
			'total_price'    => number_format( $total_price, 2, '.', '' ),
			'non_returnable' => $non_returnable,
		);

		// 11. Add to cart (unique key keeps different monograms on separate lines).
		$cart_item_key = WC()->cart->add_to_cart(
			$product_id,
			1,
			$variation_id,
			array(),
			array( 'wcpp_data' => $personalisation )
		);

		if ( ! $cart_item_key ) {
			wp_send_json_error( array( 'message' => __( 'Could not add to cart. Please try again.', 'wcpp' ) ) );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Added to cart!', 'wcpp' ),
				'cart_url' => wc_get_cart_url(),
			)
		);
	}

	/**
	 * Resolve a posted option/choice ID pair against the set.
	 * Returns a fully server-derived selection, or null if the IDs are invalid.
	 *
	 * @param array  $set       Set array.
	 * @param string $option_id Posted option ID.
	 * @param string $choice_id Posted choice ID.
	 * @return array|null
	 */
	private static function resolve_choice( $set, $option_id, $choice_id ) {
		foreach ( $set['options'] as $opt ) {
			if ( $opt['id'] !== $option_id ) {
				continue;
			}
			foreach ( $opt['choices'] as $ch ) {
				if ( $ch['id'] === $choice_id ) {
					return array(
						'option_id'        => $opt['id'],
						'option_name'      => $opt['name'],
						'choice_id'        => $ch['id'],
						'choice_name'      => $ch['name'],
						'choice_price'     => (float) $ch['price'],
						'choice_image_url' => $ch['image_url'],
					);
				}
			}
		}
		return null;
	}
}
