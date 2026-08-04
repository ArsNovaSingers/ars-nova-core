<?php
/**
 * Production Template — the single, reusable front-end template for every
 * `production` (concert) post, registered from this plugin so it survives
 * theme changes the same way the CPTs and ACF fields do (v1.4.0, 2026-08-03).
 *
 * WHY THIS EXISTS
 * ----------------
 * Concert pages were previously hand-built per event as one-off Gutenberg
 * block markup with hardcoded inline colors/spacing on every block. Every
 * new concert was a fresh design project, and a site-wide change (reorder a
 * section, fix a color) had to be repeated by hand on every page — exactly
 * what this template exists to stop. One PHP file now owns the structure and
 * styling for ALL productions; editors fill in ACF fields (dates, ticket
 * info, story, featured artist), not blocks. Change the template once, every
 * production page updates.
 *
 * HOW IT WORKS
 * ------------
 * Unlike the Season Template (page-templates.php), which delegates to
 * Kadence's own page.php and forces a few Kadence meta values, this ships
 * real markup at templates/single-production.php and calls
 * get_header()/get_footer() directly. The site's normal nav/footer stay in
 * place; everything between them is fully owned by this plugin, so Kadence's
 * per-page title-bar/content-width settings don't apply and don't need to be
 * forced.
 *
 * SECTION ORDER (per 2026-08-03 decision, do not reorder without re-checking
 * with Jonathan): Hero -> Tickets -> Story -> Featured Artist -> Support the
 * Music. The ticket section sits directly under the hero by design. "Support
 * the Music" is hardcoded in the template itself (not an ACF field) because
 * it's identical boilerplate on every production.
 *
 * @package ars-nova-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Swap in the plugin's own template file for every single `production` view.
 * No dropdown entry is needed (unlike the Season Template) — every
 * production uses this one template, there is no per-post choice.
 */
add_filter(
	'template_include',
	function ( $template ) {
		if ( ! is_singular( 'production' ) ) {
			return $template;
		}
		$plugin_template = ARS_NOVA_CORE_DIR . 'templates/single-production.php';
		return file_exists( $plugin_template ) ? $plugin_template : $template;
	}
);

/**
 * Body class, so the template can be targeted in CSS without relying on a
 * post ID.
 */
add_filter(
	'body_class',
	function ( $classes ) {
		if ( is_singular( 'production' ) ) {
			$classes[] = 'ans-production-template';
		}
		return $classes;
	}
);
