<?php
/**
 * Product Meta — per-product override to assign a specific set.
 * Category assignment lives on the SET edit screen, not here.
 * This box only lets you force a specific set (or none) for one product.
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
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes',    array( __CLASS__, 'add_meta_box' ) );
		// Priority 20: run after WooCommerce's own product meta saves (priority 10-15).
		add_action( 'save_post_product', array( __CLASS__, 'save_meta' ), 20, 2 );
	}

	/**
	 * Register the product meta box.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		// Only expose the set-assignment box to users who can actually save it.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
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

		// What would apply automatically via category, if no override?
		$auto_set_id = WCPP_Settings_Store::get_set_id( $post->ID );
		?>
		<div class="wcpp-product-assign">
			<?php if ( empty( $all_sets ) ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: link */
						esc_html__( 'No personalisation sets yet. %s first.', 'wcpp' ),
						'<a href="' . esc_url( admin_url( 'edit.php?post_type=wcpp_personalisation' ) ) . '">' . esc_html__( 'Create one', 'wcpp' ) . '</a>'
					);
					?>
				</p>
			<?php else : ?>
				<p><label for="wcpp_set_id"><strong><?php esc_html_e( 'Set for this product', 'wcpp' ); ?></strong></label></p>
				<select name="wcpp_set_id" id="wcpp_set_id" style="width:100%;">
					<option value="0"><?php esc_html_e( '— Auto (use category rules) —', 'wcpp' ); ?></option>
					<?php foreach ( $all_sets as $set ) : ?>
						<option value="<?php echo esc_attr( $set['id'] ); ?>" <?php selected( $assigned_set_id, $set['id'] ); ?>>
							<?php echo esc_html( $set['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<?php if ( ! $assigned_set_id && $auto_set_id ) : ?>
					<p class="description" style="margin-top:8px;">
						<?php
						$auto = get_the_title( $auto_set_id );
						printf(
							/* translators: %s: set name */
							esc_html__( 'Currently using "%s" via category rules.', 'wcpp' ),
							esc_html( $auto )
						);
						?>
					</p>
				<?php elseif ( ! $assigned_set_id && ! $auto_set_id ) : ?>
					<p class="description" style="margin-top:8px;">
						<?php esc_html_e( 'No personalisation will show on this product unless a set applies.', 'wcpp' ); ?>
					</p>
				<?php endif; ?>

				<p class="description" style="margin-top:8px;">
					<?php esc_html_e( 'Choosing a set here overrides any category rule.', 'wcpp' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Save product meta.
	 *
	 * @param int     $post_id Product ID.
	 * @param WP_Post $post    Product post.
	 * @return void
	 */
	public static function save_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_product', $post_id ) ) {
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
}
