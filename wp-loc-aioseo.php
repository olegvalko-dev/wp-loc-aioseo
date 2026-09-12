<?php

/*
Plugin Name: WP-LOC AIOSEO
Plugin URI: https://wp-loc.com/
Description: All in One SEO Pack multilingual integration for WP-LOC
Version: 0.2.0
Requires Plugins: wp-loc, all-in-one-seo-pack-pro
Author: VALKO.PRO
Author URI: https://valko.pro
License: GPLv2 or later
Text Domain: wp-loc-aioseo
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WP_LOC_AIOSEO_VERSION', '0.2.0' );
define( 'WP_LOC_AIOSEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_LOC_AIOSEO_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_LOC_AIOSEO_BASENAME', plugin_basename( __FILE__ ) );

add_action( 'plugins_loaded', function () {
    load_plugin_textdomain( 'wp-loc-aioseo', false, dirname( WP_LOC_AIOSEO_BASENAME ) . '/languages' );

    // AIOSEO Pro and Lite both expose the aioseo() helper; either is fine.
    if ( ! class_exists( 'WP_LOC' ) || ! function_exists( 'aioseo' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'WP-LOC AIOSEO requires both WP-LOC and All in One SEO Pack to be active.', 'wp-loc-aioseo' )
                . '</p></div>';
        } );
        return;
    }

    require_once WP_LOC_AIOSEO_PATH . 'includes/class-wp-loc-aioseo.php';
    WP_LOC_AIOSEO::instance();
}, 20 );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $url = admin_url( 'admin.php?page=wp-loc-settings&tab=aioseo' );
    array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'wp-loc-aioseo' ) . '</a>' );
    return $links;
} );
