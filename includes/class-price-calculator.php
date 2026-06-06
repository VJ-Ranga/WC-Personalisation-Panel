<?php
/**
 * Price Calculator — server-side only. Never trust a price from the browser.
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCPP_Price_Calculator
 *
 * Public API:
 *   WCPP_Price_Calculator::calculate( $product_id, $data ) → float
 *
 * Built in Phase 4.
 */
class WCPP_Price_Calculator {

	/**
	 * Calculate the personalisation price for a product + chosen data.
	 * Supports flat fee, per-character fee, or both combined.
	 *
	 * @param int   $product_id WooCommerce product ID.
	 * @param array $data       Personalisation data (must include 'text' key if using per-char).
	 * @return float
	 */
	public static function calculate( $product_id, array $data ) {
		// TODO: Phase 4 — implement pricing logic.
		// Formula: flat_fee + ( per_char_fee * strlen( text ) ).
		return 0.00;
	}
}
