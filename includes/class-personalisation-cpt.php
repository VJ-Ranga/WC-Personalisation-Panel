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
			'name'               => __( 'Personalisation Sets', 'wcpp' ),
			'singular_name'      => __( 'Personalisation Set', 'wcpp' ),
			'add_new'            => __( 'Add New Set', 'wcpp' ),
			'add_new_item'       => __( 'Add New Personalisation Set', 'wcpp' ),
			'edit_item'          => __( 'Edit Personalisation Set', 'wcpp' ),
			'new_item'           => __( 'New Personalisation Set', 'wcpp' ),
			'view_item'          => __( 'View Personalisation Set', 'wcpp' ),
			'all_items'          => __( 'Personalisation Sets', 'wcpp' ),
			'search_items'       => __( 'Search Personalisation Sets', 'wcpp' ),
			'not_found'          => __( 'No personalisation sets yet. Create your first one!', 'wcpp' ),
			'not_found_in_trash' => __( 'No personalisation sets in trash.', 'wcpp' ),
			'menu_name'          => __( 'Personalisation Sets', 'wcpp' ),
		);

		register_post_type(
			'wcpp_personalisation',
			array(
				'labels'            => $labels,
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => WCPP_Admin_Menu::MENU_SLUG,
				'show_in_nav_menus' => false,
				'show_in_admin_bar' => false,
				'supports'          => array( 'title' ),
				'has_archive'       => false,
				'rewrite'           => false,
				'query_var'         => false,
				// Map every CPT access point directly to manage_woocommerce so the
				// capability boundary matches the menu gate exactly. Using
				// capability_type => 'product' left a gap: custom roles with
				// edit_products but not manage_woocommerce could reach the edit
				// screen via direct URL even though the menu wasn't shown to them.
				'capability_type'   => 'wcpp_personalisation',
				'capabilities'      => array(
					'edit_post'              => 'manage_woocommerce',
					'read_post'              => 'manage_woocommerce',
					'delete_post'            => 'manage_woocommerce',
					'edit_posts'             => 'manage_woocommerce',
					'edit_others_posts'      => 'manage_woocommerce',
					'publish_posts'          => 'manage_woocommerce',
					'read_private_posts'     => 'manage_woocommerce',
					'delete_posts'           => 'manage_woocommerce',
					'delete_private_posts'   => 'manage_woocommerce',
					'delete_published_posts' => 'manage_woocommerce',
					'delete_others_posts'    => 'manage_woocommerce',
					'edit_private_posts'     => 'manage_woocommerce',
					'edit_published_posts'   => 'manage_woocommerce',
					'create_posts'           => 'manage_woocommerce',
				),
				'map_meta_cap'      => false,
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

		// Pricing — sidebar.
		add_meta_box(
			'wcpp_pricing',
			__( 'Pricing', 'wcpp' ),
			array( __CLASS__, 'render_pricing_box' ),
			'wcpp_personalisation',
			'side',
			'high'
		);

		// Button text override — sidebar.
		add_meta_box(
			'wcpp_button_override',
			__( 'Button Text (optional)', 'wcpp' ),
			array( __CLASS__, 'render_button_box' ),
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

		wp_localize_script(
			'wcpp-admin',
			'wcppAdmin',
			array(
				'mediaTitle'           => __( 'Select Image', 'wcpp' ),
				'mediaButton'          => __( 'Use This Image', 'wcpp' ),
				'changeImage'          => __( 'Change Image', 'wcpp' ),
				'addImage'             => __( 'Add Image', 'wcpp' ),
				'imageShort'           => __( 'Image', 'wcpp' ),
				'confirmDelete'        => __( 'Are you sure you want to delete this?', 'wcpp' ),
				'choicePlaceholder'    => __( 'Choice name, e.g. Gold', 'wcpp' ),
				'placementPlaceholder' => __( 'Placement name, e.g. Front', 'wcpp' ),
				'stepPlaceholder'      => __( 'Step name, e.g. Colour', 'wcpp' ),
				'stepDescPlaceholder'  => __( 'Step description (optional, shown below heading)', 'wcpp' ),
				'stepLabel'            => __( 'Step', 'wcpp' ),
				'duplicate'            => __( 'Duplicate', 'wcpp' ),
				'addStep'              => __( 'Add Step', 'wcpp' ),
				'addChoice'            => __( 'Add Choice', 'wcpp' ),
				'typeChoices'          => __( 'Choices', 'wcpp' ),
				'typeColor'            => __( 'Colours', 'wcpp' ),
				'typeText'             => __( 'Text input', 'wcpp' ),
				'tPlaceholder'         => __( 'Placeholder', 'wcpp' ),
				'tMax'                 => __( 'Max characters', 'wcpp' ),
				'tPrice'               => __( 'Price', 'wcpp' ),
			)
		);
	}

	// ─── BUILDER: PLACEMENTS → STEPS → CHOICES ───────────────────────────

	/**
	 * Render the builder meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_builder( $post ) {
		wp_nonce_field( 'wcpp_save_all_meta', 'wcpp_meta_nonce' );

		$placements = self::get_placements_for_edit( $post->ID );
		?>
		<div id="wcpp-builder">
			<p class="wcpp-builder-intro">
				<?php esc_html_e( 'A Placement (e.g. Front, Back) is something the customer personalises. Each Placement has its own Steps (Font, Colour…), and each Step has Choices. A customer can pick each Placement once per order.', 'wcpp' ); ?>
			</p>
			<div id="wcpp-placements-container">
				<?php foreach ( $placements as $placement ) : ?>
					<?php self::render_placement_block( $placement ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" id="wcpp-add-placement" class="button button-primary wcpp-add-placement-btn">
				&#43; <?php esc_html_e( 'Add Placement', 'wcpp' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Read placements for the edit screen, falling back to legacy options.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private static function get_placements_for_edit( $post_id ) {
		$placements = get_post_meta( $post_id, '_wcpp_placements', true );
		if ( is_array( $placements ) && ! empty( $placements ) ) {
			return $placements;
		}

		// Legacy: wrap old flat options into a single placement.
		$options = get_post_meta( $post_id, '_wcpp_options', true );
		if ( is_array( $options ) && ! empty( $options ) ) {
			return array(
				array(
					'id'    => 'pl_' . uniqid(),
					'name'  => __( 'Personalisation', 'wcpp' ),
					'steps' => $options,
				),
			);
		}

		return array();
	}

	/**
	 * Render a placement block (parent).
	 *
	 * @param array $placement Placement data.
	 * @return void
	 */
	public static function render_placement_block( $placement ) {
		$plid  = isset( $placement['id'] ) ? $placement['id'] : 'pl_' . uniqid();
		$name  = isset( $placement['name'] ) ? $placement['name'] : '';
		$img   = isset( $placement['image_id'] ) ? $placement['image_id'] : '';
		$url   = isset( $placement['image_url'] ) ? $placement['image_url'] : '';
		$steps = isset( $placement['steps'] ) && is_array( $placement['steps'] ) ? $placement['steps'] : array();
		$base  = 'wcpp_placements[' . $plid . ']';
		?>
		<div class="wcpp-placement-block" data-plid="<?php echo esc_attr( $plid ); ?>">
			<div class="wcpp-placement-header">
				<div class="wcpp-placement-image">
					<div class="wcpp-image-preview" <?php echo $url ? '' : 'style="display:none;"'; ?>>
						<img src="<?php echo esc_url( $url ); ?>" alt="" />
						<button type="button" class="wcpp-remove-image" title="<?php esc_attr_e( 'Remove', 'wcpp' ); ?>">&#10005;</button>
					</div>
					<button type="button" class="button wcpp-select-image wcpp-placement-select-image" title="<?php esc_attr_e( 'Placement image', 'wcpp' ); ?>">
						<?php echo $url ? esc_html__( 'Change', 'wcpp' ) : esc_html__( 'Image', 'wcpp' ); ?>
					</button>
					<input type="hidden" name="<?php echo esc_attr( $base ); ?>[image_id]" class="wcpp-image-id" value="<?php echo esc_attr( $img ); ?>" />
					<input type="hidden" name="<?php echo esc_attr( $base ); ?>[image_url]" class="wcpp-image-url" value="<?php echo esc_attr( $url ); ?>" />
				</div>
				<input type="hidden" name="<?php echo esc_attr( $base ); ?>[id]" value="<?php echo esc_attr( $plid ); ?>" />
				<input type="text" name="<?php echo esc_attr( $base ); ?>[name]"
					value="<?php echo esc_attr( $name ); ?>"
					placeholder="<?php esc_attr_e( 'Placement name, e.g. Front', 'wcpp' ); ?>"
					class="wcpp-placement-name-input" />
				<button type="button" class="button wcpp-duplicate-placement" title="<?php esc_attr_e( 'Duplicate this placement', 'wcpp' ); ?>">
					<span class="dashicons dashicons-admin-page"></span> <?php esc_html_e( 'Duplicate', 'wcpp' ); ?>
				</button>
				<button type="button" class="button wcpp-delete-placement" title="<?php esc_attr_e( 'Delete placement', 'wcpp' ); ?>">
					<span class="dashicons dashicons-trash"></span>
				</button>
				<button type="button" class="button wcpp-toggle-placement" title="<?php esc_attr_e( 'Collapse / Expand', 'wcpp' ); ?>">
					<span class="dashicons dashicons-arrow-up-alt2"></span>
				</button>
			</div>

			<div class="wcpp-steps-container">
				<?php foreach ( $steps as $step ) : ?>
					<?php self::render_step_block( $plid, $step ); ?>
				<?php endforeach; ?>
			</div>

			<button type="button" class="button wcpp-add-step-btn">
				&#43; <?php esc_html_e( 'Add Step', 'wcpp' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render a step block (child of a placement).
	 *
	 * @param string $plid Placement ID.
	 * @param array  $step Step data.
	 * @return void
	 */
	public static function render_step_block( $plid, $step ) {
		$stid         = isset( $step['id'] ) ? $step['id'] : 'st_' . uniqid();
		$name         = isset( $step['name'] ) ? $step['name'] : '';
		$desc         = isset( $step['description'] ) ? $step['description'] : '';
		$type         = isset( $step['type'] ) ? $step['type'] : 'choice';
		$choices      = isset( $step['choices'] ) && is_array( $step['choices'] ) ? $step['choices'] : array();
		$ph           = isset( $step['placeholder'] ) ? $step['placeholder'] : '';
		$maxc         = isset( $step['max_chars'] ) ? (int) $step['max_chars'] : 20;
		$price        = isset( $step['price'] ) ? $step['price'] : '0.00';
		$base         = 'wcpp_placements[' . $plid . '][steps][' . $stid . ']';
		$is_text      = ( 'text' === $type );
		?>
		<div class="wcpp-option-block wcpp-step-block" data-stid="<?php echo esc_attr( $stid ); ?>" data-type="<?php echo esc_attr( $type ); ?>">
			<div class="wcpp-option-header">
				<span class="wcpp-option-label"><?php esc_html_e( 'Step', 'wcpp' ); ?></span>
				<input type="hidden" name="<?php echo esc_attr( $base ); ?>[id]" value="<?php echo esc_attr( $stid ); ?>" />
				<input type="text" name="<?php echo esc_attr( $base ); ?>[name]"
					value="<?php echo esc_attr( $name ); ?>"
					placeholder="<?php esc_attr_e( 'Step name, e.g. Colour', 'wcpp' ); ?>"
					class="wcpp-option-name-input" />
				<select name="<?php echo esc_attr( $base ); ?>[type]" class="wcpp-step-type">
					<option value="choice" <?php selected( $type, 'choice' ); ?>><?php esc_html_e( 'Choices (image)', 'wcpp' ); ?></option>
					<option value="color" <?php selected( $type, 'color' ); ?>><?php esc_html_e( 'Colours', 'wcpp' ); ?></option>
					<option value="text" <?php selected( $type, 'text' ); ?>><?php esc_html_e( 'Text input', 'wcpp' ); ?></option>
				</select>
				<button type="button" class="button wcpp-delete-option" title="<?php esc_attr_e( 'Delete step', 'wcpp' ); ?>">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>

			<!-- Step description (optional, shows below heading in the panel) -->
			<div class="wcpp-step-desc-row">
				<input type="text" name="<?php echo esc_attr( $base ); ?>[description]"
					value="<?php echo esc_attr( $desc ); ?>"
					placeholder="<?php esc_attr_e( 'Step description (optional, shown below heading)', 'wcpp' ); ?>"
					class="wcpp-step-desc-input" />
			</div>

			<!-- Choice mode -->
			<div class="wcpp-step-choices" <?php echo $is_text ? 'style="display:none;"' : ''; ?>>
				<div class="wcpp-choices-list">
					<?php foreach ( $choices as $choice ) : ?>
						<?php self::render_choice_row( $base, $choice ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button wcpp-add-choice-btn">
					&#43; <?php esc_html_e( 'Add Choice', 'wcpp' ); ?>
				</button>
			</div>

			<!-- Text mode -->
			<div class="wcpp-step-text" <?php echo $is_text ? '' : 'style="display:none;"'; ?>>
				<div class="wcpp-text-settings">
					<label>
						<?php esc_html_e( 'Placeholder', 'wcpp' ); ?>
						<input type="text" name="<?php echo esc_attr( $base ); ?>[placeholder]"
							value="<?php echo esc_attr( $ph ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. Enter initials', 'wcpp' ); ?>" />
					</label>
					<label>
						<?php esc_html_e( 'Max characters', 'wcpp' ); ?>
						<input type="number" name="<?php echo esc_attr( $base ); ?>[max_chars]"
							value="<?php echo esc_attr( $maxc ); ?>" min="1" max="200" style="width:80px;" />
					</label>
					<label>
						<?php esc_html_e( 'Price', 'wcpp' ); ?> (<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>)
						<input type="number" name="<?php echo esc_attr( $base ); ?>[price]"
							value="<?php echo esc_attr( $price ); ?>" step="0.01" min="0" style="width:90px;" />
					</label>
				</div>

				<p class="description"><?php esc_html_e( 'The customer types their own text (monogram, name, initials). Charged once if a price is set.', 'wcpp' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single choice row.
	 *
	 * @param string $step_base Field-name base for the parent step.
	 * @param array  $choice    Choice data.
	 * @return void
	 */
	public static function render_choice_row( $step_base, $choice ) {
		$ch_id    = isset( $choice['id'] ) ? $choice['id'] : 'ch_' . uniqid();
		$ch_name  = isset( $choice['name'] ) ? $choice['name'] : '';
		$ch_img   = isset( $choice['image_id'] ) ? $choice['image_id'] : '';
		$ch_url   = isset( $choice['image_url'] ) ? $choice['image_url'] : '';
		$ch_color = isset( $choice['color'] ) && $choice['color'] ? $choice['color'] : '#000000';
		$ch_price = isset( $choice['price'] ) ? $choice['price'] : '0.00';
		$prefix   = $step_base . '[choices][' . $ch_id . ']';
		?>
		<div class="wcpp-choice-row">
			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[id]" value="<?php echo esc_attr( $ch_id ); ?>" />

			<!-- Image media (Choices type) -->
			<div class="wcpp-choice-image-wrap wcpp-choice-media--image">
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

			<!-- Colour media (Colours type) -->
			<div class="wcpp-choice-color-wrap wcpp-choice-media--color">
				<input type="color" name="<?php echo esc_attr( $prefix ); ?>[color]"
					value="<?php echo esc_attr( $ch_color ); ?>" class="wcpp-choice-color" />
			</div>
			<div class="wcpp-choice-field">
				<label><?php esc_html_e( 'Name', 'wcpp' ); ?></label>
				<input type="text" name="<?php echo esc_attr( $prefix ); ?>[name]"
					value="<?php echo esc_attr( $ch_name ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. Gold', 'wcpp' ); ?>"
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

	// ─── PRICING ─────────────────────────────────────────────────────────

	/**
	 * Render the pricing meta box — flat fee + fee-type selector.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_pricing_box( $post ) {
		$set_price      = get_post_meta( $post->ID, '_wcpp_set_price', true );
		$set_price      = ( '' === $set_price ) ? '' : number_format( (float) $set_price, 2, '.', '' );
		$set_price_type = get_post_meta( $post->ID, '_wcpp_set_price_type', true );
		if ( ! in_array( $set_price_type, array( 'line', 'unit' ), true ) ) {
			$set_price_type = 'line';
		}
		$symbol = get_woocommerce_currency_symbol();
		?>
		<div class="wcpp-pricing-box">
			<p>
				<label for="wcpp_set_price"><strong><?php esc_html_e( 'Flat price for this set', 'wcpp' ); ?></strong></label><br />
				<span style="font-size:14px;"><?php echo esc_html( $symbol ); ?></span>
				<input type="number" id="wcpp_set_price" name="wcpp_set_price"
					value="<?php echo esc_attr( $set_price ); ?>"
					step="0.01" min="0" placeholder="0.00" style="width:100px;" />
			</p>
			<p style="margin-bottom:4px;"><strong><?php esc_html_e( 'Fee type', 'wcpp' ); ?></strong></p>
			<p style="margin:0 0 6px;">
				<label>
					<input type="radio" name="wcpp_set_price_type" value="line"
						<?php checked( $set_price_type, 'line' ); ?> />
					<strong><?php esc_html_e( 'One-time fee', 'wcpp' ); ?></strong>
				</label><br />
				<span class="description" style="margin-left:20px;">
					<?php esc_html_e( 'Charged once per personalisation, regardless of how many units are ordered.', 'wcpp' ); ?>
				</span>
			</p>
			<p style="margin:0 0 8px;">
				<label>
					<input type="radio" name="wcpp_set_price_type" value="unit"
						<?php checked( $set_price_type, 'unit' ); ?> />
					<strong><?php esc_html_e( 'Per-item fee', 'wcpp' ); ?></strong>
				</label><br />
				<span class="description" style="margin-left:20px;">
					<?php esc_html_e( 'Charged for every unit ordered (e.g. qty 3 = 3× fee).', 'wcpp' ); ?>
				</span>
			</p>
			<p class="description">
				<?php esc_html_e( 'Added on top of any per-choice prices. Leave 0 or blank for no flat fee.', 'wcpp' ); ?>
			</p>
		</div>
		<?php
	}

	// ─── DESIGN SETTINGS ─────────────────────────────────────────────────

	/**
	 * Render the button-text override meta box.
	 * All visual design is global (Panel Settings) — this only overrides
	 * the trigger button label for this specific set.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_button_box( $post ) {
		$override = get_post_meta( $post->ID, '_wcpp_button_text', true );
		$global   = WCPP_Settings_Store::get_design();
		?>
		<div class="wcpp-button-box">
			<p>
				<input type="text" name="wcpp_button_text"
					value="<?php echo esc_attr( $override ); ?>"
					placeholder="<?php echo esc_attr( $global['btn_text'] ); ?>"
					style="width:100%;" />
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: global default button text */
					esc_html__( 'Leave blank to use the global default: "%s". All colours, fonts and sizes are set globally in Panel Settings.', 'wcpp' ),
					esc_html( $global['btn_text'] )
				);
				?>
			</p>
		</div>
		<?php
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
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpp-settings' ) ); ?>"><?php esc_html_e( 'Edit Panel Settings (design) →', 'wcpp' ); ?></a>
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
		// CPT uses capability_type => 'product', so edit_post maps to edit_product.
		// Also require manage_woocommerce explicitly: a user with edit_products but
		// NOT manage_woocommerce should not be able to edit global personalisation
		// sets (even if they can reach the URL directly without the menu).
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if (
			! isset( $_POST['wcpp_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['wcpp_meta_nonce'] ), 'wcpp_save_all_meta' )
		) {
			return;
		}

		// Save placements (parent → steps → choices).
		self::save_placements( $post_id );

		// Save category assignment.
		self::save_categories( $post_id );

		// Save button-text override.
		self::save_button_text( $post_id );

		// Save flat set price.
		self::save_set_price( $post_id );
	}

	/**
	 * Save the flat set price and fee type.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function save_set_price( $post_id ) {
		// Price.
		$raw = isset( $_POST['wcpp_set_price'] ) ? wp_unslash( $_POST['wcpp_set_price'] ) : '';
		if ( '' === trim( $raw ) ) {
			delete_post_meta( $post_id, '_wcpp_set_price' );
		} else {
			update_post_meta( $post_id, '_wcpp_set_price', number_format( max( 0.0, (float) $raw ), 2, '.', '' ) );
		}

		// Fee type: 'line' = one-time per cart line; 'unit' = per item ordered.
		$fee_type = isset( $_POST['wcpp_set_price_type'] ) ? sanitize_key( $_POST['wcpp_set_price_type'] ) : 'line';
		if ( ! in_array( $fee_type, array( 'line', 'unit' ), true ) ) {
			$fee_type = 'line';
		}
		update_post_meta( $post_id, '_wcpp_set_price_type', $fee_type );
	}

	/**
	 * Save the placements builder data (placement → steps → choices).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function save_placements( $post_id ) {
		if ( empty( $_POST['wcpp_placements'] ) || ! is_array( $_POST['wcpp_placements'] ) ) {
			update_post_meta( $post_id, '_wcpp_placements', array() );
			delete_post_meta( $post_id, '_wcpp_options' ); // Drop legacy data once migrated.
			return;
		}

		$clean = array();

		foreach ( $_POST['wcpp_placements'] as $placement ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$pl_name = sanitize_text_field( wp_unslash( $placement['name'] ?? '' ) );
			if ( '' === $pl_name ) {
				continue;
			}

			$clean_pl = array(
				'id'        => sanitize_text_field( wp_unslash( $placement['id'] ?? 'pl_' . uniqid() ) ),
				'name'      => $pl_name,
				'image_id'  => absint( $placement['image_id'] ?? 0 ),
				'image_url' => esc_url_raw( wp_unslash( $placement['image_url'] ?? '' ) ),
				'steps'     => array(),
			);

			if ( ! empty( $placement['steps'] ) && is_array( $placement['steps'] ) ) {
				foreach ( $placement['steps'] as $step ) {
					$st_name = sanitize_text_field( wp_unslash( $step['name'] ?? '' ) );
					if ( '' === $st_name ) {
						continue;
					}
					$st_type    = isset( $step['type'] ) && in_array( $step['type'], array( 'text', 'color' ), true ) ? $step['type'] : 'choice';
					$clean_step = array(
						'id'          => sanitize_text_field( wp_unslash( $step['id'] ?? 'st_' . uniqid() ) ),
						'name'        => $st_name,
						'description' => sanitize_text_field( wp_unslash( $step['description'] ?? '' ) ),
						'type'        => $st_type,
						'choices'     => array(),
					);

					if ( 'text' === $st_type ) {
						$clean_step['placeholder'] = sanitize_text_field( wp_unslash( $step['placeholder'] ?? '' ) );
						$clean_step['max_chars']   = max( 1, min( 200, (int) ( $step['max_chars'] ?? 20 ) ) );
						$clean_step['price']       = number_format( max( 0.0, (float) ( $step['price'] ?? 0 ) ), 2, '.', '' );
					}

					if ( in_array( $st_type, array( 'choice', 'color' ), true ) && ! empty( $step['choices'] ) && is_array( $step['choices'] ) ) {
						foreach ( $step['choices'] as $ch ) {
							$ch_name = sanitize_text_field( wp_unslash( $ch['name'] ?? '' ) );
							if ( '' === $ch_name ) {
								continue;
							}
							$clean_step['choices'][] = array(
								'id'        => sanitize_text_field( wp_unslash( $ch['id'] ?? 'ch_' . uniqid() ) ),
								'name'      => $ch_name,
								'image_id'  => absint( $ch['image_id'] ?? 0 ),
								'image_url' => esc_url_raw( wp_unslash( $ch['image_url'] ?? '' ) ),
								'color'     => sanitize_hex_color( $ch['color'] ?? '' ) ?: '',
								'price'     => number_format( max( 0.0, (float) ( $ch['price'] ?? 0 ) ), 2, '.', '' ),
							);
						}
					}
					$clean_pl['steps'][] = $clean_step;
				}
			}

			$clean[] = $clean_pl;
		}

		update_post_meta( $post_id, '_wcpp_placements', $clean );
		delete_post_meta( $post_id, '_wcpp_options' ); // Drop legacy data once migrated.
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
	 * Save the per-set button-text override.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function save_button_text( $post_id ) {
		$text = isset( $_POST['wcpp_button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['wcpp_button_text'] ) ) : '';
		if ( '' !== $text ) {
			update_post_meta( $post_id, '_wcpp_button_text', $text );
		} else {
			delete_post_meta( $post_id, '_wcpp_button_text' );
		}
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
			'options'    => __( 'Placements', 'wcpp' ),
			'choices'    => __( 'Total Steps', 'wcpp' ),
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
		$placements = self::get_placements_for_edit( $post_id );

		if ( 'options' === $column ) {
			echo esc_html( count( $placements ) );
		}

		if ( 'choices' === $column ) {
			$total = 0;
			foreach ( $placements as $pl ) {
				$total += count( $pl['steps'] ?? array() );
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
