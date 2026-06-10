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
	 * firing multiple times in one request (cart page, checkout page, payment
	 * processing, WC Blocks Store API) never compounds the price.
	 *
	 * fee_type controls whether the ENTIRE personalisation add-on (flat fee +
	 * all choice prices combined as total_price) is one-time or per-quantity:
	 *   'line' (one-time) — total_price charged once regardless of qty.
	 *     Amortised across units: unit_extra = total_price / qty, so the line
	 *     total is always base×qty + total_price.
	 *   'unit' (per-quantity) — total_price is a per-unit charge and
	 *     multiplies naturally: line total = (base + total_price) × qty.
	 *
	 * Guard: skip when viewing admin pages that are not AJAX or REST.
	 * — is_admin() + no AJAX + no REST = genuine WP admin screen (order editor,
	 *   settings page, etc.) where we must not touch prices.
	 * — AJAX / REST / front-end = price must be applied.
	 *
	 * @param WC_Cart $cart Cart object.
	 * @return void
	 */
	public static function apply_prices( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['wcpp_data'] ) || empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}

			$data        = $cart_item['wcpp_data'];
			$total_price = isset( $data['total_price'] ) ? (float) $data['total_price'] : 0.0;
			if ( $total_price <= 0 ) {
				continue;
			}

			// Prefer the base captured at add time; fall back to the live price.
			$base = isset( $data['base_price'] )
				? (float) $data['base_price']
				: (float) $cart_item['data']->get_price();

			$fee_type = isset( $data['fee_type'] ) ? $data['fee_type'] : 'line';
			$qty      = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;

			if ( 'unit' === $fee_type ) {
				// Per-quantity: entire personalisation cost (flat fee + all choice
				// prices) is a per-unit charge — multiplies naturally with qty.
				$unit_extra = $total_price;
			} else {
				// One-time: entire personalisation cost is charged once regardless
				// of quantity. Amortise across units so WC's (unit × qty) gives the
				// right line total.
				$unit_extra = $total_price / $qty;
			}

			$cart_item['data']->set_price( $base + $unit_extra );
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
