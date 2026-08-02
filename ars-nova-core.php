<?php
/**
 * Plugin Name:       Ars Nova Core
 * Plugin URI:        https://arsnovasingers.org
 * Description:       Site-specific content structure for Ars Nova Singers — custom post types (Productions, People, Callouts), the ACF options page, ACF field groups, image sizes, and plugin-registered page templates. Kept in a plugin (not the theme) so the site's data layer survives any theme change (StageHand → Kadence). Content registrations lifted verbatim from the StageHand theme.
 * Version:           1.3.1
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
 * Page templates registered from the plugin rather than the theme, so they
 * survive a theme change for the same reason the CPTs above do.
 */
require_once ARS_NOVA_CORE_DIR . 'includes/page-templates.php';

/**
 * People: the `ans_people_group` taxonomy (leadership / board), the per-person
 * meta and admin meta box, and the [ans_people] shortcode that drives the
 * public Leadership page.
 *
 * Kept strictly separate from the Singers Portal — see includes/people.php.
 */
require_once ARS_NOVA_CORE_DIR . 'includes/people.php';

/**
 * REST route for bulk People upsert, in the ars-nova/v1 namespace so it is
 * reachable via the connector's ans_rest_call with no connector rebuild.
 */
require_once ARS_NOVA_CORE_DIR . 'includes/people-rest.php';

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
	arsnova_core_register_people_taxonomy();
	arsnova_core_seed_people_groups();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'arsnova_core_activate' );

function arsnova_core_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'arsnova_core_deactivate' );
