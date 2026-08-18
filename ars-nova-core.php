<?php
/**
 * Plugin Name:       Ars Nova Core
 * Plugin URI:        https://arsnovasingers.org
 * Description:       Site-specific content structure for Ars Nova Singers — custom post types (Productions, People, Callouts), the ACF options page, ACF field groups, image sizes, and plugin-registered page templates. Kept in a plugin (not the theme) so the site's data layer survives any theme change (StageHand → Kadence). Content registrations lifted verbatim from the StageHand theme.
 * Version:           1.9.0
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
 * Keep this in step with the `Version:` header above and the release tag —
 * see claude/plugins/Ars_Nova_Plugin_Build_Rules.md rule 2. Added in 1.4.0;
 * before that the plugin had no internal version constant at all.
 */
define( 'ARS_NOVA_CORE_VERSION', '1.9.0' );

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
 * Project Template — the shared hero + Support band for every concert /
 * residency page, plus its scoped stylesheet. Depends on page-templates.php
 * being loaded FIRST: it reuses arsnova_season_template_meta() for the four
 * forced Kadence settings rather than duplicating them.
 */
require_once ARS_NOVA_CORE_DIR . 'includes/project-template.php';

/**
 * Project Modules — the per-page library of blended title/text/image sections
 * (Program Story, Guest Artist, Tickets) that concert pages assemble and
 * reorder. Absorbed in 1.9.0 from the standalone ars-nova-project-modules
 * plugin, which is retired; deactivate and delete it, or its copy of these
 * functions fatals on redeclare.
 *
 * Must load AFTER project-template.php: it calls arsnova_is_project_template()
 * from that file, and it hooks the_content at priority 8 so its output is
 * wrapped by the Project Template's own filter at 9.
 */
require_once ARS_NOVA_CORE_DIR . 'includes/project-modules.php';

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
 * Cart total block (WEB-24). Registers [ans_cart_total] — the WooCommerce cart
 * subtotal as a placeable block, dropped into Kadence's HTML / Mobile HTML
 * header element by hand. Not auto-attached to anything. Guarded internally on
 * WooCommerce being present.
 */
require_once ARS_NOVA_CORE_DIR . 'includes/header-cart-subtotal.php';

/**
 * Font Vote — the [ans_font_vote] site typography review page and its
 * ars-nova/v1/font-vote/* REST routes (state, options, vote), all state
 * persisted in one wp_option row. Internal team tool, not attached to any
 * menu — see includes/font-vote.php for the deliberately-public-route
 * rationale.
 *
 * font-catalog.php has to load first — it supplies the Google Fonts family
 * list the Font Vote picker is built on, plus the Settings -> Font Vote Fonts
 * page for the (optional) Google Fonts API key.
 */
require_once ARS_NOVA_CORE_DIR . 'includes/font-catalog.php';
require_once ARS_NOVA_CORE_DIR . 'includes/current-site.php';
require_once ARS_NOVA_CORE_DIR . 'includes/font-vote.php';

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
