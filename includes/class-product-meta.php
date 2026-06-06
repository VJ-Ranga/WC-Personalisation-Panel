<?php
/**
 * Product Meta — per-product personalisation settings meta box.
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCPP_Product_Meta
 */
class WCPP_Product_Meta {

	/**
	 * Register all hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes',    array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_product', array( __CLASS__, 'save_meta' ), 10, 2 );
	}

	/**
	 * Register the meta box on the product edit screen.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		add_meta_box(
			'wcpp_product_settings',
			__( 'Personalisation Settings', 'wcpp' ),
			array( __CLASS__, 'render_meta_box' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box HTML.
	 *
	 * @param WP_Post $post Current product post object.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		$product_id = $post->ID;
		$product    = WCPP_Settings_Store::get_product( $product_id );
		$global     = WCPP_Settings_Store::get_global();

		wp_nonce_field( 'wcpp_product_meta', 'wcpp_product_nonce' );
		?>
		<div class="wcpp-product-meta">

			<!-- Enable for this product -->
			<p>
				<label>
					<input
						type="checkbox"
						name="wcpp_product[enabled]"
						value="1"
						<?php checked( ! empty( $product['enabled'] ) ); ?>
					/>
					<strong><?php esc_html_e( 'Enable personalisation for this product', 'wcpp' ); ?></strong>
				</label>
			</p>

			<hr />
			<p class="description">
				<?php esc_html_e( 'Leave any field blank to use the global default.', 'wcpp' ); ?>
			</p>

			<!-- Locations override -->
			<p>
				<label for="wcpp_product_locations"><strong><?php esc_html_e( 'Locations', 'wcpp' ); ?></strong></label><br />
				<textarea
					id="wcpp_product_locations"
					name="wcpp_product[locations]"
					rows="3"
					style="width:100%"
					placeholder="<?php echo esc_attr( implode( "\n", $global['locations'] ) ); ?>"
				><?php
				$saved_locations = $product['locations'] ?? array();
				echo esc_textarea( implode( "\n", $saved_locations ) );
				?></textarea>
				<span class="description"><?php esc_html_e( 'One per line. Blank = use global.', 'wcpp' ); ?></span>
			</p>

			<!-- Types override -->
			<p>
				<strong><?php esc_html_e( 'Types', 'wcpp' ); ?></strong><br />
				<?php
				$type_options = array(
					'text'     => __( 'Text', 'wcpp' ),
					'initials' => __( 'Initials', 'wcpp' ),
					'symbol'   => __( 'Symbol', 'wcpp' ),
				);
				$saved_types  = $product['types'] ?? array();
				foreach ( $type_options as $value => $label ) :
					?>
					<label style="display:block;">
						<input
							type="checkbox"
							name="wcpp_product[types][]"
							value="<?php echo esc_attr( $value ); ?>"
							<?php checked( in_array( $value, $saved_types, true ) ); ?>
						/>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<span class="description"><?php esc_html_e( 'Blank = use global.', 'wcpp' ); ?></span>
			</p>

			<!-- Fonts override -->
			<p>
				<label for="wcpp_product_fonts"><strong><?php esc_html_e( 'Fonts', 'wcpp' ); ?></strong></label><br />
				<textarea
					id="wcpp_product_fonts"
					name="wcpp_product[fonts]"
					rows="3"
					style="width:100%"
					placeholder="<?php echo esc_attr( implode( "\n", $global['fonts'] ) ); ?>"
				><?php
				$saved_fonts = $product['fonts'] ?? array();
				echo esc_textarea( implode( "\n", $saved_fonts ) );
				?></textarea>
				<span class="description"><?php esc_html_e( 'One per line. Blank = use global.', 'wcpp' ); ?></span>
			</p>

			<!-- Colours override -->
			<p>
				<label for="wcpp_product_colours"><strong><?php esc_html_e( 'Colours', 'wcpp' ); ?></strong></label><br />
				<textarea
					id="wcpp_product_colours"
					name="wcpp_product[colours]"
					rows="3"
					style="width:100%"
					placeholder="<?php echo esc_attr( implode( "\n", $global['colours'] ) ); ?>"
				><?php
				$saved_colours = $product['colours'] ?? array();
				echo esc_textarea( implode( "\n", $saved_colours ) );
				?></textarea>
				<span class="description"><?php esc_html_e( 'Hex values, one per line. Blank = use global.', 'wcpp' ); ?></span>
			</p>

			<!-- Pricing overrides -->
			<p>
				<label for="wcpp_product_flat_fee"><strong><?php esc_html_e( 'Flat Fee', 'wcpp' ); ?></strong></label><br />
				<input
					type="number"
					id="wcpp_product_flat_fee"
					name="wcpp_product[flat_fee]"
					value="<?php echo esc_attr( $product['flat_fee'] ?? '' ); ?>"
					step="0.01"
					min="0"
					style="width:80px"
					placeholder="<?php echo esc_attr( $global['flat_fee'] ); ?>"
				/>
				<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
				<span class="description"><?php esc_html_e( 'Blank = use global.', 'wcpp' ); ?></span>
			</p>

			<p>
				<label for="wcpp_product_per_char_fee"><strong><?php esc_html_e( 'Per-Character Fee', 'wcpp' ); ?></strong></label><br />
				<input
					type="number"
					id="wcpp_product_per_char_fee"
					name="wcpp_product[per_char_fee]"
					value="<?php echo esc_attr( $product['per_char_fee'] ?? '' ); ?>"
					step="0.01"
					min="0"
					style="width:80px"
					placeholder="<?php echo esc_attr( $global['per_char_fee'] ); ?>"
				/>
				<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
				<span class="description"><?php esc_html_e( 'Blank = use global.', 'wcpp' ); ?></span>
			</p>

			<p>
				<label for="wcpp_product_max_chars"><strong><?php esc_html_e( 'Max Characters', 'wcpp' ); ?></strong></label><br />
				<input
					type="number"
					id="wcpp_product_max_chars"
					name="wcpp_product[max_chars]"
					value="<?php echo esc_attr( $product['max_chars'] ?? '' ); ?>"
					min="1"
					max="50"
					style="width:60px"
					placeholder="<?php echo esc_attr( $global['max_chars'] ); ?>"
				/>
				<span class="description"><?php esc_html_e( 'Blank = use global.', 'wcpp' ); ?></span>
			</p>

			<!-- Non-returnable -->
			<p>
				<label>
					<input
						type="checkbox"
						name="wcpp_product[non_returnable]"
						value="1"
						<?php
						$nr = $product['non_returnable'] ?? null;
						if ( null === $nr ) {
							checked( $global['non_returnable'] );
						} else {
							checked( $nr );
						}
						?>
					/>
					<?php esc_html_e( 'Non-returnable when personalised', 'wcpp' ); ?>
				</label>
			</p>

		</div>
		<?php
	}

	/**
	 * Save per-product settings when the product is saved.
	 *
	 * @param int     $post_id Product post ID.
	 * @param WP_Post $post    Product post object.
	 * @return void
	 */
	public static function save_meta( $post_id, $post ) {

		// Guard: autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Guard: capability.
		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}

		// Guard: nonce.
		if (
			! isset( $_POST['wcpp_product_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['wcpp_product_nonce'] ), 'wcpp_product_meta' )
		) {
			return;
		}

		// Guard: our fields not submitted.
		if ( ! isset( $_POST['wcpp_product'] ) ) {
			return;
		}

		$input = $_POST['wcpp_product']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised below field by field.
		$clean = array();

		$clean['enabled']      = ! empty( $input['enabled'] );
		$clean['non_returnable']= ! empty( $input['non_returnable'] );

		// Arrays — split textarea.
		$clean['locations'] = self::parse_list( $input['locations'] ?? '' );
		$clean['fonts']     = self::parse_list( $input['fonts'] ?? '' );

		// Colours — validate hex.
		$raw_colours    = self::parse_list( $input['colours'] ?? '' );
		$clean['colours'] = array_values(
			array_filter(
				$raw_colours,
				function( $c ) {
					return (bool) sanitize_hex_color( $c );
				}
			)
		);

		// Types — whitelist.
		$allowed_types   = array( 'text', 'initials', 'symbol' );
		$submitted_types = isset( $input['types'] ) && is_array( $input['types'] ) ? $input['types'] : array();
		$clean['types']  = array_values( array_intersect( $submitted_types, $allowed_types ) );

		// Pricing — only save if non-empty.
		if ( '' !== trim( $input['flat_fee'] ?? '' ) ) {
			$clean['flat_fee'] = number_format( (float) $input['flat_fee'], 2, '.', '' );
		}
		if ( '' !== trim( $input['per_char_fee'] ?? '' ) ) {
			$clean['per_char_fee'] = number_format( (float) $input['per_char_fee'], 2, '.', '' );
		}
		if ( '' !== trim( $input['max_chars'] ?? '' ) ) {
			$clean['max_chars'] = max( 1, intval( $input['max_chars'] ) );
		}

		update_post_meta( $post_id, '_wcpp_product_settings', $clean );
	}

	/**
	 * Parse a textarea/string into a clean array.
	 *
	 * @param string $raw Raw input.
	 * @return array
	 */
	private static function parse_list( $raw ) {
		$raw   = sanitize_textarea_field( wp_unslash( $raw ) );
		$items = preg_split( '/[\r\n,]+/', $raw );
		return array_values( array_filter( array_map( 'trim', $items ) ) );
	}
}
