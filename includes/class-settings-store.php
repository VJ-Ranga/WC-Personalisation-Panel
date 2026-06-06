<?php
/**
 * Settings Store — the ONLY place personalisation data is read.
 * Everything else calls this. Never read post meta directly elsewhere.
 *
 * Data lives in the wcpp_personalisation CPT.
 * Products and categories are assigned a set ID.
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCPP_Settings_Store
 *
 * Public API:
 *   WCPP_Settings_Store::get( $product_id )        → full set array for a product, or null
 *   WCPP_Settings_Store::get_set( $set_id )        → full set array by set ID, or null
 *   WCPP_Settings_Store::get_all_sets()            → array of all sets [ ['id'=>, 'name'=>], ... ]
 *   WCPP_Settings_Store::get_set_id( $product_id ) → assigned set ID for a product (0 if none)
 */
class WCPP_Settings_Store {

	/**
	 * Get the personalisation set assigned to a product.
	 * Checks product-level first, then falls back to category-level.
	 * Returns null if nothing is assigned or if the set has no options.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array|null
	 */
	public static function get( $product_id ) {
		$set_id = self::get_set_id( $product_id );
		if ( ! $set_id ) {
			return null;
		}
		return self::get_set( $set_id );
	}

	/**
	 * Get a personalisation set by its post ID.
	 *
	 * @param int $set_id Post ID of the wcpp_personalisation post.
	 * @return array|null
	 */
	public static function get_set( $set_id ) {
		$post = get_post( (int) $set_id );

		if ( ! $post || 'wcpp_personalisation' !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		$options = get_post_meta( $set_id, '_wcpp_options', true );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return array(
			'id'      => (int) $set_id,
			'name'    => $post->post_title,
			'options' => $options,
		);
	}

	/**
	 * Get the set ID assigned to a product.
	 * Checks product meta first, then product category term meta.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return int Set post ID, or 0 if none assigned.
	 */
	public static function get_set_id( $product_id ) {
		// Product-level assignment takes priority.
		$set_id = (int) get_post_meta( (int) $product_id, '_wcpp_set_id', true );
		if ( $set_id ) {
			return $set_id;
		}

		// Fall back to category-level assignment.
		$terms = get_the_terms( (int) $product_id, 'product_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_set_id = (int) get_term_meta( $term->term_id, '_wcpp_set_id', true );
				if ( $term_set_id ) {
					return $term_set_id;
				}
			}
		}

		return 0;
	}

	/**
	 * Get all published personalisation sets.
	 * Used to populate dropdowns.
	 *
	 * @return array Array of [ 'id' => int, 'name' => string ]
	 */
	public static function get_all_sets() {
		$posts = get_posts(
			array(
				'post_type'      => 'wcpp_personalisation',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$sets = array();
		foreach ( $posts as $post ) {
			$sets[] = array(
				'id'   => $post->ID,
				'name' => $post->post_title,
			);
		}
		return $sets;
	}

	/**
	 * Get all option names from a set (useful for validation).
	 *
	 * @param int $set_id Set post ID.
	 * @return array Flat array of option names.
	 */
	public static function get_option_names( $set_id ) {
		$set = self::get_set( $set_id );
		if ( ! $set ) {
			return array();
		}
		return array_column( $set['options'], 'name' );
	}

	/**
	 * Get all valid choice names for a specific option within a set.
	 * Used for server-side whitelisting of submitted values.
	 *
	 * @param int    $set_id      Set post ID.
	 * @param string $option_name The option name (e.g. 'Location').
	 * @return array Flat array of valid choice names.
	 */
	public static function get_valid_choices( $set_id, $option_name ) {
		$set = self::get_set( $set_id );
		if ( ! $set ) {
			return array();
		}
		foreach ( $set['options'] as $opt ) {
			if ( $opt['name'] === $option_name ) {
				return array_column( $opt['choices'], 'name' );
			}
		}
		return array();
	}
}
