<?php
/**
 * AJAX Handler — all AJAX endpoints, logged-in and guest.
 *
 * Endpoints:
 *   wp_ajax_wcpp_add_to_cart        (logged in)
 *   wp_ajax_nopriv_wcpp_add_to_cart (guest — mandatory)
 *   wp_ajax_wcpp_get_price          (logged in)
 *   wp_ajax_nopriv_wcpp_get_price   (guest)
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
	 * Register all AJAX hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// Add to cart — both logged-in and guest.
		add_action( 'wp_ajax_wcpp_add_to_cart',        array( __CLASS__, 'handle_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_wcpp_add_to_cart', array( __CLASS__, 'handle_add_to_cart' ) );

		// Live price preview — both logged-in and guest.
		add_action( 'wp_ajax_wcpp_get_price',        array( __CLASS__, 'handle_get_price' ) );
		add_action( 'wp_ajax_nopriv_wcpp_get_price', array( __CLASS__, 'handle_get_price' ) );
	}

	/**
	 * Handle the add-to-cart AJAX request.
	 * Validates, sanitises, whitelists, then adds to cart.
	 *
	 * @return void  Sends JSON response and exits.
	 */
	public static function handle_add_to_cart() {

		// Step 1: Verify nonce.
		check_ajax_referer( 'wcpp_nonce', 'nonce' );

		// Step 2: Get and validate product ID.
		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;

		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'wcpp' ) ) );
		}

		// Step 3: Load this product's config.
		$config = WCPP_Settings_Store::get( $product_id );

		if ( ! $config['enabled'] ) {
			wp_send_json_error( array( 'message' => __( 'Personalisation is not available for this product.', 'wcpp' ) ) );
		}

		// Step 4: Sanitise and whitelist location.
		$location = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';

		if ( empty( $location ) || ! in_array( $location, $config['locations'], true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid location selected.', 'wcpp' ) ) );
		}

		// Step 5: Build personalisation data.
		// TODO: Phase 3+ — add type, text, font, colour.
		$personalisation = array(
			'location'       => $location,
			'non_returnable' => $config['non_returnable'],
		);

		// Step 6: Handle variable products.
		$variation_id = isset( $_POST['variation_id'] ) ? intval( $_POST['variation_id'] ) : 0;
		$product      = wc_get_product( $product_id );

		if ( $product && $product->is_type( 'variable' ) && ! $variation_id ) {
			wp_send_json_error( array( 'message' => __( 'Please select a product option before adding personalisation.', 'wcpp' ) ) );
		}

		// Step 7: Add to cart — wcpp_data triggers attach_data in cart handler.
		$cart_item_key = WC()->cart->add_to_cart(
			$product_id,
			1,
			$variation_id,
			array(),
			array( 'wcpp_data' => $personalisation )
		);

		if ( ! $cart_item_key ) {
			wp_send_json_error( array( 'message' => __( 'Could not add item to cart. Please try again.', 'wcpp' ) ) );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Added to cart!', 'wcpp' ),
				'cart_url' => wc_get_cart_url(),
			)
		);
	}

	/**
	 * Handle the live price preview AJAX request.
	 * Returns server-calculated price — JS never calculates price itself.
	 *
	 * @return void  Sends JSON response and exits.
	 */
	public static function handle_get_price() {

		// Verify nonce.
		check_ajax_referer( 'wcpp_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$text       = isset( $_POST['text'] ) ? sanitize_text_field( wp_unslash( $_POST['text'] ) ) : '';

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'wcpp' ) ) );
		}

		$price = WCPP_Price_Calculator::calculate( $product_id, array( 'text' => $text ) );

		wp_send_json_success(
			array(
				'price'         => $price,
				'price_display' => wc_price( $price ),
			)
		);
	}
}
