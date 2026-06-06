<?php
/**
 * Price Calculator — applies personalisation prices to the cart.
 * Server-side only. The price stored on the cart item was already
 * recalculated server-side in the AJAX handler from set choice prices.
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCPP_Price_Calculator
 */
class WCPP_Price_Calculator {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_prices' ), 20, 1 );
	}

	/**
	 * Add the personalisation total to each personalised cart item's price.
	 *
	 * @param WC_Cart $cart Cart object.
	 * @return void
	 */
	public static function apply_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['wcpp_data']['total_price'] ) ) {
				continue;
			}

			$extra = (float) $cart_item['wcpp_data']['total_price'];
			if ( $extra <= 0 ) {
				continue;
			}

			if ( empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}

			$product = $cart_item['data'];
			$base    = (float) $product->get_price();
			$product->set_price( $base + $extra );
		}
	}

	/**
	 * Compute the personalisation total for a set of selections.
	 * Used by the AJAX handler and for any server-side recalculation.
	 *
	 * @param int   $product_id Product ID (reserved for future per-product rules).
	 * @param array $data       Personalisation data with 'selections'.
	 * @return float
	 */
	public static function calculate( $product_id, array $data ) {
		$total = 0;
		if ( ! empty( $data['selections'] ) && is_array( $data['selections'] ) ) {
			foreach ( $data['selections'] as $sel ) {
				$total += (float) ( $sel['choice_price'] ?? 0 );
			}
		}
		return round( $total, 2 );
	}
}
