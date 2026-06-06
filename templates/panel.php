<?php
/**
 * Template: Personalisation panel drawer.
 *
 * Override this in your theme: copy to yourtheme/wcpp/panel.php
 * JS reads IDs and classes in this file — keep them in sync with wcpp-panel.js.
 *
 * @package WC_Personalisation_Panel
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- Overlay backdrop -->
<div id="wcpp-overlay" class="wcpp-overlay" aria-hidden="true"></div>

<!-- Panel drawer -->
<div
	id="wcpp-panel"
	class="wcpp-panel"
	role="dialog"
	aria-modal="true"
	aria-label="<?php esc_attr_e( 'Personalisation Options', 'wcpp' ); ?>"
	aria-hidden="true"
>
	<!-- Panel header -->
	<div class="wcpp-panel__header">
		<button
			type="button"
			id="wcpp-back"
			class="wcpp-panel__back"
			aria-label="<?php esc_attr_e( 'Go back', 'wcpp' ); ?>"
			style="display:none;"
		>
			&#8592; <?php esc_html_e( 'Back', 'wcpp' ); ?>
		</button>

		<h2 id="wcpp-panel-title" class="wcpp-panel__title">
			<?php esc_html_e( 'Add Personalisation', 'wcpp' ); ?>
		</h2>

		<button
			type="button"
			id="wcpp-close"
			class="wcpp-panel__close"
			aria-label="<?php esc_attr_e( 'Close personalisation panel', 'wcpp' ); ?>"
		>
			&#10005;
		</button>
	</div>

	<!-- Progress bar -->
	<div class="wcpp-panel__progress" role="progressbar" aria-valuemin="0" aria-valuemax="6" aria-valuenow="0">
		<div id="wcpp-progress-bar" class="wcpp-panel__progress-fill" style="width:0%"></div>
	</div>

	<!-- Step content — JS renders each step into this container -->
	<div id="wcpp-step-content" class="wcpp-panel__content">
		<!-- Populated by wcpp-panel.js -->
	</div>

	<!-- Panel footer -->
	<div class="wcpp-panel__footer">
		<button
			type="button"
			id="wcpp-next"
			class="button wcpp-panel__next"
		>
			<?php esc_html_e( 'Next', 'wcpp' ); ?>
		</button>

		<button
			type="button"
			id="wcpp-add-to-bag"
			class="button button-primary wcpp-panel__add-to-bag"
			style="display:none;"
		>
			<?php esc_html_e( 'Add to Bag', 'wcpp' ); ?>
		</button>

		<div id="wcpp-price-preview" class="wcpp-panel__price-preview"></div>
	</div>

</div>
