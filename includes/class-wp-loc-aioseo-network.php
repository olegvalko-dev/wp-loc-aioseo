<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Keeps AIOSEO Pro's licensing layer "offline" so it can't fatal.
 *
 * On an unlicensed / non-canonical domain AIOSEO Pro's API
 * (licensing.aioseo.com, aioseo.com) returns malformed payloads, and several
 * AIOSEO code paths then fatal because they assume a well-formed object/array:
 *   - Pro/Admin/Updates.php::updatePluginsFilter — "assign property on true"
 *     (checkForUpdates() returns a bool), fires on every admin_init / cron / `wp plugin list`.
 *   - Pro/Utils/Addons.php::getAddons — array_filter() on a stdClass, fires on
 *     several admin screens (update-core, post editor, settings pages…).
 *
 * We don't rely on AIOSEO's licensed network features for this stack, so we
 * short-circuit its outbound requests to *.aioseo.com. AIOSEO then degrades
 * gracefully on its own:
 *   - checkForUpdates() sees a WP_Error and returns null (filter bails),
 *   - fetchAddonsFromRemote() sees a non-200 and returns its default array.
 * Its actual SEO output (meta, sitemaps, schema) does not use this API, so
 * nothing user-facing is lost.
 *
 * Opt out on a properly licensed site to restore AIOSEO's network features:
 *   add_filter( 'wp_loc_aioseo_block_remote_api', '__return_false' );
 */
class WP_LOC_AIOSEO_Network {

	public function __construct() {
		add_filter( 'pre_http_request', [ $this, 'block_aioseo_api' ], 10, 3 );
	}

	/**
	 * @param false|array|WP_Error $preempt Short-circuit value (false = let WP proceed).
	 * @param array                $args    Request args.
	 * @param string               $url     Request URL.
	 * @return false|WP_Error
	 */
	public function block_aioseo_api( $preempt, $args, $url ) {
		if ( false !== $preempt ) {
			return $preempt; // another plugin already handled it
		}

		if ( ! apply_filters( 'wp_loc_aioseo_block_remote_api', true ) ) {
			return $preempt;
		}

		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( ! $host ) {
			return $preempt;
		}

		$host = strtolower( $host );
		if ( 'aioseo.com' === $host || str_ends_with( $host, '.aioseo.com' ) ) {
			return new WP_Error(
				'wp_loc_aioseo_blocked',
				'AIOSEO remote API blocked by WP-LOC AIOSEO (unlicensed-offline mode).'
			);
		}

		return $preempt;
	}
}
