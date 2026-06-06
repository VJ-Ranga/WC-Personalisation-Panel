<?php
/**
 * Personalisation CPT — registers the personalisation set custom post type
 * and renders the option/choice builder UI in the admin.
 *
 * Each "set" is a post. Options and choices are stored as post meta.
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
		add_action( 'init',                                        array( __CLASS__, 'register_cpt' ) );
		add_action( 'add_meta_boxes',                              array( __CLASS__, 'add_builder_meta_box' ) );
		add_action( 'save_post_wcpp_personalisation',              array( __CLASS__, 'save_options' ), 10, 2 );
		add_filter( 'manage_wcpp_personalisation_posts_columns',   array( __CLASS__, 'set_admin_columns' ) );
		add_action( 'manage_wcpp_personalisation_posts_custom_column', array( __CLASS__, 'render_admin_column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts',                       array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'post_updated_messages',                       array( __CLASS__, 'updated_messages' ) );
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
			'all_items'          => __( 'All Sets', 'wcpp' ),
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
	 * Register the options builder meta box.
	 *
	 * @return void
	 */
	public static function add_builder_meta_box() {
		add_meta_box(
			'wcpp_options_builder',
			__( 'Personalisation Options', 'wcpp' ),
			array( __CLASS__, 'render_builder' ),
			'wcpp_personalisation',
			'normal',
			'high'
		);

		add_meta_box(
			'wcpp_set_info',
			__( 'How to use this set', 'wcpp' ),
			array( __CLASS__, 'render_info_box' ),
			'wcpp_personalisation',
			'side',
			'default'
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

		// WP media library for image picker.
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
				'mediaTitle'    => __( 'Select Image', 'wcpp' ),
				'mediaButton'   => __( 'Use This Image', 'wcpp' ),
				'changeImage'   => __( 'Change Image', 'wcpp' ),
				'addImage'      => __( 'Add Image', 'wcpp' ),
				'confirmDelete' => __( 'Are you sure you want to delete this?', 'wcpp' ),
				'namePlaceholder'   => __( 'Option name, e.g. Location', 'wcpp' ),
				'choicePlaceholder' => __( 'Choice name, e.g. Front', 'wcpp' ),
				'addChoice'     => __( '+ Add Choice', 'wcpp' ),
			)
		);
	}

	/**
	 * Render the options builder meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_builder( $post ) {
		wp_nonce_field( 'wcpp_save_options', 'wcpp_options_nonce' );

		$options = get_post_meta( $post->ID, '_wcpp_options', true );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		?>
		<div id="wcpp-builder">

			<p class="wcpp-builder-intro">
				<?php esc_html_e( 'Build the personalisation steps for this set. Each Option is one step in the panel (e.g. Location, Font, Color). Each Choice is one selectable item within that step.', 'wcpp' ); ?>
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

		<script>
			var wcppOptionCount = <?php echo (int) count( $options ); ?>;
		</script>
		<?php
	}

	/**
	 * Render info box in the sidebar.
	 *
	 * @return void
	 */
	public static function render_info_box() {
		?>
		<p><?php esc_html_e( 'After saving this set, go to any product and assign this set using the "Personalisation" meta box on the product edit screen.', 'wcpp' ); ?></p>
		<p><?php esc_html_e( 'You can also assign it to a product category so all products in that category use it automatically.', 'wcpp' ); ?></p>
		<?php
	}

	/**
	 * Render a single option block (one step in the wizard).
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
				<span class="wcpp-option-drag dashicons dashicons-move" title="<?php esc_attr_e( 'Drag to reorder', 'wcpp' ); ?>"></span>
				<span class="wcpp-option-label"><?php esc_html_e( 'Step', 'wcpp' ); ?> <span class="wcpp-step-num"><?php echo esc_html( $opt_idx + 1 ); ?></span></span>

				<input type="hidden"
					name="wcpp_options[<?php echo esc_attr( $opt_idx ); ?>][id]"
					value="<?php echo esc_attr( $opt_id ); ?>" />

				<input type="text"
					name="wcpp_options[<?php echo esc_attr( $opt_idx ); ?>][name]"
					value="<?php echo esc_attr( $opt_name ); ?>"
					placeholder="<?php esc_attr_e( 'Option name, e.g. Location', 'wcpp' ); ?>"
					class="wcpp-option-name-input" />

				<button type="button" class="button wcpp-delete-option" title="<?php esc_attr_e( 'Delete this option', 'wcpp' ); ?>">
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
	 * Render a single choice row inside an option block.
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

			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[id]"
				value="<?php echo esc_attr( $ch_id ); ?>" />

			<!-- Image -->
			<div class="wcpp-choice-image-wrap">
				<div class="wcpp-image-preview" <?php echo $ch_url ? '' : 'style="display:none;"'; ?>>
					<img src="<?php echo esc_url( $ch_url ); ?>" alt="" />
					<button type="button" class="wcpp-remove-image" title="<?php esc_attr_e( 'Remove', 'wcpp' ); ?>">&#10005;</button>
				</div>
				<button type="button" class="button wcpp-select-image">
					<?php echo $ch_url ? esc_html__( 'Change Image', 'wcpp' ) : esc_html__( 'Add Image', 'wcpp' ); ?>
				</button>
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[image_id]"
					class="wcpp-image-id" value="<?php echo esc_attr( $ch_img ); ?>" />
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[image_url]"
					class="wcpp-image-url" value="<?php echo esc_attr( $ch_url ); ?>" />
			</div>

			<!-- Name -->
			<div class="wcpp-choice-field">
				<label><?php esc_html_e( 'Name', 'wcpp' ); ?></label>
				<input type="text"
					name="<?php echo esc_attr( $prefix ); ?>[name]"
					value="<?php echo esc_attr( $ch_name ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. Front', 'wcpp' ); ?>"
					class="wcpp-choice-name" />
			</div>

			<!-- Price -->
			<div class="wcpp-choice-field wcpp-choice-field--price">
				<label><?php esc_html_e( 'Price', 'wcpp' ); ?> (<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>)</label>
				<input type="number"
					name="<?php echo esc_attr( $prefix ); ?>[price]"
					value="<?php echo esc_attr( $ch_price ); ?>"
					step="0.01" min="0"
					placeholder="0.00"
					class="wcpp-choice-price" />
			</div>

			<!-- Delete -->
			<button type="button" class="button wcpp-delete-choice" title="<?php esc_attr_e( 'Delete choice', 'wcpp' ); ?>">
				<span class="dashicons dashicons-trash"></span>
			</button>

		</div>
		<?php
	}

	/**
	 * Save options when the post is saved.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_options( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if (
			! isset( $_POST['wcpp_options_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['wcpp_options_nonce'] ), 'wcpp_save_options' )
		) {
			return;
		}

		if ( empty( $_POST['wcpp_options'] ) || ! is_array( $_POST['wcpp_options'] ) ) {
			update_post_meta( $post_id, '_wcpp_options', array() );
			return;
		}

		$clean = array();

		foreach ( $_POST['wcpp_options'] as $opt ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
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
	 * Custom admin columns for the CPT list screen.
	 *
	 * @param array $columns Default columns.
	 * @return array
	 */
	public static function set_admin_columns( $columns ) {
		return array(
			'cb'      => $columns['cb'],
			'title'   => __( 'Set Name', 'wcpp' ),
			'options' => __( 'Options (Steps)', 'wcpp' ),
			'choices' => __( 'Total Choices', 'wcpp' ),
			'date'    => __( 'Last Updated', 'wcpp' ),
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
	}

	/**
	 * Customise post updated messages.
	 *
	 * @param array $messages Default messages.
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
