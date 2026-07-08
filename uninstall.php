<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

// Remove the addon's own options. AIOSEO's own data and wp-loc's translation
// registry are owned by those plugins and left untouched.
delete_option( 'wp_loc_aioseo_strings' );
delete_option( 'wp_loc_aioseo_settings' );
