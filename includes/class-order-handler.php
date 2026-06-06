<?php
/**
 * Order Handler — all WooCommerce order and checkout hooks.
 *
 * Hooks owned:
 *   - woocommerce_checkout_create_order_line_item  (persist meta to order)
 *   - woocommerce_order_item_meta_end              (display in admin + customer order)
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCPP_Order_Handler
 */
class WCPP_Order_Handler {

	/**
	 * Register all hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'persist_to_order' ), 10, 4 );
		add_action( 'woocommerce_order_item_meta_end',             array( __CLASS__, 'display_in_order' ), 10, 3 );
	}

	/**
	 * Persist personalisation data to the order line item at checkout.
	 * This is the permanent record — must never be lost.
	 *
	 * @param WC_Order_Item_Product $item          Order line item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item data.
	 * @param WC_Order              $order         Order object.
	 * @return void
	 */
	public static function persist_to_order( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['wcpp_data'] ) ) {
			return;
		}

		$data = $values['wcpp_data'];

		// TODO: Phase 2+ — save all data keys as order item meta.
		if ( ! empty( $data['location'] ) ) {
			$item->add_meta_data( 'wcpp_location', sanitize_text_field( $data['location'] ), true );
		}

		// Non-returnable flag — persisted regardless of other data.
		if ( ! empty( $data['non_returnable'] ) ) {
			$item->add_meta_data( 'wcpp_non_returnable', '1', true );
		}
	}

	/**
	 * Display personalisation data in the admin order screen and customer order pages.
	 *
	 * @param WC_Order_Item $item     Order item.
	 * @param string        $cart_key Not used.
	 * @param WC_Order      $order    Order object.
	 * @return void
	 */
	public static function display_in_order( $item, $cart_key, $order ) {
		$location       = $item->get_meta( 'wcpp_location' );
		$non_returnable = $item->get_meta( 'wcpp_non_returnable' );

		if ( empty( $location ) ) {
			return;
		}

		echo '<div class="wcpp-order-meta">';

		echo '<strong>' . esc_html__( 'Personalisation', 'wcpp' ) . '</strong><br>';

		// TODO: Phase 2+ — display all meta fields.
		if ( $location ) {
			echo esc_html__( 'Location:', 'wcpp' ) . ' ' . esc_html( $location ) . '<br>';
		}

		if ( $non_returnable ) {
			echo '<span class="wcpp-non-returnable">⚠ ' . esc_html__( 'Non-returnable item', 'wcpp' ) . '</span>';
		}

		echo '</div>';
	}
}
