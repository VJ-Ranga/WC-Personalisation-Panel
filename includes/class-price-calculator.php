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
	 * set_fee handling depends on the fee_type stored in wcpp_data:
	 *   'line' (one-time fee) — the fee is amortised across the cart item
	 *     quantity so WC's (unit_price × qty) arithmetic yields:
	 *     base×qty + choice_addon×qty + set_fee (collected once per line).
	 *   'unit' (per-item fee) — the fee is part of the unit price and
	 *     naturally multiplies: base×qty + (choice_addon + set_fee)×qty.
	 * Choice prices are always per-unit and multiply with qty in both modes.
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

			$set_fee      = isset( $data['set_fee'] )   ? (float) $data['set_fee']   : 0.0;
			$fee_type     = isset( $data['fee_type'] )  ? $data['fee_type']          : 'line';
			$choice_addon = max( 0.0, $total_price - $set_fee );
			$qty          = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;

			if ( 'unit' === $fee_type ) {
				// Per-item: set_fee multiplies with quantity (total_price is already correct).
				$unit_extra = $total_price;
			} else {
				// One-time: amortise set_fee across units; choice_addon still multiplies.
				$unit_extra = $choice_addon + ( $set_fee / $qty );
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
