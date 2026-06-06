<?php
/**
 * Admin Settings — global settings page under WooCommerce menu.
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCPP_Admin_Settings
 */
class WCPP_Admin_Settings {

	/**
	 * Register all hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu',            array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_init',            array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Add settings page under WooCommerce menu.
	 *
	 * @return void
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Personalisation', 'wcpp' ),
			__( 'Personalisation', 'wcpp' ),
			'manage_woocommerce',
			'wcpp-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register settings with the WordPress Settings API.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'wcpp_settings_group',
			'wcpp_global_settings',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Enqueue admin CSS and JS on our settings page only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_wcpp-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wcpp-admin',
			WCPP_URL . 'assets/css/admin.css',
			array(),
			WCPP_VERSION
		);

		wp_enqueue_script(
			'wcpp-admin',
			WCPP_URL . 'assets/js/wcpp-admin.js',
			array( 'jquery' ),
			WCPP_VERSION,
			true
		);
	}

	/**
	 * Sanitise settings before saving.
	 * All values sanitised and whitelisted here.
	 *
	 * @param array $input Raw POST data.
	 * @return array Clean, safe data to save.
	 */
	public static function sanitize_settings( $input ) {
		$clean = array();

		$clean['enabled']          = ! empty( $input['enabled'] );
		$clean['elementor_enabled']= ! empty( $input['elementor_enabled'] );
		$clean['non_returnable']   = ! empty( $input['non_returnable'] );
		$clean['flat_fee']         = number_format( (float) ( $input['flat_fee'] ?? 0 ), 2, '.', '' );
		$clean['per_char_fee']     = number_format( (float) ( $input['per_char_fee'] ?? 0 ), 2, '.', '' );
		$clean['max_chars']        = max( 1, intval( $input['max_chars'] ?? 3 ) );

		// Arrays from textarea — split by newline or comma, trim, remove empty.
		$clean['locations'] = self::parse_list( $input['locations'] ?? '' );
		$clean['fonts']     = self::parse_list( $input['fonts'] ?? '' );

		// Colours — validate each as hex.
		$raw_colours = self::parse_list( $input['colours'] ?? '' );
		$clean['colours'] = array_values(
			array_filter(
				$raw_colours,
				function( $c ) {
					return (bool) sanitize_hex_color( $c );
				}
			)
		);

		// Types — whitelist only known values.
		$allowed_types    = array( 'text', 'initials', 'symbol' );
		$submitted_types  = isset( $input['types'] ) && is_array( $input['types'] ) ? $input['types'] : array();
		$clean['types']   = array_values( array_intersect( $submitted_types, $allowed_types ) );

		return $clean;
	}

	/**
	 * Parse a textarea input into a clean array.
	 * Accepts newline-separated or comma-separated values.
	 *
	 * @param string $raw Raw textarea input.
	 * @return array
	 */
	private static function parse_list( $raw ) {
		$raw   = sanitize_textarea_field( $raw );
		// Split on newlines and/or commas.
		$items = preg_split( '/[\r\n,]+/', $raw );
		return array_values( array_filter( array_map( 'trim', $items ) ) );
	}

	/**
	 * Render the settings page HTML.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wcpp' ) );
		}

		$settings = WCPP_Settings_Store::get_global();
		?>
		<div class="wrap wcpp-settings-wrap">
			<h1><?php esc_html_e( 'WC Personalisation Panel — Settings', 'wcpp' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'These are the global defaults. You can override any setting per product from the product edit screen.', 'wcpp' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'wcpp_settings_group' ); ?>

				<table class="form-table" role="presentation">

					<!-- Enable globally -->
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Personalisation', 'wcpp' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wcpp_global_settings[enabled]" value="1" <?php checked( $settings['enabled'] ); ?> />
								<?php esc_html_e( 'Enable the personalisation panel globally (can be toggled per product)', 'wcpp' ); ?>
							</label>
						</td>
					</tr>

					<!-- Locations -->
					<tr>
						<th scope="row">
							<label for="wcpp_locations"><?php esc_html_e( 'Locations', 'wcpp' ); ?></label>
						</th>
						<td>
							<textarea
								id="wcpp_locations"
								name="wcpp_global_settings[locations]"
								rows="5"
								class="large-text"
								placeholder="<?php esc_attr_e( 'One per line, e.g.&#10;Chest&#10;Cuff&#10;Collar&#10;Hem', 'wcpp' ); ?>"
							><?php echo esc_textarea( implode( "\n", $settings['locations'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Enter one location per line. These appear as choices in Step 1 of the panel.', 'wcpp' ); ?></p>
						</td>
					</tr>

					<!-- Types -->
					<tr>
						<th scope="row"><?php esc_html_e( 'Personalisation Types', 'wcpp' ); ?></th>
						<td>
							<?php
							$type_options = array(
								'text'     => __( 'Text (free text input)', 'wcpp' ),
								'initials' => __( 'Initials (limited characters)', 'wcpp' ),
								'symbol'   => __( 'Symbol (symbol picker)', 'wcpp' ),
							);
							foreach ( $type_options as $value => $label ) :
								?>
								<label style="display:block;margin-bottom:5px;">
									<input
										type="checkbox"
										name="wcpp_global_settings[types][]"
										value="<?php echo esc_attr( $value ); ?>"
										<?php checked( in_array( $value, $settings['types'], true ) ); ?>
									/>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Which personalisation types are available. Shown in Step 2 of the panel.', 'wcpp' ); ?></p>
						</td>
					</tr>

					<!-- Fonts -->
					<tr>
						<th scope="row">
							<label for="wcpp_fonts"><?php esc_html_e( 'Fonts', 'wcpp' ); ?></label>
						</th>
						<td>
							<textarea
								id="wcpp_fonts"
								name="wcpp_global_settings[fonts]"
								rows="5"
								class="large-text"
								placeholder="<?php esc_attr_e( 'One per line, e.g.&#10;serif&#10;script&#10;block', 'wcpp' ); ?>"
							><?php echo esc_textarea( implode( "\n", $settings['fonts'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Font slugs, one per line. Shown as options in Step 4 of the panel.', 'wcpp' ); ?></p>
						</td>
					</tr>

					<!-- Colours -->
					<tr>
						<th scope="row">
							<label for="wcpp_colours"><?php esc_html_e( 'Colours', 'wcpp' ); ?></label>
						</th>
						<td>
							<textarea
								id="wcpp_colours"
								name="wcpp_global_settings[colours]"
								rows="5"
								class="large-text"
								placeholder="<?php esc_attr_e( 'One hex colour per line, e.g.&#10;#000000&#10;#FFFFFF&#10;#C0A882', 'wcpp' ); ?>"
							><?php echo esc_textarea( implode( "\n", $settings['colours'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Hex colour values, one per line. Shown as colour swatches in Step 5 of the panel.', 'wcpp' ); ?></p>
						</td>
					</tr>

					<!-- Pricing -->
					<tr>
						<th scope="row"><?php esc_html_e( 'Flat Fee', 'wcpp' ); ?></th>
						<td>
							<input
								type="number"
								name="wcpp_global_settings[flat_fee]"
								value="<?php echo esc_attr( $settings['flat_fee'] ); ?>"
								step="0.01"
								min="0"
								class="small-text"
							/>
							<span><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
							<p class="description"><?php esc_html_e( 'Fixed charge added for any personalisation. Set to 0 for no flat fee.', 'wcpp' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Per-Character Fee', 'wcpp' ); ?></th>
						<td>
							<input
								type="number"
								name="wcpp_global_settings[per_char_fee]"
								value="<?php echo esc_attr( $settings['per_char_fee'] ); ?>"
								step="0.01"
								min="0"
								class="small-text"
							/>
							<span><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
							<p class="description"><?php esc_html_e( 'Charge per character entered. Set to 0 to disable.', 'wcpp' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wcpp_max_chars"><?php esc_html_e( 'Max Characters', 'wcpp' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="wcpp_max_chars"
								name="wcpp_global_settings[max_chars]"
								value="<?php echo esc_attr( $settings['max_chars'] ); ?>"
								min="1"
								max="50"
								class="small-text"
							/>
							<p class="description"><?php esc_html_e( 'Maximum number of characters allowed in the text input.', 'wcpp' ); ?></p>
						</td>
					</tr>

					<!-- Non-returnable -->
					<tr>
						<th scope="row"><?php esc_html_e( 'Non-Returnable', 'wcpp' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wcpp_global_settings[non_returnable]" value="1" <?php checked( $settings['non_returnable'] ); ?> />
								<?php esc_html_e( 'Mark personalised items as non-returnable by default', 'wcpp' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'A warning will appear on orders and in emails for non-returnable items.', 'wcpp' ); ?></p>
						</td>
					</tr>

					<!-- Elementor -->
					<tr>
						<th scope="row"><?php esc_html_e( 'Elementor Support', 'wcpp' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wcpp_global_settings[elementor_enabled]" value="1" <?php checked( $settings['elementor_enabled'] ); ?> />
								<?php esc_html_e( 'Enable Elementor widget (requires Elementor plugin to be active)', 'wcpp' ); ?>
							</label>
						</td>
					</tr>

				</table>

				<?php submit_button( __( 'Save Settings', 'wcpp' ) ); ?>
			</form>
		</div>
		<?php
	}
}
