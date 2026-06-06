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

		// All keys are underscore-prefixed = HIDDEN meta. WooCommerce will not
		// auto-render them; our display_in_order() renders a clean formatted block.

		if ( ! empty( $data['set_name'] ) ) {
			$item->add_meta_data( '_wcpp_set_name', sanitize_text_field( $data['set_name'] ), true );
		}

		if ( ! empty( $data['selections'] ) && is_array( $data['selections'] ) ) {
			$item->add_meta_data( '_wcpp_selections', wp_json_encode( $data['selections'] ), true );
		}

		if ( isset( $data['total_price'] ) ) {
			$item->add_meta_data( '_wcpp_total_price', (float) $data['total_price'], true );
		}

		if ( ! empty( $data['non_returnable'] ) ) {
			$item->add_meta_data( '_wcpp_non_returnable', '1', true );
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
		$json = $item->get_meta( '_wcpp_selections' );
		if ( empty( $json ) ) {
			return;
		}

		$selections = json_decode( $json, true );
		if ( ! is_array( $selections ) || empty( $selections ) ) {
			return;
		}

		$non_returnable = $item->get_meta( '_wcpp_non_returnable' );
		$is_email       = did_action( 'woocommerce_email_header' ) > 0;

		// Inline styles for emails (no external CSS); class hooks for on-site.
		$wrap_style = $is_email
			? 'style="margin-top:10px;padding:12px 14px;background:#faf9f7;border-left:3px solid #b8956a;font-size:13px;"'
			: '';

		echo '<div class="wcpp-order-meta" ' . $wrap_style . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<span class="wcpp-order-meta__title" style="display:block;font-weight:600;letter-spacing:.04em;text-transform:uppercase;font-size:11px;margin-bottom:6px;">' . esc_html__( 'Personalisation', 'wcpp' ) . '</span>';
		echo '<table class="wcpp-order-table" style="width:100%;border-collapse:collapse;" cellspacing="0" cellpadding="0">';

		foreach ( $selections as $sel ) {
			$price_html = '';
			if ( ! empty( $sel['choice_price'] ) && (float) $sel['choice_price'] > 0 ) {
				$price_html = ' <span style="color:#b8956a;">(+' . wp_kses_post( wc_price( $sel['choice_price'] ) ) . ')</span>';
			}
			echo '<tr>';
			echo '<td style="padding:3px 8px 3px 0;color:#888;white-space:nowrap;vertical-align:top;">' . esc_html( $sel['option_name'] ) . '</td>';
			echo '<td style="padding:3px 0;font-weight:600;">' . esc_html( $sel['choice_name'] ) . $price_html . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</tr>';
		}

		echo '</table>';

		if ( $non_returnable ) {
			echo '<p class="wcpp-non-returnable" style="color:#b3261e;font-weight:600;font-size:12px;margin:8px 0 0;">&#9888; ' . esc_html__( 'Non-returnable — personalised item', 'wcpp' ) . '</p>';
		}

		echo '</div>';
	}
}
