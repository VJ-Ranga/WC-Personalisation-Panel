<?php
/**
 * Template: Add Personalisation button.
 *
 * Override this in your theme: copy to yourtheme/wcpp/button.php
 *
 * @package WC_Personalisation_Panel
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<button
	type="button"
	id="wcpp-open-panel"
	class="button wcpp-button"
	data-product-id="<?php echo esc_attr( get_the_ID() ); ?>"
	aria-haspopup="dialog"
	aria-expanded="false"
	aria-controls="wcpp-panel"
>
	<?php esc_html_e( 'Add Personalisation', 'wcpp' ); ?>
</button>
