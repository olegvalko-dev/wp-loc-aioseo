<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Adds per-language hreflang alternates to AIOSEO's XML sitemap.
 *
 * AIOSEO only emits alternates when it detects WPML/Polylang, but its sitemap
 * XML view (Views/sitemap/xml/default.php) renders any `$entry['languages']`
 * array unconditionally as <xhtml:link rel="alternate" hreflang>. We attach
 * that array via AIOSEO's public `aioseo_sitemap_post` / `aioseo_sitemap_term`
 * filters, built from wp-loc's translation groups — no need to fake WPML.
 *
 * Mirrors WP_LOC_Yoast's wpseo_sitemap_* alternate handling.
 */
class WP_LOC_AIOSEO_Sitemap {

    public function __construct() {
        add_filter( 'aioseo_sitemap_post', [ $this, 'localize_post' ], 20, 4 );
        add_filter( 'aioseo_sitemap_term', [ $this, 'localize_term' ], 20, 4 );
    }

    private function enabled(): bool {
        return (bool) WP_LOC_AIOSEO::setting( 'sitemap_alternates', true );
    }

    public function localize_post( $entry, $postId, $postType, $type ) {
        if ( ! $this->enabled() || ! is_array( $entry ) || empty( $entry['loc'] ) ) {
            return $entry;
        }

        return $this->attach( $entry, $this->post_alternates( (int) $postId, (string) $postType ) );
    }

    public function localize_term( $entry, $termId, $taxonomy, $type ) {
        if ( ! $this->enabled() || ! is_array( $entry ) || empty( $entry['loc'] ) ) {
            return $entry;
        }

        return $this->attach( $entry, $this->term_alternates( (int) $termId, (string) $taxonomy ) );
    }

    /**
     * [ lang_slug => url ] for every published translation of a post.
     */
    private function post_alternates( int $post_id, string $post_type ): array {
        $alternates = [];

        foreach ( WP_LOC_AIOSEO_Lang::post_translations( $post_id, $post_type ) as $lang => $translation ) {
            $translated_id = (int) ( $translation->element_id ?? 0 );
            if ( ! $translated_id ) {
                continue;
            }

            $translated_post = get_post( $translated_id );
            if ( ! $translated_post || 'publish' !== $translated_post->post_status ) {
                continue;
            }

            $url = get_permalink( $translated_id );
            if ( $url ) {
                $alternates[ $lang ] = $url;
            }
        }

        return $alternates;
    }

    /**
     * [ lang_slug => url ] for every translation of a term.
     */
    private function term_alternates( int $term_id, string $taxonomy ): array {
        $alternates = [];

        foreach ( WP_LOC_AIOSEO_Lang::term_translations( $term_id, $taxonomy ) as $lang => $translation ) {
            $translated_term_id = WP_LOC_AIOSEO_Lang::term_id_from_taxonomy_id(
                (int) ( $translation->element_id ?? 0 ),
                $taxonomy
            );
            if ( ! $translated_term_id ) {
                continue;
            }

            $url = WP_LOC_AIOSEO_Lang::term_url( $translated_term_id, $taxonomy, $lang );
            if ( $url ) {
                $alternates[ $lang ] = $url;
            }
        }

        return $alternates;
    }

    /**
     * Turn a [ lang => url ] map into AIOSEO's $entry['languages'] subentries,
     * including an x-default pointing at the default language.
     */
    private function attach( array $entry, array $alternates ): array {
        // Only meaningful when the entry actually has siblings in other languages.
        if ( count( $alternates ) < 2 ) {
            return $entry;
        }

        $subentries = [];
        foreach ( $alternates as $lang => $url ) {
            $subentries[] = [
                'language' => WP_LOC_AIOSEO_Lang::hreflang( $lang ),
                'location' => $url,
            ];
        }

        $default = WP_LOC_AIOSEO_Lang::default_lang();
        if ( ! empty( $alternates[ $default ] ) ) {
            $subentries[] = [
                'language' => 'x-default',
                'location' => $alternates[ $default ],
            ];
        }

        $entry['languages'] = $subentries;

        return $entry;
    }
}
