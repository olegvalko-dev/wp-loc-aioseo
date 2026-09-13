# WP-LOC AIOSEO

All in One SEO Pack (Pro) multilingual integration for [WP-LOC](https://wp-loc.com/). Companion to the `wp-loc-woocommerce` and `wp-loc-multicurrency` addons.

## Requirements

- WordPress 6.5+
- [wp-loc](https://github.com/olegvalko-dev/wp-loc)
- All in One SEO Pack (Lite or Pro)

## What it does

WP-LOC duplicates posts/terms per language, and AIOSEO stores per-post/term SEO in its own tables keyed by ID — so **per-post and per-term SEO (title, description, OG/Twitter, canonical, robots, schema) is already separate per language**. You edit it in each translation's AIOSEO sidebar; no configuration needed.

This addon fills the gaps that aren't automatic:

- **Global string translation**: AIOSEO's site-wide strings (homepage / archive / author / date / search title & description templates, separators, paged format, breadcrumb formats, social homepage OG, per-post-type & per-taxonomy templates) live in a single options blob and render the same in every language. The addon serves per-language values for them, edited on **Multilingual → Settings → AIOSEO**.
- **Sitemap hreflang**: adds `<xhtml:link rel="alternate" hreflang>` alternates (incl. `x-default`) to AIOSEO's XML sitemap, grouping translated URLs. Toggleable.
- **New-translation seeding**: when a translation is created, copies structural SEO fields (robots, OG/Twitter object & image type, schema) from the source, leaving text blank for translation; remaps custom social images to the translated attachment. Toggleable.

## Settings

`Multilingual → Settings → AIOSEO` tab in wp-admin — toggles for sitemap hreflang and new-translation seeding, plus AIOSEO global SEO strings for the language selected in the admin top bar (requires the wp-loc settings extension hooks).

## Architecture

The addon never patches wp-loc or AIOSEO — it only consumes their public filters and models. All WP-LOC API access is funnelled through `WP_LOC_AIOSEO_Lang`; classes are prefixed `WP_LOC_AIOSEO_`, one module per file in `includes/`.

- **Global strings**: AIOSEO's options getter returns localizable values from the public `$localized` map on its Options objects (loaded from `aioseo_options_localized` / `aioseo_options_dynamic_localized`). On `template_redirect` — after WP-LOC has resolved the request language — the addon overwrites that in-memory map with the current language's values (`class-wp-loc-aioseo-options.php`). Nothing is persisted, so AIOSEO's canonical settings are never touched.
- **Sitemap**: attaches `$entry['languages']` via the public `aioseo_sitemap_post` / `aioseo_sitemap_term` filters; AIOSEO's sitemap XML view renders them as hreflang regardless of which multilingual plugin is active (`class-wp-loc-aioseo-sitemap.php`).
- **Seeding**: uses AIOSEO's `Models\Post` / `Pro\Models\Term` active records on `save_post` (creation only) and `created_term` (`class-wp-loc-aioseo-seed.php`).

## Install

Standalone plugin — clone into `web/app/plugins/wp-loc-aioseo/` and activate. Not distributed via Composer.
