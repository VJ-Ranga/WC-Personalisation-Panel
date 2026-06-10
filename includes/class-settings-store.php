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
			'btn_animation'     => 'lift',  // none | lift | pulse | shine | scale | bounce

			// Trigger button — padding (all 4 sides).
			'btn_pad_top'          => 15,
			'btn_pad_right'        => 26,
			'btn_pad_bottom'       => 15,
			'btn_pad_left'         => 26,
			// Trigger button — margin (all 4 sides).
			'btn_margin_top'       => 12,
			'btn_margin_right'     => 0,
			'btn_margin_bottom'    => 0,
			'btn_margin_left'      => 0,
			'btn_font_size'        => 13,   // font-size px.
			'btn_border_width'     => 1.5,  // border-width px (outline/filled styles).
			'btn_letter_spacing'   => 0.08, // letter-spacing em (e.g. 0.08 = 0.08em).

			// Placement picker & step flow.
			'placement_layout'     => 'list',       // list | grid2
			'step_flow'            => 'stacked',    // stacked | sequential

			// Panel content area.
			'panel_content_pad'    => 22,   // horizontal padding inside the content px.

			// Footer buttons (Next / Add to Bag).
			'footer_btn_radius'    => 6,    // border-radius px.
			'footer_btn_font_size' => 12,   // font-size px.
			'footer_btn_padding_v' => 16,   // vertical padding (top/bottom) px.

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
			'card_layout'       => 'grid3',     // list | grid2 | grid3.
			'card_border'       => '#e0e0e0',
			'card_selected'     => '#1A56DB',
			'card_img_size'     => 96,
			'card_img_fit'      => 'cover',     // cover | contain
			'card_img_aspect'   => 'square',    // square | landscape | portrait | auto

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
			'non_returnable'      => 0,
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

		$placements = self::read_placements( $set_id );

		$btn_override = get_post_meta( $set_id, '_wcpp_button_text', true );
		$set_price    = get_post_meta( $set_id, '_wcpp_set_price', true );

		return array(
			'id'          => (int) $set_id,
			'name'        => $post->post_title,
			'placements'  => $placements,
			'button_text' => is_string( $btn_override ) ? $btn_override : '',
			'set_price'   => ( '' === $set_price || null === $set_price ) ? 0.0 : (float) $set_price,
			'design'      => self::get_design(),
			'behaviour'   => self::get_behaviour(),
		);
	}

	/**
	 * Read placements for a set, falling back to legacy flat options
	 * (wrapped as a single placement) for backward compatibility.
	 *
	 * @param int $set_id Set post ID.
	 * @return array
	 */
	private static function read_placements( $set_id ) {
		$placements = get_post_meta( $set_id, '_wcpp_placements', true );
		if ( is_array( $placements ) && ! empty( $placements ) ) {
			return $placements;
		}

		$options = get_post_meta( $set_id, '_wcpp_options', true );
		if ( is_array( $options ) && ! empty( $options ) ) {
			return array(
				array(
					'id'    => 'pl_legacy',
					'name'  => __( 'Personalisation', 'wcpp' ),
					'steps' => $options,
				),
			);
		}

		return array();
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
	 * Resolve a step submission (choice OR text) server-side. Trusts only IDs;
	 * names/prices come from the set, and text is sanitised + length-capped here.
	 * Returns a normalised selection, or null if invalid.
	 *
	 * Normalised selection shape:
	 *   step_id, step_name, type ('choice'|'text'),
	 *   value (display string), price (float), image_url (choice only),
	 *   choice_id (choice only)
	 *
	 * @param array  $set          Full set array (from get_set).
	 * @param string $placement_id Placement ID.
	 * @param string $step_id      Step ID.
	 * @param array  $payload      Submitted data: ['choice_id'=>..] or ['text'=>..].
	 * @return array|null
	 */
	public static function resolve_step( $set, $placement_id, $step_id, $payload ) {
		if ( empty( $set['placements'] ) ) {
			return null;
		}
		foreach ( $set['placements'] as $pl ) {
			if ( $pl['id'] !== $placement_id ) {
				continue;
			}
			foreach ( ( $pl['steps'] ?? array() ) as $step ) {
				if ( $step['id'] !== $step_id ) {
					continue;
				}

				$type = isset( $step['type'] ) ? $step['type'] : 'choice';

				// ── Text step ───────────────────────────────────────────────
				if ( 'text' === $type ) {
					$text = isset( $payload['text'] ) ? sanitize_text_field( $payload['text'] ) : '';
					$max  = isset( $step['max_chars'] ) ? (int) $step['max_chars'] : 0;
					if ( $max > 0 && function_exists( 'mb_substr' ) ) {
						$text = mb_substr( $text, 0, $max );
					} elseif ( $max > 0 ) {
						$text = substr( $text, 0, $max );
					}
					if ( '' === $text ) {
						return null; // Text steps are required.
					}

					return array(
						'step_id'   => $step['id'],
						'step_name' => $step['name'],
						'type'      => 'text',
						'value'     => $text,
						'price'     => isset( $step['price'] ) ? (float) $step['price'] : 0.0,
						'image_url' => '',
						'choice_id' => '',
					);
				}

				// ── Choice step ─────────────────────────────────────────────
				$choice_id = isset( $payload['choice_id'] ) ? $payload['choice_id'] : '';
				foreach ( ( $step['choices'] ?? array() ) as $ch ) {
					if ( $ch['id'] === $choice_id ) {
						return array(
							'step_id'   => $step['id'],
							'step_name' => $step['name'],
							'type'      => ( 'color' === $type ) ? 'color' : 'choice',
							'value'     => $ch['name'],
							'price'     => (float) $ch['price'],
							'image_url' => isset( $ch['image_url'] ) ? $ch['image_url'] : '',
							'color'     => isset( $ch['color'] ) ? $ch['color'] : '',
							'choice_id' => $ch['id'],
						);
					}
				}
				return null;
			}
		}
		return null;
	}

	/**
	 * Find a placement in a set by ID.
	 *
	 * @param array  $set          Full set array.
	 * @param string $placement_id Placement ID.
	 * @return array|null
	 */
	public static function get_placement( $set, $placement_id ) {
		if ( empty( $set['placements'] ) ) {
			return null;
		}
		foreach ( $set['placements'] as $pl ) {
			if ( $pl['id'] === $placement_id ) {
				return $pl;
			}
		}
		return null;
	}
}
