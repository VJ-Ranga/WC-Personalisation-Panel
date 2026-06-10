<?php
/**
 * AJAX Handler — add personalised item to cart (placement model).
 * Public endpoint (logged-in + guest). Trusts only IDs; every name and
 * price is re-derived from the set server-side.
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

		// Guard: WC cart must be initialised. On most hosts this is guaranteed
		// by WooCommerce's plugins_loaded boot, but defensive check prevents a
		// fatal on unusual configurations (object cache cleared mid-request, etc).
		if ( null === WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart is not available. Please reload the page and try again.', 'wcpp' ) ) );
		}

		// 2. Product.
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'wcpp' ) ) );
		}

		// 3. Resolve the authorised set for this product — always derived from the
		// product server-side, never from the raw posted set_id alone. A posted
		// set_id is accepted only as a confirmation that the client held the right
		// set; if it doesn't match the authorised one the request is rejected.
		$set = WCPP_Settings_Store::get( $product_id );
		if ( ! $set ) {
			wp_send_json_error( array( 'message' => __( 'Personalisation is not available for this product.', 'wcpp' ) ) );
		}

		$posted_set_id = isset( $_POST['set_id'] ) ? absint( $_POST['set_id'] ) : 0;
		if ( $posted_set_id && $posted_set_id !== $set['id'] ) {
			wp_send_json_error( array( 'message' => __( 'Invalid personalisation set for this product.', 'wcpp' ) ) );
		}

		// 4. Decode placements payload. Only IDs are trusted.
		$raw    = isset( $_POST['placements'] ) ? wp_unslash( $_POST['placements'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- json + per-field validated below.
		$posted = is_string( $raw ) ? json_decode( $raw, true ) : null;

		if ( ! is_array( $posted ) || empty( $posted ) ) {
			wp_send_json_error( array( 'message' => __( 'Please complete your personalisation.', 'wcpp' ) ) );
		}

		// 5. Validate + rebuild each placement from the set (server-derived).
		$clean_placements = array();
		$seen_placements  = array();
		$choice_total     = 0.0;

		foreach ( $posted as $pl_in ) {
			$placement_id = isset( $pl_in['placement_id'] ) ? sanitize_text_field( $pl_in['placement_id'] ) : '';
			$placement    = WCPP_Settings_Store::get_placement( $set, $placement_id );

			if ( ! $placement ) {
				wp_send_json_error( array( 'message' => __( 'Invalid placement selected.', 'wcpp' ) ) );
			}

			// Each placement only once per order.
			if ( isset( $seen_placements[ $placement_id ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Each placement can only be added once.', 'wcpp' ) ) );
			}
			$seen_placements[ $placement_id ] = true;

			$posted_steps = isset( $pl_in['steps'] ) && is_array( $pl_in['steps'] ) ? $pl_in['steps'] : array();
			$selections   = array();
			$seen_steps   = array();

			foreach ( $posted_steps as $st_in ) {
				$step_id = isset( $st_in['step_id'] ) ? sanitize_text_field( $st_in['step_id'] ) : '';

				// Each step only once — prevents padding the count by duplicating a step
				// to satisfy the count($selections) >= count($placement['steps']) check below.
				if ( isset( $seen_steps[ $step_id ] ) ) {
					wp_send_json_error( array( 'message' => __( 'Each step can only be submitted once.', 'wcpp' ) ) );
				}
				$seen_steps[ $step_id ] = true;

				// Pass through the raw choice_id / text; resolve_step validates + sanitises.
				$payload = array(
					'choice_id' => isset( $st_in['choice_id'] ) ? sanitize_text_field( $st_in['choice_id'] ) : '',
					'text'      => isset( $st_in['text'] ) ? $st_in['text'] : '',
				);

				$resolved = WCPP_Settings_Store::resolve_step( $set, $placement_id, $step_id, $payload );
				if ( null === $resolved ) {
					wp_send_json_error( array( 'message' => __( 'Please complete all steps for each placement.', 'wcpp' ) ) );
				}

				$selections[]  = $resolved;
				$choice_total += (float) $resolved['price'];
			}

			// Require an answer for every step in the placement.
			if ( count( $selections ) < count( $placement['steps'] ?? array() ) ) {
				wp_send_json_error( array( 'message' => __( 'Please complete all steps for each placement.', 'wcpp' ) ) );
			}

			$clean_placements[] = array(
				'placement_id'   => $placement['id'],
				'placement_name' => $placement['name'],
				'selections'     => $selections,
			);
		}

		if ( empty( $clean_placements ) ) {
			wp_send_json_error( array( 'message' => __( 'Please complete your personalisation.', 'wcpp' ) ) );
		}

		// 6. Variable product needs a chosen variation.
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$product      = wc_get_product( $product_id );
		if ( $product && $product->is_type( 'variable' ) && ! $variation_id ) {
			wp_send_json_error( array( 'message' => __( 'Please select a product option (e.g. size) before personalising.', 'wcpp' ) ) );
		}

		// Validate the posted variation actually belongs to THIS product. The
		// base price is captured from the variation below and then force-applied
		// by the price calculator, so a mismatched (cheaper) variation_id from
		// another product would let a customer underpay. Reject any such mismatch.
		if ( $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->is_type( 'variation' ) || (int) $variation->get_parent_id() !== $product_id ) {
				wp_send_json_error( array( 'message' => __( 'Invalid product option selected.', 'wcpp' ) ) );
			}
		}

		// Variation attributes (attribute_pa_size => …). Sanitised per value.
		$variation_attrs = array();
		if ( $variation_id && isset( $_POST['variation'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['variation'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised below.
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $k => $v ) {
					$variation_attrs[ sanitize_text_field( $k ) ] = sanitize_text_field( $v );
				}
			}
		}

		// 7. Base price (the variation/product sell price) for idempotent add-on.
		$priced_product = $variation_id ? wc_get_product( $variation_id ) : $product;
		$base_price     = $priced_product ? (float) $priced_product->get_price() : 0.0;

		// 8. Total = flat set fee + all choice prices across placements.
		$set_fee     = isset( $set['set_price'] ) ? (float) $set['set_price'] : 0.0;
		$fee_type    = isset( $set['set_price_type'] ) ? $set['set_price_type'] : 'line';
		$total_price = $set_fee + $choice_total;

		// 9. Non-returnable honours the global Behaviour setting.
		$non_returnable = ! empty( $set['behaviour']['non_returnable'] );

		$personalisation = array(
			'set_id'         => $set['id'],
			'set_name'       => $set['name'],
			'placements'     => $clean_placements,
			'set_fee'        => number_format( $set_fee, 2, '.', '' ),
			'fee_type'       => $fee_type,  // 'line' = one-time; 'unit' = per item ordered.
			'base_price'     => $base_price,
			'total_price'    => number_format( $total_price, 2, '.', '' ),
			'non_returnable' => $non_returnable,
		);

		// 10. Read quantity from the product page (customer may have selected > 1).
		// Clamped to 1–99 server-side so a crafted POST cannot send a silly number.
		$quantity = isset( $_POST['quantity'] ) ? max( 1, min( 99, absint( $_POST['quantity'] ) ) ) : 1;

		// Run WooCommerce's add-to-cart validation filter before touching the
		// cart. Third-party plugins (purchase limits, min/max qty, subscriptions,
		// password-protected products, etc.) register here. Skipping this filter
		// would let the plugin bypass rules that WooCommerce's own AJAX handler
		// enforces (see wc-ajax.php and wc-form-handler.php).
		$passed_validation = apply_filters(
			'woocommerce_add_to_cart_validation',
			true,
			$product_id,
			$quantity,
			$variation_id,
			$variation_attrs,
			array( 'wcpp_data' => $personalisation )
		);

		if ( ! $passed_validation ) {
			$wc_notices = wc_get_notices( 'error' );
			$message    = __( 'Could not add to cart. Please try again.', 'wcpp' );

			if ( ! empty( $wc_notices ) ) {
				$first       = reset( $wc_notices );
				$notice_text = is_array( $first ) && isset( $first['notice'] )
					? wp_strip_all_tags( $first['notice'] )
					: ( is_string( $first ) ? wp_strip_all_tags( $first ) : $message );
				if ( $notice_text ) {
					$message = $notice_text;
				}
			}

			wc_clear_notices();
			wp_send_json_error( array( 'message' => $message ) );
		}

		// 11. Add to cart (pass variation attributes for reliable variation add).
		$cart_item_key = WC()->cart->add_to_cart(
			$product_id,
			$quantity,
			$variation_id,
			$variation_attrs,
			array( 'wcpp_data' => $personalisation )
		);

		if ( ! $cart_item_key ) {
			// WooCommerce queues its own notice when add_to_cart fails (e.g. stock
			// exhausted, purchasable check failed). Surface that message if present
			// so the customer sees the real reason instead of a generic error.
			$wc_notices = wc_get_notices( 'error' );
			$message    = __( 'Could not add to cart. Please try again.', 'wcpp' );

			if ( ! empty( $wc_notices ) ) {
				$first = reset( $wc_notices );
				// WC 3.9+ stores notices as arrays with 'notice' key; older as strings.
				$notice_text = is_array( $first ) && isset( $first['notice'] )
					? wp_strip_all_tags( $first['notice'] )
					: ( is_string( $first ) ? wp_strip_all_tags( $first ) : $message );
				if ( $notice_text ) {
					$message = $notice_text;
				}
			}

			wc_clear_notices();
			wp_send_json_error( array( 'message' => $message ) );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Added to cart!', 'wcpp' ),
				// Honour the woocommerce_add_to_cart_redirect filter so sites
				// configured to skip the cart and go straight to checkout behave
				// the same for personalised products as for normal WC add-to-cart.
				'cart_url' => apply_filters( 'woocommerce_add_to_cart_redirect', wc_get_cart_url(), $product ),
			)
		);
	}
}
