<?php
/**
 * Season Template — a real WordPress page template, registered from this plugin.
 *
 * WHY THIS EXISTS
 * ---------------
 * This site has no page templates at all. Kadence ships none (no template-*.php,
 * no templates directory) and there is no child theme, so the block editor's
 * Template dropdown offers only "Default template". The ~12 choices people see
 * elsewhere in the editor are two different things: the Kadence Design Library
 * (starter patterns — insert-once block content, no ongoing link) and the legacy
 * ACF "Content Blocks" field group (whose front-end templates did not survive the
 * StageHand → Kadence move, so it renders nothing).
 *
 * Registering here rather than in the theme keeps it alive across theme changes,
 * which is the same reason the CPTs and ACF definitions live in this plugin.
 *
 * HOW IT WORKS
 * ------------
 * It does NOT ship its own PHP markup. Writing a standalone template would mean
 * reimplementing Kadence's header, footer and content wrappers and then keeping
 * that copy in step with the theme forever. Instead it renders through Kadence's
 * own page.php and forces the four per-page settings Kadence already understands,
 * by filtering their post meta:
 *
 *   _kad_post_title            = hide       (no page title block)
 *   _kad_post_layout           = fullwidth  (no content-width cage)
 *   _kad_post_content_style    = unboxed    (no white boxed card)
 *   _kad_post_vertical_padding = hide       (hero sits flush under the header)
 *
 * Values are the ones Kadence itself validates in inc/meta/class-theme-meta.php.
 *
 * WHAT IT FIXES
 * -------------
 * On /concerts/this-season/ the Kadence content style was "boxed" — a white card
 * 1242px wide — while every block was alignfull at 1416px and escaped it, so the
 * boxed setting was visually inert but still shaping the container. The cover's
 * inner container also measured 1290px against the groups' 1368px, so nothing
 * lined up vertically down the page. Fullwidth + unboxed removes both.
 *
 * The settings are FORCED, not defaulted: a page on this template ignores its own
 * Kadence sidebar values. Switch the page back to "Default template" to hand
 * control back.
 *
 * @package ars-nova-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template slug. Stored in the page's _wp_page_template meta.
 * No file of this name needs to exist — template_include maps it to Kadence.
 */
define( 'ARS_NOVA_SEASON_TEMPLATE', 'ans-season-template.php' );

/**
 * The Kadence per-page settings this template forces.
 *
 * @return array meta_key => forced value.
 */
function arsnova_season_template_meta() {
	return array(
		'_kad_post_title'            => 'hide',
		'_kad_post_layout'           => 'fullwidth',
		'_kad_post_content_style'    => 'unboxed',
		'_kad_post_vertical_padding' => 'hide',
	);
}

/**
 * Add "Season Template" to the page Template dropdown.
 *
 * WordPress validates a submitted template against this same filtered list, so
 * adding it here is what makes it selectable in both the block editor and REST.
 */
add_filter(
	'theme_page_templates',
	function ( $templates ) {
		$templates[ ARS_NOVA_SEASON_TEMPLATE ] = __( 'Season Template', 'ars-nova-core' );
		return $templates;
	}
);

/**
 * Render pages on this template through the active theme's page.php.
 *
 * Falls through untouched if the theme has no page.php, so switching to a theme
 * that works differently degrades to that theme's default rather than fataling.
 */
add_filter(
	'template_include',
	function ( $template ) {
		if ( ! is_singular( 'page' ) ) {
			return $template;
		}
		if ( ARS_NOVA_SEASON_TEMPLATE !== get_page_template_slug( get_queried_object_id() ) ) {
			return $template;
		}
		$page_template = locate_template( 'page.php' );

		return $page_template ? $page_template : $template;
	}
);

/**
 * Force the Kadence settings for pages using this template.
 *
 * Note on recursion: get_page_template_slug() reads _wp_page_template, which
 * re-enters this filter. That is safe because the isset() check runs FIRST and
 * _wp_page_template is not one of our keys, so it returns $value untouched
 * before any further lookup happens. Do not reorder these two checks.
 *
 * @param mixed  $value     Short-circuit value. Null means "not overridden".
 * @param int    $object_id Post ID.
 * @param string $meta_key  Meta key being read.
 * @param bool   $single    Whether a single value was requested.
 * @return mixed
 */
add_filter(
	'get_post_metadata',
	function ( $value, $object_id, $meta_key, $single ) {
		$forced = arsnova_season_template_meta();

		if ( ! isset( $forced[ $meta_key ] ) ) {
			return $value;
		}
		if ( 'page' !== get_post_type( $object_id ) ) {
			return $value;
		}
		if ( ARS_NOVA_SEASON_TEMPLATE !== get_page_template_slug( $object_id ) ) {
			return $value;
		}

		return $single ? $forced[ $meta_key ] : array( $forced[ $meta_key ] );
	},
	10,
	4
);

/**
 * Body class, so the template can be targeted in CSS without relying on a page ID.
 */
add_filter(
	'body_class',
	function ( $classes ) {
		if ( is_singular( 'page' ) && ARS_NOVA_SEASON_TEMPLATE === get_page_template_slug( get_queried_object_id() ) ) {
			$classes[] = 'ans-season-template';
		}
		return $classes;
	}
);
