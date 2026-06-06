<?php
/**
 * Settings Store — the ONLY place settings are read and merged.
 * Everything else calls this. Never read options/meta directly elsewhere.
 *
 * @package WC_Personalisation_Panel
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCPP_Settings_Store
 *
 * Public API:
 *   WCPP_Settings_Store::get( $product_id )  → merged config array for a product
 *   WCPP_Settings_Store::get_global()        → global defaults only
 */
class WCPP_Settings_Store {

	/**
	 * Global settings defaults — used when no saved option exists.
	 *
	 * @var array
	 */
	private static $fallback = array(
		'enabled'          => false,
		'locations'        => array(),
		'types'            => array( 'text', 'initials', 'symbol' ),
		'fonts'            => array( 'serif', 'script', 'block' ),
		'colours'          => array( '#000000', '#FFFFFF', '#C0A882' ),
		'flat_fee'         => '0.00',
		'per_char_fee'     => '0.00',
		'max_chars'        => 3,
		'non_returnable'   => true,
		'elementor_enabled'=> false,
	);

	/**
	 * Get merged settings for a specific product.
	 * Per-product values override global defaults.
	 * Returns a clean, typed array every time.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array
	 */
	public static function get( $product_id ) {
		$global  = self::get_global();
		$product = self::get_product( $product_id );

		// Per-product overrides global — but only non-empty values override.
		$merged = $global;

		if ( isset( $product['enabled'] ) ) {
			$merged['enabled'] = (bool) $product['enabled'];
		}

		// Arrays: override only if the product has a non-empty array set.
		foreach ( array( 'locations', 'types', 'fonts', 'colours' ) as $key ) {
			if ( ! empty( $product[ $key ] ) && is_array( $product[ $key ] ) ) {
				$merged[ $key ] = $product[ $key ];
			}
		}

		// Scalars: override only if the product has a non-empty value set.
		foreach ( array( 'flat_fee', 'per_char_fee', 'max_chars' ) as $key ) {
			if ( isset( $product[ $key ] ) && '' !== $product[ $key ] ) {
				$merged[ $key ] = $product[ $key ];
			}
		}

		if ( isset( $product['non_returnable'] ) ) {
			$merged['non_returnable'] = (bool) $product['non_returnable'];
		}

		return self::cast( $merged );
	}

	/**
	 * Get global settings only (no product override).
	 *
	 * @return array
	 */
	public static function get_global() {
		$saved = get_option( 'wcpp_global_settings', array() );
		return self::cast( wp_parse_args( $saved, self::$fallback ) );
	}

	/**
	 * Get raw per-product settings (no merge).
	 * Returns empty array if nothing is saved for the product.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array
	 */
	public static function get_product( $product_id ) {
		$saved = get_post_meta( (int) $product_id, '_wcpp_product_settings', true );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Cast all values to their correct types.
	 * Ensures the returned array is always predictable — no mixed types.
	 *
	 * @param array $settings Raw settings array.
	 * @return array
	 */
	private static function cast( array $settings ) {
		return array(
			'enabled'          => (bool) ( $settings['enabled'] ?? false ),
			'locations'        => self::clean_array( $settings['locations'] ?? array() ),
			'types'            => self::clean_array( $settings['types'] ?? array() ),
			'fonts'            => self::clean_array( $settings['fonts'] ?? array() ),
			'colours'          => self::clean_array( $settings['colours'] ?? array() ),
			'flat_fee'         => number_format( (float) ( $settings['flat_fee'] ?? 0 ), 2, '.', '' ),
			'per_char_fee'     => number_format( (float) ( $settings['per_char_fee'] ?? 0 ), 2, '.', '' ),
			'max_chars'        => max( 1, (int) ( $settings['max_chars'] ?? 3 ) ),
			'non_returnable'   => (bool) ( $settings['non_returnable'] ?? true ),
			'elementor_enabled'=> (bool) ( $settings['elementor_enabled'] ?? false ),
		);
	}

	/**
	 * Clean an array — trim whitespace, remove empty values, re-index.
	 *
	 * @param array $arr Raw array.
	 * @return array
	 */
	private static function clean_array( $arr ) {
		if ( ! is_array( $arr ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'trim', $arr ) ) );
	}
}
