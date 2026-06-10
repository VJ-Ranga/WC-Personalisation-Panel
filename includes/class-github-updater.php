<?php
/**
 * GitHub Releases update checker for WC Personalisation Panel.
 *
 * Polls the GitHub Releases API every 12 hours and injects update info into
 * the WordPress plugin-update transient so the standard WP admin update flow
 * works (Plugins list "update available" badge + one-click update).
 *
 * No external libraries — uses WP HTTP API only.
 *
 * @package WC_Personalisation_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WCPP_GitHub_Updater
 *
 * Hooks:
 *   pre_set_site_transient_update_plugins — inject update object when GitHub
 *     has a newer tag than the installed WCPP_VERSION.
 *   plugins_api                           — populate "View details" popup.
 *   upgrader_source_selection             — rename GitHub's auto-zip folder to
 *     the correct plugin slug after extraction.
 *   admin_init                            — handle manual "force check" link.
 *   plugin_action_links_<plugin-file>     — add "Check for updates" action link.
 */
class WCPP_GitHub_Updater {

	/** GitHub repository in "owner/repo" format. */
	const GITHUB_REPO = 'VJ-Ranga/WC-Personalisation-Panel';

	/** Plugin slug (= the plugin directory name). */
	const PLUGIN_SLUG = 'wc-personalisation-panel';

	/** Plugin file path relative to wp-content/plugins/. */
	const PLUGIN_FILE = 'wc-personalisation-panel/wc-personalisation-panel.php';

	/** Transient key used to cache the GitHub API response (12 h). */
	const TRANSIENT = 'wcpp_gh_release_cache';

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	/**
	 * Register all hooks. Call once from the main plugin file.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update'    )        );
		add_filter( 'plugins_api',                           array( __CLASS__, 'plugin_info'      ), 10, 3 );
		add_filter( 'upgrader_source_selection',             array( __CLASS__, 'fix_source_dir'   ), 10, 4 );
		add_action( 'admin_init',                            array( __CLASS__, 'handle_force_check')        );

		// "Check for updates" link added at priority 20 so it appears after
		// the Settings / Sets links registered in wcpp_plugin_action_links().
		add_filter(
			'plugin_action_links_' . self::PLUGIN_FILE,
			array( __CLASS__, 'add_check_link' ),
			20
		);
	}

	// -------------------------------------------------------------------------
	// GitHub API
	// -------------------------------------------------------------------------

	/**
	 * Fetch the latest release from GitHub, cached in a transient.
	 *
	 * On a successful API call the decoded JSON object is cached for 12 hours.
	 * On failure an empty string is cached for 1 hour so we back off and do not
	 * hammer the API on every page load.
	 *
	 * @return object|null Decoded release object, or null on failure / no data.
	 */
	private static function get_latest_release() {
		$cached = get_transient( self::TRANSIENT );

		// Empty string = known-error sentinel (back-off period).
		if ( '' === $cached ) {
			return null;
		}

		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . esc_url( get_bloginfo( 'url' ) ),
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::TRANSIENT, '', HOUR_IN_SECONDS ); // 1-hour back-off
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $data->tag_name ) ) {
			set_transient( self::TRANSIENT, '', HOUR_IN_SECONDS );
			return null;
		}

		set_transient( self::TRANSIENT, $data, 12 * HOUR_IN_SECONDS );
		return $data;
	}

	/**
	 * Return the best download package URL for a release.
	 *
	 * Preference order:
	 *  1. A release asset whose filename contains the plugin slug and ends .zip
	 *     (a pre-built zip with the correct directory structure inside).
	 *  2. GitHub's auto-generated zipball (folder rename handled by fix_source_dir).
	 *
	 * @param  object $release GitHub release object.
	 * @return string Download URL.
	 */
	private static function package_url( $release ) {
		if ( ! empty( $release->assets ) && is_array( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if (
					! empty( $asset->name ) &&
					false !== strpos( $asset->name, self::PLUGIN_SLUG ) &&
					'zip' === strtolower( pathinfo( $asset->name, PATHINFO_EXTENSION ) ) &&
					! empty( $asset->browser_download_url )
				) {
					return $asset->browser_download_url;
				}
			}
		}

		// Fall back to GitHub's auto-generated source zip.
		return $release->zipball_url;
	}

	// -------------------------------------------------------------------------
	// WordPress update hooks
	// -------------------------------------------------------------------------

	/**
	 * Inject our plugin into WordPress's plugin-update transient when a newer
	 * GitHub release is available.
	 *
	 * @param  object $transient Value of site_transient('update_plugins').
	 * @return object
	 */
	public static function inject_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = self::get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version  = ltrim( $release->tag_name, 'vV' );
		$current_version = isset( $transient->checked[ self::PLUGIN_FILE ] )
			? $transient->checked[ self::PLUGIN_FILE ]
			: WCPP_VERSION;

		if ( version_compare( $remote_version, $current_version, '>' ) ) {
			$transient->response[ self::PLUGIN_FILE ] = (object) array(
				'id'           => 'w.org/plugins/' . self::PLUGIN_SLUG,
				'slug'         => self::PLUGIN_SLUG,
				'plugin'       => self::PLUGIN_FILE,
				'new_version'  => $remote_version,
				'url'          => 'https://github.com/' . self::GITHUB_REPO,
				'package'      => self::package_url( $release ),
				'icons'        => array(),
				'banners'      => array(),
				'banners_rtl'  => array(),
				'tested'       => get_bloginfo( 'version' ),
				'requires_php' => '7.4',
				'compatibility' => new stdClass(),
			);
		} else {
			// Remove any stale response entry from a previous check.
			unset( $transient->response[ self::PLUGIN_FILE ] );
		}

		return $transient;
	}

	/**
	 * Populate the "View details" thickbox popup in the Plugins list.
	 *
	 * @param  false|object|array $result  Default false.
	 * @param  string             $action  API action string.
	 * @param  object             $args    Request arguments (slug, etc.).
	 * @return false|object
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( empty( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
			return $result;
		}

		$release = self::get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$remote_version = ltrim( $release->tag_name, 'vV' );

		return (object) array(
			'name'          => 'WC Personalisation Panel',
			'slug'          => self::PLUGIN_SLUG,
			'version'       => $remote_version,
			'author'        => '<a href="https://github.com/VJ-Ranga">Cloudycode</a>',
			'author_profile' => 'https://github.com/VJ-Ranga',
			'homepage'      => 'https://github.com/' . self::GITHUB_REPO,
			'requires'      => '6.0',
			'tested'        => get_bloginfo( 'version' ),
			'requires_php'  => '7.4',
			'download_link' => self::package_url( $release ),
			'trunk'         => self::package_url( $release ),
			'last_updated'  => isset( $release->published_at ) ? $release->published_at : '',
			'sections'      => array(
				'description' => '<p>Adds a slide-in personalisation drawer to WooCommerce product pages. '
					. 'Customers choose placement, type, text, font and colour before adding to cart.</p>'
					. '<p><a href="https://github.com/' . esc_attr( self::GITHUB_REPO ) . '" target="_blank">View on GitHub</a></p>',
				'changelog'   => '<pre>' . esc_html( isset( $release->body ) ? $release->body : 'See GitHub releases for changelog.' ) . '</pre>',
			),
		);
	}

	/**
	 * Rename the extracted GitHub zipball folder to the correct plugin slug.
	 *
	 * GitHub auto-generated source zips contain a folder named
	 * "Owner-Repo-<sha>/" rather than the plugin slug, which would install the
	 * plugin under the wrong directory. This filter fires after extraction and
	 * renames the folder so WordPress places the plugin in the right location.
	 *
	 * Only acts when the upgrade target matches our plugin file.
	 *
	 * @param  string      $source        Full path to the extracted source directory.
	 * @param  string      $remote_source Path to the temp upgrade directory.
	 * @param  WP_Upgrader $upgrader      Upgrader instance.
	 * @param  array       $hook_extra    Extra data from the upgrader.
	 * @return string      Corrected source path.
	 */
	public static function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['plugin'] ) || self::PLUGIN_FILE !== $hook_extra['plugin'] ) {
			return $source;
		}

		global $wp_filesystem;

		$corrected = trailingslashit( $remote_source ) . self::PLUGIN_SLUG;

		// Nothing to do if the folder name already matches.
		if ( trailingslashit( $corrected ) === $source ) {
			return $source;
		}

		if ( ! empty( $wp_filesystem ) && $wp_filesystem->move( untrailingslashit( $source ), $corrected ) ) {
			if ( isset( $upgrader->skin ) && method_exists( $upgrader->skin, 'feedback' ) ) {
				$upgrader->skin->feedback( 'Renamed plugin folder to ' . self::PLUGIN_SLUG );
			}
			return trailingslashit( $corrected );
		}

		return $source;
	}

	// -------------------------------------------------------------------------
	// Manual force-check
	// -------------------------------------------------------------------------

	/**
	 * Handle a manual "Check for updates" click.
	 *
	 * Clears the cached release transient and the site-wide plugin-update
	 * transient so WordPress re-fetches everything immediately, then redirects
	 * back to the Plugins page.
	 *
	 * @return void
	 */
	public static function handle_force_check() {
		if ( ! isset( $_GET['wcpp_check_update'] ) ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to update plugins.', 'wcpp' ) );
		}

		check_admin_referer( 'wcpp_force_update_check' );

		delete_transient( self::TRANSIENT );
		delete_site_transient( 'update_plugins' ); // triggers a fresh WP check too

		$redirect = remove_query_arg(
			array( 'wcpp_check_update', '_wpnonce' ),
			wp_get_referer() ?: admin_url( 'plugins.php' )
		);

		wp_safe_redirect( add_query_arg( 'wcpp_checked', '1', $redirect ) );
		exit;
	}

	/**
	 * Append a "Check for updates" link to the plugin row action links.
	 *
	 * @param  array $links Existing action links.
	 * @return array
	 */
	public static function add_check_link( $links ) {
		$url = wp_nonce_url(
			add_query_arg( 'wcpp_check_update', '1', admin_url( 'plugins.php' ) ),
			'wcpp_force_update_check'
		);

		$links['wcpp_check'] = '<a href="' . esc_url( $url ) . '">'
			. esc_html__( 'Check for updates', 'wcpp' )
			. '</a>';

		return $links;
	}
}
