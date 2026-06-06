<?php
/**
 * Email Handler — personalisation details in WooCommerce order emails.
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WCPP_Email_Handler
 */
class WCPP_Email_Handler {

	/**
	 * Register all hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_order_item_meta_end', array( __CLASS__, 'display_in_email' ), 10, 3 );
	}

	/**
	 * Output personalisation data in order emails.
	 * WooCommerce calls woocommerce_order_item_meta_end for both admin and customer emails.
	 *
	 * @param WC_Order_Item $item    Order item.
	 * @param string        $unused  Not used.
	 * @param WC_Order      $order   Order object.
	 * @return void
	 */
	public static function display_in_email( $item, $unused, $order ) {
		$location       = $item->get_meta( 'wcpp_location' );
		$non_returnable = $item->get_meta( 'wcpp_non_returnable' );

		if ( empty( $location ) ) {
			return;
		}

		echo '<br><strong>' . esc_html__( 'Personalisation', 'wcpp' ) . ':</strong> ';

		// TODO: Phase 2+ — display all fields.
		echo esc_html( $location );

		if ( $non_returnable ) {
			echo '<br><em>' . esc_html__( '⚠ This item is non-returnable once personalised.', 'wcpp' ) . '</em>';
		}
	}
}
