<?php
/**
 * AJAX Handler — handles add-to-cart and price preview.
 * Both logged-in and guest (nopriv).
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

		add_action( 'wp_ajax_wcpp_get_price',        array( __CLASS__, 'handle_get_price' ) );
		add_action( 'wp_ajax_nopriv_wcpp_get_price', array( __CLASS__, 'handle_get_price' ) );
	}

	/**
	 * Handle add-to-cart AJAX.
	 * Validates nonce → product → set → selections → adds to WC cart.
	 *
	 * @return void
	 */
	public static function handle_add_to_cart() {

		// Nonce.
		check_ajax_referer( 'wcpp_nonce', 'nonce' );

		// Product.
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'wcpp' ) ) );
		}

		// Set.
		$set_id = isset( $_POST['set_id'] ) ? absint( $_POST['set_id'] ) : 0;
		$set    = $set_id ? WCPP_Settings_Store::get_set( $set_id ) : null;

		// Fall back to looking up the set from the product if set_id not sent.
		if ( ! $set ) {
			$set = WCPP_Settings_Store::get( $product_id );
		}

		if ( ! $set ) {
			wp_send_json_error( array( 'message' => __( 'Personalisation is not available for this product.', 'wcpp' ) ) );
		}

		// Selections.
		$raw_selections = isset( $_POST['selections'] ) ? sanitize_text_field( wp_unslash( $_POST['selections'] ) ) : '';
		$selections     = json_decode( $raw_selections, true );

		if ( ! is_array( $selections ) || empty( $selections ) ) {
			wp_send_json_error( array( 'message' => __( 'Please complete all personalisation steps.', 'wcpp' ) ) );
		}

		// Validate each selection server-side.
		$clean_selections = array();
		foreach ( $selections as $sel ) {
			$option_id   = sanitize_text_field( $sel['option_id'] ?? '' );
			$option_name = sanitize_text_field( $sel['option_name'] ?? '' );
			$choice_id   = sanitize_text_field( $sel['choice_id'] ?? '' );
			$choice_name = sanitize_text_field( $sel['choice_name'] ?? '' );

			if ( empty( $option_id ) || empty( $choice_name ) ) {
				continue;
			}

			// Whitelist choice against the set.
			if ( ! WCPP_Settings_Store::is_valid_choice( $set['id'], $option_id, $choice_name ) ) {
				wp_send_json_error(
					array( 'message' => sprintf( __( 'Invalid choice: %s', 'wcpp' ), $choice_name ) )
				);
			}

			// Re-fetch the choice price from server — never trust posted price.
			$server_price = self::get_choice_price( $set, $option_id, $choice_id );

			$clean_selections[] = array(
				'option_id'        => $option_id,
				'option_name'      => $option_name,
				'choice_id'        => $choice_id,
				'choice_name'      => $choice_name,
				'choice_price'     => $server_price,
				'choice_image_url' => sanitize_text_field( $sel['choice_image_url'] ?? '' ),
			);
		}

		if ( empty( $clean_selections ) ) {
			wp_send_json_error( array( 'message' => __( 'Please complete all personalisation steps.', 'wcpp' ) ) );
		}

		// Variable product — needs variation.
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$product      = wc_get_product( $product_id );

		if ( $product && $product->is_type( 'variable' ) && ! $variation_id ) {
			wp_send_json_error( array( 'message' => __( 'Please select a product option before adding personalisation.', 'wcpp' ) ) );
		}

		// Build personalisation payload.
		$personalisation = array(
			'set_id'         => $set['id'],
			'set_name'       => $set['name'],
			'selections'     => $clean_selections,
			'non_returnable' => true, // Always non-returnable for personalised items.
		);

		// Server-calculated total personalisation price.
		$total_price = 0;
		foreach ( $clean_selections as $sel ) {
			$total_price += (float) $sel['choice_price'];
		}
		$personalisation['total_price'] = number_format( $total_price, 2, '.', '' );

		// Add to WooCommerce cart.
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
	 * Handle live price preview AJAX.
	 *
	 * @return void
	 */
	public static function handle_get_price() {
		check_ajax_referer( 'wcpp_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'wcpp' ) ) );
		}

		$price = WCPP_Price_Calculator::calculate( $product_id, array() );

		wp_send_json_success(
			array(
				'price'         => $price,
				'price_display' => wc_price( $price ),
			)
		);
	}

	/**
	 * Get the server-side price for a specific choice.
	 *
	 * @param array  $set       Full set array.
	 * @param string $option_id Option ID.
	 * @param string $choice_id Choice ID.
	 * @return float
	 */
	private static function get_choice_price( $set, $option_id, $choice_id ) {
		foreach ( $set['options'] as $opt ) {
			if ( $opt['id'] === $option_id ) {
				foreach ( $opt['choices'] as $ch ) {
					if ( $ch['id'] === $choice_id ) {
						return (float) $ch['price'];
					}
				}
			}
		}
		return 0.00;
	}
}
