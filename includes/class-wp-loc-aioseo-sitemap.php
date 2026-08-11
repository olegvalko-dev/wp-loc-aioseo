<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Makes AIOSEO's XML sitemap multilingual: correct per-language <loc> values
 * plus hreflang alternates.
 *
 * AIOSEO only emits alternates when it detects WPML/Polylang, but its sitemap
 * XML view (Views/sitemap/xml/default.php) renders any `$entry['languages']`
 * array unconditionally as <xhtml:link rel="alternate" hreflang>. We attach
 * that array via AIOSEO's public `aioseo_sitemap_post` / `aioseo_sitemap_term`
 * filters, built from wp-loc's translation groups — no need to fake WPML.
 *
 * The same filters are also the only place where an entry's own URL can still
 * be corrected: AIOSEO resolves it in the language of the request that renders
 * the sitemap, which is wrong for every entry belonging to another language.
 *
 * Mirrors WP_LOC_Yoast's wpseo_sitemap_* alternate handling.
 */
class WP_LOC_AIOSEO_Sitemap {

    /**
     * Marks an entry for removal in the `aioseo_sitemap_posts` pass. The
     * per-entry filter is the only hook that knows the post ID, but its return
     * value is appended unconditionally, so exclusion needs the later pass.
     */
    private const EXCLUDE_FLAG = 'wp_loc_exclude';

    public function __construct() {
        add_filter( 'aioseo_sitemap_post', [ $this, 'localize_post' ], 20, 4 );
        add_filter( 'aioseo_sitemap_term', [ $this, 'localize_term' ], 20, 4 );
        add_filter( 'aioseo_sitemap_posts', [ $this, 'drop_excluded_posts' ], 20, 2 );
    }

    private function enabled(): bool {
        return (bool) WP_LOC_AIOSEO::setting( 'sitemap_alternates', true );
    }

    private function skip_system_pages(): bool {
        return (bool) WP_LOC_AIOSEO::setting( 'sitemap_skip_translated_system_pages', true );
    }

    public function localize_post( $entry, $postId, $postType, $type ) {
        if ( ! $this->enabled() || ! is_array( $entry ) || empty( $entry['loc'] ) ) {
            return $entry;
        }

        $post_id = (int) $postId;

        if ( $this->skip_system_pages() && in_array( $post_id, $this->translated_system_page_ids(), true ) ) {
            $entry[ self::EXCLUDE_FLAG ] = true;

            return $entry;
        }

        $front_page_url = $this->front_page_url( $post_id );
        if ( $front_page_url ) {
            $entry['loc'] = $front_page_url;
        }

        return $this->attach( $entry, $this->post_alternates( $post_id, (string) $postType ) );
    }

    public function localize_term( $entry, $termId, $taxonomy, $type ) {
        if ( ! $this->enabled() || ! is_array( $entry ) || empty( $entry['loc'] ) ) {
            return $entry;
        }

        $term_id  = (int) $termId;
        $taxonomy = (string) $taxonomy;

        // AIOSEO reads every term of every language out of the database, then
        // asks WordPress for each URL. wp-loc answers in the language of the
        // current request, so a term of any other language is handed its
        // sibling's URL: the sitemap ends up listing the default language twice
        // and the secondary language not at all. Resolve in the term's own
        // language instead — the same call the alternates already use.
        $term_lang = WP_LOC_AIOSEO_Lang::term_language( $term_id, $taxonomy );
        if ( $term_lang ) {
            $url = WP_LOC_AIOSEO_Lang::term_url( $term_id, $taxonomy, $term_lang );

            if ( $url ) {
                $entry['loc'] = $url;
            }
        }

        return $this->attach( $entry, $this->term_alternates( $term_id, $taxonomy ) );
    }

    /**
     * Strips entries flagged by localize_post().
     *
     * @param array $entries
     */
    public function drop_excluded_posts( $entries, $postType ) {
        if ( ! is_array( $entries ) ) {
            return $entries;
        }

        $kept = [];

        foreach ( $entries as $entry ) {
            if ( is_array( $entry ) && ! empty( $entry[ self::EXCLUDE_FLAG ] ) ) {
                continue;
            }

            if ( is_array( $entry ) ) {
                unset( $entry[ self::EXCLUDE_FLAG ] );
            }

            $kept[] = $entry;
        }

        return $kept;
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

            $url = $this->front_page_url( $translated_id ) ?: get_permalink( $translated_id );
            if ( $url ) {
                $alternates[ $lang ] = $url;
            }
        }

        return $alternates;
    }

    /**
     * The canonical URL of a page that serves as the front page of its language,
     * or '' when the page is an ordinary one.
     *
     * A static front page keeps its slug, so get_permalink() answers with
     * /{lang}/{slug}/ — an address that only ever 301s to /{lang}/. WordPress
     * special-cases this for the default language on its own; secondary
     * languages need the same treatment, otherwise every annotation pointing at
     * that translation is a redirect, which search engines refuse to follow
     * inside an hreflang cluster.
     */
    private function front_page_url( int $post_id ): string {
        $front_pages = $this->front_page_languages();

        if ( ! isset( $front_pages[ $post_id ] ) ) {
            return '';
        }

        $lang = $front_pages[ $post_id ];

        // WordPress already resolves the default-language front page correctly,
        // including AIOSEO's trailing-slash handling. Do not second-guess it.
        if ( $lang === WP_LOC_AIOSEO_Lang::default_lang() ) {
            return '';
        }

        // get_option() rather than home_url(): the latter is filtered by wp-loc
        // to carry the prefix of the *current* language, not the target one.
        $home = rtrim( set_url_scheme( (string) get_option( 'home' ) ), '/' );

        return $home . '/' . $lang . '/';
    }

    /**
     * [ post_id => lang_slug ] for the front page of every language.
     */
    private function front_page_languages(): array {
        static $front_pages = null;

        if ( $front_pages !== null ) {
            return $front_pages;
        }

        $front_pages = [];

        if ( 'page' !== get_option( 'show_on_front' ) ) {
            return $front_pages;
        }

        $front_id = (int) get_option( 'page_on_front' );
        if ( ! $front_id ) {
            return $front_pages;
        }

        $front_pages[ $front_id ] = WP_LOC_AIOSEO_Lang::post_language( $front_id, 'page' )
            ?: WP_LOC_AIOSEO_Lang::default_lang();

        foreach ( WP_LOC_AIOSEO_Lang::post_translations( $front_id, 'page' ) as $lang => $translation ) {
            $translated_id = (int) ( $translation->element_id ?? 0 );

            if ( $translated_id ) {
                $front_pages[ $translated_id ] = $lang;
            }
        }

        return $front_pages;
    }

    /**
     * Translations of the WooCommerce pages that must never be indexed.
     *
     * WooCommerce stores one page ID per system page, and AIOSEO keeps cart,
     * checkout and account out of the sitemap by that ID. A translation is a
     * separate post with its own ID, so it is invisible to both — the shop's
     * secondary-language cart ends up in the sitemap, where it answers with the
     * redirect WooCommerce sends every visitor.
     *
     * Shop and Terms are deliberately absent: those are ordinary landing pages
     * that belong in the sitemap.
     *
     * @return int[]
     */
    private function translated_system_page_ids(): array {
        static $ids = null;

        if ( $ids !== null ) {
            return $ids;
        }

        $ids = [];

        if ( ! function_exists( 'wc_get_page_id' ) ) {
            return $ids;
        }

        foreach ( [ 'cart', 'checkout', 'myaccount' ] as $page ) {
            $page_id = (int) wc_get_page_id( $page );

            if ( $page_id < 1 ) {
                continue;
            }

            foreach ( WP_LOC_AIOSEO_Lang::post_translations( $page_id, 'page' ) as $lang => $translation ) {
                $translated_id = (int) ( $translation->element_id ?? 0 );

                if ( $translated_id && $lang !== WP_LOC_AIOSEO_Lang::default_lang() ) {
                    $ids[] = $translated_id;
                }
            }
        }

        /**
         * Filter the post IDs this addon removes from the XML sitemap.
         *
         * @param int[] $ids
         */
        $ids = array_values( array_unique( (array) apply_filters( 'wp_loc_aioseo_sitemap_excluded_post_ids', $ids ) ) );

        return $ids;
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
        // One entry is enough. Google treats a cluster without a self-reference
        // as malformed and drops it whole, so a page whose translations are not
        // published yet still has to name itself.
        if ( ! $alternates ) {
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
