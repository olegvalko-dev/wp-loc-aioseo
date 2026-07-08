<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Seeds a freshly created translation's AIOSEO record with the structural
 * fields from its source (robots directives, OG/Twitter object & image type,
 * schema), leaving all human-readable text (title, description, OG/Twitter
 * titles & descriptions, canonical URL) blank for manual translation.
 *
 * Custom image attachments are remapped to the translated attachment when one
 * exists (mirrors WP_LOC_Yoast::copy_term_meta_from_source_translation).
 *
 * Per-post/term SEO otherwise works without code because wp-loc duplicates the
 * post/term and AIOSEO keys its tables by ID; this module is editor convenience.
 */
class WP_LOC_AIOSEO_Seed {

    private const POST_MODEL = '\\AIOSEO\\Plugin\\Common\\Models\\Post';
    private const TERM_MODEL = '\\AIOSEO\\Plugin\\Pro\\Models\\Term';

    /** Structural (non-text) fields copied from source to a new translation. */
    private const STRUCTURAL_FIELDS = [
        'robots_default',
        'robots_noindex',
        'robots_noarchive',
        'robots_nosnippet',
        'robots_nofollow',
        'robots_noimageindex',
        'robots_noodp',
        'robots_notranslate',
        'robots_max_snippet',
        'robots_max_videopreview',
        'robots_max_imagepreview',
        'og_object_type',
        'og_image_type',
        'twitter_use_og',
        'twitter_card',
        'twitter_image_type',
        'schema',
    ];

    /** Custom image id => url field pairs to remap to translated attachments. */
    private const IMAGE_FIELDS = [
        'og_image_custom_id'      => 'og_image_custom_url',
        'twitter_image_custom_id' => 'twitter_image_custom_url',
    ];

    public function __construct() {
        add_action( 'save_post', [ $this, 'maybe_seed_post' ], 40, 3 );
        add_action( 'created_term', [ $this, 'maybe_seed_term' ], 30, 3 );
    }

    private function enabled(): bool {
        return (bool) WP_LOC_AIOSEO::setting( 'seed_translations', true );
    }

    public function maybe_seed_post( int $post_id, WP_Post $post, bool $update ): void {
        // Only at creation of the translation, and only when AIOSEO is present.
        if ( $update || ! $this->enabled() || ! class_exists( self::POST_MODEL ) ) {
            return;
        }

        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( ! $this->is_translatable_post_type( $post->post_type ) ) {
            return;
        }

        $source_id = WP_LOC_AIOSEO_Lang::source_post_id( $post_id, $post->post_type );
        if ( ! $source_id || $source_id === $post_id ) {
            return;
        }

        $target_lang = WP_LOC_AIOSEO_Lang::post_language( $post_id, $post->post_type );
        if ( ! $target_lang ) {
            return;
        }

        $model  = self::POST_MODEL;
        $source = $model::getPost( $source_id );
        $target = $model::getPost( $post_id );

        if ( ! $source->exists() || $target->exists() ) {
            return;
        }

        $this->copy_structural( $source, $target, $target_lang );
        $target->post_id = $post_id;
        $target->save();
    }

    public function maybe_seed_term( int $term_id, int $term_taxonomy_id, string $taxonomy ): void {
        if ( ! $this->enabled() || ! class_exists( self::TERM_MODEL ) ) {
            return;
        }

        if ( ! $this->is_translatable_taxonomy( $taxonomy ) ) {
            return;
        }

        $source_id = WP_LOC_AIOSEO_Lang::source_term_id( $term_id, $taxonomy );
        if ( ! $source_id || $source_id === $term_id ) {
            return;
        }

        $target_lang = WP_LOC_AIOSEO_Lang::term_language( $term_id, $taxonomy );
        if ( ! $target_lang ) {
            return;
        }

        $model  = self::TERM_MODEL;
        $source = $model::getTerm( $source_id );
        $target = $model::getTerm( $term_id );

        if ( ! $source->exists() || $target->exists() ) {
            return;
        }

        $this->copy_structural( $source, $target, $target_lang );
        $target->term_id = $term_id;
        $target->save();
    }

    /**
     * Copy structural fields and remap custom social images to the new language.
     */
    private function copy_structural( $source, $target, string $lang ): void {
        foreach ( self::STRUCTURAL_FIELDS as $field ) {
            if ( property_exists( $source, $field ) ) {
                $target->$field = $source->$field;
            }
        }

        foreach ( self::IMAGE_FIELDS as $id_field => $url_field ) {
            if ( ! property_exists( $source, $id_field ) || empty( $source->$id_field ) ) {
                continue;
            }

            $new_id            = WP_LOC_AIOSEO_Lang::translated_attachment_id( (int) $source->$id_field, $lang );
            $target->$id_field = $new_id;

            if ( property_exists( $source, $url_field ) ) {
                $url               = wp_get_attachment_url( $new_id );
                $target->$url_field = $url ?: ( $source->$url_field ?? null );
            }
        }
    }

    private function is_translatable_post_type( string $post_type ): bool {
        if ( class_exists( 'WP_LOC_Admin_Settings' ) && method_exists( 'WP_LOC_Admin_Settings', 'is_translatable' ) ) {
            return (bool) WP_LOC_Admin_Settings::is_translatable( $post_type );
        }

        return in_array( $post_type, (array) apply_filters( 'wp_loc_translatable_post_types', [ 'post', 'page' ] ), true );
    }

    private function is_translatable_taxonomy( string $taxonomy ): bool {
        if ( class_exists( 'WP_LOC_Terms' ) && method_exists( 'WP_LOC_Terms', 'is_translatable' ) ) {
            return (bool) WP_LOC_Terms::is_translatable( $taxonomy );
        }

        return in_array( $taxonomy, (array) apply_filters( 'wp_loc_translatable_taxonomies', [ 'category', 'post_tag' ] ), true );
    }
}
