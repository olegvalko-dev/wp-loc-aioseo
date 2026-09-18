<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Makes AIOSEO Redirects match language-prefixed URLs.
 *
 * AIOSEO strips the "home path" from the request before matching a rule and
 * prepends home_url() to relative targets. On a non-default language
 * WP-LOC filters home_url() to ".../ru/", so "/ru/old/" is matched as
 * "/old/" (never hits a "/ru/old/" rule) and a "/ru/new/" target becomes
 * "/ru/ru/new/". AIOSEO special-cases WPML/TranslatePress for this; we get
 * the same effect by lifting the WP-LOC home_url filter around AIOSEO's
 * main redirect loop (init, priority 10) so both sides see the bare domain.
 */
class WP_LOC_AIOSEO_Redirects {

    public function __construct() {
        add_action( 'init', [ $this, 'lift_home_url_filter' ], 9 );
        add_action( 'init', [ $this, 'restore_home_url_filter' ], 11 );
    }

    public function lift_home_url_filter(): void {
        remove_filter( 'home_url', [ WP_LOC::instance()->routing, 'filter_home_url' ], 10 );
    }

    public function restore_home_url_filter(): void {
        add_filter( 'home_url', [ WP_LOC::instance()->routing, 'filter_home_url' ], 10, 4 );
    }
}
