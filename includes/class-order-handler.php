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

		// Customer-facing order page + emails.
		// WooCommerce passes 4 args: ( $item_id, $item, $order, $plain_text ).
		// We register 4 so display_meta_end() can skip HTML in plain-text emails.
		add_action( 'woocommerce_order_item_meta_end', array( __CLASS__, 'display_meta_end' ), 10, 4 );

		// Admin order editor: hide the raw _wcpp_* meta and render a clean block.
		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hide_admin_meta' ) );
		add_action( 'woocommerce_after_order_itemmeta',  array( __CLASS__, 'display_admin' ), 10, 3 );
	}

	/**
	 * Keys to hide from the admin order-item meta table.
	 *
	 * @param array $keys Hidden meta keys.
	 * @return array
	 */
	public static function hide_admin_meta( $keys ) {
		return array_merge(
			(array) $keys,
			array( '_wcpp_set_name', '_wcpp_placements', '_wcpp_set_fee', '_wcpp_total_price', '_wcpp_non_returnable' )
		);
	}

	/**
	 * Customer order page + emails. Hook: woocommerce_order_item_meta_end.
	 *
	 * @param int           $item_id    Item ID.
	 * @param WC_Order_Item $item       Order item.
	 * @param WC_Order      $order      Order.
	 * @param bool          $plain_text True when rendering a plain-text email.
	 * @return void
	 */
	public static function display_meta_end( $item_id, $item, $order, $plain_text = false ) {
		if ( $plain_text ) {
			return;
		}
		if ( is_object( $item ) && method_exists( $item, 'get_meta' ) ) {
			self::render_personalisation( $item );
		}
	}

	/**
	 * Admin order editor. Hook: woocommerce_after_order_itemmeta.
	 *
	 * @param int           $item_id Item ID.
	 * @param WC_Order_Item $item    Order item.
	 * @param WC_Product    $product Product (unused).
	 * @return void
	 */
	public static function display_admin( $item_id, $item, $product ) {
		if ( is_object( $item ) && method_exists( $item, 'get_meta' ) ) {
			self::render_personalisation( $item );
		}
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

		if ( ! empty( $data['placements'] ) && is_array( $data['placements'] ) ) {
			$item->add_meta_data( '_wcpp_placements', wp_json_encode( $data['placements'] ), true );
		}

		if ( isset( $data['set_fee'] ) && (float) $data['set_fee'] > 0 ) {
			$item->add_meta_data( '_wcpp_set_fee', (float) $data['set_fee'], true );
		}

		if ( isset( $data['total_price'] ) ) {
			$item->add_meta_data( '_wcpp_total_price', (float) $data['total_price'], true );
		}

		if ( ! empty( $data['non_returnable'] ) ) {
			$item->add_meta_data( '_wcpp_non_returnable', '1', true );
		}
	}

	/**
	 * Render the personalisation block for an order item (shared by customer,
	 * email, and admin). Reads the hidden _wcpp_* meta.
	 *
	 * @param WC_Order_Item $item Order item.
	 * @return void
	 */
	private static function render_personalisation( $item ) {
		$json = $item->get_meta( '_wcpp_placements' );
		if ( empty( $json ) ) {
			return;
		}

		$placements = json_decode( $json, true );
		if ( ! is_array( $placements ) || empty( $placements ) ) {
			return;
		}

		$non_returnable = $item->get_meta( '_wcpp_non_returnable' );

		// Always inline-styled: none of these contexts load the plugin CSS.
		$wrap_style = 'style="margin-top:8px;padding:10px 12px;background:#faf9f7;border-left:3px solid #b8956a;font-size:13px;"';

		echo '<div class="wcpp-order-meta" ' . $wrap_style . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<span class="wcpp-order-meta__title" style="display:block;font-weight:600;letter-spacing:.04em;text-transform:uppercase;font-size:11px;margin-bottom:6px;">' . esc_html__( 'Personalisation', 'wcpp' ) . '</span>';

		foreach ( $placements as $placement ) {
			echo '<div style="margin-bottom:8px;">';
			echo '<strong style="display:block;font-size:12px;text-transform:uppercase;letter-spacing:.04em;">' . esc_html( $placement['placement_name'] ?? '' ) . '</strong>';
			echo '<table class="wcpp-order-table" style="width:100%;border-collapse:collapse;" cellspacing="0" cellpadding="0">';
			foreach ( ( $placement['selections'] ?? array() ) as $sel ) {
				$val   = isset( $sel['value'] ) ? $sel['value'] : ( $sel['choice_name'] ?? '' );
				$price = isset( $sel['price'] ) ? (float) $sel['price'] : (float) ( $sel['choice_price'] ?? 0 );
				$price_html = '';
				if ( $price > 0 ) {
					$price_html = ' <span style="color:#b8956a;">(+' . wp_kses_post( wc_price( $price ) ) . ')</span>';
				}
				echo '<tr>';
				echo '<td style="padding:3px 8px 3px 0;color:#888;white-space:nowrap;vertical-align:top;">' . esc_html( $sel['step_name'] ?? '' ) . '</td>';
				echo '<td style="padding:3px 0;font-weight:600;">' . esc_html( $val ) . $price_html . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</tr>';
			}
			echo '</table>';
			echo '</div>';
		}

		$set_fee = (float) $item->get_meta( '_wcpp_set_fee' );
		if ( $set_fee > 0 ) {
			echo '<p style="margin:6px 0 0;font-size:13px;"><span style="color:#888;">' . esc_html__( 'Personalisation fee:', 'wcpp' ) . '</span> <strong>' . wp_kses_post( wc_price( $set_fee ) ) . '</strong></p>';
		}

		if ( $non_returnable ) {
			echo '<p class="wcpp-non-returnable" style="color:#b3261e;font-weight:600;font-size:12px;margin:8px 0 0;">&#9888; ' . esc_html__( 'Non-returnable — personalised item', 'wcpp' ) . '</p>';
		}

		echo '</div>';
	}
}
