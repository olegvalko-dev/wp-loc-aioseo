<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Thin static wrapper around the wp-loc language/translation API.
 *
 * Every wp-loc lookup in this addon goes through this class so the wp-loc
 * surface is referenced in exactly one place (mirrors WP_LOC_WC_Lang).
 */
class WP_LOC_AIOSEO_Lang {

    public static function current_lang(): string {
        return WP_LOC_Routing::get_current_lang();
    }

    public static function default_lang(): string {
        return WP_LOC_Languages::get_default_language();
    }

    public static function is_default_lang(): bool {
        return self::current_lang() === self::default_lang();
    }

    /**
     * Active (enabled) languages keyed by slug.
     */
    public static function active_languages(): array {
        return WP_LOC_Languages::get_active_languages();
    }

    /**
     * Non-default active language slugs.
     */
    public static function additional_languages(): array {
        return WP_LOC_Languages::get_additional_languages();
    }

    /**
     * Translated post ID for a target language, or null when none exists.
     */
    public static function translated_post_id( int $post_id, string $post_type, string $lang ): ?int {
        return WP_LOC::instance()->db->get_element_translation(
            $post_id,
            WP_LOC_DB::post_element_type( $post_type ),
            $lang
        );
    }

    /**
     * Language code of a post, or null when not registered.
     */
    public static function post_language( int $post_id, string $post_type ): ?string {
        return WP_LOC::instance()->db->get_element_language(
            $post_id,
            WP_LOC_DB::post_element_type( $post_type )
        );
    }

    /**
     * Source-language post ID for a translation (the original it was translated from).
     */
    public static function source_post_id( int $post_id, string $post_type ): ?int {
        $db           = WP_LOC::instance()->db;
        $element_type = WP_LOC_DB::post_element_type( $post_type );

        $trid = $db->get_trid( $post_id, $element_type );
        if ( ! $trid ) {
            return null;
        }

        $translations  = $db->get_element_translations( $trid, $element_type );
        $current_lang  = $db->get_element_language( $post_id, $element_type );

        if ( ! $current_lang || empty( $translations[ $current_lang ]->source_language_code ) ) {
            return null;
        }

        $source_lang = (string) $translations[ $current_lang ]->source_language_code;

        return ! empty( $translations[ $source_lang ]->element_id )
            ? (int) $translations[ $source_lang ]->element_id
            : null;
    }

    /**
     * Translated attachment ID for a target language, falling back to the original.
     */
    public static function translated_attachment_id( int $attachment_id, string $lang ): int {
        $translated = WP_LOC::instance()->db->get_element_translation(
            $attachment_id,
            WP_LOC_DB::post_element_type( 'attachment' ),
            $lang
        );

        return $translated ?: $attachment_id;
    }

    /**
     * All translations of a post keyed by language code (objects with element_id).
     */
    public static function post_translations( int $post_id, string $post_type ): array {
        $db           = WP_LOC::instance()->db;
        $element_type = WP_LOC_DB::post_element_type( $post_type );

        $trid = $db->get_trid( $post_id, $element_type );
        if ( ! $trid ) {
            return [];
        }

        return $db->get_element_translations( $trid, $element_type );
    }

    /**
     * All translations of a term keyed by language code (element_id = term_taxonomy_id).
     */
    public static function term_translations( int $term_id, string $taxonomy ): array {
        return class_exists( 'WP_LOC_Terms' )
            ? WP_LOC_Terms::get_term_translations( $term_id, $taxonomy )
            : [];
    }

    /**
     * Resolve a term_id from a term_taxonomy_id (wp-loc stores the latter as element_id).
     */
    public static function term_id_from_taxonomy_id( int $term_taxonomy_id, string $taxonomy ): int {
        return class_exists( 'WP_LOC_Terms' )
            ? (int) WP_LOC_Terms::get_term_id_from_taxonomy_id( $term_taxonomy_id, $taxonomy )
            : 0;
    }

    /**
     * Language code of a term, or null when not registered.
     */
    public static function term_language( int $term_id, string $taxonomy ): ?string {
        return class_exists( 'WP_LOC_Terms' )
            ? WP_LOC_Terms::get_term_language( $term_id, $taxonomy )
            : null;
    }

    /**
     * Source-language term ID for a translation (the original it was translated from).
     */
    public static function source_term_id( int $term_id, string $taxonomy ): ?int {
        if ( ! class_exists( 'WP_LOC_Terms' ) ) {
            return null;
        }

        $term_taxonomy_id = (int) WP_LOC_Terms::get_term_taxonomy_id( $term_id, $taxonomy );
        if ( ! $term_taxonomy_id ) {
            return null;
        }

        $db           = WP_LOC::instance()->db;
        $element_type  = WP_LOC_DB::tax_element_type( $taxonomy );
        $trid         = $db->get_trid( $term_taxonomy_id, $element_type );
        if ( ! $trid ) {
            return null;
        }

        $translations = $db->get_element_translations( $trid, $element_type );
        $current_lang = $db->get_element_language( $term_taxonomy_id, $element_type );

        if ( ! $current_lang || empty( $translations[ $current_lang ]->source_language_code ) ) {
            return null;
        }

        $source_lang = (string) $translations[ $current_lang ]->source_language_code;
        if ( empty( $translations[ $source_lang ]->element_id ) ) {
            return null;
        }

        return self::term_id_from_taxonomy_id( (int) $translations[ $source_lang ]->element_id, $taxonomy ) ?: null;
    }

    /**
     * Permalink of a term in a specific language (language-prefixed where applicable).
     */
    public static function term_url( int $term_id, string $taxonomy, string $lang ): string {
        if ( class_exists( 'WP_LOC_Terms' ) && method_exists( 'WP_LOC_Terms', 'get_term_url_for_language' ) ) {
            $url = WP_LOC_Terms::get_term_url_for_language( $term_id, $taxonomy, $lang );
            if ( $url ) {
                return $url;
            }
        }

        $link = get_term_link( $term_id, $taxonomy );

        return is_wp_error( $link ) ? '' : (string) $link;
    }

    /**
     * hreflang attribute for a language slug (locale with hyphen, e.g. uk-UA).
     */
    public static function hreflang( string $lang ): string {
        $locale = WP_LOC_Languages::get_language_locale( $lang );

        return $locale ? str_replace( '_', '-', $locale ) : $lang;
    }
}
