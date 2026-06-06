<?php
/**
 * Settings Store — single source of truth for all personalisation data.
 *
 * Lookup priority:
 *   1. Product has explicit _wcpp_set_id → use that set
 *   2. Any published set has _wcpp_apply_all = true → use it
 *   3. Product's categories match a set's _wcpp_assigned_categories → use that set
 *   4. Return null (no personalisation for this product)
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCPP_Settings_Store
 */
class WCPP_Settings_Store {

	/**
	 * Get the full personalisation set for a product.
	 * Returns null if nothing is assigned.
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
	 * Get a personalisation set by post ID.
	 * Includes options, design, and assignment data.
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
		$design  = get_post_meta( $set_id, '_wcpp_design', true );

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		// Merge design with defaults.
		$design_defaults = array(
			'primary_color' => '#1A56DB',
			'panel_width'   => 420,
			'button_text'   => 'Add Personalisation',
			'button_style'  => 'outline',
			'font_family'   => 'inherit',
			'border_radius' => 6,
		);
		$design = is_array( $design ) ? wp_parse_args( $design, $design_defaults ) : $design_defaults;

		return array(
			'id'      => (int) $set_id,
			'name'    => $post->post_title,
			'options' => $options,
			'design'  => $design,
		);
	}

	/**
	 * Resolve which set ID applies to a product.
	 * Priority: product override → apply_all set → category match.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return int Set post ID, or 0 if none.
	 */
	public static function get_set_id( $product_id ) {

		// 1. Product-level explicit assignment.
		$set_id = (int) get_post_meta( (int) $product_id, '_wcpp_set_id', true );
		if ( $set_id && get_post_status( $set_id ) === 'publish' ) {
			return $set_id;
		}

		// 2 & 3. Check all published sets for apply_all or category match.
		$product_cat_ids = self::get_product_category_ids( $product_id );

		$sets = get_posts(
			array(
				'post_type'      => 'wcpp_personalisation',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $sets as $sid ) {
			// apply_all overrides everything.
			if ( get_post_meta( $sid, '_wcpp_apply_all', true ) ) {
				return (int) $sid;
			}

			// Category match.
			$assigned_cats = get_post_meta( $sid, '_wcpp_assigned_categories', true );
			if ( is_array( $assigned_cats ) && ! empty( $assigned_cats ) ) {
				$overlap = array_intersect(
					array_map( 'intval', $assigned_cats ),
					$product_cat_ids
				);
				if ( ! empty( $overlap ) ) {
					return (int) $sid;
				}
			}
		}

		return 0;
	}

	/**
	 * Get all product category IDs for a product (including ancestors).
	 *
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	private static function get_product_category_ids( $product_id ) {
		$terms = get_the_terms( (int) $product_id, 'product_cat' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}

		$ids = array();
		foreach ( $terms as $term ) {
			$ids[] = (int) $term->term_id;
			// Include parent categories too.
			$ancestors = get_ancestors( $term->term_id, 'product_cat' );
			foreach ( $ancestors as $ancestor ) {
				$ids[] = (int) $ancestor;
			}
		}
		return array_unique( $ids );
	}

	/**
	 * Get all published personalisation sets (for dropdowns).
	 *
	 * @return array  [ ['id' => int, 'name' => string], ... ]
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
	 * Validate that a choice name is valid for a given option within a set.
	 * Used for server-side whitelisting.
	 *
	 * @param int    $set_id      Set post ID.
	 * @param string $option_id   Option ID.
	 * @param string $choice_name Choice name to validate.
	 * @return bool
	 */
	public static function is_valid_choice( $set_id, $option_id, $choice_name ) {
		$set = self::get_set( $set_id );
		if ( ! $set ) {
			return false;
		}
		foreach ( $set['options'] as $opt ) {
			if ( $opt['id'] === $option_id ) {
				foreach ( $opt['choices'] as $ch ) {
					if ( $ch['name'] === $choice_name ) {
						return true;
					}
				}
			}
		}
		return false;
	}
}
