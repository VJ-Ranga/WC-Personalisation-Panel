<?php
/**
 * Product Meta — assigns a personalisation set to a product.
 * Simple dropdown: "Use personalisation set: [None / Shirts / Jackets ...]"
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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// Category-level assignment.
		add_action( 'product_cat_add_form_fields',  array( __CLASS__, 'category_add_field' ) );
		add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'category_edit_field' ) );
		add_action( 'created_product_cat',          array( __CLASS__, 'save_category_meta' ) );
		add_action( 'edited_product_cat',           array( __CLASS__, 'save_category_meta' ) );
	}

	/**
	 * Enqueue admin assets on product edit screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'wcpp-admin', WCPP_URL . 'assets/css/admin.css', array(), WCPP_VERSION );
	}

	/**
	 * Register meta box on product edit screen.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		add_meta_box(
			'wcpp_product_assign',
			__( 'Personalisation', 'wcpp' ),
			array( __CLASS__, 'render_meta_box' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Render the product meta box.
	 *
	 * @param WP_Post $post Product post.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'wcpp_product_meta', 'wcpp_product_nonce' );

		$assigned_set_id = (int) get_post_meta( $post->ID, '_wcpp_set_id', true );
		$all_sets        = WCPP_Settings_Store::get_all_sets();
		?>
		<div class="wcpp-product-assign">

			<?php if ( empty( $all_sets ) ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: link to create personalisation set */
						esc_html__( 'No personalisation sets found. %s first.', 'wcpp' ),
						'<a href="' . esc_url( admin_url( 'edit.php?post_type=wcpp_personalisation' ) ) . '">' . esc_html__( 'Create one', 'wcpp' ) . '</a>'
					);
					?>
				</p>
			<?php else : ?>

				<p>
					<label for="wcpp_set_id"><strong><?php esc_html_e( 'Personalisation Set', 'wcpp' ); ?></strong></label>
				</p>
				<select name="wcpp_set_id" id="wcpp_set_id" style="width:100%;">
					<option value="0"><?php esc_html_e( '— None —', 'wcpp' ); ?></option>
					<?php foreach ( $all_sets as $set ) : ?>
						<option value="<?php echo esc_attr( $set['id'] ); ?>" <?php selected( $assigned_set_id, $set['id'] ); ?>>
							<?php echo esc_html( $set['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<p class="description" style="margin-top:8px;">
					<?php esc_html_e( 'Choose which personalisation set to show on this product. Overrides any category-level assignment.', 'wcpp' ); ?>
				</p>

				<?php if ( $assigned_set_id ) : ?>
					<p style="margin-top:8px;">
						<a href="<?php echo esc_url( get_edit_post_link( $assigned_set_id ) ); ?>" target="_blank">
							<?php esc_html_e( 'Edit this set ↗', 'wcpp' ); ?>
						</a>
					</p>
				<?php endif; ?>

			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Save product meta.
	 *
	 * @param int     $post_id Product post ID.
	 * @param WP_Post $post    Product post.
	 * @return void
	 */
	public static function save_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}
		if (
			! isset( $_POST['wcpp_product_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['wcpp_product_nonce'] ), 'wcpp_product_meta' )
		) {
			return;
		}

		$set_id = isset( $_POST['wcpp_set_id'] ) ? absint( $_POST['wcpp_set_id'] ) : 0;

		if ( $set_id ) {
			update_post_meta( $post_id, '_wcpp_set_id', $set_id );
		} else {
			delete_post_meta( $post_id, '_wcpp_set_id' );
		}
	}

	/**
	 * Add personalisation set field on the Add Category screen.
	 *
	 * @return void
	 */
	public static function category_add_field() {
		$all_sets = WCPP_Settings_Store::get_all_sets();
		if ( empty( $all_sets ) ) {
			return;
		}
		?>
		<div class="form-field">
			<label for="wcpp_cat_set_id"><?php esc_html_e( 'Personalisation Set', 'wcpp' ); ?></label>
			<select name="wcpp_cat_set_id" id="wcpp_cat_set_id">
				<option value="0"><?php esc_html_e( '— None —', 'wcpp' ); ?></option>
				<?php foreach ( $all_sets as $set ) : ?>
					<option value="<?php echo esc_attr( $set['id'] ); ?>">
						<?php echo esc_html( $set['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'All products in this category will use this personalisation set (unless overridden on the product).', 'wcpp' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Add personalisation set field on the Edit Category screen.
	 *
	 * @param WP_Term $term Current term.
	 * @return void
	 */
	public static function category_edit_field( $term ) {
		$all_sets        = WCPP_Settings_Store::get_all_sets();
		$assigned_set_id = (int) get_term_meta( $term->term_id, '_wcpp_set_id', true );

		if ( empty( $all_sets ) ) {
			return;
		}
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="wcpp_cat_set_id"><?php esc_html_e( 'Personalisation Set', 'wcpp' ); ?></label>
			</th>
			<td>
				<select name="wcpp_cat_set_id" id="wcpp_cat_set_id">
					<option value="0"><?php esc_html_e( '— None —', 'wcpp' ); ?></option>
					<?php foreach ( $all_sets as $set ) : ?>
						<option value="<?php echo esc_attr( $set['id'] ); ?>" <?php selected( $assigned_set_id, $set['id'] ); ?>>
							<?php echo esc_html( $set['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'All products in this category will use this personalisation set (unless overridden on the product).', 'wcpp' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save category term meta.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public static function save_category_meta( $term_id ) {
		$set_id = isset( $_POST['wcpp_cat_set_id'] ) ? absint( $_POST['wcpp_cat_set_id'] ) : 0;
		if ( $set_id ) {
			update_term_meta( $term_id, '_wcpp_set_id', $set_id );
		} else {
			delete_term_meta( $term_id, '_wcpp_set_id' );
		}
	}
}
