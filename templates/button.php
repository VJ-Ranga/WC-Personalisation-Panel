<?php
/**
 * Template: trigger button. Override: yourtheme/wcpp/button.php
 *
 * Vars: $button_text, $btn_style ('outline'|'filled'|'text'), $full_width (bool), $config.
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$classes   = array( 'wcpp-button', 'wcpp-button--' . esc_attr( $btn_style ) );
if ( ! empty( $full_width ) ) {
	$classes[] = 'wcpp-button--full';
}
?>
<button
	type="button"
	id="wcpp-open-panel"
	class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	data-product-id="<?php echo esc_attr( get_the_ID() ); ?>"
	aria-haspopup="dialog"
	aria-expanded="false"
	aria-controls="wcpp-panel"
>
	<?php echo esc_html( $button_text ); ?>
</button>
