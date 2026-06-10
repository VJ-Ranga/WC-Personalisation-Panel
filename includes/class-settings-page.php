<?php
/**
 * Panel Settings page — global design + behaviour, in its own menu item.
 * Two tabs: Design (visual) and Behaviour (config).
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCPP_Settings_Page
 */
class WCPP_Settings_Page {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init',            array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Register the single option with a sanitise callback.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'wcpp_settings_group',
			WCPP_Settings_Store::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);

		// Align the save capability with the page capability (manage_woocommerce)
		// so Shop Managers can save, not just full admins.
		add_filter( 'option_page_capability_wcpp_settings_group', array( __CLASS__, 'settings_capability' ) );
	}

	/**
	 * Capability required to save the settings group.
	 *
	 * @return string
	 */
	public static function settings_capability() {
		return 'manage_woocommerce';
	}

	/**
	 * Enqueue colour picker + admin assets on our settings page.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'wcpp-settings' ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'wcpp-admin', WCPP_URL . 'assets/css/admin.css', array( 'wp-color-picker' ), WCPP_VERSION );
		wp_enqueue_script( 'wcpp-settings', WCPP_URL . 'assets/js/wcpp-settings.js', array( 'jquery', 'wp-color-picker' ), WCPP_VERSION, true );
	}

	/**
	 * Sanitise both design and behaviour on save.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		// Start from the EXISTING saved settings so saving one tab never wipes
		// the other tab's values.
		$existing = get_option(
			WCPP_Settings_Store::OPTION,
			array(
				'design'    => WCPP_Settings_Store::design_defaults(),
				'behaviour' => WCPP_Settings_Store::behaviour_defaults(),
			)
		);
		$out = array(
			'design'    => wp_parse_args( $existing['design'] ?? array(), WCPP_Settings_Store::design_defaults() ),
			'behaviour' => wp_parse_args( $existing['behaviour'] ?? array(), WCPP_Settings_Store::behaviour_defaults() ),
		);

		// Which tab was submitted? Only that section is rebuilt.
		$tab = isset( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : '';

		if ( 'behaviour' === $tab ) {
			$b = isset( $input['behaviour'] ) && is_array( $input['behaviour'] ) ? $input['behaviour'] : array();
			foreach ( array( 'enabled', 'non_returnable', 'elementor', 'remove_on_uninstall' ) as $key ) {
				$out['behaviour'][ $key ] = empty( $b[ $key ] ) ? 0 : 1;
			}
			return $out;
		}

		// Default: design tab.
		$out['design'] = self::sanitize_design(
			isset( $input['design'] ) && is_array( $input['design'] ) ? $input['design'] : array()
		);
		return $out;
	}

	/**
	 * Sanitise the design section.
	 *
	 * @param array $d Raw design input.
	 * @return array
	 */
	private static function sanitize_design( $d ) {
		$dft = WCPP_Settings_Store::design_defaults();
		$out = array();

		foreach ( array( 'btn_bg', 'btn_text_color', 'panel_bg', 'overlay_color', 'title_color', 'progress_color', 'card_border', 'card_selected', 'footer_btn_color' ) as $key ) {
			$out[ $key ] = sanitize_hex_color( $d[ $key ] ?? '' ) ?: $dft[ $key ];
		}
		foreach ( array( 'btn_text', 'header_title', 'next_text', 'back_text', 'addbag_text', 'free_label' ) as $key ) {
			$out[ $key ] = sanitize_text_field( wp_unslash( $d[ $key ] ?? $dft[ $key ] ) );
		}

		$font_keys          = array_keys( WCPP_Settings_Store::fonts() );
		$out['font_family'] = in_array( $d['font_family'] ?? '', $font_keys, true ) ? $d['font_family'] : $dft['font_family'];

		$out['btn_style']      = in_array( $d['btn_style'] ?? '', array( 'outline', 'filled', 'text' ), true ) ? $d['btn_style'] : $dft['btn_style'];
		$out['btn_animation']  = in_array( $d['btn_animation'] ?? '', array( 'none', 'lift', 'pulse', 'shine', 'scale', 'bounce' ), true ) ? $d['btn_animation'] : $dft['btn_animation'];
		$out['btn_placement']  = in_array( $d['btn_placement'] ?? '', array( 'before_cart', 'after_cart', 'after_form', 'after_summary', 'shortcode' ), true ) ? $d['btn_placement'] : $dft['btn_placement'];
		$out['slide_from']     = in_array( $d['slide_from'] ?? '', array( 'right', 'left' ), true ) ? $d['slide_from'] : $dft['slide_from'];
		$out['progress_style'] = in_array( $d['progress_style'] ?? '', array( 'bar', 'dots', 'text' ), true ) ? $d['progress_style'] : $dft['progress_style'];
		$out['card_layout']    = in_array( $d['card_layout'] ?? '', array( 'list', 'grid2', 'grid3' ), true ) ? $d['card_layout'] : $dft['card_layout'];

		$out['btn_radius']      = max( 0, min( 50, intval( $d['btn_radius'] ?? $dft['btn_radius'] ) ) );
		$out['panel_width']     = max( 300, min( 700, intval( $d['panel_width'] ?? $dft['panel_width'] ) ) );
		$out['mobile_bp']       = max( 320, min( 1024, intval( $d['mobile_bp'] ?? $dft['mobile_bp'] ) ) );
		$out['panel_radius']    = max( 0, min( 50, intval( $d['panel_radius'] ?? $dft['panel_radius'] ) ) );
		$out['overlay_opacity'] = max( 0, min( 100, intval( $d['overlay_opacity'] ?? $dft['overlay_opacity'] ) ) );
		$out['anim_speed']      = max( 100, min( 1000, intval( $d['anim_speed'] ?? $dft['anim_speed'] ) ) );
		$out['card_img_size']   = max( 0, min( 120, intval( $d['card_img_size'] ?? $dft['card_img_size'] ) ) );

		foreach ( array( 'btn_full_width', 'progress_show', 'show_choice_price', 'show_total' ) as $key ) {
			$out[ $key ] = empty( $d[ $key ] ) ? 0 : 1;
		}

		return $out;
	}

	/**
	 * Render the settings page (both tabs).
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wcpp' ) );
		}

		$tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'design'; // phpcs:ignore WordPress.Security.NonceVerification
		$design = WCPP_Settings_Store::get_design();
		$behav  = WCPP_Settings_Store::get_behaviour();
		$cur    = get_woocommerce_currency_symbol();
		$base   = admin_url( 'admin.php?page=wcpp-settings' );
		?>
		<div class="wrap wcpp-settings-wrap">
			<div class="wcpp-settings-head">
				<h1><?php esc_html_e( 'Panel Settings', 'wcpp' ); ?></h1>
				<p class="wcpp-settings-sub"><?php esc_html_e( 'Global look & behaviour for the personalisation drawer. Applies to every set.', 'wcpp' ); ?></p>
			</div>

			<h2 class="nav-tab-wrapper wcpp-tabs">
				<a href="<?php echo esc_url( $base . '&tab=design' ); ?>" class="nav-tab <?php echo 'design' === $tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-admin-customizer"></span> <?php esc_html_e( 'Design', 'wcpp' ); ?>
				</a>
				<a href="<?php echo esc_url( $base . '&tab=behaviour' ); ?>" class="nav-tab <?php echo 'behaviour' === $tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Behaviour', 'wcpp' ); ?>
				</a>
			</h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'wcpp_settings_group' ); ?>
				<input type="hidden" name="<?php echo esc_attr( WCPP_Settings_Store::OPTION ); ?>[_tab]" value="<?php echo esc_attr( $tab ); ?>" />

				<div class="wcpp-settings-card">
					<?php if ( 'design' === $tab ) : ?>
						<?php self::render_design_tab( $design, $cur ); ?>
					<?php else : ?>
						<?php self::render_behaviour_tab( $behav ); ?>
					<?php endif; ?>
				</div>

				<div class="wcpp-settings-actions">
					<?php submit_button( __( 'Save Settings', 'wcpp' ), 'primary large', 'submit', false ); ?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a colour picker field.
	 *
	 * @param array  $design Design array.
	 * @param string $key    Field key.
	 * @return void
	 */
	private static function color_field( $design, $key ) {
		$defaults = WCPP_Settings_Store::design_defaults();
		printf(
			'<input type="text" name="%s[design][%s]" value="%s" class="wcpp-color-picker" data-default-color="%s" />',
			esc_attr( WCPP_Settings_Store::OPTION ),
			esc_attr( $key ),
			esc_attr( $design[ $key ] ),
			esc_attr( $defaults[ $key ] )
		);
	}

	/**
	 * Field name helper.
	 *
	 * @param string $group design|behaviour.
	 * @param string $key   Field key.
	 * @return string
	 */
	private static function name( $group, $key ) {
		return WCPP_Settings_Store::OPTION . '[' . $group . '][' . $key . ']';
	}

	/**
	 * Render the Design tab.
	 *
	 * @param array  $d   Design settings.
	 * @param string $cur Currency symbol.
	 * @return void
	 */
	private static function render_design_tab( $d, $cur ) {
		?>
		<h2 class="title"><?php esc_html_e( 'Trigger Button', 'wcpp' ); ?></h2>
		<p class="description"><?php esc_html_e( 'The button shown on the product page that opens the panel.', 'wcpp' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Default button text', 'wcpp' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( self::name( 'design', 'btn_text' ) ); ?>" value="<?php echo esc_attr( $d['btn_text'] ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Each set can override this with its own button text.', 'wcpp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Button style', 'wcpp' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::name( 'design', 'btn_style' ) ); ?>">
						<option value="outline" <?php selected( $d['btn_style'], 'outline' ); ?>><?php esc_html_e( 'Outline', 'wcpp' ); ?></option>
						<option value="filled" <?php selected( $d['btn_style'], 'filled' ); ?>><?php esc_html_e( 'Filled', 'wcpp' ); ?></option>
						<option value="text" <?php selected( $d['btn_style'], 'text' ); ?>><?php esc_html_e( 'Text only', 'wcpp' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Button animation', 'wcpp' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::name( 'design', 'btn_animation' ) ); ?>">
						<option value="lift"   <?php selected( $d['btn_animation'], 'lift' ); ?>><?php esc_html_e( 'Lift — subtle upward float on hover (default)', 'wcpp' ); ?></option>
						<option value="pulse"  <?php selected( $d['btn_animation'], 'pulse' ); ?>><?php esc_html_e( 'Pulse — glowing ring, draws attention to the CTA', 'wcpp' ); ?></option>
						<option value="shine"  <?php selected( $d['btn_animation'], 'shine' ); ?>><?php esc_html_e( 'Shine — light sweep across on hover', 'wcpp' ); ?></option>
						<option value="scale"  <?php selected( $d['btn_animation'], 'scale' ); ?>><?php esc_html_e( 'Scale — smooth grow on hover', 'wcpp' ); ?></option>
						<option value="bounce" <?php selected( $d['btn_animation'], 'bounce' ); ?>><?php esc_html_e( 'Bounce — playful springy hover', 'wcpp' ); ?></option>
						<option value="none"   <?php selected( $d['btn_animation'], 'none' ); ?>><?php esc_html_e( 'None — no animation', 'wcpp' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Button background', 'wcpp' ); ?></th>
				<td><?php self::color_field( $d, 'btn_bg' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Button text colour', 'wcpp' ); ?></th>
				<td><?php self::color_field( $d, 'btn_text_color' ); ?>
					<p class="description"><?php esc_html_e( 'Used for Outline/Text styles. Filled style uses white text automatically.', 'wcpp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Button radius', 'wcpp' ); ?></th>
				<td><input type="number" name="<?php echo esc_attr( self::name( 'design', 'btn_radius' ) ); ?>" value="<?php echo esc_attr( $d['btn_radius'] ); ?>" min="0" max="50" class="small-text" /> px</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Placement', 'wcpp' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::name( 'design', 'btn_placement' ) ); ?>">
						<option value="after_form" <?php selected( $d['btn_placement'], 'after_form' ); ?>><?php esc_html_e( 'Under all buttons (below Add to Cart & Buy Now) — recommended', 'wcpp' ); ?></option>
						<option value="before_cart" <?php selected( $d['btn_placement'], 'before_cart' ); ?>><?php esc_html_e( 'Above the Add to Cart button', 'wcpp' ); ?></option>
						<option value="after_cart" <?php selected( $d['btn_placement'], 'after_cart' ); ?>><?php esc_html_e( 'Between Add to Cart & Buy Now', 'wcpp' ); ?></option>
						<option value="after_summary" <?php selected( $d['btn_placement'], 'after_summary' ); ?>><?php esc_html_e( 'Below the product summary', 'wcpp' ); ?></option>
						<option value="shortcode" <?php selected( $d['btn_placement'], 'shortcode' ); ?>><?php esc_html_e( 'Shortcode / manual only [wcpp_button]', 'wcpp' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Full width', 'wcpp' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::name( 'design', 'btn_full_width' ) ); ?>" value="1" <?php checked( $d['btn_full_width'] ); ?> /> <?php esc_html_e( 'Make button full width', 'wcpp' ); ?></label></td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Drawer / Panel', 'wcpp' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Slide from', 'wcpp' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::name( 'design', 'slide_from' ) ); ?>">
						<option value="right" <?php selected( $d['slide_from'], 'right' ); ?>><?php esc_html_e( 'Right', 'wcpp' ); ?></option>
						<option value="left" <?php selected( $d['slide_from'], 'left' ); ?>><?php esc_html_e( 'Left', 'wcpp' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Panel width', 'wcpp' ); ?></th>
				<td><input type="number" name="<?php echo esc_attr( self::name( 'design', 'panel_width' ) ); ?>" value="<?php echo esc_attr( $d['panel_width'] ); ?>" min="300" max="700" class="small-text" /> px <span class="description"><?php esc_html_e( '300–700', 'wcpp' ); ?></span></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Mobile breakpoint', 'wcpp' ); ?></th>
				<td><input type="number" name="<?php echo esc_attr( self::name( 'design', 'mobile_bp' ) ); ?>" value="<?php echo esc_attr( $d['mobile_bp'] ); ?>" min="320" max="1024" class="small-text" /> px <span class="description"><?php esc_html_e( 'Below this width the panel goes full-screen.', 'wcpp' ); ?></span></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Panel background', 'wcpp' ); ?></th>
				<td><?php self::color_field( $d, 'panel_bg' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Font family', 'wcpp' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::name( 'design', 'font_family' ) ); ?>">
						<option value="inherit" <?php selected( $d['font_family'], 'inherit' ); ?>><?php esc_html_e( 'Inherit from theme', 'wcpp' ); ?></option>
						<option value="Poppins" <?php selected( $d['font_family'], 'Poppins' ); ?>>Poppins</option>
						<option value="Playfair Display" <?php selected( $d['font_family'], 'Playfair Display' ); ?>>Playfair Display</option>
						<option value="Montserrat" <?php selected( $d['font_family'], 'Montserrat' ); ?>>Montserrat</option>
						<option value="Lato" <?php selected( $d['font_family'], 'Lato' ); ?>>Lato</option>
						<option value="Cormorant Garamond" <?php selected( $d['font_family'], 'Cormorant Garamond' ); ?>>Cormorant Garamond</option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Panel radius', 'wcpp' ); ?></th>
				<td><input type="number" name="<?php echo esc_attr( self::name( 'design', 'panel_radius' ) ); ?>" value="<?php echo esc_attr( $d['panel_radius'] ); ?>" min="0" max="50" class="small-text" /> px</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Overlay colour', 'wcpp' ); ?></th>
				<td><?php self::color_field( $d, 'overlay_color' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Overlay opacity', 'wcpp' ); ?></th>
				<td><input type="number" name="<?php echo esc_attr( self::name( 'design', 'overlay_opacity' ) ); ?>" value="<?php echo esc_attr( $d['overlay_opacity'] ); ?>" min="0" max="100" class="small-text" /> %</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Animation speed', 'wcpp' ); ?></th>
				<td><input type="number" name="<?php echo esc_attr( self::name( 'design', 'anim_speed' ) ); ?>" value="<?php echo esc_attr( $d['anim_speed'] ); ?>" min="100" max="1000" step="50" class="small-text" /> ms</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Header & Progress', 'wcpp' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Panel title', 'wcpp' ); ?></th>
				<td><input type="text" name="<?php echo esc_attr( self::name( 'design', 'header_title' ) ); ?>" value="<?php echo esc_attr( $d['header_title'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Title colour', 'wcpp' ); ?></th>
				<td><?php self::color_field( $d, 'title_color' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Show progress indicator', 'wcpp' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::name( 'design', 'progress_show' ) ); ?>" value="1" <?php checked( $d['progress_show'] ); ?> /> <?php esc_html_e( 'Show progress at the top of the panel', 'wcpp' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Progress style', 'wcpp' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::name( 'design', 'progress_style' ) ); ?>">
						<option value="bar" <?php selected( $d['progress_style'], 'bar' ); ?>><?php esc_html_e( 'Bar', 'wcpp' ); ?></option>
						<option value="dots" <?php selected( $d['progress_style'], 'dots' ); ?>><?php esc_html_e( 'Dots', 'wcpp' ); ?></option>
						<option value="text" <?php selected( $d['progress_style'], 'text' ); ?>><?php esc_html_e( 'Text (Step 1 of 4)', 'wcpp' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Progress colour', 'wcpp' ); ?></th>
				<td><?php self::color_field( $d, 'progress_color' ); ?></td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Choice Cards', 'wcpp' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Layout', 'wcpp' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::name( 'design', 'card_layout' ) ); ?>">
						<option value="list" <?php selected( $d['card_layout'], 'list' ); ?>><?php esc_html_e( 'List (1 per row)', 'wcpp' ); ?></option>
						<option value="grid2" <?php selected( $d['card_layout'], 'grid2' ); ?>><?php esc_html_e( 'Grid (2 columns)', 'wcpp' ); ?></option>
						<option value="grid3" <?php selected( $d['card_layout'], 'grid3' ); ?>><?php esc_html_e( 'Grid (3 columns)', 'wcpp' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Card border colour', 'wcpp' ); ?></th>
				<td><?php self::color_field( $d, 'card_border' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Selected colour', 'wcpp' ); ?></th>
				<td><?php self::color_field( $d, 'card_selected' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Image size', 'wcpp' ); ?></th>
				<td><input type="number" name="<?php echo esc_attr( self::name( 'design', 'card_img_size' ) ); ?>" value="<?php echo esc_attr( $d['card_img_size'] ); ?>" min="0" max="120" class="small-text" /> px <span class="description"><?php esc_html_e( '0 hides images.', 'wcpp' ); ?></span></td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Footer Buttons & Pricing', 'wcpp' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Next button text', 'wcpp' ); ?></th>
				<td><input type="text" name="<?php echo esc_attr( self::name( 'design', 'next_text' ) ); ?>" value="<?php echo esc_attr( $d['next_text'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Back button text', 'wcpp' ); ?></th>
				<td><input type="text" name="<?php echo esc_attr( self::name( 'design', 'back_text' ) ); ?>" value="<?php echo esc_attr( $d['back_text'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Add to Bag text', 'wcpp' ); ?></th>
				<td><input type="text" name="<?php echo esc_attr( self::name( 'design', 'addbag_text' ) ); ?>" value="<?php echo esc_attr( $d['addbag_text'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Footer button colour', 'wcpp' ); ?></th>
				<td><?php self::color_field( $d, 'footer_btn_color' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Show price on each choice', 'wcpp' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::name( 'design', 'show_choice_price' ) ); ?>" value="1" <?php checked( $d['show_choice_price'] ); ?> /> <?php esc_html_e( 'Show the price next to each choice', 'wcpp' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Show running total', 'wcpp' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::name( 'design', 'show_total' ) ); ?>" value="1" <?php checked( $d['show_total'] ); ?> /> <?php esc_html_e( 'Show the personalisation total on the summary step', 'wcpp' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Free label', 'wcpp' ); ?></th>
				<td><input type="text" name="<?php echo esc_attr( self::name( 'design', 'free_label' ) ); ?>" value="<?php echo esc_attr( $d['free_label'] ); ?>" class="regular-text" /> <span class="description"><?php esc_html_e( 'Shown when a choice costs nothing.', 'wcpp' ); ?></span></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the Behaviour tab.
	 *
	 * @param array $b Behaviour settings.
	 * @return void
	 */
	private static function render_behaviour_tab( $b ) {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Enable plugin', 'wcpp' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::name( 'behaviour', 'enabled' ) ); ?>" value="1" <?php checked( $b['enabled'] ); ?> /> <?php esc_html_e( 'Master switch — turn personalisation on/off site-wide', 'wcpp' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Non-returnable', 'wcpp' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::name( 'behaviour', 'non_returnable' ) ); ?>" value="1" <?php checked( $b['non_returnable'] ); ?> /> <?php esc_html_e( 'Mark personalised items as non-returnable (shown on cart, order, email)', 'wcpp' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Elementor support', 'wcpp' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::name( 'behaviour', 'elementor' ) ); ?>" value="1" <?php checked( $b['elementor'] ); ?> /> <?php esc_html_e( 'Enable the Elementor widget (requires Elementor active)', 'wcpp' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Remove data on uninstall', 'wcpp' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::name( 'behaviour', 'remove_on_uninstall' ) ); ?>" value="1" <?php checked( $b['remove_on_uninstall'] ); ?> /> <?php esc_html_e( 'Delete all sets and settings when the plugin is deleted', 'wcpp' ); ?></label></td>
			</tr>
		</table>
		<?php
	}
}
