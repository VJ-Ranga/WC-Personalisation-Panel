<?php
/**
 * Personalisation CPT — registers the personalisation set custom post type
 * and handles the builder UI, category assignment, and design settings.
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCPP_Personalisation_CPT
 */
class WCPP_Personalisation_CPT {

	/**
	 * Register all hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init',                                            array( __CLASS__, 'register_cpt' ) );
		add_action( 'add_meta_boxes',                                  array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_wcpp_personalisation',                  array( __CLASS__, 'save_all_meta' ), 10, 2 );
		add_filter( 'manage_wcpp_personalisation_posts_columns',       array( __CLASS__, 'set_admin_columns' ) );
		add_action( 'manage_wcpp_personalisation_posts_custom_column', array( __CLASS__, 'render_admin_column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts',                           array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'post_updated_messages',                           array( __CLASS__, 'updated_messages' ) );
	}

	/**
	 * Register the wcpp_personalisation custom post type.
	 *
	 * @return void
	 */
	public static function register_cpt() {
		$labels = array(
			'name'               => __( 'Personalisation', 'wcpp' ),
			'singular_name'      => __( 'Personalisation Set', 'wcpp' ),
			'add_new'            => __( 'Create Personalisation', 'wcpp' ),
			'add_new_item'       => __( 'Create Personalisation Set', 'wcpp' ),
			'edit_item'          => __( 'Edit Personalisation Set', 'wcpp' ),
			'new_item'           => __( 'New Personalisation Set', 'wcpp' ),
			'all_items'          => __( 'Personalisation', 'wcpp' ),
			'search_items'       => __( 'Search Sets', 'wcpp' ),
			'not_found'          => __( 'No personalisation sets found. Create one!', 'wcpp' ),
			'not_found_in_trash' => __( 'No sets in trash.', 'wcpp' ),
			'menu_name'          => __( 'Personalisation', 'wcpp' ),
		);

		register_post_type(
			'wcpp_personalisation',
			array(
				'labels'            => $labels,
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => 'woocommerce',
				'show_in_nav_menus' => false,
				'show_in_admin_bar' => false,
				'supports'          => array( 'title' ),
				'has_archive'       => false,
				'rewrite'           => false,
				'query_var'         => false,
				'capability_type'   => 'post',
				'map_meta_cap'      => true,
			)
		);
	}

	/**
	 * Register all meta boxes.
	 *
	 * @return void
	 */
	public static function add_meta_boxes() {
		// Main options builder — full width.
		add_meta_box(
			'wcpp_options_builder',
			__( 'Personalisation Options (Steps)', 'wcpp' ),
			array( __CLASS__, 'render_builder' ),
			'wcpp_personalisation',
			'normal',
			'high'
		);

		// Category assignment — sidebar.
		add_meta_box(
			'wcpp_categories',
			__( 'Apply to Product Categories', 'wcpp' ),
			array( __CLASS__, 'render_categories_box' ),
			'wcpp_personalisation',
			'side',
			'high'
		);

		// Panel design settings — sidebar.
		add_meta_box(
			'wcpp_design',
			__( 'Panel Design', 'wcpp' ),
			array( __CLASS__, 'render_design_box' ),
			'wcpp_personalisation',
			'side',
			'default'
		);

		// How to use info — sidebar.
		add_meta_box(
			'wcpp_set_info',
			__( 'How to use this set', 'wcpp' ),
			array( __CLASS__, 'render_info_box' ),
			'wcpp_personalisation',
			'side',
			'low'
		);
	}

	/**
	 * Enqueue admin assets on the CPT edit screen only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'wcpp_personalisation' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'wcpp-admin',
			WCPP_URL . 'assets/css/admin.css',
			array( 'wp-color-picker' ),
			WCPP_VERSION
		);

		wp_enqueue_script(
			'wcpp-admin',
			WCPP_URL . 'assets/js/wcpp-admin.js',
			array( 'jquery', 'wp-color-picker' ),
			WCPP_VERSION,
			true
		);

		wp_localize_script(
			'wcpp-admin',
			'wcppAdmin',
			array(
				'mediaTitle'        => __( 'Select Image', 'wcpp' ),
				'mediaButton'       => __( 'Use This Image', 'wcpp' ),
				'changeImage'       => __( 'Change Image', 'wcpp' ),
				'addImage'          => __( 'Add Image', 'wcpp' ),
				'confirmDelete'     => __( 'Are you sure you want to delete this?', 'wcpp' ),
				'namePlaceholder'   => __( 'Option name, e.g. Location', 'wcpp' ),
				'choicePlaceholder' => __( 'Choice name, e.g. Front', 'wcpp' ),
				'addChoice'         => __( '+ Add Choice', 'wcpp' ),
			)
		);
	}

	// ─── OPTIONS BUILDER ─────────────────────────────────────────────────

	/**
	 * Render the options builder meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_builder( $post ) {
		wp_nonce_field( 'wcpp_save_all_meta', 'wcpp_meta_nonce' );

		$options = get_post_meta( $post->ID, '_wcpp_options', true );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		?>
		<div id="wcpp-builder">
			<p class="wcpp-builder-intro">
				<?php esc_html_e( 'Each Option is one step in the panel (e.g. Location, Font, Color). Each Choice is a selectable item within that step.', 'wcpp' ); ?>
			</p>
			<div id="wcpp-options-container">
				<?php foreach ( $options as $opt_idx => $option ) : ?>
					<?php self::render_option_block( $opt_idx, $option ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" id="wcpp-add-option" class="button button-primary wcpp-add-option-btn">
				&#43; <?php esc_html_e( 'Create Option', 'wcpp' ); ?>
			</button>
		</div>
		<script>var wcppOptionCount = <?php echo (int) count( $options ); ?>;</script>
		<?php
	}

	/**
	 * Render a single option block.
	 *
	 * @param int   $opt_idx Option index.
	 * @param array $option  Option data.
	 * @return void
	 */
	public static function render_option_block( $opt_idx, $option ) {
		$opt_id   = isset( $option['id'] ) ? $option['id'] : 'opt_' . uniqid();
		$opt_name = isset( $option['name'] ) ? $option['name'] : '';
		$choices  = isset( $option['choices'] ) && is_array( $option['choices'] ) ? $option['choices'] : array();
		?>
		<div class="wcpp-option-block" data-option-index="<?php echo esc_attr( $opt_idx ); ?>">
			<div class="wcpp-option-header">
				<span class="wcpp-option-label"><?php esc_html_e( 'Step', 'wcpp' ); ?> <span class="wcpp-step-num"><?php echo esc_html( $opt_idx + 1 ); ?></span></span>
				<input type="hidden" name="wcpp_options[<?php echo esc_attr( $opt_idx ); ?>][id]" value="<?php echo esc_attr( $opt_id ); ?>" />
				<input type="text" name="wcpp_options[<?php echo esc_attr( $opt_idx ); ?>][name]"
					value="<?php echo esc_attr( $opt_name ); ?>"
					placeholder="<?php esc_attr_e( 'Option name, e.g. Location', 'wcpp' ); ?>"
					class="wcpp-option-name-input" />
				<button type="button" class="button wcpp-delete-option" title="<?php esc_attr_e( 'Delete', 'wcpp' ); ?>">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>
			<div class="wcpp-choices-list">
				<?php foreach ( $choices as $ch_idx => $choice ) : ?>
					<?php self::render_choice_row( $opt_idx, $ch_idx, $choice ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button wcpp-add-choice-btn">
				&#43; <?php esc_html_e( 'Add Choice', 'wcpp' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render a single choice row.
	 *
	 * @param int   $opt_idx Option index.
	 * @param int   $ch_idx  Choice index.
	 * @param array $choice  Choice data.
	 * @return void
	 */
	public static function render_choice_row( $opt_idx, $ch_idx, $choice ) {
		$ch_id    = isset( $choice['id'] ) ? $choice['id'] : 'ch_' . uniqid();
		$ch_name  = isset( $choice['name'] ) ? $choice['name'] : '';
		$ch_img   = isset( $choice['image_id'] ) ? $choice['image_id'] : '';
		$ch_url   = isset( $choice['image_url'] ) ? $choice['image_url'] : '';
		$ch_price = isset( $choice['price'] ) ? $choice['price'] : '0.00';
		$prefix   = 'wcpp_options[' . $opt_idx . '][choices][' . $ch_idx . ']';
		?>
		<div class="wcpp-choice-row">
			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[id]" value="<?php echo esc_attr( $ch_id ); ?>" />
			<div class="wcpp-choice-image-wrap">
				<div class="wcpp-image-preview" <?php echo $ch_url ? '' : 'style="display:none;"'; ?>>
					<img src="<?php echo esc_url( $ch_url ); ?>" alt="" />
					<button type="button" class="wcpp-remove-image" title="<?php esc_attr_e( 'Remove', 'wcpp' ); ?>">&#10005;</button>
				</div>
				<button type="button" class="button wcpp-select-image">
					<?php echo $ch_url ? esc_html__( 'Change Image', 'wcpp' ) : esc_html__( 'Add Image', 'wcpp' ); ?>
				</button>
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[image_id]" class="wcpp-image-id" value="<?php echo esc_attr( $ch_img ); ?>" />
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[image_url]" class="wcpp-image-url" value="<?php echo esc_attr( $ch_url ); ?>" />
			</div>
			<div class="wcpp-choice-field">
				<label><?php esc_html_e( 'Name', 'wcpp' ); ?></label>
				<input type="text" name="<?php echo esc_attr( $prefix ); ?>[name]"
					value="<?php echo esc_attr( $ch_name ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. Front', 'wcpp' ); ?>"
					class="wcpp-choice-name" />
			</div>
			<div class="wcpp-choice-field wcpp-choice-field--price">
				<label><?php esc_html_e( 'Price', 'wcpp' ); ?> (<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>)</label>
				<input type="number" name="<?php echo esc_attr( $prefix ); ?>[price]"
					value="<?php echo esc_attr( $ch_price ); ?>"
					step="0.01" min="0" placeholder="0.00" class="wcpp-choice-price" />
			</div>
			<button type="button" class="button wcpp-delete-choice" title="<?php esc_attr_e( 'Delete', 'wcpp' ); ?>">
				<span class="dashicons dashicons-trash"></span>
			</button>
		</div>
		<?php
	}

	// ─── CATEGORY ASSIGNMENT ─────────────────────────────────────────────

	/**
	 * Render the category assignment meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_categories_box( $post ) {
		$assigned = get_post_meta( $post->ID, '_wcpp_assigned_categories', true );
		if ( ! is_array( $assigned ) ) {
			$assigned = array();
		}

		$apply_all = (bool) get_post_meta( $post->ID, '_wcpp_apply_all', true );

		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		?>
		<div class="wcpp-categories-box">
			<p>
				<label>
					<input type="checkbox" name="wcpp_apply_all" value="1" id="wcpp_apply_all" <?php checked( $apply_all ); ?> />
					<strong><?php esc_html_e( 'Apply to ALL products', 'wcpp' ); ?></strong>
				</label>
			</p>
			<p class="description"><?php esc_html_e( 'Or select specific categories:', 'wcpp' ); ?></p>
			<div class="wcpp-category-list" id="wcpp-category-list" <?php echo $apply_all ? 'style="opacity:0.4;pointer-events:none;"' : ''; ?>>
				<?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
					<?php foreach ( $categories as $cat ) : ?>
						<label class="wcpp-cat-label">
							<input type="checkbox"
								name="wcpp_assigned_categories[]"
								value="<?php echo esc_attr( $cat->term_id ); ?>"
								<?php checked( in_array( (string) $cat->term_id, array_map( 'strval', $assigned ), true ) ); ?> />
							<?php echo esc_html( $cat->name ); ?>
							<span class="wcpp-cat-count">(<?php echo esc_html( $cat->count ); ?>)</span>
						</label>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'No product categories found.', 'wcpp' ); ?></p>
				<?php endif; ?>
			</div>
			<p class="description" style="margin-top:8px;">
				<?php esc_html_e( 'Individual product settings override these.', 'wcpp' ); ?>
			</p>
		</div>
		<script>
		jQuery(function($){
			$('#wcpp_apply_all').on('change', function(){
				$('#wcpp-category-list').css({
					opacity: this.checked ? 0.4 : 1,
					pointerEvents: this.checked ? 'none' : ''
				});
			});
		});
		</script>
		<?php
	}

	// ─── DESIGN SETTINGS ─────────────────────────────────────────────────

	/**
	 * Render the panel design settings meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_design_box( $post ) {
		$design = get_post_meta( $post->ID, '_wcpp_design', true );
		if ( ! is_array( $design ) ) {
			$design = array();
		}

		$defaults = self::design_defaults();
		$design   = wp_parse_args( $design, $defaults );
		?>
		<div class="wcpp-design-box">

			<!-- Primary colour -->
			<p>
				<label><strong><?php esc_html_e( 'Primary Colour', 'wcpp' ); ?></strong></label><br />
				<input type="text" name="wcpp_design[primary_color]"
					value="<?php echo esc_attr( $design['primary_color'] ); ?>"
					class="wcpp-color-picker" data-default-color="<?php echo esc_attr( $defaults['primary_color'] ); ?>" />
			</p>

			<!-- Panel width -->
			<p>
				<label><strong><?php esc_html_e( 'Panel Width (px)', 'wcpp' ); ?></strong></label><br />
				<input type="number" name="wcpp_design[panel_width]"
					value="<?php echo esc_attr( $design['panel_width'] ); ?>"
					min="300" max="700" style="width:80px;" /> px
				<span class="description"><?php esc_html_e( '300–700px. Default: 420.', 'wcpp' ); ?></span>
			</p>

			<!-- Button text -->
			<p>
				<label><strong><?php esc_html_e( 'Button Text', 'wcpp' ); ?></strong></label><br />
				<input type="text" name="wcpp_design[button_text]"
					value="<?php echo esc_attr( $design['button_text'] ); ?>"
					style="width:100%;" />
			</p>

			<!-- Button style -->
			<p>
				<label><strong><?php esc_html_e( 'Button Style', 'wcpp' ); ?></strong></label><br />
				<select name="wcpp_design[button_style]" style="width:100%;">
					<option value="outline" <?php selected( $design['button_style'], 'outline' ); ?>><?php esc_html_e( 'Outline', 'wcpp' ); ?></option>
					<option value="filled" <?php selected( $design['button_style'], 'filled' ); ?>><?php esc_html_e( 'Filled', 'wcpp' ); ?></option>
				</select>
			</p>

			<!-- Font family -->
			<p>
				<label><strong><?php esc_html_e( 'Panel Font', 'wcpp' ); ?></strong></label><br />
				<select name="wcpp_design[font_family]" style="width:100%;">
					<option value="inherit" <?php selected( $design['font_family'], 'inherit' ); ?>><?php esc_html_e( 'Inherit from theme', 'wcpp' ); ?></option>
					<option value="'Poppins', sans-serif" <?php selected( $design['font_family'], "'Poppins', sans-serif" ); ?>>Poppins</option>
					<option value="'Playfair Display', serif" <?php selected( $design['font_family'], "'Playfair Display', serif" ); ?>>Playfair Display</option>
					<option value="'Montserrat', sans-serif" <?php selected( $design['font_family'], "'Montserrat', sans-serif" ); ?>>Montserrat</option>
					<option value="'Lato', sans-serif" <?php selected( $design['font_family'], "'Lato', sans-serif" ); ?>>Lato</option>
					<option value="'Cormorant Garamond', serif" <?php selected( $design['font_family'], "'Cormorant Garamond', serif" ); ?>>Cormorant Garamond</option>
				</select>
			</p>

			<!-- Border radius -->
			<p>
				<label><strong><?php esc_html_e( 'Button/Card Radius (px)', 'wcpp' ); ?></strong></label><br />
				<input type="number" name="wcpp_design[border_radius]"
					value="<?php echo esc_attr( $design['border_radius'] ); ?>"
					min="0" max="50" style="width:70px;" /> px
			</p>

		</div>
		<?php
	}

	/**
	 * Design setting defaults.
	 *
	 * @return array
	 */
	public static function design_defaults() {
		return array(
			'primary_color' => '#1A56DB',
			'panel_width'   => 420,
			'button_text'   => 'Add Personalisation',
			'button_style'  => 'outline',
			'font_family'   => 'inherit',
			'border_radius' => 6,
		);
	}

	// ─── INFO BOX ─────────────────────────────────────────────────────────

	/**
	 * Render the info sidebar box.
	 *
	 * @return void
	 */
	public static function render_info_box() {
		?>
		<p class="description">
			<?php esc_html_e( 'Assign this set to categories above, or go to a product and select it in the Personalisation meta box.', 'wcpp' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Product-level assignment overrides category-level.', 'wcpp' ); ?>
		</p>
		<?php
	}

	// ─── SAVE ─────────────────────────────────────────────────────────────

	/**
	 * Save all meta when the post is saved.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_all_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if (
			! isset( $_POST['wcpp_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['wcpp_meta_nonce'] ), 'wcpp_save_all_meta' )
		) {
			return;
		}

		// Save options.
		self::save_options( $post_id );

		// Save category assignment.
		self::save_categories( $post_id );

		// Save design settings.
		self::save_design( $post_id );
	}

	/**
	 * Save options builder data.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function save_options( $post_id ) {
		if ( empty( $_POST['wcpp_options'] ) || ! is_array( $_POST['wcpp_options'] ) ) {
			update_post_meta( $post_id, '_wcpp_options', array() );
			return;
		}

		$clean = array();

		foreach ( $_POST['wcpp_options'] as $opt ) { // phpcs:ignore
			$opt_name = sanitize_text_field( wp_unslash( $opt['name'] ?? '' ) );
			if ( empty( $opt_name ) ) {
				continue;
			}
			$clean_opt = array(
				'id'      => sanitize_text_field( wp_unslash( $opt['id'] ?? 'opt_' . uniqid() ) ),
				'name'    => $opt_name,
				'choices' => array(),
			);
			if ( ! empty( $opt['choices'] ) && is_array( $opt['choices'] ) ) {
				foreach ( $opt['choices'] as $ch ) {
					$ch_name = sanitize_text_field( wp_unslash( $ch['name'] ?? '' ) );
					if ( empty( $ch_name ) ) {
						continue;
					}
					$clean_opt['choices'][] = array(
						'id'        => sanitize_text_field( wp_unslash( $ch['id'] ?? 'ch_' . uniqid() ) ),
						'name'      => $ch_name,
						'image_id'  => absint( $ch['image_id'] ?? 0 ),
						'image_url' => esc_url_raw( wp_unslash( $ch['image_url'] ?? '' ) ),
						'price'     => number_format( (float) ( $ch['price'] ?? 0 ), 2, '.', '' ),
					);
				}
			}
			$clean[] = $clean_opt;
		}

		update_post_meta( $post_id, '_wcpp_options', $clean );
	}

	/**
	 * Save category assignment data.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function save_categories( $post_id ) {
		$apply_all = ! empty( $_POST['wcpp_apply_all'] );
		update_post_meta( $post_id, '_wcpp_apply_all', $apply_all ? '1' : '0' );

		$cats = array();
		if ( ! empty( $_POST['wcpp_assigned_categories'] ) && is_array( $_POST['wcpp_assigned_categories'] ) ) {
			foreach ( $_POST['wcpp_assigned_categories'] as $cat_id ) {
				$cats[] = absint( $cat_id );
			}
		}
		update_post_meta( $post_id, '_wcpp_assigned_categories', $cats );
	}

	/**
	 * Save design settings.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function save_design( $post_id ) {
		if ( empty( $_POST['wcpp_design'] ) || ! is_array( $_POST['wcpp_design'] ) ) {
			return;
		}

		$raw      = $_POST['wcpp_design']; // phpcs:ignore
		$defaults = self::design_defaults();

		$clean = array(
			'primary_color' => sanitize_hex_color( $raw['primary_color'] ?? $defaults['primary_color'] ) ?: $defaults['primary_color'],
			'panel_width'   => max( 300, min( 700, intval( $raw['panel_width'] ?? $defaults['panel_width'] ) ) ),
			'button_text'   => sanitize_text_field( wp_unslash( $raw['button_text'] ?? $defaults['button_text'] ) ),
			'button_style'  => in_array( $raw['button_style'] ?? '', array( 'outline', 'filled' ), true ) ? $raw['button_style'] : $defaults['button_style'],
			'font_family'   => sanitize_text_field( wp_unslash( $raw['font_family'] ?? $defaults['font_family'] ) ),
			'border_radius' => max( 0, min( 50, intval( $raw['border_radius'] ?? $defaults['border_radius'] ) ) ),
		);

		update_post_meta( $post_id, '_wcpp_design', $clean );
	}

	// ─── ADMIN COLUMNS ───────────────────────────────────────────────────

	/**
	 * Custom admin list columns.
	 *
	 * @param array $columns Default columns.
	 * @return array
	 */
	public static function set_admin_columns( $columns ) {
		return array(
			'cb'         => $columns['cb'],
			'title'      => __( 'Set Name', 'wcpp' ),
			'categories' => __( 'Applies To', 'wcpp' ),
			'options'    => __( 'Steps', 'wcpp' ),
			'choices'    => __( 'Total Choices', 'wcpp' ),
			'date'       => __( 'Updated', 'wcpp' ),
		);
	}

	/**
	 * Render custom column values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_admin_column( $column, $post_id ) {
		$options = get_post_meta( $post_id, '_wcpp_options', true );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( 'options' === $column ) {
			echo esc_html( count( $options ) );
		}

		if ( 'choices' === $column ) {
			$total = 0;
			foreach ( $options as $opt ) {
				$total += count( $opt['choices'] ?? array() );
			}
			echo esc_html( $total );
		}

		if ( 'categories' === $column ) {
			$apply_all = get_post_meta( $post_id, '_wcpp_apply_all', true );
			if ( $apply_all ) {
				echo '<span style="color:#2271b1;">&#9679; ' . esc_html__( 'All Products', 'wcpp' ) . '</span>';
				return;
			}
			$cats = get_post_meta( $post_id, '_wcpp_assigned_categories', true );
			if ( ! empty( $cats ) && is_array( $cats ) ) {
				$names = array();
				foreach ( $cats as $cat_id ) {
					$term = get_term( $cat_id, 'product_cat' );
					if ( $term && ! is_wp_error( $term ) ) {
						$names[] = esc_html( $term->name );
					}
				}
				echo implode( ', ', $names ); // phpcs:ignore
			} else {
				echo '<span style="color:#999;">' . esc_html__( 'Not assigned', 'wcpp' ) . '</span>';
			}
		}
	}

	/**
	 * Customise post updated messages.
	 *
	 * @param array $messages Messages.
	 * @return array
	 */
	public static function updated_messages( $messages ) {
		$messages['wcpp_personalisation'] = array(
			0  => '',
			1  => __( 'Personalisation set saved.', 'wcpp' ),
			6  => __( 'Personalisation set published.', 'wcpp' ),
			10 => __( 'Personalisation set draft updated.', 'wcpp' ),
		);
		return $messages;
	}
}
