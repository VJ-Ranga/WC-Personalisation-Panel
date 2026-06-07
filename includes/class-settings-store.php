<?php
/**
 * Settings Store — single source of truth for ALL plugin data.
 *
 * Two kinds of data:
 *   1. GLOBAL settings (design + behaviour) — one option `wcpp_settings`,
 *      applies to every panel site-wide. Read via get_design() / get_behaviour().
 *   2. PER-SET data (steps, choices, categories, button override) — stored on
 *      each wcpp_personalisation post. Read via get_set() / get().
 *
 * Set lookup priority for a product:
 *   1. Product has explicit _wcpp_set_id          → use that set
 *   2. A published set has _wcpp_apply_all = true  → use it
 *   3. Product category matches a set's categories → use that set
 *   4. null (no personalisation for this product)
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
	 * Option key for global settings.
	 *
	 * @var string
	 */
	const OPTION = 'wcpp_settings';

	// ─────────────────────────────────────────────────────────────────────
	//  GLOBAL SETTINGS (design + behaviour)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Default design tokens.
	 *
	 * @return array
	 */
	public static function design_defaults() {
		return array(
			// Trigger button (on the product page).
			'btn_text'          => 'Add Personalisation',
			'btn_style'         => 'outline',   // outline | filled | text.
			'btn_bg'            => '#1A56DB',
			'btn_text_color'    => '#1A56DB',
			'btn_radius'        => 6,
			'btn_placement'     => 'after_form', // before_cart | after_cart | after_form | after_summary | shortcode.
			'btn_full_width'    => 1,

			// Drawer / panel.
			'slide_from'        => 'right',     // right | left.
			'panel_width'       => 420,
			'mobile_bp'         => 500,
			'panel_bg'          => '#ffffff',
			'font_family'       => 'inherit',
			'panel_radius'      => 6,
			'overlay_color'     => '#000000',
			'overlay_opacity'   => 45,          // 0-100 %.
			'anim_speed'        => 350,         // ms.

			// Header.
			'header_title'      => 'Add Personalisation',
			'title_color'       => '#111111',

			// Progress indicator.
			'progress_show'     => 1,
			'progress_style'    => 'bar',       // bar | dots | text.
			'progress_color'    => '#1A56DB',

			// Choice cards.
			'card_layout'       => 'list',      // list | grid2 | grid3.
			'card_border'       => '#e0e0e0',
			'card_selected'     => '#1A56DB',
			'card_img_size'     => 52,

			// Footer buttons.
			'next_text'         => 'Next',
			'back_text'         => 'Back',
			'addbag_text'       => 'Add to Bag',
			'footer_btn_color'  => '#1A56DB',

			// Pricing display.
			'show_choice_price' => 1,
			'show_total'        => 1,
			'free_label'        => 'Free',
		);
	}

	/**
	 * Default behaviour settings.
	 *
	 * @return array
	 */
	public static function behaviour_defaults() {
		return array(
			'enabled'             => 1,
			'non_returnable'      => 1,
			'elementor'           => 0,
			'remove_on_uninstall' => 0,
		);
	}

	/**
	 * Canonical font whitelist (label => CSS family). 'inherit' = theme font.
	 *
	 * @return array
	 */
	public static function fonts() {
		return array(
			'inherit'            => 'inherit',
			'Poppins'            => "'Poppins', sans-serif",
			'Playfair Display'   => "'Playfair Display', serif",
			'Montserrat'         => "'Montserrat', sans-serif",
			'Lato'               => "'Lato', sans-serif",
			'Cormorant Garamond' => "'Cormorant Garamond', serif",
		);
	}

	/**
	 * Get merged design settings.
	 *
	 * @return array
	 */
	public static function get_design() {
		$all = get_option( self::OPTION, array() );
		$raw = isset( $all['design'] ) && is_array( $all['design'] ) ? $all['design'] : array();
		return wp_parse_args( $raw, self::design_defaults() );
	}

	/**
	 * Get merged behaviour settings.
	 *
	 * @return array
	 */
	public static function get_behaviour() {
		$all = get_option( self::OPTION, array() );
		$raw = isset( $all['behaviour'] ) && is_array( $all['behaviour'] ) ? $all['behaviour'] : array();
		return wp_parse_args( $raw, self::behaviour_defaults() );
	}

	/**
	 * Is the plugin globally enabled?
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$b = self::get_behaviour();
		return ! empty( $b['enabled'] );
	}

	// ─────────────────────────────────────────────────────────────────────
	//  PER-SET DATA
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Get the full personalisation set for a product (with global design merged in).
	 * Returns null if nothing assigned or plugin disabled.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array|null
	 */
	public static function get( $product_id ) {
		if ( ! self::is_enabled() ) {
			return null;
		}
		$set_id = self::get_set_id( $product_id );
		if ( ! $set_id ) {
			return null;
		}
		return self::get_set( $set_id );
	}

	/**
	 * Get a personalisation set by post ID. Includes options, button override,
	 * and the GLOBAL design + behaviour (so the front-end has everything).
	 *
	 * @param int $set_id Post ID.
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

		$btn_override = get_post_meta( $set_id, '_wcpp_button_text', true );

		return array(
			'id'          => (int) $set_id,
			'name'        => $post->post_title,
			'options'     => $options,
			'button_text' => is_string( $btn_override ) ? $btn_override : '',
			'design'      => self::get_design(),
			'behaviour'   => self::get_behaviour(),
		);
	}

	/**
	 * Resolve which set ID applies to a product.
	 *
	 * @param int $product_id Product ID.
	 * @return int Set post ID, or 0.
	 */
	public static function get_set_id( $product_id ) {

		// 1. Product-level explicit assignment.
		$set_id = (int) get_post_meta( (int) $product_id, '_wcpp_set_id', true );
		if ( $set_id && 'publish' === get_post_status( $set_id ) ) {
			return $set_id;
		}

		// 2 & 3. Scan published sets.
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
			if ( get_post_meta( $sid, '_wcpp_apply_all', true ) ) {
				return (int) $sid;
			}
			$assigned_cats = get_post_meta( $sid, '_wcpp_assigned_categories', true );
			if ( is_array( $assigned_cats ) && ! empty( $assigned_cats ) ) {
				$overlap = array_intersect( array_map( 'intval', $assigned_cats ), $product_cat_ids );
				if ( ! empty( $overlap ) ) {
					return (int) $sid;
				}
			}
		}

		return 0;
	}

	/**
	 * Get product category IDs including ancestors.
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
			foreach ( get_ancestors( $term->term_id, 'product_cat' ) as $ancestor ) {
				$ids[] = (int) $ancestor;
			}
		}
		return array_unique( $ids );
	}

	/**
	 * All published sets (for dropdowns).
	 *
	 * @return array
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
	 * Validate a choice name against a set's option (server-side whitelist).
	 *
	 * @param int    $set_id      Set post ID.
	 * @param string $option_id   Option ID.
	 * @param string $choice_name Choice name.
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
