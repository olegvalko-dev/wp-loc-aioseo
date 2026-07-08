<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Per-language translation of AIOSEO's site-wide localizable strings.
 *
 * AIOSEO keeps every option flagged `'localized' => true` (homepage/archive
 * title & description templates, separators, breadcrumb formats, social
 * homepage OG, per-post-type/taxonomy templates, …) in a flat map on the
 * public `$localized` property of its Options objects. Its magic getter
 * (AIOSEO\...\Traits\Options::__get) returns the value from that map when a
 * field is localizable. The map is loaded from the `aioseo_options_localized`
 * and `aioseo_options_dynamic_localized` options.
 *
 * Per-post / per-term SEO is NOT handled here: AIOSEO stores it in its own
 * tables keyed by post/term ID, and wp-loc duplicates posts/terms per
 * language, so each language already has its own SEO row automatically.
 *
 * We translate the global strings by overwriting `$localized` in memory on a
 * late frontend hook (template_redirect), once wp-loc has resolved the request
 * language. This is timing-proof: AIOSEO builds & request-caches its options on
 * plugins_loaded — before the language is known — so filtering the raw option
 * would be too early. Overwriting `$localized` late is also safe: the shutdown
 * save() only writes `aioseo_options`, never `aioseo_options_localized`.
 */
class WP_LOC_AIOSEO_Options {

    /** Option holding per-language string translations. */
    public const STRINGS_OPTION = 'wp_loc_aioseo_strings';

    /** AIOSEO options that store the localizable string maps, by bucket. */
    private const SOURCE_OPTIONS = [
        'main'    => 'aioseo_options_localized',
        'dynamic' => 'aioseo_options_dynamic_localized',
    ];

    public function __construct() {
        // Priority 1: run before AIOSEO renders the document head (wp_head).
        add_action( 'template_redirect', [ $this, 'inject_localized' ], 1 );

        // The blog-posts homepage (show_on_front=posts) is a special case: AIOSEO
        // resolves its social OG/Twitter tags from social->facebook->homePage->* /
        // general->siteName on a path that does NOT honour the late $localized
        // override above (unlike singular posts/terms, whose social tags come out
        // correctly). So patch those tags at their final output filter instead.
        add_filter( 'aioseo_facebook_tags', [ $this, 'filter_home_facebook_tags' ], 20 );
        add_filter( 'aioseo_twitter_tags', [ $this, 'filter_home_twitter_tags' ], 20 );
    }

    /**
     * Override the blog-index homepage Open Graph tags for the current language.
     */
    public function filter_home_facebook_tags( $tags ) {
        return $this->override_home_social( $tags, [
            'og:site_name'   => 'social_facebook_general_siteName',
            'og:title'       => 'social_facebook_homePage_title',
            'og:description' => 'social_facebook_homePage_description',
        ] );
    }

    /**
     * Override the blog-index homepage Twitter Card tags for the current language.
     */
    public function filter_home_twitter_tags( $tags ) {
        return $this->override_home_social( $tags, [
            'twitter:title'       => 'social_twitter_homePage_title',
            'twitter:description' => 'social_twitter_homePage_description',
        ] );
    }

    /**
     * Replace the given social tag keys with stored translations, but only on the
     * blog-posts homepage in a non-default language. Each map entry is
     * tag-key => stored main-bucket string key; empty translations are skipped so
     * the default-language value stands.
     */
    private function override_home_social( $tags, array $keymap ) {
        if ( ! is_array( $tags ) || is_admin() || ! function_exists( 'aioseo' ) ) {
            return $tags;
        }

        if ( WP_LOC_AIOSEO_Lang::is_default_lang() ) {
            return $tags;
        }

        if ( ! ( is_home() && 'posts' === get_option( 'show_on_front' ) ) ) {
            return $tags;
        }

        $main = self::get_translations( WP_LOC_AIOSEO_Lang::current_lang() )['main'];

        foreach ( $keymap as $tag_key => $string_key ) {
            $value = isset( $main[ $string_key ] ) ? (string) $main[ $string_key ] : '';
            if ( '' !== $value && array_key_exists( $tag_key, $tags ) ) {
                $tags[ $tag_key ] = $value;
            }
        }

        return $tags;
    }

    /**
     * Swap AIOSEO's in-memory localized string maps for the current language.
     */
    public function inject_localized(): void {
        if ( is_admin() || ! function_exists( 'aioseo' ) ) {
            return;
        }

        if ( WP_LOC_AIOSEO_Lang::is_default_lang() ) {
            return;
        }

        $translations = self::get_translations( WP_LOC_AIOSEO_Lang::current_lang() );
        $aioseo       = aioseo();

        $this->apply_localized( $aioseo->options ?? null, $translations['main'] );
        $this->apply_localized( $aioseo->dynamicOptions ?? null, $translations['dynamic'] );
    }

    /**
     * Merge non-empty translated values over an Options object's localized map.
     *
     * The base already carries the full key set (AIOSEO seeds it with the
     * default-language values), so no key is ever missing — that avoids the
     * write-on-read fallback inside AIOSEO's getter.
     */
    private function apply_localized( $options_object, array $map ): void {
        if ( ! is_object( $options_object ) || ! property_exists( $options_object, 'localized' ) ) {
            return;
        }

        $map = array_filter( $map, static fn( $value ) => $value !== '' && $value !== null );
        if ( empty( $map ) ) {
            return;
        }

        $base = is_array( $options_object->localized ) ? $options_object->localized : [];

        $options_object->localized = array_merge( $base, $map );
    }

    /**
     * Stored translations for a language: [ 'main' => [...], 'dynamic' => [...] ].
     */
    public static function get_translations( string $lang ): array {
        $all   = get_option( self::STRINGS_OPTION, [] );
        $entry = ( is_array( $all ) && isset( $all[ $lang ] ) && is_array( $all[ $lang ] ) ) ? $all[ $lang ] : [];

        return [
            'main'    => ( isset( $entry['main'] ) && is_array( $entry['main'] ) ) ? $entry['main'] : [],
            'dynamic' => ( isset( $entry['dynamic'] ) && is_array( $entry['dynamic'] ) ) ? $entry['dynamic'] : [],
        ];
    }

    /**
     * Persist translations for a language. $buckets = [ 'main' => [...], 'dynamic' => [...] ].
     */
    public static function save_translations( string $lang, array $buckets ): void {
        $all = get_option( self::STRINGS_OPTION, [] );
        if ( ! is_array( $all ) ) {
            $all = [];
        }

        $all[ $lang ] = [
            'main'    => isset( $buckets['main'] ) && is_array( $buckets['main'] ) ? array_filter( $buckets['main'], 'strlen' ) : [],
            'dynamic' => isset( $buckets['dynamic'] ) && is_array( $buckets['dynamic'] ) ? array_filter( $buckets['dynamic'], 'strlen' ) : [],
        ];

        update_option( self::STRINGS_OPTION, $all );
    }

    /**
     * The default-language localizable map for a bucket, read straight from
     * AIOSEO. Drives the settings UI (key set + reference values), so we never
     * hardcode AIOSEO's option paths.
     *
     * @param string $bucket 'main' or 'dynamic'.
     */
    public static function base_localized( string $bucket ): array {
        $option = self::SOURCE_OPTIONS[ $bucket ] ?? self::SOURCE_OPTIONS['main'];
        $value  = get_option( $option, [] );

        return is_array( $value ) ? $value : [];
    }
}
