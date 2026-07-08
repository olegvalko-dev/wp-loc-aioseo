<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main plugin bootstrap. Loads modules and wires them together.
 *
 * Mirrors the structure of the wp-loc-woocommerce addon: a singleton that
 * requires the include files and instantiates each module once WP-LOC and
 * AIOSEO are both confirmed active (see wp-loc-aioseo.php).
 */
class WP_LOC_AIOSEO {

    /** Option holding the addon's own toggles (sitemap alternates, seeding). */
    public const SETTINGS_OPTION = 'wp_loc_aioseo_settings';

    private static $instance = null;

    /** @var WP_LOC_AIOSEO_Options */
    public $options;

    /** @var WP_LOC_AIOSEO_Sitemap */
    public $sitemap;

    /** @var WP_LOC_AIOSEO_Seed */
    public $seed;

    /** @var WP_LOC_AIOSEO_Cache */
    public $cache;

    /** @var WP_LOC_AIOSEO_Network */
    public $network;

    /** @var WP_LOC_AIOSEO_Settings */
    public $settings;

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Read an addon toggle. Missing keys fall back to $default (features default on).
     */
    public static function setting( string $key, $default = null ) {
        $settings = get_option( self::SETTINGS_OPTION, [] );

        return ( is_array( $settings ) && array_key_exists( $key, $settings ) )
            ? $settings[ $key ]
            : $default;
    }

    private function __construct() {
        $this->load_includes();
        $this->init_modules();
    }

    private function load_includes(): void {
        $includes = [
            'class-wp-loc-aioseo-lang',
            'class-wp-loc-aioseo-options',
            'class-wp-loc-aioseo-sitemap',
            'class-wp-loc-aioseo-seed',
            'class-wp-loc-aioseo-cache',
            'class-wp-loc-aioseo-network',
        ];

        foreach ( $includes as $file ) {
            require_once WP_LOC_AIOSEO_PATH . "includes/{$file}.php";
        }

        if ( is_admin() ) {
            require_once WP_LOC_AIOSEO_PATH . 'includes/class-wp-loc-aioseo-settings.php';
        }
    }

    private function init_modules(): void {
        $this->options = new WP_LOC_AIOSEO_Options();
        $this->sitemap = new WP_LOC_AIOSEO_Sitemap();
        $this->seed    = new WP_LOC_AIOSEO_Seed();
        $this->cache   = new WP_LOC_AIOSEO_Cache();
        $this->network = new WP_LOC_AIOSEO_Network();

        if ( is_admin() ) {
            $this->settings = new WP_LOC_AIOSEO_Settings();
        }
    }
}
