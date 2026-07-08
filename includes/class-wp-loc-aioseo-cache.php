<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Clears AIOSEO's cache when the addon's translations or toggles change.
 *
 * AIOSEO's dynamic sitemap and meta output are computed per request (our
 * filters run live), and AIOSEO already regenerates its static sitemap on
 * save_post / edited_term. The only stale-state we must flush is anything
 * AIOSEO cached from the global option strings before a translation was
 * edited — so we clear on changes to our own options.
 */
class WP_LOC_AIOSEO_Cache {

    public function __construct() {
        add_action( 'update_option_' . WP_LOC_AIOSEO_Options::STRINGS_OPTION, [ $this, 'clear' ] );
        add_action( 'update_option_' . WP_LOC_AIOSEO::SETTINGS_OPTION, [ $this, 'clear' ] );
    }

    /**
     * Defensively clear AIOSEO's cache (never fatal if the API shape changes).
     */
    public function clear(): void {
        if ( ! function_exists( 'aioseo' ) ) {
            return;
        }

        $aioseo = aioseo();

        if ( isset( $aioseo->core->cache ) && method_exists( $aioseo->core->cache, 'clear' ) ) {
            $aioseo->core->cache->clear();
        }
    }
}
