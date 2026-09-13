<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * "AIOSEO" tab on the Multilingual → Settings screen: the addon's feature
 * toggles plus AIOSEO's global localizable strings per language. Rendered and
 * saved through the wp-loc settings extension hooks (the form, nonce and
 * redirect belong to wp-loc).
 *
 * The list of translatable keys is read live from AIOSEO
 * (aioseo_options_localized / aioseo_options_dynamic_localized via
 * WP_LOC_AIOSEO_Options::base_localized) and unioned with a small built-in
 * baseline so the most important strings always appear, even before AIOSEO has
 * lazily populated its localized option.
 */
class WP_LOC_AIOSEO_Settings {

    const TAB = 'aioseo';

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
        add_filter( 'wp_loc_settings_tabs', [ $this, 'register_tab' ] );
        add_action( 'wp_loc_settings_render_' . self::TAB, [ $this, 'render_tab' ] );
        add_action( 'wp_loc_settings_save_' . self::TAB, [ $this, 'handle_save' ] );
    }

    public function register_tab( array $tabs ): array {
        $tabs[ self::TAB ] = __( 'AIOSEO', 'wp-loc-aioseo' );

        return $tabs;
    }

    public function handle_save(): void {
        // Feature toggles (checkboxes: present => on).
        update_option( WP_LOC_AIOSEO::SETTINGS_OPTION, [
            'sitemap_alternates'                   => ! empty( $_POST['sitemap_alternates'] ),
            'sitemap_skip_translated_system_pages' => ! empty( $_POST['sitemap_skip_translated_system_pages'] ),
            'seed_translations'                    => ! empty( $_POST['seed_translations'] ),
        ] );

        // String translations for the language selected in the admin top bar.
        $lang = wp_loc_get_admin_lang();

        if ( in_array( $lang, WP_LOC_AIOSEO_Lang::additional_languages(), true ) ) {
            $raw = isset( $_POST['wlaioseo'] ) && is_array( $_POST['wlaioseo'] ) ? wp_unslash( $_POST['wlaioseo'] ) : [];

            WP_LOC_AIOSEO_Options::save_translations( $lang, [
                'main'    => $this->sanitize_bucket( $raw['main'] ?? [] ),
                'dynamic' => $this->sanitize_bucket( $raw['dynamic'] ?? [] ),
            ] );
        }
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

    public function render_tab(): void {
        $additional = WP_LOC_AIOSEO_Lang::additional_languages();
        $lang       = wp_loc_get_admin_lang();

        $main_defaults    = array_merge( self::BASELINE_MAIN, WP_LOC_AIOSEO_Options::base_localized( 'main' ) );
        $dynamic_defaults = WP_LOC_AIOSEO_Options::base_localized( 'dynamic' );

        $sitemap_alternates = (bool) WP_LOC_AIOSEO::setting( 'sitemap_alternates', true );
        $skip_system_pages  = (bool) WP_LOC_AIOSEO::setting( 'sitemap_skip_translated_system_pages', true );
        $seed_translations  = (bool) WP_LOC_AIOSEO::setting( 'seed_translations', true );

        ?>
        <div class="wp-loc-settings-section">
            <h2><?php esc_html_e( 'Options', 'wp-loc-aioseo' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Sitemap hreflang alternates', 'wp-loc-aioseo' ); ?></th>
                    <td><label><input type="checkbox" name="sitemap_alternates" value="1" <?php checked( $sitemap_alternates ); ?> /> <?php esc_html_e( 'Add per-language alternate links to the XML sitemap', 'wp-loc-aioseo' ); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Translated shop pages', 'wp-loc-aioseo' ); ?></th>
                    <td><label><input type="checkbox" name="sitemap_skip_translated_system_pages" value="1" <?php checked( $skip_system_pages ); ?> /> <?php esc_html_e( 'Keep translated Cart, Checkout and My account pages out of the XML sitemap', 'wp-loc-aioseo' ); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Seed new translations', 'wp-loc-aioseo' ); ?></th>
                    <td><label><input type="checkbox" name="seed_translations" value="1" <?php checked( $seed_translations ); ?> /> <?php esc_html_e( 'Copy structural SEO fields from the source when a translation is created', 'wp-loc-aioseo' ); ?></label></td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Global strings', 'wp-loc-aioseo' ); ?></h2>

            <?php if ( empty( $additional ) ) : ?>
                <p><?php esc_html_e( 'Add at least one non-default language in WP-LOC to translate AIOSEO strings.', 'wp-loc-aioseo' ); ?></p>
            <?php elseif ( ! in_array( $lang, $additional, true ) ) : ?>
                <p><?php esc_html_e( 'Switch to a non-default language in the admin top bar to translate AIOSEO global strings for that language.', 'wp-loc-aioseo' ); ?></p>
            <?php else :
                $translations = WP_LOC_AIOSEO_Options::get_translations( $lang );
                ?>
                <p class="description"><?php
                    /* translators: %s: language code */
                    printf( esc_html__( 'Editing translations for %s. Leave a field empty to fall back to the default-language value. Tags like #site_title and #separator_sa are AIOSEO smart tags and should stay as-is.', 'wp-loc-aioseo' ), '<code>' . esc_html( $lang ) . '</code>' );
                ?></p>
                <?php
                $this->render_bucket( __( 'General', 'wp-loc-aioseo' ), 'main', $main_defaults, $translations['main'] );
                if ( ! empty( $dynamic_defaults ) ) {
                    $this->render_bucket( __( 'Post types & taxonomies', 'wp-loc-aioseo' ), 'dynamic', $dynamic_defaults, $translations['dynamic'] );
                }
            endif; ?>
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
