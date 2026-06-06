<?php
/**
 * Order Handler — persists personalisation to orders and displays it.
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
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'persist_to_order' ), 10, 4 );
		add_action( 'woocommerce_order_item_meta_end',             array( __CLASS__, 'display_in_order' ), 10, 3 );
	}

	/**
	 * Persist all personalisation selections to the order line item.
	 *
	 * @param WC_Order_Item_Product $item          Line item.
	 * @param string                $cart_item_key Cart key.
	 * @param array                 $values        Cart item data.
	 * @param WC_Order              $order         Order.
	 * @return void
	 */
	public static function persist_to_order( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['wcpp_data'] ) ) {
			return;
		}

		$data = $values['wcpp_data'];

		// Save set name.
		if ( ! empty( $data['set_name'] ) ) {
			$item->add_meta_data( 'wcpp_set_name', sanitize_text_field( $data['set_name'] ), true );
		}

		// Save each selection as its own meta.
		if ( ! empty( $data['selections'] ) && is_array( $data['selections'] ) ) {
			foreach ( $data['selections'] as $idx => $sel ) {
				$prefix = 'wcpp_sel_' . (int) $idx;
				$item->add_meta_data( $prefix . '_option', sanitize_text_field( $sel['option_name'] ), true );
				$item->add_meta_data( $prefix . '_choice', sanitize_text_field( $sel['choice_name'] ), true );
				$item->add_meta_data( $prefix . '_price',  (float) $sel['choice_price'], true );
			}
			// Save as JSON blob too — easier for reporting.
			$item->add_meta_data( 'wcpp_selections_json', wp_json_encode( $data['selections'] ), true );
		}

		// Total personalisation price.
		if ( isset( $data['total_price'] ) ) {
			$item->add_meta_data( 'wcpp_total_price', (float) $data['total_price'], true );
		}

		// Non-returnable flag.
		if ( ! empty( $data['non_returnable'] ) ) {
			$item->add_meta_data( 'wcpp_non_returnable', '1', true );
		}
	}

	/**
	 * Display personalisation in admin orders and customer order pages.
	 *
	 * @param WC_Order_Item $item     Order item.
	 * @param string        $cart_key Not used.
	 * @param WC_Order      $order    Order.
	 * @return void
	 */
	public static function display_in_order( $item, $cart_key, $order ) {
		$json = $item->get_meta( 'wcpp_selections_json' );
		if ( empty( $json ) ) {
			return;
		}

		$selections = json_decode( $json, true );
		if ( ! is_array( $selections ) ) {
			return;
		}

		$non_returnable = $item->get_meta( 'wcpp_non_returnable' );

		echo '<div class="wcpp-order-meta">';
		echo '<strong>' . esc_html__( 'Personalisation', 'wcpp' ) . '</strong>';
		echo '<table class="wcpp-order-table">';

		foreach ( $selections as $sel ) {
			$price_display = '';
			if ( ! empty( $sel['choice_price'] ) && (float) $sel['choice_price'] > 0 ) {
				$price_display = ' <small>(+' . wc_price( $sel['choice_price'] ) . ')</small>';
			}
			echo '<tr>';
			echo '<td><strong>' . esc_html( $sel['option_name'] ) . '</strong></td>';
			echo '<td>' . esc_html( $sel['choice_name'] ) . wp_kses_post( $price_display ) . '</td>';
			echo '</tr>';
		}

		echo '</table>';

		if ( $non_returnable ) {
			echo '<p class="wcpp-non-returnable">&#9888; ' . esc_html__( 'Non-returnable — personalised item', 'wcpp' ) . '</p>';
		}

		echo '</div>';
	}
}
