<?php
/**
 * Plugin Name:       Ars Nova Core
 * Plugin URI:        https://arsnovasingers.org
 * Description:       Site-specific content structure for Ars Nova Singers — custom post types (Productions, People, Callouts), the ACF options page, ACF field groups, and image sizes. Kept in a plugin (not the theme) so the site's data layer survives any theme change (StageHand → Kadence). Content registrations lifted verbatim from the StageHand theme.
 * Version:           1.0.0
 * Author:            Ars Nova Singers
 * Author URI:        https://arsnovasingers.org
 * License:           GPL-2.0-or-later
 * Text Domain:       ars-nova-core
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARS_NOVA_CORE_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Content structure carried over from the StageHand theme.
 * These define WHAT the content is (data layer) — not how it looks.
 */
require_once ARS_NOVA_CORE_DIR . 'includes/custom-post-types.php';
require_once ARS_NOVA_CORE_DIR . 'includes/acf-options.php';
require_once ARS_NOVA_CORE_DIR . 'includes/image-sizes.php';

/**
 * Load ACF field-group definitions from this plugin's acf-json folder, so the
 * field definitions live with the data layer instead of the theme. (Appends to
 * ACF's existing load paths — does not replace the theme's during transition.)
 */
add_filter( 'acf/settings/load_json', function ( $paths ) {
	$paths[] = ARS_NOVA_CORE_DIR . 'acf-json';
	return $paths;
} );

/**
 * Save ACF field-group JSON back into this plugin when fields are edited in the
 * admin, keeping version control in sync. Remove this filter to freeze fields.
 */
add_filter( 'acf/settings/save_json', function ( $path ) {
	return ARS_NOVA_CORE_DIR . 'acf-json';
} );

/**
 * Register CPTs and flush rewrite rules on activation so the
 * production / person / callout permalinks work immediately.
 */
function arsnova_core_activate() {
	arsnova_core_register_cpts();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'arsnova_core_activate' );

function arsnova_core_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'arsnova_core_deactivate' );
