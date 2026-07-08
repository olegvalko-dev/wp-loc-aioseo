<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin screen for translating AIOSEO's global localizable strings per language,
 * plus the addon's feature toggles. Lives under the wp-loc "Multilingual" menu.
 *
 * The list of translatable keys is read live from AIOSEO
 * (aioseo_options_localized / aioseo_options_dynamic_localized via
 * WP_LOC_AIOSEO_Options::base_localized) and unioned with a small built-in
 * baseline so the most important strings always appear, even before AIOSEO has
 * lazily populated its localized option.
 */
class WP_LOC_AIOSEO_Settings {

    private const PAGE_SLUG  = 'wp-loc-aioseo';
    private const NONCE      = 'wp_loc_aioseo_save';
    private const LANG_PARAM = 'wl_lang';

    /**
     * Built-in baseline of the core localizable main-option keys with their
     * AIOSEO default templates, shown as reference even before AIOSEO seeds them.
     */
    private const BASELINE_MAIN = [
        'searchAppearance_global_siteTitle'                => '#site_title #separator_sa #tagline',
        'searchAppearance_global_metaDescription'          => '#tagline',
        'searchAppearance_global_keywords'                 => '',
        'searchAppearance_global_pagedFormat'              => '#separator_sa Page #page_number',
        'searchAppearance_archives_author_title'           => '#author_name #separator_sa #site_title',
        'searchAppearance_archives_author_metaDescription' => '#author_bio',
        'searchAppearance_archives_date_title'             => '#archive_date #separator_sa #site_title',
        'searchAppearance_archives_date_metaDescription'   => '',
        'searchAppearance_archives_search_title'           => '#search_term #separator_sa #site_title',
        'searchAppearance_archives_search_metaDescription' => '',
        'breadcrumbs_breadcrumbPrefix'                     => '',
        'breadcrumbs_archiveFormat'                        => 'Archives for #breadcrumb_archive_post_type_name',
        'breadcrumbs_searchResultFormat'                   => "Search Results for '#breadcrumb_search_string'",
        'breadcrumbs_errorFormat404'                       => '404 - Page Not Found',
    ];

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 30 );
        add_action( 'admin_init', [ $this, 'handle_save' ] );
    }

    public function add_menu(): void {
        add_submenu_page(
            'wp-loc',
            __( 'AIOSEO Translations', 'wp-loc-aioseo' ),
            __( 'AIOSEO SEO', 'wp-loc-aioseo' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ]
        );
    }

    public function handle_save(): void {
        if ( empty( $_POST['wp_loc_aioseo_save'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( self::NONCE ) ) {
            return;
        }

        // Feature toggles (checkboxes: present => on).
        update_option( WP_LOC_AIOSEO::SETTINGS_OPTION, [
            'sitemap_alternates' => ! empty( $_POST['sitemap_alternates'] ),
            'seed_translations'  => ! empty( $_POST['seed_translations'] ),
        ] );

        // Per-language string translations.
        $lang = isset( $_POST['wl_lang'] ) ? sanitize_key( wp_unslash( $_POST['wl_lang'] ) ) : '';
        if ( $lang && in_array( $lang, WP_LOC_AIOSEO_Lang::additional_languages(), true ) ) {
            $raw     = isset( $_POST['wlaioseo'] ) && is_array( $_POST['wlaioseo'] ) ? wp_unslash( $_POST['wlaioseo'] ) : [];
            $buckets = [
                'main'    => $this->sanitize_bucket( $raw['main'] ?? [] ),
                'dynamic' => $this->sanitize_bucket( $raw['dynamic'] ?? [] ),
            ];

            WP_LOC_AIOSEO_Options::save_translations( $lang, $buckets );
        }

        wp_safe_redirect( add_query_arg(
            [ 'page' => self::PAGE_SLUG, self::LANG_PARAM => $lang, 'updated' => '1' ],
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    private function sanitize_bucket( $bucket ): array {
        if ( ! is_array( $bucket ) ) {
            return [];
        }

        $clean = [];
        foreach ( $bucket as $key => $value ) {
            $clean[ (string) $key ] = sanitize_text_field( (string) $value );
        }

        return $clean;
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $additional = WP_LOC_AIOSEO_Lang::additional_languages();

        if ( empty( $additional ) ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'AIOSEO Translations', 'wp-loc-aioseo' ) . '</h1>';
            echo '<p>' . esc_html__( 'Add at least one non-default language in WP-LOC to translate AIOSEO strings.', 'wp-loc-aioseo' ) . '</p></div>';
            return;
        }

        $current_lang = isset( $_GET[ self::LANG_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::LANG_PARAM ] ) ) : '';
        if ( ! in_array( $current_lang, $additional, true ) ) {
            $current_lang = $additional[0];
        }

        $main_defaults    = array_merge( self::BASELINE_MAIN, WP_LOC_AIOSEO_Options::base_localized( 'main' ) );
        $dynamic_defaults = WP_LOC_AIOSEO_Options::base_localized( 'dynamic' );
        $translations     = WP_LOC_AIOSEO_Options::get_translations( $current_lang );

        $sitemap_alternates = (bool) WP_LOC_AIOSEO::setting( 'sitemap_alternates', true );
        $seed_translations  = (bool) WP_LOC_AIOSEO::setting( 'seed_translations', true );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'AIOSEO Translations', 'wp-loc-aioseo' ); ?></h1>

            <?php if ( ! empty( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wp-loc-aioseo' ); ?></p></div>
            <?php endif; ?>

            <h2 class="nav-tab-wrapper">
                <?php foreach ( $additional as $lang ) :
                    $url = add_query_arg( [ 'page' => self::PAGE_SLUG, self::LANG_PARAM => $lang ], admin_url( 'admin.php' ) );
                    $cls = $lang === $current_lang ? 'nav-tab nav-tab-active' : 'nav-tab';
                    ?>
                    <a class="<?php echo esc_attr( $cls ); ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( strtoupper( $lang ) ); ?></a>
                <?php endforeach; ?>
            </h2>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ); ?>">
                <?php wp_nonce_field( self::NONCE ); ?>
                <input type="hidden" name="wp_loc_aioseo_save" value="1" />
                <input type="hidden" name="wl_lang" value="<?php echo esc_attr( $current_lang ); ?>" />

                <h2><?php esc_html_e( 'Options', 'wp-loc-aioseo' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Sitemap hreflang alternates', 'wp-loc-aioseo' ); ?></th>
                        <td><label><input type="checkbox" name="sitemap_alternates" value="1" <?php checked( $sitemap_alternates ); ?> /> <?php esc_html_e( 'Add per-language alternate links to the XML sitemap', 'wp-loc-aioseo' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Seed new translations', 'wp-loc-aioseo' ); ?></th>
                        <td><label><input type="checkbox" name="seed_translations" value="1" <?php checked( $seed_translations ); ?> /> <?php esc_html_e( 'Copy structural SEO fields from the source when a translation is created', 'wp-loc-aioseo' ); ?></label></td>
                    </tr>
                </table>

                <h2><?php
                    /* translators: %s: language slug */
                    printf( esc_html__( 'Global strings — %s', 'wp-loc-aioseo' ), '<code>' . esc_html( $current_lang ) . '</code>' );
                ?></h2>
                <p class="description"><?php esc_html_e( 'Leave a field empty to fall back to the default-language value. Tags like #site_title and #separator_sa are AIOSEO smart tags and should stay as-is.', 'wp-loc-aioseo' ); ?></p>

                <?php
                $this->render_bucket( __( 'General', 'wp-loc-aioseo' ), 'main', $main_defaults, $translations['main'] );
                if ( ! empty( $dynamic_defaults ) ) {
                    $this->render_bucket( __( 'Post types & taxonomies', 'wp-loc-aioseo' ), 'dynamic', $dynamic_defaults, $translations['dynamic'] );
                }
                ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render one bucket of key => default-value rows with translation inputs.
     */
    private function render_bucket( string $heading, string $bucket, array $defaults, array $values ): void {
        ksort( $defaults );
        ?>
        <h3><?php echo esc_html( $heading ); ?></h3>
        <table class="form-table" role="presentation">
            <tbody>
            <?php foreach ( $defaults as $key => $default ) :
                $value = isset( $values[ $key ] ) ? (string) $values[ $key ] : '';
                ?>
                <tr>
                    <th scope="row" style="font-weight:400;">
                        <label for="<?php echo esc_attr( "wlaioseo_{$bucket}_{$key}" ); ?>"><code><?php echo esc_html( $this->humanize( $key ) ); ?></code></label>
                        <?php if ( '' !== (string) $default ) : ?>
                            <p class="description" style="margin-top:4px;"><?php echo esc_html( (string) $default ); ?></p>
                        <?php endif; ?>
                    </th>
                    <td>
                        <textarea
                            id="<?php echo esc_attr( "wlaioseo_{$bucket}_{$key}" ); ?>"
                            name="<?php echo esc_attr( "wlaioseo[{$bucket}][{$key}]" ); ?>"
                            rows="2"
                            class="large-text"
                            placeholder="<?php echo esc_attr( (string) $default ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Turn a localized key (searchAppearance_global_siteTitle) into a readable label.
     */
    private function humanize( string $key ): string {
        return ucwords( str_replace( '_', ' ', $key ) );
    }
}
