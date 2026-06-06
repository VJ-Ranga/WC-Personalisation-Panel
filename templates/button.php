<?php
/**
 * Template: Add Personalisation button.
 * Override: yourtheme/wcpp/button.php
 *
 * Available variables:
 *   $button_text  (string) — button label from design settings
 *   $btn_style    (string) — 'outline' or 'filled'
 *   $config       (array)  — full set config
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$btn_class = 'button wcpp-button wcpp-button--' . esc_attr( $btn_style );
?>
<button
	type="button"
	id="wcpp-open-panel"
	class="<?php echo esc_attr( $btn_class ); ?>"
	data-product-id="<?php echo esc_attr( get_the_ID() ); ?>"
	aria-haspopup="dialog"
	aria-expanded="false"
	aria-controls="wcpp-panel"
>
	<?php echo esc_html( $button_text ); ?>
</button>
