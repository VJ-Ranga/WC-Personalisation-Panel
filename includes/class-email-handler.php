<?php
/**
 * Email Handler — personalisation details in WooCommerce order emails.
 *
 * Note: woocommerce_order_item_meta_end already fires inside emails, and the
 * Order Handler hooks it. To avoid double output we only add an emails-specific
 * hook here if needed. WooCommerce calls order_item_meta_end for both screen
 * and email, so the Order Handler covers emails too — this class stays minimal.
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
	 * Register hooks. Intentionally empty — order_item_meta_end (handled by
	 * WCPP_Order_Handler) already renders inside emails. Kept as a seam for
	 * future email-only formatting.
	 *
	 * @return void
	 */
	public static function init() {
		// Reserved for email-specific styling in a later phase.
	}
}
