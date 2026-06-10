<?php
/**
 * Uninstall — runs when the plugin is deleted from WP Admin.
 *
 * Only deletes data if the admin opted in via
 * Panel Settings → Behaviour → "Remove data on uninstall".
 *
 * @package WC_Personalisation_Panel
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'wcpp_settings', array() );
$remove   = ! empty( $settings['behaviour']['remove_on_uninstall'] );

if ( ! $remove ) {
	return;
}

// 1. Delete all personalisation set posts and their meta.
$set_ids = get_posts(
	array(
		'post_type'      => 'wcpp_personalisation',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $set_ids as $set_id ) {
	wp_delete_post( $set_id, true );
}

// 2. Delete plugin options.
delete_option( 'wcpp_settings' );
delete_option( 'wcpp_global_settings' ); // legacy.

// 3. Delete per-product assignment meta.
delete_post_meta_by_key( '_wcpp_set_id' );

// 4. Always delete transients (plugin cache) — regardless of the remove_on_uninstall setting.
delete_transient( 'wcpp_gh_release_cache' );
delete_site_transient( 'update_plugins' ); // force a fresh WP update check.

// Note: order item meta (_wcpp_*) is intentionally KEPT — it is part of the
// permanent order record and must never be destroyed.
