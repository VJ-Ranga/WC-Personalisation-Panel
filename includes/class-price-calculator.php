<?php
/**
 * Price Calculator — computes and applies personalisation prices.
 * Server-side only. The browser's price is never trusted.
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
	 * Apply the personalisation add-on to each personalised cart item.
	 *
	 * Idempotent: the price is always set to (base_price + add-on), where
	 * base_price was captured once at add-to-cart time. This means the hook
	 * firing multiple times in one request never compounds the price.
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
			if ( empty( $cart_item['wcpp_data'] ) || empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}

			$data  = $cart_item['wcpp_data'];
			$extra = isset( $data['total_price'] ) ? (float) $data['total_price'] : 0.0;
			if ( $extra <= 0 ) {
				continue;
			}

			// Prefer the base captured at add time; fall back to the live price.
			$base = isset( $data['base_price'] )
				? (float) $data['base_price']
				: (float) $cart_item['data']->get_price();

			$cart_item['data']->set_price( $base + $extra );
		}
	}

	/**
	 * Sum the choice prices of a set of resolved selections.
	 *
	 * @param array $selections Array of selections, each with 'choice_price'.
	 * @return float
	 */
	public static function calculate( array $selections ) {
		$total = 0.0;
		foreach ( $selections as $sel ) {
			$total += (float) ( $sel['price'] ?? 0 );
		}
		return round( $total, 2 );
	}
}
