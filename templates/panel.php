<?php
/**
 * Template: panel drawer. Override: yourtheme/wcpp/panel.php
 * JS reads IDs/classes here — keep in sync with wcpp-panel.js.
 *
 * Vars: $design (array), $config (array).
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$slide_class    = 'wcpp-from-' . ( 'left' === $design['slide_from'] ? 'left' : 'right' );
$progress_style = $design['progress_show'] ? $design['progress_style'] : 'none';
$header_title   = $design['header_title'];
?>
<div id="wcpp-overlay" class="wcpp-overlay" aria-hidden="true"></div>

<div
	id="wcpp-panel"
	class="wcpp-panel <?php echo esc_attr( $slide_class ); ?>"
	role="dialog"
	aria-modal="true"
	aria-label="<?php esc_attr_e( 'Personalisation Options', 'wcpp' ); ?>"
	aria-hidden="true"
	data-progress-style="<?php echo esc_attr( $progress_style ); ?>"
>
	<div class="wcpp-panel__header">
		<button type="button" id="wcpp-back" class="wcpp-panel__back" aria-label="<?php esc_attr_e( 'Go back', 'wcpp' ); ?>" style="display:none;">
			&#8592;
		</button>
		<h2 id="wcpp-panel-title" class="wcpp-panel__title"><?php echo esc_html( $header_title ); ?></h2>
		<button type="button" id="wcpp-close" class="wcpp-panel__close" aria-label="<?php esc_attr_e( 'Close', 'wcpp' ); ?>">
			&#10005;
		</button>
	</div>

	<?php if ( 'none' !== $progress_style ) : ?>
		<div class="wcpp-panel__progress" id="wcpp-progress">
			<?php if ( 'bar' === $progress_style ) : ?>
				<div class="wcpp-progress-bar"><div class="wcpp-progress-bar__fill" id="wcpp-progress-fill"></div></div>
			<?php elseif ( 'dots' === $progress_style ) : ?>
				<div class="wcpp-progress-dots" id="wcpp-progress-dots"></div>
			<?php else : ?>
				<div class="wcpp-progress-text" id="wcpp-progress-text"></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div id="wcpp-step-content" class="wcpp-panel__content"></div>

	<div class="wcpp-panel__footer">
		<button type="button" id="wcpp-next" class="wcpp-panel__next"><?php echo esc_html( $design['next_text'] ); ?></button>
		<button type="button" id="wcpp-add-to-bag" class="wcpp-panel__add-to-bag" style="display:none;"><?php echo esc_html( $design['addbag_text'] ); ?></button>
	</div>
</div>
